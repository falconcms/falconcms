<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/falconcms/falconcms/main/public/assets/images/falcon-cms-white-logo.png">
    <img alt="FalconCMS" height="64" src="https://raw.githubusercontent.com/falconcms/falconcms/main/public/assets/images/falcon-cms-logo.png">
  </picture>
</p>

<h1 align="center">FalconCMS</h1>

<p align="center">
  <b>Build your application. Not your CMS.</b><br>
  An open-source CMS foundation for Laravel — visual page builder, custom post types,<br>
  media, menus, SEO, e-commerce, security, themes, plugins, and a WordPress-like hook system.
</p>

<p align="center">
  <a href="https://packagist.org/packages/falconcms/falconcms"><img alt="Latest Version on Packagist" src="https://img.shields.io/packagist/v/falconcms/falconcms.svg"></a>
  <a href="https://github.com/falconcms/falconcms/actions/workflows/ci.yml"><img alt="CI" src="https://github.com/falconcms/falconcms/actions/workflows/ci.yml/badge.svg"></a>
  <a href="https://packagist.org/packages/falconcms/falconcms"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/falconcms/falconcms.svg"></a>
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/github/license/falconcms/falconcms"></a>
</p>

<p align="center">
  <a href="https://demo.falconcms.com">Live Demo</a> ·
  <a href="https://falconcms.github.io/falconcms/">Documentation</a> ·
  <a href="https://falconcms.com">Website</a> ·
  <a href="https://falconcms.github.io/falconcms/changelog">Changelog</a>
</p>

<p align="center">
  <img alt="The FalconCMS dashboard — content counts, activity chart, and store metrics at a glance" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/dashboard.webp" width="900">
</p>

---

## Why FalconCMS

Every Laravel project eventually needs the same building blocks — pages, posts, media,
navigation, forms, SEO, users, permissions, custom fields, settings. Building them again
for every project takes time away from the product you actually set out to build.

FalconCMS gives you that foundation as a Composer package, running inside your existing
Laravel app alongside your own routes, models and service providers. You keep Laravel;
you skip rebuilding the CMS.

---

## 🚀 Installation

**Requirements:** PHP 8.3+ · Laravel 13+ · MySQL 5.7+ / MariaDB 10.3+

Add it to an existing Laravel application:

```bash
composer require falconcms/falconcms
php artisan falcon:install
```

`falcon:install` runs the migrations, publishes assets and the default theme, links
storage, and creates your admin user — the credentials are printed in the terminal. Then
visit `/admin`.

📖 [Installation guide](https://falconcms.github.io/falconcms/guide/installation) ·
[Configuration](https://falconcms.github.io/falconcms/guide/configuration)

---

## 🛠 Commands Summary

Falcon CMS comes with a set of automated commands to make development easier.

| Command | Description |
| :--- | :--- |
| `php artisan falcon` | **Help Menu:** Lists all available Falcon CMS commands in CLI. |
| `php artisan falcon:install` | **Full Setup:** Migrations, Assets, Themes, User and seeds. |
| `php artisan falcon:update` | **Sync Update:** Refreshes assets, themes, and permissions. |
| `php artisan falcon:seed` | **Demo Data:** Seeds default menus and initial demo data. |
| `php artisan make:falcon-page` | **Scaffold:** Creates a new dashboard page, controller, and menu item. |

---

## ✨ What's in the free core

Everything below is MIT-licensed and needs no purchase.

| | |
| :--- | :--- |
| **Content** | Pages, posts, and unlimited custom post types created from the dashboard — each with its own admin panel, archive and single view. Categories, tags, revisions, scheduling, and a media library. |
| **Falcon Builder** | Drag-and-drop containers, columns and elements over a live preview of the real theme, with desktop / tablet / mobile previews and per-device visibility. |
| **E-commerce** | Products and variants, cart, guest or registered checkout, orders and invoices, coupons, shipping zones, per-country tax, and PayPal / Stripe / SSLCommerz / bank transfer. |
| **Themes** | A WordPress-like, file-based theme system with strict view isolation — frontend views live in `resources/views/themes/{theme}/`, and anything in the root `resources/views/` is blocked from frontend rendering (404). |
| **Plugins** | Drop-in folders that add admin pages, settings, routes, views, tables and hooks without touching the core. Scaffold one with `php artisan make:plugin`. |
| **Menus & widgets** | A visual menu builder including mega menus, plus widget areas for sidebars and footer columns. |
| **Roles & permissions** | Eight seeded roles with granular per-capability permissions, and strict content ownership — Authors and Contributors never reach another user's content. |
| **Developer API** | 109 hooks (63 actions, 46 filters), template tags, helper functions, an Admin Menu API, and a REST API for headless use. |
| **Migration** | A WordPress importer that parses WXR and can be re-run without duplicating anything. |

---

## 📸 A Look Inside

Every screenshot below is the live [demo site](https://demo.falconcms.com) — sign in with
`admin@falconcms.demo` / `FalconDemo2025!` and click through it yourself.

### Falcon Builder

Drag-and-drop containers, columns and elements over a live preview of the real theme. The
navigator on the left is the page structure; changes render exactly as the frontend will
serve them.

<p align="center">
  <img alt="Falcon Builder editing a page, with the structure navigator open beside a live canvas" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/page-builder.webp" width="900">
</p>

Select any element to open its settings panel, and switch the canvas between desktop,
tablet and mobile to set per-device values and visibility.

<table>
<tr>
<td width="50%"><img alt="Icon Box element settings — icon picker, layout and alignment" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/builder-element-settings.webp"></td>
<td width="50%"><img alt="The builder canvas in mobile preview" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/builder-responsive.webp"></td>
</tr>
<tr>
<td align="center"><b>Element settings</b></td>
<td align="center"><b>Responsive preview</b></td>
</tr>
</table>

### Content & Media

<table>
<tr>
<td width="50%"><img alt="The posts list with author, slug, category, SEO and publish date columns" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/posts.webp"></td>
<td width="50%"><img alt="The media library grid" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/media-library.webp"></td>
</tr>
<tr>
<td align="center"><b>Posts &amp; pages</b></td>
<td align="center"><b>Media library</b></td>
</tr>
<tr>
<td width="50%"><img alt="Custom post types created from the dashboard" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/custom-post-types.webp"></td>
<td width="50%"><img alt="The mega menu builder" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/menus.webp"></td>
</tr>
<tr>
<td align="center"><b>Custom post types</b></td>
<td align="center"><b>Menu builder</b></td>
</tr>
</table>

### E-commerce — in the free core

<table>
<tr>
<td width="50%"><img alt="The product list with price, stock and sale columns" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/products.webp"></td>
<td width="50%"><img alt="The orders list with payment and fulfilment status" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/orders.webp"></td>
</tr>
<tr>
<td align="center"><b>Products</b></td>
<td align="center"><b>Orders</b></td>
</tr>
<tr>
<td width="50%"><img alt="Shop overview with revenue, orders and conversion metrics" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/shop-overview.webp"></td>
<td width="50%"><img alt="The storefront with filters, product grid and sale badges" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/storefront.webp"></td>
</tr>
<tr>
<td align="center"><b>Shop overview</b></td>
<td align="center"><b>Storefront</b></td>
</tr>
</table>

### Appearance & Administration

<table>
<tr>
<td width="50%"><img alt="The theme customizer — layout, colors, typography, header and footer options" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/customizer.webp"></td>
<td width="50%"><img alt="Installed themes with parent and child theme cards" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/themes.webp"></td>
</tr>
<tr>
<td align="center"><b>Customizer</b></td>
<td align="center"><b>Themes</b></td>
</tr>
<tr>
<td width="50%"><img alt="Roles and permissions, with the number of users holding each role" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/roles.webp"></td>
<td width="50%"><img alt="Installed plugins with activate and delete actions" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/plugins.webp"></td>
</tr>
<tr>
<td align="center"><b>Roles &amp; permissions</b></td>
<td align="center"><b>Plugins</b></td>
</tr>
</table>

> Screenshots are regenerated from the live demo with `node docs/scripts/screenshots.mjs`.
> See [`docs/scripts/README.md`](docs/scripts/README.md).

---

## 🛡️ Falcon Security — Laravel CMS security in one plugin (Pro)

**Falcon Security** is the security suite that ships with FalconCMS Pro: two-factor
authentication, passkeys, OTP by SMS and WhatsApp, a web application firewall, a malware
scanner with signed release integrity, a Content Security Policy builder, spam protection,
fake-order protection for cash on delivery, and an agency dashboard — configured from the
admin, no code.

| | |
| :--- | :--- |
| **Sign-in security** | Two-factor sign-in (authenticator app, passkeys / WebAuthn, or codes by SMS and WhatsApp), recovery codes, roles that must use it, "confirm it's you" before risky changes, impossible-travel checks, sign-in alerts, session management, a breached-password policy. |
| **OTP by SMS & WhatsApp** | Twilio, Meta's WhatsApp Cloud API or any SMS provider's web API (presets for Bangladesh's BulkSMSBD, sms.net.bd, Greenweb and SSL Wireless), sign-in with a phone code, and limits against SMS pumping. |
| **Firewall & WAF** | IP, range and country rules, attack patterns (SQL injection, XSS, path traversal), probe and flood bans, fake search-engine bots, an admin allowlist, live traffic, lockdown. |
| **Malware scanner** | Web shells, PHP in uploads, injected scripts in the database, files changed since the baseline — and FalconCMS's own files checked against the signed release, so an update never looks like a hack. |
| **Content Security Policy** | Learns which outside sources the site uses from browsers' reports, then enforces them: an injected script or a card skimmer is refused by the browser. |
| **Fake-order protection** | A 0–100 risk score per order from courier delivery history (Steadfast and BD Courier for Bangladesh, or any JSON service), the shop's own history and bursts of orders; hold, ask to pay online or refuse; phone confirmation by OTP on cash-on-delivery orders; an order blocklist. |
| **Alerts & reports** | Email, Telegram and WhatsApp alerts; known-vulnerability alerts for FalconCMS, plugins and themes; a security score (A–F) with a printable monthly report. |
| **Agency dashboard** | Every client site's score, security holes, malware and attacks on one screen (Agency plan). |

<p align="center">
  <img alt="Falcon Security overview for Laravel: security score and grade, firewall status, two-factor adoption, malware scanner and monthly security report" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-overview.webp" width="900">
</p>

<table>
<tr>
<td width="50%"><img alt="Falcon Security fake-order check: courier history service, risk actions and a phone number check" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-fake-orders.webp"></td>
<td width="50%"><img alt="The shop orders list with a Risk column and a courier delivery-rate check for a customer's phone number" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-orders-risk.webp"></td>
</tr>
<tr>
<td align="center"><b>Fake-order check &amp; courier history</b></td>
<td align="center"><b>Risk on the orders list</b></td>
</tr>
<tr>
<td width="50%"><img alt="OTP and alerts settings: SMS and WhatsApp gateways for one-time codes" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-otp-alerts.webp"></td>
<td width="50%"><img alt="Sign-in security: two-factor roles, trusted devices, confirm-it's-you and impossible travel" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-sign-in.webp"></td>
</tr>
<tr>
<td align="center"><b>OTP by SMS &amp; WhatsApp</b></td>
<td align="center"><b>Sign-in security</b></td>
</tr>
<tr>
<td width="50%"><img alt="Content Security Policy builder learning the sources a site uses before enforcing" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-csp.webp"></td>
<td width="50%"><img alt="An order's phone check: delivery rate, returned parcels and merchant reports" src="https://raw.githubusercontent.com/falconcms/falconcms/main/docs/public/screenshots/security-order-check.webp"></td>
</tr>
<tr>
<td align="center"><b>Content Security Policy builder</b></td>
<td align="center"><b>Phone check on an order</b></td>
</tr>
</table>

These screens are from the live [demo](https://demo.falconcms.com). The demo has no courier
account, so the delivery figures in the phone checks are sample data.

🛡️ [Falcon Security guide](https://falconcms.github.io/falconcms/guide/security)

---

## 💎 Core vs Pro

FalconCMS is **open-core**. The core above is MIT-licensed and stays free. **Pro** is a
separate, proprietary package (`falconcms/pro`) that unlocks the commercial features.

| Capability | Core | Pro | Agency |
| :--- | :---: | :---: | :---: |
| CMS — pages, posts, custom types, media | ✓ | ✓ | ✓ |
| Drag-and-drop page builder | ✓ | ✓ | ✓ |
| E-commerce — products, cart, orders, coupons | ✓ | ✓ | ✓ |
| Themes, plugins, hooks API & WordPress importer | ✓ | ✓ | ✓ |
| Menus, widgets, roles & permissions | ✓ | ✓ | ✓ |
| Layout Builder, global sections & library | — | ✓ | ✓ |
| Multi-language & translation | — | ✓ | ✓ |
| Advanced builder elements | — | ✓ | ✓ |
| Advanced custom fields (ACPT) | — | ✓ | ✓ |
| Falcon Slider — layered slider builder | — | ✓ | ✓ |
| Analytics — live visitor map & real-time users | — | ✓ | ✓ |
| Multi-device & passwordless (magic) login | — | ✓ | ✓ |
| Falcon Security — 2FA, passkeys, OTP by SMS/WhatsApp, firewall, malware scanner | — | ✓ | ✓ |
| Fake-order protection — courier history, risk score, COD phone OTP | — | ✓ | ✓ |
| Agency security dashboard — all client sites on one screen | — | — | ✓ |
| Sites per license | 1 | 1 | Unlimited |
| White-label branding | — | — | ✓ |
| Support | Community | Standard | Priority |

Pro is a **one-time license per site** and includes 12 months of updates and support;
renew only if you want to keep receiving them. Agency covers unlimited client sites.

💎 [Pricing](https://falconcms.com/#pricing) ·
[Installing Pro](https://falconcms.github.io/falconcms/guide/pro)

---

## ⚓ Hook System Examples

### Adding a Filter
```php
add_falcon_filter('falcon_general_settings_fields', function($fields) {
    $fields['my_custom_option'] = ['type' => 'text', 'label' => 'Custom Option'];
    return $fields;
});
```

### Adding an Action
```php
add_falcon_action('falcon_after_post_content', function($post) {
    echo "<div>Related Content Here</div>";
});
```

### Registering a Custom Builder Element
```php
add_falcon_filter('falcon_builder_elements', function($elements) {
    $elements['my_element'] = [
        'label' => 'My Element',
        'icon'  => 'star',
        'fields' => [],
    ];
    return $elements;
});
```

📖 [Hooks API reference](https://falconcms.github.io/falconcms/api/hooks) — all 109 hooks

---

## 📚 Documentation

| | |
| :--- | :--- |
| [Introduction](https://falconcms.github.io/falconcms/guide/introduction) | What FalconCMS is and when to reach for it |
| [Installation](https://falconcms.github.io/falconcms/guide/installation) | Requirements, install, first project |
| [Falcon Builder](https://falconcms.github.io/falconcms/builder/overview) | Containers, columns, elements, global sections |
| [Post types & taxonomies](https://falconcms.github.io/falconcms/guide/post-types) | Custom content, ACPT, revisions |
| [Themes](https://falconcms.github.io/falconcms/guide/themes) | Theme structure, template tags, the customizer |
| [Plugins](https://falconcms.github.io/falconcms/guide/plugins) | Scaffolding and shipping your own |
| [E-commerce](https://falconcms.github.io/falconcms/ecommerce/overview) | Products, storefront, checkout, orders, tax |
| [Hooks API](https://falconcms.github.io/falconcms/api/hooks) | Actions and filters, with examples |

Full documentation: **[falconcms.github.io/falconcms](https://falconcms.github.io/falconcms/)**
· Try it first on the **[live demo](https://demo.falconcms.com)**.

---

## 🤝 Contributing

Bug reports, fixes and features are welcome. [`CONTRIBUTING.md`](CONTRIBUTING.md) covers
the local setup, the test suite and what CI checks before a PR can merge.

Found a security issue? Please email tareqcodex@gmail.com rather than opening a public
issue.

---

## 📄 License

The FalconCMS core is open source under the [MIT license](LICENSE). FalconCMS Pro is a
separate commercial package under its own terms.

Developed by **[Tareq Codex](https://github.com/tareqcodex)**
