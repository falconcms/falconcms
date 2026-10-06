<?php

/**
 * Pro licensing and freemium gates.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Pro\LicenseGateway;
use Illuminate\Support\Carbon;

if (!function_exists('falcon_licensed')) {
    /**
     * Whether a valid paid Pro license is active on this site — regardless of the
     * freemium grace window or grandfathering. Used to hide the "now freemium /
     * upgrade" banners once the customer has actually licensed the site.
     */
    function falcon_licensed(): bool
    {
        try {
            return app(LicenseGateway::class)->licensed();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro_updates_allowed')) {
    /**
     * Whether Pro UPDATES may be fetched right now. Pro FEATURES are perpetual — a one-time
     * purchase is owned forever (see falcon_pro()) — but new releases are limited to the
     * licence's update window; once it lapses the site keeps every feature and must renew to
     * pull newer versions. Falls back to licensed() for older Pro gateways that predate the
     * update-window methods, and is never relevant to the free core (its updates are ungated).
     */
    function falcon_pro_updates_allowed(): bool
    {
        try {
            $gw = app(LicenseGateway::class);
            if (method_exists($gw, 'updatesAllowed')) {
                return (bool) $gw->updatesAllowed();
            }

            return $gw->licensed();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro_expired')) {
    /**
     * Whether the site has a licensed-but-expired Pro plan — features still work, only the
     * update window has ended. Used to show a "renew for updates" notice (not a lockout).
     */
    function falcon_pro_expired(): bool
    {
        try {
            $gw = app(LicenseGateway::class);

            return $gw->licensed() && method_exists($gw, 'expired') && (bool) $gw->expired();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro')) {
    /**
     * Whether a FalconCMS Pro feature is available. Core uses this to gate paid
     * features and decide when to show an "upgrade to Pro" prompt; the Pro package
     * flips these on once its license validates.
     *
     *   falcon_pro()             → is any Pro license active?
     *   falcon_pro('ecommerce')  → is the e-commerce feature available?
     *
     * Known feature keys: 'ecommerce', 'multilang', 'analytics', 'builder_pro'.
     */
    function falcon_pro(?string $feature = null): bool
    {
        // Features that have graduated into the free core — always available, no license needed.
        if ($feature !== null && in_array($feature, falcon_free_features(), true)) {
            return true;
        }

        try {
            if (app(LicenseGateway::class)->active($feature)) {
                return true;
            }
        } catch (Throwable $e) {
        }

        // Grace window after an upgrade — nothing locks yet.
        if (falcon_freemium_grace_active()) {
            return true;
        }

        // Grandfathered — features already in use before freemium stay free on this install.
        return falcon_feature_grandfathered($feature);
    }
}

if (!function_exists('falcon_upgrade_url')) {
    /** Where every "Upgrade to Pro" call-to-action points (config falcon-options.upgrade_url). */
    function falcon_upgrade_url(): string
    {
        return (string) config('falcon-options.upgrade_url', 'https://falconcms.com/#pricing');
    }
}

if (!function_exists('falcon_freemium_grace_active')) {
    /**
     * Whether the site is still inside its freemium grace window — the transition period
     * (set on upgrade) during which every Pro feature stays unlocked before gating begins.
     */
    function falcon_freemium_grace_active(): bool
    {
        try {
            // Global fixed launch cutoff (same date for every site) — free until
            // this date, Pro features lock after it unless licensed.
            $until = config('falcon-options.freemium_grace_until', '2026-08-01');
            if (!$until) {
                return false;
            }

            return Carbon::now()->lt(Carbon::parse($until));
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_feature_grandfathered')) {
    /**
     * Whether a feature was grandfathered — already in use when the site upgraded into
     * freemium, so it stays free forever on this install. Pass null to ask "is anything
     * grandfathered?".
     */
    function falcon_feature_grandfathered(?string $feature = null): bool
    {
        try {
            $raw = get_cms_option('falcon_grandfathered_features', null);
            $list = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : []);
            $list = is_array($list) ? $list : [];
            if ($feature === null) {
                return !empty($list);
            }

            return in_array($feature, $list, true);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('falcon_pro_editable')) {
    /**
     * Whether a Pro feature is FULLY unlocked for creating/editing — i.e. covered by an active
     * license or the grace window, but NOT merely grandfathered. Grandfathering keeps already-
     * created content working (it still renders), but editing/moving it — or adding more — needs
     * real Pro. The builder uses this to lock Pro elements read-only once the grace window ends.
     */
    function falcon_pro_editable(?string $feature = null): bool
    {
        // Free-core features are fully editable for everyone.
        if ($feature !== null && in_array($feature, falcon_free_features(), true)) {
            return true;
        }

        try {
            if (app(LicenseGateway::class)->active($feature)) {
                return true;
            }
        } catch (Throwable $e) {
        }

        return falcon_freemium_grace_active();
    }
}

if (!function_exists('falcon_free_features')) {
    /**
     * Feature keys that were once Pro but are now part of the free core — available on
     * every site without a licence. E-commerce graduated to free in v2.2. Override via
     * config('falcon-options.free_features') if needed.
     */
    function falcon_free_features(): array
    {
        return (array) config('falcon-options.free_features', ['ecommerce']);
    }
}
