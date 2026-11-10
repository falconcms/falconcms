<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Http\Middleware\TrackVisits;
use FalconCms\Core\Models\Analytics;
use Illuminate\Http\Request;
use Throwable;

/**
 * Real visitors only: a page view counts once a browser has shown it to a person.
 *
 * The server sees every request, and most requests are not people. Crawlers that call
 * themselves Chrome, uptime monitors, link scanners, headless browsers and pages the browser
 * fetched ahead of a click all look like a page view to it — on falconcms.com they were nine
 * rows in ten. What none of them do is show the page in a visible tab: so every public page
 * carries a few lines of script that wait until it is on screen (not prerendered, not driven by
 * WebDriver) and someone either does something — scrolls, clicks, types, touches — or keeps
 * looking at it for a couple of seconds, and then confirm the view.
 *
 * The confirmation finds the row TrackVisits wrote for that request and marks it human. A page
 * served from the page cache never reached TrackVisits at all — those visits used to be lost —
 * so then the confirmation writes the row itself. The dashboard counts human rows only
 * (Analytics' global scope).
 */
final class HumanVisits
{
    /** How long a person has to look at the page, with nothing else happening, to count. */
    public const DWELL_MS = 2500;

    /** How far back a request's row is looked for. Longer than any page takes to be read. */
    private const MATCH_MINUTES = 30;

    public static function register(): void
    {
        add_falcon_action('falcon_footer', static fn () => self::footer(), 32);
    }

    public static function footer(): void
    {
        // Signed-in pages are never counted (and never cached). An error page is no visit.
        if (auth()->check() || app()->bound('falcon.not_found')
            || request()->routeIs('admin.*') || request()->is('admin/*')) {
            return;
        }

        echo self::script(route('falcon.visit.confirm', [], false));
    }

    /** The script a public page carries. It sends one small beacon per page view, or none. */
    public static function script(string $url): string
    {
        $u = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // navigator.webdriver: Selenium, Playwright, Puppeteer and headless Chrome say so.
        // document.prerendering: the browser is building the page ahead of a click that may
        // never come; it is armed when the page is actually shown.
        return '<script>(function(u){var d=document,w=window,n=navigator,done=0,t=0;'
            .'if(n.webdriver)return;'
            .'function send(){if(done||d.visibilityState!=="visible"||d.prerendering)return;done=1;clearTimeout(t);'
            .'var b=new FormData();b.append("p",location.pathname+location.search);b.append("r",d.referrer||"");'
            .'try{if(n.sendBeacon&&n.sendBeacon(u,b))return}catch(_){}'
            .'try{fetch(u,{method:"POST",body:b,keepalive:true,credentials:"omit"})}catch(_){}}'
            .'function arm(){clearTimeout(t);if(!done&&d.visibilityState==="visible"&&!d.prerendering)t=setTimeout(send,'.self::DWELL_MS.')}'
            .'["pointerdown","keydown","scroll","touchstart","wheel"].forEach(function(e){w.addEventListener(e,send,{passive:true})});'
            .'d.addEventListener("visibilitychange",arm);d.addEventListener("prerenderingchange",arm);arm();'
            .'})('.$u.');</script>';
    }

    /**
     * Mark the visit as a person's. Returns what happened, for the tests:
     * 'confirmed' (TrackVisits' row), 'created' (a cached page, no row yet), or why it was ignored.
     */
    public static function confirm(Request $request): string
    {
        if (!Analytics::hasHumanColumn()) {
            return 'no-column';
        }

        $userAgent = (string) $request->userAgent();
        if (TrackVisits::isBot($userAgent)) {
            return 'bot';
        }

        // Only this site's own pages may confirm a visit: a page elsewhere posting here (to
        // inflate the figures) carries its own Origin.
        $origin = (string) ($request->headers->get('Origin') ?: $request->headers->get('Referer'));
        $originHost = (string) parse_url($origin, PHP_URL_HOST);
        if ($originHost === '' || !falcon_is_site_host($originHost)) {
            return 'foreign';
        }

        $path = self::path((string) $request->input('p', ''));
        $ip = (string) $request->ip();
        if ($path === null || $ip === '') {
            return 'invalid';
        }

        try {
            $rows = Analytics::withoutGlobalScope('human')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', now()->subMinutes(self::MATCH_MINUTES))
                ->orderByDesc('id')->limit(20)->get(['id', 'url', 'human', 'created_at']);

            foreach ($rows as $row) {
                if (self::path((string) $row->url) !== $path) {
                    continue;
                }
                if ($row->human) {
                    // Already confirmed: the same view reported twice (a beacon and its retry).
                    if ($row->created_at && $row->created_at->gt(now()->subSeconds(10))) {
                        return 'duplicate';
                    }
                    break; // a confirmed earlier view; this one is a new view of the same page
                }
                Analytics::withoutGlobalScope('human')->whereKey($row->id)->update(['human' => true]);

                return 'confirmed';
            }

            // A page from the page cache: the request never wrote a row, so this does.
            $query = (string) parse_url((string) $request->input('p', ''), PHP_URL_QUERY);
            $referrer = trim((string) $request->input('r', ''));
            $row = Analytics::create([
                'ip_address' => $ip,
                'url' => url($path).($query !== '' ? '?'.$query : ''),
                'referrer' => $referrer !== '' ? mb_substr($referrer, 0, 255) : null,
                'user_agent' => mb_substr($userAgent, 0, 255),
                'browser' => UserAgentParser::browser($userAgent),
                'os' => UserAgentParser::os($userAgent),
                'device_type' => UserAgentParser::device($userAgent),
                'human' => true,
            ]);
            TrackVisits::resolveGeoLater((int) $row->id, $ip);

            return 'created';
        } catch (Throwable $e) {
            report($e);

            return 'error';
        }
    }

    /** "/about/team/" and "https://site/about/team?x=1" both become "/about/team"; junk is null. */
    private static function path(string $value): ?string
    {
        $path = (string) parse_url($value, PHP_URL_PATH);
        if ($path === '' && $value !== '' && $value[0] !== '/') {
            return null;
        }
        $path = '/'.trim(rawurldecode($path), '/');

        return strlen($path) <= 255 ? $path : null;
    }
}
