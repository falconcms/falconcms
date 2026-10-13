<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Empties Cloudflare's cache when the site changes (Settings → Integrations), so a site that
 * lets Cloudflare cache its pages never shows visitors an old version.
 *
 * Wherever the CMS clears its own page cache (a post saved, the Customizer, a menu, the
 * builder…), Cloudflare is asked to purge too: once per request, after the response has been
 * sent, so a save never waits on Cloudflare and a bulk edit makes one call, not a hundred.
 */
final class Cloudflare
{
    public const ENABLED = 'cloudflare_purge_enabled';

    public const TOKEN = 'cloudflare_api_token';

    public const ZONE = 'cloudflare_zone_id';

    public const LAST = 'cloudflare_last_purge';

    private const API = 'https://api.cloudflare.com/client/v4/zones/';

    private static bool $pending = false;

    public static function configured(): bool
    {
        return self::token() !== '' && self::zone() !== '';
    }

    public static function enabled(): bool
    {
        return (string) get_cms_option(self::ENABLED, '0') === '1' && self::configured();
    }

    /** Purge once this request is done, if purging is on. Safe to call any number of times. */
    public static function purgeSoon(): void
    {
        if (self::$pending || !self::enabled()) {
            return;
        }
        // A queue worker never "terminates" between jobs, so a deferred purge would never run there.
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            self::purgeEverything();

            return;
        }
        self::$pending = true;
        app()->terminating(function () {
            self::$pending = false;
            self::purgeEverything();
        });
    }

    /**
     * Purge the whole zone now. Returns null on success, or what went wrong. The outcome is
     * kept for the settings screen either way.
     */
    public static function purgeEverything(): ?string
    {
        if (!self::configured()) {
            return 'Cloudflare is not set up.';
        }
        try {
            $response = Http::timeout(10)->withToken(self::token())->acceptJson()
                ->post(self::API.rawurlencode(self::zone()).'/purge_cache', ['purge_everything' => true]);
            $error = $response->successful() && $response->json('success')
                ? null
                : self::describe($response->status(), (array) $response->json('errors'));
        } catch (Throwable $e) {
            $error = 'Cloudflare did not answer ('.$e->getMessage().').';
        }

        self::remember($error);

        return $error;
    }

    /** @return array{at: ?string, error: ?string} the last purge, for the settings screen */
    public static function last(): array
    {
        $last = json_decode((string) get_cms_option(self::LAST, ''), true);

        return ['at' => $last['at'] ?? null, 'error' => $last['error'] ?? null];
    }

    public static function token(): string
    {
        $stored = (string) get_cms_option(self::TOKEN, '');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function zone(): string
    {
        return trim((string) get_cms_option(self::ZONE, ''));
    }

    private static function describe(int $status, array $errors): string
    {
        $message = trim(implode(' ', array_map(fn ($e) => is_array($e) ? (string) ($e['message'] ?? '') : (string) $e, $errors)));
        if (in_array($status, [401, 403], true)) {
            return 'Cloudflare refused the token. It needs the "Zone → Cache Purge" permission for this site\'s zone.'.($message !== '' ? ' ('.$message.')' : '');
        }
        if ($status === 404 || str_contains(strtolower($message), 'zone')) {
            return 'Cloudflare does not know this Zone ID. Copy it from the site\'s Overview page in Cloudflare.';
        }

        return 'Cloudflare answered '.$status.($message !== '' ? ': '.$message : '.');
    }

    private static function remember(?string $error): void
    {
        try {
            DB::table('cms_settings')->updateOrInsert(['key' => self::LAST],
                ['value' => json_encode(['at' => now()->toDateTimeString(), 'error' => $error]), 'updated_at' => now()]);
            forget_cms_options_cache();
        } catch (Throwable $e) {
        }
    }
}
