<?php

/**
 * Storefront routes of the shop plugin — registered only while the plugin is active.
 *
 * Moved verbatim from the CMS's routes/web.php, inside the same middleware groups they had
 * there. While the plugin is off, the CMS registers dormant twins of these route names
 * (routes/shop-dormant.php), so route('shop.cart') in a theme still resolves instead of
 * failing, and the URLs fall through to ordinary pages or the theme's 404.
 */

use FalconCms\Core\Http\Middleware\EnsurePro;
use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Http\Middleware\MaintenanceModeMiddleware;
use FalconCms\Core\Http\Middleware\PageCacheMiddleware;
use FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware;
use FalconShop\Http\Controllers\ShopFrontendController;
use FalconShop\Http\Controllers\WishlistController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SecurityHeadersMiddleware::class])->group(function () {
    // Frontend magic email check (AJAX, rate-limited)
    Route::post('magic-email-check', [ShopFrontendController::class, 'checkMagicEmail'])->name('shop.magic.email.check')->middleware('throttle:30,1');
});

Route::middleware(['web', SecurityHeadersMiddleware::class, MaintenanceModeMiddleware::class, PageCacheMiddleware::class, HtmlOptimizeMiddleware::class])->group(function () {
    // Shop Frontend — Pro (e-commerce). Route names stay REGISTERED (published themes call
    // route('shop.*') directly, so removing them would 500 the whole site). 'strict' mode
    // ignores grandfathering: the storefront (cart/checkout/account/add-to-cart) locks the
    // moment the freemium grace window ends — browsing products stays open elsewhere.
    Route::middleware(EnsurePro::class.':ecommerce,strict')->group(function () {
        Route::prefix('cart')->name('shop.')->group(function () {
            Route::get('/', [ShopFrontendController::class, 'cart'])->name('cart');
            Route::get('/fragment', [ShopFrontendController::class, 'miniCart'])->name('cart.fragment');
            Route::post('/add', [ShopFrontendController::class, 'addToCart'])->name('cart.add')->middleware('throttle:30,1');
            Route::post('/update', [ShopFrontendController::class, 'updateCart'])->name('cart.update')->middleware('throttle:30,1');
            Route::post('/remove/{key}', [ShopFrontendController::class, 'removeFromCart'])->name('cart.remove')->middleware('throttle:30,1');
            Route::post('/apply-coupon', [ShopFrontendController::class, 'applyCoupon'])->name('cart.coupon')->middleware('throttle:10,1');
            Route::get('/remove-coupon', [ShopFrontendController::class, 'removeCoupon'])->name('cart.coupon.remove');
            Route::post('/update-shipping', [ShopFrontendController::class, 'updateShipping'])->name('cart.shipping.update')->middleware('throttle:20,1');
            Route::post('/review', [ShopFrontendController::class, 'storeReview'])->name('review.store')->middleware('throttle:5,1');
        });
        Route::get('/checkout', [ShopFrontendController::class, 'checkout'])->name('shop.checkout');
        Route::post('/checkout', [ShopFrontendController::class, 'placeOrder'])->name('shop.place-order');
        // Post-purchase customer access stays open even after grace ends — a paid
        // customer must always reach their order confirmation, tracking and digital
        // downloads (each verifies ownership in the controller).
        Route::get('/order-confirmation/{id}', [ShopFrontendController::class, 'confirmation'])->name('shop.confirmation')
            ->withoutMiddleware([EnsurePro::class.':ecommerce,strict']);

        // Order tracking
        Route::match(['get', 'post'], '/track-order', [ShopFrontendController::class, 'trackOrder'])->name('shop.track')
            ->withoutMiddleware([EnsurePro::class.':ecommerce,strict']);

        // Account page login / logout / profile / password
        Route::post('/account-login', [ShopFrontendController::class, 'accountLogin'])->name('shop.account.login');
        Route::post('/account-logout', [ShopFrontendController::class, 'accountLogout'])->name('shop.account.logout');
        Route::post('/account-profile-update', [ShopFrontendController::class, 'updateProfile'])->name('shop.account.profile.update');
        Route::post('/account-password-update', [ShopFrontendController::class, 'updatePassword'])->name('shop.account.password.update');

        // Saved addresses. Every action re-checks ownership in the controller — the id in the URL is
        // a claim, not a permission.
        Route::post('/account-address', [ShopFrontendController::class, 'saveAddress'])->name('shop.account.address.save')->middleware('throttle:20,1');
        Route::post('/account-address/{id}/delete', [ShopFrontendController::class, 'deleteAddress'])->name('shop.account.address.delete')->middleware('throttle:20,1');
        Route::post('/account-address/{id}/default', [ShopFrontendController::class, 'setDefaultAddress'])->name('shop.account.address.default')->middleware('throttle:20,1');

        // Digital downloads (token-based, no auth required)
        Route::get('/download/{token}', [ShopFrontendController::class, 'downloadFile'])->name('shop.download')->middleware('throttle:30,1')
            ->withoutMiddleware([EnsurePro::class.':ecommerce,strict']);

        // Magic login (passwordless)
        Route::post('/magic-login', [ShopFrontendController::class, 'requestMagicLink'])->name('shop.magic.request')->middleware('throttle:5,1');
        Route::get('/magic-login/{token}', [ShopFrontendController::class, 'verifyMagicLink'])->name('shop.magic.verify');

        // Wishlist
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('shop.wishlist');
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('shop.wishlist.toggle');
        Route::post('/wishlist/remove', [WishlistController::class, 'remove'])->name('shop.wishlist.remove');

        // Online payment gateway return / cancel — gateways (e.g. SSLCommerz) POST here without a CSRF token.
        //
        // BOTH CSRF classes are listed on purpose. Laravel 11+ registers
        // PreventRequestForgery in the `web` group and keeps VerifyCsrfToken only as a legacy
        // subclass; withoutMiddleware() matches on the registered name, so excluding just the old
        // name silently did nothing and these callbacks were answering 419.
        Route::match(['get', 'post'], '/payment/return/{id}', [ShopFrontendController::class, 'paymentReturn'])
            ->name('shop.payment.return')
            ->withoutMiddleware([
                PreventRequestForgery::class,
                VerifyCsrfToken::class,
                EnsurePro::class.':ecommerce,strict',
            ]);
        Route::match(['get', 'post'], '/payment/cancel/{id}', [ShopFrontendController::class, 'paymentCancel'])
            ->name('shop.payment.cancel')
            ->withoutMiddleware([
                PreventRequestForgery::class,
                VerifyCsrfToken::class,
                EnsurePro::class.':ecommerce,strict',
            ]);

        // Stripe webhook — the reliable half of payment confirmation (the browser return URL is
        // best-effort; a customer who closes the tab never hits it). Stripe signs the request and
        // the controller verifies that signature, which is the only authentication here.
        // Stays outside the Pro gate so a store that lapses still reconciles payments already taken.
        Route::post('/payment/stripe/webhook', [ShopFrontendController::class, 'stripeWebhook'])
            ->name('shop.payment.stripe.webhook')
            ->middleware('throttle:300,1')
            ->withoutMiddleware([
                PreventRequestForgery::class,
                VerifyCsrfToken::class,
                EnsurePro::class.':ecommerce,strict',
            ]);
    }); // end EnsurePro:ecommerce — Shop Frontend
});
