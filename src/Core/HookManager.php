<?php

namespace Acme\CmsDashboard\Core;

class HookManager
{
    protected static $instance = null;
    protected $actions = [];
    protected $filters = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Actions
    public function addAction($tag, $callback, $priority = 10)
    {
        $this->actions[$tag][$priority][] = $callback;
    }

    public function doAction($tag, ...$args)
    {
        if (!isset($this->actions[$tag])) return;

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
        if (!isset($this->filters[$tag])) return $value;

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
