---
title: Off-Canvas Panels — slide-in drawers and popups in the Falcon Builder
description: Design slide-in drawers and popups with the Falcon Builder, open them from any link or menu item or automatically, and switch them on and off on a schedule.
---

# Off-Canvas Panels <Badge type="warning" text="Pro" />

An **off-canvas panel** is a slide-in drawer or popup that you design with the
[Falcon Builder](/builder/overview). Typical uses are a mobile menu, a cart sidebar or a newsletter
popup. A panel opens from any link, button or menu item, or by itself when a page loads.

::: info Pro
Creating and editing panels needs a [Pro licence](/guide/pro). Panels made earlier keep working on
the site without one.
:::

## Creating a panel

1. Go to **Falcon Builder → Library** and open the **Off-Canvas** tab.
2. Click **New Off-Canvas** and give the panel a name.
3. Design its content in the builder. **Panel Options** holds the panel's own settings.

The Library can also duplicate, export, import and delete panels. Each panel has a switch that
turns it off without deleting it.

## Opening and closing a panel

Every panel has a **trigger link**, made from its name when you create it, for example
`#offcanvas-mobile-menu`. The trigger link never changes, even if you rename the panel, so links
to it keep working.

To open a panel, use any of these:

- a link, button or menu item whose URL is the trigger link
- **Open Off-Canvas** as a link's dynamic source in the builder
- the **Off-Canvas** box under **Appearance → Menus**, which adds a menu item that opens a panel
- an element with the attribute `data-falcon-offcanvas="mobile-menu"` (the panel's slug)

A link to `#offcanvas-close` closes the panel that is open. A button inside the panel is the
usual place for it.

A page only loads the panels it links to, or that open on it by themselves. A panel nothing
points at costs the page nothing.

## Panel options

### Panel

- **Position**: left, right, top, bottom or center. Position, size, corner radius and the close
  button's size can differ on tablet and mobile, so a drawer on the right on desktop can be a
  sheet at the bottom on a phone.
- **Width** in px, % or vw, and **height** in px or vh. A height of 0 fits the content.
- Background color, corner radius and shadow.
- **Animation**: slide, fade, zoom or none, and its **duration**.

### Overlay

The overlay is the shaded area behind the panel. You set its color, **Overlay Opacity** and
**Background Blur**.

### Closing

- **Close on Overlay Click**
- **Close with Esc Key**
- a close button, with its color and size
- **Lock Page Scroll While Open**

::: warning Phones have no Esc key
If a panel has no close button and no overlay click, visitors on a phone cannot close it. Add a
button inside it that links to `#offcanvas-close`.
:::

### Auto Open

**Open By Itself** decides whether the panel opens without a click:

- **Never — only from a link**
- **After the page loads**, after a **Delay** in seconds
- **After scrolling down** to a **Scroll Depth** in percent
- **When leaving the page (exit intent)**

**How Often** sets how often a visitor sees it: **Every page view**, **Once per visit**, or **Once
every few days**, with **Show Again After** a number of days.

**On Pages** sets where it opens: the **Entire site**, **Only on…** or **Everywhere except…**.
You can pick pages, posts, archives and other targets, as in Layout conditions. You can also type
an address such as `/blog/*`.

**On Devices** turns auto-open on or off for desktop, tablet and mobile.

### Schedule <Badge type="tip" text="2.9.3" />

**Turn On At** and **Turn Off At** switch the whole panel on and off at a date and time. Times use
the site's timezone (**Settings → General**).

- Leave **Turn On At** empty to start now, and **Turn Off At** empty to never stop.
- Outside these times the panel is off everywhere. Its links do nothing and it never opens by
  itself, exactly as if its switch in the Library were off.
- Cached pages stop showing the panel on the minute it ends.
- A visitor who has the panel open when it ends sees it close.

The options show whether the panel is live now, waiting to start, or finished.
