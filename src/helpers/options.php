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

if (!function_exists('falcon_is_sensitive_option')) {
    /**
     * Option keys that decide who gets in and what they may do, or hold a secret. A bulk or
     * file-driven write that is not their own screen (the Customizer import, for one) must
     * never set them, whatever the payload claims: each has a screen of its own that checks
     * the value (Settings → General checks the default role against the person saving it).
     *
     * Covers every protected option ({@see falcon_is_protected_option()}) as well, so a caller
     * that asks this one is never weaker than one that asks that.
     */
    function falcon_is_sensitive_option($key): bool
    {
        return in_array(falcon_option_guard($key), ['protected', 'access', 'secret'], true);
    }
}

if (!function_exists('falcon_option_guard')) {
    /**
     * What kind of guarded option a key is, or null for an ordinary setting:
     *
     *  - 'protected': licence and Pro state ({@see falcon_is_protected_option()}). Never written
     *    from settings input at all; their own services write them.
     *  - 'access': decides who gets in and what they may do (registration, sign-in, the REST API,
     *    the active theme, Falcon Security, Turnstile, licence/freemium state). Only the screen
     *    that owns one, with its own checks, may write it.
     *  - 'code': printed unescaped into every page (head and footer scripts, raw HTML, custom
     *    CSS). The Customizer owns them; anything else that wrote one would put script in front
     *    of every visitor and every signed-in administrator.
     *  - 'secret': a credential by name (API keys, secrets, passwords, tokens, Search Console).
     *    A screen may write the ones it declares; nothing writes them in bulk.
     *
     * {@see falcon_is_sensitive_option()} is protected + access + secret, as it always was.
     */
    function falcon_option_guard($key): ?string
    {
        $key = strtolower(trim((string) $key));
        if ($key === '') {
            return null;
        }
        if (falcon_is_protected_option($key)) {
            return 'protected';
        }

        $code = ['theme_head_script', 'theme_footer_script', 'theme_head_html', 'header_ad_code', 'theme_custom_css', 'theme_footer_copyright'];
        if (in_array($key, $code, true)) {
            return 'code';
        }

        $exact = [
            // Registration and sign-in: who may create an account, with which role, and where.
            'default_role', 'users_can_register', 'require_email_verification',
            'login_url', 'register_url', 'magic_login_enabled', 'allow_multi_device', 'max_devices',
            // The REST API switch and the address the site's notices go to.
            'enable_rest_api', 'admin_email',
            // Which theme's code runs.
            'active_theme',
            // Which roles may use the AI writer (and spend the site's API credit).
            'ai_writer_roles',
        ];
        if (in_array($key, $exact, true)) {
            return 'access';
        }

        // Licence/Pro state, Falcon Security's settings (the lockdown included), Turnstile (bot
        // check on the sign-in and registration forms).
        foreach (['falcon_license', 'falcon_freemium', 'falcon_grandfathered', 'security_', 'turnstile_'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return 'access';
            }
        }
        // Search Console credentials, and credentials by name: API keys, secrets, passwords, tokens.
        if (str_starts_with($key, 'gsc_')) {
            return 'secret';
        }
        foreach (['_key', '_secret', '_password', '_token', '_credentials'] as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return 'secret';
            }
        }

        return null;
    }
}

if (!function_exists('falcon_settings_screen_write')) {
    /**
     * What a settings screen may write from a request, decided before anything is written.
     *
     * $posted is the request's settings (key => value). $declared names every key the screen
     * stores (its own fields, fields registered for it, keys plugins declare for it). $owned
     * names the 'access' keys the screen itself is responsible for, with its own checks.
     *
     * Refused outright (error set, nothing may be written): a protected or code key, an access
     * key the screen does not own, a malformed key, or a nested value for a key not allowed
     * one. Left out (listed in ignored): keys the screen does not declare. Never accepted
     * because a request named them.
     *
     * @param  array<array-key, mixed>  $posted
     * @param  array<int, string>  $declared
     * @param  array<int, string>  $owned
     * @param  array<int, string>  $arrayKeys  keys whose value may be a list (multiselect, tags, repeater)
     * @return array{error: ?string, data: array<string, mixed>, ignored: list<string>}
     */
    function falcon_settings_screen_write(array $posted, array $declared, array $owned = [], array $arrayKeys = []): array
    {
        $declared = array_fill_keys(array_map('strval', $declared), true);
        $owned = array_fill_keys(array_map('strval', $owned), true);
        $arrayKeys = array_fill_keys(array_map('strval', $arrayKeys), true);
        $data = [];
        $ignored = [];
        $refuse = fn (string $why) => ['error' => $why.' Nothing was saved.', 'data' => [], 'ignored' => []];

        foreach ($posted as $key => $value) {
            $key = (string) $key;
            if (!preg_match('/^[A-Za-z0-9_\-]{1,191}$/', $key)) {
                return $refuse('"'.mb_substr($key, 0, 60).'" is not a setting name.');
            }
            $guard = falcon_option_guard($key);
            if ($guard === 'protected' || $guard === 'code' || ($guard === 'access' && !isset($owned[$key]))) {
                return $refuse("\"{$key}\" cannot be changed from this screen.");
            }
            if (!isset($declared[$key])) {
                $ignored[] = $key;

                continue;
            }
            if ((is_array($value) && !isset($arrayKeys[$key])) || is_object($value)) {
                return $refuse("\"{$key}\" takes a single value.");
            }
            $data[$key] = $value;
        }

        return ['error' => null, 'data' => $data, 'ignored' => $ignored];
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
