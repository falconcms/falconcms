<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use FalconCms\Core\Models\Analytics;

class TrackVisits
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track successful GET requests for non-admin pages
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && !$request->is('admin*') && !$request->is('api*')) {
            $this->logVisit($request);
        }

        return $response;
    }

    protected function logVisit(Request $request)
    {
        $userAgent = $request->header('User-Agent');
        
        Analytics::create([
            'ip_address'  => $request->ip(),
            'url'         => $request->fullUrl(),
            'referrer'    => $request->header('referer'),
            'user_agent'  => $userAgent,
            'browser'     => \FalconCms\Core\Support\UserAgentParser::browser($userAgent),
            'os'          => \FalconCms\Core\Support\UserAgentParser::os($userAgent),
            'device_type' => \FalconCms\Core\Support\UserAgentParser::device($userAgent),
        ]);
    }
}
