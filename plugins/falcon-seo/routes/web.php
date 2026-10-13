<?php

/**
 * Site routes of Falcon SEO — registered only while the plugin is active, before the CMS's
 * own routes (so ahead of its frontend catch-all).
 */

use FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware;
use FalconSeo\Http\Controllers\Admin\WebVitalsController;
use FalconSeo\Http\Controllers\IndexNowKeyController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', SecurityHeadersMiddleware::class])->group(function () {
    // Real visitors' Core Web Vitals, sent with sendBeacon (no CSRF token: a plain beacon, rate-limited,
    // and only ever adds a measurement). Both CSRF classes are named, as for the shop's gateway callbacks.
    Route::post('/falcon-vitals', [WebVitalsController::class, 'store'])->name('frontend.web-vitals')
        ->middleware('throttle:30,1')
        ->withoutMiddleware([PreventRequestForgery::class, VerifyCsrfToken::class]);
    // IndexNow ownership key: answers only for this site's own key, and only while IndexNow is on.
    Route::get('/{indexnowKey}.txt', IndexNowKeyController::class)->name('frontend.indexnow-key')->where('indexnowKey', '[a-f0-9]{32}');
});
