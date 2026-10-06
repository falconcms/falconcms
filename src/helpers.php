<?php

/**
 * FalconCMS global helpers — the WordPress-style API that themes, plugins and the CMS call.
 *
 * The helpers live in src/helpers/, one file per topic. This file only defines the version
 * constant and loads them. Composer autoloads this file ("files" in composer.json) and the
 * service provider requires it again; both are safe, because every helper is wrapped in a
 * function_exists() guard and the topic files are loaded with require_once.
 *
 * Adding a helper: put it in the topic file it belongs to, inside an
 * if (!function_exists('...')) guard. If no file fits, or a file has grown past roughly
 * 1,000 lines, start a new topic file and add it to the list below. Do not let this file,
 * or any one topic file, grow back into a catch-all.
 *
 * Order: hooks first, builder-elements last. builder-elements calls add_falcon_filter()
 * while it loads; everything else only defines functions, so its order does not matter.
 */
if (!defined('FALCON_CMS_VERSION')) {
    $__versionFile = __DIR__.'/../version.json';
    $__versionData = file_exists($__versionFile) ? json_decode(file_get_contents($__versionFile), true) : [];
    define('FALCON_CMS_VERSION', $__versionData['version'] ?? '1.2.0');
    unset($__versionFile, $__versionData);
}

foreach ([
    'hooks',
    'shortcodes',
    'options',
    'system',
    'plugins',
    'licensing',
    'security',
    'datetime',
    'activity-log',
    'icons-fonts',
    'styles',
    'content',
    'custom-fields',
    'dynamic',
    'layouts',
    'theme',
    'menus',
    'i18n',
    'shop-catalog',
    'shop-attributes',
    'shop-filters',
    'shop-related',
    'shop-cart',
    'shop-promotions',
    'shop-shipping',
    'shop-tax',
    'shop-checkout',
    'builder-elements',
] as $__falconHelperFile) {
    require_once __DIR__.'/helpers/'.$__falconHelperFile.'.php';
}
unset($__falconHelperFile);
