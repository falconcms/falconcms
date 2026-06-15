# Multi-language

Falcon CMS has built-in multi-language support. You can create content in multiple languages and switch between them from the frontend.

## Enabling Multi-language

1. Go to **Admin → Settings → General**
2. Toggle **Enable Multi-language** on
3. Save

Once enabled, all frontend URLs get a language prefix: `/en/about-us`, `/bn/about-us`.

## Managing Languages

Go to **Admin → Tools → Languages**:

- **Add** a new language (name, ISO code, flag emoji)
- **Set default** — the language used when no prefix is in the URL
- **Enable/disable** individual languages
- **Language switcher display:** flag, text, code, or all

## Creating Translated Content

When multi-language is enabled, every post/page editor shows a **Language** metabox.

To translate a post:
1. Open the original post
2. Click **Add Translation** → select language
3. A new post is created linked to the original via `origin_id`
4. Translate the content and save

All translations share the same `origin_id` and are linked by `lang_code`.

## Reading Content by Language

```php
// Get posts in a specific language
$posts = get_lazy_posts([
    'type' => 'post',
    'lang' => 'bn',   // language code
]);

// Get translation of a post
$translation = $post->getTranslation('bn');

// Get permalink with language prefix
$url = get_lazy_permalink($post); // auto-detects current locale
```

## Language Switcher

Render a language switcher anywhere in your theme:

```php
// Links with flag + text
<?php lazy_lang_switcher(); ?>

// Flag only
<?php lazy_lang_switcher(showFlags: true); ?>

// Dropdown (auto-links to translated version of current page)
<?php lazy_lang_dropdown(); ?>
```

## URL Structure

| Mode | Homepage | Post URL |
|---|---|---|
| Single language | `/` | `/my-post` |
| Multi-language (default: en) | `/` | `/my-post` |
| Multi-language (other locale) | `/bn` | `/bn/my-post` |

## Locale Detection

Falcon CMS detects locale from:
1. URL prefix (`/bn/...`)
2. Session (`lang` key)
3. Default language setting

To switch locale programmatically:

```php
// Set locale via URL
GET /lang/bn
```

## Widget Localization

Widgets support language filtering. Add the `lang_code` field when creating widgets — they only display on matching locales.

```php
// Render widgets for current locale's sidebar
<?php render_lazy_widgets('primary-sidebar'); ?>
```
