<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\SiteHealth;
use Illuminate\Routing\Controller;

/**
 * Settings → Site Health. The page renders the fast checks and the Info inventory; the slow
 * tests (vulnerability audit, code scan, error log, updates, disk) are fetched one at a time
 * by the page through test(), so a slow network never holds the screen up.
 */
class SiteHealthController extends Controller
{
    private function authorizeSettings(): void
    {
        if (!auth()->user()?->hasPermission('manage_settings')) {
            abort(403);
        }
    }

    public function index()
    {
        $this->authorizeSettings();

        return view('falcon-cms::admin.settings.site-health', [
            'checks' => SiteHealth::checks(),
            'asyncTests' => SiteHealth::ASYNC,
            'info' => SiteHealth::info(),
        ]);
    }

    public function test(string $test)
    {
        $this->authorizeSettings();
        abort_unless(array_key_exists($test, SiteHealth::ASYNC), 404);
        // A large code base or a slow Packagist can take a while. Raise a short limit, never
        // impose one: 0 means unlimited, and replacing it with 120 would cut work short.
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0 && $limit < 120) {
            @set_time_limit(120);
        }

        return response()->json(['results' => SiteHealth::asyncTest($test)]);
    }
}
