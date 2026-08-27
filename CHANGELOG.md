# Changelog

All notable changes to FalconCMS are recorded here.

This project follows [Semantic Versioning](https://semver.org/).

## [2.5.0] — 2026-08-28

### Added

- **An Order field for columns and nested columns**, in the General tab's existing responsive
  picker. A column can be given a different visual order for Desktop, Tablet and Mobile
  independently — it's a flex `order` only, so the column never moves in the document and tab
  order / screen readers keep following the real content regardless of what any breakpoint
  says.
- **The Menu Item Options icon picker now offers every icon the builder ships** — Font Awesome
  plus Bootstrap, Remix, Boxicons and Lucide, around 10,000 icons combined, instead of a
  hand-picked subset of about a hundred. Rendering that many at once made the grid stutter, so
  it pages instead: an instant first batch, and a "Show more" control that reaches the rest,
  same as narrowing with search.

### Fixed

- **The builder menu's "Inherit" font, and its Google Fonts request, were both wrong in the
  canvas.** A saved font like "Josefin Sans, sans-serif" was sent to Google's API whole instead
  of split to just the family name, which the API doesn't recognise — the canvas silently fell
  back to a system font while the real site (which already stripped the value correctly)
  rendered the real one. Separately, a menu left on "Inherit" read the theme's *body* font
  instead of its actual *Navigation* typography setting, a different customizer option on
  themes that set one. Both left the same padding rendering as a visibly different box, because
  the two were measuring different glyphs.
- **Desktop preview could be narrower than any real desktop, so content overflowed its own
  column in the canvas.** "Desktop" mode used whatever width the editor panel had free — less
  than a real visitor's browser once the sidebar and design panel are open — so a menu or row
  of columns that fits perfectly for a real visitor spilled out past its own column in the
  editor. The desktop canvas is now floored at the theme's real breakpoint and zoomed to fit
  the panel, so it always lays out exactly as a real desktop would.
- **Cart, Search and Wishlist menu items ignored their own chosen icon.** The Menu Item Options
  modal let an editor pick one and previewed it correctly, but the menu itself always drew a
  fixed built-in icon regardless — the choice was saved and simply never reached the page. A
  chosen icon is now used; an item nobody has touched keeps the original icon unchanged.
- **The Cart/Wishlist count badge ignored the Customizer's Primary Color**, hardcoded to the
  color's own default instead — correct by coincidence on a stock install, wrong the moment a
  site picked its own color.

---

## [2.4.2] — 2026-08-15

**A security and correctness release. Updating is strongly recommended for every site,
and urgently for any site with more than one user account.**

Twelve bugs, found by giving the package a test suite for the first time. Four of them
affect a running site directly, and are listed first.

### Security

- **Eight post actions were reachable by any signed-in user.** `AdminMiddleware` waves
  every row-level `/admin/posts/…` and `/admin/pages/…` path through, on the grounds that
  the permission such a path needs depends on the row's own type and only the controller
  can know it. `store`, `edit`, `update` and `destroy` enforced it; `bulk`, `clonePost`,
  `restore`, `forceDelete`, `autosaveClassic`, `restoreRevisionClassic`, `deleteRevision`
  and `clearRevisions` never did.

  A subscriber — signed in, holding no permission at all — could bulk-trash posts,
  duplicate them, restore them from the trash, **permanently destroy them beyond
  recovery**, and wipe their revision history. Two of the gaps composed into something
  worse: write your own text into a post's history through autosave, then promote it to
  the live post by restoring that revision, defacing anything on the site.

  The check is now one method, and `bulk` filters its selection row by row so a bulk
  action cannot be a way around the per-row rules. Author and contributor ownership is
  carried through to all of them.

- **The WordPress media importer accepted SVG that the upload screen refuses.** The two
  doors into the media library each kept their own list of what the CMS will store. SVG is
  a document that can carry script, and library files are embedded in pages every visitor
  loads, from the site's own origin. The list now lives in one place —
  `falcon_blocked_upload_extensions()` — that both doors consult, and which a site can
  extend through a filter.

- **A blocked user's API token kept working.** Both dashboard login paths refuse a blocked
  account; `AuthenticateApiToken` did not look, so any token already issued stayed valid
  for as long as it existed. The storefront magic link had the same gap.

- **Magic links were single-use by convention rather than atomically.** An unconditional
  read followed by an unconditional "mark used" meant two requests carrying the same
  link — a mail scanner prefetching it alongside the customer's own click — could both
  receive a session. Claiming the token is now the conditional update.

- **`safeRedirectUrl()` could be walked past with a backslash.** Browsers read
  `/\evil.test` as `//evil.test` and leave the site, while `parse_url()` reports no host
  and calls it a relative path. Backslashes and leading control characters are normalised
  before the check, and host comparison is now case-insensitive.

- **The digital-download route resolved an admin-set path without bounding it.**
  `downloads/../../../.env` pointed at a real file, and that route hands whatever it
  points at to anyone holding the token. Defence in depth rather than a live hole, but the
  route is the wrong place to be trusting.

- **Admin status could be granted from a memo that outlived the request.** `isAdmin()`
  memoised against a set of role ids in a `static`. Role ids are reused, and a static
  survives into the next request in any long-running worker, so the memo could answer
  "yes, admin" for an id that by then belonged to a subscriber — and `isAdmin()` is the
  short-circuit at the top of `hasPermission()`, so a wrong "yes" grants everything.

- **The per-customer coupon limit did not apply to guests.** The guest branch read a
  session key nothing had ever written, so `one per customer` was enforced for signed-in
  shoppers only.

### Fixed

- **The archive price filter returned nothing on SQLite.** The filter compared a SQL
  expression against a bound parameter; PDO sends floats as strings and an expression
  carries no column affinity to coerce them back. MySQL compares them as numbers anyway,
  which is why it went unnoticed — SQLite sorts every integer before every string, so
  `500 >= '200'` was false and the product archive came back empty for anyone whose price
  slider moved.

- **Checkout reserved no stock for a variable product whose variations do not each track
  their own count.** The claim picked the variation row when a `variation_id` was present
  and only fell back to the parent when there was none, so a line with a variation that
  defers to the parent matched neither branch and was skipped. The parent quantity never
  moved, so the product stayed "in stock" and could be sold over and over.

- **`Product` carried stale copies of `Post`'s accessors.** `is_in_stock` on `Post` had
  learned about variations, backorders, the shop-wide stock switch and the out-of-stock
  threshold, and `sale_price` had learned about `sale_ends_at`; the copies on `Product`
  never did. The same product therefore answered differently depending on which model
  loaded it, and `Product` is what the cart and dashboard load. The copies are gone.

- **An empty cart quoted the shipping charge as its total.** Never chargeable — checkout
  is guarded separately — but a mini-cart or a "you are ৳X from free delivery" banner read
  it as the whole total.

- **Six controllers extended `App\Http\Controllers\Controller`,** a class belonging to the
  host application rather than to this package, which an application is free to remove or
  rename. They now extend `Illuminate\Routing\Controller` like the other 42.

- **`route:cache` failed on any site with the REST API disabled.** `CmsApiController`
  called `abort(403)` in its constructor, and Laravel instantiates every controller to
  collect its middleware — so `route:list` and, far worse, `route:cache` died, breaking
  production deploys. The check runs as controller middleware now.

- **Two per-request memos lived in `static` variables** and so outlived the request in a
  long-running worker: a product's tax status, which could be served stale after the shop
  owner edited it, and the wishlist, which is user-scoped and could be handed to whoever
  the worker served next. Both are bound to the application instance now.

- **The hook registry accumulated duplicate callbacks** in any process that boots more than
  once — a queue worker, Octane, a test run — firing every hook registered at boot twice.
  `HookManager::reset()` exists for that.

### Added

- **A test suite.** 317 tests and 713 assertions, run against an in-memory SQLite database
  through Testbench, so they exercise the real service provider, the real schema and the
  real helper API with no host site involved. Concentrated where a mistake costs money or
  leaks data: stock, pricing, tax, coupons, order totals, checkout end to end, the
  credential paths, admin authorisation, and both doors into the media library.
- Laravel Pint, PHPStan/Larastan at level 3, and a CI workflow running style, static
  analysis, the suite on PHP 8.2 and 8.4, and `php -l` across 8.1 through 8.4.
- The MIT `LICENSE` file the composer metadata and README badge had been claiming.
- `falcon_request_memo()` for per-request memoisation that cannot outlive the request.
- `falcon_blocked_upload_extensions()`, filterable, as the one definition of what the CMS
  will not store.

### Changed

- `composer.json` now declares the Illuminate components the code actually uses, rather
  than relying on them being present because the host is a full Laravel application.
- The dead `post-autoload-dump` scripts are gone. Composer only runs scripts from the root
  package, so they never fired for an installing application — `falcon:install` and
  `falcon:update` do the publishing. All they achieved was breaking `composer install`
  inside this repository.
- The whole package is formatted with Pint, and `.gitattributes` normalises line endings.

---

## [2.4.1] and earlier

See the [release notes on GitHub](https://github.com/falconcms/falconcms/releases).
