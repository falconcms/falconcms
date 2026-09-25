<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The Customizer's Responsive Typography, which headings are sized by on the front end.
 *
 * Two settings drive it — Sensitivity and Minimum Font Size Factor — and the second is not a
 * size but a multiplier of a third setting in another section, Body Typography's size. That
 * makes the floor invisible, and everything here that used to go wrong went wrong quietly.
 *
 * The Font Size boxes are free text. A heading written as "2.5rem", "150%" or a bare "40" did
 * not match the px-only pattern the fluid size was built from, so it was left fixed with no
 * sign that responsive typography had skipped it. Worse, the same reading was applied to the
 * body size to work out the floor, by stripping every non-digit: "1rem" became 1, dropping the
 * floor to 1.5px so every heading shrank to nearly nothing, and "120%" became 120, lifting the
 * floor to 180px so nothing shrank at all. One character in an unrelated field decided whether
 * the feature did far too much or nothing whatsoever.
 */
class ResponsiveTypographyTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'rt-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function customizer(): string
    {
        return $this->actingAs($this->administrator())->get('/admin/customizer')->assertOk()->getContent();
    }

    /** The two numbers a reader actually sees: the size at the small width and at the medium one. */
    private function bounds(string $clamp): array
    {
        $this->assertStringStartsWith('clamp(', $clamp, "not a fluid size: {$clamp}");
        preg_match('/^clamp\(([^,]+),.*,\s*([^,)]+)\)$/', $clamp, $m);

        return [trim($m[1]), trim($m[2])];
    }

    public function test_a_heading_in_px_slides_between_the_floor_and_its_own_size(): void
    {
        // Body 16px, factor 1.5 -> floor 24px. Sensitivity 1 means it travels the whole way.
        $css = falcon_fluid_font_size('40px', 1.0, 24.0, 800, 1100, 16.0);

        $this->assertSame(['24px', '40px'], $this->bounds($css));
        $this->assertStringContainsString('(100vw - 800px) / 300', $css);
    }

    public function test_sensitivity_decides_how_far_it_travels(): void
    {
        // Half the sensitivity, half the distance from 40px down to the 24px floor.
        $css = falcon_fluid_font_size('40px', 0.5, 24.0, 800, 1100, 16.0);

        $this->assertSame(['32px', '40px'], $this->bounds($css));
    }

    public function test_a_size_in_rem_is_no_longer_skipped(): void
    {
        // 2.5rem is 40px. The bounds stay in rem so the reader's own browser font size still
        // applies at either end; only the middle term has to be px, because calc() cannot
        // divide a length by a length.
        $css = falcon_fluid_font_size('2.5rem', 1.0, 24.0, 800, 1100, 16.0);

        $this->assertSame(['1.5rem', '2.5rem'], $this->bounds($css));
        $this->assertStringContainsString('calc(24px +', $css);
    }

    public function test_a_size_in_percent_or_em_is_read_against_the_body(): void
    {
        // 200% of a 20px body is 40px, and so is 2em.
        foreach (['200%', '2em'] as $size) {
            $css = falcon_fluid_font_size($size, 1.0, 30.0, 800, 1100, 20.0);
            $this->assertStringStartsWith('clamp(', $css, "{$size} was left fixed");
            $this->assertStringContainsString('calc(30px +', $css);
        }
    }

    public function test_a_bare_number_is_written_out_as_a_size_the_browser_accepts(): void
    {
        // `font-size: 40` is not valid CSS — the browser throws the whole declaration away, so
        // the heading silently lost its size as well as its responsiveness.
        $this->assertSame(['24px', '40px'], $this->bounds(falcon_fluid_font_size('40', 1.0, 24.0, 800, 1100, 16.0)));
        $this->assertSame('20px', falcon_fluid_font_size('20', 1.0, 24.0, 800, 1100, 16.0));
    }

    public function test_a_heading_at_or_below_the_floor_keeps_its_size(): void
    {
        // Nothing may go below the minimum, so something already there has nowhere to travel.
        $this->assertSame('24px', falcon_fluid_font_size('24px', 1.0, 24.0, 800, 1100, 16.0));
        $this->assertSame('16px', falcon_fluid_font_size('16px', 1.0, 24.0, 800, 1100, 16.0));
    }

    public function test_it_is_off_at_zero_sensitivity_and_on_misconfigured_breakpoints(): void
    {
        $this->assertSame('40px', falcon_fluid_font_size('40px', 0.0, 24.0, 800, 1100, 16.0));
        $this->assertSame('40px', falcon_fluid_font_size('40px', 1.0, 24.0, 1100, 800, 16.0));
        $this->assertSame('40px', falcon_fluid_font_size('40px', 1.0, 24.0, 900, 900, 16.0));
    }

    public function test_a_value_it_cannot_read_is_left_exactly_as_written(): void
    {
        // Never invent a size for something an author may have meant literally.
        foreach (['inherit', 'clamp(1rem, 2vw, 2rem)', '', 'calc(1rem + 2px)', '-10px'] as $size) {
            $this->assertSame($size, falcon_fluid_font_size($size, 1.0, 24.0, 800, 1100, 16.0));
        }
    }

    public function test_the_body_size_is_read_by_unit_not_by_stripping_digits(): void
    {
        // The old reading kept the digits and threw the unit away, so these three came out as
        // 15, 1 and 120 — three different floors from what is, twice over, the same size.
        $this->assertSame(15.0, falcon_css_size_to_px('15px')['px']);
        $this->assertSame(16.0, falcon_css_size_to_px('1rem')['px']);
        $this->assertSame(19.2, round(falcon_css_size_to_px('120%')['px'], 2));
    }

    public function test_a_body_in_rem_no_longer_shrinks_every_heading_to_nothing(): void
    {
        // Body 1rem = 16px, factor 1.5 -> floor 24px, exactly as if it had been written 16px.
        $bodyPx = falcon_css_size_to_px('1rem', 16.0)['px'];
        $css = falcon_fluid_font_size('40px', 1.0, $bodyPx * 1.5, 800, 1100, $bodyPx);

        $this->assertSame(['24px', '40px'], $this->bounds($css),
            'the floor collapsed again, so headings shrink far below anything readable');
    }

    public function test_the_customizer_says_what_the_settings_come_to(): void
    {
        // The sliders are two numbers that produce a clamp() out of a third setting in another
        // section. Without this, "nothing happens when I move them" has nowhere to be answered.
        update_cms_option('theme_typography_body', json_encode(['size' => '15px']));
        update_cms_option('theme_typography_h1', json_encode(['size' => '34px']));
        update_cms_option('theme_typography_h6', json_encode(['size' => '16px']));
        update_cms_option('theme_font_size_factor', '2.1');
        update_cms_option('theme_typography_sensitivity', '0.68');
        forget_cms_options_cache();

        $html = $this->customizer();

        // 15 x 2.1 = 31.5, which is above H6 and below H1 — the whole explanation in one line.
        $this->assertStringContainsString('31.5px', $html);
        $this->assertStringContainsString('H6', $html);
    }

    public function test_zero_sensitivity_is_reported_as_off_rather_than_as_numbers(): void
    {
        update_cms_option('theme_typography_sensitivity', '0');
        forget_cms_options_cache();

        $this->assertStringContainsString(
            'responsive typography is off',
            $this->customizer());
    }
}
