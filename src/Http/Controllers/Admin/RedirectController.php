<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Redirect;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    public function index(Request $request)
    {
        $query = Redirect::query();
        
        if ($request->filled('s')) {
            $query->where('old_url', 'like', '%' . $request->s . '%')
                  ->orWhere('new_url', 'like', '%' . $request->s . '%');
        }

        $redirects = $query->latest()->paginate(10);
        return view('falcon-cms::admin.seo.redirects', compact('redirects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'old_url' => 'required|string',
            'new_url' => 'required|string',
            'status_code' => 'required|in:301,302'
        ]);

        // Ensure leading slash
        $validated['old_url'] = '/' . ltrim($validated['old_url'], '/');
        
        Redirect::updateOrCreate(
            ['old_url' => $validated['old_url']],
            ['new_url' => $validated['new_url'], 'status_code' => $validated['status_code']]
        );

        return redirect()->back()->with('success', 'Redirect added successfully.');
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();
        return redirect()->back()->with('success', 'Redirect deleted successfully.');
    }

    public function bulk(Request $request)
    {
        $ids = $request->input('ids');
        if (!$ids) return redirect()->back()->with('error', 'Nothing selected.');
        
        Redirect::whereIn('id', $ids)->delete();
        return redirect()->back()->with('success', 'Selected redirects deleted.');
    }
}
