<?php

namespace FalconSeo\Support;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Every page's SEO check-up, kept up to date in the background: PageAnalyzer run page by page,
 * its score and problems stored in cms_seo_page_scores.
 *
 * A page is due when it has never been checked, when it changed since (saving a post clears
 * its date) or when its check is older than a week. A few pages go per run, so a large site
 * fills in over the hours without ever slowing the server down.
 */
final class SiteScan
{
    public const TABLE = 'cms_seo_page_scores';

    /** A check older than this is done again: links break and themes change. */
    public const STALE_DAYS = 7;

    /** Parts of a page (headers, footers, reusable blocks), never pages of their own. */
    private const LAYOUT_TYPES = ['falcon_content', 'falcon_header', 'falcon_footer', 'falcon_ptb', 'lazy_header', 'lazy_footer'];

    /** Published pages meant for search, as a query. */
    public static function pages()
    {
        return Post::query()->published()->whereNotIn('posts.type', self::excludedTypes())
            ->whereNotNull('posts.slug')->where('posts.slug', '!=', '');
    }

    /** Pages due a check, those never checked first. */
    public static function due(int $limit)
    {
        $table = self::TABLE;

        return self::pages()
            ->leftJoin($table, $table.'.post_id', '=', 'posts.id')
            ->where(fn ($q) => $q->whereNull($table.'.id')->orWhereNull($table.'.analyzed_at')
                ->orWhere($table.'.analyzed_at', '<', now()->subDays(self::STALE_DAYS)))
            ->orderByRaw($table.'.id is not null')->orderBy($table.'.analyzed_at')->orderBy('posts.id')
            ->limit($limit)->select('posts.*')->get()
            // Kept out of search on purpose: nothing to score.
            ->reject(fn (Post $post) => (($post->seo_meta['noindex'] ?? '0') == '1') || apply_falcon_filters('falcon_seo_noindex', false, $post))
            ->values();
    }

    /** Check up to $limit pages that are due. Returns how many were checked. */
    public static function run(int $limit = 10, bool $checkLinks = true): int
    {
        $done = 0;
        foreach (self::due($limit) as $post) {
            try {
                self::analyze($post, $checkLinks);
                $done++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $done;
    }

    /** Check one page now, as visitors get it (or from its content when it cannot be fetched), and keep the result. */
    public static function analyze(Post $post, bool $checkLinks = true): array
    {
        $context = ['keyword' => (string) ($post->seo_meta['focus_keyword'] ?? ''), 'slug' => (string) $post->slug, 'check_links' => $checkLinks];
        $live = PageAnalyzer::fetchLive($post);
        $result = $live
            ? PageAnalyzer::analyze($live['html'], $context + ['mode' => 'live', 'url' => $live['url']])
            : PageAnalyzer::analyze(PageAnalyzer::editorPage([
                'title' => (string) $post->title, 'seo_title' => (string) ($post->seo_meta['title'] ?? ''),
                'description' => (string) ($post->seo_meta['description'] ?? ''), 'content' => (string) $post->content,
            ]), $context + ['mode' => 'editor', 'url' => (string) get_falcon_permalink($post)]);

        self::store($post, $result);

        return $result;
    }

    /** Keep a check-up's result for a page (the editor's Analysis keeps its own runs too). */
    public static function store(Post $post, array $result): void
    {
        $issues = [];
        $errors = $warnings = 0;
        foreach ($result['groups'] ?? [] as $group) {
            foreach ($group['checks'] as $check) {
                if ($check['status'] !== 'error' && $check['status'] !== 'warning') {
                    continue;
                }
                $check['status'] === 'error' ? $errors++ : $warnings++;
                $issues[] = ['id' => $check['id'], 'group' => $group['id'], 'status' => $check['status'], 'label' => $check['label'], 'items' => $check['items'] ?? []];
            }
        }

        try {
            $row = [
                'url' => mb_substr((string) ($result['url'] ?? ''), 0, 500),
                'score' => (int) ($result['score'] ?? 0),
                'grade' => (string) ($result['grade'] ?? 'poor'),
                'errors' => $errors,
                'warnings' => $warnings,
                'mode' => (string) ($result['mode'] ?? 'live'),
                'issues' => json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'analyzed_at' => now(),
                'updated_at' => now(),
            ];
            $before = DB::table(self::TABLE)->where('post_id', $post->id)->first(['score']);
            if ($before) {
                // The weekly email compares the two.
                if (Schema::hasColumn(self::TABLE, 'previous_score')) {
                    $row['previous_score'] = (int) $before->score;
                }
                DB::table(self::TABLE)->where('post_id', $post->id)->update($row);
            } else {
                DB::table(self::TABLE)->insert($row + ['post_id' => $post->id, 'created_at' => now()]);
            }
        } catch (Throwable $e) {
            report($e); // no table yet (not migrated): the analysis itself still answered
        }
    }

    /** A page changed: its score stays on show, but it is checked again on the next run. */
    public static function markChanged(int $postId): void
    {
        try {
            DB::table(self::TABLE)->where('post_id', $postId)->update(['analyzed_at' => null]);
        } catch (Throwable $e) {
        }
    }

    /** @return array<int, object> post id => its stored row, for the posts list (loaded once per request) */
    public static function scores(): array
    {
        // Once per request, not once per process: a static kept the first list's scores for
        // the life of a worker, so a long-running one (and the test suite) showed stale ones.
        $memo = falcon_request_memo('seo_site_scan_scores');
        if (!$memo->offsetExists('scores')) {
            try {
                $memo['scores'] = DB::table(self::TABLE)->get(['post_id', 'score', 'grade', 'errors', 'warnings', 'analyzed_at'])->keyBy('post_id')->all();
            } catch (Throwable $e) {
                $memo['scores'] = [];
            }
        }

        return $memo['scores'];
    }

    /**
     * The whole site at a glance: how many pages are checked, their average, how many in each
     * grade, and each problem with the pages that have it.
     *
     * @return array{pages: int, checked: int, due: int, average: ?int, grades: array<string, int>, last: ?string, problems: list<array>, rows: list<object>}
     */
    public static function summary(): array
    {
        $table = self::TABLE;
        $ids = self::pages()->pluck('posts.id')->all();
        $rows = DB::table($table)->join('posts', 'posts.id', '=', $table.'.post_id')
            ->whereIn($table.'.post_id', $ids ?: [0])
            ->orderBy($table.'.score')->orderBy('posts.title')
            ->get([$table.'.*', 'posts.title', 'posts.type']);

        $grades = ['good' => 0, 'ok' => 0, 'poor' => 0];
        $problems = [];
        foreach ($rows as $row) {
            $grades[$row->grade] = ($grades[$row->grade] ?? 0) + 1;
            foreach (json_decode((string) $row->issues, true) ?: [] as $issue) {
                $key = $issue['id'];
                $problems[$key] ??= ['id' => $key, 'status' => $issue['status'], 'label' => self::problemLabel($key, $issue['label']), 'pages' => []];
                if ($issue['status'] === 'error') {
                    $problems[$key]['status'] = 'error';
                }
                $problems[$key]['pages'][] = ['post_id' => (int) $row->post_id, 'title' => (string) $row->title, 'type' => (string) $row->type,
                    'url' => (string) $row->url, 'label' => $issue['label'], 'items' => $issue['items']];
            }
        }
        usort($problems, fn ($a, $b) => [$a['status'] === 'error' ? 0 : 1, -count($a['pages'])] <=> [$b['status'] === 'error' ? 0 : 1, -count($b['pages'])]);

        $due = $rows->filter(fn ($r) => !$r->analyzed_at || $r->analyzed_at < now()->subDays(self::STALE_DAYS)->toDateTimeString())->count()
            + max(0, count($ids) - $rows->count());

        return [
            'pages' => count($ids),
            'checked' => $rows->count(),
            'due' => $due,
            'average' => $rows->count() ? (int) round($rows->avg('score')) : null,
            'grades' => $grades,
            'last' => $rows->max('analyzed_at'),
            'problems' => array_values($problems),
            'rows' => $rows->all(),
        ];
    }

    /** One name for a check across pages ("Not in the SEO title" is the same problem everywhere). */
    private static function problemLabel(string $id, string $label): string
    {
        return [
            'h1' => 'H1 heading missing or repeated', 'heading_order' => 'Heading levels skipped', 'subheadings' => 'Long text without H2 headings',
            'words' => 'Thin content (under 300 words)', 'long_paragraphs' => 'Very long paragraphs', 'sentences' => 'Long sentences',
            'title' => 'SEO title missing or the wrong length', 'description' => 'Meta description missing or the wrong length', 'noindex' => 'Asks not to be indexed',
            'canonical' => 'No canonical link', 'og_image' => 'No social share image', 'schema' => 'No structured data', 'lang' => 'No page language',
            'viewport' => 'No mobile viewport', 'alt' => 'Images without alt text', 'image_dimensions' => 'Images without width and height',
            'heavy_images' => 'Heavy images', 'internal_links' => 'No internal links', 'empty_links' => 'Links without text', 'broken_links' => 'Broken links',
            'kw_set' => 'No focus keyword', 'kw_title' => 'Focus keyword not in the title', 'kw_h1' => 'Focus keyword not in the H1',
            'kw_intro' => 'Focus keyword not in the first 100 words', 'kw_description' => 'Focus keyword not in the description', 'kw_slug' => 'Focus keyword not in the address',
            'kw_subheadings' => 'Focus keyword not in a subheading', 'kw_density' => 'Focus keyword used too little or too much', 'kw_alt' => 'Focus keyword not in any alt text',
        ][$id] ?? $label;
    }

    /** @return list<string> */
    private static function excludedTypes(): array
    {
        $excluded = self::LAYOUT_TYPES;
        try {
            foreach (PostType::all(['slug', 'is_active', 'is_public']) as $type) {
                if (!$type->is_active || !$type->is_public || !falcon_post_type_available($type->slug)
                    || get_cms_option('sitemap_include_'.$type->slug, '1') != '1') {
                    $excluded[] = $type->slug;
                }
            }
        } catch (Throwable $e) {
        }

        return array_values(array_unique($excluded));
    }
}
