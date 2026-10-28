# Changelog

All notable changes to FalconCMS are recorded here.

This project follows [Semantic Versioning](https://semver.org/).

Full release notes, with the reasoning behind each change, live at
<https://falconcms.github.io/falconcms/changelog>.

## [Unreleased]

## [2.8.9] — 2026-10-28

### Added
- Falcon AI (free, bundled, on by default): Write with AI in the post editor (an editable outline,
  the article streamed in section by section, then its SEO title, description, excerpt and tags),
  Improve selection (rewrite, expand, shorten, simplify, translate), and SEO suggestions, in about
  80 languages (a searchable list). Works with Claude, OpenAI or Google Gemini (which has a free
  tier) on the site's own key, set under AI → Settings & Usage, with a monthly token limit, a
  usage record and a choice of which roles may use it. Every AI answer is cleaned before it
  reaches the editor.
- `falcon_admin_post_editor_tabs`, a hook in the post editor's tab row.
- Shop hooks for plugins: `falcon_product_card_actions` (a button on product cards),
  `falcon_shop_settings_tabs` and `falcon_shop_settings_toggles` (a tab of Shop → Settings with
  switches that save), `falcon_cart_shipping_bar` (a line above the cart and in the mini-cart),
  `falcon_shipping_zone_for_country()`, and `LazyCart.added()` / `window.FalconShopUx` for
  add-to-cart feedback. Falcon Shop Boost (Pro) is built on them.
- `settings_params` in `plugin.json`: the Plugins screen's Settings link can open one tab.

### Fixed
- With static page caching on, a cached page no longer hands its first visitor's CSRF token to
  everyone after them. Each visitor gets their own token, so add to cart (and every other form)
  on a cached page stops failing with 419. Pages cached before the update are rendered afresh.

### Changed
- AI writing moved out of Falcon SEO into Falcon AI, and is now free. The saved Claude key and model
  are carried over on update; SEO → Settings no longer has an AI section. Site Analysis's AI fixes
  ask Falcon AI (`falcon_seo_ai_*` filters).
- The shop archive's "Add to cart" opens the mini-cart, like product cards elsewhere, instead
  of a popup.

## [2.8.8] — 2026-10-28

### Added
- Custom fonts: upload your own (Customizer → Typography → Custom Font), weights read from the
  file, variable fonts as a range, usable in every typography setting.
- Builder extension hooks for plugins: the Extra tab, the canvas and the page markup.

### Changed
- Uninstalling a plugin keeps its data unless "Also delete all of its data" is ticked and the
  plugin's slug typed; uninstalling several at once never deletes data.

### Fixed
- With AVIF images on, product gallery thumbnails change the main picture again.

### Security
- GitHub Actions pinned, Dependabot, and a security policy.

## [2.8.7] — 2026-10-27

### Added
- Motion Visual (Column / Container → Extra) and smooth scrolling to sections; two SEO fixes.
  See the full notes at <https://falconcms.github.io/falconcms/changelog#v2-8-7>.

## [2.8.6] — 2026-10-24

### Security

- **A baseline Content Security Policy on every page.** It blocks nothing the builder relies on
  (inline scripts and styles, fonts, embeds) but stops framing by other sites, `<base>` hijacking
  and plugin objects; on an https site it also upgrades plain-http requests. Replace it or turn it
  off with the `falcon_content_security_policy` filter (`''` sends none).
- **`X-Powered-By` is really gone.** PHP adds it itself, outside the response, so removing it
  from the response never took it off the wire; it is now removed from PHP's own headers too.

### Added

- **Plugin hooks on the admin orders list:** `falcon_admin_orders_toolbar` (beside the search),
  `falcon_admin_orders_columns` and `falcon_admin_orders_column` (a column of the plugin's own) —
  Falcon Security's "Check a number" and Risk column use them.
- **Other ways to sign in on the shop's account page.** Its sign-in form now fires
  `falcon_login_form_after` with `'shop'`, as the dashboard's login does with `'admin'`, so a
  plugin can offer another way in there — Falcon Security's "Sign in with a code to your phone".

### Changed

- **Signing in lands on the page you asked for.** An admin page opened without a session still
  answers 404 (the sign-in address stays hidden), but the page is remembered: signing in at the
  address you know then opens it, instead of the dashboard — a link opened after the session
  lapsed works once you sign in again.

## [2.8.5] — 2026-10-21

### Security

- **No more password checking without a lockout.** `POST admin/login/check` told any visitor
  whether an email and password matched — the "funny" login theme asked it on every keystroke —
  without the sign-in form's five-try lockout, so passwords could be guessed without limit and
  registered emails listed. The endpoint is gone; the theme's running button now only waits for
  both fields to be filled in.
- **One sign-in lockout for every login form.** The shop's My Account sign-in signs in any
  account, administrators included, but had no lockout or rate limit. The dashboard's lock now
  lives in `LoginGuard`, shared by the dashboard, the shop and plugins' own login forms (the
  wholesale login): five wrong passwords lock the account for 30 minutes whichever form they were
  typed into, an address the dashboard blocked is refused, too many failures from one address
  make it wait, and the shop form is limited to 10 tries a minute and no longer says whether an
  email has an account.
- **Foreign Host headers.** Password-reset, magic-login and email-verification links are built
  on APP_URL, so a request sent with another site's name can no longer mail a real token on that
  domain; analytics skips requests for host names that are not the site's (extra domains via
  `falcon_site_hosts`).
- **Analytics referrers:** only an http(s) referrer is shown as a link (a `javascript:` one would
  have run in the admin's session).

### Added

- **Roles: read and write per menu.** A section with a list and an "Add New" (posts, pages, every
  post type, products, media, users, forms, plugin menus shaped the same way) is read with the
  list's permission and changed with Add New's; a role with the list alone sees no Add New,
  checkboxes or edit links. Each submenu gets its own permission; menus of switched-off plugins
  are left out of Roles and keep their grants. A plugin runs only while its dependencies run.
- **Instagram Feed** builder element (Pro, builder_pro): the connected Business or Creator
  account (Customizer → Instagram), public post links, or pictures chosen by hand; kept on the
  site and refreshed in the background (`falcon:instagram-sync`).
- **Posts loop:** `meta_key` / `meta_value` now filter `get_falcon_posts()` (the Card element's
  "Posts By: Custom Field"); new hooks `falcon_posts_orderby`, `falcon_post_orderby_options`,
  `falcon_posts_fetched`.
- **Reading time** counts words in any script (a Bangla article read as "1 min") and reads builder
  content; label, reading speed and digits (Bangla, Devanagari, Arabic) are settings.
- **Plugin hooks:** `falcon_dynamic_sources` / `falcon_dynamic_value` (dynamic content),
  `falcon_admin_list_columns` / `falcon_admin_list_cells`, server-previewed builder elements, and
  `falcon_login_form_after` on both login pages (Falcon Security's passkey button).
- **Analytics:** a Referrer column in the visitor log (Google, Facebook, X, a site by domain,
  Direct), names matched on whole host parts.

### Fixed

- Ordering a posts loop by `views` threw on the front end; an order nobody answers falls back to
  the date.
- Admin menus loaded a count per menu on every request (~80 queries); the admin posts/pages
  "show" routes answered 500.
- SEO page scores were kept for the whole process, so a long-running worker showed the first
  list's scores ever after.

## [2.8.4] — 2026-10-16

### Fixed

- **Plugin updates that arrived with Pro but never finished.** `falcon:update` now finishes every
  active plugin whose files are newer than the version the site recorded (migrations, upgrade
  step, version). A failed reconcile during Composer's run used to leave the old version
  recorded with nothing retrying it.
- **The config cache survives an update.** Composer deletes the cached config before
  `falcon:update` runs; the dashboard updater now tells it the config was cached, and from a
  shell a surviving route cache counts for both.
- **Site Health reads the cause behind "Unable to create configured logger"**, so errors logged
  while an update swaps the files clear once the file is back.
- **Site Health no longer reports OPcache off when run from the CLI.**

### Changed

- **Title element: the editor is 260px tall**, so a two-line title is not cut off.

### Documentation

- Builder Elements (Title, Item List, new Table section, with screenshots), Upgrade Guide
  (plugins and caches on update), Media (titles do not rename files).

## [2.8.3] — 2026-10-16

### Added

- **Title element: a rich editor** (bold, italic, underline, strikethrough, colours, link, code
  view). Enter adds a line break inside the heading. Existing titles open as saved and are only
  rewritten when edited.
- **Title element: new titles start as H1.** Titles saved earlier keep rendering as H2, and their
  tag box now shows H2 instead of a blank.
- **Item List: duplicate an item**, copied right below the original.
- **Table: duplicate a row**, copied right below the original; the header row stays first.

### Changed

- **Table: rows are sorted by dragging a grip** instead of up/down arrows; the grip, duplicate and
  delete controls are matching icons.

### Fixed

- **Retitling a media item no longer renames its file.** The rename moved the image to a new
  address and broke every page that showed it.
- **Updates keep the config and route caches.** The update cleared them and nothing rebuilt them;
  it now rebuilds the ones the site had.
- **Site Health: errors an update fixed no longer stay open** — one that surfaced inside Laravel,
  or a file the update moved away, counts as fixed once new code is installed after it.

## [2.8.2] — 2026-10-16

### Added

- **Cloudflare cache: the saved API token shows half masked.** Settings → Integrations shows the
  token's first half with the rest masked, so you can tell which token is in use; the whole token
  never goes back to the browser. Saving the field as shown keeps it.
- **`falcon_pending_promotion_offers` filter.** The cart's "You qualify: free X" prompt now goes
  through a filter, so a plugin that turns a customer's promotions off can turn the prompt off too.
- **`falcon_show_sale_badge()` and the `falcon_product_sale_badge` filter.** The "Sale!" badge on the
  product page, the shop and product cards (and each variation) asks first, so a customer price
  that arrives as a sale price (a wholesale price) is not called a sale.

### Fixed

- **Analytics lost visits from browsers with a long user agent.** In-app browsers send more than
  the column holds (255 characters); the insert failed and the visit was not counted. The stored
  copy is cut to 255; the browser, system and device are still read from the whole string.
- **Site Health no longer reports a fixed view given request data as a file-inclusion hole.**
  `@include('a.view', ['x' => request('x')])` loads no file the visitor chooses. The rule still
  fires when the view name itself comes from the request, and for PHP `include`/`require`.
- **Site Health: errors from an update caught half-way count as fixed.** A "Failed to open
  stream" for a file that is there again, and the error Laravel hits drawing its own error page
  because of it, no longer stay open for a week.

## [2.8.1] — 2026-10-15

### Added

- **Cloudflare Turnstile on the site's forms.** Settings → Integrations → "Show on" switches it on
  for the login page (password and magic link), registration, forgot password and the shop's My
  Account sign-in, each on its own, once the keys are saved; plugins add their forms
  (`falcon_turnstile_locations`). The answer is checked before the form does anything. If
  Cloudflare cannot be reached the form goes through and the outage is logged, so no one is locked
  out. The widget follows light/dark, stretches with its form and turns compact on a phone.
  Themes and plugins use `falcon_turnstile('location')` and `falcon_turnstile_check($request, 'location')`.
- **Shortcodes as pairs.** `[tag]content[/tag]` passes the content to the shortcode's callback as a
  second argument (callbacks that take one argument are unaffected).
- **Users → filter by role** counts and lists people who hold a role as an extra one, not only as
  their primary role.
- **`falcon_customer_addresses` filter and `falcon_admin_order_sidebar` action.** A plugin can
  offer more saved addresses at checkout (a company's delivery addresses), and print a box of its
  own on the admin order screen's sidebar (a deposit to confirm).
- **`falcon_page_cache_is_personal` filter.** A plugin can keep a page out of the static page
  cache: one that belongs to the visitor, or carries a form whose CSRF token must not be served to
  the next visitor (the wholesale login page).
- **SEO → Rank Tracker (Pro, with DataForSEO).** Follow keywords in Google, by country and on
  desktop or mobile, checked once a week or once a day: the site's position in the top 100, the
  page that ranks, the change since the last check, the best position, a small trend line,
  Google's monthly searches and keyword difficulty. Each row also shows what else the results
  page holds (featured snippet, AI overview, People also ask, videos…) and marks a featured
  snippet that is the site's own. Checks run in the background (`falcon:seo-rank-check`,
  hourly, only the keywords that are due); each one is a single DataForSEO request. Up to 200
  keywords. It has its own permission in Users → Roles (SEO Rank Tracker).
- **Competitor gap** (Rank Tracker tab): enter a competitor's domain and a country. It lists the
  keywords they rank for in Google that this site does not, most searched first, with search
  volume, difficulty, their position and their page. Any of them can be tracked in one click.
  The answer is kept for a week, so looking again costs nothing.
- **SEO Audit: redirects and canonical URLs.** New checks:
  - **Redirect loops**, which never arrive anywhere.
  - **Redirect chains**, with the address to send the first one straight to.
  - **Pages hidden by a redirect**: the redirect answers first, so the page cannot be reached.
  - **Links in content to an address that redirects.**
  - **Canonical URLs that point the wrong way**: at an address that redirects, at a page hidden
    from search, or at something that is not an address.
  - **Pages that name another page as the original.** Listed as information, since that is
    right for copies and syndicated posts.
- **Shop: logic hooks for wholesale, B2B and multi-vendor plugins.** 46 new hooks for prices,
  the cart, coupons, promotions, shipping, tax, checkout, payment gateways, the order lifecycle,
  stock, order emails, My Account, reviews, the wishlist and the admin order list. They include:
  - `falcon_product_prices`: one filter for every price shown and charged (cards, product page,
    variations, cart, order), with the line quantity for tiered prices;
  - `falcon_add_to_cart_validation` and `falcon_checkout_validation`, to enforce minimum
    quantities or order values;
  - `falcon_payment_gateways` with `falcon_payment_redirect_url` and
    `falcon_payment_return_verified`, to add a payment gateway (bKash, Nagad, pay on invoice);
  - `falcon_order_created`, `falcon_order_paid` and `falcon_order_status_changed`. The status hook
    runs on every status change, from any screen or the Stripe webhook.

  With nothing hooked, the shop behaves exactly as before. Full list with examples:
  docs/api/hooks.md → "E-commerce Logic Hooks". Updated shop templates (theme copies older than
  these versions still work but show the catalogue price): `single-product` 1.0.1,
  `single-product-variable` 1.0.2, `archive-product` 1.0.1, `cart` 1.0.1, `account` 1.0.1,
  `wishlist` 1.0.1.
- **Shop: admin hooks for plugins.** A plugin can now add a tab to the product editor
  (`falcon_admin_product_data_tabs` / `falcon_admin_product_data_panels`), fields to each variation
  (`falcon_admin_variation_fields`), fields to product categories and users, and save them
  (`falcon_admin_product_category_saved`, `falcon_admin_user_saved`). It can also add a badge in the
  Users list (`falcon_admin_user_role_cell`).
- **Variations have a `meta` column** for data a plugin keeps per variation. Variations are rebuilt
  on every save, so that data has to live on the variation itself. The editor sends it back on
  every save, so it is kept even when its plugin's field is not on the page. Needs `php artisan migrate`.
- **Shop: plugins can hide prices.** `falcon_show_prices` / `falcon_hidden_price_html`. Product
  cards, the shop, product pages, the wishlist and the builder's Product Meta element show the
  plugin's text (for example "Log in to see prices") instead of the price and the buy button. The
  product cannot be added to the cart, and its structured data leaves the price out.
- **Shop: plugins can list by their own prices.** `falcon_product_price_filter`,
  `falcon_product_price_sort` and `falcon_product_filter_options` let a plugin apply the shop's
  price filter, price sort and price band in its own prices. `falcon_product_query` now also runs
  for related products, upsells, cross-sells and promotion suggestions. "Save All Variations"
  fires `falcon_admin_after_save_product` with mode `variations`.
- Shop: `falcon_cart_actions` below the cart's checkout button.
- Shop: an item's fields (custom fields, and what a plugin adds through
  `falcon_item_custom_fields_display`) now also show in the order email and the admin invoice. On
  the cart page, changing a quantity refreshes the line's unit price and fields as well as its
  subtotal.
- Shop templates updated (theme copies older than these still work but do not hide prices):
  `single-product` 1.0.2, `single-product-variable` 1.0.3, `archive-product` 1.0.2, `wishlist` 1.0.2,
  `cart` 1.0.3.

### Fixed

- **Security: administrators are known by their role's slug, never by its id.** Role ids 1 and 6
  were treated as administrator and super-admin without looking at the role. Where those seeded
  rows were missing, the next role created (a plugin's customer role, say) could take id 1 or 6,
  and everyone given it became an administrator. The administrator and super-admin roles now keep
  their slugs when edited (their name can still change).
- **Shortcode attributes with spaces.** `title="Trade login"` arrived as `Trade`; quoted values now
  keep their spaces, and curly quotes from a visual editor work.
- **The page cache stored the My Account page, and cart, checkout and wishlist pages at addresses
  of their own.** With static caching on, a guest could be served another guest's copy of the
  sign-in form, whose CSRF token then failed ("Page expired"); a cart or checkout page assigned at
  its own address (say `/basket`) could show one visitor's basket to the next. These pages are now
  never stored. Clear the page cache once after updating.
- **Shop checkout only accepts a payment method that is turned on.** Before, any value was
  accepted and the order was placed as an offline payment.
- **Shop: adding to the cart with a quantity that is not a whole number above zero** (for example
  `abc` or `-3`) now adds one item.
- **Shop cart page: line subtotals after a quantity change** now show the price the line was
  re-priced to, not the price from before the change.
- **Variable products: a variation with a sale price of 0** no longer shows a struck-through price.
  A variation's sale also ends when the product's sale end date passes, as it does in the cart.
- **API keys on SEO → Settings (Claude, DataForSEO, Bing) are clear about being saved, and a
  browser can no longer overwrite them.** A saved key shows in its field with the middle hidden
  (`sk-ant-api03-a••••••••••••abcd`); clicking in empties it for a new key, and saving the page
  unchanged keeps the saved one. Beside it are **Test**
  (a free call to the service: Claude checks the key and model, Bing lists the account's sites,
  DataForSEO reads the balance) and **Remove**. The fields tell password managers not to fill
  them; a browser used to put the admin password into the first one on every save, replacing the
  Bing key. A value that cannot be that service's key is refused with a message, and the rest of
  the page still saves.
- **AI writing says plainly when the Anthropic account has no credit**, instead of showing the
  API's raw error.

## [2.8.0] — 2026-10-14

SEO gets a menu and a plugin of its own, with a check-up for every page and, in Pro, AI writing,
Google keyword data, one-click fixes and a weekly email.

### Added

- **Falcon SEO, a bundled plugin (free, on by default).** The SEO and speed tools now live in
  `plugins/falcon-seo`: page analysis, SEO Audit, Bulk SEO, keyword research, internal link
  suggestions, structured data, hreflang, IndexNow, AI crawler rules, the 404 log, search
  visibility, AVIF images, speculative loading, Web Vitals and the Cloudflare purge. Switched off,
  the CMS keeps the basic SEO (title, description, canonical, Open Graph, sitemap, robots.txt,
  redirects), and no saved data is lost. Route names, settings and the old class names
  (`FalconCms\Core\Support\SeoAudit` and the rest, through aliases) are unchanged.
- **SEO has its own menu**, with tabs like Settings: Settings, Site Analysis, SEO Audit, Bulk SEO,
  Search Console (Pro) and Redirects, under `/admin/seo/...` (the old `/admin/settings/seo…`
  addresses redirect). Each screen has its own permission in Users → Roles (`access_seo_settings`,
  `access_seo_site_analysis`, `access_seo_audit`, `access_seo_bulk`, `access_seo_redirects`);
  roles keep the SEO screens they had under Settings.
- **Page analysis with a score** (editor → SEO → Analysis). Reads the published page as visitors
  get it, or the editor's content, and checks headings, the focus keyword, the title and
  description, images (alt text, size, dimensions), internal and external links, broken links,
  structured data and more, with keyword advice.
- **Searchers in every country, several at once.** All countries, with Select all; with a Bing
  key each gets its own bar, fetched 20 at a time, with a note before many requests and a clear
  message when Bing's limit is reached. With the Search Console plugin, Google's own figures for
  the keyword by country appear too.
- **Site Analysis** (SEO → Site Analysis). Every page's check-up and score, kept up to date in the
  background (ten pages an hour, again after a change and weekly), every problem listed across
  the site with its pages, and each page's score in the posts and pages lists. New table
  `cms_seo_page_scores`; command `falcon:seo-scan`.
- **WordPress import keeps your rankings.** SEO titles, descriptions, focus keywords, canonical,
  noindex/nofollow and social fields come across from Yoast SEO, Rank Math, All in One SEO and
  SEOPress, and every published post whose address changes gets a 301 from its old address.
- **Pro tools in Falcon SEO** (licence feature `seo_pro`; every Pro licence includes it): AI
  writing with Claude (SEO titles, meta descriptions, alt text; the site's own Anthropic key),
  Google keyword numbers and difficulty by country through DataForSEO, one-click fixes in Site
  Analysis (descriptions, titles, alt text, broken links; a revision is kept first), and a weekly
  SEO email (`falcon:seo-report`, Mondays).
- **Compiled CSS covers classes that come with the content.** Tailwind classes in imported HTML
  or a builder element's CSS Class field are written on the server as a small stylesheet; the
  in-browser build loads only on a page that still needs it.

### Changed

- New dependency: the official Anthropic PHP SDK (`anthropic-ai/sdk`), used by AI writing.
- New core hooks, listed in the hooks reference: `falcon_robots_txt`, `falcon_seo_robots`,
  `falcon_seo_head_links`, `falcon_seo_schema`, `falcon_optimize_html`, `falcon_customizer_sections`,
  `falcon_seo_tabs`, `falcon_admin_path_access`, `falcon_keyword_countries` and others.

### Fixed

- **The editor's SEO tabs work for editors, authors and contributors.** Keywords, Internal Linking
  and Analysis answered only administrators.
- phpstan passes with no errors; 128 baseline suppressions made unnecessary were removed.

## [2.7.11] — 2026-10-13

The shop makes its own pages (with Wishlist) and can bring back deleted ones; Media → Generate
AVIF copies; Minify CSS keeps `calc()` valid; compiled Tailwind loads where the runtime build
does. Full notes: https://falconcms.github.io/falconcms/changelog#v2-7-11

## [2.7.10] — 2026-10-13

The SEO suite (structured data graph, hreflang, IndexNow, SEO Audit, Bulk SEO, 404 log, keyword
research, AI crawler rules, AVIF, speculation rules, Web Vitals, Cloudflare purge). Full notes:
https://falconcms.github.io/falconcms/changelog#v2-7-10

### Fixed

- **"Load Assets Only When Needed" drops the Lucide library on pages with no Lucide icon.** The
  393 KB `lucide.min.js` was dropped only when a page's Lucide icons could all be drawn
  server-side, so a page with none at all, such as falconcms.com, still loaded it. On mobile
  that accounted for most of the main-thread work.
- **The mini-cart's icons show with "Load Assets Only When Needed" on.** The cart lines are loaded
  over AJAX after the library may have been dropped, so the remove button's ✕ stayed blank. They
  are now drawn on the server, through the new `falcon_lucide_icon()` helper. The
  `ecommerce/mini-cart-items` template is now at `@version 1.0.1`.

## [2.7.9] — 2026-10-07

Three new speed options, all off by default, and a new docs page explaining every
performance setting: https://falconcms.github.io/falconcms/guide/performance

### Added

- **New option: Inline Only the Icons in Use** (Customizer → Performance, off by default). Icon
  libraries (Font Awesome, Bootstrap Icons, Remix, Boxicons, Lucide) each load a whole
  stylesheet, 70–140 KB apiece, and the page waits for it before it paints. With the option on,
  the page carries, inline, only the rules for the icons it shows: usually a kilobyte or two.
  Libraries the page doesn't use are dropped, and the fonts load with `font-display: swap`. The
  whole page is scanned, so icons named in Alpine expressions and inline scripts are kept too.
  Works with any theme. In a Lighthouse test of falconcms.com that left those stylesheets out,
  the simulated mobile LCP fell from 11.4 s to about 3.8 s.
- **New option: Critical CSS** (Customizer → Performance, works with Compiled CSS, off by
  default). Each page carries, inline, only the compiled stylesheet's rules it uses: about 15 KB
  of the 68 KB file on a typical page. The full file then loads without holding up the first
  paint, so classes added later by JavaScript are styled as before; a browser without JavaScript
  gets the plain link. Checked element by element on desktop and mobile: the first paint matches
  the normal page.
- **New option: Host Google Fonts Locally** (Customizer → Performance, off by default). The
  Google Fonts the site uses are copied to `public/falcon-fonts`, and each page carries their
  `@font-face` rules inline. The text no longer waits on Google's servers, and visitors' browsers
  never contact Google, which also helps with the GDPR. The copy is made in the background after
  a page's first visit; until then, and whenever a download fails, fonts load from Google as
  before. Pages served in the meantime are not kept in the page cache. Works with any theme.

### Changed

- **The hero image is no longer lazy-loaded.** With "Lazy-load images" on, only the first image on
  the page stayed eager. That image is usually the logo, so the hero, the largest paint on most
  pages, was lazy and loaded late. The first three images now stay eager, as in WordPress.
- **The hero image is fetched first.** With "Lazy-load images" on, the first image after the
  site header gets `fetchpriority="high"`. On nearly every page that image is the hero, the
  largest paint. Pages without a `<header>` are left alone.
- **Builder images reserve their space.** An image from the media library carries its `width`
  and `height`, so the content below doesn't jump when it loads. If the element has no alt text
  of its own, the library's alt text is used.

### Fixed

- **No more JavaScript errors on a variable product page.** Before a variation was chosen, and
  again after "Reset Selection", the Availability row and the SKU read the empty selection and
  threw a burst of "Cannot read properties of null (reading 'stock_status')" errors (10 per
  load). They now read it safely. The `single-product-variable` template is now at
  `@version 1.0.1`, so Site Health flags a theme copy that still has the old code.
- **`falcon:update` restarts queue workers.** A running `queue:work` keeps the code it started
  with, so after an update it went on running the old version until someone restarted it by
  hand. The update now signals workers to restart once their current job is finished. On a site
  without a worker this does nothing.

### Accessibility

- **The mobile menu button has a name.** It was an icon alone, so screen readers announced just
  "button" and Lighthouse failed the page on it. With no trigger text set, it is labelled "Menu".
  It reports whether the menu is open (`aria-expanded`) and which menu it controls. The sidebar's
  close button is labelled "Close menu".

## [2.7.8] — 2026-10-06

### Fixed

- **The shop's CSS and JavaScript load on every server.** Plugin files were served only through
  the CMS at `/plugin-assets/…`, and many nginx setups answer every `.css`/`.js` URL straight
  from disk, so the mini-cart, wishlist, filter and shop admin scripts returned 404 there.
  Active plugins' static files are now also copied to `public/plugin-assets/{slug}`, at the same
  URLs. `falcon:install`, `falcon:update` and activating a plugin publish them, and deactivating
  removes them. Only static web file types are copied, never PHP.
- **An order or report that includes a deleted product opens.** The order page, Shop → Reports
  and the dashboard's top products linked each line to its product. Once a product was deleted
  there was no product to link to, and the page failed with a 500. The line now shows the
  product's name without a link.
- **Analytics labels a homepage visit with the domain it came in on.** It used the domain in
  `APP_URL`. A site that moved to a new domain without updating `APP_URL` therefore listed its
  homepage visits under the old domain. A homepage visit with a query string now reads
  `/?author=1` rather than `?author=1`.

## [2.7.7] — 2026-10-06

### Changed

- **The shop is now a bundled plugin, Falcon Shop (`falcon-shop`).** It is still free and still
  switched on on every site, new or updated, so nothing changes until you choose to switch it off.
  It can now be deactivated under Admin → Plugins, or forced off with
  `FALCON_DISABLED_PLUGINS=falcon-shop` in `.env`. While it is off:
  - no shop code, CSS or JavaScript loads, in the storefront or the admin
  - the Shop and Products screens leave the admin
  - shop URLs fall through to ordinary pages or the theme's 404
  - theme calls to `route('shop.*')` and the common cart helpers keep working

  Products, orders and settings are untouched in either state. Models and migrations stay in the
  core. Classes that moved keep working under their old `FalconCms\Core\...` names.
- **Theme overrides of shop templates live at the same paths as before** (for example
  `ecommerce/cart.blade.php` in the theme), and the shop's own templates now come from the plugin.
- **`falcon:update` removes the shop templates that earlier releases published into
  `resources/views/themes/falcon-theme`.** Those copies were never refreshed again and would
  have overridden the plugin's templates indefinitely. Only that parent theme is affected, and
  every update already overwrites it. Child themes are never touched.
- **The order email moved into the plugin.** A restyled copy published at the old view path
  (`vendor/falcon-cms/emails/shop/order_notification`) is still used.

### Added

- **`php artisan shop:template`** lists the shop's templates and shows which ones the active theme
  overrides. `php artisan shop:template ecommerce/cart` copies a template into the theme for you
  to customise.
- **Template versions.** Every shop template starts with a `{{-- @version x.y.z --}}` line. If a
  theme's copy is older than the shop's template, or has no version line, Site Health and
  `shop:template` report it as outdated.
- **Hooks for themes and plugins:**
  - `falcon_header_actions` (the cart icon) and `falcon_after_footer` (the mini-cart) in theme
    layouts
  - `falcon_frontend_page_response`, `falcon_single_view` and `falcon_archive_view`, so a plugin
    can render its own content
  - `falcon_plugin_post_types`, which ties a post type to the plugin that serves it
  - `falcon_site_health_checks`, which adds a plugin's checks to Site Health
- **Helpers:** `falcon_plugin_active()`, `falcon_plugin_asset()` and
  `falcon_post_type_available()`.

### Fixed

- **Shop pages wear the active theme.** Product, cart, checkout and the other shop pages always
  used falcon-theme's header and footer, whatever theme was active. They now use the active
  theme's own layout.
- **A child theme gets its parent's shop templates.** The cart, checkout, confirmation, mini-cart,
  order tracking and wishlist looked in the child theme and then went straight to falcon-theme,
  skipping a parent theme's own shop templates.
- **Four variable-product hooks fire.** `falcon_variable_before_product_images`,
  `falcon_variable_after_product_images`, `falcon_variable_before_product_description` and
  `falcon_variable_after_product_description` lived only in a template that was never rendered.
  `falcon_product_description_title` now applies to variable products as well. The unused
  `ecommerce/single.blade.php` and `ecommerce/single-product-variable.blade.php` templates are gone.
- **Products and posts without an image show a placeholder instead of a broken image.**
  - Product cards, the product page and the post archive pointed at
    `assets/images/placeholder.jpg`, a file that never shipped, so every one of them was a 404.
  - A cart line without a thumbnail loaded an image from via.placeholder.com, a service that no
    longer responds.

  Both now use `falcon_placeholder_image()`, an inline SVG, so the page makes no extra request.
  The `falcon_placeholder_image` filter lets a site use its own image instead.
- **No PHP deprecation notices when an email is sent.** The mail classes set `$tries` and
  `$backoff` without declaring them, which triggers a deprecation notice on PHP 8.2 and later.

### Developers

- `src/helpers.php` is split into topic files under `src/helpers/`, and the builder's shortcode
  converter into one class per element under `src/Services/ShortcodeConverter/Elements/`.
  Function names, signatures and behaviour are unchanged. CONTRIBUTING.md describes where new
  helpers and builder elements go.

## [2.7.6] — 2026-10-03

### Added

- **Off-canvas panels in the Builder Library.** Build slide-in panels and pop-ups with the page
  builder and show them on the pages, posts, products or CPT entries you choose — matched canvas
  size, responsive device modes and a save toast. (Pro.)
- **Site Health (Settings → Site Health).** One page that reports the PHP and database versions,
  known dependency vulnerabilities, a quick code scan and environment checks, judging errors, dev
  machines and the timezone by what is actually true rather than by guesswork.
- **A Visitor Log (Analytics → Visitor Log).** A record of individual visits — time, IP, country
  and city, page, referrer, device, browser and OS — with its own sidebar entry and a custom date
  range. It can be filtered by several countries at once (a searchable multi-select), searched by
  IP/page/referrer, paginated, exported to CSV and imported back from one, and rows can be
  bulk-selected and deleted.
- **Conditional fields in the Form Builder.** A field can show or hide based on another field's
  answer.
- **A responsive back-end.** Below 783px the admin sidebar becomes an off-canvas drawer opened by a
  hamburger, the page uses the full width (no more text spilling out of dashboard widgets), form
  fields and list tables stop overflowing, and the list-table toolbars wrap and align tidily.
- **Performance options (Customizer → Performance), all off by default.** Lazy-load images, defer
  non-critical JavaScript, minify HTML and inline CSS (separate toggles), "Load Assets Only When
  Needed" (Font Awesome, Alpine and SweetAlert load on a page only when it uses them, and Lucide
  icons render as inline SVG so the 390 KB library can be dropped), and "Compiled CSS" (one pre-built
  purged stylesheet of ~65 KB in place of the 400 KB runtime Tailwind). Colours and fonts still
  follow the Customizer.
- **Google Fonts ask only for the weights a page uses** rather than all nine per family.
- **Icon Box: Spacing Below on hover** — the gap under the icon can change on hover, like Size and
  the colours.

### Changed

- **The active theme is listed first** on the Themes screen.
- **Performance options that only work with the Falcon theme are disabled under another theme**,
  with a note saying so; the theme-agnostic ones (lazy images, defer JS, minify) stay available.
- The page builder's header/footer preview now works under **any** theme, not only the Falcon
  theme — the real header and footer are sliced out of the rendered page and shown around the canvas.
- HTML optimisations now apply to logged-in visitors too.
- Unpublishing the home page now takes it off the site.
- Faster pages: builder element definitions are resolved once per request instead of per column,
  and author, categories, tags and terms are eager-loaded in `get_falcon_posts()`.

### Fixed

- A per-element Google Fonts link rendered with literal `{{ }}` braces (a Blade `@{{` escape
  collision), producing a blocked request and no font.
- Analytics "Overview" stayed highlighted while on the Visitor Log page.
- A false "cache not writable" warning on Clear Cache on Windows (`is_writable()` reflects the
  read-only attribute, not the real permission).
- The Customizer footer breaking when a literal `<style>` tag appeared in a field description.
- The Layout screen (Falcon Builder → Sections) was dead — an apostrophe in a tooltip
  (`theme's`) closed a single-quoted JavaScript string and broke the whole script.
- Restoring a backup now keeps the activity log (the record of what happened, most needed right
  after an incident), clears the settings cache afterwards (so the site stops serving pre-restore
  values), and survives the browser tab closing mid-restore.

### Security

- Public form file uploads are now validated server-side (extension allow-list, size cap, UUID
  filename) to close an unrestricted-upload / remote-code-execution path.
- Pinned safe versions of framework dependencies to clear known advisories.

## [2.7.5] — 2026-09-25

### Added

- **A custom date range on Analytics.** *1 year* is gone: a chart of 365 points is not a
  thing anyone reads, and nobody asks their analytics "how was last year" in those words.
  In its place is a calendar that asks which days. It only offers days the site has visits
  for — a range drawn across nothing produces a page of zeroes that reads as a fault rather
  than an answer — and each pickable day carries a bar showing how busy it was against the
  busiest day in view, so the calendar answers "where is there anything worth looking at"
  before a range is chosen at all. With no visits recorded, the button says so rather than
  opening an entirely grey calendar.
- **Width and Max Width per device on the Image element.** One value for every screen is
  fine until a 180px logo sits in a column that is a quarter of a desktop and the whole
  width of a phone. Both fields now cascade mobile → tablet → desktop like every other
  responsive setting, with the usual reset and device switcher.
- **A border on the Icon Box's icon, and a state under the pointer.** Border and border
  colour join the existing size, colour, background, radius and padding — and each of those
  now has a hover half. Normal and Hover are the same fields writing to different keys, so a
  hover field left empty means "whatever Normal says" and an icon box with nothing set on
  hover renders exactly as it did before. Spacing Below is deliberately not among them:
  hovering would shift the whole card as the pointer crossed it.
- **Clear Caches, in Customizer → Performance.** A saved setting that will not take effect
  is almost always a settings cache that could not be dropped, and there was no way to clear
  it from the admin. The button checks rather than assumes: clearing is reported as success
  only when the entry is actually gone afterwards, and a failure names the folder, the usual
  cause and the command that fixes it.

### Changed

- **The builder previews mobile and tablet at the Customizer's own screen sizes.** Small
  Screen and Medium Screen build the front end's media queries, and now the canvas as well,
  so the width being previewed and the width a visitor gets are one number rather than two
  that can drift. An open builder re-reads them when its tab comes back to the front, so
  changing either one no longer needs a reload. Tablet and mobile also zoom to fit the panel:
  they lay out at a fixed width, the panel is often narrower, and the far edge of a tablet
  layout was being cut off rather than scrolled to.
- **Responsive Typography says what it is doing.** Two sliders producing a clamp() out of a
  third setting in another section is a black box, and the usual report — "I move them and
  nothing changes" — is usually true: a 15px body with the factor at 2.1 puts the floor at
  31.5px, above four of the six headings. The section now states the floor, which headings
  shrink and by how much, and which are already smaller and stay fixed.
- **Every email fits a phone.** All five were laid out for a desktop mail client and none of
  them gave way below that. Each now narrows under 520px — gutters halve, two-column rows
  become two lines — while the desktop layout stays where it was, so a client that drops the
  stylesheet gets the layout it always had rather than a broken one.

### Fixed

- **An empty layout section no longer replaces the theme's own with nothing.** A Footer
  created from the Layout screen is assigned before anything is built in it, and rendered as
  an empty wrapper: truthy, so the theme footer was skipped and the page ended with no footer
  at all. An empty section now yields to the theme default, and the Layout screen calls it
  empty rather than announcing it as active.
- **Font sizes are read by unit rather than by stripping digits.** Responsive Typography
  matched a px-only pattern, so a heading written as "2.5rem", "150%" or a bare "40" was left
  fixed with no sign it had been skipped — and a bare number is not valid CSS either, so that
  heading lost its size as well. Worse, the body size was read by deleting every non-digit:
  "1rem" became 1, dropping the floor to 1.5px so every heading shrank to nearly nothing, and
  "120%" became 120, lifting it to 180px so nothing shrank at all.
- **The builder's preview frames no longer argue about how tall they are.** Opening a page
  made the canvas jump up and down for ten seconds. Two things set the header, title bar and
  footer previews' heights and they measured different quantities — one of them
  documentElement.scrollHeight, which is floored at the viewport and so could only ever grow
  the frame while the frame's own message could shrink it. A 400ms poll drove the argument
  twenty-five times.
- **The Icon Box's background opacity reaches the page.** It was read into a variable and
  then never used, so the slider moved the canvas and nothing else. The icon colour also fell
  back to a different default on the page than in the builder.
- **Analytics answers for the window it is showing.** Thirteen queries had a start and no
  end, which is the same as a window only while the window runs to now; the hourly chart
  blanked the hours that had not happened yet, right for today and wrong for a day in March;
  and the recent-activity list was never filtered at all, so a window that closed last month
  was illustrated with this morning's visitors.

## [2.7.4] — 2026-09-22

Thirteen fixes, most of them the same shape: a setting that took a value, looked right on
the canvas, and then did something else once published.

### Added

- **Link Hover Color on the Container**, beside the Link Color it belongs with.

### Changed

- **`/admin` is a 404 for every browser without a session again.** 2.6.13 had carved out an
  exception: a browser carrying a year-long `falcon_admin_seen` cookie, dropped on any
  successful sign-in, was redirected to the login page instead of meeting a bare 404. The
  cookie was never cleared on logout, so a shared or handed-on machine kept pointing at the
  login URL for a year, to whoever used it next — which is exactly what the 404 was hiding.
- **Paste lands beside what you right-clicked**, immediately before or after it, rather than
  at the top of whatever container it was in.
- **A settings cache that will not clear is logged at error, not warning.** Every saved
  setting reads its old value until it expires, which is not a warning-level event.

### Fixed

- **Container Link Color and Border Color do what the panel says**, and a link colour set on
  a container reaches the links inside it.
- **A gradient of one colour paints that colour**, instead of fading to something else.
- **The editor and the page agree on row wrapping and on canvas typography** — two places
  where the canvas had its own copy of a default and answered it differently.
- **Icons from every bundled set** appear in the editor, not just one of the five.
- **Table row hover works under every preset**, rather than half of them.
- **A submenu marks its parents as well as itself**, so the trail to the current page is
  visible rather than only its last step.
- **The Navigator's drop marker keeps up with the drag**, and the canvas follows what the
  Navigator opens.

## [2.7.3] — 2026-09-20

Four corrections to 2.7.2, three of them sharing a shape: something the CMS did to itself
and then reported as your problem.

### Fixed

- **Updating from the dashboard no longer refuses to start after a command-line update.** A
  shell update left the package owned by root, and the pre-flight check was right to stop —
  what was missing was anything putting the ownership back, so the fix-it instruction had to
  be followed again every time. The command that causes it now repairs it.
- **A page a plugin's menu points at can finally be opened.** It answered 403 for everyone
  but an administrator: the middleware looked for the owning menu in the `menus` table, and a
  menu registered through a hook is not in it. So the menu was visible, its permission
  grantable since 2.7.2, and the page still closed.
- **Clicking Shop goes to Shop.** A migration pointed that route at the overview in June and
  the seeder overwrote it on every update since, sending it back to Orders.
- **Two columns are no longer dropped in silence** on the way into models that could not
  accept them.

## [2.7.2] — 2026-09-20

> **For developers.** The helpers and hook tags that still said `lazy_` are `falcon_` now,
> and the stored settings keyed under the old name have moved with them. Nothing already
> written stops working: every old helper name remains as a forward, a renamed hook fires for
> callbacks registered under either name, an old stored key is still read, and old
> `[lazy_*]` builder shortcodes still render.

### Added

- **A mega menu the theme header builds itself**, out of a top-level item's own sub-items —
  columns, a panel width, and eight ways of dividing the panel up. Off by default, and off
  changes nothing. Offered only where there are sub-items to make one from, with an Item
  Border Hover Color and a border on every style that can carry one.
- **Taxonomy archive bases are a setting**, rather than fixed.
- **A child theme inherits its parent's templates**, as it always claimed to.
- **The loop can order posts by `published_at`.**

### Changed

- **A menu a plugin or theme registers is listed in Users → Roles.** A package could add a
  sidebar entry that nobody could be granted; now it can be granted, or marked public, or
  kept out of the role editor on purpose.
- **An options page, its menu and its middleware guard name the same permission**, instead of
  three different ones.
- **A subscriber holds the Dashboard and the Overview, and nothing else** — rather than a
  Users menu that answered 403 when clicked.
- **Grants made under the old permission spelling are carried across** by a migration that
  only ever adds, and declines anything genuinely ambiguous rather than guessing.
- **The Post Types and Taxonomies lists use the whole width**, like the Fields screen.

### Fixed

- **Static page caching could serve one visitor's basket to another.**
- **One unguarded mega-menu property took the whole header down.**
- **Renaming a term leaves a redirect behind**, so the old address still resolves.
- **A category in a menu points at where that category lives now**, not where it used to.
- **Two menus may share a name; they may not share a slug.**
- **The loop's paginator keeps the rest of the query string**, so a filter survives page two.
- **A product page's scripts survive an out-of-stock product.**
- **Two admin screens no longer misdescribe a custom post type**, and term archive links
  follow the configured base.

## [2.7.1] — 2026-09-15

### Added

- **A custom post type can be told where to sit in the sidebar.** ACPT → Advanced offers the
  dashboard's own menus to place it after, rather than leaving the position to chance.
- **The icon picker offers every icon the dashboard can draw** — 4,237 of them, with a search
  — instead of a hand-picked 36.
- **A form can be renamed after it is created.**
- **Shop and Products have a section of their own** in the sidebar.

### Fixed

- **Products stays under Shop.** A post type's menu position was computed from its database
  id, so the wrong id collided with Shop and the sidebar drew the tie either way round.
  Positions are no longer derived from ids.
- **Analytics draws something on Today.** Today is its default range, one day is one data
  point, and a line through one point is not a line — Today is hourly now.
- **Six shop and editor faults found while filming the tutorials**, all of them saves that
  failed without saying so.

## [2.7.0] — 2026-09-08

### Added

- **Custom column widths.** Column and Nested Column → Design → *Use Custom Width* opens a
  slider for any width the preset fractions cannot express. Presets and the slider are the
  same setting, so only one is shown at a time; going back picks the nearest preset rather
  than leaving the row with nothing selected. Per device, like the presets — a column can be
  1/3 on desktop and 45% on mobile.
- **A Today range on Analytics, and it is the default.** The question the page is opened to
  answer is almost always "what is happening now". The other ranges are unchanged and still
  one click away.
- **Landing-page menus mark the section you are reading.** A menu of `#pricing`-style links
  now highlights the item whose section is at the top of the viewport, scrolls smoothly to a
  section when one is clicked, and stops short of a sticky header instead of hiding the
  heading underneath it. Works in the theme header and the Layout builder's Menu element.
- **Hover Border on the Button element** — width per edge and colour, kept directly under the
  border it changes. Blank keeps the resting value, and 0 is a real answer.
- **Hover Animation on the Button element** — Lift Up, Sink Down, Grow, Shrink, Glow or
  Pulse, and none of them for a reader whose system asks for reduced motion.

### Changed

- **Analytics counts a visitor once a day, and again the next day.** Counting distinct
  addresses across a whole range had fixed one fault and introduced its opposite: somebody
  who came back on ten different days was still a single address, so a month looked no busier
  than a day. Every figure on the page now counts distinct (address, day) pairs, so the cards
  agree with each other; over Today it is the same number it always was.
- **New vs Returning is answered over the range, not only before it.** The old query asked
  whether an address existed before the range began, so a reader who first arrived on Monday
  and came back daily counted as new all week. Their first day is new, the rest are returns,
  and the two still add up to the headline.
- **Analytics → Active Pages lists every page the people here now have read**, not only the
  one page each of them is on at this instant. Each row counts people, so re-reading a page
  never counts twice.
- **Alignment on a full-width Button moves its label.** A button set to Full Width already
  fills its row, so there was nowhere for Alignment to move it and the setting looked broken.
  It now sets the text alignment inside the button; a button sized to its label is placed
  within the row exactly as before.

### Fixed

- **Importing layouts failed with a database error** on any site that had ever deleted a
  header or footer. `posts` is unique on (slug, type, lang_code) but the import matched only
  slug and type, and through the soft-delete scope — so a section in the bin was invisible to
  the check while still holding its key, and the insert was refused. The full key is matched
  now, a section in the bin is restored and reused, and a section that still cannot be placed
  is reported and skipped instead of taking the whole import down.
- **Every custom link in a menu was marked as the current page.** An item saved as `#pricing`
  has no path of its own, and reading it as "/" made all of them match on the home page. The
  theme header and the builder's Menu element also answered this question separately and
  disagreed; they now share one answer.
- **The unsaved-changes warning appeared when saving.** Most of the admin saves by calling
  `form.submit()` from script, which fires no submit event, so the guard never saw the save
  and asked "Leave site?" at the moment the work was being written. It still warns when a tab
  with unsaved edits is closed — which is all it was ever for.
- **A Button's border did not show.** The Border Color field was the only colour field in the
  panel that hid its opacity, so a border stored at 4% read as `#aaa` and drew nothing; and
  the canvas ignored opacity entirely, showing a solid border for one the site would not
  draw. Section borders had the same fault on the canvas.
- **Menu hover settings now describe the current item too** — border and border colour as
  well as text and background, which was already the case.
- **The builder canvas and the site draw a menu the same.** Five properties were computed
  differently (default font size, a numeric letter-spacing missing its unit, a stretched
  link height, an alignment that belongs to the list, and a missing gap), a cleared number
  field was handled differently on each side, the canvas link inherited the `line-height: 0`
  that the editor puts on every element wrapper, and the editor's own padding and dashed
  border pushed the menu 5px in from where the site draws it.

## [2.6.16] — 2026-09-08

### Fixed

- **Changing the navigation font under Typography → Navigation had stopped working.**
  v2.6.15 gave the Menu section its own Navigation Font Size and Font Weight; to have any
  effect those had to out-specify the typography rule, and doing so silenced it, so the
  Typography control quietly did nothing. Both Menu fields are gone again and Typography →
  Navigation is once more the single place the navigation font is set — family, size,
  weight, line height, spacing and case.

  Menu keeps the properties typography does not own: text colour, hover colour, item
  padding and the two dropdown colours. Those are unchanged and still apply.

## [2.6.15] — 2026-09-08

### Fixed

- **Navigation Font Size and Navigation Font Weight work.** v2.6.14 removed them, on the
  grounds that Typography → Navigation already owned the navigation font. That took away two
  controls people were using instead of repairing them. Both are back and now applied, taking
  precedence over Typography → Navigation, which keeps the family, line height, letter
  spacing and case.
- **Dropdown Text Color reaches the mobile menu's sub-items**, which were painted with a
  fixed grey. The desktop dropdowns already carried it.

Every field in Customizer → Menu was then checked in a browser against the rendered page —
text colour, hover colour, font size, font weight, item padding and both dropdown colours,
on the desktop navigation and the mobile menu, resting, hovered and on the current page.

## [2.6.14] — 2026-09-08

### Fixed

- **The Customizer's Menu settings did not reach the theme header.** Navigation Text Color
  was emitted only once a Navigation typography had also been saved, so on most sites it had
  no rule at all; Menu Item Padding could not do anything because the navigation carried a
  fixed 32px gap; and the mobile menu was painted with hard-coded classes, so every one of
  these settings stopped at the desktop breakpoint. The whole section is now stated outright
  and applies to the desktop navigation and the mobile menu alike. Defaults are unchanged and
  match what was already on screen, so a site that never opened these controls looks the same.
- **Navigation Font Size and Navigation Font Weight are gone from Menu.** They were written
  to the database and then read by nothing. Typography → Navigation already owns the
  navigation font — family, size, weight, line height, spacing and case — and does work; two
  controls for one property was the defect, so the pair that never worked was removed rather
  than made to fight the pair that does.
- **A layout slot switch asks for a state instead of a flip.** The endpoint used to invert
  whatever was stored, which is only correct while the page's idea of the current state is
  exactly right. A double-click, a request the browser retried, or a tab opened before the
  slot changed elsewhere sent a second flip and landed on the opposite value — read as
  "I switched it on, reloaded, and it was off". The switch now sends the state it wants and
  that state is stored, so repeating the request cannot change the answer.
- **Falcon Builder → Sections could fail with `Undefined variable $errors`**, taking the
  whole page down with a 500 instead of drawing the layouts.

## [2.6.13] — 2026-09-07

### Fixed

- **A browser that has signed in before no longer meets the /admin 404.** The 404 added in
  v2.6.7 keeps the login URL from being guessed, but it also met the site's own admin after
  a session lapsed, where it reads as a broken site. Such a browser is now sent to the login
  page; one that has never signed in still gets the 404. The marker is an encrypted Laravel
  cookie, cannot be forged, and never signs anyone in.
- **The install guide, introduction and home page told new users to visit `/admin`** after
  installing, which has answered 404 since v2.6.7. They now name the login URL the installer
  prints.

## [2.6.12] — 2026-09-06

### Fixed

- **The Real-Time per-minute graph counted page views**, the last figure on the analytics
  page still counting rows: one person opening four pages in a minute drew a bar four
  times too tall. It counts distinct visitors per minute now, and is labelled
  "Visitors per minute".

## [2.6.11] — 2026-09-06

### Changed

- **Every analytics figure counts people, not page views.** Visitors, Visitors Today,
  Visitors This Month, the period change, Top Pages, Top Referrers, Traffic Channels,
  Traffic Sources and the Browser / Device / OS breakdowns counted one row per page view,
  so one person reading four pages counted as four. All now count distinct visitors by IP.
  Page views remain, as their own clearly-named tile and as a series on the traffic graph.

## [2.6.10] — 2026-09-06

### Fixed

- **The Real-Time panel placed one visitor on every page they had read**, so it showed
  "1 active user" above a table of six pages with one user each. The panel is now built
  from one row per visitor — their latest page view — so each person is counted once, on
  the page they are on, and the table always adds up to the headline count.
- **Analytics `created_at` was not cast to a date**, so PHP-side comparisons compared
  strings rather than moments.

## [2.6.9] — 2026-09-06

### Fixed

- **Analytics counted one person as many.** Visitors by Country, Top Countries, Active
  Pages and the Live Visitors list counted page-view rows where they meant people, so
  one person reading eight pages read as eight visitors. They now count distinct
  visitors; visit/page-view figures are unchanged.
- **A settings change could appear not to save.** A failure to invalidate the shared
  settings cache was swallowed, so the write landed in the database while every later
  request kept serving the old value until the cache expired. It is now logged, naming
  the usual cause — a cache file owned by another user after running artisan as root.
- **The real-time analytics feed could not run on SQLite** (MySQL-only date function).

## [2.6.8] — 2026-09-06

### Fixed

- **Front-end 500 on any site with an active plugin, after updating to v2.6.7.** Plugins
  moved into `resources/views/plugins`, and the check that restricts which views under
  `resources/views` may render had not been told about it. It only fires where
  `resources/views/vendor` exists — `realpath()` returns `false` without it and PHP compares
  that against `''`, which matches everything — so development never saw it and updated
  sites did.
- **A layout slot with no section assigned reported a successful on/off toggle** in Falcon
  Builder → Sections and reverted on reload, because "nothing assigned" and "now inactive"
  were the same return value.

## [2.6.7] — 2026-09-06

### Security

- **`/admin` no longer gives away the login page.** The login URL is configurable so it is
  not guessable, but an anonymous request to `/admin` was answered with a redirect to
  wherever it had been moved — and `/admin/login` redirected there too. A guest now gets a
  404. Signed-in administrators are unaffected; a session that expires mid-edit now ends in
  a 404 rather than the login form.
- **The wishlist no longer leaks the admin login URL.** Its "please log in" reply carried
  `route('admin.login')` in JSON to every anonymous visitor. Shoppers sign in on the
  storefront account page, so it points there and passes `redirect_to` so they return to
  their wishlist.

### Added

- **A warning before you lose unsaved work.** Closing the tab or navigating away with
  unsaved changes asks first — in the page builder, and on every admin screen through the
  shared layout. Only POST forms count, only real typing counts, submitting clears it, and
  rich-text editors are asked directly since they type inside their own frame.
- **Nested columns are edited in place.** A nested row draws closed with an edit/add panel
  on it; the pencil opens it and a Finished tick closes it. While open, the rest of the
  canvas dims and stops responding, and Save arms when you finish rather than on every
  keystroke inside. Closing hides the editing chrome, never the content.

### Fixed

- **The Card element ignored its Layout setting on the canvas.** Grid, List, Masonry and
  Carousel render through a stylesheet the element emits, and the builder's sanitiser
  dropped it — so every layout looked identical while the front-end was correct.
- **The Falcon Slider element showed nothing once a slider was chosen** — the same
  sanitiser removing the preview `<iframe>`.
- **An anchor-only menu item (`#section`) raised an `ltrim(null)` deprecation** on every
  page under PHP 8.1+. Active-state matching also learned that Home matches the home page,
  that a trailing slash still matches, and that a link to another host never does.

### Changed

- **Plugins live in `resources/views/plugins`**, alongside themes, instead of a root-level
  `plugins/`. `php artisan falcon:update` relocates an existing install — keeping active
  state, data and migrations — and the old location keeps working until it runs.

## [2.6.6] — 2026-09-06

### Added

- Callout, Table of Contents and Previous / Next elements (Pro); buttons inside table
  cells; Image Lightbox; Back to Top; Text Animation; drag anywhere in the navigator;
  paste wherever the clipboard can land.

### Changed

- Email verification is off on a new install.

### Fixed

- Letter spacing did nothing anywhere; a quotation mark in any text setting truncated it;
  Gallery and Image lightboxes did not cover the screen; maintenance mode did not hide the
  site; the Heading element's settings panel was empty; anchor links keep their trailing
  slash; Callout list rendering and icon picking.

## [2.6.5] — 2026-09-04

### Fixed

- The page builder returned a 500 on servers with `short_open_tag` enabled.

## [2.6.4] — 2026-09-04

### Added

- Table element, Code Block element, and a Head HTML setting.

### Fixed

- DOMPurify was missing from the package; changing the login or registration URL did
  nothing on a live site.

## [2.6.3] — 2026-09-04

### Fixed

- The sitemap returned a 500 on any server with `short_open_tag` enabled.

## [2.6.2] — 2026-09-03

### Fixed

- The sitemap returned a 500 for the whole site when it met a post with no timestamps.

## [2.6.1] — 2026-09-01

### Added

- Section Separator element, custom SVG shapes, and SVG uploads as a site decision.

### Fixed

- The builder canvas added height a published page never had.

## [2.6.0] — 2026-08-28

### Changed

- Requirements are now Laravel 13+ and PHP 8.3+.

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
