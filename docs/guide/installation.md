# Installation

## Requirements

Before installing, make sure your environment meets these requirements:

- PHP **8.1** or higher
- Laravel **10, 11, 12, or 13**
- **MySQL 5.7+** (recommended) or **MariaDB 10.3+** — the officially supported database

::: warning Database support
FalconCMS is built and tested on **MySQL / MariaDB**, which are the only fully supported databases. We strongly recommend MySQL 8+ for production.

**SQLite is not recommended.** It may work for a quick local look, but some features rely on MySQL-specific behaviour and will not work correctly on SQLite. Do not use SQLite for production or for evaluating the full feature set.

PostgreSQL and SQL Server are not supported.
:::

## Install via Composer

```bash
composer require falconcms/falconcms
```

## Run the Installer

```bash
php artisan falcon:install
```

This single command handles everything:
- Runs all database migrations
- Publishes assets (CSS, JS)
- Publishes the default theme
- Creates a storage symlink
- Creates the default admin user

::: info
Your admin credentials will be displayed in the terminal after installation.
:::

## Access the Dashboard

Open your browser and go to:

```
http://your-app.test/admin
```

Log in with the credentials shown after `falcon:install`.

---

## Manual Steps (if needed)

If you prefer to run steps individually:

```bash
# Run migrations only
php artisan migrate

# Publish assets
php artisan vendor:publish --tag=falcon-cms-assets --force

# Publish themes
php artisan vendor:publish --tag=falcon-cms-themes --force

# Create storage symlink
php artisan storage:link
```

---

## Updating

When a new version is released:

```bash
composer update falconcms/falconcms
php artisan falcon:update
```

The `falcon:update` command refreshes assets, themes, and permissions without touching your content.

---

## Uninstalling

FalconCMS ships with two uninstall commands.

### Full removal

Removes everything cleanly — database tables, the trait the installer added to `App\Models\User`, published files, and the Composer package itself — so your app keeps booting with no leftover errors:

```bash
php artisan falcon:uninstall
```

::: warning Run it before `composer remove`
`falcon:uninstall` is part of the package, so run it **while the package is still installed** — it performs the `composer remove` for you at the end. Running `composer remove falconcms/falconcms` first would leave the `HasCmsPermissions` trait referenced in your `User` model and crash the app.
:::

Options:

| Option | Effect |
| --- | --- |
| `--all` | Also drop shared Laravel tables (`users`, `sessions`, `cache`, `jobs`, …) for a full wipe |
| `--force` | Skip the confirmation prompt |
| `--keep-files` | Keep published views, themes and assets |
| `--no-composer` | Don't run `composer remove` automatically |

### Database only

Drops just the FalconCMS tables (and their migration records), leaving the package code, published files and `User` model trait in place — handy to wipe data and start fresh with `php artisan falcon:install` or `php artisan migrate`:

```bash
php artisan falcon:uninstall-db
```

Supports `--all` and `--force`.
