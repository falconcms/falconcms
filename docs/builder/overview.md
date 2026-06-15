# Falcon Builder Overview

Falcon Builder is the visual drag-and-drop page builder built into Falcon CMS. Build complex page layouts directly inside the admin dashboard — no code, no templates, no page refreshes.

It works for **Pages**, **Posts**, **Custom Post Types**, the **Header**, and the **Footer**.

---

## How to Open the Builder

### For Pages & Posts
1. Go to **Admin → Pages** (or Posts / any CPT)
2. Open or create a content item
3. Click **Open Builder** in the editor toolbar
4. The builder canvas opens in full-screen mode

### For Header & Footer
1. Go to **Admin → Appearance → Builder Sections**
2. Click **Edit Header** or **Edit Footer**
3. The same builder opens, scoped to that section

---

## Builder Interface

```
┌─────────────────────────────────────────────────────────┐
│  [← Back]  Page Title       [Desktop][Tablet][Mobile]  │  ← Top bar
│            [Save Draft]  [Publish]                      │
├──────────────┬──────────────────────────────────────────┤
│              │                                          │
│   Sidebar    │           Canvas                         │
│  (Settings)  │   ┌────────────────────────────────┐    │
│              │   │  Container                     │    │
│              │   │  ┌──────────┐  ┌──────────┐   │    │
│              │   │  │ Column 1 │  │ Column 2 │   │    │
│              │   │  │ [Element]│  │ [Element]│   │    │
│              │   │  └──────────┘  └──────────┘   │    │
│              │   └────────────────────────────────┘    │
│              │   [+ Add Container]                      │
└──────────────┴──────────────────────────────────────────┘
```

- **Canvas** — the live preview of your page. Drag, drop, and click to edit
- **Sidebar** — appears on the right when you select an element, showing its settings
- **Top bar** — device preview toggles, save, and publish

---

## Building a Page — Step by Step

### Step 1: Add a Container

Click **+ Add Container** at the bottom of the canvas. A modal opens where you choose the column layout:

| Layout | Use case |
|---|---|
| `1 column` | Full-width hero, banner |
| `1/2 + 1/2` | Two-column content |
| `1/3 + 2/3` | Sidebar + content |
| `1/3 + 1/3 + 1/3` | Three-column features |
| `1/4 × 4` | Four-column cards |

### Step 2: Add Elements to Columns

Click the **+** button inside any column. The element picker opens — choose an element type and it's added instantly.

### Step 3: Edit the Element

Click any element on the canvas to select it. Its settings appear in the **sidebar** on the right. Every change reflects live on the canvas.

### Step 4: Reorder

- **Drag elements** up/down within a column, or move them between columns
- **Drag containers** using the handle on the container toolbar to reorder rows

### Step 5: Save & Publish

- **Autosave** runs every 30 seconds in the background
- **Save Draft** — saves without making changes live
- **Publish / Update** — makes the page live immediately

---

## Container & Column Toolbar

Hover over any container or column to reveal its toolbar:

| Button | Action |
|---|---|
| ⠿ (drag handle) | Drag to reorder |
| ✏️ | Open settings (background, padding, etc.) |
| ⧉ | Duplicate |
| 📚 | Save to Library |
| 🌐 | Save as Global Section |
| 🗑️ | Delete |

---

## All 22 Built-in Elements

| Element | Category | What it does |
|---|---|---|
| **Heading** | Text | H1–H6 title with full typography control |
| **Title** | Text | Dynamic post/page title — auto-filled from content |
| **Text Block** | Text | Rich text paragraph (WYSIWYG editor) |
| **Text** | Text | Single-line text with dynamic source support |
| **Ticker** | Text | Horizontally scrolling announcement ticker |
| **Image** | Media | Single image — static or dynamic (Feature Image, Logo, Author Avatar) |
| **Gallery** | Media | Image grid, masonry, or slider with lightbox |
| **Video** | Media | YouTube, Vimeo, or self-hosted video |
| **Counter** | Interactive | Animated number that counts up on scroll |
| **Accordion** | Interactive | Collapsible FAQ / content sections |
| **Tabs** | Interactive | Tabbed content panels |
| **Button** | Layout | CTA button — solid, outline, or ghost style |
| **Card** | Layout | Image + title + description + button |
| **Spacer** | Layout | Vertical spacing — different per device |
| **Icon Box** | Layout | Icon + title + description, icon above or left |
| **Icon List** | Layout | Styled list with FontAwesome icons |
| **Post Grid** | Dynamic | Query and display posts in a responsive grid |
| **Post Content** | Dynamic | Renders the current post's full content body |
| **Post Meta** | Dynamic | Author, date, category, tags for the current post |
| **Star Rating** | Dynamic | Static star display (0–5 stars) |
| **Menu** | Navigation | Render a navigation menu — used in header builder |
| **HTML** | Advanced | Raw HTML / embed code block |

> **Custom elements** can be registered via the hook API — see [Hooks Reference](/api/hooks).

---

## Responsive Preview

Use the device buttons in the top bar to preview your layout:

- **Desktop** — full width
- **Tablet** — medium breakpoint
- **Mobile** — small breakpoint

Each element, column, and container can be independently hidden per device. See [Device Visibility](/builder/visibility).

---

## Saving Layouts for Reuse

### Library
Save any container or column to the **Library** — reuse it on other pages as an independent copy.

### Global Sections
Save a container as a **Global Section** — edit once, and it updates everywhere it's used across the site.

See [Global Sections](/builder/global-sections) and [Library](/builder/library) for details.
