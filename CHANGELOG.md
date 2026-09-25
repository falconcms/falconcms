# Changelog

All notable changes to FalconCMS are recorded here.

This project follows [Semantic Versioning](https://semver.org/).

Full release notes, with the reasoning behind each change, live at
<https://falconcms.github.io/falconcms/changelog>.

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
