# Template Tags

Template tags are PHP functions designed for use inside Blade theme templates.

## Site Information

```php
// Site name from settings
get_cms_option('site_name')          // "My Website"
get_cms_option('site_logo')          // "/storage/logo.png"
get_cms_option('site_favicon')       // "/storage/favicon.ico"
get_cms_option('site_description')   // "A great website"
```

## Post Data

```php
// In a single post template ($post is passed by the controller)

$post->title            // Post title
$post->slug             // URL slug
$post->content          // Raw content (builder JSON or HTML)
$post->excerpt          // Manual excerpt
$post->featured_image   // Featured image URL
$post->published_at     // Carbon date
$post->status           // published, draft, etc.
$post->user             // Author (User model)

// Render content
the_falcon_content($post->content)

// Generate excerpt
get_falcon_excerpt($post, 150)

// Get post URL
get_falcon_permalink($post)

// Check if this is the homepage
is_falcon_homepage($post)
```

## Archive / Loop

```php
// In posts.blade.php or archive.blade.php
// $posts is a LengthAwarePaginator or Collection

@foreach ($posts as $post)
    <article>
        <h2><a href="{{ get_falcon_permalink($post) }}">{{ $post->title }}</a></h2>
        <time>{{ cms_date($post->published_at, 'M j, Y') }}</time>
        <p>{{ get_falcon_excerpt($post) }}</p>
    </article>
@endforeach

// Pagination
the_falcon_pagination($posts)
```

## Navigation

```php
// Header menu
$menu = get_falcon_menu('header');

// Render items
@if($menu && $menu->items->count())
    <nav>
        @foreach($menu->items->whereNull('parent_id') as $item)
            <a href="{{ $item->url }}">{{ $item->title }}</a>
            @if($item->children->count())
                <ul>
                    @foreach($item->children as $child)
                        <li><a href="{{ $child->url }}">{{ $child->title }}</a></li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </nav>
@endif
```

## Media

```php
// Featured image
@if($post->featured_image)
    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}">
@endif

// Gallery (array of URLs)
@foreach($post->gallery ?? [] as $image)
    <img src="{{ $image }}" alt="">
@endforeach
```

## Comments

```php
// Comment count
$post->comments()->where('is_approved', true)->count()

// Load comments with replies (eager loaded by controller)
@foreach($post->comments->whereNull('parent_id') as $comment)
    <div class="comment">
        <strong>{{ $comment->user->name ?? 'Guest' }}</strong>
        <p>{{ $comment->content }}</p>

        @foreach($comment->replies as $reply)
            <div class="reply">{{ $reply->content }}</div>
        @endforeach
    </div>
@endforeach
```

## Header / Footer Builder

```php
// Render builder-based header (call in layouts/app.blade.php)
<?php get_falcon_header(); ?>

// Render builder-based footer
<?php get_falcon_footer(); ?>
```

## Widgets

```php
// Render sidebar
<?php render_falcon_widgets('primary-sidebar'); ?>

// Render footer areas
<?php render_falcon_widgets('footer-1'); ?>
<?php render_falcon_widgets('footer-2'); ?>
```

## Language

```php
// Current locale
app()->getLocale()       // "en"

// Language switcher links
<?php falcon_lang_switcher(); ?>       // flags + text
<?php falcon_lang_switcher(false); ?>  // text only
<?php falcon_lang_dropdown(); ?>       // dropdown

// Translate a post
$post->getTranslation('bn')         // Bengali translation or null
```

## SEO

```php
// SEO meta from post
$post->seo_meta['meta_title'] ?? $post->title
$post->seo_meta['meta_description'] ?? get_cms_option('default_meta_description')
$post->seo_meta['og_image'] ?? get_cms_option('default_og_image')
```

## Sitemap & Robots

Automatically generated at:
- `/sitemap.xml` — if `enable_xml_sitemap` is true
- `/robots.txt` — if `enable_robots_txt` is true

No template work needed.
