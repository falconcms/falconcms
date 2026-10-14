<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SEO → Rank Tracker (Pro, with DataForSEO): chosen keywords looked up in Google, country by
 * country and on desktop or mobile, once a week or once a day. Each check is the live page of
 * results, so it shows where the site really stands, keywords Search Console has no figures
 * for included, and what else that page shows (featured snippet, People also ask, videos…).
 *
 * Every check is one billed DataForSEO request: a keyword is checked only when it is due, a
 * few an hour (falcon:seo-rank-check), and the list is capped.
 */
final class RankTracker
{
    public const TABLE = 'cms_seo_rank_keywords';

    public const HISTORY = 'cms_seo_rank_history';

    /** How often each keyword is checked: daily or weekly (weekly costs a seventh). */
    public const FREQUENCY = 'seo_rank_frequency';

    public const FREQUENCIES = ['weekly' => 'Once a week', 'daily' => 'Once a day'];

    public const MAX_KEYWORDS = 200;

    /** Keywords added in one go. */
    public const MAX_ADD = 20;

    public const DEVICES = ['desktop' => 'Desktop', 'mobile' => 'Mobile'];

    /** DataForSEO's names for what a page of results can show, as people know them. */
    public const FEATURES = [
        'featured_snippet' => 'Featured snippet', 'ai_overview' => 'AI overview', 'people_also_ask' => 'People also ask',
        'video' => 'Videos', 'images' => 'Images', 'local_pack' => 'Local pack', 'map' => 'Map', 'top_stories' => 'Top stories',
        'knowledge_graph' => 'Knowledge panel', 'shopping' => 'Shopping', 'popular_products' => 'Products', 'paid' => 'Ads',
        'discussions_and_forums' => 'Forums', 'perspectives' => 'Perspectives', 'twitter' => 'X posts', 'recipes' => 'Recipes',
        'jobs' => 'Jobs', 'events' => 'Events', 'hotels_pack' => 'Hotels', 'related_searches' => 'Related searches',
        'people_also_search' => 'People also search', 'carousel' => 'Carousel', 'short_videos' => 'Short videos',
    ];

    public static function available(): bool
    {
        return DataForSeo::available();
    }

    public static function frequency(): string
    {
        $value = (string) get_cms_option(self::FREQUENCY, 'weekly');

        return array_key_exists($value, self::FREQUENCIES) ? $value : 'weekly';
    }

    /** "  Laravel   CMS " → "laravel cms". */
    public static function normalize(string $keyword): string
    {
        return mb_substr(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $keyword) ?? $keyword)), 0, 200);
    }

    /**
     * Follow keywords (one per line) in a country, on a device. Search volume and difficulty
     * are read once for each new one.
     *
     * @return array{added: list<int>, skipped: int, full: bool}
     */
    public static function add(string $lines, string $country, string $device): array
    {
        $country = strtolower($country);
        $device = array_key_exists($device, self::DEVICES) ? $device : 'desktop';
        $keywords = array_values(array_unique(array_filter(array_map([self::class, 'normalize'], preg_split('/[\r\n,]+/', $lines) ?: []))));
        $room = max(0, self::MAX_KEYWORDS - DB::table(self::TABLE)->count());

        $added = [];
        $skipped = 0;
        foreach (array_slice($keywords, 0, self::MAX_ADD) as $keyword) {
            if (DB::table(self::TABLE)->where(compact('keyword', 'country', 'device'))->exists()) {
                $skipped++;

                continue;
            }
            if (count($added) >= $room) {
                break;
            }
            $metrics = [];
            try {
                $metrics = DataForSeo::metrics($keyword, [$country])[0] ?? [];
            } catch (Throwable $e) {
                // The position matters more; the numbers can be missing.
            }
            $added[] = (int) DB::table(self::TABLE)->insertGetId([
                'keyword' => $keyword, 'country' => $country, 'device' => $device,
                'search_volume' => $metrics['searches'] ?? null,
                'difficulty' => $metrics['difficulty'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['added' => $added, 'skipped' => $skipped, 'full' => count($added) < count($keywords) - $skipped];
    }

    public static function remove(int $id): void
    {
        DB::table(self::HISTORY)->where('keyword_id', $id)->delete();
        DB::table(self::TABLE)->where('id', $id)->delete();
    }

    /** Keywords never checked, then the longest unchecked, that are due by the frequency. */
    public static function due(int $limit): array
    {
        $before = self::frequency() === 'daily' ? now()->subHours(23) : now()->subDays(7)->addHour();

        return DB::table(self::TABLE)
            ->where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<=', $before))
            ->orderByRaw('checked_at IS NOT NULL')->orderBy('checked_at')->orderBy('id')
            ->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Check the keywords that are due; returns how many were checked. */
    public static function runDue(int $limit = 20): int
    {
        if (!self::available()) {
            return 0;
        }
        $ids = self::due($limit);
        foreach ($ids as $id) {
            self::check($id);
        }

        return count($ids);
    }

    /**
     * Look one keyword up in Google now. A failed check is kept as the row's error and counts
     * as a check, so it is not billed again every hour; the next one comes when it is due.
     */
    public static function check(int $id): ?object
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$row) {
            return null;
        }
        try {
            $serp = DataForSeo::serp((string) $row->keyword, (string) $row->country, (string) $row->device);
        } catch (Throwable $e) {
            DB::table(self::TABLE)->where('id', $id)->update(['error' => mb_substr($e->getMessage(), 0, 500), 'checked_at' => now(), 'updated_at' => now()]);

            return DB::table(self::TABLE)->where('id', $id)->first();
        }

        $position = $serp['position'];
        $best = $row->best_position === null ? $position : ($position === null ? (int) $row->best_position : min((int) $row->best_position, $position));
        DB::table(self::TABLE)->where('id', $id)->update([
            // The position before this check, for the change; only once there is one.
            'previous_position' => $row->checked_at !== null && $row->error === null ? $row->position : $row->previous_position,
            'position' => $position,
            'best_position' => $best,
            'url' => $serp['url'],
            'serp_features' => json_encode($serp['features']),
            'snippet_owned' => $serp['snippet_owned'],
            'leader' => $serp['leader'],
            'error' => null,
            'checked_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(self::HISTORY)->updateOrInsert(['keyword_id' => $id, 'checked_on' => now()->toDateString()], ['position' => $position, 'url' => $serp['url']]);

        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    /**
     * Every tracked keyword for the screen, with its change and its last checks (oldest first,
     * for the small chart).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        $rows = DB::table(self::TABLE)->orderByRaw('position IS NULL')->orderBy('position')->orderBy('keyword')->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $history = DB::table(self::HISTORY)->whereIn('keyword_id', $rows->pluck('id'))->where('checked_on', '>=', now()->subDays(120)->toDateString())
            ->orderBy('checked_on')->get(['keyword_id', 'checked_on', 'position'])->groupBy('keyword_id');

        return $rows->map(function ($row) use ($history) {
            $position = $row->position === null ? null : (int) $row->position;
            $previous = $row->previous_position === null ? null : (int) $row->previous_position;
            $past = ($history->get($row->id) ?? collect())->map(fn ($h) => $h->position === null ? null : (int) $h->position)->values()->all();
            // The check before the latest, when there was one: did it find the site?
            $before = count($past) >= 2 ? ['position' => $past[count($past) - 2]] : null;

            return [
                'id' => (int) $row->id,
                'keyword' => (string) $row->keyword,
                'country' => (string) $row->country,
                'device' => (string) $row->device,
                'searches' => $row->search_volume === null ? null : (int) $row->search_volume,
                'difficulty' => $row->difficulty === null ? null : (int) $row->difficulty,
                'position' => $position,
                'best' => $row->best_position === null ? null : (int) $row->best_position,
                // Up is good: from 9 to 4 is +5. Into the top 100 or out of it shows as such.
                'change' => $position !== null && $previous !== null ? $previous - $position : null,
                'entered' => $position !== null && $before !== null && $before['position'] === null,
                'lost' => $position === null && $before !== null && $before['position'] !== null,
                'url' => $row->url,
                'features' => array_values(array_filter((array) json_decode((string) $row->serp_features, true), 'is_string')),
                'snippet_owned' => (bool) $row->snippet_owned,
                'leader' => $row->leader,
                'error' => $row->error,
                'checked_at' => $row->checked_at,
                'history' => $past,
            ];
        })->all();
    }

    /** How the tracked keywords stand: in the top 3, top 10, top 100, and not found. */
    public static function summary(array $rows): array
    {
        $checked = array_filter($rows, fn ($r) => $r['checked_at'] !== null && $r['error'] === null);
        $positions = array_column($checked, 'position');

        return [
            'total' => count($rows),
            'top3' => count(array_filter($positions, fn ($p) => $p !== null && $p <= 3)),
            'top10' => count(array_filter($positions, fn ($p) => $p !== null && $p <= 10)),
            'top100' => count(array_filter($positions, fn ($p) => $p !== null)),
            'missing' => count(array_filter($positions, fn ($p) => $p === null)),
            'up' => count(array_filter($checked, fn ($r) => ($r['change'] ?? 0) > 0)),
            'down' => count(array_filter($checked, fn ($r) => ($r['change'] ?? 0) < 0 || $r['lost'])),
            'snippets' => count(array_filter($checked, fn ($r) => $r['snippet_owned'])),
        ];
    }
}
