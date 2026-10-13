<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * How many people search for a keyword, and what else they search, from free sources only:
 *
 *  - Google Keyword Planner volumes the owner imported as a CSV (Settings → SEO).
 *  - Bing Webmaster Tools' keyword research API, with the site's own free API key.
 *  - Google's search-box suggestions (optional, off by default: not an official API).
 *
 * Every outside answer is cached for a week, so the editor never spends a quota twice.
 */
final class KeywordDemand
{
    public const BING_KEY = 'bing_webmaster_api_key';

    public const COUNTRY = 'keyword_country';

    public const LANGUAGE = 'keyword_language';

    public const AUTOCOMPLETE = 'keyword_autocomplete';

    private const BING_API = 'https://ssl.bing.com/webmaster/api.svc/json/';

    private const SUGGEST_API = 'https://suggestqueries.google.com/complete/search';

    private const CACHE_DAYS = 7;

    private const RELATED = 15;

    /** Countries asked per request: the editor asks for the rest batch by batch, so any number can be compared. */
    public const COUNTRY_BATCH = 20;

    /** Which sources this site has set up. */
    public static function sources(): array
    {
        return [
            'planner' => self::plannerCount() > 0,
            'bing' => self::bingKey() !== '',
            'autocomplete' => (string) get_cms_option(self::AUTOCOMPLETE, '0') === '1',
        ];
    }

    /**
     * A two-letter $country asks Bing and the suggestions about that market instead of the one in
     * Settings → SEO. Keyword Planner figures are whatever market the imported file was for.
     *
     * @return array{query: string, country: string, sources: array<string, bool>, volume: ?array, bing: ?array, related: list<array>, suggestions: list<string>, errors: list<string>}
     */
    public static function lookup(string $query, ?string $country = null): array
    {
        $country = strtolower(trim((string) $country));
        self::$country = preg_match('/^[a-z]{2}$/', $country) ? $country : null;
        try {
            return self::find($query);
        } finally {
            self::$country = null;
        }
    }

    /** The market asked for in this lookup, when it is not the site's own. */
    private static ?string $country = null;

    /** @return array{query: string, country: string, sources: array<string, bool>, volume: ?array, bing: ?array, related: list<array>, suggestions: list<string>, errors: list<string>} */
    private static function find(string $query): array
    {
        $query = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? $query));
        $sources = self::sources();
        $out = ['query' => $query, 'country' => self::market()[0], 'sources' => $sources, 'volume' => null, 'bing' => null, 'related' => [], 'suggestions' => [], 'errors' => []];
        if ($query === '') {
            return $out;
        }

        $related = [];
        if ($sources['planner']) {
            $out['volume'] = self::plannerRow($query);
            foreach (self::plannerRelated($query) as $row) {
                $related[$row['query']] = $row;
            }
        }

        if ($sources['bing']) {
            try {
                $bing = self::bing($query);
                $out['bing'] = $bing['keyword'];
                foreach ($bing['related'] as $row) {
                    if ($row['query'] === $query) {
                        continue;
                    }
                    $related[$row['query']] = ($related[$row['query']] ?? ['query' => $row['query']]) + ['bing' => $row['bing']];
                }
            } catch (Throwable $e) {
                $out['errors'][] = 'Bing: '.$e->getMessage();
            }
        }

        if ($sources['autocomplete']) {
            try {
                $out['suggestions'] = array_values(array_filter(self::suggestions($query), fn ($s) => $s !== $query));
            } catch (Throwable $e) {
                $out['errors'][] = 'Suggestions: '.$e->getMessage();
            }
        }

        // Most searched first: Google's figure where there is one, then Bing's.
        usort($related, fn ($a, $b) => [$b['searches_high'] ?? -1, $b['bing'] ?? -1] <=> [$a['searches_high'] ?? -1, $a['bing'] ?? -1]);
        $out['related'] = array_slice(array_values($related), 0, self::RELATED);

        return $out;
    }

    // ── Google Keyword Planner (imported) ────────────────────────────────────

    public static function plannerCount(): int
    {
        try {
            return (int) DB::table('cms_keyword_volumes')->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private static function plannerRow(string $query): ?array
    {
        $row = DB::table('cms_keyword_volumes')->where('keyword_hash', sha1($query))->first();

        return $row ? self::shape($row) : null;
    }

    /** Imported keywords that contain every word of the query: "laravel cms" → "best laravel cms". */
    private static function plannerRelated(string $query): array
    {
        $words = array_filter(preg_split('/\s+/u', $query) ?: [], fn ($w) => mb_strlen($w) > 1);
        if ($words === []) {
            return [];
        }
        $q = DB::table('cms_keyword_volumes')->where('keyword_hash', '!=', sha1($query));
        foreach ($words as $word) {
            $q->where('keyword', 'like', '%'.addcslashes($word, '%_\\').'%');
        }

        return $q->orderByDesc('searches_high')->limit(self::RELATED)->get()->map(fn ($r) => self::shape($r))->all();
    }

    private static function shape($row): array
    {
        return ['query' => (string) $row->keyword, 'searches_low' => $row->searches_low !== null ? (int) $row->searches_low : null,
            'searches_high' => $row->searches_high !== null ? (int) $row->searches_high : null, 'competition' => $row->competition];
    }

    /**
     * Reads a Keyword Planner export (UTF-16, tab separated, two title lines above the header)
     * or any keyword tool's CSV with a "Keyword" column and a volume column. Returns how many
     * keywords were stored; a keyword already there is updated.
     */
    public static function import(string $contents): int
    {
        $contents = self::utf8($contents);
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        $header = null;
        $delimiter = ',';
        $columns = [];
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if ($header === null) {
                $delimiter = substr_count($line, "\t") > substr_count($line, ',') ? "\t" : (substr_count($line, ';') > substr_count($line, ',') ? ';' : ',');
                $cells = array_map(fn ($c) => mb_strtolower(trim((string) $c)), str_getcsv($line, $delimiter, '"', ''));
                $keyword = array_search('keyword', $cells, true);
                if ($keyword === false) {
                    $keyword = array_search('keywords', $cells, true);
                }
                if ($keyword === false) {
                    continue; // a title line above the header ("Keyword Stats 2026-10-13 …")
                }
                $volume = null;
                foreach ($cells as $i => $cell) {
                    if (preg_match('/^(avg\.? monthly searches|search volume|monthly searches|volume|searches)\b/', $cell)) {
                        $volume = $i;
                        break;
                    }
                }
                $competition = array_search('competition', $cells, true);
                $header = true;
                $columns = ['keyword' => $keyword, 'volume' => $volume, 'competition' => $competition === false ? null : $competition];

                continue;
            }

            $cells = str_getcsv($line, $delimiter, '"', '');
            $keyword = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($cells[$columns['keyword']] ?? '')) ?? ''));
            if ($keyword === '' || mb_strlen($keyword) > 255) {
                continue;
            }
            [$low, $high] = $columns['volume'] !== null ? self::range((string) ($cells[$columns['volume']] ?? '')) : [null, null];
            $competition = $columns['competition'] !== null ? trim((string) ($cells[$columns['competition']] ?? '')) : '';
            $rows[sha1($keyword)] = ['keyword_hash' => sha1($keyword), 'keyword' => $keyword, 'searches_low' => $low, 'searches_high' => $high,
                'competition' => $competition !== '' ? mb_substr($competition, 0, 20) : null, 'imported_at' => now()];
            if (count($rows) >= 50000) {
                break;
            }
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('cms_keyword_volumes')->upsert($chunk, ['keyword_hash'], ['keyword', 'searches_low', 'searches_high', 'competition', 'imported_at']);
        }

        return count($rows);
    }

    /** "12,100" → [12100, 12100]; "1K – 10K" → [1000, 10000]; "" → [null, null]. */
    public static function range(string $value): array
    {
        preg_match_all('/(\d[\d,.]*)\s*([kKmM])?/u', $value, $m, PREG_SET_ORDER);
        $numbers = [];
        foreach ($m as $match) {
            $n = (float) str_replace(',', '', $match[1]);
            $suffix = strtolower($match[2] ?? '');
            $numbers[] = (int) round($n * ($suffix === 'k' ? 1000 : ($suffix === 'm' ? 1000000 : 1)));
        }
        if ($numbers === []) {
            return [null, null];
        }

        return [min($numbers), max($numbers)];
    }

    private static function utf8(string $contents): string
    {
        if (str_starts_with($contents, "\xFF\xFE")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }
        // UTF-16 without a byte order mark: every other byte is zero.
        if (strlen($contents) > 4 && $contents[1] === "\0" && $contents[3] === "\0") {
            return mb_convert_encoding($contents, 'UTF-8', 'UTF-16LE');
        }

        return $contents;
    }

    // ── Bing Webmaster Tools ─────────────────────────────────────────────────

    public static function bingKey(): string
    {
        $stored = (string) get_cms_option(self::BING_KEY, '');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable $e) {
            return '';
        }
    }

    /** @return array{keyword: ?array{impressions: int, broad: int}, related: list<array{query: string, bing: int}>} */
    private static function bing(string $query): array
    {
        [$country, $language] = self::market();

        return Cache::remember('falcon:kw:bing:'.sha1($query.'|'.$country.'|'.$language), now()->addDays(self::CACHE_DAYS), function () use ($query, $country, $language) {
            $params = ['q' => $query, 'country' => $country, 'language' => $language,
                'startDate' => now()->subDays(31)->toDateString(), 'endDate' => now()->subDay()->toDateString(), 'apikey' => self::bingKey()];

            $keyword = self::bingCall('GetKeyword', $params);
            $related = self::bingCall('GetRelatedKeywords', $params);

            return [
                'keyword' => is_array($keyword) && isset($keyword['Impressions'])
                    ? ['impressions' => (int) $keyword['Impressions'], 'broad' => (int) ($keyword['BroadImpressions'] ?? 0)] : null,
                'related' => array_values(array_filter(array_map(fn ($r) => is_array($r) && isset($r['Query'])
                    ? ['query' => mb_strtolower((string) $r['Query']), 'bing' => (int) ($r['Impressions'] ?? 0)] : null, is_array($related) ? $related : []))),
            ];
        });
    }

    private static function bingCall(string $method, array $params)
    {
        $response = Http::timeout(10)->acceptJson()->get(self::BING_API.$method, $params);
        if (!$response->successful()) {
            $message = (string) ($response->json('Message') ?? '');
            throw new \RuntimeException($response->status() === 401 || str_contains(strtolower($message), 'apikey')
                ? 'the API key was not accepted. Check it in Settings → SEO.'
                : 'no answer ('.$response->status().($message !== '' ? ': '.$message : '').').');
        }

        return $response->json('d');
    }

    /**
     * How often a keyword is searched on Bing in each of these countries (last 30 days), most
     * first, for up to COUNTRY_BATCH countries. Needs the Bing key; one call per country,
     * remembered for a week. Keyword Planner figures cover one market only, so they are not
     * split by country.
     *
     * @param  list<string>  $countries
     * @return list<array{country: string, name: string, searches: ?int, error: ?string}>
     */
    public static function byCountry(string $query, array $countries): array
    {
        $query = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? $query));
        $countries = array_values(array_unique(array_filter(array_map(fn ($c) => strtolower(trim((string) $c)), $countries), [Countries::class, 'valid'])));
        if ($query === '' || $countries === [] || self::bingKey() === '') {
            return [];
        }
        $language = self::market()[1];

        $rows = [];
        foreach (array_slice($countries, 0, self::COUNTRY_BATCH) as $country) {
            $row = ['country' => $country, 'name' => Countries::name($country), 'searches' => null, 'error' => null];
            try {
                $row['searches'] = Cache::remember('falcon:kw:bing-country:'.sha1($query.'|'.$country.'|'.$language), now()->addDays(self::CACHE_DAYS), function () use ($query, $country, $language) {
                    $keyword = self::bingCall('GetKeyword', ['q' => $query, 'country' => $country, 'language' => $language,
                        'startDate' => now()->subDays(31)->toDateString(), 'endDate' => now()->subDay()->toDateString(), 'apikey' => self::bingKey()]);

                    return is_array($keyword) && isset($keyword['Impressions']) ? (int) $keyword['Impressions'] : 0;
                });
            } catch (Throwable $e) {
                $row['error'] = $e->getMessage();
            }
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => ($b['searches'] ?? -1) <=> ($a['searches'] ?? -1));

        return $rows;
    }

    // ── Google search-box suggestions ────────────────────────────────────────

    private static function suggestions(string $query): array
    {
        [$country, $language] = self::market();

        return Cache::remember('falcon:kw:suggest:'.sha1($query.'|'.$country.'|'.$language), now()->addDays(self::CACHE_DAYS), function () use ($query, $country, $language) {
            $response = Http::timeout(6)->get(self::SUGGEST_API, ['client' => 'firefox', 'q' => $query,
                'hl' => strtolower(explode('-', $language)[0]), 'gl' => $country, 'ie' => 'utf-8', 'oe' => 'utf-8']);
            if (!$response->successful()) {
                throw new \RuntimeException('no answer ('.$response->status().').');
            }
            $list = json_decode(self::utf8($response->body()), true)[1] ?? [];

            return array_values(array_slice(array_map(fn ($s) => mb_strtolower((string) $s), array_filter($list, 'is_string')), 0, 10));
        });
    }

    /** @return array{0: string, 1: string} country code ("us") and language ("en-US") */
    private static function market(): array
    {
        return [self::$country ?? (strtolower(trim((string) get_cms_option(self::COUNTRY, 'us'))) ?: 'us'), trim((string) get_cms_option(self::LANGUAGE, 'en-US')) ?: 'en-US'];
    }
}
