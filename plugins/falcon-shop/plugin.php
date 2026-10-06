<?php

/**
 * Falcon Shop — bootstrap file.
 *
 * Loaded only while the plugin is active, at the same point in the boot cycle as a theme's
 * functions.php. Nothing in here may run while the plugin is off: that is what keeps a site
 * without a shop free of shop code, assets and queries.
 *
 * Layout of this plugin:
 *   assets/frontend   CSS/JS for the storefront (served by falcon_plugin_asset())
 *   assets/admin      CSS/JS for the shop back office
 *   resources/views/frontend, resources/views/admin   templates ("falcon-shop::...")
 *   routes/web.php    storefront and back-office routes
 *   src/              classes (namespace FalconShop\)
 *
 * The shop's data — tables, migrations and models — stays in the CMS core, so switching this
 * plugin off (or on again) never touches a single order, product or customer.
 */

// Storefront helpers (cart totals, shipping, tax, promotions, checkout fields, related products).
// The data-layer helpers the CMS itself uses — get_shop_option(), falcon_price_format(), … —
// stay in the core; while this plugin is off the core defines safe stand-ins for the few of
// these a theme might call (src/helpers/shop-fallbacks.php).
foreach (glob(__DIR__.'/src/helpers/*.php') ?: [] as $falconShopHelpers) {
    require_once $falconShopHelpers;
}
unset($falconShopHelpers);
