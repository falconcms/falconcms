# Changelog

All notable changes to **FalconCMS** are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — versions are sorted newest first.

---

## v1.3.4 <Badge type="tip" text="Latest" /> {#v1-3-4}

**Released: 2026-06-16**

### Fixed
- **Theme system** — Themes now resolve exclusively from the app's `resources/views/themes/` directory; vendor path no longer used as a runtime fallback
- **Page Builder shortcodes** — All remaining `[lazy_*]` element outputs converted to `[falcon_*]` (`text_block`, `special_text`, `html`, `icon_box`, `acc_item`, `tab_item`, `icon_list_item`); `parseColumn()` now reads both legacy `[lazy_*]` and new `[falcon_*]` element shortcodes; `unwrapEditorMarkup()` handles `falcon_` tags; sub-element closing tag patterns accept both prefixes for backward compatibility
- **Child theme activation** — Fixed `theme.json` parent reference (`lazy-theme` → `falcon-theme`); added required `index.blade.php` to child theme
- **Header / Footer logo** — Default FalconCMS logo shown when no custom logo is configured; logo served from published asset path `vendor/falcon-cms/images/falcon-cms-logo.png`

### Added
- **`falcon:install` screenshot publishing** — Theme screenshots are automatically copied to `public/themes/{slug}/` so they appear in the Themes panel immediately after installation
- **`theme.json` for Falcon Theme** — Adds proper metadata (name, version, description, author)
- **New theme screenshots** — Updated preview images for `falcon-theme` and `falcon-theme-child`
- **Default logo asset** — `public/assets/images/falcon-cms-logo.png` included in the package and published with `falcon-cms-assets`

---

## v1.3.3 {#v1-3-3}

**Released: 2026-06-15**

### Fixed
- **Dashboard update check** — `version.json` bumped to `1.3.2`; LAZY_CMS_VERSION constant now reflects the installed Packagist version correctly

---

## v1.3.2 {#v1-3-2}

**Released: 2026-06-15**

### Fixed
- Default `login_url` value changed from `admin-login` to `falcon-admin` in both the install command and the migration seeder

---

## v1.3.1 {#v1-3-1}

**Released: 2026-06-15**

### Fixed
- Default `register_url` value corrected to `falcon-registration` in the install command

---

## v1.3.0 {#v1-3-0}

**Released: 2026-06-15**

### Changed
- Complete rebrand from **Lazy CMS** to **FalconCMS** — namespaces, command names (`falcon:install`), config keys, view namespaces, and all public-facing strings updated

---

## v1.0.0 {#v1-0-0}

**Released: 2026-06-15**

Initial public release of **FalconCMS** — a powerful Laravel CMS package with page builder, e-commerce, and a WordPress-like admin dashboard.

### Core
- **WordPress-like Admin Dashboard** — Sidebar navigation, top bar, role-based permissions, activity logs
- **Page Builder (Falcon Builder)** — Drag-and-drop visual editor with rows, columns, and element blocks
- **Post & Page Management** — Custom post types, categories, tags, featured images, SEO fields
- **Media Library** — Upload, manage, and select images/files across the admin
- **User & Role Management** — Granular permission system with custom roles
- **Multi-language Support** — Built-in language management
- **Theme System** — Installable themes with Customizer support (header, footer, colors, typography)
- **Hook Architecture** — WordPress-style `add_lazy_action` / `add_lazy_filter` for extensibility
- **Custom Options Pages** — Register custom settings pages via config

### E-Commerce (Shop)
- **Product Management** — Simple and variable products, SKU, stock, sale price with scheduled expiry (`sale_ends_at`)
- **Digital / Downloadable Products** — Attach files from the media library; secure token-based download links with expiry and download count limits
- **Orders** — Full order lifecycle (pending → processing → shipped → completed), order notes, status history
- **Cart & Checkout** — AJAX cart, coupon codes, shipping zones, tax rules
- **Payments** — Cash on Delivery, Stripe, SSLCommerz integrations
- **Customer Account** — Order history, downloads tab, address management
- **Sales Reports** — Revenue by period (daily/weekly/monthly), top products, customer LTV, CSV export
- **Shop Settings** — Currency, inventory, email notifications, shipping, tax, coupon management

### Admin UI
- **URL-aware Settings Tabs** — Tab switches update the browser URL via `history.replaceState`
- **Sidebar Collapse** — "Collapse Menu" button; icon-only mode persisted in `localStorage` with no flash on navigation
- **FalconCMS Branding** — FCM logo in admin top bar

### Security
- **HTTP Security Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP, HSTS
- **Rate Limiting** — Login, forgot-password, comments, cart operations, file downloads
- **CSRF Protection** — All state-changing routes protected
- **Input Validation** — Cart quantities, file uploads, comment length, user enumeration prevention
- **Secure Downloads** — Token-based file delivery; tokens expire and have per-user download limits

### Developer Tools
- **Artisan Commands** — `lazy:expire-sales` (scheduled sale price cleanup)
- **REST API** — Configurable API key authentication
- **Backup & Snapshots** — Database and file backup tools
- **WordPress Import** — Import posts from a WordPress XML export
- **Analytics Dashboard** — Basic traffic and content stats
- **Maintenance Mode** — Toggle from Customizer with custom message and countdown timer
