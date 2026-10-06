<?php

/**
 * Back-office routes of the shop plugin — registered only while the plugin is active.
 *
 * Moved verbatim from the CMS's routes/web.php, inside the same admin group. While the plugin
 * is off the CMS registers dormant twins of these names (routes/shop-dormant.php).
 */

use FalconCms\Core\Http\Middleware\AdminMiddleware;
use FalconCms\Core\Http\Middleware\EnsureProEditable;
use FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware;
use FalconShop\Http\Controllers\Admin\ProductCategoryController;
use FalconShop\Http\Controllers\Admin\ProductDownloadController;
use FalconShop\Http\Controllers\Admin\ProductTagController;
use FalconShop\Http\Controllers\Admin\PromotionController;
use FalconShop\Http\Controllers\Admin\ReviewController;
use FalconShop\Http\Controllers\Admin\ShopController;
use FalconShop\Http\Controllers\Admin\ShopReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['web', SecurityHeadersMiddleware::class, AdminMiddleware::class])->group(function () {
    // Product Categories & Tags — Pro (e-commerce), "browse but locked": the pages are viewable;
    // EnsureProEditable (method-aware) gates only the write methods.
    Route::middleware(EnsureProEditable::class.':ecommerce')->group(function () {
        // Product Categories (dedicated, first-class — mirrors Categories)
        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::post('product-categories/bulk', [ProductCategoryController::class, 'bulk'])->name('product-categories.bulk');
        Route::post('product-categories/ajax', [ProductCategoryController::class, 'ajax'])->name('product-categories.ajax');
        Route::get('product-categories/edit/{product_category}', [ProductCategoryController::class, 'edit'])->name('product-categories.edit');
        Route::put('product-categories/{product_category}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
        Route::delete('product-categories/{product_category}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');

        // Product Tags (dedicated, first-class — mirrors Tags)
        Route::get('product-tags', [ProductTagController::class, 'index'])->name('product-tags.index');
        Route::post('product-tags', [ProductTagController::class, 'store'])->name('product-tags.store');
        Route::post('product-tags/bulk', [ProductTagController::class, 'bulk'])->name('product-tags.bulk');
        Route::post('product-tags/ajax', [ProductTagController::class, 'ajax'])->name('product-tags.ajax');
        Route::get('product-tags/edit/{product_tag}', [ProductTagController::class, 'edit'])->name('product-tags.edit');
        Route::put('product-tags/{product_tag}', [ProductTagController::class, 'update'])->name('product-tags.update');
        Route::delete('product-tags/{product_tag}', [ProductTagController::class, 'destroy'])->name('product-tags.destroy');
    }); // end EnsurePro:ecommerce — Product Categories & Tags

    // Shop Management — Pro (e-commerce), "browse but locked": the shop back-office is viewable
    // so owners can look around; EnsureProEditable (method-aware) gates only the write actions.
    Route::prefix('shop')->name('shop.')->middleware(EnsureProEditable::class.':ecommerce')->group(function () {
        Route::get('overview', [ShopController::class, 'overview'])->name('overview');
        Route::get('orders', [ShopController::class, 'orders'])->name('orders.index');
        Route::post('orders/bulk', [ShopController::class, 'ordersBulk'])->name('orders.bulk');
        Route::get('orders/{id}', [ShopController::class, 'orderShow'])->name('orders.show');
        Route::get('orders/{id}/invoice', [ShopController::class, 'orderInvoice'])->name('orders.invoice');
        Route::post('orders/{id}/status', [ShopController::class, 'orderUpdateStatus'])->name('orders.status');
        Route::post('orders/{id}/refund', [ShopController::class, 'orderRefund'])->name('orders.refund');
        Route::get('settings', [ShopController::class, 'settings'])->name('settings');
        Route::post('settings', [ShopController::class, 'saveSettings'])->name('settings.save');

        // Sales Reports
        Route::get('reports', [ShopReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ShopReportController::class, 'export'])->name('reports.export');

        // Product download files (admin)
        Route::post('products/{productDataId}/downloads', [ProductDownloadController::class, 'store'])->name('products.downloads.store');
        Route::delete('products/downloads/{download}', [ProductDownloadController::class, 'destroy'])->name('products.downloads.destroy');

        // Promotions — automatic cart rules (buy X get Y), no coupon code involved.
        Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::post('promotions/bulk', [PromotionController::class, 'bulk'])->name('promotions.bulk');
        Route::get('promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
        Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
        Route::get('promotions/{id}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
        Route::put('promotions/{id}', [PromotionController::class, 'update'])->name('promotions.update');
        Route::delete('promotions/{id}', [PromotionController::class, 'destroy'])->name('promotions.destroy');

        // Reviews
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/toggle-approve', [ReviewController::class, 'toggleApprove'])->name('reviews.toggle-approve');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('reviews/bulk', [ReviewController::class, 'bulk'])->name('reviews.bulk');
    });
});
