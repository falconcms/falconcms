<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Pro\LicenseGateway;
use FalconCms\Core\Tests\Doubles\LicensedGateway;
use FalconCms\Core\Tests\TestCase;

/**
 * Which features are free, which are paid, and what "paid" actually withholds.
 *
 * The licensing model has more corners than it looks: a feature can be free core, licensed,
 * inside the grace window, grandfathered on this install, or licensed-but-out-of-updates —
 * and they do not all mean the same thing. Getting one wrong either gives away a paid feature
 * or locks a customer out of one they own, and neither shows up as an error.
 *
 * These pin the behaviour as it ships today. Nothing here changes it.
 */
class ProLicensingTest extends TestCase
{
    /** The default in a test is an unlicensed site, the same as a fresh free install. */
    private function assertUnlicensedByDefault(): void
    {
        $this->assertFalse(falcon_licensed(), 'a test starts from an unlicensed site');
    }

    // ── free core ────────────────────────────────────────────────────────────

    public function test_ecommerce_is_free_core(): void
    {
        $this->assertUnlicensedByDefault();

        $this->assertContains('ecommerce', falcon_free_features());
        $this->assertTrue(falcon_pro('ecommerce'), 'the shop is free and must stay reachable');
        $this->assertTrue(falcon_pro_editable('ecommerce'), 'the shop is free and must stay editable');
    }

    public function test_a_paid_feature_is_closed_on_an_unlicensed_site(): void
    {
        $this->assertUnlicensedByDefault();

        foreach (['multilang', 'analytics', 'builder_pro', 'custom_fields'] as $feature) {
            $this->assertFalse(falcon_pro($feature), $feature.' must be gated without a licence');
            $this->assertFalse(falcon_pro_editable($feature), $feature.' must not be editable without a licence');
        }
    }

    public function test_the_grace_window_has_closed(): void
    {
        // A fixed date, the same for every site. It is in the past, so nothing is unlocked by
        // it any more — if this ever reads true again, every paid feature has been given away.
        $this->assertFalse(falcon_freemium_grace_active());
    }

    // ── licensed ─────────────────────────────────────────────────────────────

    public function test_a_licence_opens_the_paid_features(): void
    {
        $this->withProLicensed();

        $this->assertTrue(falcon_licensed());
        foreach (['multilang', 'analytics', 'builder_pro', 'custom_fields'] as $feature) {
            $this->assertTrue(falcon_pro($feature), $feature.' should be open to a licensed site');
            $this->assertTrue(falcon_pro_editable($feature));
        }
    }

    public function test_a_licence_does_not_take_the_free_features_away(): void
    {
        $this->withProLicensed();

        $this->assertTrue(falcon_pro('ecommerce'));
        $this->assertTrue(falcon_pro_editable('ecommerce'));
    }

    // ── grandfathering ───────────────────────────────────────────────────────

    public function test_a_grandfathered_feature_stays_usable_but_not_editable(): void
    {
        $this->setCmsOptions(['falcon_grandfathered_features' => json_encode(['multilang'])]);

        // The whole point of the split: a site that was already using a feature keeps seeing
        // it, and has to license to change it.
        $this->assertTrue(falcon_feature_grandfathered('multilang'));
        $this->assertTrue(falcon_pro('multilang'), 'a grandfathered feature stays viewable');
        $this->assertFalse(falcon_pro_editable('multilang'), 'a grandfathered feature is read-only');

        $this->assertFalse(falcon_feature_grandfathered('analytics'));
        $this->assertFalse(falcon_pro('analytics'));
    }

    public function test_nothing_is_grandfathered_on_a_fresh_install(): void
    {
        $this->assertFalse(falcon_feature_grandfathered());
        $this->assertFalse(falcon_feature_grandfathered('multilang'));
    }

    // ── updates are a separate question from features ────────────────────────

    public function test_features_are_perpetual_even_when_updates_have_lapsed(): void
    {
        // A gateway that is licensed but past its update window: the customer owns the
        // features forever and only new releases are withheld.
        $this->app->instance(LicenseGateway::class, new class extends LicensedGateway
        {
            public function updatesAllowed(): bool
            {
                return false;
            }

            public function expired(): bool
            {
                return true;
            }
        });

        $this->assertTrue(falcon_pro('multilang'), 'an expired licence must not lock a feature');
        $this->assertTrue(falcon_pro_editable('multilang'));
        $this->assertFalse(falcon_pro_updates_allowed(), 'updates stop');
        $this->assertTrue(falcon_pro_expired(), 'and the site is told to renew');
    }

    public function test_an_unlicensed_site_is_not_reported_as_expired(): void
    {
        $this->assertFalse(falcon_pro_expired());
        $this->assertFalse(falcon_pro_updates_allowed());
    }

    // ── a broken gateway must not take the site down ─────────────────────────

    public function test_a_gateway_that_throws_is_treated_as_unlicensed(): void
    {
        $this->app->instance(LicenseGateway::class, new class implements LicenseGateway
        {
            public function licensed(): bool
            {
                throw new \RuntimeException('the licence server is down');
            }

            public function active(?string $feature = null): bool
            {
                throw new \RuntimeException('the licence server is down');
            }

            public function plan(): ?string
            {
                return null;
            }

            public function features(): array
            {
                return [];
            }

            public function deactivate(): bool
            {
                return true;
            }
        });

        // Closed, not crashed — and the free features are still free.
        $this->assertFalse(falcon_licensed());
        $this->assertFalse(falcon_pro('multilang'));
        $this->assertTrue(falcon_pro('ecommerce'));
    }

    public function test_the_upgrade_link_points_somewhere(): void
    {
        $this->assertStringStartsWith('http', falcon_upgrade_url());
    }
}
