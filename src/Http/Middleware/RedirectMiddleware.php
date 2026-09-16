<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class RedirectMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Handle Redirects — guarded so a missing table (before migration / after a DB reset)
        //    degrades gracefully instead of throwing a 500 on every request.
        $path = $request->path();
        $normalizedPath = '/'.ltrim($path, '/');

        if ($this->redirectsTableExists()) {
            $redirect = Redirect::where('old_url', $normalizedPath)
                ->orWhere('old_url', $path)
                ->first();

            if ($redirect) {
                $redirect->increment('hits');
                $redirect->update(['last_hit_at' => now()]);

                return redirect($redirect->new_url, $redirect->status_code);
            }
        }

        // 2. Strict Theme Enforcement via View Composer
        View::composer('*', function ($view) {
            $viewPath = realpath($view->getPath());
            $themesPath = realpath(resource_path('views/themes'));
            $rootViewsPath = realpath(resource_path('views'));
            $vendorPath = realpath(resource_path('views/vendor'));

            // Plugins live in resources/views/plugins since v2.6.7, so a plugin's own
            // views now sit inside resources/views and have to be allowed here — without
            // this, rendering any plugin view on the front-end aborts the request.
            // Guarded against realpath() returning false for a site that has no plugins
            // directory: str_starts_with($path, false) compares against '' and matches
            // everything, which would turn this whole check off.
            $pluginsPath = realpath(resource_path('views/plugins'));

            // If the view is inside resources/views
            if ($viewPath && str_starts_with($viewPath, $rootViewsPath)) {
                $isInTheme = str_starts_with($viewPath, $themesPath);
                $isInVendor = str_starts_with($viewPath, $vendorPath);
                $isInPlugin = $pluginsPath !== false && str_starts_with($viewPath, $pluginsPath);

                // Block if it's NOT in theme, NOT in vendor and NOT a plugin's own view
                if (!$isInTheme && !$isInVendor && !$isInPlugin) {
                    abort(404, 'Security Restriction: View file must be inside the themes directory.');
                }
            }
        });

        return $next($request);
    }

    /**
     * Whether the redirects table exists (asked once per request; tolerates DB errors).
     *
     * A property, not a function-level static. A static outlives the request — it outlives the
     * whole process — so the first answer was the only answer: a site that had not migrated
     * yet cached "no table" and stopped serving redirects entirely until the workers were
     * restarted, and under a persistent runtime that is never.
     */
    protected ?bool $tableExists = null;

    protected function redirectsTableExists(): bool
    {
        if ($this->tableExists === null) {
            try {
                $this->tableExists = Schema::hasTable('cms_redirects');
            } catch (\Throwable $e) {
                $this->tableExists = false;
            }
        }

        return $this->tableExists;
    }
}
