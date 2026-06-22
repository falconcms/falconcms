<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use FalconCms\Core\Models\Analytics;

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
        $userAgent = (string) $request->header('User-Agent');

        // Skip bots/crawlers/monitors so analytics reflect real human traffic.
        if ($userAgent === '' || preg_match($this->botPattern, $userAgent)) {
            return;
        }

        $row = Analytics::create([
            'ip_address'  => $request->ip(),
            'url'         => $request->fullUrl(),
            'referrer'    => $request->header('referer'),
            'user_agent'  => $userAgent,
            'browser'     => \FalconCms\Core\Support\UserAgentParser::browser($userAgent),
            'os'          => \FalconCms\Core\Support\UserAgentParser::os($userAgent),
            'device_type' => \FalconCms\Core\Support\UserAgentParser::device($userAgent),
        ]);

        // Resolve geo location AFTER the response is sent so it never slows the page.
        // Cached per IP for 30 days, so each visitor is looked up at most once a month.
        $ip = $request->ip();
        app()->terminating(function () use ($row, $ip) {
            try {
                $geo = Cache::remember('falcon_geoip_' . md5((string) $ip), now()->addDays(30), function () use ($ip) {
                    if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)
                        || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')
                        || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
                        return ['country' => null, 'country_code' => null, 'city' => null];
                    }
                    $resp = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,countryCode,city']);
                    if ($resp->ok() && $resp->json('status') === 'success') {
                        return [
                            'country'      => $resp->json('country'),
                            'country_code' => $resp->json('countryCode'),
                            'city'         => $resp->json('city'),
                        ];
                    }
                    return ['country' => null, 'country_code' => null, 'city' => null];
                });

                if (!empty($geo['country'])) {
                    Analytics::where('id', $row->id)->update([
                        'country'      => $geo['country'],
                        'country_code' => $geo['country_code'] ?: null,
                        'city'         => $geo['city'] ?: null,
                    ]);
                }
            } catch (\Throwable $e) {
                // Geo is best-effort; never let it break a page request.
            }
        });
    }
}
