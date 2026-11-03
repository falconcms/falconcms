# News Sites <Badge type="warning" text="Pro" />

The **Falcon News** plugin adds what a news or magazine site needs on top of posts and
categories: a Most Viewed screen and settings for the [view counter](/guide/post-views), RSS feeds
and a Google News sitemap.

It ships with [FalconCMS Pro](/guide/pro) and needs FalconCMS 2.8.5 or later. Activate it under
**Plugins**. It has no menu of its own; its screens sit where you would look for them:

| What | Where |
|---|---|
| Most viewed stories, and the view counter's settings | **Analytics → Most Viewed** |
| RSS feeds | **Settings → RSS Feeds** |
| Google News sitemap | **SEO → Settings → Google News sitemap** |

For your Instagram posts, see the [Instagram Feed](/guide/instagram-feed) builder element.

## The view counter

Counting views is part of the core *(since v2.8.18)*: showing a story's count, and Most Read and
Trending boxes, work on every site — see [Post Views](/guide/post-views). Falcon News adds what a
newsroom wants on top:

- **Analytics → Most Viewed** lists the top stories today, in the last 7 days, the last 30 days
  and of all time.
- **Counter settings** on that page choose which post types are counted, the time before the same
  reader counts again (30 minutes by default), whether signed-in visitors count, and how many
  days of daily figures are kept (the all-time count of each story is kept for ever).
- Each counted post type gets a **Views** column in its admin list, with the last 7 days beside
  the total.

## Reading time

The **Reading Time** dynamic source (and the Post Meta element) counts words in any language,
and in posts made with the builder. Choose its label (`min read`, or `{min} minute read`), the
reading speed and the digits.

## Showing a lead story

To pick stories by hand, add a custom field (a checkbox or true/false field called `featured`,
say) to posts, then use a Card element with **Posts By → Custom Field**, key `featured`, value
`1`. Leave the value empty to list every post where the field is filled in.

## RSS feeds

| Feed | Address |
|---|---|
| The whole site | `/feed` |
| A category | `/category/{slug}/feed` |
| A tag | `/tag/{slug}/feed` |
| Another language | add `?lang=bn` |

Each page links its feed in the `<head>`, so browsers and feed readers find it. Under
**Settings → RSS Feeds**, choose the post types, the number of stories and whether a story shows
its summary or its full text.

To check a feed, open `/feed` in the browser, paste its address into the
[W3C Feed Validator](https://validator.w3.org/feed/), or add it to a reader such as Feedly. A new
story shows up within ten minutes.

## Google News sitemap

`/news-sitemap.xml` lists the stories published in the last 48 hours in the format Google News
reads, with each story's language. It is added to `robots.txt` for you. Submit it in Google
Search Console under **Sitemaps**, and register your publication in the Google News Publisher
Center.

Under **SEO → Settings → Google News sitemap** you can set the publication name (exactly as
Google News knows it) and limit the sitemap to news categories, leaving out sections such as
recipes.

## Permissions

Most Viewed is shown to whoever can see Analytics. RSS Feeds needs the Settings permission, the
Google News box the SEO settings permission.
