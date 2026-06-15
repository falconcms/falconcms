<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MaintenanceModeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (get_cms_option('maintenance_mode', '0') !== '1') {
            return $next($request);
        }

        if (auth()->check()) {
            return $next($request);
        }

        $message = get_cms_option('maintenance_message', "We are currently performing scheduled maintenance. We'll be back shortly!");

        return response()->view('falcon-cms::maintenance', ['message' => $message], 503);
    }
}
