# Hooks API

Falcon CMS uses a WordPress-style hook system with **Actions** and **Filters**. There are **109 hooks** total — 63 actions and 46 filters.

## How It Works

```
Actions  → do_falcon_action()     → callbacks run, no return value
Filters  → apply_falcon_filters() → callbacks modify & return a value
```

The system is priority-based — multiple callbacks can attach to the same hook at different priorities (default: 10). Lower priority numbers run first.

## Core Functions

```php
// Register an action
add_falcon_action(string $tag, callable $callback, int $priority = 10): void

// Register a filter
add_falcon_filter(string $tag, callable $callback, int $priority = 10): void

// Fire an action (called internally by the CMS)
do_falcon_action(string $tag, ...$args): void

// Apply a filter chain (called internally, returns the filtered value)
apply_falcon_filters(string $tag, mixed $value, ...$args): mixed

// Check if a hook has registered callbacks
has_falcon_action(string $tag): bool
has_falcon_filter(string $tag): bool

// Remove a previously registered callback
remove_falcon_action(string $tag, callable $callback, int $priority = 10): void
remove_falcon_filter(string $tag, callable $callback, int $priority = 10): void
```

All hook registrations go in your theme's **`functions.php`**, a
[plugin's](/guide/plugins) **`plugin.php`**, or a Laravel service provider.
Themes and plugins load at the same point in the boot cycle, so every hook below
is available to both.

---

## Action Hooks

### Admin Interface

#### `falcon_admin_head`
Fires inside the admin `<head>` tag. Use to inject custom CSS or meta tags.

```php
add_falcon_action('falcon_admin_head', function() {
    echo '<link rel="stylesheet" href="/css/my-admin.css">';
    echo '<meta name="my-meta" content="value">';
});
```

---

#### `falcon_admin_footer`
Fires at the bottom of every admin page, before `</body>`. Use to inject JS or custom HTML.

```php
add_falcon_action('falcon_admin_footer', function() {
    echo '<script src="/js/my-admin-script.js"></script>';
    echo '<p style="text-align:center;color:#999;">Built with ❤️ by My Company</p>';
});
```

---

#### `falcon_admin_bar_right_before`
Fires in the admin toolbar before the right-side content (notifications, user avatar).

```php
add_falcon_action('falcon_admin_bar_right_before', function() {
    echo '<a href="/help" class="admin-bar-link">Help</a>';
});
```

---

#### `falcon_settings_form_top`
Fires at the top of the General Settings form. Add custom settings sections above the defaults.

```php
add_falcon_action('falcon_settings_form_top', function() {
    echo '<div class="custom-settings-notice">Custom notice here</div>';
});
```

---

#### `falcon_settings_form_bottom`
Fires at the bottom of the General Settings form. Add custom settings sections below the defaults.

```php
add_falcon_action('falcon_settings_form_bottom', function() {
    echo '<div class="my-plugin-settings">
        <h3>My Plugin Settings</h3>
        <input name="my_option" value="' . get_cms_option('my_option') . '">
    </div>';
});
```

---

#### `falcon_seo_settings_form_top` / `falcon_seo_settings_form_bottom`
Same pattern as general settings, but for the SEO settings page.

---

#### `falcon_api_settings_form_bottom` / `falcon_integrations_settings_form_bottom` / `falcon_shop_settings_form_bottom`
The same injection point on the **REST API**, **Integrations** and **Shop**
settings forms. Anything echoed here renders inside that form and saves with it.

::: tip
Instead of echoing raw markup, prefer
[`falcon_add_settings_field()`](/api/settings-fields) — it renders native rows,
escapes values and handles saving on every one of these screens.
:::

---

#### `falcon_register_settings`
Fires once, the first time settings fields are collected. The place to register
fields and tabs for the settings screens.

```php
add_falcon_action('falcon_register_settings', function () {
    falcon_add_settings_field([
        'id'     => 'my_api_key',
        'label'  => 'API Key',
        'type'   => 'text',
        'screen' => 'general',
    ]);
});
```

See the [Settings Fields API](/api/settings-fields).

---

#### `falcon_admin_menu`
Fires once, when the admin sidebar is first built. Register menu items and
options pages here.

```php
add_falcon_action('falcon_admin_menu', function () {
    falcon_add_menu_page([
        'slug'  => 'my-plugin',
        'title' => 'My Plugin',
        'icon'  => 'extension',
        'route' => 'my-plugin.index',
    ]);
});
```

See the [Admin Menu API](/api/admin-menu).

---

### Frontend

#### `falcon_head`
Fires inside the frontend `<head>` tag in the theme layout.

```php
add_falcon_action('falcon_head', function() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<script>window.myConfig = { debug: false };</script>';
});
```

---

#### `falcon_footer`
Fires just before `</body>` on the frontend.

```php
add_falcon_action('falcon_footer', function() {
    echo '<script src="/js/my-script.js" defer></script>';
    echo '<!-- Tracking pixel -->';
});
```

---

### Post Content

#### `falcon_before_content`
**Args:** `$post`

Fires before the post content on single post pages.

```php
add_falcon_action('falcon_before_content', function($post) {
    if ($post->type === 'post') {
        echo '<div class="post-reading-time">
            ' . ceil(str_word_count(strip_tags($post->content)) / 200) . ' min read
        </div>';
    }
});
```

---

#### `falcon_after_content`
**Args:** `$post`

Fires after the post content. Use to add related posts, author bio, share buttons, etc.

```php
add_falcon_action('falcon_after_content', function($post) {
    echo '<div class="author-bio">
        <img src="' . $post->user->avatar . '" alt="">
        <h4>' . $post->user->name . '</h4>
        <p>' . $post->user->bio . '</p>
    </div>';
});
```

---

### Product Management (Admin)

#### `falcon_admin_before_save_product`
**Args:** `$productData, $post|null, $request`

Fires before a product is saved to the database. Use to validate or modify data.

```php
add_falcon_action('falcon_admin_before_save_product', function($productData, $post, $request) {
    // Validate custom field
    if ($request->input('min_order_qty') < 1) {
        abort(422, 'Minimum order quantity must be at least 1');
    }
});
```

---

#### `falcon_admin_after_save_product`
**Args:** `$post, $shopData, $request, $action`

Fires after a product is saved. `$action` is `'create'` or `'update'`.

```php
add_falcon_action('falcon_admin_after_save_product', function($post, $shopData, $request, $action) {
    if ($action === 'create') {
        // Send notification when new product is added
        \Mail::to('manager@example.com')->send(new \App\Mail\NewProductAlert($post));
    }

    // Clear product cache
    \Cache::forget('featured_products');
});
```

---

#### `falcon_admin_before_delete_product`
**Args:** `$post`

Fires before a product is deleted.

```php
add_falcon_action('falcon_admin_before_delete_product', function($post) {
    // Log deletion
    \Log::info("Product deleted: {$post->title} (ID: {$post->id})");
});
```

---

#### `falcon_admin_after_delete_product`
**Args:** `$postId, $title`

Fires after a product is deleted.

```php
add_falcon_action('falcon_admin_after_delete_product', function($postId, $title) {
    // Remove from search index
    \App\Services\SearchIndex::remove($postId);
});
```

---

### Simple Product Page Hooks

These hooks fire on the **single simple product** page in your theme.

#### `falcon_before_single_product` / `falcon_after_single_product`
**Args:** `$post`

Wrap the entire product page with custom HTML.

```php
add_falcon_action('falcon_before_single_product', function($post) {
    echo '<div class="product-announcement">Limited time offer!</div>';
});

add_falcon_action('falcon_after_single_product', function($post) {
    echo '<section class="recently-viewed">...</section>';
});
```

---

#### `falcon_before_product_images` / `falcon_after_product_images`
**Args:** `$post`

Inject content around the product image gallery.

```php
add_falcon_action('falcon_before_product_images', function($post) {
    if ($post->shopData->stock_quantity < 5) {
        echo '<div class="low-stock-badge">Only ' . $post->shopData->stock_quantity . ' left!</div>';
    }
});
```

---

#### `falcon_simple_before_product_title` / `falcon_simple_after_product_title`
**Args:** `$post`

Inject content before/after the product `<h1>` title.

```php
add_falcon_action('falcon_simple_before_product_title', function($post) {
    // Show brand badge
    $brand = get_custom_field($post, 'brand');
    if ($brand) echo '<span class="brand-badge">' . e($brand) . '</span>';
});
```

---

#### `falcon_simple_before_product_price` / `falcon_simple_after_product_price`
**Args:** `$post`

Inject content around the price display.

```php
add_falcon_action('falcon_simple_after_product_price', function($post) {
    echo '<p class="vat-note">Price includes VAT</p>';
});
```

---

#### `falcon_simple_before_add_to_cart_form` / `falcon_simple_after_add_to_cart_form`
**Args:** `$post`

Wrap the add-to-cart form with additional content.

---

#### `falcon_simple_add_to_cart_form_top` / `falcon_simple_add_to_cart_form_bottom`
**Args:** `$post`

Inject content inside the form, at top or bottom.

```php
add_falcon_action('falcon_simple_add_to_cart_form_top', function($post) {
    // Show custom product fields (e.g., engraving text input)
    $fields = get_post_custom_fields($post);
    if (!empty($fields['allow_engraving'])) {
        echo '<div class="engraving-field">
            <label>Engraving text:</label>
            <input type="text" name="custom_fields[engraving]" maxlength="20">
        </div>';
    }
});
```

---

#### `falcon_simple_before_add_to_cart_button` / `falcon_simple_after_add_to_cart_button`
**Args:** `$post`

Inject content immediately around the add-to-cart button.

```php
add_falcon_action('falcon_simple_after_add_to_cart_button', function($post) {
    // Wishlist button after add-to-cart
    echo '<button class="wishlist-btn" data-id="' . $post->id . '">♡ Save to Wishlist</button>';
});
```

---

#### `falcon_simple_out_of_stock_button`
**Args:** `$post`

Fires where the add-to-cart button would be when the product is out of stock. Render a custom button.

```php
add_falcon_action('falcon_simple_out_of_stock_button', function($post) {
    echo '<button class="notify-btn" data-product="' . $post->id . '">
        Notify me when available
    </button>';
});
```

---

#### `falcon_simple_before_product_meta` / `falcon_simple_after_product_meta`
**Args:** `$post`

Wrap the product meta section (SKU, category, tags).

---

#### `falcon_simple_product_meta_fields`
**Args:** `$post`

Fires inside the product meta section. Render custom field rows.

```php
add_falcon_action('falcon_simple_product_meta_fields', function($post) {
    $weight = get_custom_field($post, 'weight');
    if ($weight) {
        echo '<div class="product-meta-row">
            <span class="label">Weight:</span>
            <span class="value">' . e($weight) . ' kg</span>
        </div>';
    }
});
```

---

#### `falcon_before_product_description` / `falcon_after_product_description`
**Args:** `$post`

Wrap the product description section (tabs).

```php
add_falcon_action('falcon_after_product_description', function($post) {
    // Add a custom "Shipping Info" tab
    echo '<div class="product-tab">
        <h3>Shipping Information</h3>
        <p>Free shipping on orders over $50.</p>
    </div>';
});
```

---

### Variable Product Page Hooks

Variable products have all the same hooks as simple products, prefixed with `falcon_variable_` instead of `falcon_simple_`:

| Action Hook | Same as simple product |
|---|---|
| `falcon_variable_before_single_product` | `falcon_simple_before_*` |
| `falcon_variable_before_product_title` | `falcon_simple_before_product_title` |
| `falcon_variable_after_product_title` | — |
| `falcon_variable_before_product_price` | `falcon_simple_before_product_price` |
| `falcon_variable_after_product_price` | — |
| `falcon_variable_before_short_description` | — |
| `falcon_variable_after_short_description` | — |
| `falcon_variable_before_add_to_cart_form` | — |
| `falcon_variable_add_to_cart_form_top` | — |
| `falcon_variable_before_add_to_cart_button` | — |
| `falcon_variable_after_add_to_cart_button` | — |
| `falcon_variable_add_to_cart_form_bottom` | — |
| `falcon_variable_after_add_to_cart_form` | — |
| `falcon_variable_before_product_meta` | — |
| `falcon_variable_product_meta_fields` | — |
| `falcon_variable_after_product_meta` | — |
| `falcon_variable_before_product_description` | — |
| `falcon_variable_after_product_description` | — |
| `falcon_variable_after_single_product` | — |

Usage is identical to the simple product hooks — just replace `falcon_simple_` with `falcon_variable_`.

---

### Cart Hooks

#### `falcon_mini_cart_empty`
Fires when the mini cart is empty. Render a custom empty state.

```php
add_falcon_action('falcon_mini_cart_empty', function() {
    echo '<div class="empty-cart">
        <svg>...</svg>
        <p>Your cart is empty</p>
        <a href="/shop">Continue shopping →</a>
    </div>';
});
```

---

#### `falcon_before_mini_cart` / `falcon_after_mini_cart`
**Args:** `$cart`

Wrap the mini cart item list.

```php
add_falcon_action('falcon_before_mini_cart', function($cart) {
    $count = count($cart);
    echo '<p class="cart-count">' . $count . ' item(s) in your cart</p>';
});
```

---

#### `falcon_before_mini_cart_item` / `falcon_after_mini_cart_item`
**Args:** `$item, $key`

Wrap each item in the mini cart.

---

#### `falcon_cart_item_meta`
**Args:** `$item, $key`

Fires inside each cart item row. Render custom meta like engraving text, gift message, etc.

```php
add_falcon_action('falcon_cart_item_meta', function($item, $key) {
    if (!empty($item['custom_fields']['engraving'])) {
        echo '<p class="cart-meta">Engraving: ' . e($item['custom_fields']['engraving']) . '</p>';
    }
});
```

---

#### `falcon_before_cart_items` / `falcon_after_cart_item`
**Args:** `$cart` / `$item, $key`

Wrap the full cart and individual cart items.

---

### Checkout Hooks

#### `falcon_before_billing_fields` / `falcon_after_billing_fields`
Fires before/after the billing address form section.

```php
add_falcon_action('falcon_after_billing_fields', function() {
    // Add VAT number field after billing address
    echo '<div class="field">
        <label>VAT Number (optional)</label>
        <input type="text" name="vat_number" placeholder="VAT-XXXXXXXX">
    </div>';
});
```

---

#### `falcon_before_shipping_fields` / `falcon_after_shipping_fields`
Fires before/after the shipping address form section.

---

#### `falcon_before_checkout_order_review` / `falcon_after_checkout_order_review`
**Args:** `$cart`

Wrap the order summary table on the checkout page.

---

#### `falcon_checkout_item_meta`
**Args:** `$item`

Render custom meta in the checkout order review table for each item.

```php
add_falcon_action('falcon_checkout_item_meta', function($item) {
    if (!empty($item['custom_fields']['color'])) {
        echo '<p class="item-meta">Color: ' . e($item['custom_fields']['color']) . '</p>';
    }
});
```

---

#### `falcon_before_checkout_payment` / `falcon_after_checkout_payment`
**Args:** `$cart`

Wrap the payment methods section on checkout.

---

#### `falcon_before_place_order_button` / `falcon_after_place_order_button`
**Args:** `$cart`

Inject content immediately around the "Place Order" button.

```php
add_falcon_action('falcon_before_place_order_button', function($cart) {
    echo '<p class="terms-note">By placing your order you agree to our
        <a href="/terms">Terms & Conditions</a>.
    </p>';
});
```

---

#### `falcon_before_place_order`
**Args:** `$order, $cart, $request`

Fires just before the order is saved to the database. Last chance to validate or modify.

```php
add_falcon_action('falcon_before_place_order', function($order, $cart, $request) {
    // Store custom checkout data in the order meta
    if ($request->has('gift_message')) {
        $order->meta = array_merge($order->meta ?? [], [
            'gift_message' => $request->gift_message,
        ]);
    }
});
```

---

### Order Confirmation Hooks

#### `falcon_before_order_confirmation` / `falcon_after_order_confirmation`
**Args:** `$order`

Wrap the order confirmation page content.

```php
add_falcon_action('falcon_after_order_confirmation', function($order) {
    // Track conversion
    echo '<script>
        gtag("event", "purchase", {
            transaction_id: "' . $order->order_number . '",
            value: ' . $order->meta["total"] . '
        });
    </script>';
});
```

---

#### `falcon_order_confirmation_item_meta`
**Args:** `$item, $order`

Display custom meta in the confirmation page item list.

---

### Admin Order Hooks

#### `falcon_admin_order_item_meta`
**Args:** `$item` (OrderItem)

Fires in the admin order detail view for each item. Display custom field data.

```php
add_falcon_action('falcon_admin_order_item_meta', function($item) {
    if (!empty($item->meta['engraving'])) {
        echo '<tr><td>Engraving:</td><td>' . e($item->meta['engraving']) . '</td></tr>';
    }
});
```

---

## Filter Hooks

### Content Filters

#### `falcon_the_content`
**Args:** `$content, $post`

Modify post content before it's rendered on the frontend.

```php
add_falcon_filter('falcon_the_content', function($content, $post) {
    // Append a publication notice to all posts
    if ($post->type === 'post') {
        $content .= '<p class="published-note">Published on '
            . cms_date($post->published_at) . '</p>';
    }
    return $content;
});
```

---

#### `site_title`
Modify the site title string returned by `get_cms_option('site_title')`.

```php
add_falcon_filter('site_title', function($title) {
    return $title . ' — Best Shop';
});
```

---

### Product Display Filters

#### `falcon_simple_product_title`
**Args:** `$html, $post`

Modify the product `<h1>` title HTML.

```php
add_falcon_filter('falcon_simple_product_title', function($html, $post) {
    $badge = get_custom_field($post, 'is_new') ? '<span class="badge">NEW</span>' : '';
    return $badge . $html;
});
```

---

#### `falcon_simple_product_price`
**Args:** `$html, $post`

Modify the price display HTML.

```php
add_falcon_filter('falcon_simple_product_price', function($html, $post) {
    // Add "Save X%" badge when on sale
    $data = $post->shopData;
    if ($data->sale_price && $data->price) {
        $pct = round((1 - $data->sale_price / $data->price) * 100);
        $html .= '<span class="save-badge">Save ' . $pct . '%</span>';
    }
    return $html;
});
```

---

#### `falcon_simple_short_description`
**Args:** `$html, $post`

Modify the short description HTML.

---

#### `falcon_simple_add_to_cart_button`
**Args:** `$html, $post`

Completely replace the add-to-cart button HTML.

```php
add_falcon_filter('falcon_simple_add_to_cart_button', function($html, $post) {
    // Add a data attribute for analytics
    return str_replace(
        'class="add-to-cart',
        'data-product-id="' . $post->id . '" class="add-to-cart',
        $html
    );
});
```

---

#### `falcon_product_fields`
**Args:** `$fields, $post`

Add or remove fields from the product meta section (SKU, category links, etc.).

```php
add_falcon_filter('falcon_product_fields', function($fields, $post) {
    $fields[] = [
        'label' => 'Material',
        'value' => get_custom_field($post, 'material', 'N/A'),
    ];
    $fields[] = [
        'label' => 'Warranty',
        'value' => get_custom_field($post, 'warranty', '1 year'),
    ];
    return $fields;
});
```

---

#### `falcon_product_description`
**Args:** `$html, $post`

Modify the product description HTML.

---

#### `falcon_product_description_title`
**Args:** `$html, $post`

Modify the "Description" tab button HTML.

```php
add_falcon_filter('falcon_product_description_title', function($html, $post) {
    return str_replace('Description', 'Product Details', $html);
});
```

---

#### Variable Product Equivalents

All simple product filters have variable product equivalents:

| Simple | Variable |
|---|---|
| `falcon_simple_product_title` | `falcon_variable_product_title` |
| `falcon_simple_short_description` | `falcon_variable_short_description` |
| `falcon_simple_add_to_cart_button` | `falcon_variable_add_to_cart_button` |
| `falcon_product_fields` | `falcon_variable_product_fields` |
| `falcon_product_description` | `falcon_variable_product_description` |

---

### Cart & Checkout Filters

#### `falcon_cart_item_name`
**Args:** `$html, $item, $key`

Modify how an item's name displays in the cart.

```php
add_falcon_filter('falcon_cart_item_name', function($html, $item, $key) {
    // Append color variant label
    if (!empty($item['variation']['color'])) {
        $html .= ' <small>(' . e($item['variation']['color']) . ')</small>';
    }
    return $html;
});
```

---

#### `falcon_mini_cart_item_name`
**Args:** `$html, $item, $key`

Same as `falcon_cart_item_name` but for the mini cart dropdown.

---

#### `falcon_checkout_item_name`
**Args:** `$html, $item`

Item name in the checkout order review table.

---

#### `falcon_order_confirmation_item_name`
**Args:** `$html, $item, $order`

Item name on the order confirmation page.

---

#### `falcon_cart_item_custom_fields`
**Args:** `$fields, $product, $variation`

Add custom fields to a cart item when it's added to the cart.

```php
add_falcon_filter('falcon_cart_item_custom_fields', function($fields, $product, $variation) {
    // Capture custom product options from the request
    if (request()->has('gift_message')) {
        $fields['gift_message'] = request()->gift_message;
    }
    return $fields;
});
```

---

#### `falcon_cart_item_data`
**Args:** `$cartItem, $product, $variation`

Modify the entire cart item array before it's stored in the session.

```php
add_falcon_filter('falcon_cart_item_data', function($cartItem, $product, $variation) {
    $cartItem['added_from'] = request()->header('Referer');
    return $cartItem;
});
```

---

#### `falcon_checkout_custom_fields`
**Args:** `$fields, $request`

Add custom data to be stored with the order from checkout.

```php
add_falcon_filter('falcon_checkout_custom_fields', function($fields, $request) {
    $fields['vat_number'] = $request->input('vat_number');
    $fields['gift_wrap']  = $request->boolean('gift_wrap');
    return $fields;
});
```

---

#### `falcon_item_custom_fields_display`
**Args:** `$fields, $item, $context`

Modify which custom fields are shown. Context is `'cart'`, `'checkout'`, `'invoice'`, etc.

```php
add_falcon_filter('falcon_item_custom_fields_display', function($fields, $item, $context) {
    if ($context === 'invoice') {
        // Only show engraving on invoices
        return array_filter($fields, fn($k) => $k === 'engraving', ARRAY_FILTER_USE_KEY);
    }
    return $fields;
});
```

---

#### `falcon_custom_field_labels`
**Args:** `$labels, $context`

Override the display labels for custom fields.

```php
add_falcon_filter('falcon_custom_field_labels', function($labels, $context) {
    $labels['gift_message'] = 'Gift Message';
    $labels['engraving']    = 'Engraving Text';
    return $labels;
});
```

---

#### `falcon_checkout_field_labels`
**Args:** `$labels, $context`

Override billing/shipping field labels.

```php
add_falcon_filter('falcon_checkout_field_labels', function($labels, $context) {
    $labels['billing_company'] = 'Business Name';
    $labels['billing_phone']   = 'Mobile Number';
    return $labels;
});
```

---

#### `falcon_invoice_title`
**Args:** `$title, $order`

Change the invoice title (default: "Invoice").

```php
add_falcon_filter('falcon_invoice_title', function($title, $order) {
    return 'Tax Invoice #' . $order->order_number;
});
```

---

#### `falcon_order_item_meta`
**Args:** `$meta, $item, $order`

Modify order item meta data when the order is being created.

---

### Builder Filter

#### `falcon_builder_elements`
**Args:** `$elements`

Register custom elements in the Falcon Builder.

```php
add_falcon_filter('falcon_builder_elements', function($elements) {
    $elements['testimonial_card'] = [
        'label' => 'Testimonial Card',
        'icon'  => 'fa fa-quote-left',
        'view'  => 'themes.my-theme.elements.testimonial-card',
        'settings' => [
            'quote'  => ['type' => 'text',  'label' => 'Quote',  'default' => ''],
            'author' => ['type' => 'text',  'label' => 'Author', 'default' => ''],
            'role'   => ['type' => 'text',  'label' => 'Role',   'default' => ''],
            'rating' => ['type' => 'number','label' => 'Rating', 'default' => 5],
        ],
    ];
    return $elements;
});
```

---

### Settings Filters

#### `cms_theme_options`
**Args:** `$options`

Register custom settings pages in the admin.

```php
add_falcon_filter('cms_theme_options', function($options) {
    $options[] = [
        'id'     => 'social',
        'label'  => 'Social Media',
        'icon'   => 'fa fa-share-alt',
        'fields' => [
            ['id' => 'facebook_url', 'label' => 'Facebook URL', 'type' => 'text'],
            ['id' => 'instagram_url','label' => 'Instagram URL','type' => 'text'],
            ['id' => 'twitter_url',  'label' => 'Twitter/X URL','type' => 'text'],
        ],
    ];
    return $options;
});

// Read values anywhere:
// get_cms_option('facebook_url')
```

---

#### `falcon_general_settings_fields`
Add extra fields to the General Settings form.

```php
add_falcon_filter('falcon_general_settings_fields', function($fields) {
    $fields['support_email'] = [
        'type'    => 'email',
        'label'   => 'Support Email Address',
        'default' => '',
    ];
    return $fields;
});
```

---

### REST API Filter

#### `falcon_api_post_data`
**Args:** `$data, $post`

Modify the data returned by the REST API for posts.

```php
add_falcon_filter('falcon_api_post_data', function($data, $post) {
    // Add custom fields to API response
    $data['custom_fields'] = get_post_custom_fields($post);
    $data['reading_time']  = ceil(str_word_count(strip_tags($post->content)) / 200);
    return $data;
});
```

---

## Where to Register Hooks

### In a Theme's `functions.php`

```php
<?php
// resources/views/themes/my-theme/functions.php

add_falcon_action('falcon_after_content', function($post) {
    // ...
});

add_falcon_filter('falcon_the_content', function($content, $post) {
    return $content;
});
```

### In a Laravel Service Provider

```php
<?php
// app/Providers/CmsHooksServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class CmsHooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_falcon_action('falcon_after_single_product', function($post) {
            // ...
        });

        add_falcon_filter('falcon_cart_item_name', function($html, $item, $key) {
            return $html;
        });
    }
}
```

Register it in `bootstrap/providers.php`:

```php
return [
    // ...
    App\Providers\CmsHooksServiceProvider::class,
];
```

---

## Complete Hook List

### Actions (58 total)

| Hook | Args |
|---|---|
| `falcon_admin_head` | — |
| `falcon_admin_footer` | — |
| `falcon_admin_bar_right_before` | — |
| `falcon_head` | — |
| `falcon_footer` | — |
| `falcon_settings_form_top` | — |
| `falcon_settings_form_bottom` | — |
| `falcon_seo_settings_form_top` | — |
| `falcon_seo_settings_form_bottom` | — |
| `falcon_api_settings_form_bottom` | — |
| `falcon_integrations_settings_form_bottom` | — |
| `falcon_shop_settings_form_bottom` | — |
| `falcon_register_settings` | `SettingsExtension $registry` |
| `falcon_admin_menu` | — |
| `falcon_before_content` | `$post` |
| `falcon_after_content` | `$post` |
| `falcon_admin_before_save_product` | `$data, $post, $request` |
| `falcon_admin_after_save_product` | `$post, $shopData, $request, $action` |
| `falcon_admin_before_delete_product` | `$post` |
| `falcon_admin_after_delete_product` | `$postId, $title` |
| `falcon_admin_order_item_meta` | `$item` |
| `falcon_before_single_product` | `$post` |
| `falcon_after_single_product` | `$post` |
| `falcon_before_product_images` | `$post` |
| `falcon_after_product_images` | `$post` |
| `falcon_simple_before_product_title` | `$post` |
| `falcon_simple_after_product_title` | `$post` |
| `falcon_simple_before_product_price` | `$post` |
| `falcon_simple_after_product_price` | `$post` |
| `falcon_simple_before_short_description` | `$post` |
| `falcon_simple_after_short_description` | `$post` |
| `falcon_simple_before_add_to_cart_form` | `$post` |
| `falcon_simple_add_to_cart_form_top` | `$post` |
| `falcon_simple_before_add_to_cart_button` | `$post` |
| `falcon_simple_after_add_to_cart_button` | `$post` |
| `falcon_simple_add_to_cart_form_bottom` | `$post` |
| `falcon_simple_after_add_to_cart_form` | `$post` |
| `falcon_simple_out_of_stock_button` | `$post` |
| `falcon_simple_before_product_meta` | `$post` |
| `falcon_simple_product_meta_fields` | `$post` |
| `falcon_simple_after_product_meta` | `$post` |
| `falcon_before_product_description` | `$post` |
| `falcon_after_product_description` | `$post` |
| `falcon_variable_before_single_product` | `$post` |
| `falcon_variable_after_single_product` | `$post` |
| `falcon_variable_before_product_title` | `$post` |
| `falcon_variable_after_product_title` | `$post` |
| `falcon_variable_before_product_price` | `$post` |
| `falcon_variable_after_product_price` | `$post` |
| `falcon_variable_before_short_description` | `$post` |
| `falcon_variable_after_short_description` | `$post` |
| `falcon_variable_before_add_to_cart_form` | `$post` |
| `falcon_variable_add_to_cart_form_top` | `$post` |
| `falcon_variable_before_add_to_cart_button` | `$post` |
| `falcon_variable_after_add_to_cart_button` | `$post` |
| `falcon_variable_add_to_cart_form_bottom` | `$post` |
| `falcon_variable_after_add_to_cart_form` | `$post` |
| `falcon_variable_before_product_meta` | `$post` |
| `falcon_variable_product_meta_fields` | `$post` |
| `falcon_variable_after_product_meta` | `$post` |
| `falcon_mini_cart_empty` | — |
| `falcon_before_mini_cart` | `$cart` |
| `falcon_after_mini_cart` | `$cart` |
| `falcon_before_mini_cart_item` | `$item, $key` |
| `falcon_after_mini_cart_item` | `$item, $key` |
| `falcon_before_cart_items` | `$cart` |
| `falcon_before_cart_item` | `$item, $key` |
| `falcon_cart_item_meta` | `$item, $key` |
| `falcon_after_cart_item` | `$item, $key` |
| `falcon_before_billing_fields` | — |
| `falcon_after_billing_fields` | — |
| `falcon_before_shipping_fields` | — |
| `falcon_after_shipping_fields` | — |
| `falcon_before_checkout_order_review` | `$cart` |
| `falcon_checkout_item_meta` | `$item` |
| `falcon_after_checkout_order_review` | `$cart` |
| `falcon_before_checkout_payment` | `$cart` |
| `falcon_before_place_order_button` | `$cart` |
| `falcon_after_place_order_button` | `$cart` |
| `falcon_after_checkout_payment` | `$cart` |
| `falcon_before_place_order` | `$order, $cart, $request` |
| `falcon_before_order_confirmation` | `$order` |
| `falcon_order_confirmation_item_meta` | `$item, $order` |
| `falcon_after_order_confirmation` | `$order` |

### Filters (46 total)

| Hook | Args |
|---|---|
| `falcon_the_content` | `$content, $post` |
| `site_title` | `$title` |
| `falcon_builder_elements` | `$elements` |
| `cms_theme_options` | `$options` |
| `falcon_general_settings_fields` | `$fields` |
| `falcon_api_post_data` | `$data, $post` |
| `falcon_simple_product_title` | `$html, $post` |
| `falcon_simple_product_price` | `$html, $post` |
| `falcon_simple_short_description` | `$html, $post` |
| `falcon_simple_add_to_cart_button` | `$html, $post` |
| `falcon_product_fields` | `$fields, $post` |
| `falcon_simple_product_fields` | `$fields, $post` |
| `falcon_product_description` | `$html, $post` |
| `falcon_product_description_title` | `$html, $post` |
| `falcon_variable_product_title` | `$html, $post` |
| `falcon_variable_short_description` | `$html, $post` |
| `falcon_variable_add_to_cart_button` | `$html, $post` |
| `falcon_variable_product_fields` | `$fields, $post` |
| `falcon_variable_product_description` | `$html, $post` |
| `falcon_cart_item_name` | `$html, $item, $key` |
| `falcon_mini_cart_item_name` | `$html, $item, $key` |
| `falcon_checkout_item_name` | `$html, $item` |
| `falcon_order_confirmation_item_name` | `$html, $item, $order` |
| `falcon_cart_item_custom_fields` | `$fields, $product, $variation` |
| `falcon_cart_item_data` | `$cartItem, $product, $variation` |
| `falcon_checkout_custom_fields` | `$fields, $request` |
| `falcon_item_custom_fields_display` | `$fields, $item, $context` |
| `falcon_custom_field_labels` | `$labels, $context` |
| `falcon_checkout_field_labels` | `$labels, $context` |
| `falcon_invoice_title` | `$title, $order` |
| `falcon_order_item_meta` | `$meta, $item, $order` |
