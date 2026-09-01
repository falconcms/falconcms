<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Support\UserAgentParser;
use Illuminate\Http\Request;

class TrackVisits
{
    /** User-agent fragments that identify bots/crawlers we don't want to count as visits. */
    protected string $botPattern = '/bot|crawl|spider|slurp|mediapartners|bingpreview|facebookexternalhit|whatsapp|telegram|discordbot|skypeuripreview|preview|fetch|monitor|curl|wget|python-requests|go-http|axios|okhttp|headless|phantomjs|lighthouse|pingdom|uptimerobot|statuscake|gtmetrix|semrush|ahrefs|mj12bot|dotbot|petalbot|dataforseo|censys|zgrab|masscan/i';

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

        $userAgent = (string) $request->header('User-Agent');

        // Skip bots/crawlers/monitors so analytics reflect real human traffic.
        if ($userAgent === '' || preg_match($this->botPattern, $userAgent)) {
            return;
        }

        $row = Analytics::create([
            'ip_address' => $request->ip(),
            'url' => $request->fullUrl(),
            'referrer' => $request->header('referer'),
            'user_agent' => $userAgent,
            'browser' => UserAgentParser::browser($userAgent),
            'os' => UserAgentParser::os($userAgent),
            'device_type' => UserAgentParser::device($userAgent),
        ]);

        // Resolve geo location AFTER the response is sent so it never slows the page.
        // Cached per IP for 30 days, so each visitor is looked up at most once a month.
        $ip = $request->ip();
        app()->terminating(function () use ($row, $ip) {
            try {
                $geo = falcon_geoip((string) $ip);

                if (!empty($geo['country'])) {
                    Analytics::where('id', $row->id)->update([
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
