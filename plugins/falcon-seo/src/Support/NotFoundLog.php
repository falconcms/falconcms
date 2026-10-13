<?php

namespace FalconSeo\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Remembers the addresses that answered 404, so the owner can see broken links and redirect
 * them in one click (Redirects screen).
 *
 * Only pages are counted, not missing files: a request for a .css, .js, image or font is
 * left out, since those come from themes and bots rather than from links people follow.
 * The write happens after the response has gone out, one upsert per 404. Entries not seen
 * for 90 days are dropped when the list is read. On by default; SEO settings can turn it off.
 */
final class NotFoundLog
{
    public const KEEP_DAYS = 90;

    private const ASSET = '/\.(css|js|mjs|map|png|jpe?g|gif|webp|avif|svg|ico|bmp|woff2?|ttf|otf|eot|mp4|webm|mp3|wav|json|xml|txt|zip|gz)$/i';

    public static function enabled(): bool
    {
        return (string) get_cms_option('seo_404_log', '1') !== '0';
    }

    /** Called by RedirectMiddleware with every front-end response. */
    public static function observe(Request $request, $response): void
    {
        if (!method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 404) {
            return;
        }
        if (!$request->isMethod('get') || $request->is('admin*') || $request->is('api*') || !self::enabled()) {
            return;
        }
        $path = '/'.ltrim(rawurldecode($request->path()), '/');
        if (preg_match(self::ASSET, $path) || mb_strlen($path) > 500) {
            return;
        }
        $referrer = mb_substr((string) $request->headers->get('referer', ''), 0, 1000) ?: null;

        // Queued, then written once after the response. A callback per 404 would pile up on an
        // application that serves many requests (Octane, tests) and count each 404 again on
        // every later request.
        self::$pending[] = [$path, $referrer];
        if (!self::$flushScheduled) {
            self::$flushScheduled = true;
            app()->terminating(fn () => self::flush());
        }
    }

    /** @var list<array{0: string, 1: ?string}> */
    private static array $pending = [];

    private static bool $flushScheduled = false;

    public static function flush(): void
    {
        $pending = self::$pending;
        self::$pending = [];
        self::$flushScheduled = false;
        foreach ($pending as [$path, $referrer]) {
            self::record($path, $referrer);
        }
    }

    public static function record(string $path, ?string $referrer = null): void
    {
        try {
            $hash = sha1($path);
            $now = now();
            $updated = DB::table('cms_not_found_log')->where('path_hash', $hash)->update([
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => $now,
            ] + ($referrer ? ['referrer' => $referrer] : []));
            if (!$updated) {
                DB::table('cms_not_found_log')->insertOrIgnore([
                    'path_hash' => $hash, 'path' => $path, 'hits' => 1, 'referrer' => $referrer,
                    'first_seen_at' => $now, 'last_seen_at' => $now,
                ]);
            }
        } catch (Throwable $e) {
            // No table yet (before migrating): nothing to remember into.
        }
    }

    /** The most-hit missing addresses, newest activity first among equals. */
    public static function top(int $limit = 50): array
    {
        try {
            DB::table('cms_not_found_log')->where('last_seen_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

            return DB::table('cms_not_found_log')->orderByDesc('hits')->orderByDesc('last_seen_at')->limit($limit)->get()->all();
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Forget an address (redirected or dismissed). */
    public static function forget(string $path): void
    {
        try {
            DB::table('cms_not_found_log')->where('path_hash', sha1('/'.ltrim($path, '/')))->delete();
        } catch (Throwable $e) {
        }
    }
}
