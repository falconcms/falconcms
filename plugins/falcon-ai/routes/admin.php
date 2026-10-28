<?php

/**
 * Back-office routes of Falcon AI — registered only while the plugin is active.
 *
 * AI → Settings belongs to the AI menu (manage_ai). The writing endpoints belong to no menu: the
 * plugin decides them (Access::canWrite, through falcon_admin_path_access), and each request
 * checks again in its FormRequest. Every one is rate limited; they spend the site's own money.
 */

use FalconAi\Http\Controllers\Admin\SettingsController;
use FalconAi\Http\Controllers\Admin\WriterController;
use FalconCms\Core\Http\Middleware\AdminMiddleware;
use FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/ai')->name('admin.ai.')->middleware(['web', SecurityHeadersMiddleware::class, AdminMiddleware::class])->group(function () {
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('settings', [SettingsController::class, 'save'])->name('settings.save');
    Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test')->middleware('throttle:10,1');

    Route::post('write/outline', [WriterController::class, 'outline'])->name('write.outline')->middleware('throttle:20,1');
    Route::post('write/section', [WriterController::class, 'section'])->name('write.section')->middleware('throttle:60,1');
    Route::post('write/meta', [WriterController::class, 'meta'])->name('write.meta')->middleware('throttle:20,1');
    Route::post('write/rewrite', [WriterController::class, 'rewrite'])->name('write.rewrite')->middleware('throttle:30,1');
    Route::post('seo/suggest', [WriterController::class, 'seoSuggest'])->name('seo.suggest')->middleware('throttle:20,1');
});
