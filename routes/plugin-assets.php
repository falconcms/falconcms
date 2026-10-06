<?php

/**
 * Active plugins' static files (CSS, JS, images, fonts from a plugin's assets/ folder).
 *
 * No middleware group: a stylesheet needs no session, cookie or page cache. Loaded before the
 * frontend catch-all so it is reachable.
 */

use FalconCms\Core\Http\Controllers\PluginAssetController;
use Illuminate\Support\Facades\Route;

Route::get('plugin-assets/{slug}/{path}', PluginAssetController::class)
    ->where(['slug' => '[A-Za-z0-9_\-]+', 'path' => '.*'])
    ->name('falcon.plugin-asset');
