<?php

namespace FalconCms\Core\Tests\Doubles;

use FalconCms\Core\Pro\LicenseGateway;

/**
 * A licence gateway that says yes.
 *
 * The storefront routes are gated behind EnsurePro, which asks this contract. Whether
 * that gate is correct is the Pro package's business and is tested there; a checkout
 * test in the free core needs the gate open so it can reach the thing it is actually
 * about.
 */
class LicensedGateway implements LicenseGateway
{
    public function licensed(): bool
    {
        return true;
    }

    public function active(?string $feature = null): bool
    {
        return true;
    }

    public function plan(): ?string
    {
        return 'pro';
    }

    public function features(): array
    {
        return ['ecommerce', 'multilang', 'analytics', 'builder_pro', 'custom_fields', 'advanced_login'];
    }

    public function deactivate(): bool
    {
        return true;
    }
}
