# Post Views

Every site counts how many times each post is read *(since v2.8.18)*. Show the count on a post
or a card with the builder's dynamic content, and order a Card grid by it for "Most read" and
"Trending" boxes. No plugin is needed.

## How a view is counted

The post page reports the view once it has loaded in the reader's browser.

- **It works with the page cache and behind Cloudflare.** A page served from the cache or a CDN
  counts like any other.
- **Bots are left out.** Search engines and monitors that do not run scripts never count, and
  known bots are filtered by name too.
- **A reload is not a new reader.** A visitor counts once per post every 30 minutes.
- **Your own team is left out.** Signed-in visitors are not counted.
- **Only published posts count**, and only the post type `post` by default.

Daily figures are kept for 120 days, so today, the last 7 and the last 30 days stay exact; the
all-time count of each post is kept for ever. Old days are pruned every night by
`falcon:prune-post-views`, which runs with the [scheduler](/guide/upgrade#scheduled-tasks).

::: tip More control with Falcon News
The [Falcon News](/guide/news) plugin (Pro) adds a **Most Viewed** screen, a **Views** column in
the admin lists, and settings for which post types count, the time before a reader counts again,
whether signed-in visitors count and how many days are kept.
:::

## Showing a post's view count

Any title, text block or button can show the count. Click the database icon, choose
**Post → Post Views**, then pick:

- **Period**: all time, today, last 7 or 30 days.
- **Number**: `12,345`, `12.3K` or `12345`.
- **Label**: the words after the number (`views`), or around it with `{count}`: `Read {count} times`.
  Leave it empty for the number alone.
- **Digits**: Latin, Bangla (`১২৩`), Devanagari or Arabic.
- **Hide below**: show the fallback text instead until a post has this many views.

It works on a single post layout, in each card of a Card grid, and in the **Post Card** builder
*(since v2.8.19)*, so every card of a blog list can show `View: 1,530` where it showed the
reading time. In text you can also type the token `{lazy:post_views}`.

## Most Read and Trending boxes

Add a **Card** element and set **Order By** to one of:

| Order By | Shows |
|---|---|
| Views | the most read posts of all time |
| Views — today | today's most read |
| Views — last 7 days | this week's trending posts |
| Views — last 30 days | this month's most read |

Combine it with **Posts By → Categories** for a "Most read in Sports" box.

In code, the same orders work in `get_falcon_posts()`:

```php
$trending = get_falcon_posts(['orderby' => 'views_week', 'limit' => 5]);
```

`orderby` takes `views`, `views_day`, `views_week` and `views_month`.

## Moving from Falcon News

A site that counted with Falcon News before v2.8.18 keeps its figures: the update copies them,
and the counter's settings, into the core. Falcon News 1.1 and later count through the core, so
nothing is counted twice.
