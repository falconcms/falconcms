<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\ExportManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class ExportController extends Controller
{
    private function checkAccess(): void
    {
        $user = auth()->user();
        if (!$user || (!$user->hasPermission('manage_settings') && !$user->hasPermission('access_backup_restore'))) {
            abort(403);
        }
    }

    public function index()
    {
        $this->checkAccess();

        // Group the dynamic sources so the view can render section-aware lists.
        $sources = collect(ExportManager::sources());
        $grouped = $sources->groupBy('group');

        return view('falcon-cms::admin.tools.export', [
            'sources' => $sources,
            'grouped' => $grouped,
        ]);
    }

    public function download(Request $request)
    {
        $this->checkAccess();

        $content = $request->input('content', 'all');

        // Validate the choice against the live source list (so stale/forged keys are rejected).
        if ($content !== 'all') {
            $valid = collect(ExportManager::sources())->pluck('key')->all();
            if (!in_array($content, $valid, true)) {
                return back()->with('error', 'That export option is no longer available. Please choose again.');
            }
        }

        @set_time_limit(0);

        $xml      = ExportManager::generate($content);
        $filename = ExportManager::filename($content);

        if (function_exists('falcon_log_activity')) {
            falcon_log_activity('exported', 'Exported site content (' . $content . ')');
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($xml),
        ]);
    }
}
