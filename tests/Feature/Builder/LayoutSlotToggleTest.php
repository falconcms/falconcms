<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Http\Controllers\Admin\FalconBuilderController;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Turning a layout slot (Header, Footer, …) on or off from Falcon Builder → Sections.
 *
 * The switch used to lie. setSlotActive() returned a plain bool, so "there is nothing
 * assigned to this slot" and "it is now inactive" came back as the same `false`: the page
 * reported success, drew the switch off, wrote nothing, and the next reload showed it on
 * again. Anyone who hit that concluded the toggle simply did not work. Nothing-to-toggle is
 * now its own answer, and the endpoint says so instead of claiming a change it never made.
 */
class LayoutSlotToggleTest extends TestCase
{
    use MakesShopFixtures;

    private function toggle(string $layout, string $slot)
    {
        $request = Request::create('/admin/falcon-builder-sections/slot-toggle', 'POST', [
            'layout' => $layout,
            'slot' => $slot,
        ]);
        $request->headers->set('Accept', 'application/json');
        $this->app->instance('request', $request);

        return json_decode(
            app(FalconBuilderController::class)->toggleSlot($request)->getContent(),
            true
        );
    }

    private function actingAsAdmin(): void
    {
        $roleId = (int) DB::table('roles')->where('slug', 'administrator')->value('id');
        $this->actingAs($this->makeUser(['role_id' => $roleId]));
    }

    public function test_an_assigned_slot_toggles_and_the_new_state_is_stored(): void
    {
        $this->actingAsAdmin();
        update_cms_option('falcon_layout_global', json_encode(['header' => ['id' => 7, 'active' => true]]));

        $off = $this->toggle('global', 'header');

        $this->assertTrue($off['ok']);
        $this->assertFalse($off['active']);
        $this->assertStringContainsString('"active":false', get_cms_option('falcon_layout_global', ''));

        $on = $this->toggle('global', 'header');

        $this->assertTrue($on['ok']);
        $this->assertTrue($on['active']);
        $this->assertStringContainsString('"active":true', get_cms_option('falcon_layout_global', ''));
    }

    public function test_a_slot_with_nothing_assigned_reports_failure_rather_than_a_phantom_toggle(): void
    {
        $this->actingAsAdmin();
        update_cms_option('falcon_layout_global', json_encode(['header' => ['id' => 7, 'active' => true]]));

        $result = $this->toggle('global', 'footer');

        $this->assertFalse($result['ok'], 'an empty slot must not report a successful toggle');
        $this->assertArrayNotHasKey('active', $result);
        $this->assertStringContainsString('Assign a section', $result['message']);
        $this->assertSame(
            json_encode(['header' => ['id' => 7, 'active' => true]]),
            get_cms_option('falcon_layout_global', ''),
            'nothing may be written for a slot that has no section'
        );
    }

    public function test_an_unknown_custom_layout_reports_failure_and_writes_nothing(): void
    {
        $this->actingAsAdmin();
        $layouts = [['id' => 'lay_real', 'name' => 'Real', 'assignments' => ['content' => ['id' => 3, 'active' => true]]]];
        update_cms_option('falcon_layouts', json_encode($layouts));

        $result = $this->toggle('lay_missing', 'content');

        $this->assertFalse($result['ok']);
        $this->assertSame(json_encode($layouts), get_cms_option('falcon_layouts', ''));
    }

    public function test_a_custom_layout_slot_toggles(): void
    {
        $this->actingAsAdmin();
        update_cms_option('falcon_layouts', json_encode([
            ['id' => 'lay_a', 'name' => 'A', 'assignments' => ['content' => ['id' => 3, 'active' => true]]],
        ]));

        $result = $this->toggle('lay_a', 'content');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['active']);
        $this->assertStringContainsString('"active":false', get_cms_option('falcon_layouts', ''));
    }
}
