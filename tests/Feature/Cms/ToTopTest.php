<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Controllers\Admin\CustomizerController;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The back-to-top button.
 *
 * Rendered on the falcon_footer action rather than baked into a template, so it reaches
 * every theme that calls the hook and a theme update cannot drop it.
 *
 * The behaviour worth pinning down is what OFF means. A button that is merely hidden
 * still costs every reader its markup, its stylesheet and its script; off here means the
 * page comes back without any of them.
 */
class ToTopTest extends TestCase
{
    /** @param array<string, string> $options */
    private function render(array $options = []): string
    {
        DB::table('cms_settings')->whereIn('key', array_keys($options))->delete();

        foreach ($options as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }

        forget_cms_options_cache();

        return view('falcon-cms::components.frontend.to-top')->render();
    }

    /**
     * On by default, so a site that never opens these settings still gets it. Nothing has
     * to be saved for that to be true — the fallback is the answer.
     */
    public function test_it_is_on_by_default(): void
    {
        DB::table('cms_settings')->where('key', 'to_top_enabled')->delete();
        forget_cms_options_cache();

        $html = view('falcon-cms::components.frontend.to-top')->render();

        $this->assertStringContainsString('id="fc-to-top"', $html);
    }

    /** Off means nothing reaches the page — not a hidden button, nothing. */
    public function test_off_renders_nothing_at_all(): void
    {
        $html = $this->render(['to_top_enabled' => '0']);

        $this->assertSame('', trim($html));
    }

    /**
     * The button starts hidden to the browser as well as to the eye.
     *
     * `visibility: hidden` rather than only `opacity: 0`, so it cannot swallow a tap on
     * whatever is underneath it while it is invisible; and the `hidden` attribute until
     * the script runs, so a reader with JavaScript off is never given a button that
     * cannot do anything.
     */
    public function test_it_starts_out_of_the_way(): void
    {
        $html = $this->render(['to_top_enabled' => '1']);

        $this->assertMatchesRegularExpression('/#fc-to-top \{[^}]*visibility: hidden/s', $html);
        $this->assertMatchesRegularExpression('/<button id="fc-to-top"[^>]*\shidden/', $html);
        $this->assertStringContainsString('btn.hidden = false', $html);
    }

    /** Every setting has to reach the page, or the panel is lying about what it does. */
    public function test_the_settings_reach_the_page(): void
    {
        $html = $this->render([
            'to_top_enabled' => '1',
            'to_top_offset' => '800',
            'to_top_position' => 'left',
            'to_top_side_gap' => '40',
            'to_top_size' => '60',
            'to_top_radius' => '8',
            'to_top_icon' => 'chevron',
            'to_top_bg_color' => '#123456',
            'to_top_icon_color' => '#ffee00',
            'to_top_hover_bg_color' => '#654321',
            'to_top_show_progress' => '1',
            'to_top_hide_mobile' => '1',
        ]);

        $this->assertStringContainsString('data-after="800"', $html);
        $this->assertStringContainsString('left: 40px', $html);
        $this->assertStringContainsString('bottom: 40px', $html);
        $this->assertStringContainsString('width: 60px', $html);
        $this->assertStringContainsString('border-radius: 8%', $html);
        $this->assertStringContainsString('background: #123456', $html);
        $this->assertStringContainsString('color: #ffee00', $html);
        $this->assertStringContainsString('#654321', $html);
        $this->assertStringContainsString('conic-gradient', $html);
        $this->assertStringContainsString('max-width: 640px', $html);
        $this->assertStringContainsString('polyline points="18 15 12 9 6 15"', $html);
    }

    /**
     * An unset colour means the theme's own, not one chosen here.
     *
     * A site that has never opened these settings should get a button that looks like it
     * belongs to the theme, so the background falls through to the theme's primary colour
     * rather than to a blue somebody picked in this file.
     */
    public function test_an_unset_colour_falls_through_to_the_theme(): void
    {
        $html = $this->render(['to_top_enabled' => '1', 'to_top_bg_color' => '']);

        $this->assertStringContainsString('var(--primary-color', $html);
        $this->assertStringNotContainsString('background: ;', $html);
    }

    /** The extras cost nothing when they are not asked for. */
    public function test_the_extras_are_absent_unless_switched_on(): void
    {
        $html = $this->render(['to_top_enabled' => '1', 'to_top_show_progress' => '0', 'to_top_hide_mobile' => '0']);

        $this->assertStringNotContainsString('conic-gradient', $html);
        $this->assertStringNotContainsString('max-width: 640px', $html);
    }

    /**
     * The scroll itself is smooth, and stops being so for a reader who has asked their
     * system for less movement — a full-page glide is exactly the kind of motion that
     * setting exists to turn off.
     */
    public function test_the_scroll_is_smooth_but_respects_reduced_motion(): void
    {
        $html = $this->render(['to_top_enabled' => '1']);

        $this->assertStringContainsString("behavior: reduced ? 'auto' : 'smooth'", $html);
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $html);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
    }

    /**
     * One script however many times the footer is rendered.
     *
     * The theme layout renders it twice on some requests — once to scan it — which is
     * what makes Blade's render-once directive unusable here; the Code Block element hit
     * exactly that and shipped without its script.
     */
    public function test_the_script_is_guarded_against_a_second_render(): void
    {
        $html = $this->render(['to_top_enabled' => '1']);

        $this->assertStringContainsString('if (window.__falconToTop) return;', $html);
    }

    /** It is on the footer hook, so every theme that calls the action gets it. */
    public function test_it_is_hooked_onto_the_footer_rather_than_a_template(): void
    {
        $provider = (string) file_get_contents(__DIR__.'/../../../src/FalconCmsServiceProvider.php');

        $this->assertMatchesRegularExpression(
            "/add_falcon_action\('falcon_footer'.*?components\.frontend\.to-top/s",
            $provider,
            'the button is no longer rendered on the footer hook, so a theme could lose it'
        );
    }

    /** And the settings are where the panel says they are. */
    public function test_the_settings_live_under_performance(): void
    {
        $sections = (new \ReflectionClass(CustomizerController::class))->getMethod('sections');
        $sections->setAccessible(true);
        $fields = $sections->invoke(new CustomizerController)['performance']['fields'] ?? [];

        foreach ([
            'to_top_enabled', 'to_top_offset', 'to_top_position', 'to_top_side_gap',
            'to_top_size', 'to_top_radius', 'to_top_icon', 'to_top_bg_color',
            'to_top_icon_color', 'to_top_hover_bg_color', 'to_top_show_progress',
            'to_top_hide_mobile',
        ] as $key) {
            $this->assertArrayHasKey($key, $fields, "{$key} is missing from Customizer → Performance");
        }

        $this->assertSame('1', $fields['to_top_enabled']['default'],
            'the button is no longer on by default');

        // Everything but the switch is folded away until the switch is on.
        foreach ($fields as $key => $field) {
            if (str_starts_with($key, 'to_top_') && $key !== 'to_top_enabled') {
                $this->assertSame('to_top_enabled', $field['depends'] ?? null,
                    "{$key} is shown even when the button is switched off");
            }
        }
    }
}
