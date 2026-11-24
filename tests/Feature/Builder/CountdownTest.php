<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use Carbon\Carbon;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\Countdown;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The Countdown element (Pro): its three kinds of deadline, the numbers the page is drawn
 * with, what happens at zero, its four designs, the canvas preview and the shortcode
 * round trip.
 */
class CountdownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_cms_option('timezone', 'Asia/Dhaka');
    }

    private function render(array $s, bool $preview = false, ?string $device = null): string
    {
        return view('falcon-cms::frontend.builder.elements.countdown.render', [
            'el' => ['id' => 'cd1', 'type' => 'countdown', 'settings' => $s],
            's' => $s,
            'builderPreview' => $preview,
            'previewDevice' => $device,
        ])->render();
    }

    /** A deadline $seconds from now, as the Ends at field stores it (site timezone, to the minute). */
    private function endIn(int $seconds): string
    {
        return Carbon::now('Asia/Dhaka')->addSeconds($seconds)->format('Y-m-d\TH:i');
    }

    public function test_it_is_registered_as_a_pro_element_with_a_server_preview(): void
    {
        $def = falcon_builder_element_defs(true)[Countdown::TYPE] ?? null;
        $this->assertNotNull($def);
        $this->assertSame('server', $def['preview']);
        $this->assertSame('falcon-cms::frontend.builder.elements.countdown.render', $def['template']);
        $this->assertTrue(view()->exists($def['template']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T18:00$/', $def['fields']['end_at']['default'], 'a new countdown points a week ahead');

        $scripts = file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');
        $this->assertMatchesRegularExpression("/const proElementTypes = \[[^\]]*'countdown'/", $scripts);
    }

    public function test_seconds_are_shared_out_over_the_units_on_show(): void
    {
        $all = array_keys(Countdown::UNITS);
        $this->assertSame(['days' => 2, 'hours' => 3, 'minutes' => 4, 'seconds' => 5], Countdown::split(2 * 86400 + 3 * 3600 + 4 * 60 + 5, $all));
        // Without days, hours carry them.
        $this->assertSame(['hours' => 51, 'minutes' => 4], Countdown::split(2 * 86400 + 3 * 3600 + 4 * 60 + 5, ['hours', 'minutes']));
        $this->assertSame(['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0], Countdown::split(-50, $all));
        $this->assertSame('05', Countdown::pad(5, true));
        $this->assertSame('5', Countdown::pad(5, false));
        $this->assertSame('123', Countdown::pad(123, true));
    }

    public function test_a_daily_deadline_is_the_next_chosen_day_at_the_time_in_the_site_timezone(): void
    {
        $tz = 'Asia/Dhaka';
        $at = fn (string $local) => Carbon::parse($local, $tz)->getTimestamp() * 1000;

        // Wednesday 10:00 → today 17:00.
        $this->assertSame($at('2026-11-25 17:00'), Countdown::dailyEndMs('17:00', [0, 1, 2, 3, 4, 5, 6], $tz, $at('2026-11-25 10:00')));
        // Wednesday 18:00 → tomorrow 17:00.
        $this->assertSame($at('2026-11-26 17:00'), Countdown::dailyEndMs('17:00', [0, 1, 2, 3, 4, 5, 6], $tz, $at('2026-11-25 18:00')));
        // Friday evening, weekdays only → Monday.
        $this->assertSame($at('2026-11-30 17:00'), Countdown::dailyEndMs('17:00', [1, 2, 3, 4, 5], $tz, $at('2026-11-27 18:00')));
        $this->assertSame('09:05', Countdown::time('9:05'));
        $this->assertSame('17:00', Countdown::time('25:99'));
    }

    public function test_the_page_is_drawn_with_the_right_numbers_before_any_script_runs(): void
    {
        $html = $this->render(['mode' => 'fixed', 'end_at' => $this->endIn(2 * 86400 + 3 * 3600 + 150)]);

        $this->assertMatchesRegularExpression('/data-unit="days">\s*<span class="fcd-num">02</', $html);
        $this->assertMatchesRegularExpression('/data-unit="hours">\s*<span class="fcd-num">03</', $html);
        $this->assertStringContainsString('<span class="fcd-label">Days</span>', $html);
        $this->assertStringContainsString('role="timer"', $html);
        $this->assertStringContainsString('window.__falconCountdown', $html, 'the page carries the runtime');

        $data = $this->dataOf($html);
        $this->assertSame('fixed', $data['mode']);
        $this->assertSame(Countdown::fixedEndMs($this->endIn(2 * 86400 + 3 * 3600 + 150)), $data['end']);
        $this->assertSame('Asia/Dhaka', $data['tz']);
        $this->assertFalse($data['preview']);
    }

    public function test_hidden_units_labels_and_leading_zeros(): void
    {
        $html = $this->render([
            'end_at' => $this->endIn(2 * 86400 + 3 * 3600 + 150), 'units' => ['hours', 'minutes'],
            'labels' => 'short', 'leading_zero' => 'hide',
        ]);
        $this->assertStringNotContainsString('data-unit="days"', $html);
        $this->assertMatchesRegularExpression('/data-unit="hours">\s*<span class="fcd-num">51</', $html);
        $this->assertStringContainsString('<span class="fcd-label">h</span>', $html);
        $this->assertStringContainsString('fcd--labels-short', $html);

        $hidden = $this->render(['end_at' => $this->endIn(3600), 'labels' => 'hide']);
        $this->assertStringNotContainsString('fcd-label', strstr($hidden, '<div id="fcd-cd1"'));
    }

    public function test_at_zero_the_page_hides_shows_the_message_or_stays_at_zero(): void
    {
        $past = Carbon::now('Asia/Dhaka')->subDay()->format('Y-m-d\TH:i');

        $this->assertSame('', trim($this->render(['end_at' => $past, 'expire_action' => 'hide'])));

        $message = $this->render(['end_at' => $past, 'expire_action' => 'message', 'expire_message' => 'Sale over <b>now</b>']);
        $this->assertMatchesRegularExpression('/class="fcd-units"[^>]*hidden/', $message);
        $this->assertMatchesRegularExpression('/<div class="fcd-expired"\s*>Sale over &lt;b&gt;now&lt;\/b&gt;<\/div>/', $message);

        $zero = $this->render(['end_at' => $past, 'expire_action' => 'zero']);
        $this->assertMatchesRegularExpression('/data-unit="seconds">\s*<span class="fcd-num">00</', $zero);
        $this->assertDoesNotMatchRegularExpression('/class="fcd-units"[^>]*hidden/', $zero);

        $redirect = $this->dataOf($this->render(['end_at' => $past, 'expire_action' => 'redirect', 'expire_url' => 'javascript:alert(1)']));
        $this->assertSame('', $redirect['url'], 'only real links are followed');
        $this->assertSame('/offer-ended', $this->dataOf($this->render(['expire_action' => 'redirect', 'expire_url' => '/offer-ended']))['url']);

        // The builder keeps showing it, and says what the page will do.
        $preview = $this->render(['end_at' => $past, 'expire_action' => 'hide'], true);
        $this->assertStringContainsString('class="fcd ', $preview);
        $this->assertStringContainsString('On the page: the countdown is hidden', $preview);
        $this->assertStringNotContainsString('window.__falconCountdown', $preview, 'the builder loads the runtime itself');
        $this->assertTrue($this->dataOf($preview)['preview']);
    }

    public function test_evergreen_starts_full_and_daily_counts_to_the_time(): void
    {
        $ever = $this->render(['mode' => 'evergreen', 'ever_days' => 0, 'ever_hours' => 5, 'ever_minutes' => 30, 'ever_restart' => 'restart']);
        $this->assertMatchesRegularExpression('/data-unit="hours">\s*<span class="fcd-num">05</', $ever);
        $this->assertMatchesRegularExpression('/data-unit="minutes">\s*<span class="fcd-num">30</', $ever);
        $data = $this->dataOf($ever);
        $this->assertSame(5 * 3600 + 30 * 60, $data['ever']);
        $this->assertTrue($data['restart']);

        $daily = $this->dataOf($this->render(['mode' => 'daily', 'daily_time' => '09:30', 'daily_days' => ['1', '3', '5']]));
        $this->assertSame('09:30', $daily['time']);
        $this->assertSame([1, 3, 5], $daily['days']);
    }

    public function test_each_design_and_its_values_reach_a_scoped_stylesheet_safely(): void
    {
        foreach (Countdown::DESIGNS as $design) {
            $html = $this->render(['end_at' => $this->endIn(3600), 'design' => $design]);
            $this->assertStringContainsString('fcd--'.$design, $html);
            $this->assertSame($design === 'circles' ? 4 : 0, substr_count($html, 'class="fcd-ring"'), $design);
            $this->assertSame(in_array($design, ['flip', 'minimal'], true) ? 3 : 0, substr_count($html, 'class="fcd-sep"'), $design.' separators');
        }

        $html = $this->render([
            'end_at' => $this->endIn(3600), 'item_size' => 120, 'item_size_mobile' => 60, 'gap' => 20,
            'num_color' => '#ff0000;}</style><script>alert(1)</script>', 'box_bg' => 'rgb(1, 2, 3)',
            'num_typo_family' => 'Baloo Da 2, sans-serif', 'num_typo_size' => '40', 'num_typo_weight' => '800',
            'label_typo_letter_spacing' => '2', 'margin_top' => 30, 'margin_bottom_mobile' => 5, 'align' => 'flex-start', 'align_mobile' => 'center',
        ]);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('--fcd-size:120px;', $html);
        $this->assertStringContainsString('--fcd-bg:rgb(1, 2, 3);', $html);
        $this->assertStringContainsString("font-family: 'Baloo Da 2', sans-serif; font-weight: 800; font-size: 40px;", $html);
        $this->assertStringContainsString('letter-spacing: 2px;', $html);
        $this->assertStringContainsString('margin-top:30px;', $html);
        $this->assertMatchesRegularExpression('/@media\(max-width:800px\)\{#fcd-cd1\{--fcd-size:60px;margin-bottom:5px;--fcd-justify:center;\}\}/', $html);

        // The canvas previews the device it is set to.
        $this->assertStringContainsString('--fcd-size:60px;', $this->render(['end_at' => $this->endIn(3600), 'item_size_mobile' => 60], true, 'mobile'));
    }

    public function test_it_renders_inside_a_nested_row_on_a_page_with_the_shared_wrapper(): void
    {
        $layout = [[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'row1', 'type' => 'row', 'settings' => [],
                'columns' => [['id' => 'n1', 'basis' => '50%', 'settings' => [], 'elements' => [[
                    'id' => 'cdn', 'type' => 'countdown',
                    'settings' => ['end_at' => $this->endIn(7200), 'cssClass' => 'promo-timer', 'cssId' => 'launch', 'visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => true]],
                ]]]],
            ]]]],
        ]];
        $html = get_falcon_post_content(json_encode($layout));

        $this->assertStringContainsString('id="fcd-cdn"', $html);
        $this->assertMatchesRegularExpression('/class="falcon-builder-element falcon-custom-block w-full\s+falcon-hide-mobile promo-timer"\s+id="launch"/', $html);
    }

    public function test_the_canvas_preview_route_draws_it(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'cd-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
        $this->actingAs($admin)
            ->postJson(route('admin.falcon-builder.element-preview'), [
                'id' => 'p1', 'type' => 'countdown', 'settings' => ['end_at' => $this->endIn(3600), 'design' => 'circles'], 'device' => 'desktop',
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'id="fcd-p1"') && str_contains($html, 'fcd--circles') && str_contains($html, '&quot;preview&quot;:true'));
    }

    public function test_the_builder_loads_the_runtime_for_the_canvas(): void
    {
        $styles = file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/styles.blade.php');
        $this->assertStringContainsString("@include('falcon-cms::components.frontend.countdown-runtime')", $styles);
    }

    public function test_every_setting_survives_the_builder_shortcode_round_trip(): void
    {
        $settings = [
            'mode' => 'daily', 'end_at' => '2027-01-01T09:00', 'ever_days' => 2, 'ever_hours' => 4, 'ever_minutes' => 15,
            'ever_restart' => 'restart', 'daily_time' => '16:45', 'daily_days' => ['1', '2', '3'],
            'units' => ['hours', 'minutes', 'seconds'], 'labels' => 'above', 'label_hours' => 'ঘণ্টা',
            'leading_zero' => 'hide', 'expire_action' => 'redirect', 'expire_url' => '/sale-over?x=1&y=2',
            'design' => 'flip', 'align' => 'flex-end', 'align_mobile' => 'center', 'item_size' => 110, 'item_size_mobile' => 70,
            'gap' => 9, 'separator' => 'none', 'radius' => 4, 'num_color' => '#123456', 'box_bg' => 'rgba(0,0,0,0.5)',
            'num_typo_family' => 'Inter, sans-serif', 'num_typo_size' => '44', 'label_typo_transform' => 'uppercase',
            'margin_top' => 12, 'margin_top_mobile' => 4,
        ];
        $layout = [['type' => 'container', 'settings' => [], 'columns' => [['settings' => [], 'elements' => [
            ['id' => 'cdx', 'type' => Countdown::TYPE, 'settings' => $settings],
        ]]]]];

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout))), true);
        $restored = $back[0]['columns'][0]['elements'][0]['settings'];

        foreach ($settings as $key => $value) {
            $this->assertEquals($value, $restored[$key] ?? null, "setting {$key} survives");
        }
    }

    /** The runtime's settings, read back from the data attribute. */
    private function dataOf(string $html): array
    {
        $this->assertMatchesRegularExpression('/data-fcd="([^"]+)"/', $html);
        preg_match('/data-fcd="([^"]+)"/', $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
    }
}
