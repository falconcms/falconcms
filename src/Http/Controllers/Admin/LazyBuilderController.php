<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LazyBuilderController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $header = Post::where('type', 'lazy_header')->first();
        $footer = Post::where('type', 'lazy_footer')->first();

        return view('falcon-cms::admin.lazy-builder.sections', compact('header', 'footer'));
    }

    public function editHeader()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $header = Post::where('type', 'lazy_header')->first();
        if (!$header) {
            $header = Post::create([
                'title' => 'Global Header',
                'slug' => 'global-header',
                'type' => 'lazy_header',
                'status' => 'published',
                'user_id' => auth()->id(),
                'editor_type' => 'builder',
                'lang_code' => app()->getLocale()
            ]);
        }
        return redirect()->route('admin.lazy-builder', $header->id);
    }

    public function editFooter()
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $footer = Post::where('type', 'lazy_footer')->first();
        if (!$footer) {
            $footer = Post::create([
                'title' => 'Global Footer',
                'slug' => 'global-footer',
                'type' => 'lazy_footer',
                'status' => 'published',
                'user_id' => auth()->id(),
                'editor_type' => 'builder',
                'lang_code' => app()->getLocale()
            ]);
        }
        return redirect()->route('admin.lazy-builder', $footer->id);
    }

    public function toggleStatus($id)
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }

        $post = Post::findOrFail($id);
        $newStatus = ($post->status === 'published') ? 'draft' : 'published';
        $post->update(['status' => $newStatus]);

        $label = ($post->type === 'lazy_header') ? 'Header' : 'Footer';
        $msg = ($newStatus === 'published') ? "{$label} activated successfully." : "{$label} deactivated successfully.";

        return back()->with('success', $msg);
    }
}
