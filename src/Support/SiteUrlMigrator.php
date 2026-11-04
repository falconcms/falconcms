<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Moving a site's data to another address: every link to the old site becomes a link to this one.
 *
 * A database restored from another site (falconcms.com → a local copy) still pointed at the old
 * site: menu items, buttons, builder layouts, settings and post content all carried
 * "https://falconcms.com/…", so clicking around the copy landed on the live site.
 *
 * What changes: the old address with its scheme — "https://old.com", "http://old.com",
 * "https://www.old.com" and the same written inside JSON ("https:\/\/old.com") — wherever it is
 * the whole host, i.e. followed by "/", a quote, "?", "#", whitespace, "<" or the end. So
 * "https://old.com.au" or "info@old.com" are left alone, as is a bare "old.com" in text.
 * PHP-serialized values are unserialized, changed and serialized again, so their lengths stay right.
 *
 * What does not: visit logs, the activity log, sessions, caches and queues — records of what
 * happened on the old site, or data that is rebuilt anyway.
 */
final class SiteUrlMigrator
{
    /**
     * Tables never rewritten: history and machinery, not content. The live site's visits,
     * logs, Search Console figures and reports describe the old site and stay as they were.
     * Named here, and matched by SKIP_PATTERN for the ones plugins add.
     */
    public const SKIP_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'password_resets', 'personal_access_tokens', 'magic_login_tokens',
        'cms_analytics', 'activity_logs', 'cms_activity_logs', 'cms_404_log', 'cms_web_vitals',
        'cms_post_view_days', 'falcon_buy_clicks', 'cms_not_found_log', 'cms_seo_page_scores',
    ];

    /** Logs, reports, analytics, Search Console (gsc_*), daily figures, caches and tokens. */
    public const SKIP_PATTERN = '/(^gsc_|_logs?$|_logs?_|^logs?_|analytics|_reports?$|_daily$|_days$|_hits$|_visits$|_stats$|^cache|_cache$|_tokens?$|web_vitals|_scores$)/i';

    private const TEXT_TYPES = ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'json', 'string', 'nvarchar', 'nchar', 'clob'];

    /** The address written at the top of a backup by this CMS ("-- Site URL: https://…"). */
    public static function fromDump(string $sql): ?string
    {
        if (preg_match('/^-- Site URL: (\S+)/m', substr($sql, 0, 2000), $m)) {
            return self::normalize($m[1]);
        }

        return null;
    }

    /**
     * For a backup made before the address was written into it: the host this data links to
     * most for its own things — menu items that point at a page, post or term (not a custom
     * link), and media under /storage/. Null when nothing points anywhere.
     */
    public static function guess(): ?string
    {
        $hosts = [];
        $count = function (?string $url) use (&$hosts) {
            if ($url && preg_match('~^(https?://[^/\s"\'?#]+)~i', $url, $m)) {
                $h = strtolower($m[1]);
                $hosts[$h] = ($hosts[$h] ?? 0) + 1;
            }
        };
        try {
            if (Schema::hasTable('navigation_menu_items')) {
                foreach (DB::table('navigation_menu_items')->where('type', '!=', 'custom')->where('url', 'like', 'http%')->limit(500)->pluck('url') as $u) {
                    $count($u);
                }
            }
            if (Schema::hasTable('posts')) {
                foreach (DB::table('posts')->where('content', 'like', '%/storage/%')->limit(300)->pluck('content') as $c) {
                    if (preg_match_all('~(https?:(?://|\\\\/\\\\/)[a-z0-9.\-]+(?::\d+)?)(?:/|\\\\/)storage(?:/|\\\\/)~i', (string) $c, $mm)) {
                        foreach ($mm[1] as $u) {
                            $count(str_replace('\\/', '/', $u).'/');
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            return null;
        }
        if (!$hosts) {
            return null;
        }
        arsort($hosts);

        return self::normalize((string) array_key_first($hosts));
    }

    /** "https://Old.com/" → "https://old.com" (scheme + host + port, no path). */
    public static function normalize(?string $url): ?string
    {
        $p = parse_url(trim((string) $url));
        if (empty($p['host'])) {
            return null;
        }

        return strtolower(($p['scheme'] ?? 'https').'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : ''));
    }

    /**
     * Rewrite every link to $from (any scheme, with or without www) as a link to $to.
     *
     * $to = null (relative): the old address is dropped and the path kept — "https://old.com/a"
     * becomes "/a", "https://old.com" becomes "/" — so the links work under whatever address
     * the site is opened at (a local copy reached as falconcms.test and as 127.0.0.1:8000).
     *
     * @return array{values:int, tables:array<string,int>, skipped:list<string>}
     */
    public static function migrate(string $from, ?string $to): array
    {
        $from = self::normalize($from);
        $relative = $to === null;
        $to = $relative ? '' : self::normalize($to);
        $stats = ['values' => 0, 'tables' => [], 'skipped' => []];
        if (!$from || (!$relative && !$to)) {
            return $stats;
        }
        $oldHost = (string) parse_url($from, PHP_URL_HOST);
        $oldPort = parse_url($from, PHP_URL_PORT);
        $bareHost = preg_replace('/^www\./', '', $oldHost);
        if (!$relative && $bareHost === preg_replace('/^www\./', '', (string) parse_url($to, PHP_URL_HOST)) && $oldPort === parse_url($to, PHP_URL_PORT)) {
            return $stats; // the same site
        }

        $hostRe = '(?:www\.)?'.preg_quote($bareHost, '#').($oldPort ? ':'.$oldPort : '');
        // https://old.com  and the JSON-escaped https:\/\/old.com, ending where the host ends.
        $plain = '#https?://'.$hostRe.'(?=[/"\'?\#\s<>\\\\)]|$)#i';
        $json = '#https?:\\\\/\\\\/'.$hostRe.'(?=\\\\/|[/"\'?\#\s<>)]|$)#i';
        $plainPath = '#https?://'.$hostRe.'(?=/)#i';
        $jsonPath = '#https?:\\\\/\\\\/'.$hostRe.'(?=\\\\/)#i';
        $toJson = str_replace('/', '\\/', $to);
        // URL-encoded inside another link (a share button, an embed): https%3A%2F%2Fold.com
        $encPath = '#https?%3A%2F%2F'.$hostRe.'(?=%2F)#i';
        $enc = '#https?%3A%2F%2F'.$hostRe.'(?=[&"\'\s<>]|%3F|%23|$)#i';
        $rewrite = function (string $v) use ($plain, $json, $plainPath, $jsonPath, $encPath, $enc, $to, $toJson, $relative): string {
            if (stripos($v, '%2F%2F') !== false) {
                $v = preg_replace($encPath, $relative ? '' : rawurlencode($to), $v) ?? $v;
                $v = preg_replace($enc, $relative ? '%2F' : rawurlencode($to), $v) ?? $v;
            }
            if ($relative) {
                // Followed by its path: drop the address ("https://old.com/a" → "/a"). A bare
                // address is the site's root ("https://old.com" → "/", "https:\/\/old.com" → "\/").
                $v = preg_replace($jsonPath, '', $v) ?? $v;
                $v = preg_replace($json, '\\/', $v) ?? $v;
                $v = preg_replace($plainPath, '', $v) ?? $v;

                return preg_replace($plain, '/', $v) ?? $v;
            }
            $v = preg_replace($json, $toJson, $v) ?? $v;

            return preg_replace($plain, $to, $v) ?? $v;
        };

        // This database only: on MySQL a bare getTableListing() lists every database on the server.
        foreach (array_unique(Schema::getTableListing(Schema::getCurrentSchemaListing(), false)) as $table) {
            if (self::skips($table)) {
                continue;
            }
            try {
                $cols = array_values(array_filter(Schema::getColumns($table),
                    fn ($c) => in_array(strtolower((string) ($c['type_name'] ?? '')), self::TEXT_TYPES, true)));
                if (!$cols) {
                    continue;
                }
                $pk = self::primaryKey($table);
                if ($pk === null) {
                    $stats['skipped'][] = $table;

                    continue;
                }
                $names = array_column($cols, 'name');
                $query = DB::table($table)->where(function ($q) use ($names, $bareHost) {
                    foreach ($names as $n) {
                        $q->orWhere($n, 'like', '%'.$bareHost.'%');
                    }
                });
                $changed = 0;
                $query->chunkById(500, function ($rows) use ($table, $pk, $names, $rewrite, &$changed) {
                    foreach ($rows as $row) {
                        $update = [];
                        foreach ($names as $n) {
                            $v = $row->{$n} ?? null;
                            if (!is_string($v) || $v === '') {
                                continue;
                            }
                            $new = self::rewriteValue($v, $rewrite);
                            if ($new !== $v) {
                                $update[$n] = $new;
                            }
                        }
                        if ($update) {
                            DB::table($table)->where($pk, $row->{$pk})->update($update);
                            $changed += count($update);
                        }
                    }
                }, $pk);
                if ($changed) {
                    $stats['tables'][$table] = $changed;
                    $stats['values'] += $changed;
                }
            } catch (Throwable $e) {
                $stats['skipped'][] = $table;
            }
        }

        // Settings, menus and pages are cached: drop the copies that still hold the old links.
        foreach (['forget_cms_options_cache', 'forget_nav_menu_cache', 'clear_page_cache'] as $forget) {
            try {
                if (function_exists($forget)) {
                    $forget();
                }
            } catch (Throwable $e) {
            }
        }

        return $stats;
    }

    /** A plain string, or a PHP-serialized one taken apart so its byte counts stay true. */
    private static function rewriteValue(string $v, callable $rewrite): string
    {
        if (preg_match('/^(a|O|s|i|b|d):\d*[:;]/', $v)) {
            $data = @unserialize($v, ['allowed_classes' => false]);
            if ($data !== false || $v === 'b:0;') {
                $walk = function ($x) use (&$walk, $rewrite) {
                    if (is_string($x)) {
                        return $rewrite($x);
                    }
                    if (is_array($x)) {
                        foreach ($x as $k => $item) {
                            $x[$k] = $walk($item);
                        }
                    }

                    return $x;
                };
                $new = serialize($walk($data));

                return $new === serialize($data) ? $v : $new;
            }
        }

        return $rewrite($v);
    }

    public static function skips(string $table): bool
    {
        return in_array($table, self::SKIP_TABLES, true) || preg_match(self::SKIP_PATTERN, $table) === 1;
    }

    private static function primaryKey(string $table): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (!empty($index['primary']) && count($index['columns']) === 1) {
                return $index['columns'][0];
            }
        }

        return null;
    }
}
