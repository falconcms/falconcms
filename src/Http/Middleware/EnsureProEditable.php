<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Like {@see EnsurePro}, but for WRITE actions: it allows through only when the feature is
 * fully unlocked (licensed or in the grace window) — NOT when it's merely grandfathered.
 *
 * This backs the "browse but locked" model: a grandfathered feature stays viewable (its pages
 * and read-only AJAX use EnsurePro / no gate), but creating, editing or deleting needs real Pro.
 * Same friendly response as EnsurePro: a JSON payload for AJAX (shown as a toast), a page otherwise.
 */
class EnsureProEditable
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        if (falcon_pro_editable($feature)) {
            return $next($request);
        }

        $message = 'This feature is available in the Pro version.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => false, 'pro' => true, 'message' => $message], 200);
        }

        return response()->view('falcon-cms::pro-required', [
            'message' => $message,
            'feature' => $feature,
        ], 200);
    }
}
