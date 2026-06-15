<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Child menu permission slugs are now namespaced by their parent (e.g. Shop → Orders
 * became access_orders → access_orders_shop) so that two items sharing a title under
 * different parents no longer collide. Roles assigned before this change still hold the
 * old (un-namespaced) slug, which would silently lose them access.
 *
 * This migration grants the NEW namespaced slug to every role that holds the matching
 * OLD slug, preserving access. It only touches `access_*` child slugs — the canonical
 * top-level `manage_*` slugs (e.g. manage_settings) are intentionally left alone, since
 * a child like "Shop → Settings" used to collapse onto manage_settings and can't be told
 * apart from genuine top-level Settings; those few items are re-checked in the Roles editor.
 *
 * Idempotent: re-running only adds missing grants.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menus') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            return;
        }

        $sidebar = new \FalconCms\Core\View\Components\Admin\Sidebar();

        foreach (\FalconCms\Core\Models\Menu::all() as $menu) {
            if ($menu->children()->count() > 0) continue; // leaf menus only

            $new = $sidebar->getPermission($menu);
            $old = $this->legacySlug($menu);

            // Only reconcile renamed access_* child slugs (skip manage_* canonical slugs).
            if (!$old || $old === $new || !Str::startsWith($old, 'access_')) continue;

            $oldId = DB::table('permissions')->where('slug', $old)->value('id');
            if (!$oldId) continue; // nobody ever held the old slug

            $newId = DB::table('permissions')->where('slug', $new)->value('id');
            if (!$newId) {
                $newId = DB::table('permissions')->insertGetId([
                    'slug'       => $new,
                    'name'       => Str::headline(str_replace('_', ' ', $new)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roleIds = DB::table('role_permission')->where('permission_id', $oldId)->pluck('role_id');
            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_permission')
                    ->where('role_id', $roleId)->where('permission_id', $newId)->exists();
                if (!$exists) {
                    DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $newId]);
                }
            }
        }
    }

    /** The pre-namespacing permission slug for a menu (old derivation). */
    private function legacySlug($menu): ?string
    {
        $title = strtolower($menu->title ?? '');
        $canonical = [
            'dashboard' => 'access_dashboard', 'posts' => 'manage_posts', 'pages' => 'manage_pages',
            'media' => 'manage_media', 'users' => 'manage_users', 'settings' => 'manage_settings',
            'roles' => 'manage_roles', 'analytics' => 'manage_analytics',
        ];
        if (isset($canonical[$title])) return $canonical[$title];

        $slug = Str::slug($menu->title ?? '', '_');
        if ($menu->parent_id && in_array($title, ['add new', 'categories', 'tags', 'all posts', 'all pages'])) {
            $parent = \FalconCms\Core\Models\Menu::find($menu->parent_id);
            if ($parent) $slug .= '_' . Str::slug($parent->title, '_');
        }
        return 'access_' . $slug;
    }

    public function down(): void
    {
        // One-way reconciliation; not reversible.
    }
};
