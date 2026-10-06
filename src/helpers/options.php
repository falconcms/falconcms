<?php

/**
 * CMS options (settings store), per-request memo and option guards.
 *
 * Loaded by src/helpers.php.
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('_falcon_cms_options_store')) {
    function &_falcon_cms_options_store(): array
    {
        static $store = ['loaded' => false, 'data' => []];

        return $store;
    }
}

if (!function_exists('get_cms_option')) {
    function get_cms_option($key, $default = null)
    {
        try {
            $store = &_falcon_cms_options_store();

            // Bulk-load all settings on first use. Cross-request cache (Redis/file/etc.)
            // so most requests skip the DB entirely; per-request static store avoids
            // repeat cache hits. Invalidated by forget_cms_options_cache() on every write.
            if (!$store['loaded']) {
                $store['data'] = Cache::remember(
                    'falcon:cms_options',
                    now()->addHour(),
                    function () {
                        $data = [];
                        foreach (DB::table('cms_settings')->get(['key', 'value']) as $row) {
                            $data[$row->key] = $row->value;
                        }

                        return $data;
                    }
                );
                $store['loaded'] = true;
            }

            $localeKey = $key.'_'.app()->getLocale();

            if (array_key_exists($localeKey, $store['data'])) {
                return $store['data'][$localeKey] ?? $default;
            }
            if (array_key_exists($key, $store['data'])) {
                return $store['data'][$key] ?? $default;
            }

            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('update_cms_option')) {
    function update_cms_option($key, $value)
    {
        try {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
            // Drop the cross-request cache and the per-request store so the new
            // value is picked up immediately (here and on the next request).
            forget_cms_options_cache();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('forget_cms_options_cache')) {
    /**
     * Invalidate the CMS options cache. Call after ANY direct write to the
     * cms_settings table so cached settings never go stale.
     *
     * If the shared cache cannot be invalidated, say so. Swallowing that failure
     * produced the worst class of bug this CMS has had: the write lands in the
     * database, every later request keeps reading the old value out of cache, and
     * the setting simply appears not to save — until the entry expires an hour
     * later and it starts working on its own. The usual cause is a cache file left
     * owned by another user, which happens the moment anyone runs `php artisan`
     * as root over SSH while the site itself runs as www-data.
     *
     * This is reported at ERROR, not warning. A production .env routinely carries
     * LOG_LEVEL=error, and at warning the message was filtered out on the one kind
     * of site that needs it — new.falconcms.com sat with an unclearable cache for
     * two weeks and logged nothing at all. A setting that silently refuses to
     * apply is a broken site, so it belongs at the level people actually keep.
     */
    function forget_cms_options_cache(): void
    {
        try {
            $forgotten = Cache::forget('falcon:cms_options');

            if ($forgotten === false && Cache::has('falcon:cms_options')) {
                throw new RuntimeException('Cache::forget() reported failure and the entry is still present.');
            }
        } catch (Throwable $e) {
            try {
                Log::error(
                    'FalconCMS: could not clear the settings cache, so saved settings will keep reading '
                    .'their old values until it expires. This is usually a cache file owned by another '
                    .'user — check the ownership of storage/framework/cache (it must be writable by the '
                    .'user the site runs as). Cause: '.$e->getMessage()
                );
            } catch (Throwable $ignored) {
                // Logging must never be the thing that breaks a settings save.
            }
        }

        // The per-request store is cleared either way, so the request doing the
        // write always sees its own change even when the shared cache is stuck.
        $store = &_falcon_cms_options_store();
        $store['loaded'] = false;
        $store['data'] = [];
    }
}

if (!function_exists('falcon_email_verification_required')) {
    /**
     * Must a user confirm their email address before they can sign in?
     *
     * Settings → Membership. The answer was read in four places — both controllers that
     * act on it and the settings screen that draws the checkbox — each spelling out the
     * default for itself, which is three chances for them to disagree about what an
     * unconfigured site does. A screen showing the box ticked while the login screen
     * lets everyone through is not a difference anyone would go looking for.
     *
     * The default is on, and stays on, for a site that has never chosen. Turning it off
     * under a running site would let anyone who had registered and never confirmed sign
     * in the next time it was updated, which is not a change to make on a site owner's
     * behalf. A NEW install writes '0' explicitly instead — see falcon:install — so the
     * person who just created it can sign in without going looking for a mail server,
     * and the setting is theirs to turn on whenever they want it.
     */
    function falcon_email_verification_required(): bool
    {
        return get_cms_option('require_email_verification', '1') === '1';
    }
}

if (!function_exists('falcon_is_protected_option')) {
    /**
     * Option keys that must NEVER be written from user-supplied settings input —
     * generic settings saves ($request->except), injected fields, options pages,
     * etc. These are managed internally (licensing / Pro entitlement); letting a
     * crafted request or a registered field overwrite them would forge a license
     * and bypass Pro gating. Internal code writes them through their own services.
     */
    function falcon_is_protected_option($key): bool
    {
        $key = strtolower(trim((string) $key));
        if ($key === '') {
            return false;
        }

        // Any current/future license key or cached license state.
        if (str_starts_with($key, 'falcon_license')) {
            return true;
        }

        $protected = [
            'falcon_grandfathered_features', // grants grandfathered Pro access
        ];

        return in_array($key, $protected, true);
    }
}

if (!function_exists('falcon_request_memo')) {
    /**
     * A scratchpad that lives exactly as long as the application instance does.
     *
     * Several helpers are asked the same question many times while rendering one page, and
     * memoising the answer is worth it. Doing that in a `static` is what you reach for first
     * and is wrong twice over: under Octane or inside a queue worker the static survives into
     * the next request — serving stale data, and in the case of anything user-scoped, serving
     * one visitor's data to the next — and in tests it carries fixtures across cases.
     *
     * Keyed per caller so two helpers never collide.
     */
    function falcon_request_memo(string $key): ArrayObject
    {
        $binding = 'falcon.memo.'.$key;

        if (!app()->bound($binding)) {
            app()->instance($binding, new ArrayObject);
        }

        return app($binding);
    }
}
