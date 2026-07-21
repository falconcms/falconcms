# Plugins

Plugins are FalconCMS's **functionality** layer — the counterpart to themes, which
handle presentation. A plugin is a drop-in folder that can add admin pages,
settings, routes, views, database tables and hooks, without touching the CMS or
your theme.

Plugins are **free and unrestricted** — anyone can write, install and share them.

::: tip Theme parity
Anything a theme can do from `functions.php`, a plugin can do from `plugin.php`.
Both are loaded at the same point in the boot cycle, so plugins can use every hook
a theme can — including early, register-time filters.
:::

---

## Managing Plugins

### The Plugins screen

Go to **Plugins** in the admin sidebar. It has two sub-pages:

| Page | What it does |
| --- | --- |
| **Installed Plugins** | Lists every plugin found on disk with its status. Filter by All / Active / Inactive, search, and use the row actions to Activate, Deactivate, Update or Uninstall. |
| **Add New** | Install a plugin by dragging a `.zip` onto the drop zone, or by pasting a direct link to a `.zip` (e.g. a GitHub release asset). |

### Installing

Three ways, all equivalent:

1. **Drag & drop** — Plugins → Add New, drop the `.zip`, click **Install Now**.
2. **From URL** — Plugins → Add New, paste a direct `.zip` link, click **Install**.
3. **Drop-in** — copy the plugin folder into your app's `plugins/` directory.

Installing only puts the files in place. **Activate** it to switch it on — that's
when its migrations run and its code starts loading.

### Updating

Replace the plugin's files with a newer version (re-upload the `.zip` after
uninstalling, or drop the new files in). When the version in `plugin.json` is
newer than the version that was activated, the Plugins screen shows
**Update available**. Click **Update** to run any new migrations and record the
new version.

### Uninstalling

**Uninstall** deactivates the plugin, lets it clean up after itself, rolls back
its migrations, deletes its folder and removes its record. It is irreversible —
back up first if the plugin stores data you care about.

### From the command line

```bash
php artisan plugin:list                  # show plugins and their status
php artisan plugin:activate <slug>       # activate (runs its migrations)
php artisan plugin:deactivate <slug>     # deactivate
php artisan make:plugin "My Plugin"      # scaffold a new plugin
```

### Permissions

Managing plugins requires the **`manage_plugins`** permission. Administrators
have it automatically; assign it to other roles under **Users → Roles**.

::: warning Plugins run PHP
A plugin executes arbitrary PHP with your application's privileges — exactly like
a theme. Only install plugins from sources you trust.
:::

---

## Writing a Plugin

### Scaffold one

```bash
php artisan make:plugin "SEO Booster"
```

This creates `plugins/seo-booster/` with a manifest, a bootstrap file and a
lifecycle class ready to fill in.

### Folder structure

Only `plugin.json` is required. Everything else is picked up by convention if
present — you rarely need to write a ServiceProvider.

```
plugins/seo-booster/
├── plugin.json                 # manifest (required)
├── plugin.php                  # bootstrap — hooks, menus, settings
├── src/                        # PSR-4 classes (namespace from the manifest)
│   └── Lifecycle.php
├── routes/web.php              # auto-registered routes
├── database/migrations/        # run on activation
└── resources/views/            # available as "seo-booster::view-name"
```

### The manifest — `plugin.json`

```json
{
    "name": "SEO Booster",
    "slug": "seo-booster",
    "version": "1.0.0",
    "description": "Adds extra SEO controls.",
    "author": "Your Name",
    "requires_php": ">=8.1",
    "requires_cms": ">=2.0",
    "namespace": "SeoBooster\\",
    "lifecycle": "SeoBooster\\Lifecycle",
    "provider": "SeoBooster\\SeoBoosterServiceProvider",
    "dependencies": []
}
```

| Key | Required | Description |
| --- | --- | --- |
| `name` | ✅ | Display name shown in the admin. |
| `slug` | ✅ | Unique id. Also the view namespace. Restricted to `A–Z a–z 0–9 _ -`. |
| `version` | ✅ | Used for update detection. |
| `description`, `author` | — | Shown on the Plugins screen. |
| `requires_php` | — | Checked on activation, e.g. `">=8.1"`. |
| `requires_cms` | — | Minimum FalconCMS version. |
| `namespace` | — | PSR-4 root, autoloaded from `src/` (or the plugin folder). |
| `lifecycle` | — | Class with optional `activate`/`deactivate`/`uninstall`/`upgrade` methods. |
| `provider` | — | A Laravel ServiceProvider, registered on load. |
| `bootstrap` | — | Bootstrap filename. Defaults to `plugin.php`. |
| `dependencies` | — | Slugs that must be installed **and active** first. |

### The bootstrap — `plugin.php`

Loaded only while the plugin is active — the plugin equivalent of a theme's
`functions.php`.

```php
<?php

// Frontend: filter content as it renders
add_falcon_filter('falcon_product_description', function ($description) {
    return $description . '<p>Shipping is free this week.</p>';
});

// Backend: add a field to the native General settings page
add_falcon_action('falcon_register_settings', function () {
    falcon_add_settings_field([
        'id'    => 'seo_booster_api_key',
        'label' => 'API Key',
        'type'  => 'text',
    ]);
});

// Backend: add a sidebar menu page
falcon_add_menu_page([
    'slug'  => 'seo-booster',
    'title' => 'SEO Booster',
    'icon'  => 'trending_up',
    'route' => 'seo-booster.dashboard',
]);
```

See the [Hooks API](/api/hooks), [Admin Menu API](/api/admin-menu) and
[Settings Fields API](/api/settings-fields) for everything available here.

### Lifecycle hooks

Point `lifecycle` at a class; every method is optional.

```php
<?php

namespace SeoBooster;

class Lifecycle
{
    public function activate(): void
    {
        // Runs once when activated — seed defaults, create files.
    }

    public function deactivate(): void
    {
        // Runs when switched off. Leave data intact.
    }

    public function uninstall(): void
    {
        // Runs before removal — drop your tables/options here.
    }

    public function upgrade(?string $previousVersion): void
    {
        // Runs on Update — migrate data from an older release.
    }
}
```

### Routes

`routes/web.php` inside your plugin is registered automatically — and
**before** the CMS frontend catch-all, so your URLs resolve.

```php
use Illuminate\Support\Facades\Route;

Route::get('/seo-report', function () {
    return view('seo-booster::report');
})->middleware('web')->name('seo-booster.report');
```

::: tip
Add `->middleware('web')` if your route needs sessions, cookies or CSRF.
:::

### Views

Anything in `resources/views/` is namespaced by your slug:

```php
view('seo-booster::report');
```

### Migrations

Migrations in `database/migrations/` run when the plugin is **activated**, and
are rolled back on uninstall. Prefix your table names to avoid collisions.

### Classes and providers

Set `namespace` and your `src/` folder is PSR-4 autoloaded at runtime — no
`composer dump-autoload` needed. For full Laravel power (commands, bindings,
scheduled tasks, view composers), add a `provider` and it is registered like any
other package provider.

### Dependencies

```json
"dependencies": ["another-plugin"]
```

Dependencies are enforced both ways: a plugin cannot be activated until its
dependencies are active, and a dependency cannot be deactivated or uninstalled
while something depends on it. Load order follows the dependency graph.

---

## How plugins are loaded

1. Active plugins are read from the `plugins` table.
2. For each, in dependency order: the PSR-4 namespace is registered, its
   ServiceProvider (if any) is registered, and `plugin.php` is required — at the
   same point in the boot cycle as a theme's `functions.php`.
3. Its views, migrations and routes are wired by convention.

::: info Fatal-safe
If a plugin throws while loading, it is **automatically deactivated** and the
error is logged instead of taking the site down. Activation is guarded too: a
plugin that fails to load is never marked active in the first place.
:::
