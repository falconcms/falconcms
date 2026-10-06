<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Host Google Fonts Locally": a Google Fonts stylesheet and its font files, kept on this site.
 *
 * The page otherwise waits on two other servers before its text can show: fonts.googleapis.com
 * for the CSS (render-blocking) and fonts.gstatic.com for each font file, each with its own
 * DNS lookup and TLS handshake. Kept locally, the @font-face rules go inline in the page and
 * the files come from the site itself. Visitors' browsers then never contact Google, which
 * matters for privacy law in the EU as much as for speed.
 *
 * The download never happens on a visitor's request: the first page that needs a stylesheet
 * keeps Google's link and schedules the download for after its response has been sent. Every
 * request after that gets the local copy. A failed download keeps Google's link and is retried
 * an hour later, so a page can never lose its fonts over this.
 */
class LocalGoogleFonts
{
    /** Where the copies live, under public/. */
    public const DIR = 'falcon-fonts';

    /** A modern browser's user agent, so Google answers with woff2 files. */
    private const AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    /** Never keep more than this per stylesheet: a runaway response must not fill the disk. */
    private const MAX_BYTES = 8 * 1024 * 1024;

    /** The local @font-face CSS for a Google Fonts URL, or null when it is not stored yet. */
    public static function css(string $url): ?string
    {
        if (!self::isGoogleCss($url)) {
            return null;
        }
        $file = self::dir($url).'/fonts.css';
        if (!is_file($file)) {
            return null;
        }

        // stored with a placeholder for the folder's URL, so the copy follows the site's domain
        return str_replace('{{dir}}', rtrim(asset(self::DIR.'/'.self::hash($url)), '/'), (string) file_get_contents($file));
    }

    /** Download after the response has gone out (once per URL, retried hourly on failure). */
    public static function scheduleDownload(string $url): void
    {
        if (!self::isGoogleCss($url) || !Cache::add('falcon_gfonts_try_'.self::hash($url), 1, 3600)) {
            return;
        }
        app()->terminating(fn () => self::download($url));
    }

    /** Fetch the stylesheet and every font file it names, then write the local copy. */
    public static function download(string $url): bool
    {
        if (!self::isGoogleCss($url)) {
            return false;
        }

        try {
            $css = Http::timeout(10)->withHeaders(['User-Agent' => self::AGENT])->get(self::absolute($url))->throw()->body();
            if (!str_contains($css, '@font-face')) {
                return false;
            }

            $dir = self::dir($url);
            $tmp = $dir.'.tmp';
            File::deleteDirectory($tmp);
            File::ensureDirectoryExists($tmp);

            $bytes = 0;
            $local = preg_replace_callback('#url\((https://fonts\.gstatic\.com/[^)\s]+)\)#i', function ($m) use ($tmp, &$bytes) {
                $name = substr(sha1($m[1]), 0, 16).'.'.(pathinfo(parse_url($m[1], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'woff2');
                if (!is_file($tmp.'/'.$name)) {
                    $font = Http::timeout(15)->withHeaders(['User-Agent' => self::AGENT])->get($m[1])->throw()->body();
                    $bytes += strlen($font);
                    if ($bytes > self::MAX_BYTES) {
                        throw new \RuntimeException('the font files are larger than allowed');
                    }
                    File::put($tmp.'/'.$name, $font);
                }

                return 'url({{dir}}/'.$name.')';
            }, $css);

            if ($local === null || str_contains($local, 'fonts.gstatic.com')) {
                File::deleteDirectory($tmp);

                return false;
            }
            File::put($tmp.'/fonts.css', $local);

            // swap the finished copy in at once, so a request never sees half a download
            File::deleteDirectory($dir);
            File::moveDirectory($tmp, $dir);

            return true;
        } catch (Throwable $e) {
            Log::warning('Could not copy Google Fonts locally, the page keeps loading them from Google: '.$e->getMessage());
            File::deleteDirectory(self::dir($url).'.tmp');

            return false;
        }
    }

    /** Delete every local copy (they are rebuilt on demand). */
    public static function clear(): void
    {
        File::deleteDirectory(public_path(self::DIR));
    }

    public static function isGoogleCss(string $url): bool
    {
        return (bool) preg_match('#^(https?:)?//fonts\.googleapis\.com/css2?\?#i', html_entity_decode($url));
    }

    private static function absolute(string $url): string
    {
        $url = html_entity_decode($url);

        return str_starts_with($url, '//') ? 'https:'.$url : preg_replace('#^http:#i', 'https:', $url);
    }

    private static function hash(string $url): string
    {
        return substr(sha1(self::absolute($url)), 0, 16);
    }

    private static function dir(string $url): string
    {
        return public_path(self::DIR.'/'.self::hash($url));
    }
}
