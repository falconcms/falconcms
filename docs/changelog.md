# Changelog

All notable changes to **FalconCMS** are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — versions are sorted newest first.

---

## v1.7.4 <Badge type="tip" text="Latest" /> {#v1-7-4}

**Released: 2026-06-30**

A portability & accounts release: import/export for forms and builder-library items, an email-verification toggle, and a much smarter media backup/restore. Now officially runs on **Laravel 13** as well.

### Added
- **Form import/export** — every form can be exported to a portable `.json` file (structure + settings) and imported on any FalconCMS site as a new form, straight from the Forms list
- **Post Card & Mega Menu import/export** — builder-library items export to `.json` and import back as new items (with fresh IDs), so designs move easily between sites
- **"Require email verification" toggle** — *Settings → Membership* now lets the site owner choose whether new users must verify their email before signing in, or are logged in immediately after registering

### Changed
- **Smarter media backup & restore** — a *media-only* backup now bundles the Media Library records too, so restoring brings the library entries back (not just the files); restore also **auto-detects and strips a wrapper folder** (e.g. when a downloaded backup was unzipped and re-zipped), and preserves the full `Year/Month` folder structure
- **Deleting a media item now removes its generated size variants** too (e.g. `image-300x200.jpg`), matching WordPress — files that are tracked as their own media item are left alone
- **Honest registration feedback** — if the verification email can't be sent, registration now says so plainly instead of falsely claiming a link was sent
- **Laravel 13 support** — added to the documented requirements (Laravel 10, 11, 12, or 13)

### Fixed
- Media-only restore now places files at their correct paths (including the `media/` sub-folder) instead of flattening them

## v1.7.3 {#v1-7-3}

**Released: 2026-06-30**

A tooling & migration release: a new Export/Import pair, one-click Clone for every content type, a smarter Backup tool, plus several builder, security and migration fixes.

### Added
- **Tools → Export** — a feature-driven export screen: it lists every registered post type, taxonomy and the media library automatically (so future exportable features appear on their own) and downloads a WordPress-compatible `.xml` (WXR) file. Pick **All content** or a single source
- **Tools → Import** — the counterpart to Export: upload an export `.xml` and it restores posts, pages, custom post types and taxonomy terms (also accepts standard WordPress WXR files)
- **Clone for posts, pages, products & CPTs** — every list row now has a **Clone** action (next to View) that duplicates the item — and its taxonomies, custom fields and (for products) shop data, variations & downloads — into a fresh draft
- **Duplicate menu** — the menu editor gains a **Duplicate Menu** action that copies a navigation menu with its full item hierarchy (the copy is never auto-assigned as header/footer)

### Changed
- **Backup tool reworked** — one **Create Backup** button with three choices: **Only Database**, **Only Media**, or **Database + Media** (a single archive carrying both). Restore is now content-aware — it detects from the file itself whether to restore the database, the media, or both, so a whole site can be moved to another install with one file. Uploaded backups are detected the same way
- **Live gallery hover effect on the canvas** — the Gallery element's *Zoom* hover now previews in the builder, matching the front end
- **Column / nested-column hover effects now work on the front end** — *Zoom / Lift / Glow / Fade* hover types rendered only in the builder before; they now render on the published page too
- **Buttons without a link render cleanly** — a Button with an empty **Link URL** no longer outputs an empty `<a>` tag or a pointer cursor; any value (including `#`) makes it a real link again
- **Clearer import results** — re-importing items that already exist now reports them as *skipped* (including taxonomy terms) with an explanatory note, instead of showing all zeros

### Fixed
- **Stored-XSS hardening** — classic (non-builder) post/page content is now sanitised on output (scripts, `on*` handlers and `javascript:` URLs are stripped), closing a gap that affected imported HTML content
- **Menu save error** — saving a menu no longer fails with *Unknown column `mega_menu_id`*; the relevant migration now runs, and several migrations were made **idempotent** (guarded with `hasTable`/`hasColumn`) so `php artisan migrate` runs cleanly on fresh, partially-migrated or already-migrated installs — **without any data loss**
- **Menu selector** — choosing a menu from the dropdown no longer auto-opens it; the **Select** button is the trigger
- **Nested-row layout consistency** on the builder canvas

## v1.7.2 {#v1-7-2}

**Released: 2026-06-27**

A builder polish & tooling release: every Font Awesome free icon in the icon pickers, a media-files backup option, plus several nested-layout and colour-picker fixes that make the builder canvas match the front end.

### Added
- **Media files backup** — *Tools → Backup* now has a **Backup Media Files** button that zips everything under `storage/app/public` (uploads, generated images, etc.) into a downloadable archive, and restores media archives back into place. Database snapshots are unchanged
- **Every Font Awesome free icon in the builder** — the icon pickers (Icon Box, Button icon, Icon List and custom icon fields) now list the full Font Awesome 6 free set — **2,060 icons** across Solid, Regular and Brands (up from a few hundred). A search box reaches any icon, with the grid capped for snappy scrolling

### Changed
- **Icon Box font size accepts any CSS unit** — the Title and Description **Font Size** fields lost their `px`/`rem` dropdown and now take a free-form value (`px`, `rem`, `em`, `%`, `vw`, `vh`, `calc()`), matching the Title element
- **Nested-column Border & Box-Shadow colour pickers unified** — they now use the same round-swatch + editable hex design as every other picker, show the opacity-aware `#RRGGBBAA` code, and the colour renders **with its opacity** on canvas and front end (responsive per-device)

### Fixed
- **Nested rows match the front end on the canvas** — a nested row no longer shows a permanent whitish box; it renders transparent (like a normal element) and reveals its outline + **ROW** badge only on hover, with no extra padding gap between the row and its parent
- **No more phantom vertical gap inside nested columns** — columns with **default** alignment no longer stretch their inner content to a taller sibling's height in the builder, so spacing inside nested columns now looks exactly like the published page

## v1.7.1 {#v1-7-1}

**Released: 2026-06-26**

### Changed
- **Analytics "Page" column shows the site domain for homepage visits** — homepage hits (including bots that reach the site by raw IP) now display the configured site domain (e.g. `demo.example.com`) instead of a bare `/`. Other pages still show their request path

## v1.7.0 {#v1-7-0}

**Released: 2026-06-26**

A builder & design-tooling release: one unified colour picker everywhere, responsive background **hover** colours, full CSS-unit support for font sizes, plus several builder and analytics fixes.

### Added
- **Background Hover Color for Containers, Columns & Nested Columns** — a new **responsive** hover colour (separate desktop / tablet / mobile values). It previews live on hover in the builder canvas and renders as a real `:hover` rule (with media queries) on the front end
- **Custom Text Color for Buttons** — when *Button Style → Custom* is selected, a dedicated text-colour picker sits with the gradient colours; the default style keeps its own text colour

### Changed
- **One unified colour picker across the whole CMS** — the main builder, the mega-menu & post-card builders, the Theme Customizer and the Form builder now share a single clean picker: a round swatch, an editable hex field, and a compact popup (saturation square + hue + alpha sliders) with the **alpha bar tinted to the current colour**. The Form-builder picker also gained an **opacity slider**
- **Opacity-aware colour fields** — fields that store opacity separately now show the full 8-digit `#RRGGBBAA` code, and the alpha slider opens at the correct position
- **CSS units for every font size** — all typography / font-size inputs across builder elements (and inside header / footer / nested layouts) now accept `px`, `rem`, `em`, `%`, `vw`, `vh` and `calc()`. Values apply on both the canvas and the front end and survive the shortcode round-trip
- **Title element typography** now mirrors the Text Block (font family, weight, size, line-height, letter-spacing, transform) for a consistent editing experience

### Fixed
- **Column / nested-column background colour ignored responsive values** — tablet/mobile background colours were rendered with the desktop value on both the canvas and the front end; per-device colours are now honoured
- **A per-device colour was discarded when switching device** — picking a tablet/mobile colour and then toggling the device preview reverted it; the colour is now committed instead of reverted
- **Analytics "Page" column showed the raw server IP** — visitors who reached the site directly by IP (bots/scanners) appeared as `https://<ip>`; the column now shows a clean request path
- **Documentation** clarifies that **MySQL / MariaDB** are the only fully supported databases (SQLite is partial and not recommended; PostgreSQL / SQL Server are unsupported)

## v1.6.3 {#v1-6-3}

**Released: 2026-06-25**

### Fixed
- **Reinstalling over an existing database failed with "table already exists"** — Running `falcon:install` (or `falcon:update`) on top of a database that still had some tables — after `falcon:uninstall-db`, or when installing onto an existing Laravel app whose `users` / `cache` / `jobs` tables remained — made `migrate` try to recreate them and abort. The commands now **reconcile first**: any migration whose created tables already exist is recorded as run, so `migrate` skips it and only creates what is genuinely missing. This works for both the app's own and the package's migrations, without editing any migration file

## v1.6.2 {#v1-6-2}

**Released: 2026-06-24**

### Fixed
- **Uninstall could leave a broken `User` model** — `falcon:uninstall` removed the `HasCmsPermissions` import but only stripped a *standalone* `use HasCmsPermissions;` line. When the trait was declared in a combined list (e.g. `use HasFactory, Notifiable, HasCmsPermissions;`), the reference was left without its import, crashing the app — and any later reinstall's migrations — with *"Trait App\Models\HasCmsPermissions not found"*. The revert now also removes the trait from a combined `use` list (verified valid for leading/middle/trailing positions)

## v1.6.1 {#v1-6-1}

**Released: 2026-06-24**

### Fixed
- **Dashboard showed a stale "Installed Version"** — After updating, the dashboard kept showing an old installed version (e.g. v1.4.2) even though the new code was in place. The version check preferred Composer's reported version, which can be a pinned alias (notably on path-repository installs) and lags behind. It now reads the version from the package's `version.json` first (bumped on every release), so the dashboard reflects the version actually installed

## v1.6.0 {#v1-6-0}

**Released: 2026-06-24**

A consolidation milestone that brings together everything shipped across the 1.5.x line.

### Highlights
- **Dashboard** — Redesigned e-commerce KPI cards with month-over-month trend deltas; **Top Selling Products**, **Low Stock** and **Recent Orders** widgets; and an interactive **Orders by Country** world map (zoom, pan and per-country hover). The whole e-commerce section is now gated behind the `access_shop` permission
- **Analytics** — A **Visitors by Country** world map, a named **Traffic Sources** breakdown (Google, Facebook, Instagram, YouTube, … Direct, and other sites), and hover tooltips on the real-time active-users sparkline
- **Shop** — The **Conversion Funnel** (visitors → product → cart → checkout → orders) now lives on the Shop Overview
- **Security & reliability** — Internal AJAX fragment endpoints redirect on direct visits instead of leaking raw JSON; reliable geolocation via the shared `falcon_geoip()` helper; a richer IP blacklist (location, ISP, first/last seen)
- **Lifecycle** — New `falcon:uninstall` (full, leaves the app booting cleanly) and `falcon:uninstall-db` (database-only) commands, plus idempotent core migrations so a reinstall always succeeds

For the granular history of these changes, see the 1.5.x entries below.

## v1.5.10 {#v1-5-10}

**Released: 2026-06-24**

### Fixed
- **App crashed after a database reset** — The redirect middleware queried `cms_redirects` on every request and returned a 500 (*"Base table or view not found"*) once the tables were dropped (e.g. after `falcon:uninstall-db`). It now checks the table exists first and degrades gracefully when it doesn't
- **Reinstall failed with "table already exists"** — Uninstall keeps the shared Laravel tables (`users`, `sessions`, `cache`, `jobs`) but clears the migration records, so re-running `falcon:install` / `migrate` tried to recreate them and failed with *"Table 'users' already exists"*. The bundled `users` / `cache` / `jobs` migrations are now idempotent (`Schema::hasTable` guards), so a reinstall succeeds no matter which tables remain

## v1.5.9 {#v1-5-9}

**Released: 2026-06-24**

### Fixed
- **Uninstall left a stale provider cache** — `falcon:uninstall` removes the package with `composer remove --no-scripts`, which doesn't regenerate Laravel's package-discovery cache. `bootstrap/cache/packages.php` / `services.php` therefore still referenced `FalconCmsServiceProvider`, so the app booted with *"Class FalconCms\Core\FalconCmsServiceProvider not found"*. The command now clears those bootstrap caches as its final step. (If you hit this after a manual `composer remove`, delete `bootstrap/cache/packages.php` and `bootstrap/cache/services.php`, then run `composer dump-autoload`.)

## v1.5.8 {#v1-5-8}

**Released: 2026-06-24**

### Fixed
- **Uninstall left a broken `User` model** — The earlier uninstall dropped tables and files but did not remove the `HasCmsPermissions` trait the installer added to `App\Models\User`, so after `composer remove` the app crashed with *"Trait FalconCms\Core\Traits\HasCmsPermissions not found"*. The full uninstall now reverts that automatically

### Added
- **`falcon:uninstall` — full removal (no leftovers)** — Reverts the trait/import added to `App\Models\User`, drops tables + migration records, removes published files, and runs `composer remove falconcms/falconcms` — leaving the app booting cleanly. Options: `--all` (also drop shared Laravel tables), `--force`, `--keep-files`, `--no-composer`
- **`falcon:uninstall-db` — database-only removal** — Drops just the FalconCMS tables (and migration records); the package code, files and User model trait stay in place (e.g. to wipe data and re-`migrate`). Options: `--all`, `--force`

## v1.5.7 {#v1-5-7}

**Released: 2026-06-24**

### Added
- **`falcon:uninstall` command** — Cleanly removes FalconCMS: drops its database tables, deletes its rows from the `migrations` table (so a later reinstall re-runs cleanly), and removes published views, themes and assets. Shared Laravel tables (`users`, `sessions`, `cache`, `jobs`, …) are **kept by default** to avoid breaking the host app; `--all` drops them too for a full wipe. Options: `--force` (skip the confirmation), `--all`, `--keep-files`. Finish with `composer remove falconcms/falconcms`

## v1.5.6 {#v1-5-6}

**Released: 2026-06-24**

### Fixed
- **IP Blacklist — country always "Unknown"** — Blocked IPs were geo-resolved with `file_get_contents`, which is disabled or blocked on many production hosts, so the country never resolved. A new shared `falcon_geoip()` helper now uses the Laravel HTTP client (with a timeout, cached 30 days); existing "Unknown" rows are backfilled when the blacklist page is viewed

### Added
- **IP Blacklist — richer detail** — The blacklist table now shows **Location** (country + city/region), **ISP / network**, and both **First Blocked** and **Last Attempt** times, with an attempts badge. New blocks capture city, region and ISP

### Changed
- **Geo lookups unified** — Visit tracking and the IP blacklist now share the same cached `falcon_geoip()` helper instead of separate, less reliable lookups

## v1.5.5 {#v1-5-5}

**Released: 2026-06-24**

### Security
- **AJAX fragment endpoints no longer expose raw output on direct visit** — `GET /cart/fragment` (mini-cart) and `GET /search/live` are internal AJAX-only endpoints; opening them directly in a browser previously returned their raw JSON. Non-AJAX (direct navigation) requests are now redirected to the cart and search pages respectively, so the raw payloads are never shown. JS-driven calls (which send `X-Requested-With`) are unaffected, and only ever returned the visitor's own session data anyway

## v1.5.4 {#v1-5-4}

**Released: 2026-06-24**

### Added
- **Dashboard — Orders by Country map** — An interactive world map highlighting the countries orders came from (shaded by volume), with zoom buttons, mouse-wheel zoom, drag-to-pan and per-country hover (country name + order count), plus a top-countries list. Country values are normalized to ISO-2 from mixed order data
- **Dashboard — Top Selling Products, Low Stock & Recent Orders** — The redundant "Quick Stats" panel is replaced by a best-sellers list (units sold + revenue) and a low-stock alert list; a Recent Orders table now fills the space under the revenue chart
- **Analytics — Visitors by Country map** — The same interactive world map for geo-located visits
- **Analytics — Traffic Sources** — A named-source breakdown (Google, Bing, Facebook, Instagram, YouTube, X, LinkedIn, TikTok, … Direct, and any other site by domain) with visit counts, percentages and favicons
- **Analytics — Real-time sparkline tooltip** — Hovering the real-time active-users bars now shows the visitor count for that minute

### Changed
- **Dashboard — E-commerce KPI cards redesigned** — Accent strip, soft-tint icon, month-over-month trend delta (↑/↓ %) and a contextual subtext per card
- **Dashboard — Shop section permission-gated** — The whole e-commerce section (revenue, orders, customer names) now requires the `access_shop` permission (admins bypass), so it is no longer shown to every dashboard-accessing role
- **Shop → Overview — Conversion Funnel** — The conversion funnel (visitors → product → cart → checkout → orders) and conversion rate now live on the Shop Overview, moved from Analytics to keep shop metrics together

## v1.5.3 {#v1-5-3}

**Released: 2026-06-23**

### Fixed
- **Registration — default role ignored** — Self-registration now assigns the role configured in **Settings → New User Default Role** instead of always using *subscriber*. Selecting a different role (e.g. Editor, Author) now correctly applies it to newly registered users; if the configured role is missing it safely falls back to subscriber

## v1.5.2 {#v1-5-2}

**Released: 2026-06-23**

### Changed
- **Device limit — simpler on/off model** — The multi-device setting is now a clear cap. **Unchecked = unlimited** devices; **checked = limit** concurrent logins to *Max devices allowed*. The `-1` "unlimited" sentinel has been removed — the field is a plain positive number again (minimum 1), and the checkbox/help text now reflect the inverted meaning

## v1.5.1 {#v1-5-1}

**Released: 2026-06-23**

### Fixed
- **Multi-device login — unlimited (`-1`)** — Setting **Max devices allowed** to `-1` now correctly means unlimited concurrent sessions and never blocks sign-in. Previously the limit check (`active sessions ≥ -1`) was always true, so logging in from a second device failed with *"Login denied: Only one active session is allowed per account."* The `-1` sentinel now applies regardless of the multi-device toggle; normal numeric limits and the single-session default are unchanged

### Changed
- **Settings — Max devices allowed** — The field now accepts `-1` (minimum lowered from `1`) with a helper note that `-1` means unlimited devices

## v1.5.0 {#v1-5-0}

**Released: 2026-06-22**

### Fixed
- **Registration — duplicate username** — Usernames derived from the email local part are now sanitized and made unique (`john@a.com` and `john@b.com` no longer collide → `john`, `john1`…), fixing the duplicate-username error on sign-up
- **Admin user create/edit** — Validation errors are now displayed (a top summary plus per-field messages) instead of a database constraint crash; inputs repopulate on failure, and a success message is shown
- **User update redirect** — Saving a user now returns to the same edit page with the success notice, instead of jumping to the user list

### Added
- **Password strength & match** — The admin user create/edit password fields now show a live strength meter and a password-match indicator, matching the registration page

## v1.4.9 {#v1-4-9}

**Released: 2026-06-22**

### Added
- **Analytics — major overhaul** — Bot/crawler filtering, geo location (country/city with flags), real-time active users with a 30-minute sparkline and live tables, sessions, bounce rate, new vs returning visitors, traffic channels, e-commerce conversion KPIs and a visit→cart→checkout→order funnel, plus donut charts for channels, returning visitors and top countries
- **Analytics — data retention** — New `falcon:prune-analytics` command with a daily schedule and a cron-independent fallback, with a configurable retention window

### Fixed
- **Footer logo** — The default footer logo now uses the white brand logo (the dark logo was invisible on the dark footer) at a larger size, automatically darkened on light footer backgrounds; a custom uploaded logo is always shown as-is

## v1.4.8 {#v1-4-8}

**Released: 2026-06-22**

### Added
- **Registration — email verification** — New sign-ups are no longer logged in immediately; a time-limited (5-minute) signed verification link is emailed instead, and sign-in is blocked until the address is verified. Includes a notice page and a throttled resend flow. A migration marks all existing users as verified so no one is locked out

### Fixed
- **Order status emails** — Customers are now emailed on every order status change (pending, on-hold, processing, completed, delivered, cancelled, refunded, partially-refunded, failed), not just on delivery — for both single and bulk updates

### Changed
- **Product Meta element** — Now available only in post-card mode, like Post Meta and Content

## v1.4.7 {#v1-4-7}

**Released: 2026-06-21**

### Added
- **Builder — Product Meta element** — A new element that displays a product's price (with sale), SKU, availability, stock quantity and type; each field toggleable, with stacked/inline layout, alignment, labels and full design controls
- **Builder — Ticker** — Configurable item spacing, a duplicate-item button, and live scrolling in the builder canvas
- **Dynamic sources — Product group** — Bind any text field to live product data (price, regular/sale price, SKU, stock status, stock quantity); dynamic fields now show a live preview right in the builder using the real value of the post being edited

## v1.4.6 {#v1-4-6}

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
