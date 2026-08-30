/**
 * Regenerates every screenshot under docs/public/screenshots/ from the live demo.
 *
 * The captures are deliberately reproducible: one viewport, one device scale, one
 * settle delay for every target. Re-running this after a UI change replaces the whole
 * set consistently instead of leaving a page shot at a different width than its
 * neighbours.
 *
 * Usage (from docs/):
 *
 *   npm run docs:screenshots                     # everything
 *   npm run docs:screenshots -- dashboard orders # named targets only
 *
 * Playwright is installed with --no-save rather than being a devDependency: the docs
 * deploy workflow runs `npm install` in this directory, and a saved playwright would
 * make every deploy download Chromium.
 *
 * Set DEMO_PASSWORD if the demo credentials change; the current ones are printed on
 * the demo login page itself.
 */

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync, readFileSync, rmSync, statSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = resolve(ROOT, 'docs/public/screenshots');
const TMP = resolve(ROOT, 'docs/.screenshots-tmp');

const BASE = process.env.DEMO_URL || 'https://demo.falconcms.com';
const EMAIL = process.env.DEMO_EMAIL || 'admin@falconcms.demo';
const PASSWORD = process.env.DEMO_PASSWORD || 'FalconDemo2025!';

/** Published width. The captures are taken at 2x and downscaled to this. */
const WIDTH = 1600;
const QUALITY = 0.92;

const VIEWPORT = { width: 1440, height: 900 };

/**
 * A page whose id is baked into the URL. The demo is reseeded from time to time, so
 * if the builder captures come back empty this is the first thing to check.
 */
const BUILDER_PAGE_ID = 47;

/** Likewise for the slider the Falcon Slider captures open. */
const SLIDER_ID = 1;

/**
 * The slider editor's right-hand panel is a row of Material-symbol ligature icons
 * with no title or data attribute, so the icon name is the only thing to match on.
 * Scoping to `.fse-itab` matters: the same ligatures appear elsewhere on the page —
 * in the CMS sidebar, on the slide thumbnails, and inside zero-sized hidden buttons
 * — and matching one of those either navigates away or times out.
 */
const openSliderTab = async (page, icon) => {
  await page.locator(`.fse-itab:has-text("${icon}")`).click();
  await page.waitForTimeout(1800);
};

/**
 * name -> how to reach it. `steps` runs after the page has settled and before the
 * capture; it is where a panel gets opened or a device preview selected.
 */
const TARGETS = [
  { name: 'dashboard',                url: '/admin' },
  { name: 'posts',                    url: '/admin/posts' },
  { name: 'page-editor',              url: `/admin/pages/${BUILDER_PAGE_ID}/edit` },
  { name: 'media-library',            url: '/admin/media' },
  { name: 'custom-post-types',        url: '/admin/acpt/cpt' },
  { name: 'menus',                    url: '/admin/menus' },
  { name: 'widgets',                  url: '/admin/widgets' },
  { name: 'themes',                   url: '/admin/themes' },
  { name: 'customizer',               url: '/admin/customizer' },
  { name: 'layout-builder',           url: '/admin/falcon-builder-sections' },
  { name: 'plugins',                  url: '/admin/plugins' },
  { name: 'roles',                    url: '/admin/roles' },
  { name: 'languages',                url: '/admin/languages' },
  { name: 'settings',                 url: '/admin/settings' },
  { name: 'products',                 url: '/admin/posts?type=product' },
  { name: 'orders',                   url: '/admin/shop/orders' },
  { name: 'shop-overview',            url: '/admin/shop/overview' },
  { name: 'promotion-editor',         url: '/admin/shop/promotions/create' },
  { name: 'site-frontend',            url: '/' },
  { name: 'storefront',               url: '/product' },

  // Shipping, tax and coupons are tabs of one Alpine-driven settings page. The tab
  // is read from `?tab=` on load, so each one is reachable as its own URL.
  //
  // The demo has no zones, rates or coupons configured, and the empty states show
  // none of the fields the guide describes. Each of these opens the form the button
  // reveals — all of it client-side Alpine state, so nothing is written to the demo
  // as long as the capture never clicks Save.
  {
    name: 'shipping-zones',
    url: '/admin/shop/settings?tab=shipping',
    steps: async (page) => {
      await page.click('text=Create First Shipping Zone');
      await page.waitForTimeout(1500);
    },
  },
  {
    name: 'tax-rates',
    url: '/admin/shop/settings?tab=tax',
    steps: async (page) => {
      // Unchecked, the whole rate table is hidden behind x-show. Three controls
      // share this name — a hidden input the General tab posts, and a zero-sized
      // checkbox on an inactive tab — so match on the one that is actually laid out.
      await page.locator('input[type=checkbox][name=calc_taxes]:visible').check();
      await page.waitForTimeout(1500);
    },
  },
  {
    name: 'coupons',
    url: '/admin/shop/settings?tab=coupons',
    steps: async (page) => {
      await page.click('text=Add New Coupon');
      await page.waitForTimeout(1000);
      // The new card starts collapsed; its header toggles it open.
      await page.click('text=UNNAMED_COUPON');
      await page.waitForTimeout(1500);
    },
  },

  // Falcon Slider (Pro). SLIDER_ID is the demo's slider; see BUILDER_PAGE_ID above
  // for why an id baked into a URL is the first thing to check when a capture is
  // empty.
  { name: 'sliders',                  url: '/admin/falcon-slider' },
  { name: 'slider-editor',            url: `/admin/falcon-slider/${SLIDER_ID}/edit`, settle: 6000 },
  {
    // The layer panel, with a layer selected on the canvas. Clicking its timeline
    // bar selects it; the bars carry no label, hence the class.
    name: 'slider-layer',
    url: `/admin/falcon-slider/${SLIDER_ID}/edit`,
    settle: 6000,
    steps: async (page) => {
      await page.locator('.fse-tl-bar').first().click();
      await page.waitForTimeout(2200);
    },
  },
  {
    // Same layer selection, then the properties panel scrolled down to the
    // animation controls. The panel scrolls under the pointer, so the wheel has to
    // be moved over it first.
    name: 'slider-animation',
    url: `/admin/falcon-slider/${SLIDER_ID}/edit`,
    settle: 6000,
    steps: async (page) => {
      await page.locator('.fse-tl-bar').first().click();
      await page.waitForTimeout(1500);
      await page.mouse.move(1300, 600);
      await page.mouse.wheel(0, 2200);
      await page.waitForTimeout(1200);
    },
  },
  {
    name: 'slider-slide',
    url: `/admin/falcon-slider/${SLIDER_ID}/edit`,
    settle: 6000,
    steps: async (page) => openSliderTab(page, 'wallpaper'),
  },
  {
    name: 'slider-navigation',
    url: `/admin/falcon-slider/${SLIDER_ID}/edit`,
    settle: 6000,
    steps: async (page) => openSliderTab(page, 'swipe'),
  },

  // Falcon Builder. Each of these enters the builder fresh so one capture's open
  // panel or device preview cannot leak into the next.
  {
    name: 'page-builder',
    url: `/admin/falcon-builder/${BUILDER_PAGE_ID}`,
    settle: 6000,
  },
  {
    name: 'builder-canvas',
    url: `/admin/falcon-builder/${BUILDER_PAGE_ID}`,
    settle: 6000,
    steps: async (page) => {
      await page.mouse.wheel(0, 2600);
      await page.waitForTimeout(2000);
    },
  },
  {
    name: 'builder-element-settings',
    url: `/admin/falcon-builder/${BUILDER_PAGE_ID}`,
    settle: 6000,
    steps: async (page) => {
      await page.click('text=Icon Box >> nth=0');
      await page.waitForTimeout(2000);
    },
  },
  {
    name: 'builder-responsive',
    url: `/admin/falcon-builder/${BUILDER_PAGE_ID}`,
    settle: 6000,
    steps: async (page) => {
      // The control cycles desktop -> tablet -> mobile.
      await page.click('.topbar-icon[title="Responsive"]');
      await page.waitForTimeout(2500);
      await page.click('.topbar-icon[title="Responsive"]');
      await page.waitForTimeout(2500);
    },
  },
];

/** Scrollbars differ between machines; hiding them keeps captures byte-comparable. */
const NO_SCROLLBARS = `
  ::-webkit-scrollbar { width: 0 !important; height: 0 !important; }
  * { scrollbar-width: none !important; }
`;

const wanted = process.argv.slice(2);
const targets = wanted.length
  ? TARGETS.filter((t) => wanted.includes(t.name))
  : TARGETS;

if (!targets.length) {
  console.error(`No target matched ${wanted.join(', ')}.`);
  console.error('Known targets: ' + TARGETS.map((t) => t.name).join(', '));
  process.exit(1);
}

mkdirSync(OUT, { recursive: true });
mkdirSync(TMP, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: VIEWPORT, deviceScaleFactor: 2 });
const page = await context.newPage();

// --- sign in -------------------------------------------------------------------
// The demo locks an account after repeated failures, so a wrong password should stop
// the run rather than be retried across every target.
await page.goto(`${BASE}/falcon-admin`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name=email]', EMAIL);
await page.fill('input[name=password]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

if (!page.url().includes('/admin')) {
  console.error(`Sign-in failed — still on ${page.url()}.`);
  console.error('The demo prints its current credentials on the login page itself.');
  await browser.close();
  process.exit(1);
}

// --- capture -------------------------------------------------------------------
const captured = [];

for (const target of targets) {
  try {
    await page.goto(BASE + target.url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.addStyleTag({ content: NO_SCROLLBARS });
    await page.waitForTimeout(target.settle ?? 1800);
    if (target.steps) await target.steps(page);

    const png = resolve(TMP, `${target.name}.png`);
    await page.screenshot({ path: png });
    captured.push({ name: target.name, png });
    console.log(`captured  ${target.name}`);
  } catch (error) {
    console.error(`FAILED    ${target.name} — ${error.message.split('\n')[0]}`);
  }
}

// --- downscale and encode ------------------------------------------------------
// Chromium does the resampling and the WebP encoding, which keeps this script free of
// a native image dependency. The source has to arrive as a data URL: a file:// image
// taints the canvas and cannot be read back out.
const encoder = await context.newPage();
writeFileSync(resolve(TMP, 'encoder.html'), '<!doctype html><meta charset="utf-8">');
await encoder.goto(pathToFileURL(resolve(TMP, 'encoder.html')).href);

for (const { name, png } of captured) {
  const dataUrl = 'data:image/png;base64,' + readFileSync(png).toString('base64');

  const base64 = await encoder.evaluate(async ({ dataUrl, width, quality }) => {
    const image = new Image();
    image.src = dataUrl;
    await image.decode();

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = Math.round(image.naturalHeight * (width / image.naturalWidth));

    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/webp', quality).split(',')[1];
  }, { dataUrl, width: WIDTH, quality: QUALITY });

  const webp = resolve(OUT, `${name}.webp`);
  writeFileSync(webp, Buffer.from(base64, 'base64'));
  console.log(`written   ${name}.webp  ${Math.round(statSync(webp).size / 1024)} KB`);
}

await browser.close();
rmSync(TMP, { recursive: true, force: true });

console.log(`\n${captured.length}/${targets.length} screenshots written to docs/public/screenshots/`);
