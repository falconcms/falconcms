<?php

namespace FalconCms\Core\Support;

use Carbon\CarbonInterface;
use FalconCms\Core\Models\Analytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * How long visitors stay: on the site, on each post, page, product and custom post type, and on
 * each row of the visitor log.
 *
 * Only the browser knows, so every public page carries a few lines that count the time it was
 * visible and in use, and report it as the visitor leaves. A page left open in a background tab,
 * or abandoned on screen, stops counting. The script is part of the page, so it still runs for
 * readers served by the page cache or a CDN.
 */
final class PageTime
{
    public const TABLE = 'cms_page_time_days';

    /** The most one report can add: a browser cannot honestly report more than half an hour. */
    public const MAX_SECONDS = 1800;

    /** With no scroll, click or key for this long, a reader has stopped reading. */
    private const IDLE_MS = 120000;

    /** Gone this long and coming back is a new visit. */
    private const VISIT_GAP_MS = 1800000;

    public static function register(): void
    {
        add_falcon_action('falcon_footer', static fn () => self::footer(), 31);
    }

    public static function enabled(): bool
    {
        return (string) get_cms_option('page_time_enabled', '1') === '1';
    }

    // ── the page ────────────────────────────────────────────────────────

    public static function footer(): void
    {
        // Signed-in pages are never cached, so leaving the script out is enough not to count the
        // site's own team. An error page is not somewhere a visitor came to spend time.
        if (!self::enabled() || auth()->check() || app()->bound('falcon.not_found')
            || request()->routeIs('admin.*') || request()->is('admin/*')) {
            return;
        }

        $post = self::currentPost();
        $id = $post ? (int) $post->id : 0;
        $path = self::path((string) request()->getPathInfo());

        echo self::script($id, $path, self::sign($id, $path), route('falcon.page-time.hit', [], false));
    }

    /** The small script a public page carries. Sends nothing until a second has been spent. */
    public static function script(int $postId, string $path, string $signature, string $url): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $config = json_encode(['i' => $postId, 'p' => $path, 's' => $signature, 'u' => $url], $flags);

        // v: whether the page was visible since the last count. Asking document.hidden instead
        // would lose the last stretch, as it is already true by the time the page says it is leaving.
        return '<script>(function(c){var d=document,w=window,o=Date.now(),a=o,l=o,m=0,f=1,v=!d.hidden;'
            .'function t(){var n=Date.now(),g=n-l;l=n;if(g>15000)g=15000;'
            .'if(v&&(n-a<'.self::IDLE_MS.'||[].some.call(d.querySelectorAll("video,audio"),function(e){return!e.paused}))){m+=g}v=!d.hidden}'
            .'function s(){t();var x=Math.floor(m/1000);if(x<1)return;m-=x*1000;var e=0,n=Date.now();'
            .'if(f){e=1;try{var k=+localStorage.getItem("fpt:last")||0;e=n-k>'.self::VISIT_GAP_MS.'?1:0}catch(_){}}'
            .'try{localStorage.setItem("fpt:last",String(n))}catch(_){}'
            .'var b=new FormData();b.append("i",c.i);b.append("p",c.p);b.append("s",c.s);b.append("d",x);b.append("f",f);b.append("n",e);f=0;'
            .'try{if(navigator.sendBeacon&&navigator.sendBeacon(c.u,b))return}catch(_){}'
            .'try{fetch(c.u,{method:"POST",body:b,keepalive:true,credentials:"omit"})}catch(_){}}'
            .'["scroll","mousemove","mousedown","keydown","touchstart"].forEach(function(e){w.addEventListener(e,function(){a=Date.now()},{passive:true})});'
            .'setInterval(t,5000);'
            .'d.addEventListener("visibilitychange",function(){if(d.hidden){s()}else{l=a=Date.now();v=true}});'
            .'w.addEventListener("pagehide",s);'
            .'})('.$config.');</script>';
    }

    private static function currentPost(): ?object
    {
        try {
            $post = view()->getShared()['current_post'] ?? null;
        } catch (Throwable $e) {
            return null;
        }

        return is_object($post) && isset($post->id) ? $post : null;
    }

    private static function path(string $path): string
    {
        return Str::limit('/'.trim(rawurldecode($path), '/'), 250, '');
    }

    /** Signed, so a stranger cannot fill the table with addresses that do not exist. */
    public static function sign(int $postId, string $path): string
    {
        return substr(hash_hmac('sha256', $postId.'|'.$path, (string) config('app.key')), 0, 24);
    }

    // ── recording ───────────────────────────────────────────────────────

    /** Add a visitor's time to the day's figures, and to their row in the visitor log. */
    public static function record(int $postId, string $path, string $signature, int $seconds, bool $firstReport, bool $newVisit, string $ip, string $userAgent): bool
    {
        if (!self::enabled() || $seconds < 1 || $userAgent === '' || PostViews::isBot($userAgent)
            || !hash_equals(self::sign($postId, $path), $signature)) {
            return false;
        }

        $seconds = min($seconds, self::MAX_SECONDS);
        $views = $firstReport ? 1 : 0;
        $visits = $newVisit ? 1 : 0;

        DB::table(self::TABLE)->upsert(
            [[
                'day' => now()->timezone(PostViews::timezone())->toDateString(),
                'path_hash' => sha1($path),
                'path' => $path,
                'post_id' => $postId > 0 ? $postId : null,
                'views' => $views,
                'visits' => $visits,
                'seconds' => $seconds,
            ]],
            ['day', 'path_hash'],
            [
                'views' => DB::raw(self::TABLE.'.views + '.$views),
                'visits' => DB::raw(self::TABLE.'.visits + '.$visits),
                'seconds' => DB::raw(self::TABLE.'.seconds + '.$seconds),
            ]
        );

        self::addToVisitorLog($path, $seconds, $ip);

        return true;
    }

    /** Best effort: a page served from the cache has no row in the log for its visit. */
    private static function addToVisitorLog(string $path, int $seconds, string $ip): void
    {
        if ($ip === '') {
            return;
        }

        $rows = Analytics::query()->where('ip_address', $ip)->where('created_at', '>=', now()->subHours(6))
            ->orderByDesc('id')->limit(20)->get(['id', 'url']);

        foreach ($rows as $row) {
            if (self::path((string) parse_url((string) $row->url, PHP_URL_PATH)) === $path) {
                Analytics::query()->whereKey($row->id)->update([
                    'duration_seconds' => DB::raw('COALESCE(duration_seconds, 0) + '.$seconds),
                ]);

                return;
            }
        }
    }

    // ── reading ─────────────────────────────────────────────────────────

    /**
     * Totals for a window of days.
     *
     * @return array{views: int, visits: int, seconds: int, avg_visit: int, avg_page: int}
     */
    public static function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        try {
            $row = DB::table(self::TABLE)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('COALESCE(SUM(views),0) as views, COALESCE(SUM(visits),0) as visits, COALESCE(SUM(seconds),0) as seconds')
                ->first();
        } catch (Throwable $e) {
            $row = null;
        }

        $views = (int) ($row->views ?? 0);
        $visits = (int) ($row->visits ?? 0);
        $seconds = (int) ($row->seconds ?? 0);

        return [
            'views' => $views,
            'visits' => $visits,
            'seconds' => $seconds,
            'avg_visit' => $visits > 0 ? (int) round($seconds / $visits) : 0,
            'avg_page' => $views > 0 ? (int) round($seconds / $views) : 0,
        ];
    }

    /**
     * The addresses visitors spent the most time on, one row per post or address.
     *
     * @return list<array{label: string, type: string, url: string, views: int, seconds: int, average: int}>
     */
    public static function top(CarbonInterface $from, CarbonInterface $to, string $type = '', int $limit = 15): array
    {
        try {
            $rows = DB::table(self::TABLE.' as t')
                ->leftJoin('posts as p', 'p.id', '=', 't.post_id')
                ->whereBetween('t.day', [$from->toDateString(), $to->toDateString()])
                ->when($type === 'other', fn ($q) => $q->whereNull('t.post_id'))
                ->when($type !== '' && $type !== 'other', fn ($q) => $q->where('p.type', $type))
                ->groupBy('t.path_hash', 't.path', 't.post_id', 'p.title', 'p.type')
                ->selectRaw('t.path, t.post_id, p.title, p.type, SUM(t.views) as views, SUM(t.seconds) as seconds')
                ->orderByDesc('seconds')->limit(max(1, $limit))->get();
        } catch (Throwable $e) {
            return [];
        }

        return $rows->map(fn ($r) => [
            'label' => (string) ($r->title ?: $r->path),
            'type' => (string) ($r->type ?: 'other'),
            'url' => (string) $r->path,
            'views' => (int) $r->views,
            'seconds' => (int) $r->seconds,
            'average' => (int) $r->views > 0 ? (int) round($r->seconds / $r->views) : 0,
        ])->all();
    }

    /** The content types with time recorded in a window, for the filter. @return list<string> */
    public static function types(CarbonInterface $from, CarbonInterface $to): array
    {
        try {
            return DB::table(self::TABLE.' as t')->leftJoin('posts as p', 'p.id', '=', 't.post_id')
                ->whereBetween('t.day', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("DISTINCT COALESCE(p.type, 'other') as type")
                ->pluck('type')->sort()->values()->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** "45s", "2m 15s", "1h 5m". */
    public static function format(?int $seconds): string
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60).'m '.($seconds % 60).'s';
        }

        return floor($seconds / 3600).'h '.floor(($seconds % 3600) / 60).'m';
    }

    /** Daily: drop figures older than the kept number of days. */
    public static function prune(): int
    {
        try {
            $keep = max(31, (int) get_cms_option('analytics_retention_days', 365));

            return DB::table(self::TABLE)
                ->where('day', '<', now()->timezone(PostViews::timezone())->subDays($keep)->toDateString())
                ->delete();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
