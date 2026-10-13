<?php

namespace FalconSeo\Support;

use FalconCms\Core\Models\Post;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One page's SEO check-up, with a score out of 100.
 *
 * Reads the page as a visitor gets it: the published page itself, fetched over HTTP, so the
 * theme's own heading, images and links count too. A page that is not published (or not saved
 * yet) is read from the editor instead, with the content and SEO fields as they stand.
 *
 * Each check is good, warning or error (info is shown but not scored). The score is the
 * weighted share of checks passed, a warning counting half.
 */
final class PageAnalyzer
{
    /** Links checked for being broken, per analysis: enough for a page, quick enough to wait for. */
    public const LINK_CHECK_LIMIT = 40;

    /** An image heavier than this slows the page noticeably on a phone. */
    public const HEAVY_IMAGE_BYTES = 300 * 1024;

    private const GROUPS = [
        'keyword' => 'Focus keyword',
        'content' => 'Content & headings',
        'meta' => 'Title, description & search appearance',
        'images' => 'Images',
        'links' => 'Links',
    ];

    /** How much each check weighs in the score. */
    private const WEIGHT = [
        'h1' => 3, 'title' => 3, 'description' => 3, 'broken_links' => 3, 'noindex' => 3,
        'kw_title' => 3, 'kw_h1' => 2, 'kw_intro' => 2, 'kw_description' => 2, 'kw_slug' => 1, 'kw_subheadings' => 1,
        'kw_density' => 2, 'kw_alt' => 1, 'kw_set' => 2,
        'words' => 2, 'heading_order' => 1, 'subheadings' => 1, 'long_paragraphs' => 1, 'sentences' => 1,
        'canonical' => 1, 'og_image' => 1, 'schema' => 1, 'lang' => 1, 'viewport' => 2,
        'alt' => 2, 'image_dimensions' => 1, 'heavy_images' => 1, 'internal_links' => 2, 'empty_links' => 1,
    ];

    /**
     * The page's HTML as a visitor gets it, or null when it cannot be fetched (not published,
     * maintenance mode, a server that cannot reach itself).
     *
     * @return array{html: string, url: string}|null
     */
    public static function fetchLive(Post $post): ?array
    {
        if ($post->status !== 'published') {
            return null;
        }
        $url = (string) get_falcon_permalink($post);
        try {
            $response = Http::timeout(15)->withHeaders(['User-Agent' => 'FalconSEO-PageAnalysis/1.0'])->get($url);
        } catch (Throwable $e) {
            return null;
        }
        if (!$response->successful() || stripos((string) $response->header('Content-Type'), 'html') === false) {
            return null;
        }

        return ['html' => (string) $response->body(), 'url' => $url];
    }

    /**
     * A stand-in page built from the editor: the SEO title and description in the head, the
     * post title as its H1 (as the default theme prints it) and the content below.
     *
     * @param  array{title?: string, seo_title?: string, description?: string, content?: string}  $input
     */
    public static function editorPage(array $input): string
    {
        $content = (string) ($input['content'] ?? '');
        $trimmed = ltrim($content);
        // Builder pages keep their layout as JSON: only their words can be read without rendering them.
        $body = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[{')
            ? '<p>'.e(falcon_content_text($content)).'</p>'
            : (string) do_falcon_shortcode($content);
        $title = trim((string) ($input['seo_title'] ?? '')) ?: trim((string) ($input['title'] ?? ''));

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.e($title).'</title>'
            .'<meta name="description" content="'.e((string) ($input['description'] ?? '')).'"></head>'
            .'<body><main><h1>'.e((string) ($input['title'] ?? '')).'</h1>'.$body.'</main></body></html>';
    }

    /**
     * @param  array{url?: string, keyword?: string, slug?: string, mode?: string, check_links?: bool}  $context
     * @return array{score: int, grade: string, mode: string, url: string, stats: array<string, int>, groups: list<array{id: string, label: string, checks: list<array>}>}
     */
    public static function analyze(string $html, array $context = []): array
    {
        $mode = (string) ($context['mode'] ?? 'live');
        $live = $mode === 'live';
        $url = (string) ($context['url'] ?? '');
        $keyword = mb_strtolower(trim((string) ($context['keyword'] ?? '')));
        $siteHost = strtolower((string) parse_url($url !== '' ? $url : url('/'), PHP_URL_HOST));

        // Read with patterns, not a DOM: PHP's HTML parser predates HTML5 and rebuilds builder
        // markup (a slider inside a <p>, say) into a different tree, losing links and headings.
        $page = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;
        $page = preg_replace('#<(script|style|noscript|template|svg|textarea)\b[^>]*>.*?</\1\s*>#is', ' ', $page) ?? $page;
        // The page's own content, without the site's header, menus and footer, where the theme
        // marks it; else the whole body.
        $root = self::section($page);

        $text = self::text($root);
        $words = self::words($text);
        $wordCount = count($words);
        $headings = [];
        preg_match_all('#<h([1-6])\b[^>]*>(.*?)</h\1\s*>#is', $page, $m, PREG_SET_ORDER);
        foreach ($m as $h) {
            $headings[] = ['level' => (int) $h[1], 'text' => self::text($h[2])];
        }
        $h1s = array_values(array_filter($headings, fn ($h) => $h['level'] === 1));
        $title = preg_match('#<title\b[^>]*>(.*?)</title\s*>#is', $html, $t) ? self::text($t[1]) : '';
        $description = self::meta($html, 'description');
        preg_match_all('#<img\b[^>]*>#i', $root, $m);
        $images = array_map([self::class, 'attributes'], $m[0]);
        $links = self::links($root, $url, $siteHost);

        $groups = [];
        $add = function (string $group, string $id, string $status, string $label, string $detail = '', array $items = []) use (&$groups) {
            $groups[$group][] = ['id' => $id, 'status' => $status, 'label' => $label, 'detail' => $detail, 'items' => array_slice($items, 0, 25)];
        };

        // ── Focus keyword ─────────────────────────────────────────────────────
        if ($keyword === '') {
            $add('keyword', 'kw_set', 'warning', 'No focus keyword set',
                'Choose the search phrase this page should be found for (General tab → Focus Keyword). The checks below then show where it is missing.');
        } else {
            $in = fn (string $haystack) => KeywordSuggestions::contains(mb_strtolower($haystack), $keyword);
            $intro = implode(' ', array_slice($words, 0, 100));
            $slug = str_replace(['-', '_'], ' ', mb_strtolower(rawurldecode((string) ($context['slug'] ?? ''))));
            $occurrences = preg_match_all('/(?<![\p{L}\p{M}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{M}\p{N}])/u', mb_strtolower($text));
            $keywordWords = max(1, count(self::words($keyword)));
            $density = $wordCount ? round($occurrences * $keywordWords / $wordCount * 100, 2) : 0.0;
            $subheadings = array_filter($headings, fn ($h) => $h['level'] >= 2 && $h['level'] <= 3);
            $alts = array_map(fn ($img) => (string) ($img['alt'] ?? ''), $images);

            $add('keyword', 'kw_title', $in($title) ? 'good' : 'error', $in($title) ? 'In the SEO title' : 'Not in the SEO title',
                $in($title) ? '' : 'The title is the strongest signal of what a page is about. Put the keyword in it, near the start if it reads well.');
            $add('keyword', 'kw_h1', array_filter($h1s, fn ($h) => $in($h['text'])) ? 'good' : 'warning',
                array_filter($h1s, fn ($h) => $in($h['text'])) ? 'In the main heading (H1)' : 'Not in the main heading (H1)');
            $add('keyword', 'kw_intro', $in($intro) ? 'good' : 'warning', $in($intro) ? 'In the first 100 words' : 'Not in the first 100 words',
                $in($intro) ? '' : 'Say early what the page is about: readers and search engines both look there first.');
            $add('keyword', 'kw_description', $in($description) ? 'good' : 'warning', $in($description) ? 'In the meta description' : 'Not in the meta description',
                $in($description) ? '' : 'Google shows the searched words in bold in the description, which draws the eye.');
            $add('keyword', 'kw_slug', $in($slug) ? 'good' : 'warning', $in($slug) ? 'In the address (slug)' : 'Not in the address (slug)');
            $add('keyword', 'kw_subheadings', array_filter($subheadings, fn ($h) => $in($h['text'])) ? 'good' : ($subheadings ? 'warning' : 'info'),
                array_filter($subheadings, fn ($h) => $in($h['text'])) ? 'In a subheading (H2/H3)' : 'Not in any subheading (H2/H3)');
            $densityOk = $density >= 0.5 && $density <= 3;
            $add('keyword', 'kw_density', $occurrences === 0 ? 'error' : ($densityOk ? 'good' : 'warning'),
                'Used '.$occurrences.' time(s), '.$density.'% of the words',
                $occurrences === 0 ? 'The keyword never appears in the content.'
                    : ($density < 0.5 ? 'A little more often would make the topic clearer (about 0.5–3%).' : ($density > 3 ? 'That is a lot: it can read as keyword stuffing. Use variations instead.' : '')));
            if ($images) {
                $hasAlt = (bool) array_filter($alts, fn ($a) => $in($a));
                $add('keyword', 'kw_alt', $hasAlt ? 'good' : 'warning', $hasAlt ? 'In an image\'s alt text' : 'Not in any image\'s alt text');
            }
        }

        // ── Content & headings ────────────────────────────────────────────────
        if (count($h1s) === 1) {
            $add('content', 'h1', 'good', 'One H1 heading', $h1s[0]['text']);
        } elseif (!$h1s) {
            $add('content', 'h1', 'error', 'No H1 heading', 'Every page needs one main heading that says what it is about.');
        } else {
            $add('content', 'h1', 'warning', count($h1s).' H1 headings', 'Keep one H1 (the page title) and make the others H2.',
                array_map(fn ($h) => $h['text'] ?: '(empty)', $h1s));
        }

        $skips = [];
        $last = 0;
        foreach ($headings as $h) {
            if ($last && $h['level'] > $last + 1) {
                $skips[] = 'H'.$last.' → H'.$h['level'].': '.($h['text'] ?: '(empty)');
            }
            $last = $h['level'];
        }
        $add('content', 'heading_order', $skips ? 'warning' : 'good', $skips ? 'Heading levels are skipped' : 'Headings are in order',
            $skips ? 'A heading should go one level down at a time (H2, then H3). Screen readers and search engines read the outline.' : '', $skips);

        $counts = array_count_values(array_map(fn ($h) => 'H'.$h['level'], $headings));
        ksort($counts);
        $add('content', 'subheadings', ($counts['H2'] ?? 0) > 0 || $wordCount < 300 ? 'good' : 'warning',
            $headings ? implode(', ', array_map(fn ($tag, $n) => $n.' × '.$tag, array_keys($counts), $counts)) : 'No headings',
            ($counts['H2'] ?? 0) === 0 && $wordCount >= 300 ? 'Break a longer text into sections with H2 headings.' : '');

        $add('content', 'words', $wordCount >= 300 ? 'good' : ($wordCount >= 100 ? 'warning' : 'error'), $wordCount.' words',
            $wordCount >= 300 ? '' : 'Thin pages rarely rank. Aim for at least 300 words that answer what a searcher wants to know.');

        $longParagraphs = [];
        preg_match_all('#<p\b[^>]*>(.*?)</p\s*>#is', $root, $m);
        foreach ($m[1] as $p) {
            $n = count(self::words(self::text($p)));
            if ($n > 150) {
                $longParagraphs[] = $n.' words: '.mb_substr(self::text($p), 0, 80).'…';
            }
        }
        $add('content', 'long_paragraphs', $longParagraphs ? 'warning' : 'good', $longParagraphs ? count($longParagraphs).' very long paragraph(s)' : 'Paragraphs are a readable length',
            $longParagraphs ? 'Over 150 words is hard to read on a phone. Split them.' : '', $longParagraphs);

        $sentences = array_filter(preg_split('/(?<=[.!?।])\s+/u', $text) ?: [], fn ($s) => count(self::words($s)) > 2);
        if ($sentences) {
            $average = (int) round(array_sum(array_map(fn ($s) => count(self::words($s)), $sentences)) / count($sentences));
            $add('content', 'sentences', $average <= 25 ? 'good' : 'warning', 'Sentences average '.$average.' words',
                $average > 25 ? 'Shorter sentences (under about 20 words) are easier to read.' : '');
        }

        // ── Title, description & search appearance ───────────────────────────
        $titleLength = mb_strlen($title);
        $add('meta', 'title', $title === '' ? 'error' : ($titleLength >= 30 && $titleLength <= 60 ? 'good' : 'warning'),
            $title === '' ? 'No title tag' : 'Title: '.$titleLength.' characters',
            $title === '' ? '' : ($titleLength > 60 ? 'Google cuts titles around 60 characters: '.$title : ($titleLength < 30 ? 'Short titles waste space in the results: say a little more. '.$title : $title)));
        $descriptionLength = mb_strlen($description);
        $add('meta', 'description', $description === '' ? 'error' : ($descriptionLength >= 120 && $descriptionLength <= 160 ? 'good' : 'warning'),
            $description === '' ? 'No meta description' : 'Meta description: '.$descriptionLength.' characters',
            $description === '' ? 'Without one Google picks a snippet from the page, often not the best one. Write 120–160 characters.'
                : ($descriptionLength > 160 ? 'It will be cut off after about 160 characters.' : ($descriptionLength < 120 ? 'There is room to say more (120–160 characters).' : '')));

        $robots = mb_strtolower(self::meta($html, 'robots'));
        if (str_contains($robots, 'noindex')) {
            $add('meta', 'noindex', 'error', 'This page asks not to be indexed (noindex)', 'It will not appear in Google. Untick it under Advanced if that is not intended.');
        }

        if ($live) {
            $canonical = '';
            preg_match_all('#<link\b[^>]*>#i', $html, $m);
            foreach ($m[0] as $tag) {
                $a = self::attributes($tag);
                if (strtolower($a['rel'] ?? '') === 'canonical') {
                    $canonical = trim($a['href'] ?? '');
                    break;
                }
            }
            $elsewhere = $canonical !== '' && $url !== '' && rtrim($canonical, '/') !== rtrim($url, '/');
            $add('meta', 'canonical', $canonical === '' ? 'warning' : ($elsewhere ? 'info' : 'good'),
                $canonical === '' ? 'No canonical link' : ($elsewhere ? 'Canonical points to another address' : 'Canonical link is set'), $canonical);
            $ogImage = self::meta($html, 'og:image', 'property');
            $add('meta', 'og_image', $ogImage !== '' ? 'good' : 'warning', $ogImage !== '' ? 'Has a social share image' : 'No social share image (og:image)',
                $ogImage !== '' ? '' : 'Set a featured image or one under Social: shared links with a picture get far more clicks.');
            $schema = preg_match_all('/<script[^>]+application\/ld\+json/i', $html);
            $add('meta', 'schema', $schema ? 'good' : 'warning', $schema ? 'Structured data found' : 'No structured data',
                $schema ? '' : 'Structured data (JSON-LD) helps Google understand the page and can earn rich results.');
            $lang = preg_match('#<html\b[^>]*>#i', $html, $h) ? trim(self::attributes($h[0])['lang'] ?? '') : '';
            $add('meta', 'lang', $lang !== '' ? 'good' : 'warning', $lang !== '' ? 'Language is declared ('.$lang.')' : 'No language declared on <html>');
            $viewport = self::meta($html, 'viewport');
            $add('meta', 'viewport', $viewport !== '' ? 'good' : 'error', $viewport !== '' ? 'Mobile-friendly viewport' : 'No viewport meta tag',
                $viewport !== '' ? '' : 'Without it phones show a zoomed-out desktop page, and Google ranks mobile-first.');
        }

        // ── Images ────────────────────────────────────────────────────────────
        if (!$images) {
            $add('images', 'alt', $wordCount >= 300 ? 'info' : 'good', 'No images in the content',
                $wordCount >= 300 ? 'An image or two makes a long page easier to take in, and can bring visitors from image search.' : '');
        } else {
            $noAlt = [];
            $noSize = [];
            $heavy = [];
            foreach ($images as $img) {
                $src = (string) (($img['src'] ?? '') ?: ($img['data-src'] ?? ''));
                $name = basename((string) parse_url($src, PHP_URL_PATH)) ?: $src;
                if (trim($img['alt'] ?? '') === '') {
                    $noAlt[] = $name;
                }
                if (!isset($img['width'], $img['height'])) {
                    if (!str_contains($img['style'] ?? '', 'aspect-ratio')) {
                        $noSize[] = $name;
                    }
                }
                if (preg_match('~/storage/([^?#]+)~', $src, $m)) {
                    $file = storage_path('app/public/'.rawurldecode($m[1]));
                    if (is_file($file) && filesize($file) > self::HEAVY_IMAGE_BYTES) {
                        $heavy[] = $name.' ('.round(filesize($file) / 1024).' KB)';
                    }
                }
            }
            $add('images', 'alt', $noAlt ? 'warning' : 'good', $noAlt ? count($noAlt).' of '.count($images).' image(s) have no alt text' : 'All '.count($images).' image(s) have alt text',
                $noAlt ? 'Alt text describes an image to screen readers and to Google Images. Leave it empty only for purely decorative images.' : '', $noAlt);
            $add('images', 'image_dimensions', $noSize ? 'warning' : 'good', $noSize ? count($noSize).' image(s) without width and height' : 'Images reserve their space',
                $noSize ? 'Without a size the page jumps as each image arrives (layout shift, a Core Web Vital).' : '', $noSize);
            $add('images', 'heavy_images', $heavy ? 'warning' : 'good', $heavy ? count($heavy).' heavy image(s)' : 'No heavy images',
                $heavy ? 'Over '.(self::HEAVY_IMAGE_BYTES / 1024).' KB each. Compress them or turn on WebP/AVIF in Customizer → Performance.' : '', $heavy);
        }

        // ── Links ─────────────────────────────────────────────────────────────
        $internal = array_values(array_filter($links, fn ($l) => $l['internal']));
        $external = array_values(array_filter($links, fn ($l) => !$l['internal']));
        $add('links', 'internal_links', $internal ? 'good' : 'warning', count($internal).' internal link(s), '.count($external).' external',
            $internal ? '' : 'Link to related pages on your site (the Internal Linking tab suggests some): it helps readers and spreads ranking strength.');
        $empty = array_values(array_filter($links, fn ($l) => $l['text'] === ''));
        if ($empty) {
            $add('links', 'empty_links', 'warning', count($empty).' link(s) without text', 'A link needs words (or an image with alt text) that say where it goes.',
                array_map(fn ($l) => $l['href'], $empty));
        }
        if (!empty($context['check_links'])) {
            $broken = self::brokenLinks(array_values(array_unique(array_map(fn ($l) => $l['href'], $links))));
            $add('links', 'broken_links', $broken['broken'] ? 'error' : 'good',
                $broken['broken'] ? count($broken['broken']).' broken link(s)' : 'No broken links ('.$broken['checked'].' checked)',
                $broken['broken'] ? 'Visitors and search engines hit a dead end there. Fix the address or remove the link.' : ($broken['skipped'] ? $broken['skipped'].' more not checked (limit '.self::LINK_CHECK_LIMIT.').' : ''),
                array_map(fn ($b) => $b['url'].' — '.$b['status'], $broken['broken']));
        }

        return self::score($groups, $mode, $url, [
            'words' => $wordCount, 'images' => count($images), 'internal_links' => count($internal), 'external_links' => count($external),
            'headings' => count($headings), 'h1' => count($h1s), 'h2' => $counts['H2'] ?? 0, 'h3' => $counts['H3'] ?? 0,
        ]);
    }

    /**
     * Which of these addresses answer with an error. Each is asked once a day at most; a HEAD
     * the server refuses is retried as a GET.
     *
     * @param  list<string>  $urls
     * @return array{broken: list<array{url: string, status: string}>, checked: int, skipped: int}
     */
    public static function brokenLinks(array $urls): array
    {
        $urls = array_values(array_filter($urls, fn ($u) => preg_match('#^https?://#i', $u)));
        $skipped = max(0, count($urls) - self::LINK_CHECK_LIMIT);
        $urls = array_slice($urls, 0, self::LINK_CHECK_LIMIT);

        $status = [];
        $ask = [];
        foreach ($urls as $u) {
            $cached = Cache::get('falcon:seo:link:'.sha1($u));
            $cached !== null ? $status[$u] = $cached : $ask[] = $u;
        }

        foreach (array_chunk($ask, 10) as $chunk) {
            try {
                $responses = Http::pool(fn (Pool $pool) => array_map(fn ($u) => $pool->as($u)->timeout(6)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FalconSEO-LinkCheck/1.0)'])->withOptions(['allow_redirects' => ['max' => 5]])->head($u), $chunk));
            } catch (Throwable $e) {
                $responses = [];
            }
            foreach ($chunk as $u) {
                $r = $responses[$u] ?? null;
                $code = $r instanceof Response ? $r->status() : 0;
                // Many servers do not answer HEAD (405, 403, 501): ask properly before calling it broken.
                if ($code === 0 || $code === 405 || $code === 403 || $code === 501 || $code === 400) {
                    try {
                        $code = Http::timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; FalconSEO-LinkCheck/1.0)'])->get($u)->status();
                    } catch (Throwable $e) {
                        $code = 0;
                    }
                }
                $status[$u] = $code;
                Cache::put('falcon:seo:link:'.sha1($u), $code, now()->addDay());
            }
        }

        $broken = [];
        foreach ($status as $u => $code) {
            // 401/403 are pages behind a login or a bot wall, not dead ones; 429 is "slow down".
            if ($code === 0 || ($code >= 400 && !in_array($code, [401, 403, 429], true))) {
                $broken[] = ['url' => $u, 'status' => $code === 0 ? 'no answer' : (string) $code];
            }
        }

        return ['broken' => $broken, 'checked' => count($urls), 'skipped' => $skipped];
    }

    /** @param  array<string, list<array>>  $groups */
    private static function score(array $groups, string $mode, string $url, array $stats): array
    {
        $total = 0.0;
        $earned = 0.0;
        $out = [];
        foreach (self::GROUPS as $id => $label) {
            if (empty($groups[$id])) {
                continue;
            }
            $rank = ['error' => 0, 'warning' => 1, 'info' => 2, 'good' => 3];
            usort($groups[$id], fn ($a, $b) => $rank[$a['status']] <=> $rank[$b['status']]);
            foreach ($groups[$id] as $check) {
                if ($check['status'] === 'info') {
                    continue;
                }
                $weight = self::WEIGHT[$check['id']] ?? 1;
                $total += $weight;
                $earned += $weight * ['good' => 1, 'warning' => 0.5, 'error' => 0][$check['status']];
            }
            $out[] = ['id' => $id, 'label' => $label, 'checks' => $groups[$id]];
        }
        $score = $total > 0 ? (int) round($earned / $total * 100) : 0;

        return ['score' => $score, 'grade' => $score >= 80 ? 'good' : ($score >= 50 ? 'ok' : 'poor'), 'mode' => $mode, 'url' => $url, 'stats' => $stats, 'groups' => $out];
    }

    /** @return list<array{href: string, text: string, internal: bool}> */
    private static function links(string $html, string $pageUrl, string $siteHost): array
    {
        $out = [];
        preg_match_all('#<a\b([^>]*)>(.*?)</a\s*>#is', $html, $m, PREG_SET_ORDER);
        foreach ($m as $match) {
            $a = self::attributes('<a'.$match[1].'>');
            $href = trim($a['href'] ?? '');
            if ($href === '' || $href[0] === '#' || preg_match('#^(mailto|tel|javascript|data|sms):#i', $href)) {
                continue;
            }
            $absolute = self::absolute($href, $pageUrl);
            $host = strtolower((string) parse_url($absolute, PHP_URL_HOST));
            $text = self::text($match[2]);
            if ($text === '') {
                preg_match_all('#<img\b[^>]*>#i', $match[2], $imgs);
                foreach ($imgs[0] as $img) {
                    $text .= trim(self::attributes($img)['alt'] ?? '');
                }
                $text = $text ?: trim(($a['aria-label'] ?? '') ?: ($a['title'] ?? ''));
            }
            $out[] = ['href' => $absolute, 'text' => $text, 'internal' => $host === '' || $host === $siteHost || preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $siteHost)];
        }

        return $out;
    }

    private static function absolute(string $href, string $base): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $base = $base !== '' ? $base : url('/');
        $scheme = (string) parse_url($base, PHP_URL_SCHEME) ?: 'https';
        $host = (string) parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);
        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');
        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }
        if ($href[0] === '/') {
            return $origin.$href;
        }
        $path = (string) parse_url($base, PHP_URL_PATH);

        return $origin.rtrim(substr($path, 0, (int) strrpos($path, '/')), '/').'/'.$href;
    }

    /** The page's own content: <main>, else <article>, else <body>, else everything. */
    private static function section(string $page): string
    {
        foreach (['main', 'article', 'body'] as $tag) {
            if (preg_match('#<'.$tag.'\b[^>]*>#i', $page, $open, PREG_OFFSET_CAPTURE)
                && ($close = strripos($page, '</'.$tag)) !== false && $close > $open[0][1]) {
                $start = $open[0][1] + strlen($open[0][0]);

                return substr($page, $start, $close - $start);
            }
        }

        return $page;
    }

    /** A tag's attributes, names lowercased, values decoded: '<img src="a.jpg" alt>' → ['src' => 'a.jpg', 'alt' => '']. */
    private static function attributes(string $tag): array
    {
        $out = [];
        $inner = preg_replace('#^<[a-z0-9]+|/?>$#i', '', trim($tag)) ?? '';
        preg_match_all('#([^\s=/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?#', $inner, $m, PREG_SET_ORDER);
        foreach ($m as $attr) {
            $value = ($attr[2] ?? '') !== '' ? $attr[2] : (($attr[3] ?? '') !== '' ? $attr[3] : ($attr[4] ?? ''));
            $out[strtolower($attr[1])] ??= html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $out;
    }

    private static function meta(string $html, string $name, string $attribute = 'name'): string
    {
        preg_match_all('#<meta\b[^>]*>#i', $html, $m);
        foreach ($m[0] as $tag) {
            $a = self::attributes($tag);
            if (strtolower($a[$attribute] ?? '') === $name) {
                return trim($a['content'] ?? '');
            }
        }

        return '';
    }

    private static function text(string $html): string
    {
        // Tags become spaces so words either side of one do not run together.
        $text = html_entity_decode(strip_tags(preg_replace('#<[^>]+>#', ' $0 ', $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{M}\p{N}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
