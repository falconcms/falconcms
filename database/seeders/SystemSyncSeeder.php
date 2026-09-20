<?php

namespace FalconCms\Core\Database\Seeders;

use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\View\Components\Admin\Sidebar;
use Illuminate\Database\Seeder;

class SystemSyncSeeder extends Seeder
{
    public function run()
    {
        // 1. Sync Base Permissions
        $permissions = [
            ['name' => 'Access Dashboard', 'slug' => 'access_dashboard'],
            ['name' => 'Manage Content',   'slug' => 'manage_content'],
            ['name' => 'Manage Users',     'slug' => 'manage_users'],
            ['name' => 'Manage Roles',     'slug' => 'manage_roles'],
            ['name' => 'Manage Settings',  'slug' => 'manage_settings'],
            ['name' => 'Manage Media',     'slug' => 'manage_media'],
            ['name' => 'Manage Posts',     'slug' => 'manage_posts'],
            ['name' => 'Manage Pages',     'slug' => 'manage_pages'],
            ['name' => 'Manage Categories', 'slug' => 'manage_categories'],
            ['name' => 'Manage Tags',       'slug' => 'manage_tags'],
            ['name' => 'View Analytics',    'slug' => 'manage_analytics'],
            ['name' => 'Manage Comments',   'slug' => 'manage_comments'],
            ['name' => 'Manage Forms',      'slug' => 'manage_forms'],
            ['name' => 'Manage Appearance', 'slug' => 'manage_appearance'],
            ['name' => 'Manage ACPT',       'slug' => 'manage_acpt'],
            ['name' => 'Manage Tools',      'slug' => 'manage_tools'],
            ['name' => 'Manage SEO',        'slug' => 'manage_seo'],
            ['name' => 'Manage Plugins',    'slug' => 'manage_plugins'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['slug' => $p['slug']], $p);
        }

        // 2. Sync Roles
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Unrestricted access to all system features.'],
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full access to all settings and content.'],
            ['name' => 'Editor', 'slug' => 'editor', 'description' => 'Can publish and manage posts.'],
            ['name' => 'Author', 'slug' => 'author', 'description' => 'Can publish and manage their own posts.'],
            ['name' => 'Contributor', 'slug' => 'contributor', 'description' => 'Can write and manage their own posts but cannot publish them.'],
            ['name' => 'Subscriber', 'slug' => 'subscriber', 'description' => 'Can only manage their profile.'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user with content management access.'],
            ['name' => 'Customer', 'slug' => 'customer', 'description' => 'Customer who registered via store checkout or account.'],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(['slug' => $roleData['slug']], $roleData);
        }

        // 3. Sync Default Menus
        $this->call(MenuSeeder::class);

        // 4. IMPORTANT: Generate and Create Permissions for ALL Menus & Children
        $menus = Menu::with('children')->get();
        foreach ($menus as $menu) {
            $slug = $menu->permission ?: $this->generatePermissionSlug($menu);
            Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['_', '-'], ' ', $slug))]
            );

            foreach ($menu->children as $child) {
                $childSlug = $child->permission ?: $this->generatePermissionSlug($child, $menu);
                Permission::firstOrCreate(
                    ['slug' => $childSlug],
                    ['name' => ucwords(str_replace(['_', '-'], ' ', $childSlug))]
                );
            }
        }

        // 4b. Permissions for menus a plugin or theme registered in code. Without a row the
        // Roles screen still lists the checkbox, but nothing holds it until someone saves —
        // creating them here means a package's menu is grantable as soon as it is installed.
        try {
            foreach (app(AdminMenu::class)->permissionTree() as $items) {
                foreach ($items as $item) {
                    foreach (array_merge([$item], $item['children']) as $entry) {
                        Permission::firstOrCreate(
                            ['slug' => $entry['slug']],
                            ['name' => ucwords(str_replace(['_', '-'], ' ', $entry['slug']))]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            // Seeding runs on boot here; a package throwing must not stop the site coming up.
        }

        // 5. Gather ALL permissions (Now fully populated)
        $allPermissionIds = Permission::pluck('id')->toArray();
        $allPermissionSlugs = Permission::pluck('slug')->toArray();

        // 6. Assign Permissions to Roles
        $roleAssignments = [
            'super-admin' => 'all',
            'administrator' => 'all',
            'editor' => ['access_dashboard', 'manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'access_categories_posts', 'access_tags_posts', 'manage_media', 'access_library_media', 'access_add_new_media', 'access_comments', 'manage_analytics'],
            'author' => ['access_dashboard', 'manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'access_categories_posts', 'access_tags_posts', 'manage_media', 'access_library_media', 'access_add_new_media', 'access_comments'],
            'contributor' => ['access_dashboard', 'manage_posts', 'manage_media', 'access_library_media', 'access_add_new_media', 'access_comments'],
            // Dashboard and its Overview, and nothing else. A subscriber was given
            // manage_users, which put a Users entry in their sidebar that led to a 403 —
            // the menu said they could manage users and every page behind it disagreed.
            'subscriber' => ['access_dashboard', 'access_overview_dashboard'],
            // Same as a subscriber, and for the same reason: a customer signs in to the
            // storefront account page, not to user management, and manage_users only ever
            // drew them a Users menu that answered 403.
            'customer' => ['access_dashboard', 'access_overview_dashboard'],
            'user' => [
                'access_dashboard',
                'manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'access_categories_posts', 'access_tags_posts',
                'manage_pages', 'access_all_pages_pages', 'access_add_new_pages',
                'manage_media', 'access_library_media', 'access_add_new_media',
                'access_comments', 'manage_analytics',
                'manage_tools', 'access_languages_tools',
            ],
        ];

        foreach ($roleAssignments as $roleSlug => $perms) {
            $role = Role::where('slug', $roleSlug)->first();
            if ($role) {
                if ($perms === 'all' || $roleSlug === 'administrator') {
                    $role->permissions()->sync($allPermissionIds);
                } else {
                    // Only sync if role has NO permissions yet
                    if ($role->permissions()->count() === 0) {
                        $ids = Permission::whereIn('slug', $perms)->pluck('id')->toArray();
                        $role->permissions()->sync($ids);
                    }
                }
            }
        }
    }

    protected function generatePermissionSlug($menu, $parent = null)
    {
        // The same resolver the sidebar and the Roles screen use, rather than a second copy of
        // the rules. The copy that used to live here namespaced only a handful of child titles
        // by their parent, so it created access_overview while the sidebar asked for
        // access_overview_dashboard — a permission you could grant that nothing ever checked.
        return (new Sidebar)->getPermission($menu);
    }
}
