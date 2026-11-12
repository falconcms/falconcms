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

    /**
     * Request attribute: old builder-grid page parameters found in this request, old => new.
     * A grid paged with ?cp_ + a hash of its settings; it now pages with ?page= or ?cp_ + its
     * element id's hash (falcon_grid_page_param). Only the rendered grid knows its old name,
     * so the redirect is decided here, after the page is rendered.
     */
    public const RENAMED = 'falcon.grid_page_renamed';

    public function handle(Request $request, Closure $next)
    {
        if (!$request->is('admin', 'admin/*', 'api/*')) {
            Paginator::defaultView(self::VIEW);
            Paginator::defaultSimpleView(self::VIEW);
        }

        $response = $next($request);

        $renamed = $request->attributes->get(self::RENAMED);
        if ($renamed && $request->isMethod('get') && !$request->ajax() && $response->getStatusCode() === 200) {
            $query = $request->query();
            foreach ($renamed as $old => $new) {
                if (array_key_exists($old, $query)) {
                    $query[$new] = $query[$old];
                    unset($query[$old]);
                }
            }

            return redirect()->to($request->url().($query ? '?'.http_build_query($query) : ''), 301);
        }

        return $response;
    }
}
