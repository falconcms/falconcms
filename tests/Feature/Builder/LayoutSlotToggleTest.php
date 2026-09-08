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
 *
 * The switch also asks for a state rather than for a flip. A flip is only correct while the
 * page knows the current state exactly, and a second flip — from a double-click, a retried
 * request, or a tab opened before someone else changed the slot — lands on the opposite
 * value, which is read as "I switched it on, reloaded, and it was off".
 */
class LayoutSlotToggleTest extends TestCase
{
    use MakesShopFixtures;

    /** @param  bool|null  $active  the state being asked for; null sends none and asks for a flip */
    private function toggle(string $layout, string $slot, ?bool $active = null)
    {
        $payload = ['layout' => $layout, 'slot' => $slot];
        if ($active !== null) {
            $payload['active'] = $active;
        }

        $request = Request::create('/admin/falcon-builder-sections/slot-toggle', 'POST', $payload);
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

    public function test_asking_for_a_state_stores_that_state_however_often_it_is_asked(): void
    {
        $this->actingAsAdmin();
        update_cms_option('falcon_layout_global', json_encode(['header' => ['id' => 7, 'active' => true]]));

        // The same request repeated — the duplicate a double-click or a browser retry sends.
        foreach ([false, false, false] as $ignored) {
            $result = $this->toggle('global', 'header', false);
            $this->assertTrue($result['ok']);
            $this->assertFalse($result['active'], 'asking for off must never come back on');
        }

        $this->assertStringContainsString('"active":false', get_cms_option('falcon_layout_global', ''));

        $back = $this->toggle('global', 'header', true);

        $this->assertTrue($back['active']);
        $this->assertStringContainsString('"active":true', get_cms_option('falcon_layout_global', ''));
    }

    public function test_asking_for_a_state_is_not_thrown_off_by_a_stale_idea_of_the_current_one(): void
    {
        $this->actingAsAdmin();
        update_cms_option('falcon_layout_global', json_encode(['header' => ['id' => 7, 'active' => true]]));

        // Someone else turns it off while this page still shows it on.
        $this->toggle('global', 'header', false);

        // The stale page asks for off as well. A flip would turn it back ON here.
        $result = $this->toggle('global', 'header', false);

        $this->assertFalse($result['active']);
        $this->assertStringContainsString('"active":false', get_cms_option('falcon_layout_global', ''));
    }

    public function test_a_client_that_sends_no_state_still_gets_a_flip(): void
    {
        $this->actingAsAdmin();
        update_cms_option('falcon_layout_global', json_encode(['header' => ['id' => 7, 'active' => true]]));

        $this->assertFalse($this->toggle('global', 'header')['active']);
        $this->assertTrue($this->toggle('global', 'header')['active']);
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
