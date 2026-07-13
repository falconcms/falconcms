# Upgrade Guide

## Upgrading to a New Version

### From the dashboard (recommended)

**Dashboard → Updates** shows your installed version and the latest release. When
an update is available, tick **"I have created a backup"** and click **Run Update
Now**. FalconCMS installs the exact latest version, runs `falcon:update` and
clears caches — all from the browser, no terminal required.

::: tip Nothing to click? Already up to date.
The page reads the latest version from Packagist; if it matches what you have,
it shows **"You're up to date"**. A freshly-published release can take a few
minutes to appear.
:::

### Manually (if the dashboard updater can't run)

On hosts where `exec()` is disabled or Composer isn't on the CLI, run:

```bash
# 1. Update the package
composer update falconcms/falconcms

# 2. Run the update command
php artisan falcon:update
```

`falcon:update` handles everything automatically:
- Runs new database migrations
- Syncs permissions and roles
- Refreshes admin assets (CSS, JS)
- Refreshes the default theme
- Publishes child theme skeleton (non-destructive — never overwrites your customizations)
- Clears application cache
- Auto-creates any missing e-commerce pages

::: tip Child theme safety
`falcon:update` will **never** overwrite files in your child theme. Your customizations in `resources/views/themes/my-child-theme/` are always preserved.
:::

## CLI Commands Reference

| Command | Description |
|---|---|
| `php artisan falcon` | Show all Falcon CMS commands |
| `php artisan falcon:install` | Full fresh install |
| `php artisan falcon:update` | Update assets, migrations, and permissions |
| `php artisan falcon:seed` | Seed default menus and demo content |
| `php artisan falcon:publish-scheduled` | Manually publish due scheduled posts |
| `php artisan make:falcon-page {Name}` | Scaffold a new admin dashboard page |

## Scaffolding a Custom Admin Page

```bash
php artisan make:falcon-page Analytics
```

Creates:
- `app/Http/Controllers/Admin/AnalyticsController.php`
- `resources/views/admin/analytics/index.blade.php`
- Route entry in your routes file
- Sidebar menu item

## Scheduled Tasks

Falcon CMS auto-registers a scheduled task via Laravel's scheduler. Ensure your cron is set up:

```bash
# crontab -e
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

This runs `falcon:publish-scheduled` every minute to auto-publish scheduled posts.

If you can't set up cron, Falcon CMS also auto-publishes as a **terminating callback** after every web request — so scheduled posts publish on the next page load after their scheduled time.

## Backups

Always back up before upgrading:

1. Go to **Admin → Tools → Backup**
2. Click **Create Backup**
3. Download the backup file
4. Then run `composer update` and `falcon:update`

If something goes wrong, restore from backup:
1. **Admin → Tools → Backup → Upload** your backup file
2. Click **Restore**

## WordPress Migration

Import content from a WordPress site:

1. Go to **Admin → Tools → WordPress Import**
2. Export your WordPress content as XML (`wp-admin → Tools → Export`)
3. Upload the XML file
4. Optionally: import media files
5. All posts, pages, categories, tags, and users are imported

## REST API

Falcon CMS includes a REST API for headless use.

### Enable API

1. **Admin → Settings → API**
2. Toggle **Enable REST API**
3. Click **Generate Token**

### Endpoints

```http
GET /api/v1/posts
GET /api/v1/posts/{slug}
GET /api/v1/pages
GET /api/v1/products
GET /api/v1/products/{slug}
GET /api/v1/categories
GET /api/v1/tags
GET /api/v1/menus
GET /api/v1/settings
GET /api/v1/search?q=keyword
```

### Authentication

```http
Authorization: Bearer your-api-token-here
```

Write operations (create/update/delete) require a token with appropriate permissions.
