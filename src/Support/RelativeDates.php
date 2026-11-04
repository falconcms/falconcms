<?php

namespace FalconCms\Core\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Post Date as "time ago": "5 minutes ago", "3 hours ago", "2 days ago" — what a news site
 * shows, so a reader sees at a glance how fresh a story is.
 *
 * A post older than the limit (7 days unless the element says otherwise; 0 = no limit) shows
 * its date in the element's format instead: "45 days ago" says less than "Sep 20, 2026".
 *
 * The page is built once and then served from the page cache (and a CDN) for hours, so text
 * written while building it would go stale: "3 hours ago" still showing the next day. The
 * value is therefore written in a <time datetime="…"> that a few lines of script set again
 * from the reader's clock when the page opens, and every minute after. The server's words are
 * what a reader without script, or a search engine, sees.
 *
 * Words come in the site's language (Carbon on the server, Intl.RelativeTimeFormat with the
 * page's lang in the browser), and the element's Digits setting writes the numbers in Bangla,
 * Devanagari or Arabic digits.
 */
final class RelativeDates
{
    /** Default limit, in days, before a post shows its date instead. */
    public const DEFAULT_DAYS = 7;

    /** Whether this page shows one, so the footer carries the script only where it is needed. */
    private static bool $used = false;

    public static function register(): void
    {
        add_falcon_action('falcon_footer', [self::class, 'footer'], 40);
    }

    /** The limit in days from an element's setting: empty = the default, 0 = always "ago". */
    public static function days($setting): int
    {
        if ($setting === null || $setting === '' || !is_numeric($setting)) {
            return self::DEFAULT_DAYS;
        }

        return max(0, min(3650, (int) $setting));
    }

    /**
     * The words for a date: "3 hours ago" while it is within the limit, else the date itself.
     *
     * @param  array{date_format?:string, date_relative_days?:mixed, digits?:string}  $config
     * @return array{text:string, relative:bool, iso:string, date:string, days:int, digits:string}
     */
    public static function describe($date, array $config = [], ?CarbonInterface $now = null): array
    {
        $d = Carbon::parse($date);
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $days = self::days($config['date_relative_days'] ?? null);
        $digits = (string) ($config['digits'] ?? '');
        $format = trim((string) ($config['date_format'] ?? '')) ?: 'M j, Y';
        $dateText = falcon_localize_digits($d->format($format), $digits);

        $age = $now->getTimestamp() - $d->getTimestamp();
        $relative = $days === 0 || $age < $days * 86400;

        $text = $dateText;
        if ($relative) {
            try {
                // A scheduled date a moment ahead (clock skew) still reads as "just now".
                $text = $age < 1
                    ? $d->copy()->locale(app()->getLocale())->diffForHumans($d, ['options' => Carbon::JUST_NOW, 'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW])
                    : $d->copy()->locale(app()->getLocale())->diffForHumans($now, ['syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW, 'parts' => 1]);
            } catch (Throwable $e) {
                $text = $d->diffForHumans($now, ['syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW]);
            }
            $text = falcon_localize_digits($text, $digits);
        }

        return [
            'text' => $text,
            'relative' => $relative,
            'iso' => $d->toIso8601String(),
            'date' => $dateText,
            'days' => $days,
            'digits' => $digits,
        ];
    }

    /**
     * The value as a <time> the footer script keeps current, with the element's Before and
     * After text around it. An HtmlString, so a title, button or text block that escapes its
     * text still puts the tag on the page rather than showing it.
     */
    public static function html($date, array $config = [], string $before = '', string $after = ''): HtmlString
    {
        $info = self::describe($date, $config);
        self::$used = true;

        $attrs = 'class="falcon-time-ago" datetime="'.e($info['iso']).'"'
            .' data-falcon-ago="'.$info['days'].'"'
            .' data-date="'.e($info['date']).'"'
            .($info['digits'] !== '' ? ' data-digits="'.e($info['digits']).'"' : '');

        return new HtmlString(e($before).'<time '.$attrs.'>'.e($info['text']).'</time>'.e($after));
    }

    /** True once a time-ago value was written on this page (and resets, for tests). */
    public static function used(bool $reset = false): bool
    {
        $was = self::$used;
        if ($reset) {
            self::$used = false;
        }

        return $was;
    }

    /** On the falcon_footer hook: the script, on pages that show a time-ago value. */
    public static function footer(): void
    {
        if (self::$used) {
            echo self::script();
        }
    }

    /**
     * A few hundred bytes: every <time data-falcon-ago> is written again from the reader's clock,
     * in the page's language, when the page opens and every minute after; past its limit it
     * shows its date. Units step down like Carbon's: the largest whole one.
     */
    public static function script(): string
    {
        return '<script data-no-defer>(function(){if(window.falconTimeAgo)return;'
            .'var L=document.documentElement.lang||"en",R;try{R=new Intl.RelativeTimeFormat(L,{numeric:"always"})}catch(e){return}'
            .'var D={bengali:"০১২৩৪৫৬৭৮৯",devanagari:"०१२३४५६७८९",arabic:"٠١٢٣٤٥٦٧٨٩"},'
            .'U=[["year",31536000],["month",2592000],["week",604800],["day",86400],["hour",3600],["minute",60],["second",1]];'
            .'function w(t){var s=Math.floor((Date.now()-t)/1000);if(s<1)return new Intl.RelativeTimeFormat(L,{numeric:"auto"}).format(0,"second");'
            .'for(var i=0;i<U.length;i++){if(s>=U[i][1])return R.format(-Math.floor(s/U[i][1]),U[i][0])}return R.format(-s,"second")}'
            .'function g(x,d){var m=D[d];return m?x.replace(/[0-9]/g,function(c){return m[+c]}):x}'
            .'function run(){var n=document.querySelectorAll("time[data-falcon-ago]");for(var i=0;i<n.length;i++){var e=n[i],t=Date.parse(e.getAttribute("datetime"));if(isNaN(t))continue;'
            .'var k=+e.getAttribute("data-falcon-ago")||0,v=(k>0&&Date.now()-t>=k*864e5)?e.getAttribute("data-date"):g(w(t),e.getAttribute("data-digits"));'
            .'if(v&&e.textContent!==v)e.textContent=v}}'
            .'window.falconTimeAgo=run;run();if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);setInterval(run,60000)})();</script>';
    }
}
