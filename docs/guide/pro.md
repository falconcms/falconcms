# Installing FalconCMS Pro

FalconCMS is **open-core**. The free `falconcms/falconcms` package (MIT, on public
Packagist) gives you the CMS, page builder, themes, menus, media and hooks API.

**Pro** unlocks the commercial features:

- **E-commerce** — storefront, cart, checkout, orders, coupons
- **Multi-language** — translated content and language switching
- **Analytics** — dashboard insights and reports
- **Advanced page builder** — the Pro elements, Library and Global Sections
- **Custom fields** — ACPT field groups
- **Advanced login** — multi-device & magic login

Pro ships as a separate, proprietary package — `falconcms/pro` — delivered from a
**private repository**. It is never bundled in the free download, so you install it
with Composer using the credentials you receive when you buy a license.

::: tip You need two things
1. A **license key** (`PRO-…` or `AGENCY-…`) — activates the features inside the dashboard.
2. A **Composer token** — lets Composer download the private `falconcms/pro` package.

Both are emailed to you right after purchase (and are always available in your
account portal). The license key is public-facing; **keep the Composer token secret** —
treat it like a password.
:::

## Requirements

- FalconCMS core **v2.0** or higher (`composer show falconcms/falconcms`)
- PHP **8.1+**
- Composer **2.x**

If you are on core v1.x, [upgrade to v2](/guide/upgrade) first.

## Step 1 — Add the private repository

Tell Composer where the Pro package lives. Run this in your project root:

```bash
composer config repositories.falconcms-pro composer https://repo.packagist.com/falconcms/
```

This adds a `repositories` entry to your `composer.json`. You only do it once per project.

## Step 2 — Authenticate with your Composer token

Store the token you received by email. This writes it to Composer's **global** auth
file (`~/.composer/auth.json` or `COMPOSER_HOME/auth.json`), so it is **not** committed
with your project:

```bash
composer config --global --auth http-basic.repo.packagist.com token YOUR-COMPOSER-TOKEN
```

::: warning Never commit the token
Do not put the token in your project's `composer.json` or in version control. Use the
global auth file (above), or an `auth.json` in the project root that is listed in
`.gitignore`. For servers and CI, see [Deploying to production](#deploying-to-production).
:::

## Step 3 — Install the package

```bash
composer require falconcms/pro
```

Composer pulls `falconcms/pro` from the private repository and registers its service
provider automatically (Laravel package discovery).

## Step 4 — Activate your license key

1. Open your dashboard → **Falcon Builder → License**.
2. Paste your **license key** (`PRO-…` or `AGENCY-…`) and click **Activate**.
3. The status card turns green and every Pro feature unlocks.

That's it. The key is validated against your plan and stored privately on your site.

## Updating Pro

When a new Pro version is released, update it exactly like the core package:

```bash
composer update falconcms/pro
```

Or update both together:

```bash
composer update falconcms/falconcms falconcms/pro
```

::: info Keep core and Pro in step
Pro requires a matching core major version. When you move to a new core major (e.g.
v2 → v3), update both at once so their constraints resolve.
:::

## Deploying to production

Your production server and CI pipeline also need the Composer token to install Pro.
Pick whichever fits your setup — **do not** hard-code the token in tracked files.

**Option A — `auth.json` on the server** (git-ignored):

```json
{
  "http-basic": {
    "repo.packagist.com": {
      "username": "token",
      "password": "YOUR-COMPOSER-TOKEN"
    }
  }
}
```

**Option B — environment variable** (great for CI / Docker):

```bash
export COMPOSER_AUTH='{"http-basic":{"repo.packagist.com":{"username":"token","password":"YOUR-COMPOSER-TOKEN"}}}'
composer install --no-dev
```

## Troubleshooting

**“Key saved, but Pro is not active.”**
The license key is stored but the `falconcms/pro` package isn't installed on this site
yet. Run Steps 1–3 above, then reload the License page.

**Composer: `Could not authenticate against repo.packagist.com`**
The token is missing or wrong. Re-run Step 2, or check your `auth.json`. Confirm the
token in your account portal hasn't been revoked.

**Composer: `Could not find package falconcms/pro`**
The private repository entry is missing. Re-run Step 1, then `composer require falconcms/pro`.

**A key `did not resolve to a valid plan.`**
Double-check the key was copied in full. If it still fails, deactivate and re-activate,
or contact support with your order reference.

::: tip Lost your key or token?
Both are always available in your account portal, and a leaked Composer token can be
revoked and reissued there without affecting your license key.
:::
