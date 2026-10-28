# Installation

## Requirements

Before installing, make sure your environment meets these requirements:

- PHP **8.3** or higher
- Laravel **13+**
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

`falcon:install` prints the login URL when it finishes. On a fresh install it is:

```
http://your-app.test/falcon-admin
```

Log in with the credentials shown after `falcon:install`.

::: warning Not /admin
`/admin` is the dashboard itself, and from **v2.6.7** it answers a visitor without a
session with a **404** rather than redirecting to the login page — otherwise the one
address everybody guesses would hand out the address you moved the login page to.

So open the login URL, not `/admin`. You can change it in **Settings → Change login url**.

Once you have signed in on a browser, that browser is remembered: if your session later
ends and you open `/admin` or an old bookmark, it takes you to the login page instead of
the 404. A browser that has never signed in still sees nothing.

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

## Web Server (nginx)

FalconCMS works with nginx's default settings. On a production server, give PHP's response
headers more room than the default as well:

```nginx
# In the http block, or in the server block of the site
fastcgi_buffer_size 32k;
fastcgi_buffers 16 16k;
fastcgi_busy_buffers_size 64k;
```

nginx reads every response header PHP sends (cookies, security headers) into one buffer of
4 KB by default. When they are larger, it answers **502 Bad Gateway** and logs
`upstream sent too big header while reading response header from upstream`. FalconCMS keeps
its own headers well below that: since 2.8.10 the cart cookie stays small whatever the cart
holds. A plugin or theme that sends large cookies or headers can still cross it, and the
setting above stops that becoming a 502.

Check the configuration and reload nginx:

```bash
nginx -t && nginx -s reload
```

::: tip Docker
In a container, put the lines in a file of their own under `/etc/nginx/conf.d/` (for example
`fastcgi-buffers.conf`) and mount it, so recreating the container keeps them.
:::

::: details Seeing 502 Bad Gateway?
Check nginx's error log first (`/var/log/nginx/error.log`):

- `upstream sent too big header` — the headers above. Add the buffer settings, and update
  FalconCMS to 2.8.10 or later if a full cart causes it.
- `connect() failed` or `Connection refused` — PHP-FPM is not running or listens on another
  address than `fastcgi_pass`.
- `upstream timed out` — a request took longer than `fastcgi_read_timeout`; a large import or
  update may need it raised.
- A few seconds of 502 during an update, while PHP-FPM reloads, is normal.
:::

---

## Updating

### From the dashboard (recommended)

Go to **Dashboard → Updates**. When a new version is available, tick the backup
confirmation and click **Run Update Now** — FalconCMS pulls the latest release
with Composer, runs `falcon:update` (migrations, assets, themes, cache) and
refreshes the OPcache for you. No terminal needed.

### Manually (if the dashboard updater can't run)

Some hosts disable `exec()` or ship without a CLI Composer. In that case, run:

```bash
composer update falconcms/falconcms
php artisan falcon:update
```

::: info
`falcon:update` refreshes assets, themes and permissions without touching your
content. The dashboard updater runs exactly these steps for you.
:::

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
