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

    /** A site Google cannot know by its address (localhost, an IP) has no rankings to find. */
    public const NO_DOMAIN = 'This site\'s address (APP_URL in .env) is not a public domain, so Google results cannot be matched to it. Set APP_URL to the live address, like https://example.com.';

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
        $language = self::language();

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

    /**
     * Where the site ranks in Google for a keyword right now, in one country on one device: its
     * first result in the top 100, which address that is, the site in first place, and what
     * else the page of results shows (featured snippet, People also ask, videos…).
     * One billed request each time; the Rank Tracker decides how often.
     *
     * @return array{position: ?int, url: ?string, features: list<string>, snippet_owned: bool, leader: ?string}
     */
    public static function serp(string $keyword, string $country, string $device = 'desktop'): array
    {
        $location = Countries::adsLocation($country);
        if ($location === null) {
            throw new RuntimeException('Unknown country.');
        }
        $task = self::call('serp/google/organic/live/advanced', ['keyword' => $keyword, 'location_code' => $location,
            'language_code' => self::language(), 'device' => $device === 'mobile' ? 'mobile' : 'desktop', 'depth' => 100]);

        $own = self::ownDomain();
        $out = ['position' => null, 'url' => null, 'features' => [], 'snippet_owned' => false, 'leader' => null];
        $features = [];
        foreach ((array) ($task['result'][0]['items'] ?? []) as $item) {
            $type = (string) ($item['type'] ?? '');
            $domain = (string) ($item['domain'] ?? '');
            $mine = $domain !== '' && self::sameSite($domain, $own);
            if ($type === 'organic') {
                $out['leader'] ??= $domain !== '' ? $domain : null;
                if ($mine && $out['position'] === null) {
                    $out['position'] = (int) ($item['rank_group'] ?? 0) ?: null;
                    $out['url'] = isset($item['url']) ? (string) $item['url'] : null;
                }
            } elseif ($type !== '') {
                $features[$type] = true;
                if ($type === 'featured_snippet' && $mine) {
                    $out['snippet_owned'] = true;
                    $out['url'] ??= isset($item['url']) ? (string) $item['url'] : null;
                }
            }
        }
        $out['features'] = array_keys($features);

        return $out;
    }

    /**
     * Keywords a competitor ranks for in Google and this site does not, most searched first,
     * with the competitor's position and page. Kept a week: each look is billed.
     *
     * @return list<array{keyword: string, searches: ?int, difficulty: ?int, position: ?int, url: ?string}>
     */
    public static function keywordGap(string $competitor, string $country, int $limit = 100): array
    {
        $competitor = self::domain($competitor);
        $location = Countries::adsLocation($country);
        if ($competitor === '' || $location === null) {
            throw new RuntimeException($competitor === '' ? 'Enter the competitor\'s domain, like example.com.' : 'Unknown country.');
        }
        if (self::ownDomain() === '') {
            throw new RuntimeException(self::NO_DOMAIN);
        }

        return Cache::remember(self::gapKey($competitor, $country), now()->addDays(7), function () use ($competitor, $location, $limit) {
            $task = self::call('dataforseo_labs/google/domain_intersection/live', [
                'target1' => $competitor, 'target2' => self::ownDomain(), 'location_code' => $location, 'language_code' => self::language(),
                // false: what the first ranks for and the second does not.
                'intersections' => false, 'limit' => $limit, 'order_by' => ['keyword_data.keyword_info.search_volume,desc'],
            ]);
            $out = [];
            foreach ((array) ($task['result'][0]['items'] ?? []) as $item) {
                $keyword = (string) ($item['keyword_data']['keyword'] ?? '');
                if ($keyword === '') {
                    continue;
                }
                $serp = (array) ($item['first_domain_serp_element'] ?? []);
                $out[] = [
                    'keyword' => $keyword,
                    'searches' => isset($item['keyword_data']['keyword_info']['search_volume']) ? (int) $item['keyword_data']['keyword_info']['search_volume'] : null,
                    'difficulty' => isset($item['keyword_data']['keyword_properties']['keyword_difficulty']) ? (int) $item['keyword_data']['keyword_properties']['keyword_difficulty'] : null,
                    'position' => isset($serp['rank_group']) ? (int) $serp['rank_group'] : null,
                    'url' => isset($serp['url']) ? (string) $serp['url'] : null,
                ];
            }

            return $out;
        });
    }

    /** The last keyword gap fetched for a competitor and country, without asking again. */
    public static function cachedGap(string $competitor, string $country): ?array
    {
        $competitor = self::domain($competitor);

        return $competitor === '' ? null : Cache::get(self::gapKey($competitor, $country));
    }

    /** "https://www.Example.com/blog" → "example.com". */
    public static function domain(string $input): string
    {
        $input = trim(mb_strtolower($input));
        $host = parse_url(str_contains($input, '://') ? $input : 'http://'.$input, PHP_URL_HOST);
        $host = is_string($host) ? preg_replace('/^www\./', '', $host) : '';

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', (string) $host) ? (string) $host : '';
    }

    /** This site's domain, as DataForSEO knows it. */
    public static function ownDomain(): string
    {
        return self::domain((string) config('app.url'));
    }

    /** The same site: the same domain, or a subdomain of it (blog.example.com). */
    private static function sameSite(string $domain, string $own): bool
    {
        $domain = preg_replace('/^www\./', '', mb_strtolower($domain)) ?? '';

        return $own !== '' && ($domain === $own || str_ends_with($domain, '.'.$own));
    }

    private static function gapKey(string $competitor, string $country): string
    {
        return 'falcon:dfs:gap:'.sha1($competitor.'|'.self::ownDomain().'|'.$country.'|'.self::language());
    }

    /** The site's keyword language (SEO → Settings), as DataForSEO's two-letter code. */
    private static function language(): string
    {
        return strtolower(explode('-', (string) get_cms_option(KeywordDemand::LANGUAGE, 'en-US'))[0]) ?: 'en';
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
