# Installation

## Requirements

Before installing, make sure your environment meets these requirements:

- PHP **8.1** or higher
- Laravel **10, 11, or 12**
- MySQL 5.7+ / MariaDB 10.3+ / **SQLite** (all supported)
- `php_pdo_sqlite` extension enabled (for SQLite)

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
