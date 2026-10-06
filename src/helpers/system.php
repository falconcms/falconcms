<?php

/**
 * Version checks, update checks, outbound HTTP, geo-IP and cache/route housekeeping.
 *
 * Loaded by src/helpers.php.
 */

use Composer\InstalledVersions;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if (!function_exists('falcon_cms_installed_version')) {
    /**
     * The installed code version. version.json ships inside the package and is bumped
     * on every release, so it is the source of truth — it is accurate for both real
     * Composer installs and path-repo/dev installs (where Composer's reported version
     * can be a stale pinned alias). Composer's version is only a last-resort fallback.
     */
    function falcon_cms_installed_version(): string
    {
        // Two possible sources, each unreliable in a different way:
        //  - version.json ships in the code, but a release that forgot to bump it
        //    reports an old number (e.g. v2.0.0 still said 1.8.3).
        //  - Composer's installed version is exact for real installs but bogus for
        //    path-repo / symlink dev installs (reports a stale pinned alias).
        // Taking the HIGHER of the two is correct in every case: a real install on a
        // stale-version.json release is rescued by Composer, and a dev/symlink install
        // (where Composer lies low) is rescued by version.json.
        $fromJson = (defined('FALCON_CMS_VERSION') && preg_match('/^v?\d+\.\d+\.\d+$/', FALCON_CMS_VERSION))
            ? ltrim(FALCON_CMS_VERSION, 'v')
            : null;

        $fromComposer = null;
        if (class_exists(InstalledVersions::class)) {
            try {
                $clean = ltrim((string) InstalledVersions::getPrettyVersion('falconcms/falconcms'), 'v');
                if (preg_match('/^\d+\.\d+\.\d+$/', $clean)) {
                    $fromComposer = $clean;
                }
            } catch (Throwable $e) {
            }
        }

        if ($fromJson && $fromComposer) {
            return version_compare($fromComposer, $fromJson, '>') ? $fromComposer : $fromJson;
        }

        return $fromJson ?? $fromComposer ?? (defined('FALCON_CMS_VERSION') ? FALCON_CMS_VERSION : '1.2.0');
    }
}

if (!function_exists('falcon_check_update')) {
    function falcon_check_update(bool $force = false): array
    {
        $cacheKey = 'falcon_cms_update_check';
        if (!$force && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $current = falcon_cms_installed_version();
        $result = ['current' => $current, 'latest' => null, 'has_update' => false, 'url' => null, 'checked_at' => now()->toDateTimeString()];

        try {
            $res = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'FalconCMS/'.$current])
                ->get('https://repo.packagist.org/p2/falconcms/falconcms.json');

            if ($res->successful()) {
                $versions = $res->json('packages.falconcms/falconcms') ?? [];
                foreach ($versions as $v) {
                    $ver = ltrim($v['version'] ?? '', 'v');
                    if (preg_match('/^\d+\.\d+\.\d+$/', $ver)) {
                        $result['latest'] = $ver;
                        $result['url'] = 'https://packagist.org/packages/falconcms/falconcms';
                        break;
                    }
                }
            }
        } catch (Exception $e) {
        }

        if (!$result['latest']) {
            try {
                // Try GitHub Releases first, fall back to Tags (tags exist even without formal releases)
                $gh = Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/'.$current])
                    ->get('https://api.github.com/repos/falconcms/falconcms/releases/latest');
                if ($gh->successful() && $gh->json('tag_name')) {
                    $tag = ltrim($gh->json('tag_name'), 'v');
                    if ($tag) {
                        $result['latest'] = $tag;
                        $result['url'] = $gh->json('html_url');
                    }
                }
            } catch (Exception $e) {
            }
        }

        if (!$result['latest']) {
            try {
                // Fall back to tags list when no formal GitHub Release exists
                $gh = Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'LazyCMS/'.$current])
                    ->get('https://api.github.com/repos/falconcms/falconcms/tags');
                if ($gh->successful()) {
                    foreach ($gh->json() ?? [] as $t) {
                        $tag = ltrim($t['name'] ?? '', 'v');
                        if (preg_match('/^\d+\.\d+\.\d+$/', $tag)) {
                            $result['latest'] = $tag;
                            $result['url'] = 'https://github.com/falconcms/falconcms/releases/tag/v'.$tag;
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }

        if ($result['latest']) {
            $result['has_update'] = version_compare($result['latest'], $result['current'], '>');
        }

        cache()->put($cacheKey, $result, now()->addHours(6));

        return $result;
    }
}

if (!function_exists('falcon_pro_installed_version')) {
    /** The installed falconcms/pro package version, or null when Pro isn't installed. */
    function falcon_pro_installed_version(): ?string
    {
        try {
            if (class_exists(InstalledVersions::class)
                && InstalledVersions::isInstalled('falconcms/pro')) {
                return ltrim((string) InstalledVersions::getPrettyVersion('falconcms/pro'), 'v');
            }
        } catch (Throwable $e) {
        }

        return null;
    }
}

if (!function_exists('falcon_pro_check_update')) {
    /**
     * Check whether a newer falconcms/pro release is available. The Pro package lives in
     * a private repo (no Packagist), so the latest version is published as a small public
     * manifest (config falcon-options.pro_version_url). Mirrors falcon_check_update().
     *
     * @return array{installed:?string,latest:?string,has_update:bool,installed_pro:bool,url:?string,min_cms:?string,checked_at:string}
     */
    function falcon_pro_check_update(bool $force = false): array
    {
        $cacheKey = 'falcon_pro_update_check';
        if (!$force && cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $installed = falcon_pro_installed_version();
        $result = [
            'installed' => $installed,
            'installed_pro' => $installed !== null,
            'latest' => null,
            'has_update' => false,
            'url' => null,
            'min_cms' => null,
            'checked_at' => now()->toDateTimeString(),
        ];

        $manifestUrl = (string) config('falcon-options.pro_version_url', 'https://falconcms.com/pro-version.json');

        try {
            $res = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'FalconCMS-Pro-Check'])
                ->get($manifestUrl);
            if ($res->successful()) {
                $data = $res->json();
                $latest = ltrim((string) ($data['version'] ?? ''), 'v');
                if (preg_match('/^\d+\.\d+\.\d+$/', $latest)) {
                    $result['latest'] = $latest;
                    $result['url'] = $data['url'] ?? null;
                    $result['min_cms'] = $data['min_cms'] ?? null;
                }
            }
        } catch (Exception $e) {
        }

        // Only meaningful when Pro is actually installed AND a newer version exists.
        if ($result['installed_pro'] && $result['latest'] && $installed) {
            $result['has_update'] = version_compare($result['latest'], $installed, '>');
        }

        cache()->put($cacheKey, $result, now()->addHours(6));

        return $result;
    }
}

if (!function_exists('falcon_refresh_route_cache')) {
    /**
     * Rebuild the route cache, so a setting that routes are built from takes effect.
     *
     * The login and registration slugs are read in routes/web.php while the routes are
     * being registered. On a cached site that file never runs, so changing either one
     * did nothing: the new URL 404'd, the old one kept working, and nothing said why.
     *
     * Rebuilt rather than only cleared — a site that was cached stays cached, instead
     * of quietly losing the speed it was set up with. If the rebuild fails (a host with
     * no writable bootstrap/cache, say) the stale cache is dropped anyway: a site that
     * has to compile its routes each request is slower, but it is correct, and a login
     * URL that silently refuses to change is not something to leave in place.
     */
    function falcon_refresh_route_cache(): void
    {
        try {
            if (!app()->routesAreCached()) {
                return;
            }

            Artisan::call('route:cache');
        } catch (Throwable $e) {
            try {
                Artisan::call('route:clear');
            } catch (Throwable $inner) {
                // Nothing further to try; the next deploy will rebuild it.
            }
        }
    }
}

if (!function_exists('clear_page_cache')) {
    function clear_page_cache()
    {
        try {
            Artisan::call('cache:clear');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_gateway_http')) {
    /**
     * Send a payment-gateway HTTP request with a hard timeout so a slow or
     * unreachable gateway can never hang (or 500) the checkout request.
     *
     * $fn receives a pre-configured PendingRequest and returns the Response.
     * Returns null on any connection/timeout failure (logged) — callers must
     * treat a null response as "not verified / failed", never as success.
     */
    function falcon_gateway_http(callable $fn): ?Response
    {
        try {
            return $fn(Http::timeout(15)->connectTimeout(5));
        } catch (Throwable $e) {
            Log::error('Payment gateway connection error: '.$e->getMessage());

            return null;
        }
    }
}

if (!function_exists('falcon_geoip')) {
    /**
     * Resolve an IP address to geo/network details via ip-api.com, cached for 30 days.
     * Uses the Laravel HTTP client (with a timeout) rather than file_get_contents, which is
     * often disabled or blocked on production hosts. Returns null values when unresolved.
     *
     * @return array{country: ?string, country_code: ?string, city: ?string, region: ?string, isp: ?string}
     */
    function falcon_geoip(?string $ip): array
    {
        $empty = ['country' => null, 'country_code' => null, 'city' => null, 'region' => null, 'isp' => null];
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)
            || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
            return $empty;
        }

        return Cache::remember('falcon_geoip_'.md5($ip), now()->addDays(30), function () use ($ip, $empty) {
            try {
                $resp = Http::timeout(3)
                    ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,countryCode,city,regionName,isp']);
                if ($resp->ok() && $resp->json('status') === 'success') {
                    return [
                        'country' => $resp->json('country'),
                        'country_code' => $resp->json('countryCode'),
                        'city' => $resp->json('city'),
                        'region' => $resp->json('regionName'),
                        'isp' => $resp->json('isp'),
                    ];
                }
            } catch (Throwable $e) {
            }

            return $empty;
        });
    }
}
