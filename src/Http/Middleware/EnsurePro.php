<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks access to a Pro feature on an unlicensed site with a clear message instead
 * of a bare error, so visitors never mistake a gated feature for a bug.
 *
 * Applied per route group as `EnsurePro::class.':<feature>'` (e.g. ':ecommerce',
 * ':multilang'). Routes stay registered so views that link to them with route(...)
 * never break; only reaching a gated screen is denied:
 *   - AJAX/JSON (add-to-cart, cart updates, admin fetches) → a JSON payload the
 *     existing scripts already surface as a message.
 *   - A full page (cart, checkout, a gated admin screen) → a clear "Pro feature" page.
 */
class EnsurePro
{
    public function handle(Request $request, Closure $next, string $feature, string $mode = '')
    {
        // Default: grandfather-inclusive (falcon_pro) — a feature used before freemium
        // stays reachable. 'strict' mode ignores grandfathering (license OR grace only),
        // so the feature locks the moment the grace window ends — used for the storefront
        // (browsing products stays open, but cart/checkout/account become Pro-only).
        $available = $mode === 'strict' ? falcon_pro_editable($feature) : falcon_pro($feature);
        if ($available) {
            return $next($request);
        }

        $message = 'This feature is available in the Pro version.';

        if ($request->ajax() || $request->expectsJson()) {
            // 200 + success:false is the shape the storefront/admin scripts read to show
            // an inline message (a 4xx would fall into their generic "try again" branch).
            return response()->json(['success' => false, 'pro' => true, 'message' => $message], 200);
        }

        return response()->view('falcon-cms::pro-required', [
            'message' => $message,
            'feature' => $feature,
        ], 200);
    }
}
