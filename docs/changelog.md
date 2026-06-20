# Changelog

All notable changes to **FalconCMS** are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — versions are sorted newest first.

---

## v1.3.30 <Badge type="tip" text="Latest" /> {#v1-3-30}

**Released: 2026-06-20**

### Fixed
- **Settings — Demo mode** — `register_url` and `login_url` inputs now properly disabled with a visible warning banner; initial attempt in v1.3.29 was incomplete

---

## v1.3.29 {#v1-3-29}

**Released: 2026-06-20**

### Added
- **Settings — Demo mode** — Initial attempt to disable registration URL and login URL fields when `APP_DEMO=true` (superseded by v1.3.30)

---

## v1.3.28 {#v1-3-28}

**Released: 2026-06-20**

### Fixed
- **Demo credentials** — Corrected email to `admin@falconcms.demo` and password to `FalconDemo2025!` (were set to wrong values in v1.3.26)

### Added
- **Docs — Demo page** — Live demo request page (`/demo`) with lead capture form; credentials sent to visitor's email via EmailJS after form submission

---

## v1.3.27 {#v1-3-27}

**Released: 2026-06-20**

### Fixed
- **Demo credentials box** — Applied to the correct login view (`login-modern.blade.php`); previous version edited the wrong file (`login.blade.php`)

---

## v1.3.26 {#v1-3-26}

**Released: 2026-06-20**

### Added
- **Demo mode — Login page** — Demo credentials box displayed above login form when `APP_DEMO=true`
- **Demo mode — User management** — All fields on user create and user edit pages disabled with warning banner when `APP_DEMO=true`; submit button also disabled

---

## v1.3.25 {#v1-3-25}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — `theme_footer_logo` and `theme_site_logo` rows deleted from `cms_settings` during `falcon:update` so the embedded base64 default logo is always used and never overridden by a stale DB value

---

## v1.3.24 {#v1-3-24}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — Default logo embedded as base64 directly in the footer template; eliminates dependency on file copy or asset publish step during deployment

---

## v1.3.23 {#v1-3-23}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — Logo element moved outside the widget check block so it always renders in column 1 regardless of whether any widgets are active

---

## v1.3.22 {#v1-3-22}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — `syncFooterDefaults()` now copies the logo file directly, independent of `vendor:publish`

---

## v1.3.21 {#v1-3-21}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — Removed `theme_site_logo` fallback from footer logo; `syncFooterDefaults()` called during `falcon:update` to ensure footer defaults are always set

---

## v1.3.20 {#v1-3-20}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — Fixed white/invisible logo by using the correct asset; updated default About text and copyright text; migration added to correct existing DB values

---

## v1.3.19 {#v1-3-19}

**Released: 2026-06-20**

### Fixed
- **`falcon:update`** — Published admin views are now deleted on update instead of being re-published, ensuring the package's vendor views are always served fresh

---

## v1.3.18 {#v1-3-18}

**Released: 2026-06-20**

### Added
- **Customizer — Performance** — "Clear All Cache" button added to Performance section for one-click cache clearing from the admin

---

## v1.3.17 {#v1-3-17}

**Released: 2026-06-20**

### Fixed
- **Footer** — Logo removed from the lower footer bar; logo now only appears in the top footer column 1 widget area

---

## v1.3.16 {#v1-3-16}

**Released: 2026-06-20**

### Fixed
- **Footer logo** — Replaced with the correct `falcon-cms-footer-logo` asset; size increased to `h-12` for better visibility

---

## v1.3.15 {#v1-3-15}

**Released: 2026-06-20**

### Added
- **Footer** — FalconCMS logo added to the footer bottom bar; default copyright text updated

---

## v1.3.14 {#v1-3-14}

**Released: 2026-06-20**

### Fixed
- **Product editor** — Added `x-cloak` to the product data metabox to prevent Alpine.js flash of unstyled content (FOUC) on page load

---

## v1.3.13 {#v1-3-13}

**Released: 2026-06-20**

### Fixed
- **`falcon:update`** — Admin views are now automatically re-published during update to keep the published copies in sync with the package

---

## v1.3.12 {#v1-3-12}

**Released: 2026-06-20**

### Fixed
- **Sale end date** — `sale_ends_at` datetime input minimum value now set using client-side local time instead of server UTC, preventing timezone-related validation errors

---

## v1.3.11 {#v1-3-11}

**Released: 2026-06-20**

### Fixed
- **Product archive** — `archive-product.blade.php` now uses `productCategories` relation instead of the old `taxonomyTerms` call
- **Product archive** — `productCategories` relation eagerly loaded on CPT archive queries (e.g. `/product/`) to prevent N+1 queries

---

## v1.3.10 {#v1-3-10}

**Released: 2026-06-20**

### Fixed
- **Sale end date validation** — Removed `after:now` rule that was incorrectly rejecting valid future dates in some timezone configurations

---

## v1.3.9 {#v1-3-9}

**Released: 2026-06-19**

### Fixed
- **PHP_BINARY** — Resolved to the CLI `php` binary in web (php-fpm) context where `PHP_BINARY` points to the FPM worker
- **Product categories** — Category label display corrected on product cards and single product pages
- **Sale end date** — Validation and countdown timer logic corrected
- **Page Builder** — Fixed offset constants in `parseColumnsFromContent()` that caused incorrect column parsing
- **Shop** — Tab active-state bug fixed; `hold_stock` order cancellation implemented
- **Hooks & helpers** — Remaining `lazy_` CSS classes and helper function references renamed to `falcon_` throughout the package theme source

---

## v1.3.4 {#v1-3-4}

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
- Complete rebrand from **FalconCMS** to **FalconCMS** — namespaces, command names (`falcon:install`), config keys, view namespaces, and all public-facing strings updated

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
