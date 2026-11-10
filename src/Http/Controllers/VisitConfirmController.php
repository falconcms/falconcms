<?php

namespace FalconCms\Core\Http\Controllers;

use FalconCms\Core\Support\HumanVisits;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Where a page confirms that a person, in a real browser, is looking at it (HumanVisits).
 * Outside the web group like the other beacons — the page may be a cached copy, so there is no
 * session or CSRF token — and always 204, so it tells a scraper nothing about what was counted.
 */
class VisitConfirmController extends Controller
{
    public function __invoke(Request $request)
    {
        HumanVisits::confirm($request);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
