# Helper Functions

Falcon CMS provides 50+ global helper functions available anywhere in your application.

## CMS Options

```php
// Get a setting value
get_cms_option(string $key, mixed $default = null): mixed

// Examples
$name = get_cms_option('site_name', 'My Site');
$logo = get_cms_option('site_logo');
$tz   = get_cms_option('timezone', 'UTC');

// Save a setting
update_cms_option(string $key, mixed $value): void
update_cms_option('site_name', 'New Name');
```

## Time & Timezone

```php
// Get CMS timezone string
cms_timezone(): string
// Returns: "Asia/Dhaka"

// Current time in CMS timezone (Carbon)
cms_now(): Carbon

// Format a datetime in CMS timezone
cms_date(mixed $datetime, string $format = 'M j, Y H:i'): string
// Example: cms_date($post->published_at, 'd/m/Y') → "11/06/2026"
```

## Content Queries

```php
// Get multiple posts
get_lazy_posts(array $args = []): Collection

// Available args:
get_lazy_posts([
    'type'              => 'post',        // post type slug
    'status'            => 'published',   // status filter
    'limit'             => 10,            // posts per page (0 = all)
    'page'              => 1,             // current page
    'category'          => 'news',        // category slug
    'tag'               => 'featured',    // tag slug
    'lang'              => 'en',          // language code
    'author'            => 3,             // user ID
    'search'            => 'keyword',     // search term
    'orderby'           => 'date',        // date|title|random|menu_order
    'order'             => 'DESC',        // ASC|DESC
    'exclude'           => [1, 2, 3],     // exclude post IDs
    'include'           => [4, 5],        // include only these IDs
    'product_category'  => 'phones',      // product category slug
    'taxonomy'          => 'color',       // ACPT taxonomy slug
    'taxonomy_term'     => 'red',         // ACPT term slug
    'paginate'          => true,          // return paginator
    'with_count'        => true,          // include total count
]);

// Get a single post by slug or ID
get_lazy_post(string|int $slugOrId): ?Post

// Get permalink for a post (language-aware)
get_lazy_permalink(Post $post): string
// Returns: "/en/my-post" or "/my-post" depending on locale

// Check if post is the homepage
is_lazy_homepage(Post $post): bool
```

## Content Display

```php
// Render builder JSON or classic HTML (returns HTML string)
get_lazy_content(string $content): string

// Echo rendered content
the_lazy_content(string $content): void
// Usage in template: <?php the_lazy_content($post->content); ?>

// Generate excerpt (builder or classic, trimmed to $limit chars)
get_lazy_excerpt(Post $post, int $limit = 120): string

// Render post loop
the_lazy_loop(array $args = [], ?string $view = null): void

// Render pagination
the_lazy_pagination(LengthAwarePaginator $items, ?string $view = null): void
```

## Categories & Tags

```php
// Get all categories with post counts
get_lazy_categories(string $taxonomy = 'category'): Collection

// Returns categories with ->posts_count attribute
$categories = get_lazy_categories();
foreach ($categories as $cat) {
    echo $cat->name . ' (' . $cat->posts_count . ')';
}
```

## Navigation

```php
// Get a menu by location or slug
get_lazy_menu(string $slugOrLocation): ?NavigationMenu

// Examples
$headerMenu = get_lazy_menu('header');
$footerMenu = get_lazy_menu('footer');
$customMenu = get_lazy_menu('main-nav');

// $menu->items contains NavigationMenuItem collection (hierarchical)
```

## Language

```php
// Render language switcher (flags + text links)
lazy_lang_switcher(bool $showFlags = true): void

// Render language dropdown (auto-links to current page's translations)
lazy_lang_dropdown(): void
```

## Header & Footer

```php
// Render builder-based header (from builder sections)
get_lazy_header(): void

// Render builder-based footer
get_lazy_footer(): void
```

## Widgets

```php
// Render all active widgets for a given area
render_lazy_widgets(string $area): void

// Area slugs: 'primary-sidebar', 'footer-1', 'footer-2', 'footer-3', 'footer-4'
// Usage:
<?php render_lazy_widgets('primary-sidebar'); ?>
```

## Custom Fields

```php
// Get a single custom field value
get_custom_field(Post $post, string $fieldName, mixed $default = null): mixed

// Get all custom fields as keyed array
get_post_custom_fields(Post $post): array

// Examples
$price    = get_custom_field($post, 'price');
$deadline = get_custom_field($post, 'project_deadline', 'TBD');
$fields   = get_post_custom_fields($post);
echo $fields['material'];
```

## Activity Logging

```php
// Log a user action (with IP, country, user-agent auto-captured)
lazy_log_activity(
    string $action,
    string $description,
    ?Model $model = null,
    array $properties = []
): void

// Examples
lazy_log_activity('create', 'Created post: My First Post', $post);
lazy_log_activity('login', 'User logged in from IP 192.168.1.1');
```

## Dynamic Tokens

```php
// Resolve tokens in a string
lazy_resolve_tokens(string $value, ?Post $post = null): string

// Example
$text = 'Welcome to {site_name}! Today is {current_date}.';
echo lazy_resolve_tokens($text);
// → "Welcome to My Site! Today is June 11, 2026."

// Available tokens:
// {site_name}, {site_url}, {current_date}, {current_year}
// {post_title}, {post_excerpt}, {post_date}, {author_name}
// {featured_image}, {author_avatar}
```

## Version Check

```php
// Get installed version
lazy_cms_installed_version(): string
// Returns: "1.0.2"

// Check for updates (cached 6 hours)
lazy_check_update(bool $force = false): array
// Returns: [
//   'current'    => '1.0.2',
//   'latest'     => '1.0.3',
//   'has_update' => true,
//   'url'        => 'https://packagist.org/...',
//   'checked_at' => '2026-06-11 10:00:00',
// ]
```
