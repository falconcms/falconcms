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

        // Backfill geo for blocked IPs missing a country (older rows, or earlier failed lookups).
        // Runs after the response so the page is never slowed; ip-api is rate-limited, so cap per load.
        $needGeo = BlockedIp::where(function ($q) {
                $q->whereNull('country')->orWhere('country', '')->orWhere('country', 'Unknown');
            })->whereNotIn('ip_address', ['127.0.0.1', '::1'])
            ->limit(15)->get(['id', 'ip_address']);
        if ($needGeo->isNotEmpty()) {
            app()->terminating(function () use ($needGeo) {
                foreach ($needGeo as $row) {
                    try {
                        $geo = falcon_geoip($row->ip_address);
                        if (!empty($geo['country'])) {
                            BlockedIp::where('id', $row->id)->update([
                                'country'      => $geo['country'],
                                'country_code' => $geo['country_code'] ? strtolower($geo['country_code']) : null,
                                'city'         => $geo['city'],
                                'region'       => $geo['region'],
                                'isp'          => $geo['isp'],
                            ]);
                        }
                    } catch (\Throwable $e) {}
                }
            });
        }

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
