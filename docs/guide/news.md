# News Sites <Badge type="warning" text="Pro" />

The **Falcon News** plugin adds what a news or magazine site needs on top of posts and
categories: a view counter with Most Read lists, view counts anywhere in the builder, RSS feeds
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

Every time a reader opens a story, it is counted. **Analytics → Most Viewed** lists the top
stories today, in the last 7 days, the last 30 days and of all time.

- **It works with the page cache and behind Cloudflare.** The story page reports the view once it
  has loaded, so a cached page counts like any other.
- **Bots are left out.** Search engines and monitors that do not run scripts never count, and
  known bots are filtered by name too.
- **A reload is not a new reader.** A visitor counts once per story every 30 minutes.
- **Your own team is left out.** Signed-in visitors are not counted unless you switch that on.

Open **Counter settings** on the Most Viewed page to choose which post types are counted, the
time before the same reader counts again, whether signed-in visitors count, and how many days of
daily figures are kept (the all-time count of each story is kept for ever). Each counted post
type gets a **Views** column in its admin list, with the last 7 days beside the total.

### Most Read and Trending boxes

Add a **Card** element and set **Order By** to one of:

| Order By | Shows |
|---|---|
| Views | the most read stories of all time |
| Views — today | today's most read |
| Views — last 7 days | this week's trending stories |
| Views — last 30 days | this month's most read |

Combine it with **Posts By → Categories** for a "Most read in Sports" box.

### Showing a story's view count

Any title, text block or button can show the count through **dynamic content**: click the
database icon, choose **Post Views**, then pick:

- **Period**: all time, today, last 7 or 30 days.
- **Number**: `12,345`, `12.3K` or `12345`.
- **Label**: the words after the number (`views`), or around it with `{count}`: `Read {count} times`.
- **Digits**: Latin, Bangla (`১২৩`), Devanagari or Arabic.
- **Hide below**: show the fallback text instead until a story has this many views.

It works on a single post layout and in each card of a Card grid. In text you can also type the
token `{lazy:post_views}`.

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
