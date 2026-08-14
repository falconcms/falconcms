<?php

use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Models\FieldGroup;
use FalconCms\Core\Models\Language;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Models\Order;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostTranslation;
use Illuminate\Database\Migrations\Migration;

/**
 * Freemium transition for sites upgrading from a pre-freemium version.
 *
 * At migration time a FRESH install has no content yet (seeders run afterwards), so the
 * presence of existing posts means this is an UPGRADE. For upgrades we:
 *   - grandfather every Pro feature the site was already using (kept free forever), and
 *   - open a 30-day grace window during which nothing locks at all.
 * Fresh installs get neither, so they see clean freemium from day one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!function_exists('get_cms_option') || !function_exists('update_cms_option')) {
            return;
        }
        if (get_cms_option('falcon_freemium_initialized', null)) {
            return; // run once
        }
        update_cms_option('falcon_freemium_initialized', now()->toIso8601String());

        $has = function (callable $cb): bool {
            try {
                return (bool) $cb();
            } catch (Throwable $e) {
                return false;
            }
        };

        // Existing content at migrate time ⇒ this is an upgrade, not a fresh install.
        if (!$has(fn () => Post::query()->exists())) {
            return;
        }

        // Which Pro features was this site already using?
        $used = [];
        if ($has(fn () => Order::query()->exists())
            || $has(fn () => Post::where('type', 'product')->exists())) {
            $used[] = 'ecommerce';
        }
        if ($has(fn () => Language::where('status', true)->count() > 1)
            || $has(fn () => PostTranslation::query()->exists())) {
            $used[] = 'multilang';
        }
        if ($has(fn () => FieldGroup::query()->exists())) {
            $used[] = 'custom_fields';
        }
        if ($has(fn () => Analytics::query()->exists())) {
            $used[] = 'analytics';
        }
        $rawLayouts = get_cms_option('falcon_layouts', null);
        $layouts = is_array($rawLayouts) ? $rawLayouts : (is_string($rawLayouts) ? json_decode($rawLayouts, true) : []);
        if (!empty($layouts)
            || $has(fn () => NavigationMenuItem::whereNotNull('mega_menu_id')->exists())
            || $has(fn () => Post::where('type', 'falcon_content')->exists())) {
            $used[] = 'builder_pro';
        }

        if (!empty($used)) {
            update_cms_option('falcon_grandfathered_features', json_encode(array_values(array_unique($used))));
        }

        // The grace window is now a single global date (config falcon-options.freemium_grace_until),
        // not a per-install option — every site becomes free until that fixed launch date.
    }

    public function down(): void
    {
        // Options only — safe to leave in place on rollback.
    }
};
