<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Google's own keyword numbers through DataForSEO (Pro, the site's own DataForSEO account):
 * monthly searches, cost per click, ad competition and ranking difficulty, country by country.
 *
 * DataForSEO bills per request, so each keyword and country is asked once and kept for a
 * month; its Live keyword endpoints take at most 12 requests a minute per account.
 */
final class DataForSeo
{
    private const API = 'https://api.dataforseo.com/v3/';

    private const CACHE_DAYS = 30;

    /** Countries asked per call from the editor (each is one billed request, two with difficulty). */
    public const COUNTRY_BATCH = 5;

    public static function available(): bool
    {
        [$login, $password] = Pro::dataForSeo();

        return Pro::active() && $login !== '' && $password !== '';
    }

    /**
     * @param  list<string>  $countries  alpha-2 codes
     * @return list<array{country: string, name: string, searches: ?int, cpc: ?float, competition: ?string, difficulty: ?int, trend: list<int>, error: ?string}>
     */
    public static function metrics(string $keyword, array $countries): array
    {
        $keyword = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $keyword) ?? $keyword));
        if ($keyword === '' || !self::available()) {
            return [];
        }
        $language = strtolower(explode('-', (string) get_cms_option(KeywordDemand::LANGUAGE, 'en-US'))[0]) ?: 'en';

        $rows = [];
        foreach (array_slice(array_values(array_unique($countries)), 0, self::COUNTRY_BATCH) as $country) {
            $location = Countries::adsLocation($country);
            $row = ['country' => $country, 'name' => Countries::name($country), 'searches' => null, 'cpc' => null,
                'competition' => null, 'difficulty' => null, 'trend' => [], 'error' => null];
            if ($location === null) {
                $rows[] = ['error' => 'Unknown country.'] + $row;

                continue;
            }
            try {
                $row = Cache::remember('falcon:dfs:'.sha1($keyword.'|'.$location.'|'.$language), now()->addDays(self::CACHE_DAYS),
                    fn () => self::fetch($keyword, $location, $language) + $row);
            } catch (Throwable $e) {
                $row['error'] = $e->getMessage();
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => ($b['searches'] ?? -1) <=> ($a['searches'] ?? -1));

        return $rows;
    }

    private static function fetch(string $keyword, int $location, string $language): array
    {
        $volume = self::call('keywords_data/google_ads/search_volume/live', ['keywords' => [$keyword], 'location_code' => $location]);
        $item = $volume['result'][0] ?? [];
        $out = [
            'searches' => isset($item['search_volume']) ? (int) $item['search_volume'] : 0,
            'cpc' => isset($item['cpc']) ? round((float) $item['cpc'], 2) : null,
            'competition' => isset($item['competition']) ? strtolower((string) $item['competition']) : null,
            // Oldest first, for a small trend line.
            'trend' => array_reverse(array_map(fn ($m) => (int) ($m['search_volume'] ?? 0), array_slice((array) ($item['monthly_searches'] ?? []), 0, 12))),
        ];

        try {
            $difficulty = self::call('dataforseo_labs/google/bulk_keyword_difficulty/live', ['keywords' => [$keyword], 'location_code' => $location, 'language_code' => $language]);
            $out['difficulty'] = isset($difficulty['result'][0]['items'][0]['keyword_difficulty']) ? (int) $difficulty['result'][0]['items'][0]['keyword_difficulty'] : null;
        } catch (Throwable $e) {
            // Volume without difficulty is still worth showing.
            $out['difficulty'] = null;
        }

        return $out;
    }

    /** One Live task; returns the task (with its "result"). */
    private static function call(string $endpoint, array $task): array
    {
        [$login, $password] = Pro::dataForSeo();
        $response = Http::withBasicAuth($login, $password)->timeout(30)->acceptJson()->post(self::API.$endpoint, [$task]);
        if ($response->status() === 401) {
            throw new RuntimeException('DataForSEO did not accept the login. Check it in SEO → Settings.');
        }
        if (!$response->successful()) {
            throw new RuntimeException('DataForSEO did not answer ('.$response->status().').');
        }
        $body = $response->json();
        $taskResult = $body['tasks'][0] ?? null;
        if ((int) ($body['status_code'] ?? 0) !== 20000 || !is_array($taskResult) || (int) ($taskResult['status_code'] ?? 0) !== 20000) {
            $message = (string) ($taskResult['status_message'] ?? $body['status_message'] ?? 'unknown error');
            throw new RuntimeException('DataForSEO: '.$message);
        }

        return $taskResult;
    }
}
