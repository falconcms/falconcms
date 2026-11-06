<?php

namespace FalconCms\Core\Http\Controllers;

use FalconCms\Core\Support\PageTime;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Where a page reports the time a visitor spent on it. Outside the web middleware group, as the
 * page may be a day-old cached copy: no session, no cookies, no CSRF token to go stale. Always
 * answers 204, so it tells a scraper nothing about what was counted.
 */
class PageTimeHitController extends Controller
{
    public function __invoke(Request $request)
    {
        PageTime::record(
            max(0, (int) $request->input('i', 0)),
            mb_substr((string) $request->input('p', ''), 0, 255),
            (string) $request->input('s', ''),
            (int) $request->input('d', 0),
            $request->boolean('f'),
            $request->boolean('n'),
            (string) $request->ip(),
            (string) $request->userAgent()
        );

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
