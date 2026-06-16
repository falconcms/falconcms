<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ThemeController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasPermission('access_themes') && !auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        $activeTheme = $settings['active_theme'] ?? 'falcon-theme';

        $themes = [];
        $appThemesPath = resource_path('views/themes');

        if (File::isDirectory($appThemesPath)) {
            foreach (File::directories($appThemesPath) as $dir) {
                $slug = basename($dir);

                $screenshot = null;
                if (File::exists($dir . '/screenshot.png')) {
                    $screenshot = asset('themes/' . $slug . '/screenshot.png');
                } elseif (File::exists($dir . '/screenshot.jpg')) {
                    $screenshot = asset('themes/' . $slug . '/screenshot.jpg');
                }

                $themeJson = [];
                $themeJsonFile = $dir . '/theme.json';
                if (File::exists($themeJsonFile)) {
                    $themeJson = json_decode(File::get($themeJsonFile), true) ?: [];
                }
                $parentSlug = $themeJson['parent'] ?? null;

                if ($parentSlug) {
                    $parentPath = resource_path('views/themes/' . $parentSlug);
                    $isActivatable = File::isDirectory($parentPath) && File::exists($parentPath . '/index.blade.php');
                } else {
                    $isActivatable = File::exists($dir . '/index.blade.php');
                }

                $themes[$slug] = [
                    'name'           => $themeJson['name'] ?? ucfirst(str_replace('-', ' ', $slug)),
                    'slug'           => $slug,
                    'screenshot'     => $screenshot,
                    'is_active'      => ($slug === $activeTheme),
                    'is_activatable' => $isActivatable,
                    'parent'         => $parentSlug,
                    'description'    => $themeJson['description'] ?? null,
                    'version'        => $themeJson['version']     ?? null,
                ];
            }
        }

        return view('falcon-cms::admin.themes.index', compact('themes', 'activeTheme'));
    }

    public function activate($slug)
    {
        if (!auth()->user()->hasPermission('access_themes') && !auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $themePath = $this->findThemePath($slug);
        if (!$themePath) {
            return redirect()->back()->with('error', 'Theme not found!');
        }

        // Detect child theme
        $themeJson  = [];
        $jsonFile   = $themePath . '/theme.json';
        if (File::exists($jsonFile)) {
            $themeJson = json_decode(File::get($jsonFile), true) ?: [];
        }
        $parentSlug = $themeJson['parent'] ?? null;

        // Validate: child theme checks parent's index.blade.php; regular theme checks its own
        if ($parentSlug) {
            $parentPath = $this->findThemePath($parentSlug);
            if (!$parentPath || !File::exists($parentPath . '/index.blade.php')) {
                return redirect()->back()->with('error', "Child theme '{$slug}' requires parent theme '{$parentSlug}' with an 'index.blade.php'.");
            }
        } elseif (!File::exists($themePath . '/index.blade.php')) {
            return redirect()->back()->with('error', "Theme '{$slug}' is invalid! It must contain an 'index.blade.php' file.");
        }

        DB::table('cms_settings')->updateOrInsert(
            ['key' => 'active_theme'],
            ['value' => $slug, 'updated_at' => now()]
        );

        falcon_log_activity('theme_activated', "Activated theme: {$slug}");

        return redirect()->back()->with('success', "Theme '{$slug}' activated successfully!");
    }

    public function destroy($slug)
    {
        if (!auth()->user()->hasPermission('access_themes') && !auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        // Prevent deleting core theme or active theme
        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();
        $activeTheme = $settings['active_theme'] ?? 'falcon-theme';

        if ($slug === 'falcon-theme') {
            return redirect()->back()->with('error', "The core 'Lazy Theme' cannot be deleted!");
        }

        if ($slug === 'falcon-theme-child') {
            return redirect()->back()->with('error', "The default child theme 'Lazy Theme Child' cannot be deleted!");
        }

        if ($slug === $activeTheme) {
            return redirect()->back()->with('error', "Active theme cannot be deleted!");
        }

        $themePath = $this->findThemePath($slug);
        if ($themePath) {
            File::deleteDirectory($themePath);
            
            // Also delete assets if they exist
            $assetsPath = public_path('themes/' . $slug);
            if (File::isDirectory($assetsPath)) {
                File::deleteDirectory($assetsPath);
            }

            falcon_log_activity('theme_deleted', "Deleted theme: {$slug}");
            return redirect()->back()->with('success', "Theme '{$slug}' deleted successfully!");
        }

        return redirect()->back()->with('error', 'Theme not found!');
    }

    public function upload(Request $request)
    {
        if (!auth()->user()->hasPermission('access_themes') && !auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $request->validate([
            'theme_zip' => 'required|file|mimes:zip|max:20480', // 20MB Max
        ]);

        $zipFile = $request->file('theme_zip');
        $zip = new \ZipArchive();
        
        if ($zip->open($zipFile->getRealPath()) === TRUE) {
            // Reject ZIP containing path traversal entries
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
                    $zip->close();
                    return redirect()->back()->with('error', 'Invalid ZIP file: contains unsafe file paths.');
                }
            }

            $tempPath = storage_path('app/temp_theme_' . time());
            File::makeDirectory($tempPath);
            $zip->extractTo($tempPath);
            $zip->close();

            // Find the theme folder (sometimes zip contains a folder, sometimes files directly)
            $files = File::directories($tempPath);
            if (count($files) === 0) {
                // Files are directly in zip, use a name based on zip file
                $themeSlug = Str::slug(pathinfo($zipFile->getClientOriginalName(), PATHINFO_FILENAME));
                $sourcePath = $tempPath;
            } else {
                // Zip contains a folder
                $sourcePath = $files[0];
                $themeSlug = basename($sourcePath);
            }

            // Target path (Main App)
            $targetPath = resource_path('views/themes/' . $themeSlug);
            
            if (File::isDirectory($targetPath)) {
                File::deleteDirectory($tempPath);
                return redirect()->back()->with('error', "Theme '{$themeSlug}' already exists!");
            }

            // Move to resources/views/themes
            File::ensureDirectoryExists(resource_path('views/themes'));
            File::moveDirectory($sourcePath, $targetPath);
            
            // Clean up temp
            if (File::isDirectory($tempPath)) File::deleteDirectory($tempPath);

            falcon_log_activity('theme_uploaded', "Uploaded theme: {$themeSlug}");

            return redirect()->back()->with('success', "Theme '{$themeSlug}' uploaded successfully!");
        }

        return redirect()->back()->with('error', 'Could not open ZIP file!');
    }

    protected function findThemePath($slug)
    {
        $path = resource_path('views/themes/' . $slug);
        return File::isDirectory($path) ? $path : null;
    }
}
