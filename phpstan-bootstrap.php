<?php

/**
 * PHPStan bootstrap: declare the shop plugin's helper functions.
 *
 * The core helpers reach PHPStan through Composer's autoload "files"; a plugin's helpers are
 * only loaded at runtime while the plugin is active, so without this every call to one of them
 * would read as "function not found".
 */
foreach (glob(__DIR__.'/plugins/*/src/helpers/*.php') ?: [] as $file) {
    require_once $file;
}
