# Builder Elements

Elements marked <Badge type="warning" text="Pro" /> need a [Pro licence](/guide/pro). The builder shows them in the palette with a lock badge and prompts to upgrade when you add one. Every other element is part of the free core.

Elements are the content blocks inside columns.

The complete, current list — with what each one does and which need Pro — is in
[Builder Overview → All Built-in Elements](/builder/overview#all-built-in-elements). This page
covers the settings behind them.

![The settings panel for an Icon Box element](/screenshots/builder-element-settings.webp)

*Selecting an element opens its settings in the sidebar — here an Icon Box, with the icon picker, layout and alignment.*

## Text Elements

### Title
A heading. Type it yourself, or pick a [dynamic value](#dynamic-values) (the database icon
next to the field) to fill it from the current post, page or product — useful in templates,
the header builder and single-post layouts.

![The Title element's rich editor in the settings panel](/screenshots/builder-title-editor.webp)

*The Title field is a rich editor with the formatting a heading can hold.*

**The Title field** is the same editor as the Text Block's, with the tools that make sense inside
a heading: **bold**, *italic*, underline, strikethrough, text colour, background colour, link,
clear formatting, and the code view for HTML. Press **Enter** to start a new line inside the
heading. Paragraphs, lists and tables are left out on purpose, because a heading cannot contain
them.

Titles written before the editor existed open exactly as they were saved. A title is only
rewritten when you edit it.

**Settings:**
- **HTML Heading Tag** (Design tab): H1–H6, `div` or `p`. A new Title starts as **H1**.
  A Title saved before 2.8.3 has no tag stored and renders as **H2**, as it always has, so
  adding the H1 default changed no existing page.
- Font family, size, weight, line height, letter spacing, text transform
- Colour, hover colour, or a gradient
- Text alignment (per device)
- Separator line under the heading
- Text shadow and text stroke
- Title link

::: tip One H1 per page
Search engines read the H1 as the page's main heading. If you add a second Title to a page,
switch it to H2 or lower under **Design → HTML Heading Tag**.
:::

### Text Block
A rich-text paragraph block with a WYSIWYG editor.

**Settings:**
- Content (WYSIWYG)
- Font size, line height
- Text alignment
- Color

### Ticker <Badge type="warning" text="Pro" />
A horizontally scrolling text marquee — great for announcements or news tickers.

**Settings:**
- Items (list of text lines)
- Speed
- Direction (left / right)
- Separator style
- Font size, color, background

---

## Media Elements

### Image
A single image with optional link.

**Settings:**
- **Dynamic Source** — pull the image automatically from context:
  - `Feature Image` — the current post's featured image
  - `Author Avatar` — the post author's avatar
  - `Site Logo` — the logo set in Customizer → General → Logo
- Image URL (media library picker, used when no dynamic source is selected)
- Alt text
- Width / Max Width
- **Link URL** — static URL or dynamic source:
  - `Site URL` — links to the site homepage
  - `Post URL` — links to the current post's permalink
- Link target (`_blank`, `_self`)
- Aspect ratio + focus point (X/Y) for cropped images
- Border radius, border (per-side), box shadow
- Hover effect
- Sticky width (shrinks when header is sticky)

### Gallery <Badge type="warning" text="Pro" />
A responsive image grid or slider.

**Settings:**
- Images (multi-select from media library)
- Layout: grid / masonry / slider
- Columns (2, 3, 4)
- Gap between images
- Lightbox on click

### Video
Embed YouTube, Vimeo, or self-hosted video.

**Settings:**
- Video URL
- Autoplay, Loop, Mute
- Show/hide controls
- Aspect ratio

---

## Interactive Elements

### Counter <Badge type="warning" text="Pro" />
An animated number that counts up on scroll.

**Settings:**
- Starting number
- Final number
- Duration (ms)
- Prefix (e.g., `$`)
- Suffix (e.g., `+`, `k`)
- Label (caption below number)
- Font size, color

**Example:** `$` → `10,000` → `+` counted from `0` in `2000ms`.

### Accordion <Badge type="warning" text="Pro" />
Collapsible FAQ / content sections.

**Settings:**
- Items (title + content pairs)
- Single open mode (collapse others on open)
- Icon (arrow / plus)
- Icon position (left / right)
- Colors, border, padding

### Tabs <Badge type="warning" text="Pro" />
Tabbed content panels.

**Settings:**
- Tabs (label + content pairs)
- Default active tab
- Tab alignment (left, center, right)
- Style (underline / pill / border)

---

## Layout Elements

### Button
A call-to-action button.

**Settings:**
- Text
- Link URL + target
- Style: solid / outline / ghost
- Colors: background, text, border
- Hover: background, text, border
- **Hover Border** <Badge type="tip" text="2.7.0" /> — width per edge and colour, sitting
  directly under the border they change. Leave a field empty to keep the border's own value
  on hover; `0` is a real answer and means "no border on that edge while hovered".
- **Hover Animation** <Badge type="tip" text="2.7.0" /> — *Lift Up*, *Sink Down*, *Grow*,
  *Shrink*, *Glow* or *Pulse*. Readers whose system asks for reduced motion get none of them.
- Border radius
- Padding
- Icon (FontAwesome class + position)
- Full width toggle

::: tip Alignment and Full Width
With **Full Width (Span)** off, *Alignment* places the button within its row. With it on the
button already fills the row, so *Alignment* moves the label inside the button instead.
Set per device, like the other responsive controls.
:::

### Card <Badge type="warning" text="Pro" />
A content card with image, title, description, and optional button.

**Settings:**
- Image (media library)
- Title, description
- Button text + link
- Border radius, shadow
- Hover effect

### Spacer
Empty vertical space for layout control.

**Settings:**
- Height (px)
- Desktop / Tablet / Mobile heights (responsive)

### Icon Box <Badge type="warning" text="Pro" />
An icon paired with a title and description.

**Settings:**
- Icon (FontAwesome class)
- Icon size, color, background
- Title
- Description
- Link
- Layout: icon above / icon left

### Item List <Badge type="warning" text="Pro" />
A styled list with icons.

![Item List items, each with drag, duplicate and delete controls](/screenshots/builder-item-list.webp)

*Each item has a drag grip, a duplicate button and a delete button.*

**Items:**
- **Add Item** adds a new item at the end.
- **Duplicate** (the copy icon) copies the whole item — icon, icon colour, text and link —
  right below the original. Handy when items differ by a word or two.
- **Drag** an item by its grip to reorder the list.
- Click an item to open it and change its icon, colour, text or link.

**Settings:**
- Items (icon + text pairs)
- Icon color
- Text size, color
- Gap between items

### Table <Badge type="warning" text="Pro" />
A data table with an optional header row and column, sortable columns and a sticky header.

![The Table element's Cells grid, with drag, duplicate and delete on each row](/screenshots/builder-table-rows.webp)

*Every row in the Cells grid has a drag grip, a duplicate button and a delete button.*

**Filling it in:**
- **Paste a table** — Markdown, CSV, or a paste from a spreadsheet. A Markdown alignment row
  (`:---:`) sets the column alignments. Importing replaces the grid.
- **Cells grid** — type into each cell. Cells take `` `code` ``, `**bold**`, `*italic*`,
  `[text](url)` and `<br>`; anything else is shown as written, so a cell can never carry script.
- **+ Row** / **+ Column** add one at the end. Each column has its alignment, a width, arrows to
  move it, and × to delete it.

**Rows:**
- **Drag** a row by its grip (⋮⋮) and drop it where it should go. The row under the pointer is
  outlined. Only the grip starts a drag, so selecting text inside a cell works as usual.
- **Duplicate** copies the row right below the original. Duplicating the header row gives a
  body row with the same text; the header itself stays first.
- **Delete** removes the row. A table always keeps at least one row.

**Settings:**
- First row is a header / First column is a header
- Caption, shown under the table
- Readers can sort by clicking a header; the header can stay visible while scrolling
- Max height (0 shows the whole table; above that it scrolls)
- On small screens: scroll sideways, or stack each row as a card

---

## Post & Dynamic Elements

### Post Content
Renders the full content of the current post — used in single post/page templates.

### Post Meta
Displays post metadata — author, date, category, tags, etc.

**Settings:**
- Select which meta fields to show
- Date format
- Separator style

### Star Rating <Badge type="warning" text="Pro" />
Display a static star rating.

**Settings:**
- Rating value (0–5)
- Icon color
- Size

---

## Advanced Elements

### HTML Block <Badge type="warning" text="Pro" />
A raw HTML block — paste any embed code or custom markup.

**Settings:**
- Raw HTML / embed code

### Menu <Badge type="warning" text="Pro" />
Render a navigation menu inline inside the builder canvas — primarily used in the header builder.

**Settings:**
- Select menu (by location or slug)
- Layout: horizontal / vertical
- Item padding, spacing, border radius
- Colours and hover colours for text, background and border
- Mega menu support

The hover colours and border also describe the **current** item — the page being viewed, or
on a landing page the section being read. See
[One-page menus](/guide/menus#one-page-menus).

---

## Element Settings (All Elements)

Every element has these shared settings in the **General** tab:

### Element Visibility
Control which devices see this element:

```
[ Desktop ] [ Tablet ] [ Mobile ]
```

Click a device to toggle visibility. Useful for showing different content per breakpoint.

### CSS Class
Add one or more custom CSS classes to the element wrapper.

### CSS ID
Add a unique ID for anchor links or JavaScript targeting.

### Extra Options Tab
- Custom CSS (applied to this element only)
- Hover effects
- Animation on scroll (fade, slide, zoom)
- Z-index
- Position (static, relative, absolute)

---

## Dynamic Values

Many text fields support dynamic tokens — they're replaced at render time:

| Token | Replaced with |
|---|---|
| `{post_title}` | Current post's title |
| `{post_excerpt}` | Post excerpt |
| `{post_date}` | Published date |
| `{site_name}` | Site name from settings |
| `{current_date}` | Today's date |
| `{author_name}` | Post author's name |

**Example:** Set a Heading text to `Welcome back, {author_name}!` for a personalized greeting.

---

## Older layouts

A page saved by an earlier version may still contain one of these. They keep rendering exactly
as they did, but they are no longer offered in the element picker — **Title**, **Text Block**
and **Card** cover what they did.

### Heading *(legacy)*
A title or subtitle block (H1–H6). Settings: text, tag, font size / weight / line height,
letter spacing, alignment, colour, CSS class and ID.

### Text *(legacy)*
A single-line text element with dynamic source support. Settings: text or dynamic source
(post title, site name, …), font size / weight / colour, alignment.

### Post Grid *(legacy)*
Posts in a grid. Settings: post type, limit, category and tag filters, order by and direction,
columns, which parts to show (thumbnail, title, excerpt, date, author, read-more) and
pagination.
