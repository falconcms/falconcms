# Contributing to FalconCMS

Thanks for considering a contribution. This repo is the free, MIT-licensed core
(`falconcms/falconcms`) — page builder, CMS, e-commerce, hooks API. (Pro is a separate
private package and isn't covered by this guide.)

## Getting set up

```bash
git clone https://github.com/falconcms/falconcms.git
cd falconcms
composer install
```

The suite runs on [Testbench](https://packages.tools/testbench), which boots a throwaway
Laravel app around the package and migrates everything into an in-memory SQLite database —
no host site or real database needed. Requires the `pdo_sqlite` PHP extension.

## Before you open a PR

```bash
composer test        # PHPUnit — the full suite
composer lint         # Pint, check-only (what CI runs)
composer format       # Pint, apply the fixes
composer analyse      # PHPStan / Larastan, level 3
```

All four run in CI on every push and pull request (see
[`.github/workflows/ci.yml`](.github/workflows/ci.yml)), across the PHP versions the package
supports (8.3–8.4). A PR won't merge red.

`phpstan-baseline.neon` records the errors that already existed when static analysis was
turned on. Don't add to it to silence a genuine bug in new code — only pre-existing debt.

## Code conventions

- **Theme isolation**: frontend views live under `resources/views/themes/{theme-name}/`.
  Anything placed directly in the root `resources/views/` is blocked from frontend
  rendering (404) — this is enforced, not just a convention.
- **Hooks over hard dependencies**: prefer `add_falcon_filter()` / `add_falcon_action()`
  extension points over reaching into another feature's internals directly.
- **Global helpers go in a topic file**: `src/helpers.php` only loads `src/helpers/*.php`,
  one file per topic (`content.php`, `menus.php`, `shop-tax.php`, …). Put a new helper in
  the file it belongs to, inside an `if (!function_exists('...'))` guard. If no file fits,
  or the file is past roughly 1,000 lines, add a new topic file and list it in
  `src/helpers.php`. Don't add helpers to `src/helpers.php` itself.
- **One class per builder element**: each core element converts between builder JSON and
  its shortcode in its own class, `src/Services/ShortcodeConverter/Elements/<Name>Element.php`,
  which extends `Element` and holds both directions: `toShortcode()` and `fromShortcode()`.
  Change the two together, because a setting that is written but not read back is lost on
  the next save. To add an element:
  1. create the class and add it to `BuilderShortcodeConverter::ELEMENTS`;
  2. mirror it in `public/assets/js/falcon-builder-converter.js` (the canvas's converter);
  3. add its render view in `resources/views/frontend/builder/elements/` and its builder
     component in `resources/views/admin/falcon-builder/partials/components/elements/`.

  Never add a `case` back into `BuilderShortcodeConverter` itself; it is only the entry point
  and the element dispatch. Containers, columns, filter-registered elements and the lossless
  extra attributes live beside the elements in `src/Services/ShortcodeConverter/`
  (`ContainerConverter`, `ColumnConverter`, `CustomElementConverter`, `Fidelity`).
  `BuilderElementRegistryTest` fails for a class that isn't registered or doesn't round-trip.
- **Tests accompany behavior changes**: if you fix a bug or add a feature, add or update a
  test that would have caught it. [Test coverage](#test-coverage) below lists what each
  suite guards.
- Follow the code style Pint already enforces — run `composer format` before committing
  rather than hand-matching it.

## Test coverage

Deliberately concentrated on the money and the parsing of untrusted input.

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

## Reporting a bug

Open a [GitHub issue](https://github.com/falconcms/falconcms/issues) with:

- FalconCMS version (`composer show falconcms/falconcms`), PHP version, Laravel version
- Steps to reproduce
- What you expected vs what happened

## Security issues

Please don't open a public issue for a security vulnerability. Email
tareqcodex@gmail.com instead.

## License

By contributing, you agree your contribution is licensed under the project's
[MIT license](LICENSE).

