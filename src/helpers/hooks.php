<?php

/**
 * Hook system: actions and filters.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Core\HookManager;

// --- Hook System Helpers ---

if (!function_exists('add_falcon_action')) {
    function add_falcon_action($tag, $callback, $priority = 10)
    {
        HookManager::getInstance()->addAction($tag, $callback, $priority);
    }
}

if (!function_exists('do_falcon_action')) {
    function do_falcon_action($tag, ...$args)
    {
        HookManager::getInstance()->doAction($tag, ...$args);
    }
}

if (!function_exists('add_falcon_filter')) {
    function add_falcon_filter($tag, $callback, $priority = 10)
    {
        HookManager::getInstance()->addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('apply_falcon_filters')) {
    function apply_falcon_filters($tag, $value, ...$args)
    {
        return HookManager::getInstance()->applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('has_falcon_action')) {
    function has_falcon_action($tag)
    {
        return HookManager::getInstance()->hasAction($tag);
    }
}

if (!function_exists('has_falcon_filter')) {
    function has_falcon_filter($tag)
    {
        return HookManager::getInstance()->hasFilter($tag);
    }
}

if (!function_exists('remove_falcon_action')) {
    function remove_falcon_action($tag, $callback, $priority = 10)
    {
        return HookManager::getInstance()->removeAction($tag, $callback, $priority);
    }
}

if (!function_exists('remove_falcon_filter')) {
    function remove_falcon_filter($tag, $callback, $priority = 10)
    {
        return HookManager::getInstance()->removeFilter($tag, $callback, $priority);
    }
}
