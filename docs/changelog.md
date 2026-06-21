# Changelog

All notable changes to **FalconCMS** are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — versions are sorted newest first.

---

## v1.4.6 <Badge type="tip" text="Latest" /> {#v1-4-6}

**Released: 2026-06-21**

### Fixed
- **`falcon:update` — stale published view overrides** — Update now removes the entire published `resources/views/vendor/falcon-cms` directory, not just the `admin` subfolder. A leftover published copy of a namespaced package view (e.g. `frontend/builder/column.blade.php`) silently shadows the real vendor view, so layout fixes never appear on the site no matter how many caches are cleared. Clearing the whole override namespace guarantees the package's own views are always used

## v1.4.5 {#v1-4-5}

**Released: 2026-06-21**

### Fixed
- **Dashboard Update — stale frontend after update** — The dashboard "Update" now resets the php-fpm OPcache from the web request itself. Previously the `falcon:update` subprocess ran under CLI php, whose `opcache_reset()` only clears the CLI OPcache — the php-fpm workers that serve frontend pages kept executing the old compiled Blade views, so builder/layout fixes did not appear on the live site until a manual container restart
- **Dashboard footer** — Default admin footer credit changed to "Theme developed by Falcon CMS"

### Changed
- **Taxonomy screens** — Removed the non-functional "Screen Options" and "Help" buttons from the top-right of all taxonomy list pages (Categories, Tags, Product Categories, Product Tags, and custom ACPT taxonomy terms)

## v1.4.4 {#v1-4-4}

**Released: 2026-06-21**

### Fixed
- **Dashboard Update — php-fpm binary** — The dashboard "Update" button ran `falcon:update` with `PHP_BINARY`, which in a web (php-fpm) request points at the php-fpm executable and cannot run `artisan` (it printed FastCGI usage and aborted, so migrations/cache-clear/OPcache reset never ran). The updater now locates a real CLI php binary, checking absolute paths first since the php-fpm worker often runs with a stripped `PATH`

## v1.4.3 {#v1-4-3}

**Released: 2026-06-21**

### Fixed
- **Page Builder — Preview mode blank canvas** — Toggling the builder's eye-icon Preview no longer blanks the whole canvas. In preview the canvas kept `grid-area: auto`, which auto-placed it into the now-hidden sidebar's 0-width grid column; it is now pinned to its named `canvas` area so the design stays visible

## v1.4.2 {#v1-4-2}

**Released: 2026-06-21**

### Fixed
- **Page Builder — Row content layout** — Elements inside a column with Content Layout set to "Row" now stay side-by-side and never wrap to the next line (`flex-wrap: nowrap`); previously `flex-wrap: wrap` caused elements to stack when they did not fit
- **`falcon:update` — OPcache** — OPcache is now reset after cache clearing so freshly compiled Blade views are served immediately without requiring a server restart

---

## v1.4.1 {#v1-4-1}

**Released: 2026-06-21**

### Fixed
- **Multi-device login** — `-1` value for Max Devices now correctly means unlimited; previously `count() >= -1` was always true, blocking all logins even when unlimited was intended
- **Page Builder — Nested Column row layout** — Elements inside a column with Content Layout set to "Row" now render side-by-side on the frontend; previously `width: 100%` on element wrappers caused items to stack vertically despite `flex-direction: row`

---

## v1.4.0 {#v1-4-0}

**Released: 2026-06-20**

### Added
- **Demo mode — Login page** — Demo credentials box displayed above login form when `APP_DEMO=true`
- **Demo mode — User management** — All fields on user create and user edit pages disabled with warning banner when `APP_DEMO=true`
- **Demo mode — Settings** — `register_url` and `login_url` inputs disabled with warning banner when `APP_DEMO=true`
- **Docs — Demo page** — Live demo request page with lead capture form; credentials sent to visitor's email via EmailJS after form submission

### Fixed
- **Footer logo** — Default FalconCMS logo always shown in footer column 1; embedded as base64 to remove file dependency; `theme_footer_logo` / `theme_site_logo` cleared from DB on `falcon:update` so stale overrides are never applied
- **`falcon:update`** — Published admin views deleted on update so vendor views are always served fresh (no stale published copies)

---

## v1.3.18 {#v1-3-18}

**Released: 2026-06-20**

### Added
- **Customizer — Performance** — "Clear All Cache" button for one-click cache clearing from the admin

### Fixed
- **Product editor** — Added `x-cloak` to product data metabox to prevent Alpine.js FOUC on page load
- **`falcon:update`** — Admin views automatically re-published during update to keep published copies in sync
- **Sale end date** — `sale_ends_at` datetime input minimum set using client-side local time instead of server UTC
- **Product archive** — `productCategories` relation used in `archive-product.blade.php`; eager loaded on CPT archive queries to prevent N+1
- **Sale end date validation** — Removed `after:now` rule causing false rejections in some timezones
- **PHP_BINARY** — Correctly resolved to CLI `php` in web (php-fpm) context
- **Product categories** — Category label display corrected on product cards and single product pages
- **Shop** — Tab active-state bug fixed; `hold_stock` order cancellation implemented
- **Page Builder** — Fixed offset constants in `parseColumnsFromContent()`
- **Hooks & helpers** — Remaining `lazy_` class and helper references renamed to `falcon_`

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
