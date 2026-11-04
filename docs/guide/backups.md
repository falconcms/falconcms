# Backups & Moving a Site

**Tools → Backup** makes a copy of your site in one file, and puts a site back from one — the
same site after a mistake, or a whole site on another server or address.

## Making a backup

Choose what to include:

- **Database** — posts, pages, users, menus, settings and everything else stored in the database
  (a `.sql` file).
- **Media** — uploaded images and files (a `.zip`).
- **Database + Media** — both in one `.zip`: a complete copy of the site.

Download the file and keep it somewhere safe, off the server.

::: tip
Database backups need MySQL or MariaDB. On SQLite, media backups still work.
:::

## Restoring

Click **Restore** beside a backup in the list. A backup made on another site is first uploaded
with **Upload Backup from Another Site**, then restored the same way.

Restore works out by itself what a file holds — a database, media, or both — and brings it back.
A full restore can take a while; it carries on on the server even if the browser gives up waiting.

::: warning
Restoring the database replaces the site's current content and users with the backup's.
Make a backup of the current site first if you might want it back.
:::

## Moving a site to another address <Badge type="tip" text="2.9.0" /> {#moving}

Moving the live site to a local copy, a staging server or a new domain is a backup on one and a
restore on the other. **Links to the old address are changed to the new one by themselves** —
you don't search and replace anything.

A backup records the address of the site it was made on. When it is restored on a different
address, every link to the old address becomes a link to this one:

- menus, buttons and links in builder layouts and post content
- settings (logo, social links and so on) and page revisions
- links written inside JSON, inside PHP-serialized values, and URL-encoded inside other links
  (share buttons, embeds)
- `http` and `https`, with or without `www`

The message after the restore says how many links moved, and from where to where. The site's
caches are cleared, so the new links show at once.

**Left as they were:**

- other domains — `demo.example.com` or `example.com.au` when the old site was `example.com`
- e-mail addresses, and a domain written as plain text without `http`
- visit logs, the 404 log, Search Console figures and similar records of what happened on the
  old site

A backup made before 2.9.0 does not record its address; the old address is then read from the
data (where its menu items and media point).

### Menus under any address

Menu items for pages, posts and categories are worked out from the item when the page is shown,
and links to the site itself are written as paths (`/about`). So menus are right whatever address
the site is opened at — even a copy reached both as `mysite.test` and as `127.0.0.1:8000`.

## Changing addresses by hand {#replace-url}

Data copied in some other way — imported straight into the database, say — can be moved with
the `falcon:replace-url` command:

```bash
# Links to the old site → this site's APP_URL
php artisan falcon:replace-url https://old-site.com

# … or to an address you give
php artisan falcon:replace-url https://old-site.com https://new-site.com

# Drop the address and keep the path (/about): right under any address
php artisan falcon:replace-url https://old-site.com --relative
```

With no old address, the command works it out from the data and asks before changing anything
(`--force` skips the question). It lists how many values changed in each table.

::: tip
Set `APP_URL` in `.env` to the address the site is actually opened at, then run
`php artisan config:clear`.
:::

::: warning
A builder tab left open from before a move still holds the old links. Saving it brings them
back. Close such tabs without saving and open the page again.
:::
