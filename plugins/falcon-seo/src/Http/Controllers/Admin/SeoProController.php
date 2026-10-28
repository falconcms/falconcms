<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconCms\Core\Models\Post;
use FalconSeo\Support\DataForSeo;
use FalconSeo\Support\Pro;
use FalconSeo\Support\SeoAi;
use FalconSeo\Support\SeoFixer;
use FalconSeo\Support\SeoReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * The Pro tools: Google keyword numbers, one-click fixes, the weekly email. (Writing with AI —
 * "Suggest with AI" in the editor — is the Falcon AI plugin's; the AI fixes here ask it.)
 */
class SeoProController extends Controller
{
    /** Pages fixed per "Fix all" request: AI fixes take a few seconds each. */
    public const FIX_BATCH = 5;

    /** Editor → SEO → Analysis: Google's searches, cost per click and difficulty by country (DataForSEO). */
    public function metrics(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|max:80',
            'countries' => 'required|array|max:'.DataForSeo::COUNTRY_BATCH,
            'countries.*' => 'string|size:2',
        ]);
        if ($locked = $this->locked()) {
            return $locked;
        }
        if (!DataForSeo::available()) {
            return response()->json(['message' => 'Add your DataForSEO login in SEO → Settings → Keyword data.'], 422);
        }

        return response()->json(['rows' => DataForSeo::metrics($data['q'], $data['countries'])]);
    }

    /** Site Analysis → "Fix" / "Fix all": a few pages per request, the screen calls again for the rest. */
    public function fix(Request $request)
    {
        $data = $request->validate([
            'check' => 'required|in:'.implode(',', SeoFixer::FIXABLE),
            'method' => 'nullable|in:auto,ai',
            'post_ids' => 'required|array|max:'.self::FIX_BATCH,
            'post_ids.*' => 'integer',
        ]);
        if ($locked = $this->locked()) {
            return $locked;
        }
        $method = $data['method'] ?? 'auto';
        if ($method === 'ai' && !SeoAi::available()) {
            return response()->json(['message' => SeoAi::NOT_SET_UP], 422);
        }

        $results = [];
        foreach (Post::whereIn('id', $data['post_ids'])->get() as $post) {
            $results[] = ['post_id' => $post->id, 'title' => (string) $post->title] + SeoFixer::fix($post, $data['check'], $method);
        }
        // Once for the whole batch: cached copies of the fixed pages still show the old head and content.
        if (in_array(true, array_column($results, 'fixed'), true)) {
            clear_page_cache();
        }

        return response()->json(['results' => $results]);
    }

    /** SEO → Settings → Weekly SEO email → "Send a test now". */
    public function testReport(Request $request)
    {
        if ($locked = $this->locked()) {
            return redirect()->back()->with('error', 'The weekly SEO email is part of FalconCMS Pro.');
        }
        // The button submits the settings form, so the addresses typed beside it come along:
        // keep them, or the test goes to the old ones and the field comes back empty.
        if ($request->has(Pro::REPORT_TO) && auth()->user()?->hasPermission('access_seo_settings')) {
            DB::table('cms_settings')->updateOrInsert(['key' => Pro::REPORT_TO],
                ['value' => trim((string) $request->input(Pro::REPORT_TO, '')), 'updated_at' => now()]);
            forget_cms_options_cache();
        }
        $sent = SeoReport::send();

        return redirect()->back()->with($sent ? 'success' : 'error', $sent
            ? "Test email sent to {$sent} address(es)."
            : 'Nothing sent: add a recipient, and check a few pages under Site Analysis first.');
    }

    private function locked()
    {
        return Pro::active() ? null : response()->json(['message' => 'This is part of FalconCMS Pro.', 'upgrade' => falcon_upgrade_url()], 403);
    }
}
