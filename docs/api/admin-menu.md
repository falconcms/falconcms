# Admin Menu API

Register admin sidebar menus and settings pages at runtime from a theme's
`functions.php` or a plugin's `plugin.php` — the FalconCMS equivalent of
WordPress's `add_menu_page()` and the Settings API.

Registered menus are merged into the DB-driven sidebar, so they survive
`falcon:update` (which re-seeds the core menu table) without any migration.

## Functions

```php
falcon_add_menu_page(array $args): void       // top-level sidebar item
falcon_add_submenu_page(string $parent, array $args): void
falcon_add_options_page(array $args): void    // menu item + a rendered settings page
```

---

## Adding a menu page

```php
falcon_add_menu_page([
    'slug'       => 'seo-booster',            // required, unique
    'title'      => 'SEO Booster',            // sidebar label
    'icon'       => 'trending_up',            // Material Symbols icon name
    'route'      => 'seo-booster.dashboard',  // named route to link to
    'permission' => 'manage_settings',        // required capability
    'position'   => 60,                       // sort order
    'group'      => 'Extend',                 // sidebar section heading
]);
```

| Key | Default | Description |
| --- | --- | --- |
| `slug` | — | Unique identifier. Required. |
| `title` | slug | Label shown in the sidebar. |
| `icon` | — | [Material Symbols](https://fonts.google.com/icons) name. |
| `route` | — | Named route the item links to. |
| `params` | `[]` | Route parameters. |
| `permission` | `manage_settings` | Capability required to see/open it. |
| `position` | `100` | Sort order within the group. |
| `group` | `Extend` | Sidebar section. Anything other than `Main` renders as a labelled section. |

::: warning
An item whose `route` is not registered is hidden automatically — so a menu
pointing at a route from a deactivated plugin never 404s.
:::

## Adding a submenu

```php
falcon_add_submenu_page('seo-booster', [
    'title' => 'Reports',
    'route' => 'seo-booster.reports',
]);
```

## Registering from a hook

Menus can be registered directly, or deferred to the `falcon_admin_menu` action,
which fires once when the sidebar is first built:

```php
add_falcon_action('falcon_admin_menu', function () {
    falcon_add_menu_page([...]);
});
```

---

## Options pages

`falcon_add_options_page()` registers a sidebar item **and** a fully rendered
settings page — fields, saving and validation included. You write no controller,
route or view.

```php
falcon_add_options_page([
    'slug'        => 'seo-booster',
    'title'       => 'SEO Booster Settings',
    'menu_title'  => 'SEO Booster',
    'icon'        => 'trending_up',
    'description' => 'Configure the SEO Booster plugin.',
    'capability'  => 'manage_settings',
    'fields'      => [
        ['name' => 'sb_api_key', 'label' => 'API Key',  'type' => 'text'],
        ['name' => 'sb_enabled', 'label' => 'Enabled',  'type' => 'checkbox'],
    ],
]);
```

The page is served at `/admin/options/{slug}`. Values are stored as CMS options:

```php
$key = get_cms_option('sb_api_key');
```

See [Settings Fields API](/api/settings-fields#field-types) for every field type
and option.

### Tabbed options pages

Swap `fields` for `tabs` to get a tabbed layout. All tabs live in one form and
save together; the active tab is preserved across saves and deep-linkable via
`?tab=`.

```php
falcon_add_options_page([
    'slug'  => 'seo-booster',
    'title' => 'SEO Booster',
    'tabs'  => [
        [
            'id'     => 'general',
            'label'  => 'General',
            'icon'   => 'tune',
            'fields' => [
                ['name' => 'sb_api_key', 'label' => 'API Key', 'type' => 'text'],
            ],
        ],
        [
            'id'     => 'advanced',
            'label'  => 'Advanced',
            'fields' => [
                ['name' => 'sb_debug', 'label' => 'Debug mode', 'type' => 'checkbox'],
            ],
        ],
    ],
]);
```

---

## Choosing an approach

| You want | Use |
| --- | --- |
| A link to your own controller/view | `falcon_add_menu_page()` |
| A settings screen without writing one | `falcon_add_options_page()` |
| Fields on an **existing** settings screen | [`falcon_add_settings_field()`](/api/settings-fields) |
| A new tab in the **Settings** area | [`falcon_add_settings_tab()`](/api/settings-fields#custom-settings-tabs) |
