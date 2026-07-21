<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Collection;

/**
 * Runtime registry for extending the CMS's native Settings area.
 *
 * WordPress analogue: add_settings_field() + a settings sub-page.
 *
 * Two things can be registered:
 *   • Fields  — extra fields injected into an existing settings screen
 *               ('general' → /admin/settings, 'seo' → /admin/settings/seo).
 *               They render as native table rows inside the native <form>, so
 *               they save through the existing settings controller.
 *   • Tabs    — brand-new top-level tabs in the Settings nav bar. Each tab is
 *               its own page (/admin/settings/tab/{id}) with its own fields and
 *               Save button, styled exactly like the native settings screens.
 *
 * Values are read/written with get_cms_option(). Registrations may be made
 * directly in a theme's functions.php or on the `falcon_register_settings`
 * action (fired once, lazily).
 */
class SettingsExtension
{
    /** Custom top-level tabs, keyed by id. Each: id, label, icon, order, permission. */
    protected array $tabs = [];

    /** Extra fields (flat list). Each is a field def plus screen + optional tab. */
    protected array $fields = [];

    protected bool $collected = false;

    /**
     * Register a new top-level tab (its own settings page) in the nav bar.
     */
    public function addTab(array $args): void
    {
        $id = self::sanitizeKey($args['id'] ?? null);
        if ($id === '') {
            return;
        }
        $this->tabs[$id] = [
            'id'         => $id,
            'label'      => $args['label'] ?? \Illuminate\Support\Str::headline($id),
            'icon'       => $args['icon'] ?? null,
            'order'      => $args['order'] ?? 100,
            'permission' => $args['permission'] ?? 'manage_settings',
        ];
    }

    /**
     * Register a field.
     *
     * With a `tab` it renders on that custom tab's page. Without a `tab` it
     * renders inline on the native `screen` page ('general' or 'seo'), as a
     * native settings row. Supports every type the field-control renderer
     * knows (text, textarea, select, checkbox, radio, color, date, image,
     * file, range, multiselect, tags, wysiwyg, repeater, …).
     */
    public function addField(array $args): void
    {
        // Names/ids flow into HTML name attributes and inline JS (onclick, Alpine
        // x-show); restrict them to a safe charset so a field def can't inject.
        $name = self::sanitizeKey($args['name'] ?? $args['id'] ?? null);
        if ($name === '') {
            return;
        }
        $args['name']   = $name;
        $args['tab']    = isset($args['tab']) ? (self::sanitizeKey($args['tab']) ?: null) : null;
        $args['screen'] = $args['screen'] ?? 'general';
        $args['order']  = $args['order'] ?? 100;
        $this->fields[] = $args;
    }

    /** Restrict a field/tab identifier to [A-Za-z0-9_-]. */
    protected static function sanitizeKey($value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $value) ?? '';
    }

    /** Fire the lazy registration action exactly once. */
    protected function collect(): void
    {
        if ($this->collected) {
            return;
        }
        $this->collected = true;
        if (function_exists('do_falcon_action')) {
            do_falcon_action('falcon_register_settings', $this);
        }
    }

    /** All custom top-level tabs, ordered — used to render the nav bar. */
    public function tabs(): Collection
    {
        $this->collect();
        return collect($this->tabs)->sortBy('order')->values();
    }

    /** A single tab's config, or null. */
    public function tab(string $id): ?array
    {
        $this->collect();
        return $this->tabs[$id] ?? null;
    }

    /** True when a native screen has any inline (untabbed) fields to render. */
    public function hasInline(string $screen): bool
    {
        $this->collect();
        foreach ($this->fields as $f) {
            if (empty($f['tab']) && ($f['screen'] ?? 'general') === $screen) {
                return true;
            }
        }
        return false;
    }

    /** Inline (untabbed) fields for a native screen, ordered. */
    public function inlineFields(string $screen): Collection
    {
        $this->collect();
        return collect($this->fields)
            ->filter(fn ($f) => empty($f['tab']) && ($f['screen'] ?? 'general') === $screen)
            ->sortBy('order')
            ->values();
    }

    /** Fields belonging to a custom tab page, ordered. */
    public function fieldsForTab(string $tabId): Collection
    {
        $this->collect();
        return collect($this->fields)
            ->filter(fn ($f) => ($f['tab'] ?? null) === $tabId)
            ->sortBy('order')
            ->values();
    }

    /**
     * All fields for a screen, grouped by their target tab id (falling back to
     * $defaultTab when a field declares none). Used by screens that already have
     * their own client-side tab UI (e.g. Shop's Alpine tabs), so injected fields
     * can render inside the matching tab panel instead of always-on at the bottom.
     */
    public function fieldsForScreenGrouped(string $screen, string $defaultTab = 'general'): Collection
    {
        $this->collect();
        return collect($this->fields)
            ->filter(fn ($f) => ($f['screen'] ?? 'general') === $screen)
            ->sortBy('order')
            ->groupBy(fn ($f) => $f['tab'] ?? $defaultTab);
    }

    /**
     * Persist all inline fields registered for a native screen. Native save
     * controllers that save a whitelist (rather than every posted key) call
     * this so injected fields still save. Screens whose controller already
     * saves all posted fields (General, SEO) don't need it.
     */
    public function persist(string $screen, \Illuminate\Http\Request $request): void
    {
        foreach ($this->inlineFields($screen) as $field) {
            $name = $field['name'];
            // A registered field must not be able to overwrite internal/licensing keys.
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

            \Illuminate\Support\Facades\DB::table('cms_settings')->updateOrInsert(
                ['key' => $name],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }
}
