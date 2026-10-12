<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Models\Redirect;
use FalconCms\Core\Support\NotFoundLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RedirectController extends Controller
{
    public function index(Request $request)
    {
        $query = Redirect::query();

        if ($request->filled('s')) {
            $query->where('old_url', 'like', '%'.$request->s.'%')
                ->orWhere('new_url', 'like', '%'.$request->s.'%');
        }

        $redirects = $query->latest()->paginate(10);
        $notFound = NotFoundLog::top(50);
        // "Redirect" on a 404 row opens the form with its address already in.
        $prefill = (string) $request->query('from', '');

        return view('falcon-cms::admin.seo.redirects', compact('redirects', 'notFound', 'prefill'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'old_url' => 'required|string',
            'new_url' => 'required|string',
            'status_code' => 'required|in:301,302',
        ]);

        // Ensure leading slash
        $validated['old_url'] = '/'.ltrim($validated['old_url'], '/');

        Redirect::updateOrCreate(
            ['old_url' => $validated['old_url']],
            ['new_url' => $validated['new_url'], 'status_code' => $validated['status_code']]
        );

        // A redirected address is no longer missing.
        NotFoundLog::forget($validated['old_url']);

        return redirect()->route('admin.redirects.index')->with('success', 'Redirect added successfully.');
    }

    /** Dismiss one 404 entry (by path), or all of them. */
    public function forgetNotFound(Request $request)
    {
        if ($request->boolean('all')) {
            DB::table('cms_not_found_log')->delete();
        } else {
            NotFoundLog::forget((string) $request->input('path', ''));
        }

        return redirect()->back()->with('success', '404 list updated.');
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();

        return redirect()->back()->with('success', 'Redirect deleted successfully.');
    }

    public function bulk(Request $request)
    {
        $ids = $request->input('ids');
        if (!$ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }

        Redirect::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', 'Selected redirects deleted.');
    }
}
