<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomOptionsController extends Controller
{
    public function index($slug)
    {
        $config = config('falcon-options.pages.' . $slug);

        if (!$config) {
            abort(404, 'Options page not found.');
        }

        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('manage_options_' . $slug)) {
            abort(403);
        }

        $settings = DB::table('cms_settings')->pluck('value', 'key')->toArray();

        return view('falcon-cms::admin.options.generic', [
            'slug' => $slug,
            'config' => $config,
            'settings' => $settings
        ]);
    }

    public function update(Request $request, $slug)
    {
        $config = config('falcon-options.pages.' . $slug);

        if (!$config) {
            abort(404);
        }

        if (!auth()->user()->hasPermission('manage_settings') && !auth()->user()->hasPermission('manage_options_' . $slug)) {
            abort(403);
        }

        $data = $request->except('_token');
        
        // Handle Checkboxes and File Uploads
        foreach ($config['fields'] as $key => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$key] = $request->has($key) ? '1' : '0';
            } elseif ($field['type'] === 'image' && $request->hasFile($key)) {
                $file = $request->file($key);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
                $allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
                $ext  = strtolower($file->getClientOriginalExtension());
                $mime = $file->getMimeType();

                if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) {
                    return redirect()->back()->withErrors([$key => 'Only image files (JPG, PNG, GIF, WebP, AVIF) are allowed.']);
                }

                $filename = Str::slug($key) . '-' . time() . '.' . $ext;
                $file->move(public_path('uploads/settings'), $filename);
                $data[$key] = 'uploads/settings/' . $filename;
            }
        }

        foreach ($data as $key => $value) {
            // If it's an image field and no new file was uploaded, don't overwrite with empty
            if ($config['fields'][$key]['type'] === 'image' && !$request->hasFile($key)) {
                continue;
            }

            DB::table('cms_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        forget_cms_options_cache();
        return redirect()->back()->with('success', "{$config['title']} updated successfully!");
    }
}
