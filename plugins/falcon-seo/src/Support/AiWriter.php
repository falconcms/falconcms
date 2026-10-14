<?php

namespace FalconSeo\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use RuntimeException;
use Throwable;

/**
 * SEO writing with Claude (Pro): titles, meta descriptions and image alt text, each written
 * for the page's own content and focus keyword, a few to choose from with the reason for each.
 *
 * One request per suggestion, with the answer held to a JSON schema. Nothing is saved here:
 * the editor fills a field, or a one-click fix writes it, only when someone chooses to.
 */
final class AiWriter
{
    /** Content sent along: enough for the gist of a long page without paying for all of it. */
    private const CONTENT_CHARS = 12000;

    /** Images described in one request. */
    public const MAX_IMAGES = 20;

    public static function available(): bool
    {
        return Pro::active() && Pro::claudeKey() !== '' && class_exists(Client::class);
    }

    /**
     * SEO title or meta description options.
     *
     * @param  array{title?: string, content?: string, keyword?: string, current?: string, site?: string}  $page
     * @return list<array{text: string, why: string}>
     */
    public static function suggest(string $field, array $page): array
    {
        if (!in_array($field, ['title', 'description'], true)) {
            throw new RuntimeException('Unknown field.');
        }
        $rules = $field === 'title'
            ? 'Write 3 SEO titles. Each 45 to 60 characters, the focus keyword near the start if it reads naturally, specific to this page, no clickbait, no quotes. Do not add the site name.'
            : 'Write 3 meta descriptions. Each 130 to 155 characters, say what the reader gets from the page, include the focus keyword once if it fits naturally, end with a reason to click. No quotes.';

        $answer = self::ask(
            "You write search snippets for web pages. {$rules} Write in the same language as the page. For each, say in one short sentence why it works.",
            self::pageText($page),
            ['type' => 'object', 'properties' => ['options' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['text' => ['type' => 'string'], 'why' => ['type' => 'string']],
                'required' => ['text', 'why'], 'additionalProperties' => false,
            ]]], 'required' => ['options'], 'additionalProperties' => false],
            1200,
        );

        return array_values(array_filter(array_map(fn ($o) => ['text' => trim((string) ($o['text'] ?? '')), 'why' => trim((string) ($o['why'] ?? ''))],
            (array) ($answer['options'] ?? [])), fn ($o) => $o['text'] !== ''));
    }

    /**
     * Alt text for images, from their file names and the text around them.
     *
     * @param  list<array{src: string, context: string}>  $images
     * @return array<string, string> src => alt
     */
    public static function altTexts(array $images, array $page = []): array
    {
        $images = array_slice(array_values($images), 0, self::MAX_IMAGES);
        if ($images === []) {
            return [];
        }
        $list = implode("\n", array_map(fn ($i, $n) => ($n + 1).'. file: '.basename((string) parse_url($i['src'], PHP_URL_PATH))
            .' | near: '.mb_substr(trim($i['context']), 0, 300), $images, array_keys($images)));

        $answer = self::ask(
            'You write alt text for images on a web page, for screen readers and image search. For each image, one plain sentence under 120 characters saying what it most likely shows, judged from its file name and the text near it, in the language of the page. Do not start with "Image of". Use the focus keyword only where it truly describes the image.',
            self::pageText($page)."\n\nImages:\n".$list,
            ['type' => 'object', 'properties' => ['alts' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['number' => ['type' => 'integer'], 'alt' => ['type' => 'string']],
                'required' => ['number', 'alt'], 'additionalProperties' => false,
            ]]], 'required' => ['alts'], 'additionalProperties' => false],
            2000,
        );

        $out = [];
        foreach ((array) ($answer['alts'] ?? []) as $row) {
            $i = (int) ($row['number'] ?? 0) - 1;
            $alt = trim((string) ($row['alt'] ?? ''));
            if (isset($images[$i]) && $alt !== '') {
                $out[$images[$i]['src']] = mb_substr($alt, 0, 200);
            }
        }

        return $out;
    }

    private static function pageText(array $page): string
    {
        $content = mb_substr(trim(falcon_content_text((string) ($page['content'] ?? ''))), 0, self::CONTENT_CHARS);

        return implode("\n", array_filter([
            'Site: '.($page['site'] ?? (string) get_cms_option('site_title', '')),
            'Page title: '.($page['title'] ?? ''),
            !empty($page['keyword']) ? 'Focus keyword: '.$page['keyword'] : 'Focus keyword: (none set)',
            !empty($page['current']) ? 'Current text: '.$page['current'] : null,
            "Page content:\n".($content !== '' ? $content : '(empty)'),
        ]));
    }

    /**
     * One request, answered as JSON matching $schema. Errors come back as a RuntimeException
     * with a message an editor can act on.
     */
    private static function ask(string $system, string $prompt, array $schema, int $maxTokens): array
    {
        if (!self::available()) {
            throw new RuntimeException('AI writing needs FalconCMS Pro and a Claude API key (SEO → Settings → AI writing).');
        }

        try {
            $client = new Client(apiKey: Pro::claudeKey(), requestOptions: self::requestOptions());
            $message = $client->beta->messages->create(
                model: Pro::claudeModel(),
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                // Short, well-defined writing: low effort is plenty and keeps it quick and cheap.
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                // When the model declines for a policy reason, the API retries on its default substitute.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new RuntimeException('Claude did not accept the API key. Check it in SEO → Settings → AI writing.');
        } catch (RateLimitException $e) {
            throw new RuntimeException('Claude is busy or the account limit was reached. Try again in a minute.');
        } catch (BadRequestException $e) {
            $reason = self::reason($e);
            throw new RuntimeException(str_contains(strtolower($reason), 'credit balance')
                ? 'The Anthropic account has no credit left. Add some at console.anthropic.com → Plans & Billing, then try again.'
                : 'Claude could not take this request: '.$reason);
        } catch (APIStatusException $e) {
            throw new RuntimeException('Claude did not answer ('.self::reason($e).'). Try again shortly.');
        } catch (APIConnectionException $e) {
            throw new RuntimeException('Claude could not be reached from this server. Check its internet access.');
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Claude declined to write this one. Try rewording the page or write it yourself.');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        throw new RuntimeException($message->stopReason === 'max_tokens' ? 'The answer was cut short. Try again.' : 'Claude gave no usable answer. Try again.');
    }

    /** The API's own sentence from an error ("Your credit balance is too low…"), not the raw response. */
    private static function reason(Throwable $e): string
    {
        $text = $e->getMessage();
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $text, $m)) {
            return (string) json_decode('"'.$m[1].'"');
        }

        return trim(strtok($text, "\n") ?: $text);
    }

    /** Short timeouts for an editor waiting on a button; tests swap the transport in. */
    private static function requestOptions(): RequestOptions
    {
        $transporter = null;
        try {
            $transporter = app()->bound('falcon-seo.ai.transporter') ? app('falcon-seo.ai.transporter') : null;
        } catch (Throwable $e) {
        }

        return RequestOptions::with(timeout: 60.0, maxRetries: 1, transporter: $transporter);
    }
}
