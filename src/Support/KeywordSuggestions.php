<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Post;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Keyword ideas for one page, from its own words: the phrases it says most that the rest of
 * the site says least (TF-IDF over the site's published content), each with where it is
 * still missing: title, headings, first paragraph, address, meta description.
 *
 * No outside service and no cost. What the page says is not what people search, so plugins add
 * the searcher's side through the `falcon_keyword_suggestions` filter (Search Console adds the
 * searches Google already shows the page for).
 */
final class KeywordSuggestions
{
    public const LIMIT = 10;

    /** Candidates taken to the (one query each) site-wide comparison. */
    private const CANDIDATES = 40;

    /** Types that are never pages of their own. */
    private const LAYOUT_TYPES = ['falcon_content', 'falcon_header', 'falcon_footer', 'falcon_ptb', 'lazy_header', 'lazy_footer'];

    /** Longer phrases are closer to what people type into a search box. */
    private const LENGTH_WEIGHT = [1 => 1.0, 2 => 1.7, 3 => 2.0];

    private const STOPWORDS = [
        // English
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'as', 'at', 'be',
        'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'can', 'could', 'did', 'do', 'does',
        'doing', 'done', 'down', 'during', 'each', 'even', 'every', 'few', 'for', 'from', 'further', 'get', 'gets', 'got',
        'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'him', 'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it',
        'its', 'itself', 'just', 'let', 'like', 'make', 'many', 'may', 'me', 'might', 'more', 'most', 'much', 'must', 'my',
        'need', 'no', 'nor', 'not', 'now', 'of', 'off', 'on', 'once', 'one', 'only', 'or', 'other', 'our', 'ours', 'out',
        'over', 'own', 'per', 'put', 'quite', 'rather', 'really', 'same', 'see', 'she', 'should', 'so', 'some', 'such',
        'than', 'that', 'the', 'their', 'theirs', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'through',
        'thus', 'to', 'too', 'under', 'until', 'up', 'upon', 'us', 'use', 'used', 'using', 'very', 'via', 'was', 'way', 'we',
        'well', 'were', 'what', 'when', 'where', 'whether', 'which', 'while', 'who', 'whom', 'whose', 'why', 'will', 'with',
        'within', 'without', 'would', 'yet', 'you', 'your', 'yours', 'yourself', 'read', 'click', 'here', 'more', 'new',
        // Bangla
        'এবং', 'ও', 'এর', 'এই', 'যে', 'না', 'করে', 'কি', 'কী', 'জন্য', 'থেকে', 'একটি', 'একটা', 'হয়', 'হয়', 'তার', 'আমরা',
        'আমাদের', 'আপনার', 'আপনি', 'সঙ্গে', 'সাথে', 'হবে', 'ছিল', 'করা', 'তা', 'সে', 'কিন্তু', 'বা', 'যা', 'এক', 'আর', 'হলে',
        'তবে', 'নয়', 'নয়', 'কোন', 'কোনো', 'সব', 'খুব', 'আছে', 'নেই', 'দিয়ে', 'দিয়ে', 'মধ্যে', 'পর', 'আগে', 'তো', 'এ', 'যদি',
        'তাই', 'এটা', 'এটি', 'ওই', 'সেই', 'তারা', 'তাদের', 'আমি', 'আমার', 'তুমি', 'তোমার', 'করতে', 'করেন', 'হতে', 'পারে',
    ];

    /** Builder placeholder text left in a page: words on it, but nothing it is about. */
    private const PLACEHOLDERS = ['list item', 'icon box', 'text block', 'heading', 'title', 'button', 'click here',
        'lorem', 'ipsum', 'lorem ipsum', 'dolor', 'sit amet', 'your title', 'add your', 'tab title', 'accordion title'];

    /**
     * @param  array{post_id?: int, title?: string, seo_title?: string, description?: string, slug?: string, content?: string, keyword?: string}  $input
     * @return array{phrases: list<array>, words: int, google: list<array>, google_status: ?string}
     */
    public static function for(array $input): array
    {
        $postId = (int) ($input['post_id'] ?? 0);
        $post = $postId ? Post::find($postId) : null;
        $content = (string) ($input['content'] ?? '');
        if (trim($content) === '' && $post) {
            $content = (string) $post->content;
        }

        $context = [
            'post_id' => $postId,
            'title' => mb_strtolower(trim((string) ($input['seo_title'] ?? '')) ?: trim((string) ($input['title'] ?? ($post->title ?? '')))),
            'description' => mb_strtolower((string) ($input['description'] ?? '')),
            'slug' => mb_strtolower(str_replace(['-', '_'], ' ', rawurldecode((string) ($input['slug'] ?? ($post->slug ?? ''))))),
            'headings' => mb_strtolower(implode(' . ', self::headings($content))),
            'text' => mb_strtolower(falcon_content_text($content)),
            'keyword' => mb_strtolower(trim((string) ($input['keyword'] ?? ''))),
        ];
        $context['intro'] = implode(' ', array_slice(preg_split('/\s+/u', $context['text'], -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 100));

        $result = [
            'phrases' => self::phrases($content, $context),
            'words' => count(preg_split('/\s+/u', $context['text'], -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'google' => [],
            'google_status' => null,
        ];

        try {
            $result = (array) apply_falcon_filters('falcon_keyword_suggestions', $result, $context);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }

    /** Where a phrase already is, and where it is not: what the editor shows beside each idea. */
    public static function placement(string $phrase, array $context): array
    {
        $phrase = mb_strtolower(trim($phrase));
        $has = fn (string $where) => $phrase !== '' && self::contains($context[$where] ?? '', $phrase);

        return [
            'title' => $has('title'),
            'headings' => $has('headings'),
            'intro' => $has('intro'),
            'url' => $has('slug'),
            'description' => $has('description'),
        ];
    }

    /** Whole-word match: "art" is not in "start". */
    public static function contains(string $haystack, string $phrase): bool
    {
        return preg_match('/(?<![\p{L}\p{M}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{M}\p{N}])/u', $haystack) === 1;
    }

    /** @return list<array{phrase: string, count: int, score: int, in: array<string, bool>}> */
    private static function phrases(string $content, array $context): array
    {
        $counts = [];
        $sizes = [];
        foreach (self::segments($content) as $segment) {
            // Words, hyphenated ones whole ("e-commerce"); an apostrophe joins ("don't" → "dont").
            preg_match_all('/[\p{L}\p{M}\p{N}]+(?:-[\p{L}\p{M}\p{N}]+)*/u', str_replace(["'", '’'], '', $segment), $words);
            $tokens = $words[0];
            $n = count($tokens);
            for ($i = 0; $i < $n; $i++) {
                for ($len = 1; $len <= 3 && $i + $len <= $n; $len++) {
                    $gram = array_slice($tokens, $i, $len);
                    if (!self::usable($gram)) {
                        continue;
                    }
                    $phrase = implode(' ', $gram);
                    $counts[$phrase] = ($counts[$phrase] ?? 0) + 1;
                    $sizes[$phrase] = $len;
                }
            }
        }

        // "developer" and "developers" are one idea: counted together, under the form used more.
        foreach (array_keys($counts) as $phrase) {
            $plural = $phrase.'s';
            if (isset($counts[$phrase], $counts[$plural])) {
                [$keep, $drop] = $counts[$plural] > $counts[$phrase] ? [$plural, $phrase] : [$phrase, $plural];
                $counts[$keep] += $counts[$drop];
                unset($counts[$drop]);
            }
        }

        // Once is chance; the title is the exception, as it says what the page is about.
        $candidates = [];
        foreach ($counts as $phrase => $count) {
            if (in_array((string) $phrase, self::PLACEHOLDERS, true)) {
                continue;
            }
            $inTitle = self::contains($context['title'], $phrase);
            if ($count < 2 && !$inTitle) {
                continue;
            }
            $candidates[$phrase] = $count * self::LENGTH_WEIGHT[$sizes[$phrase]] * ($inTitle ? 1.5 : 1);
        }
        arsort($candidates);
        $candidates = array_slice($candidates, 0, self::CANDIDATES, true);
        if ($candidates === []) {
            return [];
        }

        // Rarity on this site: what every page says ("our team") is not what this one is about.
        [$total, $frequency] = self::siteFrequency(array_keys($candidates), (int) $context['post_id']);
        $scored = [];
        foreach ($candidates as $phrase => $weight) {
            $scored[$phrase] = $weight * log(($total + 1) / (($frequency[$phrase] ?? 0) + 1) + 1);
        }
        arsort($scored);

        $picked = [];
        foreach ($scored as $phrase => $score) {
            // "pricing" adds nothing next to "laravel cms pricing" that is said almost as often.
            foreach ($picked as $kept => $_) {
                if (self::contains($kept, $phrase) && $counts[$phrase] <= $counts[$kept] * 1.5) {
                    continue 2;
                }
            }
            foreach ($picked as $kept => $_) {
                if (self::contains($phrase, $kept) && $counts[$kept] <= $counts[$phrase] * 1.5) {
                    unset($picked[$kept]);
                }
            }
            $picked[$phrase] = $score;
            if (count($picked) >= self::LIMIT) {
                break;
            }
        }

        $top = max($picked) ?: 1;
        $out = [];
        foreach ($picked as $phrase => $score) {
            $out[] = [
                'phrase' => (string) $phrase,
                'count' => $counts[$phrase],
                'score' => (int) max(1, round($score / $top * 100)),
                'in' => self::placement((string) $phrase, $context),
            ];
        }

        return $out;
    }

    /** Sentences and headings, so no phrase is stitched across the end of one and the start of the next. */
    private static function segments(string $content): array
    {
        $text = mb_strtolower(falcon_content_text(preg_replace('/<\/?(p|div|h[1-6]|li|br|td|th|section|blockquote)\b[^>]*>/i', ' | ', $content) ?? $content, ' | '));

        return preg_split('/[|.!?;:,()\[\]{}"“”।\n]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function usable(array $gram): bool
    {
        $first = $gram[0];
        $last = $gram[count($gram) - 1];
        if (in_array($first, self::STOPWORDS, true) || in_array($last, self::STOPWORDS, true)) {
            return false;
        }
        if (count($gram) === 1 && (mb_strlen($first) < 3 || ctype_digit($first))) {
            return false;
        }

        return !ctype_digit($first) && !ctype_digit($last);
    }

    /** @return array{0: int, 1: array<string, int>} pages on the site, and how many use each phrase */
    private static function siteFrequency(array $phrases, int $exceptId): array
    {
        try {
            $base = DB::table('posts')->where('status', 'published')->whereNull('deleted_at')
                ->whereNotIn('type', self::LAYOUT_TYPES)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId));
            $total = (clone $base)->count();
            $frequency = [];
            foreach ($phrases as $phrase) {
                $like = '%'.addcslashes($phrase, '%_\\').'%';
                $frequency[$phrase] = (clone $base)->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))->count();
            }

            return [$total, $frequency];
        } catch (Throwable $e) {
            return [0, []];
        }
    }

    /** Heading text: <h1>-<h3> in HTML, and heading elements in builder JSON. */
    private static function headings(string $content): array
    {
        $out = [];
        if (preg_match_all('/<h[1-3]\b[^>]*>(.*?)<\/h[1-3]>/is', $content, $m)) {
            foreach ($m[1] as $h) {
                $out[] = falcon_html_to_text($h);
            }
        }
        // Builder elements keep their settings in an object: {"type": "title", "settings": {"title": "…"}}.
        if (preg_match_all('/"type"\s*:\s*"(?:heading|title|icon_box)"\s*,\s*"settings"\s*:\s*\{[^{}]*?"(?:text|title)"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $content, $m)) {
            foreach ($m[1] as $h) {
                $out[] = falcon_html_to_text(json_decode('"'.$h.'"') ?? $h);
            }
        }

        return $out;
    }
}
