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
2. An **access token** — lets Composer download the private `falconcms/pro` package.

Both are emailed to you right after purchase. The license key is public-facing;
**keep the access token secret** — treat it like a password.
:::

Downloading the code and unlocking the features are **two separate steps**: the access
token gets the code onto your server (Composer), and the license key turns the features
on (in the dashboard).

## Requirements

- FalconCMS core **v2.0** or higher (`composer show falconcms/falconcms`)
- PHP **8.1+**
- Composer **2.x**

If you are on core v1.x, [upgrade to v2](/guide/upgrade) first.

## Install from the dashboard (recommended)

FalconCMS can install Pro for you — no terminal needed:

1. Open your dashboard → **Falcon Builder → License**.
2. Paste your **license key** (`PRO-…` or `AGENCY-…`) and click **Activate**. The key is
   saved; the page notes that the Pro package still needs installing.
3. In the same page, paste your **access token** and click **Save token & install Pro**.
   FalconCMS writes your `auth.json`, adds the private repository and runs
   `composer require falconcms/pro` on the server (this can take up to a minute).
4. Reload the page — the status card turns green and every Pro feature unlocks.

That's the whole flow. Under the hood it does exactly the manual steps below, so use
those if your host blocks server-side Composer (some shared hosts disable `exec()`).

## Install manually (fallback)

Run these in your project root, using the **access token** from your purchase email.

**1. Add the private repository:**

```bash
composer config repositories.falconcms-pro vcs https://github.com/falconcms/falconcms-pro.git
```

**2. Create an `auth.json`** next to `composer.json` (Composer reads it automatically —
a file avoids copy-paste/escaping issues with the long token):

```json
{
    "github-oauth": {
        "github.com": "YOUR-ACCESS-TOKEN"
    }
}
```

::: warning Never commit the token
Add `auth.json` to your `.gitignore`. For servers and CI, see
[Deploying to production](#deploying-to-production).
:::

**3. Install:**

```bash
composer require falconcms/pro
```

**4. Activate:** dashboard → **Falcon Builder → License** → paste your license key →
**Activate**.

## Updating Pro

### From the dashboard

Pro updates ride along with the core updater — **Dashboard → Updates → Run Update Now**
refreshes both. To bump only Pro, re-run the install (or use the command below).

### Manually

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

Your production server and CI pipeline also need the access token to install Pro.
Pick whichever fits your setup — **do not** hard-code the token in tracked files.

**Option A — `auth.json` on the server** (git-ignored):

```json
{
  "github-oauth": {
    "github.com": "YOUR-ACCESS-TOKEN"
  }
}
```

**Option B — environment variable** (great for CI / Docker):

```bash
export COMPOSER_AUTH='{"github-oauth":{"github.com":"YOUR-ACCESS-TOKEN"}}'
composer install --no-dev
```

## Troubleshooting

**“Access token saved, but the Pro package could not be installed automatically.”**
Your host can't run Composer from the browser (or the project isn't writable). The token
is still saved — just follow [Install manually](#install-manually-fallback), starting at
step 1.

**“This license key is not valid.”**
The key wasn't recognised. Double-check you pasted it in full (no spaces). If it still
fails, deactivate and try again, or contact support with your order reference.

**“This license key has reached its activation limit.”**
The key is already active on another site (Pro allows one site). Deactivate it there
first — **Falcon Builder → License → Deactivate** frees the slot so you can activate here.

**“Key saved, but Pro is not active.”**
The key is valid but the `falconcms/pro` package isn't installed yet. Paste your access
token on the License page (or [install manually](#install-manually-fallback)).

**Composer: `Could not authenticate` / `404` on github.com**
The access token is missing or wrong. Re-check your `auth.json`, and confirm the token
from your purchase email hasn't been revoked.

::: tip Lost your key or token?
Contact support with your order reference. A leaked access token can be revoked and
reissued without affecting your license key.
:::
