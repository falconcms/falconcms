# FalconCMS

[![Latest Version on Packagist](https://img.shields.io/packagist/v/falconcms/falconcms.svg)](https://packagist.org/packages/falconcms/falconcms)
[![CI](https://github.com/falconcms/falconcms/actions/workflows/ci.yml/badge.svg)](https://github.com/falconcms/falconcms/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/falconcms/falconcms)](LICENSE)

A powerful, modular, and easy-to-use CMS package for Laravel applications with built-in multi-language support, robust Role-Based Access Control (RBAC), a drag-and-drop page builder (Falcon Builder), and a WordPress-like theme & hook system.

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
| `Shop/CartPriceRefreshTest` | a cart left open is reconciled against the database before checkout totals anything |
| `Shop/ShippingWeightTest` | weight bands, fractional weights, and a malformed rule falling back to the base cost instead of shipping free |
| `Shop/ArchiveFilterTest` | filters do what was asked, and a hand-edited query string cannot crash or steer them |
| `Shop/ProductAttributeIndexTest` | the derived attribute index matches what can actually be bought; slug collisions stay distinct |
| `Shop/CustomerAddressTest` | defaults, checkout pre-fill, and one customer never reaching another's address |
| `Shop/LinkedProductsTest` | upsells/cross-sells survive their targets being unpublished or deleted; schema.org output |
| `Cms/BuilderShortcodeConverterTest` | JSON ↔ shortcode round-trip stays lossless & readable (no base64) |
| `Cms/ScheduleStatusTest` | "schedule only on a future time" status logic |
| `Cms/PublishTimezoneTest` | publish date is interpreted in the CMS timezone and stored as UTC |
| `Cms/SchedulePublishFlowTest` | due posts auto-publish; scheduled posts stay hidden until live |
| `Cms/WordPressImporterTest` | WXR parsing, and re-importing the same file creating nothing twice |
| `MigrationsTest` | the install path — every table and column the shop logic reads |

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

- PHP 8.1+
- Laravel 10, 11, 12, or 13
- MySQL 5.7+ / MariaDB 10.3+ / SQLite 3.x

---

Developed by **[Tareq Codex](https://github.com/tareqcodex)**
