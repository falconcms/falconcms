<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Support\UserAgentParser;
use Illuminate\Http\Request;

class TrackVisits
{
    /** User-agent fragments that identify bots/crawlers we don't want to count as visits. */
    public const BOT_PATTERN = '/bot|crawl|spider|slurp|mediapartners|bingpreview|facebookexternalhit|whatsapp|telegram|discordbot|skypeuripreview|preview|fetch|monitor|curl|wget|python-requests|go-http|axios|okhttp|headless|phantomjs|lighthouse|pingdom|uptimerobot|statuscake|gtmetrix|semrush|ahrefs|mj12bot|dotbot|petalbot|dataforseo|censys|zgrab|masscan|dataprovider|webdriver|selenium|puppeteer|playwright/i';

    /** Is this user agent one we never count (a bot, a crawler, a monitor, or none at all)? */
    public static function isBot(string $userAgent): bool
    {
        return $userAgent === '' || (bool) preg_match(self::BOT_PATTERN, $userAgent);
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track successful GET requests for non-admin, non-api front-end pages.
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && !$request->is('admin*') && !$request->is('api*')) {
            $this->logVisit($request);
        }

        return $response;
    }

    protected function logVisit(Request $request): void
    {
        // Signed-in people are not the audience these figures are read for: an
        // editor checking a page they just published, or a customer in their own
        // account, is the site looking at itself. Counting them inflates every
        // number on the analytics page — most visibly on a site whose own team
        // browses it all day. This middleware is pushed to the end of the `web`
        // group, so the session has already been resolved by the time it runs.
        if (auth()->check()) {
            return;
        }

        // A Host that is not this site — the bare IP, or a domain a scanner put in the
        // header — reached us only because the web server has no catch-all block. On the
        // demo these were 60% of all rows, each shown on Recent Visits as a foreign domain.
        if (!falcon_is_site_host($request->getHost())) {
            return;
        }

        $userAgent = (string) $request->header('User-Agent');

        // Skip bots/crawlers/monitors so analytics reflect real human traffic.
        if (self::isBot($userAgent)) {
            return;
        }

        // The column is a varchar(255), and some browsers and in-app webviews send more than
        // that: the whole insert failed (SQLSTATE 22001) and the visit was lost. The parser
        // still reads the full string; only the stored copy is cut.
        $row = Analytics::create([
            'ip_address' => $request->ip(),
            'url' => $request->fullUrl(),
            'referrer' => $request->header('referer'),
            'user_agent' => mb_substr($userAgent, 0, 255),
            'browser' => UserAgentParser::browser($userAgent),
            'os' => UserAgentParser::os($userAgent),
            'device_type' => UserAgentParser::device($userAgent),
        ] + (Analytics::hasHumanColumn() ? [
            // Not counted until the page's own script, in a real browser, confirms the visit
            // (HumanVisits). Most requests that never confirm are bots posing as Chrome.
            'human' => false,
        ] : []));

        self::resolveGeoLater((int) $row->id, (string) $request->ip());
    }

    /**
     * Fill in the visitor's country AFTER the response is sent, so it never slows the page.
     * Cached per IP for 30 days, so each visitor is looked up at most once a month.
     */
    public static function resolveGeoLater(int $rowId, string $ip): void
    {
        app()->terminating(function () use ($rowId, $ip) {
            try {
                $geo = falcon_geoip($ip);

                if (!empty($geo['country'])) {
                    Analytics::withoutGlobalScope('human')->where('id', $rowId)->update([
                        'country' => $geo['country'],
                        'country_code' => $geo['country_code'] ?: null,
                        'city' => $geo['city'] ?: null,
                    ]);
                }
            } catch (\Throwable $e) {
                // Geo is best-effort; never let it break a page request.
            }
        });
    }
}
