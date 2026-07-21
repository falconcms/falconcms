<?php

/**
 * Admin sidebar menu API — the WordPress `add_menu_page()` / `add_submenu_page()`
 * analogue for FalconCMS. Call these from a `falcon_admin_menu` action callback:
 *
 *   add_falcon_action('falcon_admin_menu', function () {
 *       falcon_add_menu_page([
 *           'slug'       => 'reports',
 *           'menu_title' => 'Reports',
 *           'route'      => 'admin.reports',      // your named route
 *           'capability' => 'manage_reports',     // RBAC permission
 *           'icon'       => 'bar_chart',          // material-symbol name (or raw <svg>)
 *           'position'   => 60,
 *           'group'      => 'Extend',             // sidebar section ('Main' = no label)
 *       ]);
 *       falcon_add_submenu_page('reports', [
 *           'menu_title' => 'Monthly',
 *           'route'      => 'admin.reports.monthly',
 *           'capability' => 'manage_reports',
 *       ]);
 *   });
 *
 * Menus are registered in code and rendered at runtime — nothing is written to the
 * database. See {@see \FalconCms\Core\Support\AdminMenu}.
 */

use FalconCms\Core\Support\AdminMenu;

if (! function_exists('falcon_add_menu_page')) {
    /** Register a top-level admin sidebar menu. */
    function falcon_add_menu_page(array $args): void
    {
        app(AdminMenu::class)->addMenuPage($args);
    }
}

if (! function_exists('falcon_add_submenu_page')) {
    /** Register a submenu item under a parent menu's slug. */
    function falcon_add_submenu_page(string $parentSlug, array $args): void
    {
        app(AdminMenu::class)->addSubmenuPage($parentSlug, $args);
    }
}

if (! function_exists('falcon_add_options_page')) {
    /**
     * Register a self-rendering settings page (menu + form + save), WordPress
     * Settings-API style. Example:
     *
     *   add_falcon_action('falcon_admin_menu', function () {
     *       falcon_add_options_page([
     *           'slug'       => 'my_plugin',
     *           'menu_title' => 'My Plugin',
     *           'title'      => 'My Plugin Settings',
     *           'capability' => 'manage_settings',
     *           'icon'       => 'extension',
     *           'fields'     => [
     *               ['name' => 'my_api_key', 'label' => 'API Key',  'type' => 'text', 'help' => 'From your dashboard'],
     *               ['name' => 'my_enabled', 'label' => 'Enabled',  'type' => 'checkbox'],
     *               ['name' => 'my_mode',    'label' => 'Mode',      'type' => 'select', 'options' => ['live' => 'Live', 'test' => 'Test']],
     *               ['name' => 'my_notes',   'label' => 'Notes',     'type' => 'textarea'],
     *           ],
     *       ]);
     *   });
     *
     * Each field's value is stored/read as a CMS option keyed by its `name`
     * (get_cms_option / update_cms_option).
     */
    function falcon_add_options_page(array $args): void
    {
        app(AdminMenu::class)->addOptionsPage($args);
    }
}
