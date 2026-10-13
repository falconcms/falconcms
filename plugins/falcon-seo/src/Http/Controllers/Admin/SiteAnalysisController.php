<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconCms\Core\Models\Post;
use FalconSeo\Support\SiteScan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/** SEO → Site Analysis: every page's score and the problems across the site. */
class SiteAnalysisController extends Controller
{
    /** Pages per "Scan now" request: each is fetched and its links checked, so a few at a time. */
    public const BATCH = 3;

    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('access_seo_site_analysis'), 403);

        return view('falcon-seo::admin.site-analysis', ['summary' => SiteScan::summary()]);
    }

    /** "Scan now": the next few pages that are due; the screen calls again until none are left. */
    public function scan(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('access_seo_site_analysis'), 403);
        // With "all", every page is due again: the first call clears the dates.
        if ($request->boolean('restart')) {
            DB::table(SiteScan::TABLE)->update(['analyzed_at' => null]);
        }
        $done = SiteScan::run(self::BATCH, $request->boolean('links', true));
        $summary = SiteScan::summary();

        return response()->json(['done' => $done, 'due' => $summary['due'], 'checked' => $summary['checked'], 'pages' => $summary['pages']]);
    }

    /** Check one page again now (the "Check again" link on a row). */
    public function rescan(Post $post)
    {
        abort_unless(auth()->user()?->hasPermission('access_seo_site_analysis'), 403);
        SiteScan::analyze($post);

        return redirect()->back()->with('success', 'Checked "'.$post->title.'" again.');
    }
}
