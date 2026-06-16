# Products

## Creating a Product

1. Go to **Admin → Products → Add New**
2. Fill in the product details:
   - **Title** — Product name
   - **Description** — Full description (Classic editor or Falcon Builder)
   - **Short Description** — Shown in product card
   - **Featured Image** — Main product photo
   - **Gallery** — Additional photos
   - **Price** — Regular price
   - **Sale Price** — Discounted price (optional)
   - **SKU** — Stock keeping unit
   - **Stock Status** — In stock / Out of stock / Backorder
   - **Stock Quantity** — Number in stock (requires Manage Stock enabled)
3. Select **Product Category** and **Product Tags**
4. Set SEO meta fields (optional)
5. Click **Publish**

## Product Categories

Product Categories are a dedicated, first-class taxonomy — separate from Post categories.

**Admin → Products → Categories:**
- Hierarchical (parent → child)
- Each category has: name, slug, description
- Supports multi-language
- AJAX inline creation from the product editor

**Frontend URL:** `/product-category/{slug}`

## Product Tags

Flat taxonomy for product tagging.

**Admin → Products → Tags**

**Frontend URL:** `/product-tag/{slug}`

## Variable Products

Variable products have options like size or color, each with their own price and stock.

### Creating a Variable Product

1. Create a product as normal
2. Scroll to the **Variations** section
3. Add variation attributes (e.g., Size: S, M, L)
4. For each variation, set: price, sale price, SKU, stock status

**Frontend:** Customers select from dropdowns, price updates dynamically.

## Inventory Management

| Stock Status | Description |
|---|---|
| `instock` | Product available for purchase |
| `outofstock` | Cannot be added to cart |
| `backorder` | Can be ordered but will ship when available |

Enable **Manage Stock** to track exact quantities. Stock auto-decrements when an order is placed.

## Product Custom Fields

Add custom fields to products using the ACPT system:

1. Go to **Admin → ACPT**
2. Find or create the `product` post type
3. Add a Field Group with your fields
4. Fields appear in the product editor

**Read in templates:**
```php
$material = get_custom_field($post, 'material');
$warranty  = get_custom_field($post, 'warranty_years');
```

## Product Reviews

Customers can leave star ratings and text reviews on product pages.

**Moderate reviews:** Admin → Shop → Reviews

- Approve or reject reviews before they show publicly
- Bulk approve/reject

## Querying Products in Code

```php
// Get all published products
$products = get_falcon_posts([
    'type'  => 'product',
    'limit' => 12,
]);

// Filter by product category
$phones = get_falcon_posts([
    'type'       => 'product',
    'product_category' => 'phones', // category slug
    'limit'      => 6,
    'orderby'    => 'date',
    'order'      => 'DESC',
]);

foreach ($products as $product) {
    echo $product->title;
    echo $product->shopData->price;
    echo $product->shopData->stock_status;
    echo get_falcon_permalink($product);
}
```
