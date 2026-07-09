<?php

namespace FalconCms\Core\Pro;

/**
 * Default gateway, used whenever the Pro package is absent or its license is
 * invalid. Every paid feature reads as inactive, so core consistently renders
 * its "upgrade to Pro" prompts instead of the gated feature.
 */
class NullLicenseGateway implements LicenseGateway
{
    public function licensed(): bool
    {
        return false;
    }

    public function active(?string $feature = null): bool
    {
        return false;
    }

    public function plan(): ?string
    {
        return null;
    }

    public function features(): array
    {
        return [];
    }
}
