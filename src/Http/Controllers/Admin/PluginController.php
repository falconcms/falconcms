<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\PluginManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

/**
 * Admin UI for drop-in plugins — the functional counterpart to the Themes
 * screen. Lists discovered plugins and drives their lifecycle (activate /
 * deactivate / update / uninstall) plus install-by-upload and install-by-URL.
 */
class PluginController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user() || ! auth()->user()->hasPermission('manage_plugins')) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(Request $request, PluginManager $plugins)
    {
        $all = $plugins->all();

        $counts = [
            'all'      => count($all),
            'active'   => count(array_filter($all, fn ($p) => $p['active'])),
            'inactive' => count(array_filter($all, fn ($p) => ! $p['active'])),
        ];

        $status = $request->query('status');
        if ($status === 'active') {
            $all = array_filter($all, fn ($p) => $p['active']);
        } elseif ($status === 'inactive') {
            $all = array_filter($all, fn ($p) => ! $p['active']);
        }

        if ($search = trim((string) $request->query('s'))) {
            $needle = strtolower($search);
            $all = array_filter($all, fn ($p) => str_contains(strtolower(($p['name'] ?? '') . ' ' . ($p['description'] ?? '') . ' ' . $p['slug']), $needle));
        }

        return view('falcon-cms::admin.plugins.index', [
            'plugins'    => $all,
            'counts'     => $counts,
            'status'     => $status,
            'search'     => $search ?? '',
            'pluginsDir' => $plugins->path(),
        ]);
    }

    public function create()
    {
        return view('falcon-cms::admin.plugins.create');
    }

    public function activate(string $slug, PluginManager $plugins)
    {
        return $this->flash($plugins->activate($slug));
    }

    public function deactivate(string $slug, PluginManager $plugins)
    {
        return $this->flash($plugins->deactivate($slug));
    }

    public function update(string $slug, PluginManager $plugins)
    {
        return $this->flash($plugins->update($slug));
    }

    public function destroy(string $slug, PluginManager $plugins)
    {
        return $this->flash($plugins->uninstall($slug));
    }

    public function upload(Request $request, PluginManager $plugins)
    {
        $request->validate([
            'plugin_zip' => 'required|file|mimes:zip|max:51200', // 50 MB
        ]);

        $file   = $request->file('plugin_zip');
        $result = $plugins->installFromZip($file->getRealPath(), $file->getClientOriginalName());

        if ($result['ok']) {
            falcon_log_activity('plugin_installed', 'Installed plugin: ' . ($result['slug'] ?? ''));
        }
        return $this->flash($result);
    }

    /**
     * Install a plugin from a direct ZIP URL (a lightweight "marketplace":
     * paste a release ZIP link). Downloads to a temp file then reuses the ZIP
     * installer with all its safety checks.
     */
    public function installUrl(Request $request, PluginManager $plugins)
    {
        $request->validate(['plugin_url' => 'required|url']);

        $url = $request->input('plugin_url');
        try {
            $contents = @file_get_contents($url);
        } catch (\Throwable $e) {
            $contents = false;
        }
        if ($contents === false) {
            return $this->flash(['ok' => false, 'message' => 'Could not download the plugin from that URL.']);
        }

        $tmp = storage_path('app/tmp_plugin_dl_' . time() . '.zip');
        File::put($tmp, $contents);
        $result = $plugins->installFromZip($tmp, basename(parse_url($url, PHP_URL_PATH) ?: 'plugin.zip'));
        @unlink($tmp);

        if ($result['ok']) {
            falcon_log_activity('plugin_installed', 'Installed plugin from URL: ' . ($result['slug'] ?? ''));
        }
        return $this->flash($result);
    }

    /** Redirect back with the manager result as a success/error flash. */
    protected function flash(array $result)
    {
        return redirect()
            ->route('admin.plugins.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
