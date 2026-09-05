<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * Where a copied node can be pasted.
 *
 * Paste used to require that the clipboard's context type equalled the destination's:
 * the type recorded was where the node had been right-clicked, not what it was. So an
 * element copied from a plain column could not go into a nested one, a column copied
 * inside a nested row could not go into a plain container, and a container copied on one
 * page could only be pasted by right-clicking another container on the next — which a
 * page with nothing on it yet does not have, leaving the copy with nowhere to go at all.
 *
 * pasteResolve() answers where the clipboard would land, and both the buttons and the
 * hint line under them read from it, so the menu can say what will happen instead of
 * only going grey. Its answers live in the builder's inline script, which PHPStan does
 * not read and PHPUnit cannot call, so they are lifted out and run in node.
 */
class ClipboardPasteTest extends TestCase
{
    /** One container with a column and a nested row, plus a container holding nothing. */
    private function layout(): array
    {
        return [
            [
                'id' => 'c0',
                'columns' => [
                    ['id' => 'c0k0', 'elements' => [
                        ['id' => 'e00', 'type' => 'title'],
                        ['id' => 'r0', 'type' => 'row', 'columns' => [
                            ['id' => 'n0', 'elements' => [['id' => 'ne0', 'type' => 'button']]],
                        ]],
                    ]],
                ],
            ],
            ['id' => 'c1', 'columns' => []],
        ];
    }

    /**
     * Ask where each clipboard would land for each context menu.
     *
     * @param  array<int, array{clip: array<string, mixed>|null, menu: array<string, mixed>}>  $cases
     * @return array<int, array<string, mixed>>
     */
    private function resolve(array $cases): array
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot run the paste helpers');
        }

        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $lifted = $this->liftConst($scripts, 'CLIP_KINDS')."\n"
            .$this->liftLine($scripts, 'clipKind')."\n"
            .$this->liftLine($scripts, '_at')."\n"
            .$this->liftConst($scripts, 'pasteResolve')."\n"
            .$this->liftLine($scripts, 'canPasteHere')."\n"
            .$this->liftConst($scripts, 'pasteHint')."\n";

        $dir = sys_get_temp_dir().'/fc-paste-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/run.js',
            'const CASES = '.json_encode($cases).";\n"
            .'const START = '.json_encode($this->layout()).";\n"
            ."const layout = { value: null };\n"
            ."const ctxClipboard = { value: null };\n"
            ."const ctxMenu = { value: null };\n"
            // The two computeds are Vue's; here they only have to read on demand.
            ."const computed = (fn) => ({ get value() { return fn(); } });\n"
            .$lifted
            ."const out = CASES.map(({ clip, menu }) => {\n"
            ."    layout.value = JSON.parse(JSON.stringify(START));\n"
            ."    ctxClipboard.value = clip;\n"
            ."    ctxMenu.value = menu;\n"
            ."    const r = pasteResolve(menu);\n"
            // A list is compared by identity, which JSON cannot carry, so it is reported
            // as the ids already in it — enough to say which list was chosen.
            ."    return {\n"
            ."        can: canPasteHere.value,\n"
            ."        hint: pasteHint.value,\n"
            ."        where: r.where || null,\n"
            ."        error: r.error || null,\n"
            ."        into: r.list ? r.list.map((n) => n.id) : null,\n"
            ."    };\n"
            ."});\n"
            .'process.stdout.write(JSON.stringify(out));'
        );

        $out = shell_exec(escapeshellarg($node).' '.escapeshellarg($dir.'/run.js').' 2>&1');
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $result = json_decode((string) $out, true);
        $this->assertIsArray($result, "the paste helpers did not run:\n".$out);

        return $result;
    }

    private function clip(string $type, string $elementType = 'title'): array
    {
        return ['type' => $type, 'data' => ['id' => 'copied', 'type' => $elementType]];
    }

    /**
     * A container goes on the page itself, so it can be pasted from anywhere — including
     * the bare canvas of a page that has nothing on it, which is the case that previously
     * had no node to right-click at all.
     */
    public function test_a_container_can_be_pasted_onto_an_empty_page(): void
    {
        [$canvas, $onElement] = $this->resolve([
            ['clip' => $this->clip('container'), 'menu' => ['type' => 'canvas']],
            ['clip' => $this->clip('container'), 'menu' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0]],
        ]);

        $this->assertTrue($canvas['can'], 'a container cannot be pasted onto the bare canvas');
        $this->assertSame(['c0', 'c1'], $canvas['into'], 'the container would not land on the page itself');

        // And from anywhere else, it still lands on the page rather than being refused
        // for having been right-clicked on the wrong kind of node.
        $this->assertTrue($onElement['can']);
        $this->assertSame(['c0', 'c1'], $onElement['into']);
    }

    /**
     * An element copied from a plain column goes into a nested one, and the reverse. The
     * old rule compared where each was right-clicked, so neither direction worked.
     */
    public function test_an_element_crosses_between_plain_and_nested_columns(): void
    {
        [$intoNested, $outOfNested] = $this->resolve([
            [
                'clip' => $this->clip('element'),
                'menu' => ['type' => 'nested-element', 'ci' => 0, 'coli' => 0, 'eli' => 1, 'ncoli' => 0, 'neli' => 0],
            ],
            [
                'clip' => $this->clip('nested-element'),
                'menu' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            ],
        ]);

        $this->assertTrue($intoNested['can']);
        $this->assertSame(['ne0'], $intoNested['into'], 'the element would not land in the nested column');

        $this->assertTrue($outOfNested['can']);
        $this->assertSame(['e00', 'r0'], $outOfNested['into'], 'the element would not land in the plain column');
    }

    /** A column reaches a nested row's columns and a plain container's alike. */
    public function test_a_column_reaches_both_kinds_of_parent(): void
    {
        [$intoNestedRow, $intoContainer, $intoEmptyContainer] = $this->resolve([
            [
                'clip' => $this->clip('column'),
                'menu' => ['type' => 'nested-column', 'ci' => 0, 'coli' => 0, 'eli' => 1, 'ncoli' => 0],
            ],
            [
                'clip' => $this->clip('nested-column'),
                'menu' => ['type' => 'column', 'ci' => 0, 'coli' => 0],
            ],
            [
                'clip' => $this->clip('column'),
                'menu' => ['type' => 'container', 'ci' => 1],
            ],
        ]);

        $this->assertSame(['n0'], $intoNestedRow['into'], 'a column cannot reach a nested row');
        $this->assertSame(['c0k0'], $intoContainer['into'], 'a nested column cannot come back out to a container');
        $this->assertSame([], $intoEmptyContainer['into'], 'a column cannot fill an empty container');
    }

    /**
     * A nested column renders elements, never another nested row: one saved there draws
     * as nothing at all. Refused, and with a reason — the alternative is a paste that
     * appears to work and produces an invisible element.
     */
    public function test_a_nested_row_cannot_be_pasted_into_a_nested_column(): void
    {
        [$r] = $this->resolve([[
            'clip' => $this->clip('element', 'row'),
            'menu' => ['type' => 'nested-element', 'ci' => 0, 'coli' => 0, 'eli' => 1, 'ncoli' => 0, 'neli' => 0],
        ]]);

        $this->assertFalse($r['can']);
        $this->assertNotNull($r['error'], 'the paste was refused without saying why');
        $this->assertStringContainsString('nested', (string) $r['error']);
    }

    /** With nothing copied there is nothing to offer, and the hint says so. */
    public function test_an_empty_clipboard_offers_nothing(): void
    {
        [$r] = $this->resolve([[
            'clip' => null,
            'menu' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
        ]]);

        $this->assertFalse($r['can']);
        $this->assertSame('Clipboard is empty', $r['hint']);
    }

    /**
     * The hint is the point of the change: a paste that cannot happen used to be a grey
     * button and no explanation. It says what lands where when it can, and why not when
     * it cannot.
     */
    public function test_the_hint_says_what_lands_where(): void
    {
        [$ok, $refused] = $this->resolve([
            [
                'clip' => $this->clip('element'),
                'menu' => ['type' => 'nested-element', 'ci' => 0, 'coli' => 0, 'eli' => 1, 'ncoli' => 0, 'neli' => 0],
            ],
            [
                'clip' => $this->clip('element', 'row'),
                'menu' => ['type' => 'nested-element', 'ci' => 0, 'coli' => 0, 'eli' => 1, 'ncoli' => 0, 'neli' => 0],
            ],
        ]);

        $this->assertStringContainsString('element', $ok['hint']);
        $this->assertStringContainsString('nested column', $ok['hint']);

        $this->assertNotSame('', $refused['hint'], 'a refused paste explains nothing');
        $this->assertSame($refused['error'], $refused['hint'],
            'the hint should carry the reason the paste was refused');
    }

    /**
     * A node that is no longer there — copied, then the row it pointed into was deleted —
     * is refused rather than throwing, which in a Vue computed takes the whole panel down.
     */
    public function test_a_destination_that_is_gone_is_refused_quietly(): void
    {
        $cases = $this->resolve([
            ['clip' => $this->clip('element'), 'menu' => ['type' => 'element', 'ci' => 9, 'coli' => 0, 'eli' => 0]],
            ['clip' => $this->clip('element'), 'menu' => ['type' => 'nested-element', 'ci' => 0, 'coli' => 0, 'eli' => 1, 'ncoli' => 7, 'neli' => 0]],
            ['clip' => $this->clip('column'), 'menu' => ['type' => 'nested-column', 'ci' => 0, 'coli' => 0, 'eli' => 0, 'ncoli' => 0]],
        ]);

        foreach ($cases as $i => $r) {
            $this->assertFalse($r['can'], "case {$i} offers a paste into something that is not there");
            $this->assertNull($r['into']);
        }
    }

    /** An unknown clipboard kind is refused rather than guessed at. */
    public function test_an_unknown_clipboard_kind_is_refused(): void
    {
        [$r] = $this->resolve([[
            'clip' => $this->clip('widget'),
            'menu' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
        ]]);

        $this->assertFalse($r['can']);
        $this->assertNull($r['into']);
    }

    /**
     * The menu itself has to read from the same answer, or the buttons and the hint can
     * disagree with what pressing them does.
     */
    public function test_the_menu_reads_from_the_resolver(): void
    {
        $menu = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/modals/context-menu.blade.php'
        );

        $this->assertStringContainsString('canPasteHere && ctxPaste(\'start\')', $menu);
        $this->assertStringContainsString('canPasteHere && ctxPaste(\'end\')', $menu);
        $this->assertStringContainsString('v-if="pasteHint"', $menu);

        // The old rule must be gone from the template, not merely unused.
        $this->assertStringNotContainsString('ctxClipboard.type === ctxMenu.type', $menu,
            'the menu still compares where things were right-clicked');

        // Editing and duplicating make no sense on the bare canvas; only paste does.
        $this->assertStringContainsString("v-if=\"ctxMenu.type !== 'canvas'\"", $menu,
            'right-clicking the canvas offers actions that have no node to act on');
    }

    /** And the canvas has to offer the menu at all, or an empty page still cannot be reached. */
    public function test_the_bare_canvas_opens_the_menu(): void
    {
        $canvas = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/canvas.blade.php'
        );

        $this->assertStringContainsString("@contextmenu.prevent=\"openCtxMenu(\$event, 'canvas')\"", $canvas,
            'right-clicking an empty page opens nothing, so a copied container has nowhere to go');
    }

    /**
     * ctxPaste() has to act on the resolver's answer rather than repeating the decision,
     * which is what left five near-identical branches to drift apart in the first place.
     */
    public function test_the_paste_action_uses_the_resolver(): void
    {
        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $start = strpos($scripts, 'const ctxPaste = ');
        $this->assertNotFalse($start, 'ctxPaste is gone');
        $body = substr($scripts, $start, (int) strpos($scripts, "\n".self::INDENT.'};', $start) - $start);

        $this->assertStringContainsString('pasteResolve(ctxMenu.value)', $body);
        $this->assertStringContainsString('r.list.unshift(copy)', $body);
        $this->assertStringContainsString('r.list.push(copy)', $body);
        $this->assertStringContainsString('showToast(r.error', $body,
            'a refused paste closes the menu without saying why');

        // Pasted nodes must not keep the ids they were copied from: two nodes sharing an
        // id make Vue reuse one of them and the page renders a duplicate of the wrong one.
        $this->assertStringContainsString('assignNewIds(copy)', $body,
            'the pasted copy keeps the original ids');

        // The old per-type branches are gone rather than left behind unreachable.
        $this->assertStringNotContainsString("m.type === 'nested-column'", $body,
            'ctxPaste still decides the destination for itself');
    }

    /**
     * Copying something too large for localStorage clears the key.
     *
     * It used to leave whatever was there before, so the next paste on another page
     * quietly produced an older copy instead of the one just made — the worst kind of
     * failure, because it succeeds.
     */
    public function test_a_copy_too_large_to_store_does_not_leave_an_older_one_behind(): void
    {
        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $this->assertStringContainsString('localStorage.removeItem(_CLIP_KEY)', $scripts,
            'a copy that could not be stored leaves the previous one in place');
    }

    private const INDENT = '            ';

    /** Lift one `const name = … };` from the builder's script — see TextAnimationTest. */
    private function liftConst(string $source, string $name): string
    {
        $open = "\n".self::INDENT.'const '.$name.' = ';
        $start = strpos($source, $open);
        $this->assertNotFalse($start, "the builder no longer defines {$name} where the test can find it");

        // Two shapes end these: a plain arrow function closes with `};`, one wrapped in
        // computed() with `});`. Whichever comes first is this definition's end — taking
        // only the first shape ran straight past a computed() and lifted the next
        // definition along with it.
        $end = false;
        $close = '';
        foreach (['};', '});'] as $terminator) {
            $at = strpos($source, "\n".self::INDENT.$terminator, $start);
            if ($at !== false && ($end === false || $at < $end)) {
                $end = $at;
                $close = $terminator;
            }
        }
        $this->assertNotFalse($end, "the definition of {$name} does not end where the test can see it");

        $lifted = substr($source, $start, $end - $start + strlen("\n".self::INDENT.$close));

        // A one-liner has no closing brace of its own, so this runs on to the end of
        // whatever is defined after it and lifts that too. In node that reads as a
        // duplicate declaration rather than as a mistake here, which is how it was found.
        $this->assertSame(1, substr_count($lifted, "\n".self::INDENT.'const '),
            "{$name} is written on one line; lift it with liftLine()");

        return $lifted;
    }

    /** The same, for a helper written on one line. */
    private function liftLine(string $source, string $name): string
    {
        $open = "\n".self::INDENT.'const '.$name.' = ';
        $start = strpos($source, $open);
        $this->assertNotFalse($start, "the builder no longer defines {$name} where the test can find it");

        return substr($source, $start, (int) strpos($source, "\n", $start + 1) - $start);
    }

    private function nodeBinary(): ?string
    {
        foreach (['node', 'node.exe'] as $bin) {
            $probe = shell_exec(escapeshellarg($bin).' -v 2>&1');
            if (is_string($probe) && preg_match('/^v\d+/', trim($probe))) {
                return $bin;
            }
        }

        return null;
    }
}
