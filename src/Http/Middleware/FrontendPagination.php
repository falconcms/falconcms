<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

/**
 * Every pagination on the site's pages looks the same: a theme's or a plugin's plain
 * `$items->links()` renders FalconCMS's frontend pagination instead of Laravel's default.
 * The admin keeps its own (it passes its view, or gets Laravel's default as before).
 */
class FrontendPagination
{
    public const VIEW = 'falcon-cms::components.frontend.pagination';

    public function handle(Request $request, Closure $next)
    {
        if (!$request->is('admin', 'admin/*', 'api/*')) {
            Paginator::defaultView(self::VIEW);
            Paginator::defaultSimpleView(self::VIEW);
        }

        return $next($request);
    }
}
