<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\CodeHighlighter;
use FalconCms\Core\Tests\TestCase;

/**
 * The Code Block's Design tab uses the shared typography control (cb_*), like every other
 * element; blocks saved with its old three fields keep their look until opened.
 */
class CodeBlockTypographyTest extends TestCase
{
    private function render(array $s): string
    {
        return get_falcon_post_content(json_encode([[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'cb1', 'type' => 'code_block', 'settings' => $s + ['code' => 'echo 1;', 'language' => 'php'],
            ]]]],
        ]]));
    }

    /** The <pre> rule of the block's own stylesheet. */
    private function preRule(string $html): string
    {
        $this->assertMatchesRegularExpression('/#fc-code-cb1 pre \{([^}]*)\}/', $html);
        preg_match('/#fc-code-cb1 pre \{([^}]*)\}/', $html, $m);

        return preg_replace('/\s+/', ' ', $m[1]);
    }

    public function test_the_shared_control_reaches_the_page(): void
    {
        $html = $this->render([
            'cb_family' => 'Fira Code, monospace', 'cb_size' => '15', 'cb_weight' => '500',
            'cb_line_height' => '1.9', 'cb_letter_spacing' => '0.5', 'cb_transform' => 'uppercase',
        ]);
        $pre = $this->preRule($html);

        $this->assertStringContainsString('font-size: 15px;', $pre);
        $this->assertStringContainsString('line-height: 1.9;', $pre, 'a ratio stays a ratio');
        $this->assertStringContainsString('font-weight: 500;', $pre);
        $this->assertStringContainsString('letter-spacing: 0.5px;', $pre);
        $this->assertStringContainsString('text-transform: uppercase;', $pre);
        $this->assertMatchesRegularExpression('/#fc-code-cb1 \{[^}]*font-family: Fira Code, monospace;/', $html);
    }

    public function test_defaults_and_the_empty_size(): void
    {
        // On the shared control with nothing chosen: monospace, the body size, 1.7.
        $html = $this->render(['cb_size' => '', 'cb_family' => 'inherit']);
        $pre = $this->preRule($html);
        $this->assertStringContainsString('font-size: inherit;', $pre);
        $this->assertStringContainsString('line-height: 1.7;', $pre);
        $this->assertStringNotContainsString('font-weight', $pre);
        $this->assertStringContainsString("'SF Mono'", $html);
    }

    public function test_blocks_saved_with_the_old_fields_keep_their_look(): void
    {
        $this->assertSame(
            ['family' => 'Courier New, monospace', 'size' => '18px', 'lineHeight' => '2', 'weight' => '', 'letterSpacing' => '', 'transform' => ''],
            CodeHighlighter::typography(['fontFamily' => 'Courier New, monospace', 'fontSize' => 18, 'lineHeight' => '2'])
        );
        $this->assertSame('14px', CodeHighlighter::typography([])['size'], 'never set: the 14px blocks always had');
        $this->assertSame('inherit', CodeHighlighter::typography(['fontSize' => ''])['size']);
        // Once on the shared control, the old keys are not read any more.
        $this->assertSame('inherit', CodeHighlighter::typography(['cb_size' => '', 'fontSize' => 18])['size']);
        $this->assertSame('1.7', CodeHighlighter::typography(['cb_size' => '', 'lineHeight' => '3'])['lineHeight']);
    }

    public function test_values_cannot_leave_the_stylesheet(): void
    {
        $html = $this->render(['cb_size' => '14px;}</style><script>alert(1)</script>', 'cb_weight' => '700;color:red', 'cb_transform' => 'none;x']);
        $this->assertStringNotContainsString('<script>alert', $html);
        $pre = $this->preRule($html);
        $this->assertStringNotContainsString('color:red', $pre);
        $this->assertStringNotContainsString('text-transform', $pre);
    }

    public function test_the_builder_offers_the_shared_control_and_moves_old_blocks_into_it(): void
    {
        $design = file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/code-block-design.blade.php');
        $this->assertStringContainsString("fields.typography', ['prefix' => 'cb'", $design);
        $this->assertStringNotContainsString('settings.fontFamily', $design);

        $scripts = file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');
        $this->assertStringContainsString('function fcCodeTypoMigrate(el)', $scripts);
        $this->assertStringContainsString('watch(() => editingElement.value, (el) => fcCodeTypoMigrate(el), { immediate: true });', $scripts);
        $this->assertStringContainsString("cb_family: '', cb_weight: '', cb_size: ''", $scripts);
    }

    public function test_the_typography_survives_the_shortcode_round_trip(): void
    {
        $trip = function (array $s): array {
            $layout = [['type' => 'container', 'settings' => [], 'columns' => [['settings' => [], 'elements' => [
                ['id' => 'cbx', 'type' => 'code_block', 'settings' => $s + ['code' => 'x']],
            ]]]]];

            return json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout))), true)[0]['columns'][0]['elements'][0]['settings'];
        };

        $set = ['cb_family' => 'Fira Code, monospace', 'cb_size' => '15px', 'cb_weight' => '600', 'cb_line_height' => '1.8', 'cb_letter_spacing' => '1', 'cb_transform' => 'lowercase'];
        $back = $trip($set);
        foreach ($set as $key => $value) {
            $this->assertSame($value, $back[$key] ?? null, $key);
        }

        // An empty size stays empty (follow the body size); it must not fall back to 14px.
        $empty = $trip(['cb_size' => '']);
        $this->assertArrayHasKey('cb_size', $empty);
        $this->assertSame('', $empty['cb_size']);
        $this->assertSame('inherit', CodeHighlighter::typography($empty)['size']);

        // An older block still comes back as it was.
        $this->assertArrayNotHasKey('cb_size', $trip(['fontSize' => 18]));
        $this->assertSame('18px', CodeHighlighter::typography($trip(['fontSize' => 18]))['size']);
    }
}
