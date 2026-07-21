<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\AdminMenu;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Renders and saves developer-registered options pages (see
 * `falcon_add_options_page()`). One generic controller drives every registered
 * page: it looks the page up by slug, renders its fields, and persists each field
 * to a CMS option on save — the WordPress Settings API, minus the boilerplate.
 */
class OptionsPageController extends Controller
{
    public function show(string $slug)
    {
        $page = $this->page($slug);

        $values = [];
        foreach ($this->fieldsOf($page) as $field) {
            if (! empty($field['name'])) {
                $values[$field['name']] = get_cms_option($field['name'], $field['default'] ?? '');
            }
        }

        return view('falcon-cms::admin.options-page', compact('page', 'slug', 'values'));
    }

    public function save(string $slug, Request $request)
    {
        $page = $this->page($slug);

        foreach ($this->fieldsOf($page) as $field) {
            $name = $field['name'] ?? null;
            if (! $name || falcon_is_protected_option($name)) {
                continue;
            }

            $type = $field['type'] ?? 'text';
            if ($type === 'checkbox') {
                // Unchecked checkboxes don't post a value, so store an explicit 0/1.
                update_cms_option($name, $request->boolean($name) ? '1' : '0');
            } elseif ($type === 'multiselect' || $type === 'tags') {
                // Multiple values / free-form tags are stored as a JSON array.
                update_cms_option($name, json_encode(array_values((array) $request->input($name, []))));
            } elseif ($type === 'repeater') {
                // A JSON array of row objects; drop empty non-arrays and re-index.
                $rows = array_values(array_filter((array) $request->input($name, []), 'is_array'));
                update_cms_option($name, json_encode($rows));
            } else {
                update_cms_option($name, (string) $request->input($name, ''));
            }
        }

        if (function_exists('falcon_log_activity')) {
            falcon_log_activity('options_saved', 'Saved settings page: ' . ($page['title'] ?? $slug));
        }

        // Preserve the active tab across the save redirect.
        $params = ['slug' => $slug];
        if ($tab = $request->input('_tab')) {
            $params['tab'] = $tab;
        }

        return redirect()->route('admin.options.show', $params)
            ->with('success', ($page['title'] ?? 'Settings') . ' saved.');
    }

    /**
     * Flatten a page's fields — a page has either a flat `fields` list or `tabs`,
     * each tab carrying its own `fields`. Both save into the same option store.
     *
     * @return array<int,array>
     */
    protected function fieldsOf(array $page): array
    {
        if (! empty($page['tabs'])) {
            $fields = [];
            foreach ($page['tabs'] as $tab) {
                foreach ($tab['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
            return $fields;
        }

        return $page['fields'] ?? [];
    }

    /** Resolve the registered page for a slug, or 404 / 403. */
    protected function page(string $slug): array
    {
        $page = app(AdminMenu::class)->optionsPage($slug);
        abort_if($page === null, 404);

        $capability = $page['capability'] ?? 'manage_settings';
        if (! auth()->check() || ! auth()->user()->hasPermission($capability)) {
            abort(403);
        }

        return $page;
    }
}
