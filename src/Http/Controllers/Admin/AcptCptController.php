<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Support\MaterialIcons;
use FalconCms\Core\Support\MenuPlacement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AcptCptController extends Controller
{
    /** Shown for a post type that never picked an icon. */
    private const DEFAULT_ICON = 'description';

    public function index(Request $request)
    {
        // Auto-fix missing columns if migration failed
        try {
            DB::statement('ALTER TABLE post_types MODIFY COLUMN icon TEXT NULL');
        } catch (\Exception $e) {
        }

        if (!Schema::hasColumn('post_types', 'singular_name')) {
            Schema::table('post_types', function ($table) {
                $table->string('singular_name')->nullable()->after('slug');
            });
        }
        if (!Schema::hasColumn('post_types', 'show_in_menu')) {
            Schema::table('post_types', function ($table) {
                $table->boolean('show_in_menu')->default(true)->after('is_active');
            });
        }
        if (!Schema::hasColumn('post_types', 'is_public')) {
            Schema::table('post_types', function ($table) {
                if (Schema::hasColumn('post_types', 'public')) {
                    $table->renameColumn('public', 'is_public');
                } else {
                    $table->boolean('is_public')->default(true)->after('show_in_menu');
                }
            });
        }

        $query = PostType::where('is_builtin', false);

        if ($request->filled('s')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->s.'%')
                    ->orWhere('slug', 'like', '%'.$request->s.'%');
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
        foreach (PostType::where('is_builtin', false)->where('is_active', 1)->get() as $pt) {
            $this->syncCptMenus($pt);
        }

        $postTypes = $query->latest()->get();

        $allCount = PostType::where('is_builtin', false)->withoutTrashed()->count();
        $activeCount = PostType::where('is_builtin', false)->withoutTrashed()->where('is_active', 1)->count();
        $inactiveCount = PostType::where('is_builtin', false)->withoutTrashed()->where('is_active', 0)->count();
        $trashCount = PostType::where('is_builtin', false)->onlyTrashed()->count();

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
            $postTypes = PostType::whereIn('id', $ids)->get();
            foreach ($postTypes as $postType) {
                $this->removeCptMenus($postType);
                $postType->delete();
            }

            return redirect()->back()->with('success', 'Selected Post Types moved to trash.');
        }

        if ($action === 'restore') {
            $postTypes = PostType::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($postTypes as $postType) {
                $postType->restore();
                if ($postType->is_active) {
                    $this->syncCptMenus($postType);
                }
            }

            return redirect()->back()->with('success', 'Selected Post Types restored.');
        }

        if ($action === 'delete') {
            $postTypes = PostType::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($postTypes as $postType) {
                $this->removeCptMenus($postType);
                $postType->forceDelete();
            }

            return redirect()->back()->with('success', 'Selected Post Types permanently deleted.');
        }

        if ($action === 'deactivate') {
            foreach ($ids as $id) {
                $postType = PostType::find($id);
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
                $postType = PostType::find($id);
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

    /**
     * Create or refresh a custom post type's sidebar rows — the parent item plus its
     * "All …" and "Add New" children.
     *
     * Every path that touches these rows comes through here: creating, importing, editing,
     * duplicating, re-activating, and the repair pass on the index page. Six near-copies, two
     * of which computed `order` as `40 + id` while the rest used `60 + id`, is precisely what
     * kept wedging a post type between Shop and Products. Position is now MenuPlacement's job
     * and is decided by the neighbour the user picked, never by an id.
     *
     * @param  string|null  $oldTitle  the menu's title before a rename, so an edit moves the row
     *                                 that already exists instead of orphaning it
     * @param  bool  $place  re-apply the post type's chosen position. Off by default: the repair
     *                       pass must not shuffle a sidebar the user has already arranged.
     */
    protected function syncCptMenus($postType, ?string $oldTitle = null, bool $place = false)
    {
        $listRoute = '/admin/posts?type='.$postType->slug;
        $createRoute = '/admin/posts/create?type='.$postType->slug;
        $icon = $postType->icon ?: self::DEFAULT_ICON;

        $parentMenu = $this->findCptMenu($postType, $oldTitle);

        if (!$parentMenu) {
            // Born at the bottom of the group, so it can never appear inside a locked block
            // even for the instant before it is placed.
            $parentMenu = Menu::create([
                'title' => $postType->name,
                'route' => $listRoute,
                'icon' => $icon,
                'group' => 'Main',
                'order' => MenuPlacement::nextOrder('Main'),
            ]);
            $place = true;
        } else {
            $parentMenu->update(['title' => $postType->name, 'route' => $listRoute, 'icon' => $icon]);
        }

        // Matched on shape rather than title: "All Courses" becomes "All Programmes" on a
        // rename, so looking the child up by its current title would create a duplicate.
        $allChild = Menu::where('parent_id', $parentMenu->id)->where('title', 'like', 'All %')->first();
        $allChild
            ? $allChild->update(['title' => 'All '.$postType->name, 'route' => $listRoute])
            : Menu::create(['parent_id' => $parentMenu->id, 'title' => 'All '.$postType->name, 'route' => $listRoute, 'order' => 1]);

        $addChild = Menu::where('parent_id', $parentMenu->id)->where('title', 'Add New')->first();
        $addChild
            ? $addChild->update(['route' => $createRoute])
            : Menu::create(['parent_id' => $parentMenu->id, 'title' => 'Add New', 'route' => $createRoute, 'order' => 2]);

        if ($place) {
            MenuPlacement::placeAfter($parentMenu, $postType->menu_after);
        }

        return $parentMenu;
    }

    /**
     * A post type's existing top-level menu row.
     *
     * Route first, because it is the one thing that stays put through a rename; the title is
     * only a fallback for rows created before the route was written that way.
     */
    protected function findCptMenu($postType, ?string $oldTitle = null)
    {
        return Menu::whereNull('parent_id')->where('route', '/admin/posts?type='.$postType->slug)->first()
            ?: Menu::whereNull('parent_id')->where('title', $oldTitle ?: $postType->name)->first();
    }

    protected function removeCptMenus($postType, ?string $oldTitle = null)
    {
        $parentMenu = $this->findCptMenu($postType, $oldTitle);
        if ($parentMenu) {
            Menu::where('parent_id', $parentMenu->id)->delete();
            $parentMenu->delete();
        }
    }

    /** The sidebar menus a post type can be positioned after, plus the icon set, for the form. */
    protected function formOptions($postType = null): array
    {
        return [
            'menuAnchors' => MenuPlacement::anchorOptions(
                $postType ? optional($this->findCptMenu($postType))->id : null
            ),
            'iconNames' => MaterialIcons::ordered(),
        ];
    }

    public function create()
    {
        return view('falcon-cms::admin.acpt.cpt.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        // Auto-fix missing columns
        try {
            DB::statement('ALTER TABLE post_types MODIFY COLUMN icon TEXT NULL');
        } catch (\Exception $e) {
        }

        if (!Schema::hasColumn('post_types', 'singular_name')) {
            Schema::table('post_types', function ($table) {
                $table->string('singular_name')->nullable()->after('slug');
            });
        }
        if (!Schema::hasColumn('post_types', 'is_public')) {
            Schema::table('post_types', function ($table) {
                if (Schema::hasColumn('post_types', 'public')) {
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
            'supports' => 'nullable|array',
            'menu_after' => 'nullable|integer|exists:menus,id',
        ]);

        $supports = $request->input('supports', ['title']); // fallback to title if empty

        $postType = PostType::create([
            'name' => $request->plural_label,
            'singular_name' => $request->singular_label,
            'slug' => $request->post_type_key,
            'supports' => $supports,
            'icon' => $request->input('icon'),
            'is_builtin' => false,
            'is_active' => true,
            'show_in_menu' => $request->has('show_in_menu'),
            'menu_after' => $request->input('menu_after') ?: null,
            'is_public' => (bool) $request->input('is_public', 1),
        ]);

        if ($postType->is_active) {
            $this->syncCptMenus($postType, null, true);
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type created successfully!');
    }

    public function exportCpt($id)
    {
        $pt = PostType::findOrFail($id);

        return falcon_export_response('falcon_cpt', [
            'name' => $pt->name,
            'singular_name' => $pt->singular_name,
            'slug' => $pt->slug,
            'description' => $pt->description,
            'icon' => $pt->icon,
            'supports' => $pt->supports,
            'show_in_menu' => (bool) $pt->show_in_menu,
            'is_public' => (bool) $pt->is_public,
        ], ($pt->slug ?: 'cpt').'-cpt');
    }

    public function importCpt(Request $request)
    {
        $request->validate(['import_file' => ['required', 'file', 'max:5120']]);
        $d = falcon_read_import($request, 'import_file', 'falcon_cpt');
        if ($d === null) {
            return back()->with('error', 'That is not a valid Custom Post Type export file.');
        }

        $slug = Str::slug($d['slug'] ?? 'cpt') ?: 'cpt';
        $attrs = [
            'name' => $d['name'] ?? 'Imported Type',
            'singular_name' => $d['singular_name'] ?? ($d['name'] ?? 'Item'),
            'description' => $d['description'] ?? null,
            'icon' => $d['icon'] ?? null,
            'supports' => is_array($d['supports'] ?? null) ? $d['supports'] : ['title'],
            'is_active' => true,
            'show_in_menu' => (bool) ($d['show_in_menu'] ?? true),
            'is_public' => (bool) ($d['is_public'] ?? true),
        ];
        // Sidebar position is not exported: it names a menu row by id, and the same id means
        // something else on the site importing it. An imported type lands at the bottom.

        // Idempotent: update an existing (non-builtin) post type with the same slug
        // rather than creating a duplicate. Built-in types are never overwritten.
        $existing = PostType::where('slug', $slug)->first();
        if ($existing && !$existing->is_builtin) {
            $existing->update($attrs);
            $pt = $existing;
            $verb = 'updated';
        } else {
            if ($existing) {
                $slug = $this->uniqueColumnValue('post_types', 'slug', $slug); // clash with a built-in
            }
            $pt = PostType::create(array_merge($attrs, ['slug' => $slug, 'is_builtin' => false]));
            if ($pt->is_active && $pt->show_in_menu) {
                $this->syncCptMenus($pt, null, true);
            }
            $verb = 'imported';
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', "Custom Post Type {$verb} successfully.");
    }

    /** Return $value or $value-1, -2… so it is unique in the given table column. */
    private function uniqueColumnValue(string $table, string $column, string $value): string
    {
        $base = $value;
        $i = 1;
        while (DB::table($table)->where($column, $value)->exists()) {
            $value = $base.'-'.$i;
            $i++;
        }

        return $value;
    }

    public function edit($id)
    {
        $postType = PostType::findOrFail($id);

        return view('falcon-cms::admin.acpt.cpt.edit', compact('postType') + $this->formOptions($postType));
    }

    public function update(Request $request, $id)
    {
        $postType = PostType::findOrFail($id);

        $request->validate([
            'plural_label' => 'required|string|max:255',
            'singular_label' => 'required|string|max:255',
            'post_type_key' => 'required|string|max:20|unique:post_types,slug,'.$postType->id,
            'supports' => 'nullable|array',
            'menu_after' => 'nullable|integer|exists:menus,id',
        ]);

        $supports = $request->input('supports', ['title']);
        $oldPlural = $postType->name;
        $oldMenuAfter = $postType->menu_after;
        $newMenuAfter = $request->input('menu_after') ?: null;

        $postType->update([
            'name' => $request->plural_label,
            'singular_name' => $request->singular_label,
            'slug' => $request->post_type_key,
            'supports' => $supports,
            'icon' => $request->input('icon'),
            'show_in_menu' => $request->has('show_in_menu'),
            'menu_after' => $newMenuAfter,
            'is_public' => $request->input('is_public') == '1' ? true : false,
        ]);

        $postType->refresh(); // Ensure we have the latest state

        // Cleanup Navigation Menu Items if show_in_menu is disabled
        if (!$postType->show_in_menu) {
            NavigationMenuItem::where('type', $postType->slug)->delete();
        }

        if ($postType->is_active) {
            // Only re-position when the choice actually changed, so saving an unrelated edit
            // leaves a sidebar the user has since rearranged exactly where it is.
            $this->syncCptMenus($postType, $oldPlural, (int) $oldMenuAfter !== (int) $newMenuAfter);
        } else {
            $this->removeCptMenus($postType, $oldPlural);
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type updated successfully!');
    }

    public function destroy($id)
    {
        $postType = PostType::findOrFail($id);
        $parentMenu = Menu::where('title', $postType->name)->whereNull('parent_id')->first();
        if ($parentMenu) {
            Menu::where('parent_id', $parentMenu->id)->delete();
            $parentMenu->delete();
        }
        $postType->delete();

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type trashed!');
    }

    public function duplicate($id)
    {
        $postType = PostType::findOrFail($id);
        $newPostType = $postType->replicate();
        $newPostType->name = $postType->name.' (Copy)';
        $newPostType->slug = $postType->slug.'_copy_'.time(); // ensure uniqueness
        $newPostType->save();

        if ($newPostType->is_active) {
            // A copy belongs next to what it was copied from, so it is placed after the
            // original rather than inheriting the original's own anchor (which would have
            // slotted the copy in above it).
            $newPostType->menu_after = optional($this->findCptMenu($postType))->id;
            $newPostType->save();
            $this->syncCptMenus($newPostType, null, true);
        }

        return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type duplicated!');
    }

    public function toggleStatus($id)
    {
        $postType = PostType::findOrFail($id);
        $postType->is_active = !$postType->is_active;
        $postType->save();

        if (!$postType->is_active) {
            $this->removeCptMenus($postType);
        } else {
            // Re-activating restores the position it had before it was switched off.
            $this->syncCptMenus($postType, null, true);
        }

        $msg = $postType->is_active ? 'Activated' : 'Deactivated';

        // To allow internal calls (e.g. from bulk) to not redirect early if we modify it to return state
        if (request()->routeIs('*.toggle-status')) {
            return redirect()->route('admin.acpt.cpt.index')->with('success', 'Custom Post Type '.$msg.' successfully!');
        }
    }
}
