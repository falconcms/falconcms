<?php

/**
 * Helper API for extending the native CMS Settings screens.
 *
 * Mirrors the WordPress Settings API. A theme/plugin uses these to inject
 * fields — and optional tabs — into the existing General or SEO settings
 * pages. Everything renders inside the native form and saves automatically
 * through the settings controller; read values back with get_cms_option().
 *
 * Usage (theme functions.php, or on the `falcon_register_settings` action):
 *
 *     // A field on the General settings page (inline, no tab):
 *     falcon_add_settings_field([
 *         'id'          => 'google_maps_key',
 *         'label'       => 'Google Maps API Key',
 *         'type'        => 'text',
 *         'description' => 'Used for map embeds.',
 *     ]);
 *
 *     // A custom tab with its own fields:
 *     falcon_add_settings_tab(['id' => 'integrations', 'label' => 'Integrations', 'icon' => 'hub']);
 *     falcon_add_settings_field([
 *         'id'    => 'mailchimp_key',
 *         'label' => 'Mailchimp API Key',
 *         'type'  => 'text',
 *         'tab'   => 'integrations',
 *     ]);
 *
 *     // Target the SEO page instead of General:
 *     falcon_add_settings_field(['id' => 'gsc_token', 'label' => 'Search Console', 'screen' => 'seo']);
 */

use FalconCms\Core\Support\SettingsExtension;

if (! function_exists('falcon_add_settings_field')) {
    /**
     * Register a field on a settings screen.
     *
     * @param array $args id/name, label, type, description, options, default,
     *                    placeholder, tab, screen ('general'|'seo'), order, …
     */
    function falcon_add_settings_field(array $args): void
    {
        app(SettingsExtension::class)->addField($args);
    }
}

if (! function_exists('falcon_add_settings_tab')) {
    /**
     * Register a tab on a settings screen.
     *
     * @param array $args id, label, icon, screen ('general'|'seo'), order
     */
    function falcon_add_settings_tab(array $args): void
    {
        app(SettingsExtension::class)->addTab($args);
    }
}
