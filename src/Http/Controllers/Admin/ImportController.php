<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Services\WordPressImporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Native importer — the counterpart to the Export tool. It accepts the
 * WordPress-compatible WXR (.xml) file produced by Tools → Export (or any
 * WordPress export) and restores posts, pages, custom post types and
 * taxonomy terms back into the site.
 */
class ImportController extends Controller
{
    private function checkAccess(): void
    {
        $user = auth()->user();
        if (!$user || (!$user->hasPermission('manage_settings') && !$user->hasPermission('access_backup_restore'))) {
            abort(403);
        }
    }

    private function iniToBytes(string $val): int
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1] ?? '');
        $num  = (int) $val;
        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function maxUploadBytes(): int
    {
        $upload = $this->iniToBytes(ini_get('upload_max_filesize') ?: '8M');
        $post   = $this->iniToBytes(ini_get('post_max_size')       ?: '8M');
        return min($upload, $post);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
        if ($bytes >= 1024 * 1024)        return round($bytes / 1024 / 1024, 0) . ' MB';
        return round($bytes / 1024, 0) . ' KB';
    }

    public function index()
    {
        $this->checkAccess();

        return view('falcon-cms::admin.tools.import', [
            'maxUploadBytes' => $this->maxUploadBytes(),
            'maxUploadHuman' => $this->formatBytes($this->maxUploadBytes()),
        ]);
    }

    public function import(Request $request)
    {
        $this->checkAccess();

        $maxKb = (int) ($this->maxUploadBytes() / 1024);

        $request->validate([
            'import_file' => [
                'required', 'file', 'max:' . $maxKb,
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['xml', 'wxr'])) {
                        $fail('Only export files (.xml or .wxr) are allowed.');
                    }
                },
            ],
        ], [
            'import_file.required' => 'Please choose an export (.xml) file.',
            'import_file.max'      => 'The file exceeds the server upload limit of ' . $this->formatBytes($this->maxUploadBytes()) . '.',
        ]);

        $xml = file_get_contents($request->file('import_file')->getRealPath());

        if (!$xml || stripos($xml, '<rss') === false || stripos($xml, 'wordpress.org/export') === false) {
            return back()->with('error', 'That does not look like a valid export file. Use a file produced by Tools → Export (or a WordPress WXR export).');
        }

        @set_time_limit(0);

        $summary = (new WordPressImporter())->importFromXml($xml, [
            'user_id'      => auth()->id(),
            'lang'         => app()->getLocale(),
            'import_pages' => $request->boolean('import_pages', true),
        ]);

        if (function_exists('falcon_log_activity')) {
            falcon_log_activity('imported', 'Imported content from export file (posts: ' . ($summary['posts'] ?? 0) . ', pages: ' . ($summary['pages'] ?? 0) . ')');
        }
        if (function_exists('clear_page_cache')) {
            clear_page_cache();
        }

        return back()->with('import_summary', $summary)->with('success', 'Import finished.');
    }
}
