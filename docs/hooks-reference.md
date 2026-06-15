# Falcon CMS — Hook Reference

> Hook system is WordPress-style. Use `add_falcon_action()` / `add_falcon_filter()` in your theme's `functions.php`.

---

## Helper Functions

| Function | Description |
|---|---|
| `add_falcon_action($tag, $callback, $priority)` | Register an action hook |
| `do_falcon_action($tag, ...$args)` | Fire an action hook |
| `remove_falcon_action($tag, $callback, $priority)` | Remove a registered action |
| `add_falcon_filter($tag, $callback, $priority)` | Register a filter hook |
| `apply_falcon_filters($tag, $value, ...$args)` | Apply filters and return modified value |
| `remove_falcon_filter($tag, $callback, $priority)` | Remove a registered filter |
| `has_lazy_action($tag)` | Check if any callbacks are registered for an action |
| `has_lazy_filter($tag)` | Check if any callbacks are registered for a filter |

---

## Frontend Hooks — Single Product (All Types)

These fire on **both** simple and variable product pages.

### Actions

| Hook Tag | Args | Description |
|---|---|---|
| `lazy_before_single_product` | `$post` | Before the entire product page wrapper |
| `lazy_after_single_product` | `$post` | After the entire product page wrapper |
| `lazy_before_product_images` | `$post` | Before the product image gallery column |
| `lazy_after_product_images` | `$post` | After the product image gallery column |
| `lazy_before_product_description` | `$post` | Before the description section |
| `lazy_after_product_description` | `$post` | After the description section |

### Filters

| Hook Tag | Args | Description |
|---|---|---|
| `lazy_product_description_title` | `$html, $post` | Modify the "Description" heading HTML |
| `lazy_product_description` | `$html, $post` | Modify the full description HTML |

---

## Frontend Hooks — Simple Product

These fire **only** on simple product pages (`single.blade.php`).

### Actions

| Hook Tag | Args | Description |
|---|---|---|
| `lazy_simple_before_product_title` | `$post` | Before the `<h1>` title |
| `lazy_simple_after_product_title` | `$post` | After the `<h1>` title |
| `lazy_simple_before_product_price` | `$post` | Before the price block |
| `lazy_simple_after_product_price` | `$post` | After the price block |
| `lazy_simple_before_short_description` | `$post` | Before the short description |
| `lazy_simple_after_short_description` | `$post` | After the short description |
| `lazy_simple_before_add_to_cart_form` | `$post` | Before the add-to-cart form (or out-of-stock button) |
| `lazy_simple_after_add_to_cart_form` | `$post` | After the add-to-cart form |
| `lazy_simple_add_to_cart_form_top` | `$post` | Inside the form, at the very top (good for hidden fields) |
| `lazy_simple_add_to_cart_form_bottom` | `$post` | Inside the form, at the very bottom |
| `lazy_simple_before_add_to_cart_button` | `$post` | Just before the submit button |
| `lazy_simple_after_add_to_cart_button` | `$post` | Just after the submit button |
| `lazy_simple_out_of_stock_button` | `$post` | Replaces the default out-of-stock button (if callback is registered) |
| `lazy_simple_before_product_meta` | `$post` | Before the SKU / category meta row |
| `lazy_simple_product_meta_fields` | `$post` | Inside the meta block — add extra meta lines |
| `lazy_simple_after_product_meta` | `$post` | After the SKU / category meta row |

### Filters

| Hook Tag | Args | Description |
|---|---|---|
| `lazy_simple_product_title` | `$html, $post` | Modify the `<h1>` HTML |
| `lazy_simple_product_price` | `$html, $post` | Modify the price block HTML |
| `lazy_simple_short_description` | `$html, $post` | Modify the short description HTML |
| `lazy_simple_add_to_cart_button` | `$html, $post` | Modify the Add to Cart button HTML |

---

## Frontend Hooks — Variable Product

These fire **only** on variable product pages (`single-product-variable.blade.php`).

### Actions

| Hook Tag | Args | Description |
|---|---|---|
| `lazy_variable_before_single_product` | `$post` | Before the variable product wrapper |
| `lazy_variable_after_single_product` | `$post` | After the variable product wrapper |
| `lazy_variable_before_product_images` | `$post` | Before the image gallery |
| `lazy_variable_after_product_images` | `$post` | After the image gallery |
| `lazy_variable_before_product_title` | `$post` | Before the `<h1>` title |
| `lazy_variable_after_product_title` | `$post` | After the `<h1>` title |
| `lazy_variable_before_product_price` | `$post` | Before the price block |
| `lazy_variable_after_product_price` | `$post` | After the price block |
| `lazy_variable_before_short_description` | `$post` | Before the short description |
| `lazy_variable_after_short_description` | `$post` | After the short description |
| `lazy_variable_before_add_to_cart_form` | `$post` | Before the variation selector + form |
| `lazy_variable_after_add_to_cart_form` | `$post` | After the form |
| `lazy_variable_add_to_cart_form_top` | `$post` | Inside the form, at the top |
| `lazy_variable_add_to_cart_form_bottom` | `$post` | Inside the form, at the bottom |
| `lazy_variable_before_add_to_cart_button` | `$post` | Just before the submit button |
| `lazy_variable_after_add_to_cart_button` | `$post` | Just after the submit button |
| `lazy_variable_before_product_meta` | `$post` | Before the meta block |
| `lazy_variable_product_meta_fields` | `$post` | Inside the meta block |
| `lazy_variable_after_product_meta` | `$post` | After the meta block |
| `lazy_variable_before_product_description` | `$post` | Before description section |
| `lazy_variable_after_product_description` | `$post` | After description section |

### Filters

| Hook Tag | Args | Description |
|---|---|---|
| `lazy_variable_product_title` | `$html, $post` | Modify the `<h1>` HTML |
| `lazy_variable_product_price` | `$html, $post` | Modify the price block HTML (static, pre-variation) |
| `lazy_variable_short_description` | `$html, $post` | Modify the short description HTML |
| `lazy_variable_add_to_cart_button` | `$html, $post` | Modify the Add to Cart button HTML |
| `lazy_variable_product_description` | `$html, $post` | Modify the full description HTML |

---

## Cart & Checkout Hooks

| Hook Tag | Type | Args | Description |
|---|---|---|---|
| `falcon_cart_item_custom_fields` | filter | `$fields, $product, $variation` | Modify/validate custom fields before storing in cart |
| `lazy_order_item_meta` | filter | `$meta, $item, $order` | Modify order item meta before saving to DB |
| `lazy_before_place_order` | action | `$order, $cart, $request` | Fires after order record is created, before items are saved |

---

## Admin Hooks — Products

| Hook Tag | Type | Args | Description |
|---|---|---|---|
| `lazy_admin_before_save_product` | filter | `$productData, $post\|null, $request` | Modify product data array before DB insert/update |
| `lazy_admin_after_save_product` | action | `$post, $shopData, $request, $action` | Fires after product is saved (`$action` = `'create'` or `'update'`) |
| `lazy_admin_before_delete_product` | action | `$post` | Fires before product is moved to trash |
| `lazy_admin_after_delete_product` | action | `$postId, $title` | Fires after product is trashed |

---

## Custom Fields — Add to Cart

You can add custom input fields to the add-to-cart form via the `lazy_simple_add_to_cart_form_top` (or `_bottom`) hook. Field names **must** be prefixed with `lazy_custom_`. The values are automatically:
1. Stored in the cart session under `meta.custom_fields`
2. Persisted to `shop_order_items.meta` when the order is placed

### Example: Add a "Gift Message" field (simple product)

```php
// In your theme's functions.php

add_falcon_action('lazy_simple_add_to_cart_form_top', function ($post) {
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">Gift Message</label>';
    echo '<textarea name="lazy_custom_gift_message" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Add a personal message..."></textarea>';
    echo '</div>';
});
```

### Showing custom fields in the admin order view

The custom fields are stored in `$item->meta['custom_fields']` on each `OrderItem`. You can read them in a custom admin panel or within the order detail view.

---

## Example: Append a badge after the product title

```php
add_falcon_action('lazy_simple_after_product_title', function ($post) {
    if ($post->sku === 'FEATURED-001') {
        echo '<span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-0.5 rounded mb-3">Staff Pick</span>';
    }
});
```

## Example: Modify the price HTML

```php
add_falcon_filter('lazy_simple_product_price', function ($html, $post) {
    if ($post->sale_price) {
        $savings = $post->price - $post->sale_price;
        $html .= '<p class="text-sm text-green-600 mt-1">You save ' . lazy_price_format($savings) . '</p>';
    }
    return $html;
}, 10);
```

## Example: Remove the description section entirely

```php
add_falcon_filter('lazy_product_description', function ($html, $post) {
    return ''; // return empty string to suppress
}, 10);
```

## Example: Low-stock alert on product save

```php
add_falcon_action('lazy_admin_after_save_product', function ($post, $shopData, $request, $action) {
    if ($shopData && $shopData->manage_stock && $shopData->stock_quantity <= 5) {
        \Illuminate\Support\Facades\Log::warning("Low stock: {$post->title} has {$shopData->stock_quantity} units left.");
    }
}, 10);
```
