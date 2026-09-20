<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give roles the permission their menu is actually checked against.
 *
 * Two different rules used to derive a menu's permission slug. The seeder namespaced only a
 * handful of child titles by their parent, so it wrote `access_library`; the sidebar namespaces
 * every child, so it asks for `access_library_media`. A role granted the first was checked
 * against the second and came up empty — Media → Library simply never appeared for an editor,
 * and nothing said why. The seeder now uses the sidebar's resolver; this carries the grants
 * that were made under the old spelling across to the new one.
 *
 * It only ever ADDS. The old grant stays exactly where it is, so anything still looking for it
 * keeps finding it, and a role can only end up seeing more of what it was already meant to see.
 *
 * Two kinds of pair are deliberately skipped:
 *
 *  - An old slug that more than one menu produced. The old rule returned `manage_settings` for
 *    a child titled "Settings" as readily as for the top-level one, so Shop → Settings and
 *    Settings were indistinguishable. Carrying that would hand the Shop's settings to every
 *    role holding the site's, which is a grant nobody made.
 *  - An old slug that is really a canonical permission in its own right (`manage_posts` and
 *    friends), for the same reason.
 *
 * Both rules are written out here rather than called, because a migration should keep doing
 * what it did on the day it ran even after the code it was written against moves on.
 */
return new class extends Migration
{
    /** Permissions that mean something on their own; never treated as an old spelling. */
    private const CANONICAL = [
        'access_dashboard', 'manage_posts', 'manage_pages', 'manage_media',
        'manage_users', 'manage_settings', 'manage_roles', 'manage_analytics',
    ];

    public function up(): void
    {
        foreach (['menus', 'roles', 'permissions', 'role_permission'] as $table) {
            if (!Schema::hasTable($table)) {
                return;
            }
        }

        $pairs = $this->unambiguousPairs();
        if (!$pairs) {
            return;
        }

        foreach ($pairs as $old => $new) {
            $oldId = DB::table('permissions')->where('slug', $old)->value('id');
            if (!$oldId) {
                continue;
            }

            $roleIds = DB::table('role_permission')->where('permission_id', $oldId)->pluck('role_id')->all();
            if (!$roleIds) {
                continue;
            }

            $newId = DB::table('permissions')->where('slug', $new)->value('id');
            if (!$newId) {
                $newId = DB::table('permissions')->insertGetId([
                    'slug' => $new,
                    'name' => ucwords(str_replace(['_', '-'], ' ', $new)),
                ]);
            }

            foreach ($roleIds as $roleId) {
                $already = DB::table('role_permission')
                    ->where('role_id', $roleId)->where('permission_id', $newId)->exists();
                if (!$already) {
                    DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $newId]);
                }
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the old grants were never removed, and taking the new ones away
        // would put back the very gap this closed.
    }

    /**
     * old slug => new slug, for every menu where the two rules disagree and the old slug
     * belonged to that menu alone.
     *
     * @return array<string,string>
     */
    private function unambiguousPairs(): array
    {
        $menus = DB::table('menus')->get(['id', 'title', 'parent_id', 'permission']);
        $byId = [];
        foreach ($menus as $menu) {
            $byId[$menu->id] = $menu;
        }

        $pairs = [];
        $oldCounts = [];

        foreach ($menus as $menu) {
            // A menu carrying its own permission column was never derived either way.
            if (!empty($menu->permission)) {
                continue;
            }

            $parent = $menu->parent_id ? ($byId[$menu->parent_id] ?? null) : null;
            $old = $this->oldSlug($menu, $parent);
            $new = $this->newSlug($menu, $parent);

            $oldCounts[$old] = ($oldCounts[$old] ?? 0) + 1;

            if ($old !== $new) {
                $pairs[$old] = $new;
            }
        }

        foreach (array_keys($pairs) as $old) {
            if ($oldCounts[$old] > 1 || in_array($old, self::CANONICAL, true)) {
                unset($pairs[$old]);
            }
        }

        return $pairs;
    }

    /** SystemSyncSeeder's rule as it stood before it delegated to the sidebar. */
    private function oldSlug(object $menu, ?object $parent): string
    {
        $title = strtolower((string) $menu->title);

        $canonical = [
            'dashboard' => 'access_dashboard', 'posts' => 'manage_posts', 'pages' => 'manage_pages',
            'media' => 'manage_media', 'users' => 'manage_users', 'settings' => 'manage_settings',
        ];
        // Applied whether or not the menu had a parent — which is exactly why a child titled
        // "Settings" collided with the site's own.
        if (isset($canonical[$title])) {
            return $canonical[$title];
        }

        $slug = Str::slug((string) $menu->title, '_');
        if ($parent && in_array($title, ['add new', 'categories', 'tags', 'all posts', 'all pages'], true)) {
            $slug .= '_'.Str::slug((string) $parent->title, '_');
        }

        return 'access_'.$slug;
    }

    /** Sidebar::getPermission's rule: canonical names for top-level only, children always namespaced. */
    private function newSlug(object $menu, ?object $parent): string
    {
        $title = strtolower((string) $menu->title);

        if (!$menu->parent_id) {
            $canonical = [
                'dashboard' => 'access_dashboard', 'posts' => 'manage_posts', 'pages' => 'manage_pages',
                'media' => 'manage_media', 'users' => 'manage_users', 'settings' => 'manage_settings',
                'roles' => 'manage_roles', 'analytics' => 'manage_analytics',
            ];
            if (isset($canonical[$title])) {
                return $canonical[$title];
            }
        }

        $slug = Str::slug((string) $menu->title, '_');
        if ($parent) {
            $slug .= '_'.Str::slug((string) $parent->title, '_');
        }

        return 'access_'.$slug;
    }
};
