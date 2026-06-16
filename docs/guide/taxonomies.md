# Taxonomies

Falcon CMS supports multiple taxonomy systems for organizing content.

## Post Categories

Hierarchical taxonomy for blog posts. Categories support parent → child relationships.

**Admin:** Admin → Posts → Categories

```php
// Get all categories with post count
$cats = get_falcon_categories();

foreach ($cats as $cat) {
    echo $cat->name;          // "Technology"
    echo $cat->slug;          // "technology"
    echo $cat->posts_count;   // 12
    echo $cat->description;   // Optional description
    echo $cat->parent_id;     // Parent category ID (null if top-level)
}
```

**Frontend URL:** `/category/{slug}` or `/{locale}/category/{slug}`

## Post Tags

Flat (non-hierarchical) taxonomy for blog posts.

**Admin:** Admin → Posts → Tags

**Frontend URL:** `/tag/{slug}`

## Product Categories

Dedicated hierarchical taxonomy for products — completely separate from Post categories.

**Admin:** Admin → Products → Categories

**Frontend URL:** `/product-category/{slug}`

```php
use Acme\CmsDashboard\Models\ProductCategory;

$cats = ProductCategory::withCount('posts')->get();
```

## Product Tags

Flat taxonomy for products.

**Admin:** Admin → Products → Tags

**Frontend URL:** `/product-tag/{slug}`

## Custom Taxonomies (ACPT)

Attach custom taxonomies to any Advanced Custom Post Type.

### Creating a Custom Taxonomy

1. Go to **Admin → ACPT → Taxonomies**
2. Click **Add New**
3. Set: Name, Plural Name, Slug, Hierarchical (yes/no)
4. Attach to one or more CPTs

### Using Custom Taxonomy Terms

```php
// Get posts filtered by custom taxonomy term
$posts = get_falcon_posts([
    'type'          => 'project',
    'taxonomy'      => 'project_status',  // taxonomy slug
    'taxonomy_term' => 'in-progress',     // term slug
]);
```

## Archive Pages

Falcon CMS automatically generates archive pages for all taxonomies:

| Taxonomy | URL |
|---|---|
| Post category | `/category/{slug}` |
| Post tag | `/tag/{slug}` |
| Product category | `/product-category/{slug}` |
| Product tag | `/product-tag/{slug}` |
| Author | `/author/{user-id}` |

The `archive.blade.php` theme template handles category/tag archives.

## Menus with Taxonomy Links

In **Admin → Appearance → Menus**, you can add:
- Post categories and tags
- Product categories and product tags
- Custom taxonomy terms

These automatically link to their respective archive pages.
