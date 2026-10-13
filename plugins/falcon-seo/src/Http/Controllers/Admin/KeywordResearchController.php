<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconSeo\Support\KeywordDemand;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Search demand for the editor's Keywords tab, and the Keyword Planner import behind it
 * (SEO → Settings → Keyword research).
 */
class KeywordResearchController extends Controller
{
    public function demand(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|max:200', 'country' => 'nullable|string|size:2']);

        return response()->json(KeywordDemand::lookup($data['q'], $data['country'] ?? null));
    }

    /** Editor → SEO → Analysis: searches in each chosen country, one batch per request. */
    public function countries(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|max:200',
            'countries' => 'required|array|max:'.KeywordDemand::COUNTRY_BATCH,
            'countries.*' => 'string|size:2',
        ]);

        return response()->json(['rows' => KeywordDemand::byCountry($data['q'], $data['countries'])]);
    }

    public function import(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('manage_settings'), 403);
        // By extension: Keyword Planner writes UTF-16, which content sniffing calls "octet-stream".
        $request->validate(['keywords_file' => 'required|file|max:20480|extensions:csv,tsv,txt']);

        try {
            $count = KeywordDemand::import((string) file_get_contents($request->file('keywords_file')->getRealPath()));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['keywords_file' => 'The file could not be read: '.$e->getMessage()]);
        }

        return back()->with($count > 0 ? 'success' : 'error', $count > 0
            ? number_format($count).' keywords imported.'
            : 'No keywords found. The file needs a "Keyword" column and a volume column such as "Avg. monthly searches".');
    }

    public function clear()
    {
        abort_unless(auth()->user()?->hasPermission('manage_settings'), 403);
        DB::table('cms_keyword_volumes')->delete();

        return back()->with('success', 'Imported keyword data removed.');
    }
}
