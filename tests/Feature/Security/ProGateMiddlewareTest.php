<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Http\Middleware\EnsurePro;
use FalconCms\Core\Http\Middleware\EnsureProEditable;
use FalconCms\Core\Tests\TestCase;

/**
 * The two gates that actually withhold a paid feature.
 *
 * Everything else about licensing is a helper returning a boolean; these are what a request
 * meets. They have two jobs that pull against each other: never let an unlicensed site write
 * to a paid feature, and never make a gated feature look like a broken one — which is why a
 * refusal is a readable page or a JSON payload the existing scripts already understand, not
 * a 403 and not an exception.
 *
 * The gates are exercised on routes defined here rather than on the real ones, so the test
 * cannot be quietly invalidated by the shipped routes moving, and so both a free feature and
 * a paid one can be put through the same gate side by side.
 */
class ProGateMiddlewareTest extends TestCase
{
    /** Testbench registers the application's routes through this, before the CMS's catch-all. */
    protected function defineRoutes($router): void
    {
        $router->middleware(['web', EnsurePro::class.':multilang'])
            ->get('/gate/read-paid', fn () => 'through');

        $router->middleware(['web', EnsurePro::class.':ecommerce,strict'])
            ->get('/gate/read-free-strict', fn () => 'through');

        $router->middleware(['web', EnsurePro::class.':multilang,strict'])
            ->get('/gate/read-paid-strict', fn () => 'through');

        $router->middleware(['web', EnsureProEditable::class.':multilang'])
            ->get('/gate/write-paid', fn () => 'through');

        $router->middleware(['web', EnsureProEditable::class.':multilang'])
            ->post('/gate/write-paid', fn () => 'through');

        $router->middleware(['web', EnsureProEditable::class.':ecommerce'])
            ->post('/gate/write-free', fn () => 'through');
    }

    // ── reading ──────────────────────────────────────────────────────────────

    public function test_a_free_feature_passes_the_strict_gate_without_a_licence(): void
    {
        $this->get('/gate/read-free-strict')->assertOk()->assertSee('through');
    }

    public function test_a_paid_feature_is_refused_readably_without_a_licence(): void
    {
        $response = $this->get('/gate/read-paid');

        // 200 and a page, deliberately: a 403 reads as "you are not allowed here", and this
        // is "this costs money". The wording is what tells the visitor which.
        $response->assertOk()->assertSee('Pro version', false);
        $response->assertDontSee('through');
    }

    public function test_a_licence_opens_the_gate(): void
    {
        $this->withProLicensed();

        $this->get('/gate/read-paid')->assertOk()->assertSee('through');
        $this->get('/gate/read-paid-strict')->assertOk()->assertSee('through');
    }

    public function test_an_ajax_refusal_is_json_the_existing_scripts_understand(): void
    {
        $response = $this->getJson('/gate/read-paid');

        // The storefront and admin scripts read success:false and show the message inline; a
        // 4xx would fall into their generic "something went wrong" branch instead.
        $response->assertOk()
            ->assertJson(['success' => false, 'pro' => true])
            ->assertJsonStructure(['success', 'pro', 'message']);
    }

    // ── writing ──────────────────────────────────────────────────────────────

    public function test_reading_a_paid_screen_is_allowed_where_only_writing_is_gated(): void
    {
        // The "browse but locked" model: the screen opens, the save does not.
        $this->get('/gate/write-paid')->assertOk()->assertSee('through');
    }

    public function test_writing_to_a_paid_feature_is_refused_without_a_licence(): void
    {
        $response = $this->post('/gate/write-paid');

        $response->assertOk()->assertSee('Pro version', false);
        $response->assertDontSee('through');
    }

    public function test_writing_to_a_paid_feature_is_allowed_with_a_licence(): void
    {
        $this->withProLicensed();

        $this->post('/gate/write-paid')->assertOk()->assertSee('through');
    }

    public function test_writing_to_a_free_feature_is_always_allowed(): void
    {
        $this->post('/gate/write-free')->assertOk()->assertSee('through');
    }

    public function test_a_grandfathered_feature_may_be_read_but_not_written(): void
    {
        $this->setCmsOptions(['falcon_grandfathered_features' => json_encode(['multilang'])]);

        // EnsurePro is grandfather-inclusive, EnsureProEditable is not. That difference is
        // the whole of the read/write split, so both halves are asserted together.
        $this->get('/gate/read-paid')->assertOk()->assertSee('through');
        $this->post('/gate/write-paid')->assertOk()->assertSee('Pro version', false);
    }

    public function test_the_strict_gate_ignores_grandfathering(): void
    {
        $this->setCmsOptions(['falcon_grandfathered_features' => json_encode(['multilang'])]);

        // Strict is what the storefront uses: the moment the grace window ends, a
        // grandfathered site still cannot check out.
        $this->get('/gate/read-paid')->assertSee('through');
        $this->get('/gate/read-paid-strict')->assertSee('Pro version', false);
    }
}
