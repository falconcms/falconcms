# Slides & Backgrounds

A slider is a stack of **slides**. Each slide has its own background, its own set of [layers](/slider/layers), and its own entrance transition. This page covers building slides and everything about backgrounds.

![The Slide tab in the slider editor](/screenshots/slider-slide.webp)

*The **Slide** tab holds everything that belongs to one slide — its name, an optional whole-slide link, its transition and speed, and the background colour and image.*

---

## Managing slides

The **Slides rail** on the left of the editor lists every slide as a thumbnail.

| Action | How |
|---|---|
| **Add a slide** | Click **+** at the bottom of the rail |
| **Select / edit** | Click a thumbnail — the canvas switches to that slide |
| **Reorder** | Drag a thumbnail up or down |
| **Rename** | Open the **Slide** tab → *Slide name* |
| **Duplicate** | **Slide** tab → *Duplicate slide* |
| **Delete** | Hover a thumbnail and click the delete icon |

Open the **Slide** tab in the right panel to edit the currently selected slide.

---

## Backgrounds

Every slide has a background, set in the **Slide** tab. Choose a background **type**:

### Solid colour
A single flat colour behind the layers.

### Gradient
A linear gradient with a **from** colour, **to** colour and **angle** (degrees).

### Image
Pick from the [Media Library](/guide/media). Control how it sits:

| Option | Values |
|---|---|
| **Size** | `cover`, `contain`, `auto`, or a custom `width% height%` |
| **Position** | `center`, the nine anchor points, or a custom `x% y%` |
| **Repeat** | `no-repeat`, `repeat`, `repeat-x`, `repeat-y` |

The first slide's image is treated as the page's **LCP** element and preloaded automatically.

### Video

Set a background **video** in three ways:

- **Self-hosted** — a direct `.mp4` URL from the Media Library. Plays muted, looped and auto-playing; the image (if set) acts as its poster.
- **YouTube** — paste any YouTube URL; it's embedded, muted, looped and sized to *cover* the slide (no player chrome).
- **Vimeo** — paste any Vimeo URL; embedded in background mode.

Video backgrounds are **deferred** — a slide only loads its video when it's about to be shown.

---

## Overlays

An **overlay** sits on top of the image/video but *under* the layers — perfect for darkening a busy video so text stays readable.

- **Colour overlay** — a single colour at an adjustable opacity
- **Gradient overlay** — a linear gradient (from · to · angle) at an adjustable opacity

---

## Motion effects

### Ken Burns
A slow, cinematic zoom/pan on the background image while the slide is shown. Pick a direction (zoom in/out, pan) from the **Slide** tab. Previews live on the canvas.

### Parallax
When enabled (slider **Settings** tab), layers subtly shift as the visitor moves their mouse, giving the slide depth. Parallax respects the visitor's reduced-motion preference when that option is on.

---

## Slide transitions

The **transition** controls how a slide animates *in* when the slider advances to it.

Set a **global** transition for the whole slider (Settings tab), and optionally **override** it per slide (Slide tab). Leave a slide's transition empty to use the global default.

Available transitions:

`fade` · `slide-left` · `slide-right` · `slide-up` · `slide-down` · `zoom` · `zoom-out` · `fade-scale` · `rotate` · `rotate-ccw` · `roll` · `flip` (Y) · `flip-x` · `skew` · `glide-up` · `glide-down` · `blur`

The transition **speed** (ms) is also global with an optional per-slide override. Every transition previews live on the canvas as you pick it.

---

## Whole-slide link

Make an **entire slide clickable** — set a **Slide link** in the Slide tab (with an optional *Open in a new tab*).

Falcon Slider is smart about precedence:

- **Interactive layers** (buttons, layers with their own link, video, audio, HTML) keep their own click behaviour.
- **Passive areas** — empty space and plain text/image/shape/icon layers — follow the slide link.

```
┌──────────────────────────────────────┐
│  Headline (passive) → slide link      │
│                                       │
│         [ Buy now ] → its own link    │
│                                       │
│  ← empty space anywhere → slide link  │
└──────────────────────────────────────┘
```

This mirrors real anchor semantics, so middle-click, right-click → *Open in new tab*, and the *new tab* option all work as expected.

---

## Next

- [Layers](/slider/layers) — add text, images, buttons and more on top of the background
- [Animations](/slider/animations) — animate each layer in
