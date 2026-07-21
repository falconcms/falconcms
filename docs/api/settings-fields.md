# Settings Fields API

Add your own fields to the CMS's **existing** settings screens, or create whole
new settings tabs — from a theme's `functions.php` or a plugin's `plugin.php`.
No controllers, routes or views required: fields render as native settings rows
and save through the screen they live on.

## Functions

```php
falcon_add_settings_field(array $args): void   // a field on a settings screen
falcon_add_settings_tab(array $args): void     // a new top-level Settings tab
```

Register them directly, or defer to the `falcon_register_settings` action:

```php
add_falcon_action('falcon_register_settings', function () {
    falcon_add_settings_field([...]);
});
```

Read values back with `get_cms_option()`:

```php
$key = get_cms_option('my_api_key');
```

---

## Adding a field to an existing screen

Target a screen with `screen`. The field renders beneath that screen's own
fields and saves with its **Save Changes** button.

```php
falcon_add_settings_field([
    'id'          => 'google_maps_key',
    'label'       => 'Google Maps API Key',
    'type'        => 'text',
    'description' => 'Used for map embeds.',
    'screen'      => 'general',   // default
]);
```

### Supported screens

| `screen` | Admin page |
| --- | --- |
| `general` *(default)* | Settings → General Settings |
| `seo` | Settings → SEO Settings |
| `api` | Settings → REST API |
| `integrations` | Settings → Integrations |
| `shop` | Shop → Settings |

::: info Not injectable
**Activity Logs** is a log viewer and **Email Templates** is a structured
template editor — neither is a generic settings form. Use a
[custom settings tab](#custom-settings-tabs) or an
[options page](/api/admin-menu#options-pages) instead.
:::

### The Shop screen

Shop settings are stored under a `shop_` prefix, and the screen has its own tabs.
Use `tab` to place your field in one of them:

```php
falcon_add_settings_field([
    'id'     => 'stripe_key',
    'label'  => 'Stripe Public Key',
    'type'   => 'text',
    'screen' => 'shop',
    'tab'    => 'payments',
]);
```

Valid shop tabs: `general` *(default)*, `products`, `payments`, `shipping`,
`tax`, `coupons`, `emails_accounts`.

Read shop values with the prefix:

```php
$key = get_shop_option('shop_stripe_key');
```

---

## Custom settings tabs

`falcon_add_settings_tab()` adds a **new top-level tab** to the Settings nav bar,
alongside General / SEO / REST API. Each tab is its own page at
`/admin/settings/{id}`, with its own fields and Save button, styled exactly like
the native screens.

```php
falcon_add_settings_tab([
    'id'    => 'licensing',
    'label' => 'Licensing',
    'icon'  => 'key',
]);

falcon_add_settings_field([
    'id'    => 'license_provider',
    'label' => 'Provider',
    'type'  => 'text',
    'tab'   => 'licensing',   // lives on that tab's page
]);
```

| Key | Default | Description |
| --- | --- | --- |
| `id` | — | Unique slug. Becomes the URL. Restricted to `A–Z a–z 0–9 _ -`. |
| `label` | from id | Tab label. |
| `icon` | — | Material Symbols icon name. |
| `order` | `100` | Position among custom tabs. |
| `permission` | `manage_settings` | Capability required. |

::: warning Reserved slugs
Don't reuse a native slug (`seo`, `api`, `integrations`, `email-templates`,
`activity-logs`) — the native page wins. Pick something distinct.
:::

---

## Field types

Every field accepts these common keys:

| Key | Description |
| --- | --- |
| `id` *(or `name`)* | Option key it saves to. Required. |
| `label` | Field label. Defaults to a humanised id. |
| `type` | One of the types below. Defaults to `text`. |
| `description` *(or `help`)* | Help text under the field. |
| `default` | Value used before anything is saved. |
| `placeholder` | Input placeholder. |
| `screen` / `tab` / `order` | Placement, as described above. |

### Text-like

`text` · `number` · `email` · `password` · `url` · `textarea`

```php
['id' => 'tagline', 'label' => 'Tagline', 'type' => 'textarea'];
```

### Choices

**`select`**, **`radio`** — need `options` as `value => label`:

```php
[
    'id'      => 'layout',
    'label'   => 'Layout',
    'type'    => 'select',
    'options' => ['boxed' => 'Boxed', 'wide' => 'Wide'],
]
```

**`checkbox`** — saves `'1'` / `'0'`; `checkbox_label` sets the inline text:

```php
['id' => 'enable_cache', 'label' => 'Cache', 'type' => 'checkbox', 'checkbox_label' => 'Enable']
```

**`multiselect`** — a searchable, chip-based picker (Select2-style). Saves a JSON
array:

```php
[
    'id'      => 'active_services',
    'label'   => 'Services',
    'type'    => 'multiselect',
    'options' => ['seo' => 'SEO', 'ads' => 'Ads'],
]
```

```php
$services = json_decode(get_cms_option('active_services'), true);
```

**`tags`** — free-form entry; type and press Enter. Optional `suggestions`.
Saves a JSON array.

### Pickers

**`color`** — a colour swatch. **`date`** — a date picker.

**`image`** / **`file`** — open the Media Library and store the chosen URL.

```php
['id' => 'og_image', 'label' => 'Share image', 'type' => 'image']
```

**`range`** — a slider with a live value. Accepts `min`, `max`, `step`.

```php
['id' => 'quality', 'label' => 'Quality', 'type' => 'range', 'min' => 10, 'max' => 100, 'step' => 5]
```

### Rich content

**`wysiwyg`** — a TinyMCE editor.

**`repeater`** — a repeatable group of sub-fields, saved as a JSON array of rows.
Sub-fields support `text`, `textarea`, `checkbox`, `select`, `color`, `number`,
`email`, `url`, `date`.

```php
[
    'id'           => 'team_members',
    'label'        => 'Team',
    'type'         => 'repeater',
    'button_label' => 'member',
    'fields'       => [
        ['name' => 'name',  'label' => 'Name',  'type' => 'text'],
        ['name' => 'role',  'label' => 'Role',  'type' => 'text'],
    ],
]
```

```php
$team = json_decode(get_cms_option('team_members'), true) ?: [];
foreach ($team as $member) {
    echo $member['name'];
}
```

---

## Raw HTML injection

For full control, the settings forms also expose plain action hooks. Echo any
markup; posted fields on `general` and `seo` are saved automatically.

```php
add_falcon_action('falcon_settings_form_bottom', function () {
    $value = get_cms_option('my_key', '');
    echo '<div class="mb-6">
        <label>My Field</label>
        <input type="text" name="my_key" value="' . e($value) . '">
    </div>';
});
```

Available: `falcon_settings_form_top` / `_bottom`,
`falcon_seo_settings_form_top` / `_bottom`, `falcon_api_settings_form_bottom`,
`falcon_integrations_settings_form_bottom`, `falcon_shop_settings_form_bottom`.

::: tip
Prefer `falcon_add_settings_field()` — it handles rendering, escaping, native
styling, array serialisation and per-screen saving for you.
:::

---

## Protected options

For security, a few internal keys can **never** be written through settings
saves, injected fields or options pages — they are managed by the CMS itself:

- `falcon_license_*` (license key and cached license state)
- `falcon_grandfathered_features`

Attempts to write them are ignored silently. Everything else is yours.
