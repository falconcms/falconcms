<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * A column's (or nested column's) visual position within its row, settable per breakpoint.
 *
 * The point of the feature is that a column can sit third on desktop and first on mobile
 * without touching its place in the actual document — order is a purely visual CSS property,
 * so tab order and screen readers keep following the real content regardless of what any
 * breakpoint's order says. Regular and nested columns share the same settings object and the
 * same rendering code (editingColumn resolves to either one, columnOuterStyle renders both),
 * so one field and one CSS rule is genuinely both, not two features that happen to look alike.
 */
class ColumnOrderTest extends TestCase
{
    private function columnCanvasSource(): string
    {
        return file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );
    }

    private function columnFrontendSource(): string
    {
        return file_get_contents(
            __DIR__.'/../../../resources/views/frontend/builder/column.blade.php'
        );
    }

    public function test_a_new_column_has_no_order_set(): void
    {
        $scripts = $this->columnCanvasSource();

        $start = strpos($scripts, 'const makeColumnSettings');
        $this->assertNotFalse($start, 'the column settings factory has moved');

        $this->assertStringContainsString('order: null', substr($scripts, $start, 2200),
            'a freshly added column no longer defaults order to unset');
    }

    /**
     * order is a flex property: it only visually rearranges the columns it applies to, and
     * does nothing on its own to a column nobody has touched — CSS's own default (0) already
     * matches "leave it where it is" relative to every other untouched column. Forcing 0 onto
     * every column here would be redundant, not wrong, but the settings factory leaving it
     * unset is the actual guarantee that an old layout with no opinion about order renders
     * identically to before this feature existed.
     */
    public function test_the_canvas_applies_the_responsive_order_to_the_column(): void
    {
        $scripts = $this->columnCanvasSource();

        $start = strpos($scripts, 'const columnOuterStyle');
        $this->assertNotFalse($start, 'columnOuterStyle has moved');

        $block = substr($scripts, $start, 4000);

        $this->assertStringContainsString("order: getResponsiveVal(s, 'order', device.value)", $block,
            'the canvas column no longer reads the order setting for the active device');
    }

    public function test_the_front_end_writes_order_as_a_plain_style_for_desktop(): void
    {
        $template = $this->columnFrontendSource();

        $this->assertStringContainsString("\$outerStyles[] = 'order: ' . \$s['order']", $template,
            'the front end no longer writes a plain (non-responsive) order declaration for the column');
    }

    public function test_the_front_end_writes_a_separate_important_order_rule_per_breakpoint(): void
    {
        $template = $this->columnFrontendSource();

        // Rules generated inside the tablet/mobile media-query loop must win over the plain
        // desktop declaration above — that's what !important is doing here, matching every
        // other responsive column override (alignment, padding, z-index, ...) in this file.
        $this->assertStringContainsString("\$getColRespOvr('order', \$rDev)", $template,
            'the responsive order override no longer reads the per-breakpoint setting');
        $this->assertStringContainsString('"order:{$rOrder}!important"', $template,
            'the responsive order override no longer emits an !important rule that can beat the desktop value');
    }

    /**
     * A real render, not just a source match — the same class of check that caught the
     * itemColorActive crash earlier in this file's sibling tests. order is read straight off
     * $s with no null-coalescing before use anywhere in this path, so a column that has never
     * had an order (every column before this feature shipped) must still render cleanly.
     */
    public function test_a_column_with_no_order_set_still_renders(): void
    {
        $container = ['settings' => ['columnGap' => '20px'], 'columns' => [['basis' => '100%']]];
        $column = ['id' => 'col-1', 'basis' => '100%', 'settings' => [], 'elements' => []];

        $html = view('falcon-cms::frontend.builder.column', [
            'column' => $column,
            'container' => $container,
        ])->render();

        $this->assertStringNotContainsString('order:', $html,
            'a column with no order setting must not emit an order rule at all');
    }

    /** And the actual feature: setting order for one breakpoint renders that breakpoint's rule. */
    public function test_a_column_with_a_mobile_order_renders_the_mobile_rule(): void
    {
        $container = ['settings' => ['columnGap' => '20px'], 'columns' => [['basis' => '100%']]];
        $column = ['id' => 'col-2', 'basis' => '100%', 'settings' => ['order_mobile' => 1], 'elements' => []];

        $html = view('falcon-cms::frontend.builder.column', [
            'column' => $column,
            'container' => $container,
        ])->render();

        $this->assertStringContainsString('order:1!important', $html,
            'setting order for mobile did not render the responsive order rule');
    }
}
