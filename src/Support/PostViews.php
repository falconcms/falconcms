<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Post;
use FalconNews\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Post views, in the core: counting a reader, reading the figures back (all time, today, the last
 * 7 or 30 days), writing them for people ("1.2K views", "১২৩ বার পড়া হয়েছে"), ordering posts by
 * them, and the Post Views dynamic source that shows them on any title, text block or button.
 *
 * It began in the Falcon News plugin; every site has it now. Falcon News keeps its Most Viewed
 * screen and reads these figures.
 *
 * A view is counted by a beacon the post page sends once it has loaded, not while the page is
 * built: the page cache, and a CDN in front of the site, serve most readers a page that was built
 * once, so only the browser knows a reader arrived.
 */
final class PostViews
{
    public const TOTALS = 'cms_post_views';

    public const DAYS = 'cms_post_view_days';

    /** The dynamic source key, also usable as the {lazy:post_views} token. */
    public const SOURCE = 'post_views';

    /** Option key => default. */
    public const DEFAULTS = [
        'post_views_enabled' => '1',
        // Comma-separated post type slugs whose single pages are counted.
        'post_views_post_types' => 'post',
        // Signed-in visitors are the site's own team more often than not; off by default.
        'post_views_count_logged_in' => '0',
        // One view per visitor per post in this many minutes (a reload is not a new reader).
        'post_views_window_minutes' => '30',
        // Daily figures older than this are dropped; the all-time total is kept for ever.
        'post_views_keep_days' => '120',
    ];

    /** Period key => label, as the admin and the builder offer them. */
    public const PERIODS = [
        'all' => 'All time',
        'day' => 'Today',
        'week' => 'Last 7 days',
        'month' => 'Last 30 days',
    ];

    /** Order keys the Card element and get_falcon_posts() accept => the period each reads. */
    public const ORDERS = [
        'views' => 'all',
        'views_day' => 'day',
        'views_week' => 'week',
        'views_month' => 'month',
    ];

    /** Requests from these are not readers. Matches the CMS's own analytics list. */
    private const BOTS = '/bot|crawl|spider|slurp|mediapartners|bingpreview|facebookexternalhit|whatsapp|telegram|discordbot|skypeuripreview|preview|fetch|monitor|curl|wget|python-requests|go-http|axios|okhttp|headless|phantomjs|lighthouse|pingdom|uptimerobot|statuscake|gtmetrix|semrush|ahrefs|mj12bot|dotbot|petalbot|dataforseo|censys|zgrab|masscan/i';

    // ── wiring ──────────────────────────────────────────────────────────

    /**
     * Hook the counter, the orders and the dynamic source into the CMS. A Falcon News from before
     * the move (1.0.x) does all of this itself; while it is the one running, these stand aside,
     * so a view is neither counted twice nor offered twice.
     */
    public static function register(): void
    {
        add_falcon_action('falcon_footer', static fn () => self::legacyNews() ? null : self::footer(), 30);
        add_falcon_filter('falcon_posts_orderby', static fn ($handled = false, $query = null, $orderby = '', $order = 'desc') => self::legacyNews() ? $handled : self::orderBy((bool) $handled, $query, (string) $orderby, (string) $order));
        add_falcon_filter('falcon_post_orderby_options', static fn ($options = []) => self::legacyNews() ? $options : (array) $options + [
            'views_day' => 'Views — today',
            'views_week' => 'Views — last 7 days',
            'views_month' => 'Views — last 30 days',
        ]);
        add_falcon_action('falcon_posts_fetched', static fn ($posts = null) => self::legacyNews() ? null : self::preloadPosts($posts));
        add_falcon_filter('falcon_dynamic_sources', static fn ($sources = []) => self::legacyNews() ? $sources : self::registerSource((array) $sources));
        add_falcon_filter('falcon_dynamic_value', static fn ($value = null, $source = '', $post = null, $config = []) => self::legacyNews() ? $value : self::resolve($value, $source, $post, $config));
    }

    /**
     * Whether an older Falcon News, which counts views on its own, is the one running: its
     * classes are loaded (it is active) and it does not say the core counts now.
     */
    public static function legacyNews(): bool
    {
        return class_exists(Settings::class)
            && !defined(Settings::class.'::VIEWS_IN_CORE');
    }

    // ── options ─────────────────────────────────────────────────────────

    public static function option(string $key): string
    {
        return (string) get_cms_option($key, self::DEFAULTS[$key] ?? '');
    }

    public static function on(string $key): bool
    {
        return self::option($key) === '1';
    }

    /** @return list<string> */
    public static function postTypes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', self::option('post_views_post_types')))));
    }

    private static function int(string $key, int $min, int $max): int
    {
        return max($min, min($max, (int) self::option($key)));
    }

    // ── counting ────────────────────────────────────────────────────────

    /**
     * Count one view of a post, unless it should not count: a bot, a post that is not
     * published or not of a counted type, or the same visitor again within the window.
     * Returns whether it was counted.
     */
    public static function record(int $postId, string $ip, string $userAgent): bool
    {
        if ($postId < 1 || !self::on('post_views_enabled')) {
            return false;
        }
        if ($userAgent === '' || self::isBot($userAgent)) {
            return false;
        }
        if (!self::trackable($postId)) {
            return false;
        }

        // The browser keeps its own note of the window too; this catches a script that does not.
        $window = self::int('post_views_window_minutes', 0, 1440);
        if ($window > 0 && !Cache::add('post_view_'.sha1($postId.'|'.$ip.'|'.$userAgent), 1, now()->addMinutes($window))) {
            return false;
        }

        self::increment($postId);

        return true;
    }

    public static function isBot(string $userAgent): bool
    {
        return (bool) preg_match(self::BOTS, $userAgent);
    }

    /** Add views to a post's total and to today's row. */
    public static function increment(int $postId, int $by = 1, ?Carbon $at = null): void
    {
        $at ??= now();
        $day = $at->copy()->timezone(self::timezone())->toDateString();

        DB::table(self::TOTALS)->upsert(
            [['post_id' => $postId, 'views' => $by, 'last_viewed_at' => $at]],
            ['post_id'],
            ['views' => DB::raw(self::TOTALS.'.views + '.$by), 'last_viewed_at' => $at]
        );
        DB::table(self::DAYS)->upsert(
            [['post_id' => $postId, 'day' => $day, 'views' => $by]],
            ['post_id', 'day'],
            ['views' => DB::raw(self::DAYS.'.views + '.$by)]
        );

        falcon_request_memo('post_views')->exchangeArray([]);
    }

    /** Whether a post is one whose views are counted: published, of a counted type. */
    public static function trackable(int $postId): bool
    {
        $types = self::postTypes();

        return (bool) Cache::remember('post_view_trackable_'.$postId.'_'.md5(implode(',', $types)), now()->addMinutes(10), function () use ($postId, $types) {
            $post = Post::query()->whereKey($postId)->first(['id', 'type', 'status']);

            return $post && $post->status === 'published' && in_array($post->type, $types, true);
        });
    }

    // ── the beacon ──────────────────────────────────────────────────────

    /** On the footer hook: print the script when the page is a counted post. */
    public static function footer(): void
    {
        $post = self::currentPost();
        if (!$post || !self::wanted($post)) {
            return;
        }

        $window = self::int('post_views_window_minutes', 0, 1440) * 60000;

        // Relative, so a page cached under one address (http, www) still reports to its own origin.
        echo self::script((int) $post->id, route('falcon.post-views.hit', [], false), $window);
    }

    /**
     * The few lines a counted post page carries. Part of the page, so it is cached with it and
     * still runs for every reader the page cache (or a CDN) serves. The browser remembers each
     * post it reported for the counting window, and reports with sendBeacon, so it costs the
     * reader nothing and never holds the page up.
     */
    public static function script(int $postId, string $url, int $windowMs): string
    {
        $url = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return '<script>(function(){var id='.$postId.',w='.$windowMs.',k="fnv:"+id,n=Date.now();'
            .'try{var l=+localStorage.getItem(k)||0;if(w&&n-l<w)return;localStorage.setItem(k,String(n));}catch(e){}'
            .'var u='.$url.',d=new FormData();d.append("id",id);'
            .'try{if(navigator.sendBeacon&&navigator.sendBeacon(u,d))return;}catch(e){}'
            .'try{fetch(u,{method:"POST",body:d,keepalive:true,credentials:"omit"});}catch(e){}'
            .'})();</script>';
    }

    /** Whether this rendering of the post should report a view. */
    private static function wanted(object $post): bool
    {
        if (!self::on('post_views_enabled')) {
            return false;
        }
        if (($post->status ?? '') !== 'published' || !in_array($post->type ?? '', self::postTypes(), true)) {
            return false;
        }
        // The builder's own preview frames and the admin are not readers.
        if (request()->routeIs('admin.*') || request()->is('admin/*')) {
            return false;
        }
        // Signed-in pages are never cached, so leaving the script out is enough to not count them.
        if (auth()->check() && !self::on('post_views_count_logged_in')) {
            return false;
        }

        return true;
    }

    /** The post the single page is showing (the front end shares it as current_post). */
    private static function currentPost(): ?object
    {
        try {
            $post = view()->getShared()['current_post'] ?? null;
        } catch (Throwable $e) {
            return null;
        }

        return is_object($post) && isset($post->id) ? $post : null;
    }

    // ── reading ─────────────────────────────────────────────────────────

    /** A post's views in a period ('all', 'day', 'week', 'month'). */
    public static function count(int $postId, string $period = 'all'): int
    {
        $period = isset(self::PERIODS[$period]) ? $period : 'all';
        $memo = falcon_request_memo('post_views');
        $key = $period.':'.$postId;
        if (!$memo->offsetExists($key)) {
            self::preload([$postId], $period);
        }

        return (int) ($memo[$key] ?? 0);
    }

    /**
     * Load the views of many posts in one query, so a grid of cards or the admin list does not
     * ask once per post.
     *
     * @param  iterable<int>  $postIds
     */
    public static function preload(iterable $postIds, string $period = 'all'): void
    {
        $period = isset(self::PERIODS[$period]) ? $period : 'all';
        $memo = falcon_request_memo('post_views');
        $ids = [];
        foreach ($postIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !$memo->offsetExists($period.':'.$id)) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return;
        }

        try {
            $rows = $period === 'all'
                ? DB::table(self::TOTALS)->whereIn('post_id', $ids)->pluck('views', 'post_id')
                : DB::table(self::DAYS)->whereIn('post_id', $ids)->where('day', '>=', self::since($period))
                    ->groupBy('post_id')->select('post_id', DB::raw('SUM(views) as total'))->pluck('total', 'post_id');
        } catch (Throwable $e) {
            $rows = collect(); // tables not migrated yet
        }

        foreach ($ids as $id) {
            $memo[$period.':'.$id] = (int) ($rows[$id] ?? 0);
        }
    }

    /** On falcon_posts_fetched: load the counts for a whole loop at once. */
    public static function preloadPosts($posts): void
    {
        $ids = [];
        foreach ($posts ?? [] as $post) {
            if (isset($post->id)) {
                $ids[] = (int) $post->id;
            }
        }
        self::preload($ids, 'all');
    }

    /**
     * The most viewed posts in a period, most first.
     *
     * @return list<array{post_id: int, views: int}>
     */
    public static function top(string $period = 'week', int $limit = 10, array $types = []): array
    {
        $period = isset(self::PERIODS[$period]) ? $period : 'week';
        $query = $period === 'all'
            ? DB::table(self::TOTALS.' as v')->select('v.post_id', 'v.views')
            : DB::table(self::DAYS.' as v')->where('v.day', '>=', self::since($period))
                ->groupBy('v.post_id')->select('v.post_id', DB::raw('SUM(v.views) as views'));

        $query->join('posts', 'posts.id', '=', 'v.post_id')->whereNull('posts.deleted_at')
            ->where('posts.status', 'published');
        if ($types !== []) {
            $query->whereIn('posts.type', $types);
        }

        return $query->orderByDesc('views')->orderByDesc('v.post_id')->limit(max(1, $limit))->get()
            ->map(fn ($r) => ['post_id' => (int) $r->post_id, 'views' => (int) $r->views])->all();
    }

    /** Every view in a period, across the site. */
    public static function total(string $period = 'all'): int
    {
        try {
            return $period === 'all'
                ? (int) DB::table(self::TOTALS)->sum('views')
                : (int) DB::table(self::DAYS)->where('day', '>=', self::since($period))->sum('views');
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** The first day (Y-m-d, in the site's timezone) a period covers. */
    public static function since(string $period): string
    {
        $today = now()->timezone(self::timezone())->startOfDay();

        return match ($period) {
            'week' => $today->subDays(6)->toDateString(),
            'month' => $today->subDays(29)->toDateString(),
            default => $today->toDateString(),
        };
    }

    /** Drop daily rows older than the kept number of days. Returns how many went. */
    public static function prune(): int
    {
        $keep = self::int('post_views_keep_days', 31, 3650);
        $before = now()->timezone(self::timezone())->startOfDay()->subDays($keep)->toDateString();

        return DB::table(self::DAYS)->where('day', '<', $before)->delete();
    }

    // ── writing ─────────────────────────────────────────────────────────

    /**
     * A view count for people. $style: 'full' (12,345), 'short' (12.3K) or 'plain' (12345).
     * $label goes after the number ("views"), or around it when it holds {count}.
     */
    public static function format(int $count, string $style = 'full', string $label = '', string $digits = ''): string
    {
        $number = match ($style) {
            'short' => self::short($count),
            'plain' => (string) $count,
            default => number_format($count),
        };

        $label = trim($label);
        $text = $label === '' ? $number : (str_contains($label, '{count}') ? str_replace('{count}', $number, $label) : $number.' '.$label);

        return function_exists('falcon_localize_digits') ? falcon_localize_digits($text, $digits) : $text;
    }

    private static function short(int $n): string
    {
        foreach ([1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'K'] as $size => $suffix) {
            if ($n >= $size) {
                $value = $n / $size;

                // One decimal while it says something (1.2K, 12.3K), none past that (123K).
                $text = number_format($value, $value < 100 ? 1 : 0, '.', '');

                return (str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text).$suffix;
            }
        }

        return (string) $n;
    }

    // ── the dynamic source ──────────────────────────────────────────────

    /** On falcon_dynamic_sources: the picker entry and its options. */
    public static function registerSource(array $sources): array
    {
        $sources['text'][] = [
            'key' => self::SOURCE,
            'label' => 'Post Views',
            'icon' => 'fa-eye',
            'group' => 'Post',
            'sample' => '1,234 views',
            'subFields' => [
                ['key' => 'dynamic_views_period', 'label' => 'Period', 'type' => 'select', 'options' => array_map(
                    fn ($value, $label) => ['value' => (string) $value, 'label' => $label], array_keys(self::PERIODS), self::PERIODS)],
                ['key' => 'dynamic_views_format', 'label' => 'Number', 'type' => 'select', 'options' => [
                    ['value' => 'full', 'label' => '12,345'],
                    ['value' => 'short', 'label' => '12.3K'],
                    ['value' => 'plain', 'label' => '12345'],
                ]],
                ['key' => 'dynamic_views_label', 'label' => 'Label', 'type' => 'text', 'placeholder' => 'views  (or e.g. Read {count} times)'],
                ['key' => 'dynamic_digits', 'label' => 'Digits', 'type' => 'select', 'options' => [
                    ['value' => '', 'label' => '0123 (Latin)'],
                    ['value' => 'bengali', 'label' => '০১২৩ (Bangla)'],
                    ['value' => 'devanagari', 'label' => '०१२३ (Devanagari)'],
                    ['value' => 'arabic', 'label' => '٠١٢٣ (Arabic)'],
                ]],
                ['key' => 'dynamic_views_min', 'label' => 'Hide below', 'type' => 'number', 'placeholder' => '0 (always show)'],
                ['key' => 'dynamic_before', 'label' => 'Before', 'type' => 'text', 'placeholder' => 'Text before value'],
                ['key' => 'dynamic_after', 'label' => 'After', 'type' => 'text', 'placeholder' => 'Text after value'],
                ['key' => 'dynamic_fallback', 'label' => 'Fallback', 'type' => 'text', 'placeholder' => 'Shown when hidden'],
            ],
        ];

        return $sources;
    }

    /** On falcon_dynamic_value: the count for the post being rendered. */
    public static function resolve($value, $source = '', $post = null, $config = [])
    {
        if ($source !== self::SOURCE || !is_object($post) || empty($post->id)) {
            return $value;
        }

        $s = is_array($config['settings'] ?? null) ? $config['settings'] : [];
        $count = self::count((int) $post->id, (string) ($s['dynamic_views_period'] ?? 'all'));

        // Below the threshold the value is empty, so the element's fallback shows instead:
        // "3 views" on a story an hour old reads worse than nothing.
        if ($count < max(0, (int) ($s['dynamic_views_min'] ?? 0))) {
            return '';
        }

        $label = array_key_exists('dynamic_views_label', $s) ? (string) $s['dynamic_views_label'] : 'views';

        return self::format($count, (string) ($s['dynamic_views_format'] ?? 'full'), $label, (string) ($config['digits'] ?? ($s['dynamic_digits'] ?? '')));
    }

    // ── ordering ────────────────────────────────────────────────────────

    /**
     * Answer get_falcon_posts()'s falcon_posts_orderby filter for the view orders. Posts with
     * the same count keep the newest first.
     *
     * @param  Builder<Post>  $query
     */
    public static function orderBy(bool $handled, $query, string $orderby, string $order): bool
    {
        if ($handled || !isset(self::ORDERS[$orderby]) || !$query) {
            return $handled;
        }

        $period = self::ORDERS[$orderby];
        $sub = $period === 'all'
            ? DB::table(self::TOTALS)->select('views')->whereColumn(self::TOTALS.'.post_id', 'posts.id')
            : DB::table(self::DAYS)->selectRaw('SUM(views)')->whereColumn(self::DAYS.'.post_id', 'posts.id')
                ->where(self::DAYS.'.day', '>=', self::since($period));

        self::orderBySub($query, $sub, $order === 'asc' ? 'asc' : 'desc');
        $query->orderByDesc('published_at')->orderByDesc('id');

        return true;
    }

    /** @param  Builder<Post>  $query */
    private static function orderBySub($query, QueryBuilder $sub, string $direction): void
    {
        $query->orderByRaw('COALESCE(('.$sub->toSql().'), 0) '.$direction, $sub->getBindings());
    }

    /** The site's timezone, the one "today" is counted in. */
    public static function timezone(): string
    {
        return function_exists('cms_timezone') ? (string) cms_timezone() : (string) config('app.timezone', 'UTC');
    }
}
