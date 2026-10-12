<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\WebVitals;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Analytics → Web Vitals: real visitors' Core Web Vitals, and the beacon that collects them. */
class WebVitalsController extends Controller
{
    public function index(Request $request)
    {
        // The Analytics menu's permission, as for the other Analytics screens.
        abort_unless(auth()->user()?->hasPermission('manage_analytics'), 403);

        $days = in_array((int) $request->query('range'), [1, 7, 28, 90], true) ? (int) $request->query('range') : 28;
        $device = in_array($request->query('device'), ['m', 'd'], true) ? (string) $request->query('device') : '';

        return view('falcon-cms::admin.analytics.web-vitals', [
            'report' => WebVitals::report($days, $device),
            'days' => $days,
            'device' => $device,
            'enabled' => WebVitals::enabled(),
        ]);
    }

    /** The visitor's browser posts here with sendBeacon as the page is left. Always 204: nothing to say back. */
    public function store(Request $request)
    {
        WebVitals::record($request);

        return response()->noContent();
    }
}
