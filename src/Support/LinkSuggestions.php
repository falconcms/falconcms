<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Internal link suggestions for the post being edited, both ways:
 *
 *  - outbound: related pages this one could link to, scored by the significant words its
 *    title and focus keyword share with theirs;
 *  - inbound: pages that already talk about this one's focus keyword (or title) but do not
 *    link to it, which is how a new or orphaned page gets found.
 *
 * Each suggestion says why it was made. Plugins add their own through the
 * `falcon_link_suggestions` filter (Search Console adds pages Google ranks for the keyword).
 * Read-only: nothing is inserted into any post.
 */
final class LinkSuggestions
{
    private const LIMIT = 8;

    private const EXCLUDED_TYPES = ['falcon_content', 'falcon_header', 'falcon_footer', 'falcon_ptb', 'lazy_header', 'lazy_footer'];

    private const STOPWORDS = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'your', 'you', 'are', 'how', 'what', 'why',
        'when', 'who', 'its', 'our', 'has', 'have', 'was', 'not', 'but', 'all', 'can', 'into', 'about', 'more', 'best', 'new', 'use'];

    /**
     * @return array{outbound: list<array>, inbound: list<array>}
     */
    public static function for(string $title, string $keyword, int $postId): array
    {
        $keyword = mb_strtolower(trim($keyword));
        $words = self::words($title.' '.$keyword);
        $selfPath = null;
        if ($postId > 0) {
            try {
                $self = DB::table('posts')->where('id', $postId)->first(['id', 'title', 'slug', 'type', 'lang_code', 'origin_id']);
                $selfPath = $self ? self::path(get_falcon_permalink($self)) : null;
            } catch (Throwable $e) {
            }
        }

        $result = [
            'outbound' => self::outbound($words, $keyword, $postId),
            'inbound' => $selfPath ? self::inbound($keyword !== '' ? $keyword : mb_strtolower(trim($title)), $postId, $selfPath) : [],
        ];

        return (array) apply_falcon_filters('falcon_link_suggestions', $result, ['title' => $title, 'keyword' => $keyword, 'post_id' => $postId]);
    }

    private static function outbound(array $words, string $keyword, int $postId): array
    {
        if ($words === []) {
            return [];
        }
        try {
            $rows = self::published()->where('id', '!=', $postId)
                ->where(function ($q) use ($words, $keyword) {
                    foreach ($words as $w) {
                        $q->orWhere('title', 'like', '%'.addcslashes($w, '%_\\').'%');
                    }
                    if ($keyword !== '') {
                        $q->orWhere('title', 'like', '%'.addcslashes($keyword, '%_\\').'%');
                    }
                })
                ->limit(200)->get(['id', 'title', 'slug', 'type', 'lang_code', 'origin_id']);
        } catch (Throwable $e) {
            return [];
        }

        $scored = [];
        foreach ($rows as $row) {
            $theirs = self::words((string) $row->title);
            $shared = array_values(array_intersect($words, $theirs));
            $score = count($shared) * 2 + ($keyword !== '' && str_contains(mb_strtolower((string) $row->title), $keyword) ? 5 : 0);
            if ($score < 2) {
                continue;
            }
            $scored[] = self::item($row, $score, $shared ? 'Shares: '.implode(', ', array_slice($shared, 0, 4)) : 'Title contains the focus keyword');
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, self::LIMIT);
    }

    private static function inbound(string $phrase, int $postId, string $selfPath): array
    {
        if (mb_strlen($phrase) < 3) {
            return [];
        }
        try {
            $rows = self::published()->where('id', '!=', $postId)
                ->where('content', 'like', '%'.addcslashes($phrase, '%_\\').'%')
                ->limit(200)->get(['id', 'title', 'slug', 'type', 'lang_code', 'origin_id', 'content']);
        } catch (Throwable $e) {
            return [];
        }

        $items = [];
        $needle = preg_quote(trim($selfPath, '/'), '#');
        foreach ($rows as $row) {
            // Already links here (any form of the address)? Then nothing to suggest.
            if ($needle !== '' && preg_match('#(?:href|url|link_url|link)\s*=\s*\\\\?["\'](?:https?://[^/"\']+)?/'.$needle.'/?\\\\?["\']#i', (string) $row->content)) {
                continue;
            }
            $items[] = self::item($row, substr_count(mb_strtolower(strip_tags((string) $row->content)), $phrase),
                'Mentions “'.$phrase.'” but does not link here');
        }
        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($items, 0, self::LIMIT);
    }

    private static function published()
    {
        return DB::table('posts')->where('status', 'published')->whereNull('deleted_at')->whereNotIn('type', self::EXCLUDED_TYPES);
    }

    private static function item(object $row, int $score, string $reason): array
    {
        $url = get_falcon_permalink($row);
        $type = (string) $row->type;
        $route = $type === 'page' ? 'admin.pages.edit' : 'admin.posts.edit';

        return [
            'id' => (int) $row->id, 'title' => (string) $row->title, 'url' => $url, 'path' => self::path($url),
            'score' => $score, 'reason' => $reason,
            'edit' => \Illuminate\Support\Facades\Route::has($route) ? route($route, $row->id) : null,
        ];
    }

    /** @return list<string> lowercase words of 3+ letters, stopwords removed */
    public static function words(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_unique(array_filter($parts, fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true))));
    }

    private static function path(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        return '/'.trim(is_string($path) ? $path : '', '/');
    }
}
