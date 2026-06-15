<?php

use Illuminate\Support\Facades\Route;
use FalconCms\Core\Http\Controllers\Api\V1\CmsApiController;

Route::prefix('api/v1')->middleware(['api', 'throttle:60,1'])->group(function() {
    
    // Posts API
    Route::get('/posts', [CmsApiController::class, 'posts']);
    Route::get('/posts/{slug}', [CmsApiController::class, 'singlePost']);

    // Pages API
    Route::get('/pages', function() {
        return (new CmsApiController)->posts(request()->merge(['type' => 'page']));
    });

    // Settings API
    Route::get('/settings', [CmsApiController::class, 'settings']);

    // Menus API
    Route::get('/menus', [CmsApiController::class, 'menus']);

    // Products / eCommerce
    Route::get('/products', [CmsApiController::class, 'products']);
    Route::get('/products/{slug}', [CmsApiController::class, 'singleProduct']);

    // Taxonomies
    Route::get('/categories', [CmsApiController::class, 'categories']);
    Route::get('/tags', [CmsApiController::class, 'tags']);

    // Search (?q=)
    Route::get('/search', [CmsApiController::class, 'search']);

    // ── Write endpoints — require a Bearer API token + the matching permission ──
    Route::middleware('api.token')->group(function () {
        Route::post('/posts', [CmsApiController::class, 'storePost']);
        Route::put('/posts/{id}', [CmsApiController::class, 'updatePost']);
        Route::patch('/posts/{id}', [CmsApiController::class, 'updatePost']);
        Route::delete('/posts/{id}', [CmsApiController::class, 'destroyPost']);
    });
});
