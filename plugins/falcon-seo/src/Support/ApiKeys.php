<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The outside services' keys on SEO → Settings: Bing Webmaster Tools (search demand) and
 * DataForSEO (Google keyword data). The AI keys are Falcon AI's (AI → Settings).
 *
 * Saving: a key is stored encrypted; an empty field keeps the one saved; "Remove" clears it.
 * A value that cannot be that service's key is refused with a message instead of saved: a
 * browser filling the admin password into the first password field it sees would otherwise
 * replace a good key every time the page is saved.
 *
 * Testing: each service has a call that costs nothing (list the account's sites, read the
 * account balance), so "Test" says whether the saved key works.
 */
final class ApiKeys
{
    /** What hides the middle of a saved key in its field; a field still holding it is unchanged. */
    public const MASK = '•';

    /** How much of a saved key shows: [characters at the start, characters at the end]. */
    private const VISIBLE = ['bing' => [4, 4], 'dataforseo' => [2, 2]];

    /** service => [option, label, the shape a real key has, what to do when it does not] */
    public const SERVICES = [
        'bing' => [KeywordDemand::BING_KEY, 'Bing Webmaster API key', '/^[a-f0-9]{32}$/i', 'A Bing Webmaster API key is 32 letters and digits (0-9, a-f). Copy it again from Bing Webmaster Tools → Settings → API access.'],
        'dataforseo' => [Pro::DATAFORSEO_PASSWORD, 'DataForSEO API password', '/^\S{8,}$/', 'The DataForSEO API password is the one on app.dataforseo.com → API Access (not your account password).'],
    ];

    /**
     * Settings → SEO save: encrypt the keys, keep the saved ones when a field is empty, clear
     * them on "Remove", and refuse anything that is not a key (the rest of the page still saves).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function save(array $data, $request = null): array
    {
        $problems = [];
        foreach (self::SERVICES as [$option, $label, $pattern, $help]) {
            $value = trim((string) ($data[$option] ?? ''));
            $remove = $request ? $request->boolean($option.'_remove') : !empty($data[$option.'_remove']);
            unset($data[$option], $data[$option.'_remove']);
            if ($remove) {
                $data[$option] = '';
            } elseif ($value !== '' && !str_contains($value, self::MASK)) {
                if (preg_match($pattern, $value)) {
                    $data[$option] = Crypt::encryptString($value);
                } else {
                    $problems[] = $label.' was not saved: '.$help;
                }
            }
        }

        if (array_key_exists(Pro::DATAFORSEO_LOGIN, $data)) {
            $login = trim((string) $data[Pro::DATAFORSEO_LOGIN]);
            if ($login !== '' && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
                unset($data[Pro::DATAFORSEO_LOGIN]);
                $problems[] = 'DataForSEO login was not saved: it is the email address you sign in to DataForSEO with.';
            } else {
                $data[Pro::DATAFORSEO_LOGIN] = $login;
            }
        }

        if ($problems && function_exists('session')) {
            try {
                session()->flash('error', implode(' ', $problems));
            } catch (Throwable $e) {
            }
        }

        return $data;
    }

    /**
     * The saved key as its field shows it: the start and the end, the middle hidden
     * ("sk-ant-api03-a••••••••••••VwAA"). Saving the field unchanged keeps the key.
     */
    public static function masked(string $service): ?string
    {
        $key = self::key($service);
        if ($key === '') {
            return null;
        }
        [$start, $end] = self::VISIBLE[$service] ?? [2, 2];
        // A short key keeps most of itself hidden.
        if (strlen($key) < ($start + $end) * 2) {
            [$start, $end] = [min(2, $start), min(2, $end)];
        }

        return substr($key, 0, $start).str_repeat(self::MASK, 12).substr($key, -$end);
    }

    public static function key(string $service): string
    {
        $option = self::SERVICES[$service][0] ?? null;
        $stored = $option ? (string) get_cms_option($option, '') : '';
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Whether the saved key works, by a call that costs nothing.
     *
     * @return array{ok: bool, message: string}
     */
    public static function test(string $service): array
    {
        $key = self::key($service);
        if ($key === '') {
            return ['ok' => false, 'message' => 'No key saved yet. Paste it, then Save Changes, then Test.'];
        }

        try {
            return match ($service) {
                'bing' => self::testBing($key),
                'dataforseo' => self::testDataForSeo($key),
                default => ['ok' => false, 'message' => 'Unknown service.'],
            };
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'The test could not run: '.$e->getMessage()];
        }
    }

    private static function testBing(string $key): array
    {
        $response = Http::timeout(15)->acceptJson()->get('https://ssl.bing.com/webmaster/api.svc/json/GetUserSites', ['apikey' => $key]);
        // Bing answers a key it does not know with 400 rather than 401.
        if (in_array($response->status(), [400, 401, 403], true)) {
            return ['ok' => false, 'message' => 'Bing refused the key. Copy it again from Bing Webmaster Tools → Settings → API access.'];
        }
        if (!$response->successful()) {
            return ['ok' => false, 'message' => 'Bing did not answer ('.$response->status().').'];
        }
        $sites = (array) ($response->json('d') ?? []);

        return ['ok' => true, 'message' => 'Works: '.count($sites).' site(s) in this Bing Webmaster account.'];
    }

    private static function testDataForSeo(string $password): array
    {
        $login = trim((string) get_cms_option(Pro::DATAFORSEO_LOGIN, ''));
        if ($login === '') {
            return ['ok' => false, 'message' => 'Add the DataForSEO login (your email) too, then Save Changes.'];
        }
        $response = Http::withBasicAuth($login, $password)->timeout(15)->acceptJson()->get('https://api.dataforseo.com/v3/appendix/user_data');
        if ($response->status() === 401) {
            return ['ok' => false, 'message' => 'DataForSEO refused the login or API password (app.dataforseo.com → API Access).'];
        }
        $task = $response->json('tasks.0') ?? [];
        if (!$response->successful() || (int) ($task['status_code'] ?? 0) !== 20000) {
            return ['ok' => false, 'message' => 'DataForSEO: '.($task['status_message'] ?? $response->json('status_message') ?? 'no answer ('.$response->status().')')];
        }
        $balance = $task['result'][0]['money']['balance'] ?? null;

        return ['ok' => true, 'message' => 'Works'.($balance !== null ? ': balance $'.number_format((float) $balance, 2) : '').'.'];
    }
}
