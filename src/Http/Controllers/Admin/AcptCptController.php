<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class AcptCptController extends Controller
{
    public function index(Request $request)
    {
        // Auto-fix missing columns if migration failed
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE post_types MODIFY COLUMN icon TEXT NULL");
        } catch (\Exception $e) {}

        if (!\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'singular_name')) {
            \Illuminate\Support\Facades\Schema::table('post_types', function ($table) {
                $table->string('singular_name')->nullable()->after('slug');
            });
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'show_in_menu')) {
            \Illuminate\Support\Facades\Schema::table('post_types', function ($table) {
                $table->boolean('show_in_menu')->default(true)->after('is_active');
            });
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'is_public')) {
            \Illuminate\Support\Facades\Schema::table('post_types', function ($table) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'public')) {
                    $table->renameColumn('public', 'is_public');
                } else {
                    $table->boolean('is_public')->default(true)->after('show_in_menu');
                }
            });
        }

        $query = \FalconCms\Core\Models\PostType::where('is_builtin', false);
        
        if ($request->filled('s')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->s . '%')
                  ->orWhere('slug', 'like', '%' . $request->s . '%');
            });
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $query->where('is_active', 1)->withoutTrashed();
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0)->withoutTrashed();
        } elseif ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        // Ensure all active CPTs have their menus synced (prevents 404/missing menu issues after updates)
        foreach (\FalconCms\Core\Models\PostType::where('is_builtin', false)->where('is_active', 1)->get() as $pt) {
            $this->syncCptMenus($pt);
        }

        $postTypes = $query->latest()->get();

        $allCount = \FalconCms\Core\Models\PostType::where('is_builtin', false)->withoutTrashed()->count();
        $activeCount = \FalconCms\Core\Models\PostType::where('is_builtin', false)->withoutTrashed()->where('is_active', 1)->count();
        $inactiveCount = \FalconCms\Core\Models\PostType::where('is_builtin', false)->withoutTrashed()->where('is_active', 0)->count();
        $trashCount = \FalconCms\Core\Models\PostType::where('is_builtin', false)->onlyTrashed()->count();

        return view('falcon-cms::admin.acpt.cpt.index', compact('postTypes', 'allCount', 'activeCount', 'inactiveCount', 'trashCount'));
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action');
        if (empty($action) || $action === 'none') {
            $action = $request->input('action2');
        }
        $ids = $request->input('post_types', []);

        if (empty($ids) || empty($action) || $action === 'none') {
            return redirect()->back()->with('success', 'No action or post types selected.');
        }

        if ($action === 'trash') {
            $postTypes = \FalconCms\Core\Models\PostType::whereIn('id', $ids)->get();
            foreach ($postTypes as $postType) {
                $this->removeCptMenus($postType);
                $postType->delete();
            }
            return redirect()->back()->with('success', 'Selected Post Types moved to trash.');
        }

        if ($action === 'restore') {
            $postTypes = \FalconCms\Core\Models\PostType::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($postTypes as $postType) {
                $postType->restore();
                if ($postType->is_active) {
                    $this->syncCptMenus($postType);
                }
            }
            return redirect()->back()->with('success', 'Selected Post Types restored.');
        }

        if ($action === 'delete') {
            $postTypes = \FalconCms\Core\Models\PostType::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($postTypes as $postType) {
                $this->removeCptMenus($postType);
                $postType->forceDelete();
            }
            return redirect()->back()->with('success', 'Selected Post Types permanently deleted.');
        }

        if ($action === 'deactivate') {
            foreach ($ids as $id) {
                $postType = \FalconCms\Core\Models\PostType::find($id);
                if ($postType && $postType->is_active) {
                    $postType->is_active = 0;
                    $postType->save();
                    $this->removeCptMenus($postType);
                }
            }
            return redirect()->back()->with('success', 'Selected Post Types deactivated.');
        }

        if ($action === 'activate') {
            foreach ($ids as $id) {
                $postType = \FalconCms\Core\Models\PostType::find($id);
                if ($postType && !$postType->is_active) {
                    $postType->is_active = 1;
                    $postType->save();
                    $this->syncCptMenus($postType);
                }
            }
            return redirect()->back()->with('success', 'Selected Post Types activated.');
        }

        return redirect()->back();
    }

    protected function syncCptMenus($postType)
    {
        $order = 50 + $postType->id;
        $defaultIcon = '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';

        $parentMenu = \FalconCms\Core\Models\Menu::firstOrCreate(
            ['title' => $postType->name, 'parent_id' => null],
            [
                'route' => '/admin/posts?type=' . $postType->slug,
                'icon' => $postType->icon ?: $defaultIcon,
                'group' => 'Main',
                'order' => $order,
            ]
        );

        \FalconCms\Core\Models\Menu::firstOrCreate(
            ['parent_id' => $parentMenu->id, 'title' => 'All ' . $postType->name],
            ['route' => '/admin/posts?type=' . $postType->slug, 'order' => 1]
        );

        \FalconCms\Core\Models\Menu::firstOrCreate(
            ['parent_id' => $parentMenu->id, 'title' => 'Add New'],
            ['route' => '/admin/posts/create?type=' . $postType->slug, 'order' => 2]
        );
    }

    protected function removeCptMenus($postType)
    {
        $parentMenu = \FalconCms\Core\Models\Menu::where('title', $postType->name)->whereNull('parent_id')->first();
        if ($parentMenu) {
            \FalconCms\Core\Models\Menu::where('parent_id', $parentMenu->id)->delete();
            $parentMenu->delete();
        }
    }

    public function create()
    {
        return view('falcon-cms::admin.acpt.cpt.create');
    }

    public function store(Request $request)
    {
        // Auto-fix missing columns
        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE post_types MODIFY COLUMN icon TEXT NULL");
        } catch (\Exception $e) {}

        if (!\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'singular_name')) {
            \Illuminate\Support\Facades\Schema::table('post_types', function ($table) {
                $table->string('singular_name')->nullable()->after('slug');
            });
        }
        if (!\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'is_public')) {
            \Illuminate\Support\Facades\Schema::table('post_types', function ($table) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'public')) {
                    $table->renameColumn('public', 'is_public');
                } else {
                    $table->boolean('is_public')->default(true)->after('show_in_menu');
                }
            });
        }

        $request->validate([
            'plural_label' => 'required|string|max:255',
            'singular_label' => 'required|string|max:255',
            'post_type_key' => 'required|string|max:20|unique:post_types,slug',
            'supports' => 'nullable|array'
        ]);

        $supports = $request->input('supports', ['title']); // fallback to title if empty

        $postType = \FalconCms\Core\Models\PostType::create([
            'name' => $request->plural_label,
            'singular_name' => $request->singular_label,
            'slug' => $request->post_type_key,
            'supports' => $supports,
            'icon' => $request->input('icon'),
            'is_builtin' => false,
            'is_active' => true,
            'show_in_menu' => $request->has('show_in_menu'),
            'is_public' => (bool)$request->input('is_public', 1),
        ]);

        if ($postType->is_active) {
            // Place between Comments and Menu
            $order = 50 + $postType->id;
            $defaultIcon = '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';

            $parentMenu = \FalconCms\Core\Models\Menu::create([
                 'title' => $request->plural_label,
                 'route' => '/admin/posts?type=' . $request->post_type_key,
                 'icon' => $postType->icon ?: $defaultIcon,
                 'group' => 'Main',
                 'order' => $order,
            ]);

            \FalconCms\Core\Models\Menu::create([
                'parent_id' => $parentMenu->id,
                'title' => 'All ' . $request->plural_label,
                'route' => '/admin/posts?type=' . $request->post_type_key,
                'order' => 1,
            ]);

            \FalconCms\Core\Models\Menu::create([
                'parent_id' => $parentMenu->id,
                'title' => 'Add New',
                'route' => '/admin/posts/create?type=' . $request->post_type_key,
                'order' => 2,
            ]);
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type created successfully!');
    }

    public function exportCpt($id)
    {
        $pt = \FalconCms\Core\Models\PostType::findOrFail($id);
        return falcon_export_response('falcon_cpt', [
            'name'          => $pt->name,
            'singular_name' => $pt->singular_name,
            'slug'          => $pt->slug,
            'description'   => $pt->description,
            'icon'          => $pt->icon,
            'supports'      => $pt->supports,
            'show_in_menu'  => (bool) $pt->show_in_menu,
            'is_public'     => (bool) $pt->is_public,
        ], ($pt->slug ?: 'cpt') . '-cpt');
    }

    public function importCpt(Request $request)
    {
        $request->validate(['import_file' => ['required', 'file', 'max:5120']]);
        $d = falcon_read_import($request, 'import_file', 'falcon_cpt');
        if ($d === null) {
            return back()->with('error', 'That is not a valid Custom Post Type export file.');
        }

        $slug = $this->uniqueColumnValue('post_types', 'slug', \Illuminate\Support\Str::slug($d['slug'] ?? 'cpt') ?: 'cpt');

        $pt = \FalconCms\Core\Models\PostType::create([
            'name'          => $d['name'] ?? 'Imported Type',
            'singular_name' => $d['singular_name'] ?? ($d['name'] ?? 'Item'),
            'slug'          => $slug,
            'description'   => $d['description'] ?? null,
            'icon'          => $d['icon'] ?? null,
            'supports'      => is_array($d['supports'] ?? null) ? $d['supports'] : ['title'],
            'is_builtin'    => false,
            'is_active'     => true,
            'show_in_menu'  => (bool) ($d['show_in_menu'] ?? true),
            'is_public'     => (bool) ($d['is_public'] ?? true),
        ]);

        if ($pt->is_active && $pt->show_in_menu) {
            $this->buildCptMenu($pt);
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type imported successfully.');
    }

    /** Create the admin sidebar menu (parent + All/Add New) for a CPT — mirrors store(). */
    private function buildCptMenu(\FalconCms\Core\Models\PostType $pt): void
    {
        $defaultIcon = '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';
        $parentMenu = \FalconCms\Core\Models\Menu::create([
            'title' => $pt->name,
            'route' => '/admin/posts?type=' . $pt->slug,
            'icon'  => $pt->icon ?: $defaultIcon,
            'group' => 'Main',
            'order' => 50 + $pt->id,
        ]);
        \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'All ' . $pt->name, 'route' => '/admin/posts?type=' . $pt->slug, 'order' => 1]);
        \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'Add New', 'route' => '/admin/posts/create?type=' . $pt->slug, 'order' => 2]);
    }

    /** Return $value or $value-1, -2… so it is unique in the given table column. */
    private function uniqueColumnValue(string $table, string $column, string $value): string
    {
        $base = $value; $i = 1;
        while (\Illuminate\Support\Facades\DB::table($table)->where($column, $value)->exists()) {
            $value = $base . '-' . $i; $i++;
        }
        return $value;
    }

    public function edit($id)
    {
        $postType = \FalconCms\Core\Models\PostType::findOrFail($id);
        return view('falcon-cms::admin.acpt.cpt.edit', compact('postType'));
    }

    public function update(Request $request, $id)
    {
        $postType = \FalconCms\Core\Models\PostType::findOrFail($id);

        $request->validate([
            'plural_label' => 'required|string|max:255',
            'singular_label' => 'required|string|max:255',
            'post_type_key' => 'required|string|max:20|unique:post_types,slug,'.$postType->id,
            'supports' => 'nullable|array'
        ]);

        $supports = $request->input('supports', ['title']);
        $oldSlug = $postType->slug;
        $oldPlural = $postType->name;

        $postType->update([
            'name' => $request->plural_label,
            'singular_name' => $request->singular_label,
            'slug' => $request->post_type_key,
            'supports' => $supports,
            'icon' => $request->input('icon'),
            'show_in_menu' => $request->has('show_in_menu'),
            'is_public' => $request->input('is_public') == '1' ? true : false,
        ]);
        
        $postType->refresh(); // Ensure we have the latest state

        // Cleanup Navigation Menu Items if show_in_menu is disabled
        if (!$postType->show_in_menu) {
            \FalconCms\Core\Models\NavigationMenuItem::where('type', $postType->slug)->delete();
        }

        if ($postType->is_active) {
            $parentMenu = \FalconCms\Core\Models\Menu::where('title', $oldPlural)->whereNull('parent_id')->first();
            if (!$parentMenu) {
                 // Create if didn't exist
                 $order = 40 + $postType->id;
                 $defaultIcon = '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';
                 $parentMenu = \FalconCms\Core\Models\Menu::create([
                      'title' => $request->plural_label,
                      'route' => '/admin/posts?type=' . $request->post_type_key,
                      'icon' => $postType->icon ?: $defaultIcon,
                      'group' => 'Main',
                      'order' => $order,
                 ]);
                 \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'All ' . $request->plural_label, 'route' => '/admin/posts?type=' . $request->post_type_key, 'order' => 1]);
                 \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'Add New', 'route' => '/admin/posts/create?type=' . $request->post_type_key, 'order' => 2]);
            } else {
                // Update existing
                $defaultIcon = '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';
                $parentMenu->update([
                    'title' => $request->plural_label,
                    'route' => '/admin/posts?type=' . $request->post_type_key,
                    'icon' => $postType->icon ?: $defaultIcon
                ]);
                $allMenu = \FalconCms\Core\Models\Menu::where('parent_id', $parentMenu->id)->where('title', 'like', 'All %')->first();
                if ($allMenu) $allMenu->update(['title' => 'All ' . $request->plural_label, 'route' => '/admin/posts?type=' . $request->post_type_key]);
                $addNewMenu = \FalconCms\Core\Models\Menu::where('parent_id', $parentMenu->id)->where('title', 'Add New')->first();
                if ($addNewMenu) $addNewMenu->update(['route' => '/admin/posts/create?type=' . $request->post_type_key]);
            }
        } else {
            // Remove menu if is_active is false
            $parentMenu = \FalconCms\Core\Models\Menu::where('title', $oldPlural)->whereNull('parent_id')->first();
            if ($parentMenu) {
                \FalconCms\Core\Models\Menu::where('parent_id', $parentMenu->id)->delete();
                $parentMenu->delete();
            }
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type updated successfully!');
    }

    public function destroy($id)
    {
        $postType = \FalconCms\Core\Models\PostType::findOrFail($id);
        $parentMenu = \FalconCms\Core\Models\Menu::where('title', $postType->name)->whereNull('parent_id')->first();
        if ($parentMenu) {
            \FalconCms\Core\Models\Menu::where('parent_id', $parentMenu->id)->delete();
            $parentMenu->delete();
        }
        $postType->delete();
        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type trashed!');
    }

    public function duplicate($id)
    {
        $postType = \FalconCms\Core\Models\PostType::findOrFail($id);
        $newPostType = $postType->replicate();
        $newPostType->name = $postType->name . ' (Copy)';
        $newPostType->slug = $postType->slug . '_copy_' . time(); // ensure uniqueness
        $newPostType->save();

        if ($newPostType->is_active) {
            $order = 40 + $newPostType->id;
            $parentMenu = \FalconCms\Core\Models\Menu::create([
                 'title' => $newPostType->name,
                 'route' => '/admin/posts?type=' . $newPostType->slug,
                 'icon' => '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>',
                 'group' => 'Main',
                 'order' => $order,
            ]);
            \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'All ' . $newPostType->name, 'route' => '/admin/posts?type=' . $newPostType->slug, 'order' => 1]);
            \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'Add New', 'route' => '/admin/posts/create?type=' . $newPostType->slug, 'order' => 2]);
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type duplicated!');
    }

    public function toggleStatus($id)
    {
        $postType = \FalconCms\Core\Models\PostType::findOrFail($id);
        $postType->is_active = !$postType->is_active;
        $postType->save();
        
        if (!$postType->is_active) {
            $parentMenu = \FalconCms\Core\Models\Menu::where('title', $postType->name)->whereNull('parent_id')->first();
            if ($parentMenu) {
                \FalconCms\Core\Models\Menu::where('parent_id', $parentMenu->id)->delete();
                $parentMenu->delete();
            }
        } else {
            $order = 40 + $postType->id;
            // Ensure no duplicate parent config already
            if (\FalconCms\Core\Models\Menu::where('title', $postType->name)->whereNull('parent_id')->doesntExist()) {
                $parentMenu = \FalconCms\Core\Models\Menu::create([
                        'title' => $postType->name,
                        'route' => '/admin/posts?type=' . $postType->slug,
                        'icon' => '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>',
                        'group' => 'Main',
                        'order' => $order,
                ]);
                \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'All ' . $postType->name, 'route' => '/admin/posts?type=' . $postType->slug, 'order' => 1]);
                \FalconCms\Core\Models\Menu::create(['parent_id' => $parentMenu->id, 'title' => 'Add New', 'route' => '/admin/posts/create?type=' . $postType->slug, 'order' => 2]);
            }
        }
        
        $msg = $postType->is_active ? 'Activated' : 'Deactivated';
        
        // To allow internal calls (e.g. from bulk) to not redirect early if we modify it to return state
        if (request()->routeIs('*.toggle-status')) {
            return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type ' . $msg . ' successfully!');
        }
    }
}
