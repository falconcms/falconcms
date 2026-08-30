# Documentation scripts

## `screenshots.mjs`

Regenerates every image under `docs/public/screenshots/` from the live demo at
<https://demo.falconcms.com>. Those images are what the README and the documentation
pages embed, so this is how they stay current after a UI change.

```bash
cd docs

npm run docs:screenshots                      # all targets
npm run docs:screenshots -- dashboard orders  # just these two
```

The first run installs Playwright and its Chromium build with `--no-save`. That is
deliberate: the docs deploy workflow runs `npm install` in this directory, and a saved
`playwright` dependency would make every deploy download a browser it never uses.

### How it works

Every target is captured at a 1440×900 viewport with `deviceScaleFactor: 2`, then
downscaled to 1600px wide and encoded as WebP — by Chromium itself, so the script needs
no native image library. The whole set is about 2 MB.

Because the viewport, the device scale and the settle delay are the same for all of
them, re-running the script replaces the set consistently rather than leaving one page
shot at a different width than its neighbours.

### When a capture comes back wrong

- **Sign-in fails.** The demo prints its current credentials on the login page. Pass a
  new one with `DEMO_PASSWORD=… npm run docs:screenshots`. The script stops on a failed
  sign-in instead of retrying, because the demo locks an account after repeated
  failures.
- **The builder captures are empty.** They address a specific page by id
  (`BUILDER_PAGE_ID` at the top of the script). The demo is reseeded from time to time;
  when it is, pick a new page from `/admin/pages` and update the constant.
- **A panel did not open.** The builder targets drive the UI through `steps`, which
  clicks real selectors — `.topbar-icon[title="Responsive"]` and the navigator nodes. A
  markup change in the builder is the usual cause.

### Pointing it at something else

`DEMO_URL`, `DEMO_EMAIL` and `DEMO_PASSWORD` override the demo. A local site works too:

```bash
DEMO_URL=http://falcon.test DEMO_EMAIL=admin@example.com DEMO_PASSWORD=secret \
  npm run docs:screenshots
```

Prefer the public demo for anything that ships. It carries demo content that is meant to
be seen, and it is the same site a reader can click through for themselves.
