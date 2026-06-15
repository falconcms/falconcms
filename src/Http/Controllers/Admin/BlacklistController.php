<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\BlockedIp;
use Illuminate\Http\Request;

class BlacklistController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }

        $query = BlockedIp::latest();

        if ($request->filled('s')) {
            $query->where('ip_address', 'like', '%' . $request->s . '%')
                  ->orWhere('country', 'like', '%' . $request->s . '%')
                  ->orWhere('reason', 'like', '%' . $request->s . '%');
        }

        $blockedIps = $query->paginate(10)->withQueryString();
        return view('falcon-cms::admin.blacklist.index', compact('blockedIps'));
    }

    public function bulk(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }

        $action = $request->input('action') ?: $request->input('action2');
        $ids = $request->input('ids', []);

        if ($action === 'delete' && !empty($ids)) {
            BlockedIp::whereIn('id', $ids)->delete();
            return redirect()->route('admin.blacklist.index')->with('success', 'Selected IPs unblocked successfully.');
        }

        return redirect()->back();
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }
        $blockedIp = BlockedIp::findOrFail($id);
        $blockedIp->delete();

        return redirect()->route('admin.blacklist.index')->with('success', 'IP unblocked successfully.');
    }
}
