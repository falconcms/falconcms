<?php

namespace FalconCms\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Menu;
use Illuminate\Support\Facades\DB;

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

        // 5. Gather ALL permissions (Now fully populated)
        $allPermissionIds = Permission::pluck('id')->toArray();
        $allPermissionSlugs = Permission::pluck('slug')->toArray();

        // 6. Assign Permissions to Roles
        $roleAssignments = [
            'super-admin'   => 'all', 
            'administrator' => 'all', 
            'editor'        => ['access_dashboard', 'manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'access_categories_posts', 'access_tags_posts', 'manage_media', 'access_library', 'access_add_new_media', 'access_comments', 'manage_analytics'],
            'author'        => ['access_dashboard', 'manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'access_categories_posts', 'access_tags_posts', 'manage_media', 'access_library', 'access_add_new_media', 'access_comments'],
            'contributor'   => ['access_dashboard', 'manage_posts', 'manage_media', 'access_library', 'access_add_new_media', 'access_comments'],
            'subscriber'    => ['access_dashboard', 'manage_users', 'access_your_profile'],
            'customer'      => ['access_dashboard', 'manage_users', 'access_your_profile'],
            'user'          => [
                'access_dashboard', 
                'manage_posts', 'access_all_posts_posts', 'access_add_new_posts', 'access_categories_posts', 'access_tags_posts',
                'manage_pages', 'access_all_pages_pages', 'access_add_new_pages',
                'manage_media', 'access_library', 'access_add_new_media',
                'access_comments', 'manage_analytics',
                'manage_tools', 'access_languages_tools'
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
        if ($menu->permission) return $menu->permission;
        
        $title = strtolower($menu->title);
        if ($title === 'dashboard') return 'access_dashboard';
        if ($title === 'posts') return 'manage_posts';
        if ($title === 'pages') return 'manage_pages';
        if ($title === 'media') return 'manage_media';
        if ($title === 'users') return 'manage_users';
        if ($title === 'settings') return 'manage_settings';
        
        $slug = \Illuminate\Support\Str::slug($menu->title, '_');
        
        if ($parent && in_array($title, ['add new', 'categories', 'tags', 'all posts', 'all pages'])) {
            $slug .= '_' . \Illuminate\Support\Str::slug($parent->title, '_');
        }
        
        return 'access_' . $slug;
    }
}
