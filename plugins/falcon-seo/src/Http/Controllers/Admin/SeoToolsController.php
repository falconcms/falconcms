<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconSeo\Support\Cloudflare;
use FalconSeo\Support\KeywordSuggestions;
use FalconSeo\Support\LinkSuggestions;
use FalconSeo\Support\NotFoundLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/** The editor's link and keyword suggestions, the 404 list and the Cloudflare purge button. */
class SeoToolsController extends Controller
{
    /** Internal link suggestions for the post being edited: where to link out, and who should link in. */
    public function relatedPosts(Request $request)
    {
        $title = (string) $request->query('s', '');
        if (mb_strlen(trim($title)) < 3 && trim((string) $request->query('keyword', '')) === '') {
            return response()->json(['outbound' => [], 'inbound' => []]);
        }

        return response()->json(LinkSuggestions::for($title, (string) $request->query('keyword', ''), (int) $request->query('exclude', 0)));
    }

    /**
     * Keyword ideas for the page being edited (SEO → Keywords): from what it is unsaved in the
     * editor, or what is saved when the editor sends no content (the page builder).
     */
    public function keywordSuggestions(Request $request)
    {
        $data = $request->validate([
            'post_id' => 'nullable|integer',
            'title' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'slug' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:2000000',
            'keyword' => 'nullable|string|max:200',
        ]);

        return response()->json(KeywordSuggestions::for(array_map(fn ($v) => (string) $v, $data) + ['post_id' => 0]));
    }

    /** SEO → Redirects: dismiss one 404 entry (by path), or all of them. */
    public function forgetNotFound(Request $request)
    {
        if ($request->boolean('all')) {
            DB::table('cms_not_found_log')->delete();
        } else {
            NotFoundLog::forget((string) $request->input('path', ''));
        }

        return redirect()->back()->with('success', '404 list updated.');
    }

    /** Settings → Integrations → Cloudflare → "Purge everything now". */
    public function purgeCloudflare()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
        $error = Cloudflare::purgeEverything();

        return redirect()->back()->with($error ? 'error' : 'success', $error ?? 'Cloudflare cache purged.');
    }
}
