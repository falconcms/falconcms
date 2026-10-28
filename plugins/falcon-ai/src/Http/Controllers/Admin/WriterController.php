<?php

namespace FalconAi\Http\Controllers\Admin;

use FalconAi\Exceptions\AiException;
use FalconAi\Http\Requests\MetaRequest;
use FalconAi\Http\Requests\OutlineRequest;
use FalconAi\Http\Requests\RewriteRequest;
use FalconAi\Http\Requests\SectionRequest;
use FalconAi\Http\Requests\SeoSuggestRequest;
use FalconAi\Prompts\ArticlePrompts;
use FalconAi\Support\AiManager;
use FalconAi\Support\HtmlSanitizer;
use FalconAi\Support\SeoWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Write with AI in the post editor, and SEO suggestions in its SEO box.
 *
 * Nothing is saved here: the answers go back to the editor, where the author decides what to
 * keep and saves the post as usual. Every piece of HTML is cleaned before it is sent back.
 */
class WriterController extends Controller
{
    public function __construct(private AiManager $ai) {}

    /** Step 1: a title and the sections, for the author to edit. */
    public function outline(OutlineRequest $request): JsonResponse
    {
        return $this->answer(function () use ($request) {
            $data = $this->ai->json(ArticlePrompts::outline($request->validated()))->data ?? [];
            $sections = [];
            foreach (array_slice((array) ($data['sections'] ?? []), 0, 12) as $section) {
                $heading = $this->line($section['heading'] ?? '', 200);
                if ($heading !== '') {
                    $sections[] = [
                        'heading' => $heading,
                        'points' => array_values(array_filter(array_map(fn ($p) => $this->line($p, 300), array_slice((array) ($section['points'] ?? []), 0, 8)))),
                    ];
                }
            }
            if ($sections === []) {
                throw new AiException('No outline came back. Try again, or describe the topic a little more.');
            }

            return ['title' => $this->line($data['title'] ?? '', 300), 'sections' => $sections];
        });
    }

    /**
     * Step 2: one section, streamed as server-sent events:
     *   event: text   {"text": "…"}   raw text as it is written (shown as a preview, never as HTML)
     *   event: done   {"html": "…"}   the section, cleaned HTML, ready for the editor
     *   event: error  {"message": "…"}
     */
    public function section(SectionRequest $request): StreamedResponse
    {
        $data = $request->validated();
        $headings = array_values($data['headings']);
        $index = (int) $data['index'];
        $aiRequest = ArticlePrompts::section($data, $data['title'], $headings, $index, $headings[$index], array_values($data['points'] ?? []));

        // The session is not needed while the text streams; closing it keeps other requests
        // from this browser from waiting on it.
        $request->session()->save();

        return response()->stream(function () use ($aiRequest) {
            // Each event goes out as soon as it is written (the way Laravel's own eventStream
            // sends): pushed through any output buffer, then to the web server.
            $send = function (string $event, array $payload) {
                echo 'event: '.$event."\n".'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                $result = $this->ai->stream($aiRequest, fn (string $text) => $send('text', ['text' => $text]));
                $send('done', ['html' => HtmlSanitizer::clean($result->text)]);
            } catch (AiException $e) {
                $send('error', ['message' => $e->getMessage()]);
            } catch (Throwable $e) {
                report($e);
                $send('error', ['message' => 'Something went wrong while writing. Try again.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store',
            // Nginx and some proxies hold a response back until it ends unless told not to.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Step 3: SEO title, meta description, excerpt and tags. */
    public function meta(MetaRequest $request): JsonResponse
    {
        return $this->answer(function () use ($request) {
            $data = $request->validated();
            $text = trim(falcon_content_text($data['content']));
            if ($text === '') {
                throw new AiException('Write (or generate) the article first.');
            }
            $answer = $this->ai->json(ArticlePrompts::meta($data['title'], $text, $data['keyword'] ?? null, $data['language'] ?? null))->data ?? [];

            return [
                'seo_title' => $this->line($answer['seo_title'] ?? '', 120),
                'meta_description' => $this->line($answer['meta_description'] ?? '', 320),
                'excerpt' => $this->line($answer['excerpt'] ?? '', 600),
                'tags' => array_values(array_unique(array_filter(array_map(fn ($t) => $this->line($t, 60), array_slice((array) ($answer['tags'] ?? []), 0, 8))))),
            ];
        });
    }

    /** Improve the text selected in the editor. */
    public function rewrite(RewriteRequest $request): JsonResponse
    {
        return $this->answer(function () use ($request) {
            $data = $request->validated();
            $answer = $this->ai->json(ArticlePrompts::rewrite($data['html'], $data['action'], $data['language'] ?? null))->data ?? [];
            $html = HtmlSanitizer::clean((string) ($answer['html'] ?? ''));
            if ($html === '') {
                throw new AiException('Nothing came back. Try again.');
            }

            return ['html' => $html];
        });
    }

    /** The editor's SEO box: three SEO title or meta description options. */
    public function seoSuggest(SeoSuggestRequest $request): JsonResponse
    {
        return $this->answer(function () use ($request) {
            $data = $request->validated();

            return ['options' => SeoWriter::suggest($data['field'], [
                'title' => (string) ($data['title'] ?? ''),
                'content' => (string) ($data['content'] ?? ''),
                'keyword' => (string) ($data['keyword'] ?? ''),
                'current' => (string) ($data['current'] ?? ''),
            ])];
        });
    }

    /** Run, and answer a failure with its message (422) instead of a server error. */
    private function answer(callable $work): JsonResponse
    {
        try {
            return response()->json($work());
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** One line of plain text: no tags, no line breaks, at most $max characters. */
    private function line($value, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return mb_substr($text, 0, $max);
    }
}
