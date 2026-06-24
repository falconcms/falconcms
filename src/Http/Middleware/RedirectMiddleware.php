<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use FalconCms\Core\Models\Redirect;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\View;

class RedirectMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Handle Redirects — guarded so a missing table (before migration / after a DB reset)
        //    degrades gracefully instead of throwing a 500 on every request.
        $path = $request->path();
        $normalizedPath = '/' . ltrim($path, '/');

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

            // If the view is inside resources/views
            if ($viewPath && str_starts_with($viewPath, $rootViewsPath)) {
                $isInTheme = str_starts_with($viewPath, $themesPath);
                $isInVendor = str_starts_with($viewPath, $vendorPath);
                
                // Block if it's NOT in theme and NOT in vendor
                if (!$isInTheme && !$isInVendor) {
                    abort(404, "Security Restriction: View file must be inside the themes directory.");
                }
            }
        });

        return $next($request);
    }

    /** Whether the redirects table exists (cached per request; tolerates DB errors). */
    protected function redirectsTableExists(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $exists = Schema::hasTable('cms_redirects');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }
        return $exists;
    }
}
