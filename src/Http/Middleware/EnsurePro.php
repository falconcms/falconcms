<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks access to a Pro feature's admin screens on an unlicensed site.
 *
 * Applied per route group as `EnsurePro::class.':<feature>'` (e.g. ':ecommerce',
 * ':multilang'). The routes stay registered so admin views that link to them with
 * route(...) never break — only reaching a gated screen is denied. The sidebar hides
 * these items separately, so this is the backstop for someone hitting a URL directly.
 */
class EnsurePro
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        if (! falcon_pro($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
