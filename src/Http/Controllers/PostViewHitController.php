<?php

namespace FalconCms\Core\Http\Controllers;

use FalconCms\Core\Support\PostViews;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Where a post page's beacon reports a reader. Outside the web middleware group on purpose: no
 * session, no cookies and no CSRF token to go stale in a cached page. Always answers 204, so it
 * tells a scraper nothing about what was or was not counted.
 */
class PostViewHitController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            PostViews::record($id, (string) $request->ip(), (string) $request->userAgent());
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
