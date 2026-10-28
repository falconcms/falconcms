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

#### `falcon_login_form_after`
**Args:** `$where` (`'admin'` or `'shop'`)

Fires inside the login card, under the sign-in form, on both login pages (modern and funny) with
`'admin'`, and under the shop account page's sign-in with `'shop'`. Echo another way to sign in —
Falcon Security prints its "Sign in with a passkey" button (admin) and "Sign in with a code to
your phone" (both) here. *(Since v2.8.5; `'shop'` since v2.8.6.)*

```php
add_falcon_action('falcon_login_form_after', function ($where) {
    if ($where !== 'admin') {
        return;
    }
    echo '<a href="/sso/start" class="btn-submit" style="display:block;text-align:center;margin-top:1rem">Sign in with SSO</a>';
});
```

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

#### `falcon_header_actions`
Fires inside the theme header's icon row (next to search and account). The shop plugin puts
its cart icon here. Call it from a custom theme's header to get the cart icon.

```php
add_falcon_action('falcon_header_actions', function() {
    echo '<a href="/contact" class="header-icon">Contact</a>';
});
```

---

#### `falcon_after_footer`
Fires after the footer, just before `</body>`. Use it for markup such as drawers and modals.
The shop plugin adds its mini-cart here, or at `falcon_footer` if a theme has no
`falcon_after_footer`.

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

An element that draws data the browser does not have (posts from an API, figures from the
database) can ask for its canvas preview to be its real output: add `'preview' => 'server'` and a
`'template'`. The builder then renders that template with the settings being edited, refetching
as they change, so the canvas shows what the page will. The template receives `$el`, `$s` (the
settings), `$previewDevice` and `$builderPreview = true`.

### Extending the builder's own elements

A plugin can add options to the elements, containers and columns the builder already has,
without a new element. The [Pro Scroll Effects plugin](/guide/pro) adds its Scroll Reveal (Title
and Text Block) and Scroll Motion (everything) this way.

#### `falcon_builder_element_extras` (action)
**Args:** none

Prints into the Extra tab of every element, inside the builder's Vue app. Echo Vue markup bound to
`editingElement.settings` (show it only for the types it is for with `v-if`). Keep setting values
flat scalars — the shortcode converter keeps any such setting it has no attribute for. The page
must check every value again: nothing typed in the panel is trusted.

```php
add_falcon_action('falcon_builder_element_extras', function () {
    echo '<div v-if="editingElement.type === 'title'">'
        .'<label class="text-[10px] text-slate-500">My option</label>'
        .'<select v-model="editingElement.settings.my_option">…</select></div>';
});
```

#### `falcon_builder_container_extras` / `falcon_builder_column_extras` (actions)
**Args:** `$base` — the node's expression in the builder (`layout[editingContext.ci]`, `editingColumn`…)

The same, in the Extra tab of a container and of a column. Bind to `{$base}.settings`.

#### `falcon_builder_assets` (action)
**Args:** none

Prints in the builder page before the app mounts: the plugin's `<style>` and `<script>` for the
canvas. A script there can mark a canvas wrapper by pushing a function onto
`window.falconBuilderCanvas`; it gets the node and its kind (`'element'`, `'container'` or
`'column'`; a nested row is an element of type `row`) and returns attributes for its wrapper
(classes, `data-*`, CSS variables in `style`), or `null`. Styles and classes from several plugins
add up. It reads `node.settings`, so the wrapper follows the panel as it changes.

```js
(window.falconBuilderCanvas = window.falconBuilderCanvas || []).push(function (node, kind) {
    return node.settings.my_option ? { 'data-my-option': node.settings.my_option } : null;
});
```

#### `falcon_builder_element_html` (filter)
**Args:** `$html, $el`

An element's finished HTML on the page, with the element (`type`, `id`, `settings`). Return it
changed, or as it came. The HTML is only captured while something is hooked here.

#### `falcon_builder_node_attributes` (filter)
**Args:** `$attributes, $node, $kind`

Extra attributes on the outer tag of a container or a column on the page (`$kind` is
`'container'` or `'column'`). Start from `''`, append ` name="value"` pairs, and escape the values
with `e()`. The tag already has `class` and `style`, so add your own `data-*` attributes.

---

## Posts, Loops and Dynamic Content

These let a plugin order and annotate the posts the CMS lists, and add its own dynamic sources.
The [Falcon News plugin](/guide/news) is built on them.

#### `falcon_posts_orderby` (filter)
**Args:** `$handled, $query, $orderby, $order, $args`

Lets a plugin answer an `orderby` value of its own in `get_falcon_posts()` (and so in the Card
element). Order `$query` and return `true`; return `$handled` unchanged for orders that are not
yours. An order nobody answers falls back to the date.

```php
add_falcon_filter('falcon_posts_orderby', function ($handled, $query, $orderby, $order) {
    if ($orderby !== 'comment_count') {
        return $handled;
    }
    $query->withCount('comments')->orderBy('comments_count', $order);

    return true;
});
```

#### `falcon_post_orderby_options` (filter)
**Args:** `$options`

Extra choices for the Card element's **Order By** list, as `value => label`.

#### `falcon_posts_fetched` (action)
**Args:** `$posts, $args`

Fired with every result of `get_falcon_posts()` (a collection, or a paginator). Use it to load
something you show per post for the whole set in one query, instead of one query per card.

#### `falcon_dynamic_sources` (filter)
**Args:** `$sources`

Adds sources to the builder's dynamic-content picker. `$sources` has `text`, `link` and `image`
lists; each source is `key`, `label`, `icon`, `group`, an optional `sample` shown on the canvas,
and `subFields` (its options, each with `key`, `label`, `type` text/number/select and `options`).
Answer the source with `falcon_dynamic_value`.

#### `falcon_dynamic_value` (filter)
**Args:** `$value, $source, $post, $config`

The value of a dynamic source the core does not know, for the post being rendered. The values
of your sub-fields are in `$config['settings']` under their keys. Also answers the
`{lazy:your_key}` token, with an empty `$config`. Return `$value` unchanged for sources that are
not yours.

#### `falcon_admin_list_columns` (action)
**Args:** `$type, $posts, $where`

Prints extra `<th>` cells in the Posts list of each post type, once for the header
(`$where = 'head'`, with the page's `$posts`, so you can load their data at once) and once for
the footer (`'foot'`).

#### `falcon_admin_list_cells` (action)
**Args:** `$post, $type`

Prints the matching `<td>` for each row.

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

#### `falcon_placeholder_image`
**Args:** `$src`

Sets the image shown for a post or product that has none (`falcon_placeholder_image()`). The
default is an inline grey SVG.

```php
add_falcon_filter('falcon_placeholder_image', fn () => asset('images/no-photo.webp'));
```

---

#### `falcon_site_health_checks`
**Args:** `$checks`

Add checks of your own to **Settings → Site Health**. Each check is an array with `id`,
`label`, `status` (`good`, `recommended` or `critical`), and optionally `category` (defaults to
`Plugins`), `description`, `action`, and `details` (`['files' => [...]]` shows a list).

```php
add_falcon_filter('falcon_site_health_checks', function($checks) {
    $checks[] = [
        'id'          => 'my_api_key',
        'label'       => get_cms_option('my_api_key') ? 'My API key is set' : 'My API key is missing',
        'status'      => get_cms_option('my_api_key') ? 'good' : 'recommended',
        'description' => 'My Plugin needs an API key to sync orders.',
    ];
    return $checks;
});
```

---

#### `falcon_site_hosts`
**Args:** `array $hosts` (APP_URL's host)

The host names that count as this site. Analytics skips requests for any other host (scanners
sending another site's name, bots on the bare IP), and the firewall's host check uses the same
list. `www.` is ignored on both sides. Add the extra domains a site really answers on:
*(Since v2.8.5.)*

```php
add_falcon_filter('falcon_site_hosts', function (array $hosts) {
    $hosts[] = 'shop.example.com';

    return $hosts;
});
```

---

### Plugin Routing Filters

A plugin uses these filters to take over rendering of its own content. Each one starts as `null`, which tells the CMS to render as usual. Return something else to replace the CMS's choice.

#### `falcon_frontend_page_response`
**Args:** `$response, $post`

Return a response or view to render instead of a page. The shop uses this filter for the pages
assigned as Shop, Cart, Checkout, Account and Wishlist.

#### `falcon_single_view`
**Args:** `$view, $post`

Return the view name to render a single post with, for example a product template.

#### `falcon_archive_view`
**Args:** `$view, $postTypeSlug`

Return the view name for a post type's archive.

#### `falcon_plugin_post_types`
**Args:** `$owners`

Maps a post type to the plugin that serves it (`['product' => 'falcon-shop']`). While that
plugin is off, its post type's singles and archives return 404 and are left out of search and
the sitemap, and its admin screens are hidden.

```php
add_falcon_filter('falcon_plugin_post_types', function($owners) {
    $owners['event'] = 'my-events-plugin';
    return $owners;
});
```

::: warning Register it where it always runs
Register this filter in a theme's `functions.php` or in an always-on provider. If the plugin
registers it from its own code, the filter disappears when the plugin is turned off, which is
exactly when it is needed.
:::

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

### SEO, Shop Pages and Admin Panels

These let a plugin add to the SEO tools, the admin screens and the shop's own pages. The
[Search Console plugin](/guide/search-console) is built on them.

#### `falcon_seo_metabox_after` (action)
**Args:** `$post`

Prints under the editor's SEO box. Use it for a panel of your own about the page.

#### `falcon_admin_list_seo_cell` (action)
**Args:** `$post`

Prints inside the SEO column of the Posts and Pages lists, one row at a time.

#### `falcon_dashboard_widgets` (action)
**Args:** —

Prints a card on the admin dashboard.

#### `falcon_install_defaults` (action)
**Args:** —

Fired by `falcon:install`, `falcon:update` and the default-content seeder. A plugin creates the
content it needs here. The shop makes its pages here, only once, so a page the owner deleted is
not brought back.

#### `falcon_seo_noindex` (filter)
**Args:** `$noindex, $post`

Return `true` to keep a page out of search: it gets `noindex`, and is left out of the sitemap and
the SEO Audit. `$post` is `null` on pages that are not a post (archives, plugin routes).

```php
add_falcon_filter('falcon_seo_noindex', function ($noindex, $post = null) {
    return $noindex || ($post && $post->slug === 'thank-you');
});
```

#### `falcon_breadcrumbs` (filter)
**Args:** `$items, $post`

The breadcrumb trail, a list of `['title' => …, 'url' => …]` from Home to the current page. It is
used by every breadcrumb on the site and by the page's `BreadcrumbList`.

#### `falcon_structured_data` (filter)
**Args:** `$graph, $page`

The page's JSON-LD graph (a list of nodes) before it is printed. Add a node, or change one.

#### `falcon_seo_audit_checks` (filter)
**Args:** `$checks, $posts`

Add a check to **SEO → SEO Audit**. Each check is
`['key', 'label', 'severity' => 'error'|'warning'|'info', 'help', 'items' => [['id', 'title', 'detail', 'edit']]]`.

#### `falcon_link_suggestions` / `falcon_keyword_suggestions` (filters)
**Args:** `$result, $context`

Add to the editor's Internal Linking and Keywords tabs. `$context` carries the post id, its title
and focus keyword (and, for keywords, the page's text, headings and description).

#### `falcon_template_page_ids` (filter)
**Args:** `$ids`

Ids of pages drawn by templates rather than their own content (the shop adds its pages). The SEO
Audit does not report them as empty.

#### `falcon_turnstile_locations` / `falcon_turnstile_enabled` / `falcon_turnstile_verified` (filters)
**Args:** `array $locations` / `bool $enabled, string $location` / `bool $passed, string $location, Request $request`

Cloudflare Turnstile on the site's forms. Add a form of your own to Settings → Integrations
(`$locations[key] = 'Label'`), then print `falcon_turnstile('key')` inside it and check
`falcon_turnstile_check($request, 'key')` (null: passed) before acting on it. The other two let
you switch the check on or off for a request, or overrule Cloudflare's answer.

```php
add_falcon_filter('falcon_turnstile_locations', fn ($l) => $l + ['newsletter' => 'Newsletter sign-up']);
```

#### `falcon_customer_addresses` (filter)
**Args:** `Collection $addresses, User $user`

The signed-in customer's saved addresses, for the checkout's "Use a saved address" picker and the
pre-filled defaults. Add addresses they may also use (a company's shared delivery addresses).

#### `falcon_admin_order_sidebar` (action)
**Args:** `Order $order`

Prints a box in the admin order screen's sidebar, above Customer Details. Use the `wp-metabox`
markup to match.

#### `falcon_admin_orders_toolbar` (action)
Prints beside the search on the admin orders list (Falcon Security's "Check a number").
*(Since v2.8.6.)*

#### `falcon_admin_orders_columns` / `falcon_admin_orders_column` (actions)
**Args:** none / `Order $order`

A column of your own in the admin orders list: print its `<th class="wp-table-header">` in the
first, and its `<td class="wp-table-cell">` for each order in the second — always both, so the
rows line up. Falcon Security's Risk column uses them. *(Since v2.8.6.)*

#### `falcon_page_cache_is_personal` (filter)
**Args:** `$personal, $request`

Return `true` to keep a page out of the static page cache: one that belongs to the visitor, or
carries a form whose CSRF token must not be served to the next visitor (the wholesale login page).

#### Hooks the core fires for Falcon SEO

The Falcon SEO plugin does all its work through these. Another plugin can use them too.

| Hook | Type | Args | What it is for |
|---|---|---|---|
| `falcon_site_hidden_from_search` | filter | `$hidden` | `true` keeps the whole site out of search engines: noindex everywhere, no sitemap. Ask it with `falcon_site_hidden_from_search()`. |
| `falcon_robots_txt` | filter | `$content` | The robots.txt text before it is sent. |
| `falcon_seo_robots` | filter | `$directives, $post` | The robots meta directives of an indexable page (empty: no tag). |
| `falcon_seo_head_links` | action | `$post, $noindex` | Prints extra `<link>` tags after the canonical (hreflang). |
| `falcon_seo_schema` | filter | `null, $page` | The page's JSON-LD. Nothing returned, no script. |
| `falcon_optimize_html` | filter | `$html, $request` | Rewrites the finished page in the HTML optimizer, after images and before scripts are deferred. |
| `falcon_response_sent` | action | `$request, $response` | Every web response (the 404 log). |
| `falcon_redirect_saved` | action | `$from, $to` | A redirect was added in SEO → Redirects. |
| `falcon_redirects_screen` | action | — | Prints a panel on the Redirects screen. |
| `falcon_media_image_stored` | filter | `$made, $path` | An image was stored or replaced in the library. Return `true` if you made a copy. |
| `falcon_media_file_deleted` | action | `$path` | A library file was deleted. |
| `falcon_media_library_actions` | action | — | Prints a button next to the Media Library title. |
| `falcon_page_cache_cleared` | action | — | The page cache was emptied. |
| `falcon_customizer_sections` | filter | `$sections` | Add fields to the Customizer's sections. |
| `falcon_seo_settings_sections` | action | `$settings` | Prints a section on SEO → Settings. Its fields save with the form. |
| `falcon_seo_settings_checkboxes` | filter | `$names` | Names of your checkboxes on SEO → Settings, so an unticked one saves as `0`. |
| `falcon_seo_settings_data` | filter | `$data, $request` | What SEO → Settings is about to save (encrypt a key here). |
| `falcon_integrations_settings_after_form` | action | — | Prints after the Integrations form (a form of your own). |
| `falcon_integrations_settings_saved` | action | `$request` | Settings → Integrations was saved. |
| `falcon_admin_path_access` | filter | `null, $path, $user, $request` | Decide an admin path no menu owns: `true` lets the user in, `false` denies, `null` leaves it to the menus. |
| `falcon_keyword_countries` | filter | `null, $keyword, $countries, $post` | Figures for a keyword by country for the Analysis tab: `['source', 'days', 'rows' => [['country', 'name', 'impressions', 'clicks', 'position']], 'total', 'error']`. The Search Console plugin answers it with Google's numbers. |
| `falcon_seo_metabox_tabs` / `falcon_seo_metabox_panels` | action | `$post` | Add a tab to the editor's SEO box: a `.seo-tab-btn` with id `seo-tab-btn-{name}` and a `.seo-tab-content` with id `seo-tab-{name}`. Opening it fires the `falcon-seo-tab` DOM event with `detail.tab`. |

---

## E-commerce Logic Hooks

The hooks above change how the shop **looks**. These change what it **does**: prices, the cart,
coupons, shipping, tax, checkout, payment, orders and stock. They are what a wholesale,
role-pricing, B2B or multi-vendor plugin is built on.

Rules that apply to all of them:

- **No hook registered, no change.** Every figure is exactly what it was without the hook.
- **What a filter returns is checked.** A wrong type is ignored and the shop's own value is kept.
  A price below zero becomes 0. A sale price of 0 or less becomes "no sale".
- **Validation filters** (`*_validation`, `falcon_coupon_is_valid`) take `true`. Return `true` to
  allow. Return a string to refuse: the string is the message the shopper sees.
- Money is always recalculated on the server. No hook value is ever read from the browser.

### Prices and catalogue

#### `falcon_product_prices` (filter)
**Args:** `array $prices, int $postId, ?int $variationId, int $quantity, string $context`

`$prices` is `['price' => float, 'sale_price' => float|null]`. `$context` is `'display'` (a page
showing the price) or `'cart'` (a cart line being priced; the order is written from it).

Every storefront price goes through this one filter: product cards, the single product page,
the variable product's price range and its variation prices, the wishlist, the builder Product
Meta element, the cart, and therefore the order. The page and the charge cannot disagree.

In the cart, `$quantity` is the line's quantity. The cart is re-priced every time it changes, so
a tiered price follows the quantity.

```php
// Wholesale: 20% off for the "wholesale" role, 30% off from 50 pieces.
add_falcon_filter('falcon_product_prices', function ($prices, $postId, $variationId, $quantity, $context) {
    if (!auth()->check() || auth()->user()->role?->slug !== 'wholesale') {
        return $prices;
    }
    $off = ($context === 'cart' && $quantity >= 50) ? 0.30 : 0.20;

    // Show the retail price struck through and the wholesale price as the "sale" price.
    return ['price' => $prices['price'], 'sale_price' => round($prices['price'] * (1 - $off), 2)];
});
```

Storefront templates read `$product->shopData->current_price` and `current_sale_price`, which go
through this filter. `price` and `active_sale_price` stay the catalogue's own numbers (the admin
edits those). In PHP, use `falcon_product_prices($product, $variation = null, $quantity = 1)`.

> **Page cache:** signed-in visitors never get a cached page, so prices that depend on the
> customer are safe. Do not change guest prices based on something only in the session. The
> cached page would show it to every guest.

> **Theme copies:** a theme that copied the shop templates before this release still shows the
> catalogue price. The cart still charges the filtered price. Run `php artisan shop:template` to
> see which copies are behind (their `@version` is lower).

#### `falcon_product_is_in_stock` (filter)
**Args:** `bool $inStock, ProductData $product`

Whether a product can be bought. Used by the stock badge and by `$post->is_in_stock`. Use it to
take products off sale while a vendor is on holiday, or to hide wholesale-only items from retail
customers.

#### `falcon_product_query` (action)
**Args:** `Builder $query, array $filters`

Runs on every storefront product listing: the shop archive, category and tag archives, and the
assigned Shop page. Change the query in place.

```php
// A vendor's store page: /product?vendor=12
add_falcon_action('falcon_product_query', function ($query, $filters) {
    if ($vendor = (int) request('vendor')) {
        $query->whereIn('posts.id', VendorProduct::where('vendor_id', $vendor)->pluck('post_id'));
    }
});
```

### Cart

#### `falcon_add_to_cart_validation` (filter)
**Args:** `true $valid, Product $product, ?ProductVariation $variation, int $quantity, Request $request`

Allow or refuse an add to cart. `$quantity` is what the line will hold once this is added
(already in the cart plus what is being added). Return a string to refuse with that message.

```php
add_falcon_filter('falcon_add_to_cart_validation', function ($valid, $product, $variation, $quantity) {
    return is_wholesale_customer() && $quantity < 10 ? 'Wholesale orders start at 10 pieces.' : $valid;
});
```

#### `falcon_update_cart_validation` (filter)
**Args:** `true $valid, string $cartKey, array $item, int $newQuantity`

The same, for a quantity change on the cart page. A refused line keeps its old quantity, and the
cart page reloads to show what the basket really holds.

#### `falcon_add_to_cart` (action)
**Args:** `string $cartKey, array $item, Product $product, ?ProductVariation $variation, int $quantityAdded`

After an item is added and priced.

#### `falcon_cart_updated` (action)
**Args:** `array $cart`

After the cart page saves new quantities. The cart has already been re-priced.

#### `falcon_cart_item_removed` (action)
**Args:** `string $cartKey, array $item`

#### `falcon_cart_subtotal` (filter)
**Args:** `float $subtotal, array $cart`

The cart subtotal. Free-shipping thresholds, coupon minimums, tax and the order all use it. To
change a line's price, use `falcon_product_prices` instead, so the lines still add up to the
subtotal.

#### `falcon_product_card_actions` (action)
**Args:** `Post $product`

Inside a product card's image, beside the wishlist heart (shop page, related products, upsells,
cross-sells). Print a small round button, such as a quick view.

#### `falcon_shop_settings_tabs` (filter)
**Args:** `array $tabs`

Add a tab to Shop → Settings: `['my-tab' => 'My Tab']` (a plain slug; built-in tabs cannot be
renamed). Print its panel at `falcon_shop_settings_form_bottom` inside
`<div x-show="tab === 'my-tab'">`. The form saves each field as `shop_<name>`; a checkbox also
needs `falcon_shop_settings_toggles`. Link to it with `/admin/shop/settings?tab=my-tab`.

#### `falcon_shop_settings_toggles` (filter)
**Args:** `array $toggles`

Request name => option key (`shop_…`) of the checkboxes a plugin adds to the shop settings, so an
unticked box is saved as `0`. The save also deletes `<key>_%` rows, so no key may be the start of
another one.

#### `falcon_cart_shipping_bar` (filter)
**Args:** `string $html, string $context`

A line above the cart: on the cart page and in its AJAX updates (`$context` 'cart') and above the
mini-cart subtotal ('mini-cart'). Empty unless hooked. It is sent with every cart change and never
in a page the cache keeps, so it may speak about this shopper's cart (a free shipping bar, say).

#### `falcon_cart_total` (filter)
**Args:** `float $total, array $parts`

The amount charged. `$parts` holds `subtotal`, `shipping`, `tax`, `discount` and `cart`. Use it
for a surcharge or a credit. The order is written with this total.

### Coupons and promotions

#### `falcon_coupon_is_valid` (filter)
**Args:** `true $valid, array $coupon, array $cart`

Asked when a coupon is applied **and again on every cart change**. A coupon that stops
qualifying is removed. Return a string to refuse with that message.

#### `falcon_coupon_discount_amount` (filter)
**Args:** `float $discount, array $coupon, array $cart, ?float $base`

What one coupon takes off. The result is never negative and never more than the amount it is
taken from.

#### `falcon_coupon_applied` / `falcon_coupon_removed` (actions)
**Args:** `array $coupon, array $cart` / `string $code`

#### `falcon_cart_promotions` (filter)
**Args:** `array $promotions, array $cart`

The automatic promotions the cart has earned. Each entry is
`['id', 'name', 'summary', 'discount', 'applications', 'units']`. Return `[]` to turn
promotions off (for example for wholesale customers). Entries without a positive `discount`
are dropped.

### Shipping and tax

#### `falcon_shipping_methods` (filter)
**Args:** `array $methods, ?string $country, array $cart`

The shipping methods on offer, keyed by id: `['id' => …, 'label' => …, 'cost' => float]`. Add
your own (freight, vendor shipping), change prices, or remove methods. The free-shipping coupon
and "hide paid options when free" rules run after this filter, so they apply to your methods
too. If the filter removes every method, the built-in list is kept.

#### `falcon_tax_rate` (filter)
**Args:** `?array $rate, ?string $country`

The rate for a country: `['rate' => float, 'name' => string, 'shipping' => bool]`, or `null`.
Return `null` for a tax-exempt customer. Not called while tax is turned off.

#### `falcon_cart_tax` (filter)
**Args:** `float $tax, array $cart`

The cart's tax. The tax line, the total and the order all use it.

### Checkout and orders

#### `falcon_checkout_validation_rules` / `falcon_checkout_validation_attributes` (filters)
**Args:** `array $rules, Request $request` / `array $labels, Request $request`

The Laravel validation rules for placing an order, and the field names used in error messages.

```php
add_falcon_filter('falcon_checkout_validation_rules', function ($rules) {
    if (is_wholesale_customer()) {
        $rules['company_vat'] = 'required|string|max:30';
    }
    return $rules;
});
```

#### `falcon_checkout_validation` (filter)
**Args:** `true $valid, Request $request, array $cart`

Last check before anything is saved: no account, no stock taken, no order. Return a string to
stop the order with that message (for example a minimum order value).

#### `falcon_payment_gateways` (filter)
**Args:** `array $gateways`

The payment methods on offer: `['id', 'title', 'desc', 'type' => 'offline'|'online']`. Checkout
only accepts a method that is in this list.

- An **offline** gateway places the order right away, like Cash on Delivery.
- An **online** gateway of your own needs the two filters below.

#### `falcon_payment_redirect_url` (filter)
**Args:** `null $url, Order $order, string $method`

Start a payment for your own online gateway. Return the provider's `https://` payment URL, or
`null` if it could not start (the order stays pending). The provider must send the customer
back to `route('shop.payment.return', $order->id)` with `?gateway=<your id>`.

#### `falcon_payment_return_verified` (filter)
**Args:** `false $verified, Order $order, string $gateway, Request $request`

The customer has come back from your gateway. **Check the payment with the provider from the
server.** Never trust the query string alone. Return `true`, or the provider's transaction id, to
mark the order paid. Anything else leaves it pending. An order is marked paid only once, however
often the customer comes back.

```php
add_falcon_filter('falcon_payment_gateways', function ($g) {
    $g['bkash'] = ['id' => 'bkash', 'title' => 'bKash', 'desc' => 'Pay with bKash', 'type' => 'online'];
    return $g;
});
add_falcon_filter('falcon_payment_redirect_url', function ($url, $order, $method) {
    return $method === 'bkash' ? Bkash::createPayment($order)->redirectUrl : $url;
});
add_falcon_filter('falcon_payment_return_verified', function ($ok, $order, $gateway, $request) {
    if ($gateway !== 'bkash') return $ok;
    $payment = Bkash::execute($request->query('paymentID'));
    return $payment->completed && $payment->amount >= $order->total ? $payment->trxID : false;
});
```

#### `falcon_order_number` (filter)
**Args:** `string $number, Request $request`

The order number the customer sees. If the number is empty or already used, the default random
number is used instead.

#### `falcon_order_data` (filter)
**Args:** `array $orderData, array $cart, Request $request`

The order row before it is saved. Usually you add to `meta`. The money columns are already
calculated. Change them only if you change the payment side to match.

#### `falcon_order_item_data` (filter)
**Args:** `array $itemData, array $cartItem, Order $order`

Each order item row before it is saved (for example `meta.vendor_id`). The item always belongs to
the order being placed.

#### `falcon_order_created` (action)
**Args:** `Order $order, array $cart, Request $request`

The order and all its items are saved and the stock is taken. Payment has not started yet. Use
it to split an order per vendor, send it to an ERP, or record commission.

```php
// Multi-vendor: one sub-order per vendor.
add_falcon_action('falcon_order_created', function ($order) {
    $order->items->groupBy(fn ($i) => $i->meta['vendor_id'] ?? 0)
        ->each(fn ($items, $vendorId) => VendorOrder::createFrom($order, $vendorId, $items));
});
```

#### `falcon_order_finalized` (action)
**Args:** `Order $order`

The order is confirmed: an offline order was just placed, or an online order was paid. Order
emails are sent and the cart is cleared. Runs once per order.

#### `falcon_order_paid` (action)
**Args:** `Order $order`

An online or zero-total order was paid. Runs once per order, from whichever arrives first: the
browser coming back or the Stripe webhook.

#### `falcon_order_status_changed` (action) and `falcon_order_status_{status}` (action)
**Args:** `Order $order, string $oldStatus, string $newStatus` / `Order $order, string $oldStatus`

Runs on **every** status change, wherever it comes from: the admin order screen, bulk actions,
refunds, the Stripe webhook, payment, or your own `$order->update(['status' => …])`. Only runs
when the status really changes. Example: `falcon_order_status_completed`.

#### `falcon_order_refunded` (action)
**Args:** `Order $order, float $amount, string $source`

A refund was recorded. `$amount` is this refund only. `$source` is `'admin'` or `'stripe_webhook'`.

#### `falcon_order_payment_failed` (action)
**Args:** `Order $order, string $gateway`

#### `falcon_product_stock_changed` (action)
**Args:** `int $postId, ?int $variationId, int $newQuantity, int $change, string $reason`

Stock moved. `$reason` is `'checkout'` (taken when an order is placed), `'order_restocked'`
(cancelled, failed or refunded) or `'order_reduced'` (an order moved back to an active status).

#### `falcon_customer_registered` (action)
**Args:** `User $user, Request $request`

A customer account was created at checkout.

### Emails

#### `falcon_order_admin_email_recipients` (filter)
**Args:** `array $emails, Order $order`

Who gets the "new order" email. Starts with the address in Shop settings. Invalid addresses are
dropped.

#### `falcon_order_email_subject` (filter)
**Args:** `string $subject, Order $order, string $type, string $recipientType`

The full subject of an order email. `$type` is `'placed'` or `'status_updated'`, and
`$recipientType` is `'customer'` or `'admin'`. Order emails can be queued, so this may run in the
queue worker. Do not rely on the current request.

### Account, reviews, wishlist, admin

#### `falcon_account_tabs` (filter) and `falcon_account_tab_content` (action)
**Args:** `array $tabs, ?User $user` / `string $tab, User $user`

Add a tab to My Account. A tab is `[slug, label, lucide icon]`, and the slug may use `a-z 0-9 _ -`.
Print the tab's content from the action. The action only runs for tabs that are not built in.

```php
add_falcon_filter('falcon_account_tabs', fn ($tabs) => [...$tabs, ['vendor', 'My store', 'store']]);
add_falcon_action('falcon_account_tab_content', function ($tab, $user) {
    if ($tab === 'vendor') echo view('my-plugin::vendor-dashboard', compact('user'))->render();
});
```

#### `falcon_account_orders_query` (action)
**Args:** `Builder $query, User $user`

The customer's order list on My Account.

#### `falcon_admin_orders_query` (action)
**Args:** `Builder $query, Request $request`

The order list in Shop → Orders (for example to show a vendor only their own orders).

#### `falcon_review_auto_approve` (filter) and `falcon_review_created` (action)
**Args:** `bool $approve, array $data, ?User $user` / `Review $review`

#### `falcon_wishlist_item_added` / `falcon_wishlist_item_removed` (actions)
**Args:** `int $productId, int $userId`

### Hiding prices, and listing by a plugin's own prices

#### `falcon_show_prices` (filter) and `falcon_hidden_price_html` (filter)
**Args:** `bool $show, $product` / `string $html, $product, string $context`

Whether the visitor may see a product's price and buy it. `falcon_can_see_prices($product)` asks
the first filter. It is always true unless a plugin says otherwise. When it is false:

- product cards, the shop archive, the product pages, the wishlist and the builder's Product Meta
  element print `falcon_hidden_price_html($product, 'price')` in place of the price, and
  `falcon_hidden_price_html($product, 'cart')` in place of the buy button (a "See Detail" link
  when that is empty);
- a variable product's variation prices are left out of the page source;
- adding to the cart is refused (403). The message is the text of the `'cart'` HTML;
- the product's structured data leaves out `offers`, so search engines do not get the price.

```php
// Prices for signed-in customers only.
add_falcon_filter('falcon_show_prices', fn ($show) => $show && auth()->check());
add_falcon_filter('falcon_hidden_price_html', fn ($html, $product, $context) => $context === 'cart'
    ? '<a href="/account" class="btn">Log in to buy</a>'
    : '<a href="/account">Log in to see prices</a>');
```

Guests share cached pages, so decide for all guests alike. Do not decide by something only in the
session.

#### `falcon_product_price_filter` and `falcon_product_price_sort` (filters)
**Args:** `bool $handled, Builder $query, ?float $min, ?float $max` / `bool $handled, Builder $query, string $direction`

The shop's price filter and price sort read the catalogue prices in SQL. A plugin that prices
some shoppers differently can apply the range or the order itself: change `$query` and return
`true`, and the catalogue version is skipped. Return `$handled` unchanged to leave it alone.
`$direction` is `ASC` or `DESC`.

#### `falcon_product_filter_options` (filter)
**Args:** `array $options, array $productIds`

The filter sidebar's options. A plugin with its own prices replaces `min_price` and `max_price`
(the price band). `$productIds` are the products in the listing.

#### Product listings
`falcon_product_query` (above) now also runs for related products, upsells, cross-sells and
promotion reward suggestions. A product a plugin hides is hidden everywhere.

#### `falcon_cart_actions` (action)
**Args:** `array $cart`

Below the cart page's "Proceed to checkout" button. Print another way forward, for example
"Request a quote".

#### `falcon_admin_after_save_product` with mode `'variations'`
The editor's "Save All Variations" button now fires `falcon_admin_after_save_product` too, with
`$mode = 'variations'`. Data derived from the variations (a price index) stays current.

### Admin screens for shop plugins

These let a plugin add its own fields to the product editor, to each variation, to product
categories and to users, and save them. Falcon Wholesale is built on them.

#### `falcon_admin_product_data_tabs` (filter) and `falcon_admin_product_data_panels` (action)
**Args:** `array $tabs, ?Post $post` / `?Post $post`

Add a tab to the product editor's Product Data box. A tab is
`['id' => 'my-tab', 'label' => 'My tab', 'icon' => 'storefront', 'show' => "productType !== 'variable'"]`.
`icon` is a Material Symbols name. `show` is optional: an Alpine expression that decides when the
tab is visible. The id may use `a-z 0-9 _ -` and cannot be one of the built-in tabs.

Print the panel from the action as `<div x-show="activeTab === 'my-tab'">…</div>`. It is inside
the product form and its Alpine scope (`productType`, `variations`…). Its fields are posted with the
product, so save them in `falcon_admin_after_save_product`.

```php
add_falcon_filter('falcon_admin_product_data_tabs', fn ($tabs) => [...$tabs, ['id' => 'supplier', 'label' => 'Supplier', 'icon' => 'local_shipping']]);
add_falcon_action('falcon_admin_product_data_panels', function ($post) {
    echo '<div x-show="activeTab === \'supplier\'"><input type="hidden" name="supplier_panel" value="1">'
       . '<input name="supplier_code" value="'.e(supplier_code($post)).'"></div>';
});
add_falcon_action('falcon_admin_after_save_product', function ($post, $shopData, $request) {
    if ($request->has('supplier_panel')) {
        save_supplier_code($post, $request->input('supplier_code'));
    }
});
```

Add a hidden marker field, as above, and save only when it is present. Then a save from a screen
without your panel never clears your data.

#### `falcon_admin_variation_fields` (action) and `falcon_admin_variation_meta` (filter)
**Args:** `?Post $post` / `array $meta, array $input, ProductData $shopData`

Variations are deleted and recreated every time a product is saved, so their ids change. Store
per-variation data in the variation's own **`meta`** column (`ProductVariation::$meta`, an array),
not in a table keyed by variation id.

The action prints once inside the editor's variation loop, where `variation` and `vIndex` are in
scope. Bind your field to `variation.meta.<your key>` and name it
`'variations['+vIndex+'][meta][<your key>]'`:

```php
add_falcon_action('falcon_admin_variation_fields', function () {
    echo '<div class="md:col-span-2" x-init="variation.meta = variation.meta || {}">'
       . '<input x-model="variation.meta.supplier_code" :name="\'variations[\'+vIndex+\'][meta][supplier_code]\'">'
       . '</div>';
});
```

Both the Save button and the editor's "Save All Variations" button save it. The editor sends each
variation's whole `meta` back on every save, so a key whose field is not on the page (for example
because its plugin is turned off) is kept. Use the filter to check or clean what is stored. Return
the array. An empty array is stored as `null`.

#### `falcon_admin_product_category_fields` (action) and `falcon_admin_product_category_saved` (action)
**Args:** `?ProductCategory $category, string $context` / `ProductCategory $category, Request $request, string $mode`

Fields on the product category screens. `$context` is `'add'` (the "Add New Category" form: print
`<div class="mb-4">` blocks) or `'edit'` (print `<tr><th>…</th><td>…</td></tr>` rows). `$mode` is
`'create'` or `'update'`.

#### `falcon_admin_user_form_fields` (action) and `falcon_admin_user_saved` (action)
**Args:** `?User $user` / `User $user, Request $request, string $mode`

Rows (`<tr><th>…</th><td>…</td></tr>`) on the Add User and Edit User screens (`$user` is `null` on
Add User), and the save after the user is created or updated. `$mode` is `'create'` or `'update'`.

#### `falcon_admin_user_role_cell` (action)
**Args:** `User $user`

Print a badge after the role in the Users list.

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

### Actions (128 total)

| Hook | Args |
|---|---|
| `falcon_admin_head` | — |
| `falcon_admin_footer` | — |
| `falcon_admin_bar_right_before` | — |
| `falcon_login_form_after` | `$where` |
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
| `falcon_admin_order_sidebar` | `$order` |
| `falcon_admin_orders_toolbar` | — |
| `falcon_admin_orders_columns` | — |
| `falcon_admin_orders_column` | `$order` |
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
| `falcon_header_actions` | — |
| `falcon_after_footer` | — |
| `falcon_seo_metabox_after` | `$post` |
| `falcon_admin_list_seo_cell` | `$post` |
| `falcon_dashboard_widgets` | — |
| `falcon_install_defaults` | — |
| `falcon_product_query` | `$query, $filters` |
| `falcon_add_to_cart` | `$cartKey, $item, $product, $variation, $quantity` |
| `falcon_cart_updated` | `$cart` |
| `falcon_cart_item_removed` | `$cartKey, $item` |
| `falcon_coupon_applied` | `$coupon, $cart` |
| `falcon_coupon_removed` | `$code` |
| `falcon_customer_registered` | `$user, $request` |
| `falcon_order_created` | `$order, $cart, $request` |
| `falcon_order_finalized` | `$order` |
| `falcon_order_paid` | `$order` |
| `falcon_order_status_changed` | `$order, $oldStatus, $newStatus` |
| `falcon_order_status_{status}` | `$order, $oldStatus` |
| `falcon_order_refunded` | `$order, $amount, $source` |
| `falcon_order_payment_failed` | `$order, $gateway` |
| `falcon_product_stock_changed` | `$postId, $variationId, $newQuantity, $change, $reason` |
| `falcon_account_tab_content` | `$tab, $user` |
| `falcon_account_orders_query` | `$query, $user` |
| `falcon_admin_orders_query` | `$query, $request` |
| `falcon_review_created` | `$review` |
| `falcon_wishlist_item_added` | `$productId, $userId` |
| `falcon_wishlist_item_removed` | `$productId, $userId` |
| `falcon_admin_product_data_panels` | `$post` |
| `falcon_admin_variation_fields` | `$post` |
| `falcon_admin_product_category_fields` | `$category, $context` |
| `falcon_admin_product_category_saved` | `$category, $request, $mode` |
| `falcon_admin_user_form_fields` | `$user` |
| `falcon_admin_user_saved` | `$user, $request, $mode` |
| `falcon_admin_user_role_cell` | `$user` |
| `falcon_cart_actions` | `$cart` |
| `falcon_posts_fetched` | `$posts, $args` |
| `falcon_admin_list_columns` | `$type, $posts, $where` |
| `falcon_admin_list_cells` | `$post, $type` |
| `falcon_builder_element_extras` | — |
| `falcon_builder_container_extras` | `$base` |
| `falcon_builder_column_extras` | `$base` |
| `falcon_builder_assets` | — |

### Filters (88 total)

| Hook | Args |
|---|---|
| `falcon_site_hosts` | `array $hosts` |
| `falcon_the_content` | `$content, $post` |
| `site_title` | `$title` |
| `falcon_builder_elements` | `$elements` |
| `falcon_builder_element_html` | `$html, $el` |
| `falcon_builder_node_attributes` | `$attributes, $node, $kind` |
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
| `falcon_site_health_checks` | `$checks` |
| `falcon_frontend_page_response` | `$response, $post` |
| `falcon_single_view` | `$view, $post` |
| `falcon_archive_view` | `$view, $postTypeSlug` |
| `falcon_plugin_post_types` | `$owners` |
| `falcon_placeholder_image` | `$src` |
| `falcon_seo_noindex` | `$noindex, $post` |
| `falcon_breadcrumbs` | `$items, $post` |
| `falcon_structured_data` | `$graph, $page` |
| `falcon_seo_audit_checks` | `$checks, $posts` |
| `falcon_link_suggestions` | `$result, $context` |
| `falcon_keyword_suggestions` | `$result, $context` |
| `falcon_template_page_ids` | `$ids` |
| `falcon_page_cache_is_personal` | `$personal, $request` |
| `falcon_customer_addresses` | `$addresses, $user` |
| `falcon_turnstile_locations` | `$locations` |
| `falcon_turnstile_enabled` | `$enabled, $location` |
| `falcon_turnstile_verified` | `$passed, $location, $request` |
| `falcon_product_prices` | `$prices, $postId, $variationId, $quantity, $context` |
| `falcon_product_is_in_stock` | `$inStock, $product` |
| `falcon_add_to_cart_validation` | `$valid, $product, $variation, $quantity, $request` |
| `falcon_update_cart_validation` | `$valid, $cartKey, $item, $quantity` |
| `falcon_cart_subtotal` | `$subtotal, $cart` |
| `falcon_shop_settings_toggles` | `$toggles` |
| `falcon_shop_settings_tabs` | `$tabs` |
| `falcon_cart_shipping_bar` | `$html, $context` |
| `falcon_cart_total` | `$total, $parts` |
| `falcon_coupon_is_valid` | `$valid, $coupon, $cart` |
| `falcon_coupon_discount_amount` | `$discount, $coupon, $cart, $base` |
| `falcon_cart_promotions` | `$promotions, $cart` |
| `falcon_shipping_methods` | `$methods, $country, $cart` |
| `falcon_tax_rate` | `$rate, $country` |
| `falcon_cart_tax` | `$tax, $cart` |
| `falcon_checkout_validation_rules` | `$rules, $request` |
| `falcon_checkout_validation_attributes` | `$labels, $request` |
| `falcon_checkout_validation` | `$valid, $request, $cart` |
| `falcon_payment_gateways` | `$gateways` |
| `falcon_payment_redirect_url` | `$url, $order, $method` |
| `falcon_payment_return_verified` | `$verified, $order, $gateway, $request` |
| `falcon_order_number` | `$number, $request` |
| `falcon_order_data` | `$orderData, $cart, $request` |
| `falcon_order_item_data` | `$itemData, $cartItem, $order` |
| `falcon_order_admin_email_recipients` | `$emails, $order` |
| `falcon_order_email_subject` | `$subject, $order, $type, $recipientType` |
| `falcon_account_tabs` | `$tabs, $user` |
| `falcon_review_auto_approve` | `$approve, $data, $user` |
| `falcon_admin_product_data_tabs` | `$tabs, $post` |
| `falcon_admin_variation_meta` | `$meta, $input, $shopData` |
| `falcon_show_prices` | `$show, $product` |
| `falcon_hidden_price_html` | `$html, $product, $context` |
| `falcon_product_price_filter` | `$handled, $query, $min, $max` |
| `falcon_product_price_sort` | `$handled, $query, $direction` |
| `falcon_product_filter_options` | `$options, $productIds` |
| `falcon_posts_orderby` | `$handled, $query, $orderby, $order, $args` |
| `falcon_post_orderby_options` | `$options` |
| `falcon_dynamic_sources` | `$sources` |
| `falcon_dynamic_value` | `$value, $source, $post, $config` |
