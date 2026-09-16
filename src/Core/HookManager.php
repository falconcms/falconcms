<?php

namespace FalconCms\Core\Core;

class HookManager
{
    protected static $instance = null;

    protected $actions = [];

    protected $filters = [];

    /**
     * Hook names that were renamed, old => new.
     *
     * These tags went out under the old brand and third-party themes and plugins are hooked
     * onto them. Renaming the tag in the CMS would silently stop every one of those callbacks
     * — no error, just a filter that no longer runs — so both names are the same hook: the old
     * one is folded into the new one on the way in, whether it is being registered on, fired,
     * removed or asked about.
     *
     * Add to this map rather than leaving a tag renamed and a user's code broken.
     */
    protected const RENAMED = [
        'lazy_api_post_data' => 'falcon_api_post_data',
        'lazy_invoice_title' => 'falcon_invoice_title',
        'lazy_item_custom_fields_display' => 'falcon_item_custom_fields_display',
        'lazy_billing_fields' => 'falcon_billing_fields',
        'lazy_shipping_fields' => 'falcon_shipping_fields',
    ];

    /**
     * The name a tag is stored under — its new name, if it has one.
     *
     * One family cannot be listed: the field-group filters are built from a key a theme
     * chooses, `lazy_{$key}_fields`, so the set is open-ended and a fixed map would only ever
     * cover the keys the CMS happens to ship. The shape is matched instead.
     */
    public static function canonical(string $tag): string
    {
        if (isset(self::RENAMED[$tag])) {
            return self::RENAMED[$tag];
        }

        if (str_starts_with($tag, 'lazy_') && str_ends_with($tag, '_fields')) {
            return 'falcon_'.substr($tag, 5);
        }

        return $tag;
    }

    /** Every old name still accepted, for anything that needs to list them. */
    public static function renamedTags(): array
    {
        return self::RENAMED;
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Drop every registered hook and start again.
     *
     * The registry is a process-wide singleton, which is right for one PHP-FPM request and
     * wrong for anything longer-lived: hooks are registered during boot, so a process that
     * boots more than once — a test run, a queue worker, Octane — accumulates a second copy
     * of every callback and fires each of them twice. Call this immediately before a
     * re-boot, never during a request.
     *
     * Note what this does NOT bring back. Hooks registered while a file is being loaded —
     * helpers.php registers the whole builder element library that way — run once per
     * process and are gone for good once this is called. Prefer snapshot()/restore() when
     * what you want is isolation rather than a genuinely empty registry.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Capture the current registry, to be handed back to restore() later.
     *
     * The pair exists for callers that re-boot the application inside one process and want
     * every boot to start from the same baseline: take a snapshot before the first boot,
     * restore it before each subsequent one. Unlike reset() this keeps whatever was
     * registered at file-load time, because the snapshot was taken while it was still there.
     *
     * @return array{actions: array<string, mixed>, filters: array<string, mixed>}
     */
    public static function snapshot(): array
    {
        $instance = self::getInstance();

        return [
            'actions' => $instance->actions,
            'filters' => $instance->filters,
        ];
    }

    /**
     * @param  array{actions: array<string, mixed>, filters: array<string, mixed>}  $state
     */
    public static function restore(array $state): void
    {
        $instance = self::getInstance();
        $instance->actions = $state['actions'] ?? [];
        $instance->filters = $state['filters'] ?? [];
    }

    // Actions
    public function addAction($tag, $callback, $priority = 10)
    {
        $this->actions[self::canonical($tag)][$priority][] = $callback;
    }

    public function doAction($tag, ...$args)
    {
        $tag = self::canonical($tag);
        if (!isset($this->actions[$tag])) {
            return;
        }

        ksort($this->actions[$tag]);

        foreach ($this->actions[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    // Filters
    public function addFilter($tag, $callback, $priority = 10)
    {
        $this->filters[self::canonical($tag)][$priority][] = $callback;
    }

    public function applyFilters($tag, $value, ...$args)
    {
        $tag = self::canonical($tag);
        if (!isset($this->filters[$tag])) {
            return $value;
        }

        ksort($this->filters[$tag]);

        foreach ($this->filters[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }

        return $value;
    }

    public function removeAction($tag, $callback, $priority = 10)
    {
        $tag = self::canonical($tag);
        if (isset($this->actions[$tag][$priority])) {
            foreach ($this->actions[$tag][$priority] as $index => $registered_callback) {
                if ($registered_callback === $callback) {
                    unset($this->actions[$tag][$priority][$index]);
                    // Prune what is now empty. Left behind, the empty priority bucket keeps
                    // has_falcon_*() answering true for a hook with nothing on it.
                    if (empty($this->actions[$tag][$priority])) {
                        unset($this->actions[$tag][$priority]);
                    }
                    if (empty($this->actions[$tag])) {
                        unset($this->actions[$tag]);
                    }

                    return true;
                }
            }
        }

        return false;
    }

    public function removeFilter($tag, $callback, $priority = 10)
    {
        $tag = self::canonical($tag);
        if (isset($this->filters[$tag][$priority])) {
            foreach ($this->filters[$tag][$priority] as $index => $registered_callback) {
                if ($registered_callback === $callback) {
                    unset($this->filters[$tag][$priority][$index]);
                    // Prune what is now empty. Left behind, the empty priority bucket keeps
                    // has_falcon_*() answering true for a hook with nothing on it.
                    if (empty($this->filters[$tag][$priority])) {
                        unset($this->filters[$tag][$priority]);
                    }
                    if (empty($this->filters[$tag])) {
                        unset($this->filters[$tag]);
                    }

                    return true;
                }
            }
        }

        return false;
    }

    public function hasAction($tag)
    {
        return !empty($this->actions[self::canonical($tag)]);
    }

    public function hasFilter($tag)
    {
        return !empty($this->filters[self::canonical($tag)]);
    }
}
