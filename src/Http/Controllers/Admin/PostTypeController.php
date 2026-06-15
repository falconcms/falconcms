<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\PostType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostTypeController extends Controller
{
    public function index()
    {
        $postTypes = PostType::latest()->get();
        return view('falcon-cms::admin.post-types.index', compact('postTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:post_types,slug',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $postType = PostType::create($validated);

        $menu = \FalconCms\Core\Models\Menu::create([
            'title' => $postType->name,
            'route' => '/admin/posts?type=' . $postType->slug,
            'icon'  => '<svg class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>',
            'group' => 'Content Management',
            'order' => 30,
        ]);

        $menu->children()->createMany([
            ['title' => 'All ' . $postType->name, 'route' => '/admin/posts?type=' . $postType->slug, 'order' => 1],
            ['title' => 'Add New', 'route' => '/admin/posts/create?type=' . $postType->slug, 'order' => 2],
        ]);

        return redirect()->route('admin.post-types.index')->with('success', 'Custom Post Type registered.');
    }

    public function destroy(PostType $postType)
    {
        if ($postType->is_builtin) {
            return back()->with('error', 'Cannot delete built-in post types.');
        }
        $postType->delete();
        \FalconCms\Core\Models\Menu::where('title', $postType->name)->delete();

        return redirect()->route('admin.post-types.index')->with('success', 'Custom Post Type deleted.');
    }
}
