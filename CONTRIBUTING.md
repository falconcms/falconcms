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
supports (8.1–8.4). A PR won't merge red.

`phpstan-baseline.neon` records the errors that already existed when static analysis was
turned on. Don't add to it to silence a genuine bug in new code — only pre-existing debt.

## Code conventions

- **Theme isolation**: frontend views live under `resources/views/themes/{theme-name}/`.
  Anything placed directly in the root `resources/views/` is blocked from frontend
  rendering (404) — this is enforced, not just a convention.
- **Hooks over hard dependencies**: prefer `add_falcon_filter()` / `add_falcon_action()`
  extension points over reaching into another feature's internals directly.
- **Tests accompany behavior changes**: if you fix a bug or add a feature, add or update a
  test that would have caught it. See the README's test-coverage table for the areas each
  suite guards.
- Follow the code style Pint already enforces — run `composer format` before committing
  rather than hand-matching it.

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
