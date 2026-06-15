<?php

namespace FalconCms\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\CustomTaxonomy;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::truncate();

        // 1. Dashboard
        $dashMenu = Menu::create([
            'title' => 'Dashboard',
            'route' => 'admin.dashboard.index',
            'icon'  => 'dashboard',
            'group' => 'Main',
            'order' => 10,
        ]);
        $dashMenu->children()->createMany([
            ['title' => 'Overview', 'route' => 'admin.dashboard.index', 'order' => 1],
            ['title' => 'Updates',  'route' => 'admin.update',          'order' => 2],
        ]);

        // 2. Posts
        $postMenu = Menu::create([
            'title' => 'Posts',
            'route' => 'admin.posts.index',
            'icon'  => 'push_pin',
            'group' => 'Main',
            'order' => 20,
        ]);
        $postMenu->children()->createMany([
            ['title' => 'All Posts',  'route' => 'admin.posts.index',      'order' => 1],
            ['title' => 'Add New',    'route' => 'admin.posts.create',     'order' => 2],
            ['title' => 'Categories', 'route' => 'admin.categories.index', 'order' => 3],
            ['title' => 'Tags',       'route' => 'admin.tags.index',       'order' => 4],
        ]);

        // 3. Media
        $mediaMenu = Menu::create([
            'title' => 'Media',
            'route' => 'admin.media.index',
            'icon'  => 'perm_media',
            'group' => 'Main',
            'order' => 30,
        ]);
        $mediaMenu->children()->createMany([
            ['title' => 'Library',        'route' => 'admin.media.index',  'order' => 1],
            ['title' => 'Add New',        'route' => 'admin.media.create', 'order' => 2],
        ]);

        // 4. Pages
        $pageMenu = Menu::create([
            'title' => 'Pages',
            'route' => 'admin.pages.index',
            'icon'  => 'description',
            'group' => 'Main',
            'order' => 25,
        ]);
        $pageMenu->children()->createMany([
            ['title' => 'All Pages', 'route' => 'admin.pages.index', 'order' => 1],
            ['title' => 'Add New',  'route' => 'admin.pages.create', 'order' => 2],
        ]);

        // 5. Comments
        Menu::create([
            'title' => 'Comments',
            'route' => 'admin.comments.index',
            'icon'  => 'chat_bubble',
            'group' => 'Main',
            'order' => 50,
        ]);

        // 6. Forms
        $formsMenu = Menu::create([
            'title' => 'Forms',
            'route' => 'admin.forms.index',
            'icon'  => 'dynamic_form',
            'group' => 'Main',
            'order' => 35,
        ]);
        $formsMenu->children()->createMany([
            ['title' => 'All Forms',    'route' => 'admin.forms.index',           'order' => 1],
            ['title' => 'Add New',     'route' => 'admin.forms.create',          'order' => 2],
            ['title' => 'Submissions', 'route' => 'admin.forms.all-submissions', 'order' => 3],
        ]);

        // 7. Appearance
        $appearanceMenu = Menu::create([
            'title' => 'Appearance',
            'route' => 'admin.themes.index',
            'icon'  => 'palette',
            'group' => 'Main',
            'order' => 40,
        ]);
        $appearanceMenu->children()->createMany([
            ['title' => 'Themes',     'route' => 'admin.themes.index',     'order' => 1],
            ['title' => 'Customizer', 'route' => 'admin.customizer.index', 'order' => 2],
            ['title' => 'Menus',      'route' => 'admin.menus.index',      'order' => 3],
            ['title' => 'Widgets',    'route' => 'admin.widgets.index',    'order' => 4],
        ]);

        // 7b. Lazy Builder
        $lazyBuilderMenu = Menu::create([
            'title' => 'Lazy Builder',
            'route' => 'admin.falcon-builder.sections',
            'icon'  => 'view_quilt',
            'group' => 'Main',
            'order' => 42,
        ]);
        $lazyBuilderMenu->children()->createMany([
            ['title' => 'Sections',       'route' => 'admin.falcon-builder.sections',     'order' => 1],
            ['title' => 'Header Builder', 'route' => 'admin.falcon-builder.header',        'order' => 2],
            ['title' => 'Footer Builder', 'route' => 'admin.falcon-builder.footer',        'order' => 3],
            ['title' => 'Library',        'route' => 'admin.falcon-builder.library',       'order' => 4],
        ]);

        // 8. ACPT
        $acptMenu = Menu::create([
            'title' => 'ACPT',
            'route' => 'admin.acpt.cpt.index',
            'icon'  => 'settings_input_component',
            'group' => 'Advanced',
            'order' => 70,
        ]);
        $acptMenu->children()->createMany([
            ['title' => 'Post Types',   'route' => 'admin.acpt.cpt.index',        'order' => 1],
            ['title' => 'Taxonomies',   'route' => 'admin.acpt.taxonomies.index', 'order' => 2],
            ['title' => 'Field Groups', 'route' => 'admin.acpt.fields.index',      'order' => 3],
        ]);

        // 9. Users
        $userMenu = Menu::create([
            'title' => 'Users',
            'route' => 'admin.users.index',
            'icon'  => 'group',
            'group' => 'System',
            'order' => 80,
        ]);
        $userMenu->children()->createMany([
            ['title' => 'All Users',    'route' => 'admin.users.index',     'order' => 1],
            ['title' => 'Add New',     'route' => 'admin.users.create',    'order' => 2],
            ['title' => 'Roles',       'route' => 'admin.roles.index',     'order' => 3],
            ['title' => 'Blacklist',   'route' => 'admin.blacklist.index', 'order' => 4],
            ['title' => 'Your Profile', 'route' => 'admin.profile',         'order' => 5],
        ]);

        // 10. Tools
        $toolsMenu = Menu::create([
            'title' => 'Tools',
            'route' => 'admin.backup.index',
            'icon'  => 'construction',
            'group' => 'System',
            'order' => 85,
        ]);
        $toolsMenu->children()->createMany([
            ['title' => 'Backup & Restore',  'route' => 'admin.backup.index',    'order' => 1],
            ['title' => 'WordPress Import',  'route' => 'admin.wp-import.index', 'order' => 2],
            ['title' => 'Languages',         'route' => 'admin.languages.index', 'order' => 3],
        ]);

        // 11. Analytics
        Menu::create([
            'title' => 'Analytics',
            'route' => 'admin.analytics',
            'icon'  => 'insights',
            'group' => 'System',
            'order' => 87,
        ]);

        // 12. Settings
        $settingsMenu = Menu::create([
            'title' => 'Settings',
            'route' => 'admin.settings.index',
            'icon'  => 'settings',
            'group' => 'System',
            'order' => 90,
        ]);
        $settingsMenu->children()->createMany([
            ['title' => 'General',         'route' => 'admin.settings.index',           'order' => 1],
            ['title' => 'SEO',             'route' => 'admin.settings.seo',             'order' => 2],
            ['title' => 'Redirects',       'route' => 'admin.redirects.index',          'order' => 3],
            ['title' => 'Activity Logs',   'route' => 'admin.settings.activity-logs',   'order' => 4],
            ['title' => 'REST API',        'route' => 'admin.settings.api',             'order' => 5],
            ['title' => 'Integrations',    'route' => 'admin.settings.integrations',    'order' => 6],
            ['title' => 'Email Templates', 'route' => 'admin.settings.email-templates', 'order' => 7],
        ]);

        // 13. Help
        $helpMenu = Menu::create([
            'title' => 'Help',
            'route' => 'admin.documentation',
            'icon'  => 'help',
            'group' => 'System',
            'order' => 100,
        ]);
        $helpMenu->children()->createMany([
            ['title' => 'Documentation', 'route' => 'admin.documentation', 'order' => 1],
        ]);

        // 14. Products
        $productMenu = Menu::create([
            'title' => 'Products',
            'route' => 'admin.posts.index',
            'params' => json_encode(['type' => 'product']),
            'icon'  => 'inventory_2',
            'group' => 'Main',
            'order' => 55,
        ]);
        $productMenu->children()->createMany([
            ['title' => 'All Products',  'route' => 'admin.posts.index', 'params' => json_encode(['type' => 'product']), 'order' => 1],
            ['title' => 'Add New',    'route' => 'admin.posts.create', 'params' => json_encode(['type' => 'product']), 'order' => 2],
            ['title' => 'Categories', 'route' => 'admin.product-categories.index', 'params' => null, 'order' => 3],
            ['title' => 'Tags',       'route' => 'admin.product-tags.index', 'params' => null, 'order' => 4],
        ]);

        // 15. eCommerce Menu
        $ecommerceMenu = Menu::create([
            'title' => 'Shop',
            'route' => 'admin.shop.orders.index',
            'params' => null,
            'icon'  => 'storefront',
            'group' => 'Main',
            'order' => 60,
        ]);
        $ecommerceMenu->children()->createMany([
            ['title' => 'Overview',         'route' => 'admin.shop.overview',      'order' => 0],
            ['title' => 'Orders',          'route' => 'admin.shop.orders.index', 'order' => 1],
            ['title' => 'Product Reviews',  'route' => 'admin.shop.reviews.index', 'order' => 2],
            ['title' => 'Settings',         'route' => 'admin.shop.settings',      'order' => 4],
        ]);

        // Dynamic CPTs
        $customCPTs = PostType::where('is_builtin', false)->where('is_active', true)->get();
        foreach ($customCPTs as $cpt) {
            $permSlug = 'manage_' . str_replace('-', '_', $cpt->slug);
            \FalconCms\Core\Models\Permission::firstOrCreate(
                ['slug' => $permSlug],
                ['name' => 'Manage ' . $cpt->name, 'description' => 'Access ' . $cpt->name . ' section in sidebar']
            );
            $defaultIcon = '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>';
            $cptParent = Menu::create([
                'title'      => $cpt->name,
                'route'      => '/admin/posts?type=' . $cpt->slug,
                'icon'       => $cpt->icon ?: $defaultIcon,
                'group'      => 'Main',
                'order'      => 50 + $cpt->id,
                'permission' => $permSlug,
            ]);
            Menu::create(['parent_id' => $cptParent->id, 'title' => "All {$cpt->name}", 'route' => '/admin/posts?type=' . $cpt->slug, 'order' => 1]);
            Menu::create(['parent_id' => $cptParent->id, 'title' => 'Add New', 'route' => '/admin/posts/create?type=' . $cpt->slug, 'order' => 2]);
        }
    }
}
