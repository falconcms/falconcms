<?php

namespace FalconCms\Core\Core;

class HookManager
{
    protected static $instance = null;

    protected $actions = [];

    protected $filters = [];

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
        $this->actions[$tag][$priority][] = $callback;
    }

    public function doAction($tag, ...$args)
    {
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
        $this->filters[$tag][$priority][] = $callback;
    }

    public function applyFilters($tag, $value, ...$args)
    {
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
        if (isset($this->actions[$tag][$priority])) {
            foreach ($this->actions[$tag][$priority] as $index => $registered_callback) {
                if ($registered_callback === $callback) {
                    unset($this->actions[$tag][$priority][$index]);

                    return true;
                }
            }
        }

        return false;
    }

    public function removeFilter($tag, $callback, $priority = 10)
    {
        if (isset($this->filters[$tag][$priority])) {
            foreach ($this->filters[$tag][$priority] as $index => $registered_callback) {
                if ($registered_callback === $callback) {
                    unset($this->filters[$tag][$priority][$index]);

                    return true;
                }
            }
        }

        return false;
    }

    public function hasAction($tag)
    {
        return !empty($this->actions[$tag]);
    }

    public function hasFilter($tag)
    {
        return !empty($this->filters[$tag]);
    }
}
