<?php

namespace FalconCms\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile on the site's own forms: the login page, registration, forgot password,
 * the shop's My Account sign-in, and any a plugin adds (Falcon Wholesale: its login and its
 * application form).
 *
 * Each form is switched on separately in Settings → Integrations, once the site and secret keys
 * are saved there. A form shows the widget with falcon_turnstile('location'), and its controller
 * checks the answer with falcon_turnstile_check($request, 'location').
 *
 * If Cloudflare cannot be reached, the form goes through (and the outage is logged): a captcha
 * that locks everyone out of the admin while Cloudflare is down does more harm than the bots it
 * stops. An answer Cloudflare rejects is always refused.
 */
final class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    /** The forms the core knows. Plugins add theirs through `falcon_turnstile_locations`. */
    public const CORE_LOCATIONS = [
        'login' => 'Login page (staff and customers, including the magic link form)',
        'register' => 'Registration page',
        'password_reset' => 'Forgot password page',
        'shop_account' => 'Shop: My Account sign-in (password and magic link)',
    ];

    /** Both keys are saved. */
    public static function configured(): bool
    {
        return trim((string) get_cms_option('turnstile_site_key', '')) !== ''
            && trim((string) get_cms_option('turnstile_secret_key', '')) !== '';
    }

    /**
     * Every form the widget can go on: key => label for the settings screen.
     *
     * Filter: `falcon_turnstile_locations` (array $locations).
     *
     * @return array<string, string>
     */
    public static function locations(): array
    {
        $locations = apply_falcon_filters('falcon_turnstile_locations', self::CORE_LOCATIONS);
        $clean = [];
        foreach ((array) $locations as $key => $label) {
            if (is_string($key) && preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
                $clean[$key] = (string) $label;
            }
        }

        return $clean;
    }

    /** The setting that switches a form on. */
    public static function optionKey(string $location): string
    {
        return 'turnstile_on_'.$location;
    }

    /**
     * Whether this form asks for Turnstile.
     *
     * Filter: `falcon_turnstile_enabled` (bool $enabled, string $location).
     */
    public static function enabled(string $location): bool
    {
        $on = self::configured() && get_cms_option(self::optionKey($location), '0') === '1';

        return (bool) apply_falcon_filters('falcon_turnstile_enabled', $on, $location);
    }

    /**
     * The widget for a form, or '' when it is off. The script is added once per page; the widget
     * stretches with its form (data-size="flexible") and follows the visitor's light/dark setting.
     */
    public static function widget(string $location): string
    {
        if (!self::enabled($location)) {
            return '';
        }

        $html = '<div class="falcon-turnstile cf-turnstile" style="margin:.25rem 0;max-width:100%"'
            .' data-sitekey="'.e((string) get_cms_option('turnstile_site_key')).'"'
            .' data-action="'.e($location).'" data-theme="auto" data-size="flexible"'
            .' data-response-field-name="cf-turnstile-response"></div>'
            // The flexible widget is at least 300px wide; in a narrower form (a phone) the compact
            // one fits instead. Decided before Cloudflare's script (async) draws it.
            .'<script data-no-defer>(function(s){var t=s&&s.previousElementSibling;if(t&&t.classList.contains("falcon-turnstile")&&t.offsetWidth&&t.offsetWidth<300){t.setAttribute("data-size","compact");}})(document.currentScript);</script>';

        $memo = falcon_request_memo('turnstile');
        if (!$memo->offsetExists('script')) {
            $memo['script'] = true;
            $html .= '<script src="'.self::SCRIPT_URL.'" async defer data-no-defer></script>';
        }

        return $html;
    }

    /**
     * Check a form's answer. Null when it passes (or the form does not ask), else the message to
     * show.
     *
     * Filter: `falcon_turnstile_verified` (bool $passed, string $location, Request $request).
     */
    public static function check(Request $request, string $location): ?string
    {
        if (!self::enabled($location)) {
            return null;
        }

        $token = trim((string) $request->input('cf-turnstile-response', ''));
        $passed = false;
        if ($token !== '') {
            try {
                $response = Http::asForm()->timeout(6)->connectTimeout(3)->post(self::VERIFY_URL, [
                    'secret' => (string) get_cms_option('turnstile_secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
                if ($response->successful()) {
                    $passed = (bool) $response->json('success');
                } else {
                    // Cloudflare itself failing is an outage, not a bot.
                    Log::warning('Turnstile verification unavailable (HTTP '.$response->status().'); the form was let through.');
                    $passed = true;
                }
            } catch (Throwable $e) {
                Log::warning('Turnstile verification unavailable ('.$e->getMessage().'); the form was let through.');
                $passed = true;
            }
        }

        $passed = (bool) apply_falcon_filters('falcon_turnstile_verified', $passed, $location, $request);

        return $passed ? null : 'Please complete the security check and try again.';
    }
}
