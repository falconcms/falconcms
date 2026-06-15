# Changelog

All notable changes to **Lazy CMS Builder** are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — versions are sorted newest first.

---

## v1.1.0 <Badge type="tip" text="Latest" /> {#v1-1-0}

**Released: 2026-06-15**

### Security
- **HTTP Security Headers** — `SecurityHeadersMiddleware` added globally: `X-Content-Type-Options`, `X-Frame-Options` (SAMEORIGIN), `Referrer-Policy`, `Permissions-Policy`, and `Content-Security-Policy`
- **Rate Limiting** — Login & forgot-password throttled to 5 req/min; comment submission to 3 req/min; general frontend to 120 req/min
- **User Enumeration Prevention** — Forgot-password endpoint now returns an identical generic message whether the email exists or not, closing an account-probing attack vector
- **Nullable Password on User Update** — Editing a user profile no longer requires a new password; existing hash is preserved when the field is left blank
- **ZIP Path Traversal Protection** — Theme upload validates all ZIP entries via `basename()` before extraction
- **MIME Validation** — Post/page featured image uploads are checked against an explicit allowlist (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`)
- **Builder Permission Checks** — `LazyBuilderController` enforces `manage_pages` / `manage_posts` permissions on all save, clone, and delete operations
- **Comment Max Length** — Frontend comment body capped at 3,000 characters server-side

### Added
- **Maintenance Mode** — Toggle from Customizer → Performance. Shows a branded maintenance page to public visitors; logged-in admins always bypass. Supports custom message and countdown timer (`maintenance_coming_soon_date`)
- **Header Side Padding** — New Customizer → Header option (`theme_header_side_padding`, default `0px`) aligns header/footer containers with full-width builder sections. Mobile fallback: `max(16px, setting)` prevents logo touching viewport edge
- **Search Highlight** — Matched keyword is highlighted on archive/search result pages
- **Documentation: Security section** — New section covering all hardening features, maintenance mode, and the header padding option

### Changed
- `LAZY_CMS_VERSION` bumped to `1.1.0`
- `version.json` introduced as single source of truth for versioning (PHP, VitePress, and Stats component all read from it automatically)

---

## v1.0.9 {#v1-0-9}

**Released: 2026-06-13**

### Added
- **Ticker: Enter-Exit-Pause Animation** — Text scrolls in from one side, exits the other, ticker pauses 1.5 s, then repeats (cleaner than the continuous loop)
- **Ticker: Label Badge Animation System** — Choose from Blink Dot, Pulse, Flash, Shake, Bounce, or None for the label badge
- **Ticker: Separator — Dancing Dots** — Three animated dots with staggered bounce added to the separator options
- **Ticker: Text Effects** — Glow (text-shadow) and Highlight (semi-transparent item background) effects
- **Ticker: Canvas Two-Copy Loop** — Builder canvas uses a seamless two-copy loop so text is always visible while editing

### Fixed
- Builder canvas element wrapper height inflation (flex + `font-size: 0` on column wrapper)
- Heading `h2` default margin appearing in canvas preview
- Style element visibility causing extra height in canvas (`.canvas-container style` set to `display:none`)
- Ticker speed capped at 15 s in canvas for fast editing preview

---

## v1.0.8 {#v1-0-8}

**Released: 2026-06-13**

### Performance
- **Settings Bulk-Load** — `get_cms_option()` previously fired 2 DB queries per call; a page rendering 15–20 options triggered 30–40 extra queries per request. A shared in-memory store now loads all settings in **one query** on first access. Subsequent calls are served from memory. `update_cms_option()` also writes back to the store so same-request reads are immediately consistent

### Added
- GitHub Pages CI/CD workflow (`docs.yml`) — VitePress docs auto-build and deploy on every push to `main`
- Hero SVG illustration on the docs homepage
- Live stats section on docs homepage (Packagist downloads, GitHub stars, latest version)

### Docs
- Builder overview rewritten with accurate element list (22 total) and full usage guide

---

## v1.0.7 {#v1-0-7}

**Released: 2026-06-12**

### Added
- **Mega Menu Builder** — Visual drag-and-drop mega menus with multi-column layouts, images, headings, and icon lists. Drops centered to site width with no CSS required
- **Image Element: Dynamic Sources** — Featured image, post thumbnail, and custom URL can be bound dynamically in the builder
- **SEO Improvements** — Better OpenGraph and meta-description handling on archive and single pages

### Fixed
- Various builder stability fixes for column resizing and element drag-and-drop

---

## v1.0.6 {#v1-0-6}

**Released: 2026-06-12**

### Added
- **Ticker Element** — Full builder element with CSS-only seamless animation. Options: direction (left/right), speed slider, label badge, separator, color pickers (iris picker), typography, height, border-radius. Canvas live preview included
- **Text Widget** — Rich text widget with TinyMCE editor (no image upload), frontend prose rendering
- **Menu: Open in New Tab** — Per-item checkbox in menu admin; works in default header nav and builder-rendered menus

### Fixed
- Widget `is_active` checkbox: unchecked now correctly saves `0`; widget hidden on frontend when inactive
- Image widget: removed unintended zoom-in hover effect when image has a link
- Ticker color fields in Design tab now use the iris color picker (consistent with other color fields)
- Ticker full-width class correctly applied in canvas column wrapper

---

## v1.0.5 {#v1-0-5}

**Released: 2026-06-10**

### Added
- **Builder Preview Mode** — Toggle hides all builder outlines and borders without affecting user-designed borders and box-shadows. `is-preview` class added/removed via JS watch handler for smooth transition
- **Context Menu Redesign** — Dark theme (`#1e1e1e`), per-item icons, improved text contrast
- **Hooks API Documentation** — Full rewrite covering all 104 hooks (58 actions, 46 filters) with examples and a searchable reference table

### Fixed
- Handle bar alignment: removed `+2px` offset so purple and blue handles align flush at zero padding
- Nested column: static left/right border lines added to match top/bottom behaviour
- Nested column overlays: padding overlays shown on active state, not just on drag
- Topbar/sidebar decluttered: removed gear, help, and `+` icons from topbar; removed gear tab from sidebar

---

## v1.0.4 {#v1-0-4}

**Released: 2026-06-09**

### Added
- Full **VitePress documentation site** (`docs/`) covering installation, page builder, e-commerce, hooks API, helpers, themes, RBAC, and multilingual

---

## v1.0.3 {#v1-0-3}

**Released: 2026-06-08**

### Docs
- README rewritten for v1.0 public release with installation instructions, quick-start guide, and feature overview

---

## v1.0.2 {#v1-0-2}

**Released: 2026-06-07**

### Fixed
- **SQLite Compatibility** — Date-grouping queries in the dashboard (activity charts, stats) now use `strftime()` on SQLite and `DATE_FORMAT()` on MySQL, preventing errors in SQLite-based test environments

---

## v1.0.1 {#v1-0-1}

**Released: 2026-06-06**

### Changed
- `LAZY_CMS_VERSION` constant synced to `1.0.0`

---

## v1.0.0 {#v1-0-0}

**Released: 2026-06-05**

### Initial Public Release
- Package rebranded and published as **`lazycmsapp/lazy-cms-builder`** on Packagist
- WordPress-style drag-and-drop page builder with 22 built-in elements
- Full e-commerce: products, variants, cart, checkout, orders, coupons
- Multi-language support with clean URL prefixes (`/en/`, `/bn/`)
- WordPress-style hooks system (Actions & Filters) with 100+ hooks
- Role-Based Access Control (RBAC) with 6 predefined roles
- Mega menu builder
- Custom Post Types (CPTs) from the dashboard — no code required
- Media library with image optimization
- SEO engine (OpenGraph, JSON-LD schema, sitemap, robots.txt)
- REST API with token authentication
- Revisions & autosave
- Customizer with 60+ configurable theme options
- Maintenance mode, magic link login, multi-device session control
