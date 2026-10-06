<?php

/**
 * Dormant twins of the shop plugin's routes — loaded only while the shop plugin is OFF.
 *
 * Themes and published theme copies call route('shop.cart') and friends directly; without a
 * route of that name Laravel throws and the whole page fails. These keep every name
 * resolvable, so links still render, while answering any request with a plain 404.
 *
 * They are registered after the CMS's own routes, so a GET for /cart or /checkout is caught
 * by the frontend catch-all first: an ordinary page with that slug shows if there is one, the
 * theme's 404 if not — exactly as if there had never been a shop. Only requests the catch-all
 * does not take (POSTs, chiefly) reach the 404 here.
 *
 * Every name the plugin registers — storefront and back office — must appear here;
 * BundledPluginTest checks that they match.
 */

use Illuminate\Support\Facades\Route;

$dormant = [
    // name => [methods, uri]
    'shop.magic.email.check' => [['POST'], 'magic-email-check'],
    'shop.cart' => [['GET', 'HEAD'], 'cart'],
    'shop.cart.fragment' => [['GET', 'HEAD'], 'cart/fragment'],
    'shop.cart.add' => [['POST'], 'cart/add'],
    'shop.cart.update' => [['POST'], 'cart/update'],
    'shop.cart.remove' => [['POST'], 'cart/remove/{key}'],
    'shop.cart.coupon' => [['POST'], 'cart/apply-coupon'],
    'shop.cart.coupon.remove' => [['GET', 'HEAD'], 'cart/remove-coupon'],
    'shop.cart.shipping.update' => [['POST'], 'cart/update-shipping'],
    'shop.review.store' => [['POST'], 'cart/review'],
    'shop.checkout' => [['GET', 'HEAD'], 'checkout'],
    'shop.place-order' => [['POST'], 'checkout'],
    'shop.confirmation' => [['GET', 'HEAD'], 'order-confirmation/{id}'],
    'shop.track' => [['GET', 'POST', 'HEAD'], 'track-order'],
    'shop.account.login' => [['POST'], 'account-login'],
    'shop.account.logout' => [['POST'], 'account-logout'],
    'shop.account.profile.update' => [['POST'], 'account-profile-update'],
    'shop.account.password.update' => [['POST'], 'account-password-update'],
    'shop.account.address.save' => [['POST'], 'account-address'],
    'shop.account.address.delete' => [['POST'], 'account-address/{id}/delete'],
    'shop.account.address.default' => [['POST'], 'account-address/{id}/default'],
    'shop.download' => [['GET', 'HEAD'], 'download/{token}'],
    'shop.magic.request' => [['POST'], 'magic-login'],
    'shop.magic.verify' => [['GET', 'HEAD'], 'magic-login/{token}'],
    'shop.wishlist' => [['GET', 'HEAD'], 'wishlist'],
    'shop.wishlist.toggle' => [['POST'], 'wishlist/toggle'],
    'shop.wishlist.remove' => [['POST'], 'wishlist/remove'],
    'shop.payment.return' => [['GET', 'POST', 'HEAD'], 'payment/return/{id}'],
    'shop.payment.cancel' => [['GET', 'POST', 'HEAD'], 'payment/cancel/{id}'],
    'shop.payment.stripe.webhook' => [['POST'], 'payment/stripe/webhook'],

    // back office (admin.*): links in the admin menu, the dashboard and product screens
    'admin.product-categories.index' => [['GET', 'HEAD'], 'admin/product-categories'],
    'admin.product-categories.store' => [['POST'], 'admin/product-categories'],
    'admin.product-categories.ajax' => [['POST'], 'admin/product-categories/ajax'],
    'admin.product-categories.bulk' => [['POST'], 'admin/product-categories/bulk'],
    'admin.product-categories.edit' => [['GET', 'HEAD'], 'admin/product-categories/edit/{product_category}'],
    'admin.product-categories.update' => [['PUT'], 'admin/product-categories/{product_category}'],
    'admin.product-categories.destroy' => [['DELETE'], 'admin/product-categories/{product_category}'],
    'admin.product-tags.index' => [['GET', 'HEAD'], 'admin/product-tags'],
    'admin.product-tags.store' => [['POST'], 'admin/product-tags'],
    'admin.product-tags.ajax' => [['POST'], 'admin/product-tags/ajax'],
    'admin.product-tags.bulk' => [['POST'], 'admin/product-tags/bulk'],
    'admin.product-tags.edit' => [['GET', 'HEAD'], 'admin/product-tags/edit/{product_tag}'],
    'admin.product-tags.update' => [['PUT'], 'admin/product-tags/{product_tag}'],
    'admin.product-tags.destroy' => [['DELETE'], 'admin/product-tags/{product_tag}'],
    'admin.shop.orders.index' => [['GET', 'HEAD'], 'admin/shop/orders'],
    'admin.shop.orders.bulk' => [['POST'], 'admin/shop/orders/bulk'],
    'admin.shop.orders.show' => [['GET', 'HEAD'], 'admin/shop/orders/{id}'],
    'admin.shop.orders.invoice' => [['GET', 'HEAD'], 'admin/shop/orders/{id}/invoice'],
    'admin.shop.orders.refund' => [['POST'], 'admin/shop/orders/{id}/refund'],
    'admin.shop.orders.status' => [['POST'], 'admin/shop/orders/{id}/status'],
    'admin.shop.overview' => [['GET', 'HEAD'], 'admin/shop/overview'],
    'admin.shop.products.downloads.destroy' => [['DELETE'], 'admin/shop/products/downloads/{download}'],
    'admin.shop.products.downloads.store' => [['POST'], 'admin/shop/products/{productDataId}/downloads'],
    'admin.shop.promotions.index' => [['GET', 'HEAD'], 'admin/shop/promotions'],
    'admin.shop.promotions.store' => [['POST'], 'admin/shop/promotions'],
    'admin.shop.promotions.bulk' => [['POST'], 'admin/shop/promotions/bulk'],
    'admin.shop.promotions.create' => [['GET', 'HEAD'], 'admin/shop/promotions/create'],
    'admin.shop.promotions.update' => [['PUT'], 'admin/shop/promotions/{id}'],
    'admin.shop.promotions.destroy' => [['DELETE'], 'admin/shop/promotions/{id}'],
    'admin.shop.promotions.edit' => [['GET', 'HEAD'], 'admin/shop/promotions/{id}/edit'],
    'admin.shop.reports.index' => [['GET', 'HEAD'], 'admin/shop/reports'],
    'admin.shop.reports.export' => [['GET', 'HEAD'], 'admin/shop/reports/export'],
    'admin.shop.reviews.index' => [['GET', 'HEAD'], 'admin/shop/reviews'],
    'admin.shop.reviews.bulk' => [['POST'], 'admin/shop/reviews/bulk'],
    'admin.shop.reviews.destroy' => [['DELETE'], 'admin/shop/reviews/{review}'],
    'admin.shop.reviews.toggle-approve' => [['POST'], 'admin/shop/reviews/{review}/toggle-approve'],
    'admin.shop.settings' => [['GET', 'HEAD'], 'admin/shop/settings'],
    'admin.shop.settings.save' => [['POST'], 'admin/shop/settings'],
];

foreach ($dormant as $name => [$methods, $uri]) {
    // _falcon_dormant: the admin sidebar hides links to these (see Sidebar::routeAvailable()).
    Route::match($methods, $uri, fn () => abort(404))->name($name)->defaults('_falcon_dormant', true);
}
