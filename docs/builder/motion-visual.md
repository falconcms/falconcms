---
title: Motion Visual — animated dots, shapes, text and pictures in the Falcon Builder
description: Motion Visual adds an animated layer of drifting dots, shapes, text and your own pictures across a whole column or container in the Falcon Builder, free in the core.
---

# Motion Visual <Badge type="tip" text="2.8.7" />

**Motion Visual** puts an animated layer across a whole column or container: drifting dots,
shapes, text and your own pictures. The layer sits behind the content or in front of it. Motion
Visual is part of the free core.

## Adding a Motion Visual

1. Select a **column** or **container**. Nested columns work too.
2. Open the **Extra** tab and find **Motion Visual**.
3. Under **Start from a preset**, pick a preset, then change anything you like.

There are seven presets: **Floating Dots**, **Connected Dots**, **Soft Bubbles**, **Geometric**,
**Blobs**, **Big Word** and **Custom** (an empty layer to build yourself).

The builder canvas shows the real layer. Drag an item to move it, and use its handles to resize
or turn it.

The panel's switch turns the visual off without losing its settings. A visual that is off adds
nothing to the page: no markup, no style and no script.

## Layer and colors

- **Layer**: *Behind* the content or *In front* of it. A layer in front lets clicks pass
  through to the content below.
- **Main color** and **Accent**: left empty, they follow the theme's colors. Items can use
  *Main*, *Accent* or *Random*. Random keeps changing by itself.
- **Speed**, **opacity**, and whether the layer **follows the mouse**.

## Dots

Dots are drawn on a canvas. You set:

- how many dots there are, their size, color and speed
- **Join near dots with lines**, and the distance at which they join
- **When the mouse comes near**: the dots move away, are pulled in, or do nothing

## Shapes, text and pictures

Under **Shapes & pictures** you add items to the layer:

- **Shape**: 39 shapes, among them circles, rings, stars, blobs, waves, hearts and arrows.
- **Picture**: any image from the [Media Library](/guide/media).
- **Text**: drawn filled, as an outline, or as a gradient from the main color to the accent. It
  can use the page font, Sans, Serif, Mono or any of your [custom fonts](/guide/custom-fonts). Its
  size is a share of the layer, so the text keeps its place on every screen size.

Each item has its own position, size, turn, color, opacity, blur and mouse depth. Each item also
gets one animation: **float**, **drift**, **rotate**, **pulse**, **swing**, **orbit** or
**bounce**.

## Phones, tablets and reduced motion

Tablets and phones each have their own choice:

- **Full motion**
- **Calmer**: fewer dots, and the layer does not follow the touch
- **Still**: the layer is drawn but does not move
- **Hidden**

Visitors whose device asks for reduced motion always see the layer still.

## Performance

Shapes animate with CSS alone. One small script draws the dots and follows the mouse. It loads
only on pages that have a visual, and it pauses while the layer is off screen.

## For developers

Plugins and themes can add their own shapes and presets with two filters. Keys use lowercase
letters, numbers and dashes.

```php
// A shape: SVG drawn on a 0–100 square in currentColor, so the color setting paints it.
add_falcon_filter('falcon_motion_visual_shapes', function ($shapes) {
    $shapes['brand-mark'] = [
        'name' => 'Brand mark',
        'svg'  => '<path d="M10 90 L50 10 L90 90 Z" fill="currentColor"/>',
    ];
    return $shapes;
});

// A preset: a starting point that copies its settings into the panel.
add_falcon_filter('falcon_motion_visual_presets', function ($presets) {
    $presets['brand-dots'] = [
        'name'        => 'Brand Dots',
        'description' => 'Linked dots in the brand colors',
        'settings'    => ['mv_dots' => true, 'mv_dots_count' => 40, 'mv_dots_links' => true, 'items' => []],
    ];
    return $presets;
});
```

The shape's SVG is trusted like a view, so only use markup you wrote. Pictures that users upload
never go through this filter.
