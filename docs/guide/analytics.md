# Analytics <Badge type="warning" text="Pro" />

**Admin → Analytics** answers two different questions about your traffic, and it is worth
being clear which is which:

- **Visitors** — how many *people*.
- **Page Views** — how much they *read*.

Someone who opens six pages is one visitor and six page views. Every card that says
"visitors" counts people; the traffic series and Page Views count reading.

---

## Date range

`Today · 7 days · 30 days · 90 days · 1 year`

The page opens on **Today** <Badge type="tip" text="2.7.0" />, because that is what it is
usually opened to find out. The range applies to every card on the page except the real-time
panel, which always shows the last 30 minutes.

Days are your site's days, not the server's — the boundary is midnight in the timezone set in
**Settings → General**. A site set to Asia/Dhaka gets a "today" that begins six hours before
the server's own midnight.

---

## How a visitor is counted

::: tip One visitor per day
Someone who comes back four times in an afternoon is **one** visitor that day. If they return
tomorrow they are counted **again**, and from that second day on they are counted as
**returning**.
:::

Visitors are recognised by IP address. That is the honest limit of the figure: it counts
devices on a network rather than named people, so an office behind one address reads as one
visitor, and a phone moving between wi-fi and mobile data can read as two.

::: warning Numbers changed in 2.7.0
Before 2.7.0, a range counted distinct addresses across the whole period — so a reader who
came back on ten different days was still a single visitor, and a month looked no busier than
a day. The same stored data now reads **higher** on the longer ranges. Nothing was recorded
differently; the counting was corrected. **Today** is unchanged.
:::

### New vs Returning

A day is *returning* when that address has been here on an earlier day — including earlier
days inside the range being viewed. So a reader who first arrived on Monday and came back all
week is one new visitor and four returns, and the two always add up to the headline figure.

---

## Real-time panel

The last 30 minutes, refreshed every 12 seconds.

- **Active Now** — people seen in the last 5 minutes.
- **Visitors per minute** — people, not page views, so one reader opening four pages in a
  minute draws a bar of one.
- **Active Pages** — every page those active people have read in the window
  <Badge type="tip" text="2.7.0" />, not only the page each of them is on at this instant.
  Each row counts people, so re-reading a page never counts twice. The column therefore adds
  up to more than the Active Now figure, which is correct: it counts pages read, not people.
- **Live Visitors** — one row per person, showing their most recent activity.

---

## Web Vitals

Web Vitals is part of the free core: it needs no Pro licence.

**Analytics → Web Vitals** shows how fast your pages are for the people who actually open them,
measured in their browsers:

| Metric | Measures | Good |
|---|---|---|
| **LCP**: Largest Contentful Paint | How long until the main content shows | ≤ 2.5 s |
| **INP**: Interaction to Next Paint | How fast the page answers a tap or click | ≤ 200 ms |
| **CLS**: Cumulative Layout Shift | How much the page jumps while loading | ≤ 0.1 |
| **TTFB**: Time to First Byte | How long the server takes to answer | ≤ 800 ms |

LCP, INP and CLS are the Core Web Vitals Google uses in ranking.

![Analytics → Web Vitals](/screenshots/web-vitals.webp)

*Each figure is the 75th percentile, as Google judges it, with the share of good, middling and poor visits. Pages are listed slowest first.*

- Turn it on with **Measure Real Visitor Speed** under **Customizer → Performance**. It is off by
  default.
- Pick a range (today, 7, 28 or 90 days) and a device (mobile or desktop).
- Each figure is the **75th percentile**: three in four visits were at least this fast.
- **Pages, slowest first** lists every page with at least three measured visits. Fix the red ones
  first.

How it works:

- **The script:** a few hundred bytes, using the browser's own measuring tools, with no outside
  service. It sends its numbers once, as the visitor leaves the page, so it never slows the page
  down.
- **What is stored:** only the page address, mobile or desktop, and the four figures. No IP, no
  cookie, nothing personal.
- **What is left out:** bots and admin pages are not measured. At most 20,000 visits a day are
  kept, and data older than 90 days is removed.

Google's own field data in Search Console runs 28 days behind and often has nothing for a small
site. These figures are today's.

---

## What is not counted

- Known bots and crawlers are filtered when the visit is recorded.
- Admin pages are not tracked.

---

## Without Pro

The page renders with believable sample figures behind an upgrade prompt — never your site's
real numbers, and nothing you see there is stored. Activating a [Pro licence](/guide/pro)
turns on the live queries.
