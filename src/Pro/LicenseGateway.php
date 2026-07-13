<?php

namespace FalconCms\Core\Pro;

/**
 * Contract for the FalconCMS Pro license gateway.
 *
 * Core ships a {@see NullLicenseGateway} where every paid feature is inactive.
 * When the `falconcms/pro` package is installed and its license validates, its
 * service provider rebinds this contract to a live gateway that turns the paid
 * features on.
 *
 * Core code must never check for the Pro package (or a license) directly — it
 * asks this gateway, normally through the `falcon_pro()` helper, whether a
 * feature is available, and renders an upgrade prompt when it is not.
 */
interface LicenseGateway
{
    /** Whether a valid Pro license is active at all. */
    public function licensed(): bool;

    /**
     * Whether a given Pro feature is available right now. Pass null to ask
     * "is any Pro feature active?". Known feature keys:
     *   'ecommerce', 'multilang', 'analytics', 'builder_pro'.
     */
    public function active(?string $feature = null): bool;

    /** The active plan slug (e.g. 'pro', 'agency'), or null when unlicensed. */
    public function plan(): ?string;

    /** All active feature keys (empty when unlicensed). */
    public function features(): array;

    /**
     * Release this site's activation with the provider (e.g. free the Lemon
     * Squeezy instance slot) so the key can be re-used on another site, then
     * forget any cached state. Returns false only on a provider error.
     */
    public function deactivate(): bool;
}
