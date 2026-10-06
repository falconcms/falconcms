<?php

/**
 * Custom fields (ACPT) and their rendering.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\Post;
use Illuminate\Support\Facades\DB;

if (!function_exists('get_custom_field')) {
    function get_custom_field($post, $fieldName, $default = null)
    {
        try {
            $postId = is_object($post) ? $post->id : $post;
            $value = DB::table('post_custom_field_values')
                ->join('custom_fields', 'post_custom_field_values.field_id', '=', 'custom_fields.id')
                ->where('post_custom_field_values.post_id', $postId)
                ->where('custom_fields.name', $fieldName)
                ->value('post_custom_field_values.value');

            return $value !== null ? $value : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('get_acpt_field')) {
    /**
     * Resolve a single ACPT / custom-field value for a post by the field's slug.
     * The "Field Slug" entered in the builder maps to the `custom_fields.name` column.
     * Returns the raw stored value, or $default when the field or its value is absent.
     *
     * Used by falcon_resolve_dynamic_value() for the `acpt_custom` dynamic source.
     */
    function get_acpt_field($post, string $slug, $default = '')
    {
        try {
            $postId = is_object($post) ? ($post->id ?? null) : $post;
            if (!$postId || $slug === '') {
                return $default;
            }

            $value = DB::table('post_custom_field_values')
                ->join('custom_fields', 'post_custom_field_values.field_id', '=', 'custom_fields.id')
                ->where('post_custom_field_values.post_id', $postId)
                ->where('custom_fields.name', $slug)
                ->value('post_custom_field_values.value');

            return $value !== null ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('get_post_custom_fields')) {
    /**
     * Resolve ALL custom fields that apply to a post (by its type's field groups) as a
     * keyed array [field_name => value]. Data-driven: it reads the field DEFINITIONS and
     * VALUES from the database at call time, so adding/removing a field in a field group
     * is reflected automatically everywhere — including the REST API — with no code change.
     *
     * JSON-encoded values (repeaters, galleries, checkboxes, etc.) are decoded to arrays.
     * Fields with no value yet are returned as null so consumers always see the schema.
     */
    function get_post_custom_fields($post): array
    {
        try {
            $post = is_object($post) ? $post : Post::find($post);
            if (!$post) {
                return [];
            }
            $type = $post->type;

            $groupIds = DB::table('custom_field_groups')
                ->where('is_active', 1)->get()
                ->filter(function ($g) use ($type) {
                    $rules = json_decode($g->rules ?? '', true);
                    $pt = is_array($rules) ? ($rules['post_type'] ?? null) : null;

                    return is_array($pt) ? in_array($type, $pt, true) : $pt === $type;
                })->pluck('id');

            if ($groupIds->isEmpty()) {
                return [];
            }

            $fields = DB::table('custom_fields')->whereIn('field_group_id', $groupIds)->orderBy('order')->get();
            $values = DB::table('post_custom_field_values')->where('post_id', $post->id)->pluck('value', 'field_id');

            $out = [];
            foreach ($fields as $f) {
                $raw = $values[$f->id] ?? null;
                if (is_string($raw) && strlen($raw) && in_array($raw[0], ['[', '{'], true)) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $raw = $decoded;
                    }
                }
                $out[$f->name] = $raw;
            }

            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('falcon_render_product_field')) {
    function falcon_render_product_field(array $field): string
    {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $required = !empty($field['required']);
        $value = $field['value'] ?? '';
        $class = $field['class'] ?? '';
        $rows = (int) ($field['rows'] ?? 3);
        $min = $field['min'] ?? null;
        $max = $field['max'] ?? null;
        $options = $field['options'] ?? [];
        $hint = $field['hint'] ?? '';

        // wrapper: HTML tag ('div', 'li', 'p', 'span', false = no wrapper)
        $wrapperTag = $field['wrapper'] ?? 'div';
        $wrapperClass = $field['wrapper_class'] ?? 'mb-4';
        // extra attributes on the wrapper element (e.g. 'data-foo="bar"')
        $wrapperAttrs = $field['wrapper_attrs'] ?? '';

        $baseInput = 'w-full border border-gray-300 rounded-sm px-3 py-2 text-sm focus:outline-none focus:border-gray-500 '.$class;
        $req = $required ? ' required' : '';
        $reqStar = $required ? '<span class="text-red-500 ml-0.5">*</span>' : '';

        $inner = '';

        switch ($type) {
            case 'textarea':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $inner .= '<textarea name="'.e($name).'" rows="'.$rows.'" placeholder="'.e($placeholder).'" class="'.e($baseInput).'"'.$req.'>'.e($value).'</textarea>';
                break;

            case 'select':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $inner .= '<select name="'.e($name).'" class="'.e($baseInput).'"'.$req.'>';
                if ($placeholder) {
                    $inner .= '<option value="">'.e($placeholder).'</option>';
                }
                foreach ($options as $optVal => $optLabel) {
                    $selected = ($value == $optVal) ? ' selected' : '';
                    $inner .= '<option value="'.e($optVal).'"'.$selected.'>'.e($optLabel).'</option>';
                }
                $inner .= '</select>';
                break;

            case 'radio':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $inner .= '<div class="flex flex-wrap gap-3 mt-1">';
                foreach ($options as $optVal => $optLabel) {
                    $checked = ($value == $optVal) ? ' checked' : '';
                    $inner .= '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">';
                    $inner .= '<input type="radio" name="'.e($name).'" value="'.e($optVal).'"'.$checked.$req.' class="accent-primary">';
                    $inner .= e($optLabel).'</label>';
                }
                $inner .= '</div>';
                break;

            case 'checkbox':
                $checked = !empty($field['checked']) ? ' checked' : '';
                $inner .= '<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">';
                $inner .= '<input type="checkbox" name="'.e($name).'" value="'.e($value ?: '1').'"'.$checked.' class="accent-primary">';
                $inner .= e($label).$reqStar.'</label>';
                break;

            case 'number':
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $minAttr = $min !== null ? ' min="'.e($min).'"' : '';
                $maxAttr = $max !== null ? ' max="'.e($max).'"' : '';
                $inner .= '<input type="number" name="'.e($name).'" value="'.e($value).'" placeholder="'.e($placeholder).'" class="'.e($baseInput).'"'.$minAttr.$maxAttr.$req.'>';
                break;

            case 'hidden':
                return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';

            case 'raw':
                // 'content' key — raw HTML, trusted developer input
                $inner = $field['content'] ?? '';
                break;

            default: // text, email, tel, url, date, etc.
                if ($label) {
                    $inner .= '<label class="block text-sm font-medium text-gray-700 mb-1">'.e($label).$reqStar.'</label>';
                }
                $minAttr = $min !== null ? ' minlength="'.e($min).'"' : '';
                $maxAttr = $max !== null ? ' maxlength="'.e($max).'"' : '';
                $inner .= '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" placeholder="'.e($placeholder).'" class="'.e($baseInput).'"'.$minAttr.$maxAttr.$req.'>';
                break;
        }

        if ($hint) {
            $inner .= '<p class="text-xs text-gray-400 mt-1">'.e($hint).'</p>';
        }

        // No wrapper
        if (!$wrapperTag) {
            return $inner;
        }

        $tag = preg_replace('/[^a-z0-9]/', '', strtolower($wrapperTag));

        return '<'.$tag.($wrapperClass ? ' class="'.e($wrapperClass).'"' : '').($wrapperAttrs ? ' '.$wrapperAttrs : '').'>'
            .$inner
            .'</'.$tag.'>';
    }
}

if (!function_exists('falcon_render_product_fields')) {
    function falcon_render_product_fields(array $fields): void
    {
        foreach ($fields as $field) {
            echo falcon_render_product_field($field);
        }
    }
}

if (!function_exists('falcon_render_item_custom_fields')) {
    /**
     * Render custom fields for a cart session item (array) or an OrderItem model.
     * Context labels can be overridden via the falcon_custom_field_labels filter.
     *
     * @param  array|object  $item  Cart array item OR OrderItem model
     * @param  string  $context  'cart' | 'checkout' | 'confirmation' | 'admin'
     * @param  string  $wrapClass  CSS class on the wrapper div
     */
    function falcon_render_item_custom_fields($item, string $context = 'cart', string $wrapClass = 'mt-1.5 space-y-0.5'): string
    {
        // Support both cart session array and OrderItem model
        if (is_array($item)) {
            $customFields = $item['meta']['custom_fields'] ?? [];
        } else {
            $meta = is_array($item->meta) ? $item->meta : (json_decode($item->meta ?? '{}', true) ?? []);
            $customFields = $meta['custom_fields'] ?? [];
        }

        $customFields = apply_falcon_filters('falcon_item_custom_fields_display', $customFields, $item, $context);

        if (empty($customFields)) {
            return '';
        }

        // Allow label overrides via filter
        $labels = apply_falcon_filters('falcon_custom_field_labels', [], $context);

        $html = '<div class="'.e($wrapClass).'">';
        foreach ($customFields as $key => $value) {
            if ((string) $value === '') {
                continue;
            }
            $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
            $html .= '<div class="text-[11px] text-gray-500 leading-snug">'
                   .'<span class="font-semibold text-gray-700">'.e($label).':</span> '
                   .e($value)
                   .'</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('falcon_normalize_custom_fields')) {
    /**
     * Normalize a custom builder element definition (registered via falcon_builder_elements)
     * into a flat, keyed fields array the builder + frontend can consume consistently.
     *
     * Handles both the legacy `fields` map and the Avada-style indexed `params` array,
     * auto-generates param_name from heading, normalizes type aliases, and preserves
     * extended keys (condition, options, unit, step, fields/params for repeaters, etc.).
     *
     * @return array<string,array> keyed by field key
     */
    function falcon_normalize_custom_fields(array $custEl): array
    {
        $typeMap = [
            'textfield' => 'text',
            'colorpickeralpha' => 'color',
            'colorpicker' => 'color',
            'textarea_html' => 'wysiwyg',
        ];

        // suffix → apply_as + base stripping (shared with the array param_name sugar)
        $suffixAs = ['_hover_color' => 'hover_color', '_hover_bg' => 'hover_bg', '_color' => 'color', '_bg' => 'bg', '_typo' => '', '_pad' => 'padding', '_margin' => 'margin'];
        $stripBase = function ($k) use ($suffixAs) {
            foreach ($suffixAs as $suf => $as) {
                if (str_ends_with($k, $suf)) {
                    return [substr($k, 0, -strlen($suf)), $as];
                }
            }

            return [$k, ''];
        };

        $slug = fn ($t) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($t)), '_');
        $contentTypes = ['text', 'textfield', 'textarea', 'wysiwyg', 'image', 'media', 'icon', 'button', 'repeater'];

        // Pre-scan content-field keys so array param_name sugar can avoid colliding with them
        $contentKeys = [];
        foreach (($custEl['params'] ?? []) as $p) {
            if (!in_array($p['type'] ?? 'text', $contentTypes, true)) {
                continue;
            }
            $pn = $p['param_name'] ?? null;
            $ck = is_array($pn) ? ($pn[0] ?? null) : $pn;
            if (!$ck && !empty($p['heading'])) {
                $ck = $slug($p['heading']);
            }
            if ($ck) {
                $contentKeys[] = $ck;
            }
        }

        $autoKey = function ($p) use ($slug) {
            $pn = $p['param_name'] ?? null;
            if (is_array($pn) && !empty($pn)) {
                return $pn[0];
            }
            if (!empty($pn)) {
                return $pn;
            }
            if (!empty($p['heading'])) {
                return $slug($p['heading']);
            }

            return null;
        };

        // Start from legacy fields map (already keyed)
        $fields = $custEl['fields'] ?? [];

        foreach (($custEl['params'] ?? []) as $p) {
            $key = $autoKey($p);
            if (!$key) {
                continue;
            }
            $rawType = $p['type'] ?? 'text';

            // Array param_name → sugar for relating one field to many targets
            $applyTo = $p['apply_to'] ?? null;
            $applyAs = $p['apply_as'] ?? null;
            if (is_array($p['param_name'] ?? null) && !empty($p['param_name'])) {
                $entries = $p['param_name'];
                [$b0, $as0] = $stripBase($entries[0]);
                if ($b0 !== $entries[0]) {
                    // Suffixed entries (e.g. title_color) → first is the storage key, strip suffix for targets
                    if ($applyAs === null) {
                        $applyAs = $as0;
                    }
                    if ($applyTo === null) {
                        $applyTo = array_map(fn ($k) => $stripBase($k)[0], $entries);
                    }
                } else {
                    // Bare target names (e.g. ['title','subtitle']) → synthesise a non-colliding storage key
                    $base = !empty($p['heading']) ? $slug($p['heading']) : ('cf_'.substr(md5(implode(',', $entries)), 0, 6));
                    while (in_array($base, $contentKeys, true)) {
                        $base .= '_x';
                    }
                    $key = $base;
                    if ($applyTo === null) {
                        $applyTo = $entries;
                    }
                    if ($applyAs === null) {
                        $nt = $typeMap[$rawType] ?? $rawType;
                        $applyAs = $nt === 'dimensions' ? 'padding' : ($nt === 'color' ? 'color' : '');
                    }
                }
            }

            $fields[$key] = [
                'type' => $typeMap[$rawType] ?? $rawType,
                'raw_type' => $rawType,
                'label' => $p['heading'] ?? $key,
                'default' => $p['value'] ?? '',
                'tab' => $p['tab'] ?? 'general',
                'placeholder' => $p['placeholder'] ?? '',
                'description' => $p['description'] ?? '',
                'options' => $p['options'] ?? [],
                'rows' => $p['rows'] ?? null,
                'min' => $p['min'] ?? null,
                'max' => $p['max'] ?? null,
                'step' => $p['step'] ?? null,
                'unit' => $p['unit'] ?? '',
                'condition' => $p['condition'] ?? null,
                'dynamic' => $p['dynamic'] ?? false,
                'apply_to' => $applyTo,
                'apply_as' => $applyAs,
                // repeater sub-fields (either key works)
                'fields' => $p['fields'] ?? [],
                'params' => $p['params'] ?? [],
            ];
        }

        return $fields;
    }
}
