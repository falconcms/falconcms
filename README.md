<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/falconcms/falconcms/main/public/assets/images/falcon-cms-white-logo.png">
    <img alt="FalconCMS" height="64" src="https://raw.githubusercontent.com/falconcms/falconcms/main/public/assets/images/falcon-cms-logo.png">
  </picture>
</p>

<h1 align="center">FalconCMS</h1>

<p align="center">
  A WordPress-like CMS for Laravel — drag-and-drop page builder, full e-commerce,<br>
  multi-language content, themes, plugins, and a WordPress-style hook system.
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

## 🚀 Installation

To install the package in a fresh Laravel project:

1. **Require the package via Composer:**
   ```bash
   composer require falconcms/falconcms
   ```

2. **Run the Automated Installation:**
   ```bash
   php artisan falcon:install
   ```
   *This command handles migrations, asset publishing, theme distribution, storage linking, and default admin creation.*

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

## 🔐 Role-Based Access Control (RBAC)

FalconCMS includes a granular permission system with several predefined roles:

- **Administrator**: Full access to all settings, content, and system configurations.
- **Editor**: Can publish and manage all posts and pages, access media library, and moderate comments.
- **Author**: Can publish and manage **only their own** posts.
- **Contributor**: Can write and manage their own posts but **cannot** publish them (pending review).
- **Subscriber**: Access to their own profile and basic dashboard view.
- **User**: Custom role with access to Posts, Pages, Media, Comments, and Language Tools.

> **Note:** Content ownership is strictly enforced. Authors and Contributors are isolated from other users' content.

---

## 🎨 Theme Development & Isolation

### 📂 Strict Theme Structure
Frontend views **MUST** be located in `resources/views/themes/{theme-name}/`.
For security and organization, any view file created directly in the root `resources/views/` folder will be blocked from frontend rendering (returns a 404).

### 🪄 Automated Theme Sync
When you update the package, your themes are automatically refreshed. To ensure this works, add the following to your `composer.json` scripts:
```json
"post-autoload-dump": [
    "@php artisan vendor:publish --tag=falcon-cms-themes --force"
]
```

---

## 🌐 Multi-Language Support

Falcon CMS supports dynamic localization. You can enable or disable multi-language support from the Admin Settings.

- **Clean URLs:** When multi-language is disabled, URLs are clean (e.g., `/my-post`).
- **ISO Prefixes:** When enabled, URLs include the language code (e.g., `/en/my-post`).
- **Dynamic Admin UI:** The language selection metabox automatically hides when multi-language is disabled.

---

## 🛠 Features

- **Consolidated Migrations:** Optimized database structure.
- **Dynamic Post Types (CPT):** Create custom post types from the dashboard.
- **Advanced Hook System:** WordPress-like Action and Filter hooks.
- **Headless Mode:** Full REST API support for decoupled apps.
- **Theme Isolation:** High-security frontend view resolution.
- **Falcon Builder:** Drag-and-drop page builder with global header/footer sections.
- **E-Commerce:** Built-in shop, orders, product management, and checkout flow.

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

---

## 🧪 Testing

The suite lives in this repository under `tests/` and runs on
[Testbench](https://packages.tools/testbench), which boots a throwaway Laravel application
around the package. Every migration is applied to an in-memory SQLite database, so the
tests exercise the real service provider, the real schema and the real helper API without
needing a host site — and without any possibility of touching real data.

Requirements: the `pdo_sqlite` PHP extension must be enabled (`extension=pdo_sqlite` in `php.ini`).

```bash
composer install     # first time only
composer test        # run everything

# a single class, or a single test
./vendor/bin/phpunit --filter StockStatusTest
./vendor/bin/phpunit --filter test_an_expired_sale_disappears_from_every_surface_at_once
```

Current coverage — deliberately concentrated on the money and the parsing of untrusted input:

| File | Guards |
|------|--------|
| `Shop/StockStatusTest` | the badge, the archive filter and the shelf never disagree — variations, backorders, thresholds |
| `Shop/PricingTest` | expired sales vanish from every surface but survive for the admin form; variable products show a range, not ৳0.00 |
| `Shop/TaxTest` | how prices were entered vs how they are displayed, rate matching, per-product tax status, taxed shipping |
| `Shop/CouponTest` | every rule between a coupon and giving stock away: expiry, minimum spend, usage caps, restrictions, stacking |
| `Shop/OrderTotalTest` | how the parts compose, and the cart, the order row and the gateway amount all agreeing |
| `Shop/CheckoutTest` | end to end through the real route: the order written, stock taken, coupon spent, nothing half-done |
| `Shop/CartPriceRefreshTest` | a cart left open is reconciled against the database before checkout totals anything |
| `Shop/ShippingWeightTest` | weight bands, fractional weights, and a malformed rule falling back to the base cost instead of shipping free |
| `Shop/StockClaimTest` | the last-unit race, and a partly-claimed cart putting everything back |
| `Shop/ArchiveFilterTest` | filters do what was asked, and a hand-edited query string cannot crash or steer them |
| `Shop/ProductAttributeIndexTest` | the derived attribute index matches what can actually be bought; slug collisions stay distinct |
| `Shop/CustomerAddressTest` | defaults, checkout pre-fill, and one customer never reaching another's address |
| `Shop/LinkedProductsTest` | upsells/cross-sells survive their targets being unpublished or deleted; schema.org output |
| `Security/AdminAccessTest` | who reaches the dashboard and what of it — the default is deny |
| `Security/PostActionGuardTest` | the row-level post actions AdminMiddleware hands off to the controller |
| `Security/AccessControlTest` | open redirects, API tokens, magic links — written from the attacker's side |
| `Security/MediaUploadTest` | what the CMS will accept onto its own disk |
| `Security/MediaImportTest` | the second door into the media library, held to the same rule |
| `Security/DigitalDownloadTest` | paid files: token scope, expiry, per-file cap, no path escaping storage |
| `Cms/BuilderShortcodeConverterTest` | JSON ↔ shortcode round-trip stays lossless & readable (no base64) |
| `Cms/ScheduleStatusTest` | "schedule only on a future time" status logic |
| `Cms/PublishTimezoneTest` | publish date is interpreted in the CMS timezone and stored as UTC |
| `Cms/SchedulePublishFlowTest` | due posts auto-publish; scheduled posts stay hidden until live |
| `Cms/WordPressImporterTest` | WXR parsing, and re-importing the same file creating nothing twice |
| `MigrationsTest` | the install path — every table and column the shop logic reads |

The suite is checked in reverse order as well as forwards, so nothing in it depends on
the order tests happen to run in.

Green ✓ = safe; red ⨯ = something regressed (the diff shows expected vs actual).

---

## 🧹 Code style & static analysis

The package ships with [Laravel Pint](https://laravel.com/docs/pint) and
[PHPStan/Larastan](https://github.com/larastan/larastan). Both run on every push and pull
request (see [`.github/workflows/ci.yml`](.github/workflows/ci.yml)), so a contribution only
needs these three commands locally:

```bash
composer install     # dev dependencies (Pint, Larastan, Testbench)

composer format      # apply the Laravel code style
composer lint        # check the style without changing anything — what CI runs
composer analyse     # PHPStan level 3
```

`phpstan-baseline.neon` records the errors that already existed when analysis was switched on.
New code is expected to be clean: add to the baseline only with a reason, never to silence a
genuine bug.

---

## Requirements

- PHP 8.3+
- Laravel 13+
- MySQL 5.7+ / MariaDB 10.3+

---

Developed by **[Tareq Codex](https://github.com/tareqcodex)**
