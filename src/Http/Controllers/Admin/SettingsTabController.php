<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\SettingsExtension;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Renders and saves the custom top-level Settings tabs registered via
 * falcon_add_settings_tab(). Each tab is its own settings page with its own
 * fields; values are stored flat in cms_settings like the native screens.
 */
class SettingsTabController extends Controller
{
    public function show(string $tab)
    {
        $config = $this->tab($tab);

        $fields = app(SettingsExtension::class)->fieldsForTab($tab)->all();
        $values = [];
        foreach ($fields as $f) {
            $values[$f['name']] = get_cms_option($f['name'], $f['default'] ?? '');
        }

        return view('falcon-cms::admin.settings.custom-tab', [
            'tab'    => $config,
            'fields' => $fields,
            'values' => $values,
        ]);
    }

    public function save(string $tab, Request $request)
    {
        $this->tab($tab);

        $registered = app(SettingsExtension::class)->fieldsForTab($tab);

        foreach ($registered as $field) {
            $name = $field['name'];
            if (falcon_is_protected_option($name)) {
                continue;
            }
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $value = $request->boolean($name) ? '1' : '0';
            } else {
                $value = $request->input($name);
                if (is_array($value)) {
                    $value = json_encode(array_values($value));
                }
                $value = $value ?? '';
            }

            DB::table('cms_settings')->updateOrInsert(
                ['key' => $name],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        forget_cms_options_cache();

        return redirect()
            ->route('admin.settings.custom-tab.show', ['tab' => $tab])
            ->with('success', 'Settings updated successfully!');
    }

    /** Resolve a registered tab config or 404 / 403. */
    protected function tab(string $tab): array
    {
        $config = app(SettingsExtension::class)->tab($tab);
        abort_if(! $config, 404);
        abort_unless(auth()->user()->hasPermission($config['permission'] ?? 'manage_settings'), 403);

        return $config;
    }
}
