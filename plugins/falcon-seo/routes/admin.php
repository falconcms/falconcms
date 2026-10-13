<?php

/**
 * Back-office routes of Falcon SEO — registered only while the plugin is active.
 *
 * Moved from the CMS's routes/web.php with their names unchanged, inside the same admin group.
 * With the plugin off the names are gone: the menu hides their entries, and core screens ask
 * Route::has() before linking to them.
 */

use FalconCms\Core\Http\Middleware\AdminMiddleware;
use FalconCms\Core\Http\Middleware\SecurityHeadersMiddleware;
use FalconSeo\Http\Controllers\Admin\BulkSeoController;
use FalconSeo\Http\Controllers\Admin\KeywordResearchController;
use FalconSeo\Http\Controllers\Admin\MediaAvifController;
use FalconSeo\Http\Controllers\Admin\PageAnalysisController;
use FalconSeo\Http\Controllers\Admin\SeoAuditController;
use FalconSeo\Http\Controllers\Admin\SeoToolsController;
use FalconSeo\Http\Controllers\Admin\WebVitalsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['web', SecurityHeadersMiddleware::class, AdminMiddleware::class])->group(function () {
    Route::post('media/avif-generate', [MediaAvifController::class, 'generateAvif'])->name('media.avif-generate');
    Route::get('analytics/web-vitals', [WebVitalsController::class, 'index'])->name('analytics.vitals');

    Route::get('settings/seo-audit', [SeoAuditController::class, 'index'])->name('settings.seo-audit');
    Route::get('settings/seo-bulk', [BulkSeoController::class, 'index'])->name('settings.seo-bulk');
    Route::post('settings/seo-bulk', [BulkSeoController::class, 'update'])->name('settings.seo-bulk.update');
    Route::post('settings/integrations/cloudflare-purge', [SeoToolsController::class, 'purgeCloudflare'])->name('settings.integrations.cloudflare-purge');

    Route::post('seo/redirects/not-found/forget', [SeoToolsController::class, 'forgetNotFound'])->name('redirects.not-found.forget');
    Route::get('seo/related-posts', [SeoToolsController::class, 'relatedPosts'])->name('seo.related-posts');
    Route::post('seo/keyword-suggestions', [SeoToolsController::class, 'keywordSuggestions'])->name('seo.keyword-suggestions');
    Route::post('seo/keyword-demand', [KeywordResearchController::class, 'demand'])->name('seo.keyword-demand')->middleware('throttle:60,1');
    Route::post('seo/keyword-countries', [KeywordResearchController::class, 'countries'])->name('seo.keyword-countries')->middleware('throttle:60,1');
    Route::post('seo/page-analysis', [PageAnalysisController::class, 'analyze'])->name('seo.page-analysis')->middleware('throttle:30,1');
    Route::post('settings/seo/keywords-import', [KeywordResearchController::class, 'import'])->name('settings.seo.keywords-import');
    Route::post('settings/seo/keywords-clear', [KeywordResearchController::class, 'clear'])->name('settings.seo.keywords-clear');
});
