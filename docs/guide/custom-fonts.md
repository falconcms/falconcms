---
title: Custom Fonts — use your own font files in FalconCMS
description: Upload your own font files and use them in the theme's typography, every Falcon Builder element and Motion Visual text, next to the Google Fonts list.
---

# Custom Fonts <Badge type="tip" text="2.8.8" />

Use your own font files (a brand font, a font that Google Fonts does not have) anywhere a font is
chosen:

- the theme's typography in the Customizer
- every typography setting of the [Falcon Builder](/builder/overview)
- text in a [Motion Visual](/builder/motion-visual)

Custom fonts are part of the free core.

::: warning Check the licence
Make sure each font's licence allows using it on a website. Many desktop font licences do not.
:::

## Adding a font

1. Upload the font files to the [Media Library](/guide/media). FalconCMS reads **WOFF2**, **WOFF**,
   **TTF** and **OTF**.
2. Open **Falcon Builder → Customizer → Typography → Custom Font**.
3. Add one box for each **font face**, which is one weight and style of a font:
   - **Font Name**: the name you will pick the font by, for example `Brand Sans`. Boxes with the
     same name together make one family with several weights.
   - **Font Weight**: choose **Use what the file says** to read the weight from the file. Turn
     on **Variable** for a variable font, which holds a range of weights in one file. The range
     is then read from the file, or you set it with **From**.
   - **Font Style**: Normal or Italic.
   - **Files**: choose a file for each format you have, from the Media Library.
4. Press **Save Changes**.

The weight and style are read from TTF, OTF and WOFF files. A WOFF2 file cannot be read on the
server, so its weight is guessed from the file name. The screen says so, and you should check it.

## Where the font shows

A saved font appears under **Custom Fonts** in every font picker, above the Google Fonts list.
Only the weights you added are offered, so the browser never draws a fake bold.

## What happens on the page

- **Only your own files are used.** A file must be in the Media Library, and its contents must
  really be the format of the slot it was chosen for. A box with no usable file is dropped on
  save, with a note.
- **A WOFF is made for you.** If you give a TTF or OTF but no WOFF, FalconCMS makes a compact
  WOFF copy on save. Copies no font uses any more are removed. Your Media Library files are never
  changed.
- **The smallest file loads first.** The browser is offered WOFF2, then WOFF, then TTF, then OTF.
- **Text stays readable while the font loads.** It uses a fallback (sans-serif, serif,
  monospace, cursive or system-ui) until the font arrives.
- **Custom fonts are never requested from Google.** They load from your own site, so they work
  with **Host Google Fonts Locally** on or off.

A site can hold up to 100 font faces. The fonts are saved with the Customizer's settings.
