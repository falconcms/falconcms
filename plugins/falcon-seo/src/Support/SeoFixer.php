<?php

namespace FalconSeo\Support;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Revision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-click fixes for what Site Analysis finds (Pro): a missing meta description or SEO title,
 * images without alt text, links to pages that are gone.
 *
 * Each fix is asked for by someone, page by page or "fix all", and changes only what the
 * problem is about. Before a page's content changes a revision is kept, so it can be put
 * back. Written text never replaces what an author wrote unless AI was asked to rewrite it.
 */
final class SeoFixer
{
    /** The problems a fix exists for. */
    public const FIXABLE = ['description', 'title', 'alt', 'broken_links'];

    /**
     * @param  string  $method  "auto" (from the page itself) or "ai" (written by Claude)
     * @return array{fixed: bool, message: string}
     */
    public static function fix(Post $post, string $check, string $method = 'auto'): array
    {
        try {
            return match ($check) {
                'description' => self::description($post, $method),
                'title' => self::title($post, $method),
                'alt' => self::alt($post, $method),
                'broken_links' => self::brokenLinks($post),
                default => ['fixed' => false, 'message' => 'Nothing to fix automatically here.'],
            };
        } catch (Throwable $e) {
            return ['fixed' => false, 'message' => $e->getMessage()];
        }
    }

    private static function description(Post $post, string $method): array
    {
        $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
        $current = trim((string) ($seo['description'] ?? ''));

        if ($method === 'ai') {
            $options = AiWriter::suggest('description', self::page($post, $current));
            $text = $options[0]['text'] ?? '';
        } else {
            if ($current !== '') {
                return ['fixed' => false, 'message' => 'It has a description already: only AI rewrites one an author wrote.'];
            }
            $text = self::sentenceCut(falcon_content_text((string) ($post->excerpt ?: $post->content)), 155);
        }
        if ($text === '') {
            return ['fixed' => false, 'message' => 'The page has no text to write a description from.'];
        }
        self::saveSeo($post, ['description' => $text]);

        return ['fixed' => true, 'message' => 'Description set: '.$text];
    }

    private static function title(Post $post, string $method): array
    {
        if ($method !== 'ai') {
            return ['fixed' => false, 'message' => 'A better title needs AI (or your own words in the editor).'];
        }
        $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
        $text = AiWriter::suggest('title', self::page($post, (string) ($seo['title'] ?? '')))[0]['text'] ?? '';
        if ($text === '') {
            return ['fixed' => false, 'message' => 'No title came back.'];
        }
        self::saveSeo($post, ['title' => $text]);

        return ['fixed' => true, 'message' => 'SEO title set: '.$text];
    }

    /** Alt text for every image in the content that has none: from AI, or from its file name. */
    private static function alt(Post $post, string $method): array
    {
        $content = (string) $post->content;
        $builder = self::isBuilder($content);
        $images = $builder ? self::builderImages(json_decode($content, true)) : self::htmlImages($content);
        if ($images === []) {
            return ['fixed' => false, 'message' => 'Every image in the content has alt text (images the theme adds are edited in the theme).'];
        }

        $alts = $method === 'ai' ? AiWriter::altTexts($images, self::page($post)) : [];
        foreach ($images as $image) {
            $alts[$image['src']] ??= self::altFromFileName($image['src']);
        }
        $alts = array_filter($alts);

        $updated = $builder ? self::setBuilderAlts($content, $alts) : self::setHtmlAlts($content, $alts);
        if ($updated === $content) {
            return ['fixed' => false, 'message' => 'No alt text could be written.'];
        }
        self::saveContent($post, $updated);

        return ['fixed' => true, 'message' => count($alts).' image(s) given alt text.'];
    }

    /** Links to addresses Site Analysis found broken lose the link and keep their words. */
    private static function brokenLinks(Post $post): array
    {
        $issues = json_decode((string) DB::table(SiteScan::TABLE)->where('post_id', $post->id)->value('issues'), true) ?: [];
        $urls = [];
        foreach ($issues as $issue) {
            if ($issue['id'] === 'broken_links') {
                foreach ($issue['items'] as $item) {
                    $urls[] = trim(explode(' — ', (string) $item)[0]);
                }
            }
        }
        if ($urls === []) {
            return ['fixed' => false, 'message' => 'No broken links recorded for this page. Check it again first.'];
        }

        $content = (string) $post->content;
        $unlink = function (string $html) use ($urls): string {
            return preg_replace_callback('#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a\s*>#is', function ($m) use ($urls) {
                $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                foreach ($urls as $url) {
                    if ($href === $url || rtrim($href, '/') === rtrim($url, '/') || ($href !== '' && $href[0] === '/' && str_ends_with(rtrim($url, '/'), rtrim($href, '/')))) {
                        return $m[3];
                    }
                }

                return $m[0];
            }, $html) ?? $html;
        };
        $updated = self::isBuilder($content) ? self::mapBuilderStrings($content, $unlink) : $unlink($content);
        if ($updated === $content) {
            return ['fixed' => false, 'message' => 'The broken links are not in the page\'s own content (the theme or a widget adds them).'];
        }
        self::saveContent($post, $updated);

        return ['fixed' => true, 'message' => 'Removed the link from '.count($urls).' broken address(es); their words stay.'];
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private static function page(Post $post, string $current = ''): array
    {
        $seo = is_array($post->seo_meta) ? $post->seo_meta : [];

        return ['title' => (string) $post->title, 'content' => (string) $post->content, 'keyword' => (string) ($seo['focus_keyword'] ?? ''), 'current' => $current];
    }

    private static function saveSeo(Post $post, array $values): void
    {
        $post->seo_meta = array_merge(is_array($post->seo_meta) ? $post->seo_meta : [], $values);
        $post->save();
    }

    private static function saveContent(Post $post, string $content): void
    {
        try {
            Revision::snapshot($post, 'revision');
        } catch (Throwable $e) {
            report($e);
        }
        $post->content = $content;
        $post->save();
    }

    private static function isBuilder(string $content): bool
    {
        $trimmed = ltrim($content);

        return (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) && is_array(json_decode($content, true));
    }

    /** @return list<array{src: string, context: string}> */
    private static function htmlImages(string $html): array
    {
        $out = [];
        preg_match_all('#<img\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$tag, $offset]) {
            if (preg_match('#\balt\s*=\s*(["\'])\s*\S#i', $tag) || !preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag, $src)) {
                continue;
            }
            $context = falcon_html_to_text(substr($html, max(0, $offset - 400), 800));
            $out[] = ['src' => html_entity_decode($src[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'context' => $context];
        }

        return $out;
    }

    private static function setHtmlAlts(string $html, array $alts): string
    {
        return preg_replace_callback('#<img\b[^>]*>#i', function ($m) use ($alts) {
            $tag = $m[0];
            if (preg_match('#\balt\s*=\s*(["\'])\s*\S#i', $tag) || !preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag, $src)) {
                return $tag;
            }
            $alt = $alts[html_entity_decode($src[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')] ?? null;
            if ($alt === null) {
                return $tag;
            }
            $tag = preg_replace('#\s+alt\s*=\s*(["\'])\s*\1#i', '', $tag) ?? $tag;

            return preg_replace('#^<img\b#i', '<img alt="'.e($alt).'"', $tag, 1) ?? $tag;
        }, $html) ?? $html;
    }

    /** Builder image elements keep their image as settings: {"url": "…", "alt": ""}. */
    private static function builderImages($node, string $context = ''): array
    {
        $out = [];
        if (!is_array($node)) {
            return $out;
        }
        $url = $node['url'] ?? $node['src'] ?? null;
        if (is_string($url) && preg_match('#\.(jpe?g|png|webp|gif|avif|svg)(\?|$)#i', $url) && array_key_exists('alt', $node) && trim((string) $node['alt']) === '') {
            $out[] = ['src' => $url, 'context' => $context];
        }
        foreach ($node as $value) {
            $out = array_merge($out, self::builderImages($value, $context));
        }

        return $out;
    }

    private static function setBuilderAlts(string $content, array $alts): string
    {
        $data = json_decode($content, true);
        $walk = function (&$node) use (&$walk, $alts) {
            if (!is_array($node)) {
                return;
            }
            $url = $node['url'] ?? $node['src'] ?? null;
            if (is_string($url) && isset($alts[$url]) && array_key_exists('alt', $node) && trim((string) $node['alt']) === '') {
                $node['alt'] = $alts[$url];
            }
            foreach ($node as &$child) {
                $walk($child);
            }
        };
        $walk($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Apply $fn to every string in builder JSON (the HTML of text blocks lives in strings). */
    private static function mapBuilderStrings(string $content, callable $fn): string
    {
        $data = json_decode($content, true);
        array_walk_recursive($data, function (&$value) use ($fn) {
            if (is_string($value) && str_contains($value, '<a')) {
                $value = $fn($value);
            }
        });
        $updated = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return json_decode($updated, true) == json_decode($content, true) ? $content : $updated;
    }

    /** "green-tea_cup-2.jpg" → "Green tea cup" */
    private static function altFromFileName(string $src): string
    {
        $name = pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_FILENAME);
        $name = preg_replace(['/[-_]+/', '/\b(img|image|dsc|photo|pic|screenshot)\b/i', '/\b\d+(x\d+)?\b/', '/\b[0-9a-f]{8,}\b/i', '/\s+/'], [' ', ' ', ' ', ' ', ' '], $name) ?? $name;
        $name = trim($name);

        return $name === '' ? '' : Str::ucfirst(mb_strtolower($name));
    }

    /** Up to $limit characters, cut at the end of a sentence or a word. */
    private static function sentenceCut(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $sentence = max((int) mb_strrpos($cut, '. '), (int) mb_strrpos($cut, '। '));
        if ($sentence > $limit * 0.6) {
            return mb_substr($cut, 0, $sentence + 1);
        }

        return rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')), ' ,;:-').'…';
    }
}
