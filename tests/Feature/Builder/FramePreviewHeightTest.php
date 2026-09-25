<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * The header, title bar and footer previews at the top of the page builder.
 *
 * Each is an iframe of the real thing, and an iframe has no height of its own — something has
 * to measure the content and set one. Two things did, and they measured different quantities,
 * so they spent the first ten seconds in the builder disagreeing: the canvas visibly jumped up
 * and down while they argued.
 *
 * The frame reported `body.getBoundingClientRect().height`, its content's real height. The
 * builder used `Math.max(body.scrollHeight, documentElement.scrollHeight)`, and the second of
 * those is the trap: the root element's scrolling box IS the viewport, so that number can
 * never come back smaller than the frame already is. It could only grow the frame; the frame's
 * own message could shrink it. With a header whose menu hangs a dropdown outside the body box
 * — body 76px tall, scrollHeight 120 — the two settled on nothing:
 *
 *     90 -> 76 -> 120 -> 76 -> 120 -> 76 -> …      51 changes in ten seconds
 *
 * A 400ms poll drove it, twenty-five times, whatever happened. Nothing here runs JavaScript;
 * it reads both files and holds them to the three things that stopped the argument.
 */
class FramePreviewHeightTest extends TestCase
{
    private const BUILDER = __DIR__.'/../../../resources/views/admin/falcon-builder/index.blade.php';

    private const FRAME = __DIR__.'/../../../resources/views/themes/falcon-theme/layouts/app.blade.php';

    private function read(string $path): string
    {
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_both_sides_measure_the_same_thing(): void
    {
        // One quantity, named the same way in both files, is the whole fix.
        foreach ([self::BUILDER, self::FRAME] as $path) {
            $this->assertStringContainsString('body.getBoundingClientRect().height', $this->read($path),
                basename($path).' measures the frame some other way than its content height');
        }
    }

    public function test_the_builder_no_longer_asks_the_root_element_how_tall_it_is(): void
    {
        // documentElement.scrollHeight is floored at the viewport, so it can only ever grow
        // the frame — a ratchet, fighting a message that could shrink it.
        // Named exactly, so the comment above it in that file explaining the trap does not
        // count as the trap.
        $this->assertStringNotContainsString(
            'Math.max(d.body.scrollHeight, d.documentElement.scrollHeight)', $this->read(self::BUILDER),
            'the height ratchet is back');
    }

    public function test_neither_side_writes_a_height_that_has_not_changed(): void
    {
        // The frame's observer watches the body, and the height the builder writes is what
        // resizes that body. Without a guard on each side, that is a conversation with no end.
        $builder = $this->read(self::BUILDER);
        $this->assertMatchesRegularExpression('/function apply\(f, h\)[^}]*Math\.abs/s', $builder,
            'the builder applies a height without checking whether it is a change');
        $this->assertStringContainsString('.forEach(function (f) { apply(f, d.height); })', $builder,
            'the message handler bypasses the guard and writes the style directly');

        $this->assertMatchesRegularExpression('/Math\.abs\(h - last\) <= 1/', $this->read(self::FRAME),
            'the frame reports a height it has already reported');
    }

    public function test_the_poll_stops_once_nothing_is_moving(): void
    {
        // It is a safety net for a message that never arrives, not a ten-second schedule.
        $builder = $this->read(self::BUILDER);

        $this->assertStringContainsString('settled >= 3', $builder,
            'the poll runs its full course whatever happens, which is what made the jumping last');
        $this->assertStringNotContainsString('if (++n > 25) clearInterval(iv)', $builder,
            'the old fixed-length poll is back');
    }

    public function test_a_burst_of_resizes_is_reported_once(): void
    {
        $this->assertStringContainsString('requestAnimationFrame', $this->read(self::FRAME),
            'every step of a reflow is announced separately instead of the result of it');
    }

    public function test_the_builder_page_still_renders(): void
    {
        // The edits are inside <script> in a Blade file; a stray brace would take the whole
        // page down rather than just the previews.
        $compiler = new BladeCompiler(
            app('files'), storage_path('framework/views')
        );

        foreach ([self::BUILDER, self::FRAME] as $path) {
            $compiler->compile($path);
            $this->assertTrue(true, basename($path).' compiles');
        }
    }
}
