<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use FalconCms\Core\Models\Language;

class LocalizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->segment(1);

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('cms_languages')) {
                return $next($request);
            }

            $supportedLocales = Language::where('status', true)->pluck('code')->toArray();

            if (in_array($locale, $supportedLocales)) {
                App::setLocale($locale);
            } else {
                // Check session or default
                $default = Language::where('is_default', true)->first();
                $locale = session('locale', $default->code ?? 'en');
                App::setLocale($locale);
            }
        } catch (\Exception $e) {
            // Silently fail and use default if DB not ready
        }

        return $next($request);
    }
}
