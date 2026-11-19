<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Role;

/**
 * Which role an account made by the public registration form gets, and whether that is safe.
 *
 * Settings → "New User Default Role" names the role, but the option is only a name: the role
 * it points at can be an administrator role, can gain permissions after it was chosen, or can
 * have been written by a path that never checked it (an import, a database edit, an older
 * version). So the role is judged every time someone registers, by its slug and by what it
 * actually grants at that moment, never by what it granted when it was picked.
 *
 * A role is unsafe for strangers when it is an administrator role (Role::ADMIN_SLUGS: those
 * hold every permission without granting any) or when it grants one of the permissions below.
 * An unsafe role falls back to Subscriber, only after the same test passes for Subscriber;
 * when neither is safe nobody is registered at all (fail closed).
 */
final class PublicRegistration
{
    /**
     * Permissions no stranger may receive by signing up, and why.
     *
     * Exact slugs. A section's sub-options are also caught by {@see PRIVILEGED_SUFFIXES}, which
     * follow the CMS's naming (Sidebar::getPermission / AdminMenu::derivedPermission: a child
     * menu's permission is access_<child>_<parent>), so a sub-option added later is covered
     * without being listed here.
     */
    public const PRIVILEGED = [
        // Users: creating, editing, blocking and deleting accounts, which reaches every
        // account a non-admin manager may touch (passwords and emails included).
        'manage_users' => 'user management',
        'access_users' => 'user management',
        'access_all_users' => 'user management',
        'access_add_new_users' => 'user management',
        'access_blacklist' => 'user management (blacklist)',
        // Roles: whoever edits roles decides what every role, their own included, may do.
        'manage_roles' => 'role management',
        'access_roles' => 'role management',
        // Settings: the default role itself, the login and registration addresses, the REST
        // API, integrations' secrets, email templates; and the Customizer and its import,
        // which put HTML and scripts into every page.
        'manage_settings' => 'site settings',
        'access_settings' => 'site settings',
        // Plugins: installing or switching on a plugin runs its PHP code on the server.
        'manage_plugins' => 'plugin installation (runs code)',
        'access_plugins' => 'plugin installation (runs code)',
        // Themes: an uploaded theme is PHP and Blade code; the Customizer injects scripts.
        'access_themes' => 'theme installation (runs code)',
        'access_themes_appearance' => 'theme installation (runs code)',
        'access_customizer' => 'Customizer (head HTML and scripts on every page)',
        'access_customizer_appearance' => 'Customizer (head HTML and scripts on every page)',
        'access_customizer_falcon_builder' => 'Customizer (head HTML and scripts on every page)',
        // Updates: installs new code, from Composer and the Pro channel.
        'access_updates' => 'updates (installs code)',
        'access_updates_dashboard' => 'updates (installs code)',
        // The licence: who may activate, change or remove the site's Pro key.
        'access_license' => 'licence management',
        'access_license_falcon_builder' => 'licence management',
        // Backups, import and export: a restore replaces the database, users and roles
        // included; an export hands over the whole site's content.
        'access_backup_restore' => 'backup and restore (replaces the database)',
        'access_backups' => 'backup and restore (replaces the database)',
        'access_import' => 'import (writes content and users)',
        'access_export' => 'export (reads the whole site)',
        'access_wordpress_import' => 'import (writes content and users)',
        'access_tools' => 'tools (backup, import, export)',
        // Falcon Security (Pro): the firewall, sign-in rules, two-factor resets, temporary
        // accounts and the lockdown switch.
        'manage_security' => 'security administration',
        // The shop's settings hold the payment gateways' credentials and where money goes.
        'access_settings_shop' => 'shop settings (payment credentials)',
        'manage_shop_settings' => 'shop settings (payment credentials)',
    ];

    /**
     * Every sub-option of these sections is privileged, for the reasons given above for the
     * section itself: Users (_users), Settings and the settings screens plugins add (_settings),
     * Plugins (_plugins), Tools (_tools), Falcon Security (_security).
     */
    public const PRIVILEGED_SUFFIXES = [
        '_users' => 'user management',
        '_settings' => 'site settings',
        '_plugins' => 'plugin installation (runs code)',
        '_tools' => 'tools (backup, import, export)',
        '_security' => 'security administration',
    ];

    /** Custom options pages (manage_options_<page>) write site settings. */
    public const PRIVILEGED_PREFIXES = [
        'manage_options_' => 'site settings (options pages)',
    ];

    /**
     * Permissions that match a pattern above but only concern the account's own profile.
     * Users → Your Profile lives in the Users section, and every account may edit itself.
     */
    public const NOT_PRIVILEGED = ['access_your_profile', 'access_your_profile_users'];

    /** The role used when the configured one is unsafe or missing — if it passes the same test. */
    public const FALLBACK = 'subscriber';

    /**
     * The privileged permissions among $slugs, each with the reason it is privileged.
     *
     * Plugins may add slugs through the `falcon_privileged_permissions` filter (slug => reason).
     * They can only add: the list above always applies.
     *
     * @param  array<int, string>  $slugs
     * @return array<string, string>
     */
    public static function privileged(array $slugs): array
    {
        $extra = [];
        try {
            $added = apply_falcon_filters('falcon_privileged_permissions', []);
            foreach (is_array($added) ? $added : [] as $slug => $reason) {
                if (is_string($slug) && $slug !== '') {
                    $extra[$slug] = is_string($reason) && $reason !== '' ? $reason : 'marked privileged by a plugin';
                }
            }
        } catch (\Throwable $e) {
            // A failing plugin cannot make the list shorter, only fail to make it longer.
        }
        $exact = self::PRIVILEGED + $extra;

        $found = [];
        foreach ($slugs as $slug) {
            $slug = (string) $slug;
            if (in_array($slug, self::NOT_PRIVILEGED, true)) {
                continue;
            }
            if (isset($exact[$slug])) {
                $found[$slug] = $exact[$slug];

                continue;
            }
            foreach (self::PRIVILEGED_SUFFIXES as $suffix => $reason) {
                if (str_ends_with($slug, $suffix)) {
                    $found[$slug] = $reason;

                    continue 2;
                }
            }
            foreach (self::PRIVILEGED_PREFIXES as $prefix => $reason) {
                if (str_starts_with($slug, $prefix)) {
                    $found[$slug] = $reason;

                    continue 2;
                }
            }
        }
        ksort($found);

        return $found;
    }

    /** Why $role may not be given to a stranger, or null when it may. */
    public static function roleError(Role $role): ?string
    {
        $slug = (string) $role->getAttribute('slug');
        if (Role::isAdminSlug($slug)) {
            return "\"{$slug}\" is an administrator role.";
        }

        $privileged = self::privileged(Role::permissionSlugsFor([(int) $role->getKey()]));
        if ($privileged !== []) {
            return "\"{$slug}\" grants privileged permissions: ".implode(', ', array_slice(array_keys($privileged), 0, 10))
                .(count($privileged) > 10 ? ', …' : '').'.';
        }

        return null;
    }

    /**
     * The role a new public account gets, decided now.
     *
     * - role: the role to give, or null when there is no safe one (register nobody);
     * - configured: the slug Settings names;
     * - problem: why the configured role was not used, or null when it was;
     * - fallback: whether role is the fallback rather than the configured one.
     *
     * @return array{role: ?Role, configured: string, problem: ?string, fallback: bool}
     */
    public static function resolve(): array
    {
        $configured = trim((string) get_cms_option('default_role', self::FALLBACK));
        if ($configured === '') {
            $configured = self::FALLBACK;
        }

        $role = Role::where('slug', $configured)->first();
        $problem = $role ? self::roleError($role) : "The role \"{$configured}\" does not exist.";
        if ($role && $problem === null) {
            return ['role' => $role, 'configured' => $configured, 'problem' => null, 'fallback' => false];
        }

        $fallback = (string) ($role?->getAttribute('slug') ?? '') === self::FALLBACK ? null : Role::where('slug', self::FALLBACK)->first();
        if ($fallback && self::roleError($fallback) === null) {
            return ['role' => $fallback, 'configured' => $configured, 'problem' => $problem, 'fallback' => true];
        }

        return ['role' => null, 'configured' => $configured, 'problem' => $problem, 'fallback' => false];
    }
}
