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

    // NOTE: a live gateway may also implement `deactivate(): bool` to release its
    // provider activation slot. It is intentionally NOT part of this interface —
    // adding a required method here would fatal any already-installed Pro package
    // that predates it. Core calls it defensively via method_exists() instead.
}
