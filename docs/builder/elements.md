# Builder Elements

Elements are the content blocks inside columns. Lazy Builder ships with **22 built-in element types**.

## Text Elements

### Heading
A title or subtitle block (H1–H6).

**Settings:**
- Text content
- Tag (H1–H6)
- Font size, weight, line height
- Letter spacing
- Text alignment (left, center, right)
- Color
- CSS Class / CSS ID

### Title
A dynamic post/page title element — automatically pulls the current content's title. Useful in templates, header builder, and single post layouts.

**Settings:**
- Tag (H1–H6)
- Font size, weight, color
- Text alignment

### Text Block
A rich-text paragraph block with a WYSIWYG editor.

**Settings:**
- Content (WYSIWYG)
- Font size, line height
- Text alignment
- Color

### Text
A simple single-line text element with dynamic source support.

**Settings:**
- Text content or dynamic source (post title, site name, etc.)
- Font size, weight, color
- Text alignment

### Ticker
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

### Gallery
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

### Counter
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

### Accordion
Collapsible FAQ / content sections.

**Settings:**
- Items (title + content pairs)
- Single open mode (collapse others on open)
- Icon (arrow / plus)
- Icon position (left / right)
- Colors, border, padding

### Tabs
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
- Border radius
- Padding
- Icon (FontAwesome class + position)
- Full width toggle

### Card
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

### Icon Box
An icon paired with a title and description.

**Settings:**
- Icon (FontAwesome class)
- Icon size, color, background
- Title
- Description
- Link
- Layout: icon above / icon left

### Icon List
A styled list with icons.

**Settings:**
- Items (icon + text pairs)
- Icon color
- Text size, color
- Gap between items

---

## Post & Dynamic Elements

### Post Grid
Dynamically display posts in a grid.

**Settings:**
- Post type (post / page / product / CPT)
- Limit (number of posts)
- Category filter
- Tag filter
- Order by (date, title, random)
- Order (ASC / DESC)
- Columns (1–4)
- Show: thumbnail, title, excerpt, date, author, read more button
- Pagination on/off

### Post Content
Renders the full content of the current post — used in single post/page templates.

### Post Meta
Displays post metadata — author, date, category, tags, etc.

**Settings:**
- Select which meta fields to show
- Date format
- Separator style

### Star Rating
Display a static star rating.

**Settings:**
- Rating value (0–5)
- Icon color
- Size

---

## Advanced Elements

### HTML
A raw HTML block — paste any embed code or custom markup.

**Settings:**
- Raw HTML / embed code

### Menu
Render a navigation menu inline inside the builder canvas — primarily used in the header builder.

**Settings:**
- Select menu (by location or slug)
- Layout: horizontal / vertical
- Mega menu support

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
