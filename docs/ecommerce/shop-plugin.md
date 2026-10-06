# Shop Plugin & Templates

The store ships with FalconCMS as a bundled plugin, **Falcon Shop** (`falcon-shop`). It is
free, it is **on by default** — on a new install and on every site that updates — and it can be
switched off like any other plugin.

## Switching the shop off

**Admin → Plugins → Falcon Shop → Deactivate.** With the shop off:

- No shop code runs and none of its CSS or JavaScript loads, in the storefront or the admin.
- The Shop and Products menus leave the admin sidebar, and their screens answer 404. The
  dashboard and every other screen work as before.
- Products, orders, customers and settings stay in the database untouched. Switch the shop back
  on and everything is where you left it.
- Products are not served, and they are left out of search and the sitemap.
- `/cart`, `/checkout` and the other shop URLs behave as though there had never been a shop: a
  page of your own with that slug shows, otherwise the theme's 404. The pages you assigned as
  Shop, Cart, Checkout and Account are shown as ordinary pages.
- A theme that calls `route('shop.cart')` keeps working, because the shop's route names still
  resolve while it is off.

To force it off from the server, for example while you investigate a problem, add this to `.env`:

```dotenv
FALCON_DISABLED_PLUGINS=falcon-shop
```

This overrides the setting in the admin. Remove the line to hand control back to the admin.
After you change `.env`, run `php artisan optimize:clear`, as you would for any other `.env`
change. A cached configuration won't see the new value, and cached pages still show the
shop's cart icon. If the CMS finds a route cache that was built with the shop in the other
state, it deletes that cache itself.

A bundled plugin cannot be uninstalled, and it updates along with the CMS.

## Theme integration

Add the shop to a theme with two hooks in its layout. The shop fills them while it is on, and
they print nothing while it is off:

```blade
{{-- in the header, next to the search or account icon: the cart icon and its count --}}
<?php do_falcon_action('falcon_header_actions'); ?>

{{-- just before </body>: the slide-out mini-cart --}}
<?php do_falcon_action('falcon_after_footer'); ?>
```

If a theme has no `falcon_after_footer`, the mini-cart is added at `falcon_footer` instead.

Anything else in a theme that belongs to the shop needs a check first:

```blade
@if (falcon_plugin_active('falcon-shop'))
    <a href="{{ route('shop.wishlist') }}">Wishlist</a>
@endif
```

## Customising shop templates

A theme overrides a shop template by holding its own copy at the same path. The shop looks in the
active theme first, then in its parent theme, and finally uses its own template:

| Template | Page |
|---|---|
| `archive-product` | Shop page and product category / tag archives |
| `single-product` | A simple product |
| `single-product-variable` | A variable product |
| `ecommerce/cart` | Cart |
| `ecommerce/checkout` | Checkout |
| `ecommerce/confirmation` | Order confirmation |
| `ecommerce/account` | Customer account |
| `ecommerce/track-order` | Order tracking |
| `ecommerce/wishlist` | Wishlist |
| `ecommerce/mini-cart-items` | The lines inside the mini-cart |

Copy one into the theme with the command and edit the copy:

```bash
php artisan shop:template                      # every template, and which ones the theme overrides
php artisan shop:template ecommerce/cart       # copy the cart into the active theme
php artisan shop:template cart --theme=aurora  # into another theme ("cart" is enough)
```

The command will not replace a copy that already exists unless you pass `--force`. Doing that
throws away your changes to that copy.

`php artisan make:theme my-theme --shop` creates a theme that already has a copy of every
template.

### Keeping copies up to date

Every shop template starts with a version line:

```blade
{{-- @version 1.0.0 --}}
```

When a release changes a template in a way your copy has to follow, such as a new checkout field
or a renamed variable, that number goes up. If your copy carries an older number, or has no
version line at all, **Settings → Site Health** lists it as outdated, and so does
`php artisan shop:template`. Compare your copy with the current template and carry your changes
across. Keep the version line in your copy, and set it to the version you brought it up to.

## For developers

- The shop's PHP classes live under the `FalconShop\` namespace in
  `vendor/falconcms/falconcms/plugins/falcon-shop/src`. Their old `FalconCms\Core\...` names
  still work, so code written against them does not need to change.
- The models (`Order`, `Product` and the rest) and the database migrations remain in the core.
- The shop's helper functions (`falcon_shipping_methods()`, `falcon_active_promotions()` and the
  rest) exist only while the shop is on, so check `falcon_plugin_active('falcon-shop')` before
  calling one. The exceptions are the ones themes commonly call, and these return safe empty
  values while the shop is off instead of failing:
  - `get_falcon_cart_url()`
  - `get_falcon_cart_subtotal()`, `get_falcon_cart_total()`, `get_falcon_cart_shipping()`,
    `get_falcon_cart_tax()`, `get_falcon_cart_shipping_details()`
  - `get_falcon_coupon_discount_amount()`
  - `falcon_related_products()`, `falcon_linked_products()`, `falcon_cart_cross_sells()`
- Plugin assets are served from `/plugin-assets/{slug}/{path}`. Build the URL with
  `falcon_plugin_asset('falcon-shop', 'frontend/css/wishlist.css')`.
