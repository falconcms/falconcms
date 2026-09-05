<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * Dragging a node in the navigator tree.
 *
 * The navigator draws the whole page as one tree, so it invites dragging across
 * branches. None of that worked: every branch of the handler demanded that the source
 * and destination share a parent and that their context types match exactly, so an
 * element could be reordered inside its own column and nowhere else. Reordering worked,
 * which is why the tree looked like it half-worked rather than like it was broken —
 * a drag that silently does nothing gives no reason to suspect the code.
 *
 * The logic lives in the builder's inline script, which PHPStan does not read and
 * PHPUnit cannot call. So it is lifted out and run in node against a layout of a known
 * shape. This does not prove the drag events are wired up — only a browser can — but it
 * does pin down every answer the wiring depends on, and those are what was wrong.
 */
class NavigatorDropTest extends TestCase
{
    /**
     * A page with two containers: the first holds two columns of two elements each and a
     * nested row; the second is empty of columns, which is the case that had nowhere to
     * drop at all.
     */
    private function layout(): array
    {
        return [
            [
                'id' => 'c0',
                'columns' => [
                    ['id' => 'c0k0', 'elements' => [
                        ['id' => 'e00', 'type' => 'title'],
                        ['id' => 'e01', 'type' => 'text_block'],
                    ]],
                    ['id' => 'c0k1', 'elements' => [
                        ['id' => 'e10', 'type' => 'image'],
                        ['id' => 'r0', 'type' => 'row', 'columns' => [
                            ['id' => 'n0', 'elements' => [
                                ['id' => 'ne0', 'type' => 'button'],
                            ]],
                            ['id' => 'n1', 'elements' => []],
                        ]],
                    ]],
                ],
            ],
            ['id' => 'c1', 'columns' => []],
        ];
    }

    /**
     * Run one drag and report what the layout looks like afterwards, as the ids in each
     * list — which is the whole of what a move is supposed to change.
     *
     * @param  array<int, array{src: array<string, mixed>, dst: array<string, mixed>}>  $drags
     * @return array<int, array<string, mixed>>
     */
    private function drag(array $drags): array
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot run the navigator helpers');
        }

        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $lifted = '';
        foreach (['NAV_KINDS', 'navSlot', 'navHolds', 'navDropTarget', 'navCanDrop'] as $name) {
            $lifted .= $this->liftConst($scripts, $name)."\n";
        }
        // The one-liners, which end on their own line rather than on a closing brace.
        foreach (['navKind', '_navAt', 'navNode'] as $name) {
            $lifted .= $this->liftLine($scripts, $name)."\n";
        }

        $dir = sys_get_temp_dir().'/fc-navdrop-'.getmypid();
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/run.js',
            'const DRAGS = '.json_encode($drags).";\n"
            .'const START = '.json_encode($this->layout()).";\n"
            ."const layout = { value: null };\n"
            ."const navDragSrc = { value: null };\n"
            ."const messages = [];\n"
            ."const showToast = (m) => messages.push(m);\n"
            .$lifted
            // navDrop() itself is a Vue handler taking loose arguments and calling
            // navDragEnd(); the move it performs is these four lines, kept in step with
            // the source by the assertions below rather than lifted with it.
            ."const perform = (src, dst) => {\n"
            ."    const from = navSlot(src);\n"
            ."    const target = navDropTarget(src, dst);\n"
            ."    if (!from || !target.list) return;\n"
            ."    const moved = from.list[from.index];\n"
            ."    if (!moved) return;\n"
            ."    if (target.list === from.list && target.index === from.index) return;\n"
            ."    const intoNested = (dst.type === 'nested-element')\n"
            ."        || (navKind(src.type) === 'element' && dst.type === 'nested-column');\n"
            ."    if (intoNested && moved.type === 'row') { showToast('nested row'); return; }\n"
            ."    if (navHolds(moved, target.list)) { showToast('into itself'); return; }\n"
            ."    from.list.splice(from.index, 1);\n"
            ."    target.list.splice(target.index, 0, moved);\n"
            ."};\n"
            ."const ids = (l) => l.map((c) => ({\n"
            ."    id: c.id,\n"
            ."    columns: (c.columns || []).map((k) => ({\n"
            ."        id: k.id,\n"
            ."        elements: (k.elements || []).map((e) => e.id),\n"
            ."        nested: (k.elements || []).map((e) => (e.columns || []).map((n) => ({\n"
            ."            id: n.id, elements: (n.elements || []).map((x) => x.id),\n"
            ."        }))),\n"
            ."    })),\n"
            ."}));\n"
            ."const out = DRAGS.map(({ src, dst }) => {\n"
            ."    layout.value = JSON.parse(JSON.stringify(START));\n"
            ."    navDragSrc.value = src;\n"
            ."    messages.length = 0;\n"
            ."    const allowed = navCanDrop(dst.type, dst.ci, dst.coli, dst.eli, dst.ncoli, dst.neli);\n"
            ."    perform(src, dst);\n"
            ."    return { allowed, after: ids(layout.value), messages: messages.slice() };\n"
            ."});\n"
            .'process.stdout.write(JSON.stringify(out));'
        );

        $out = shell_exec(escapeshellarg($node).' '.escapeshellarg($dir.'/run.js').' 2>&1');
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $result = json_decode((string) $out, true);
        $this->assertIsArray($result, "the navigator helpers did not run:\n".$out);

        return $result;
    }

    /** The ids in one column, after a drag. */
    private function elements(array $after, int $ci, int $coli): array
    {
        return $after[$ci]['columns'][$coli]['elements'];
    }

    /**
     * The move that never worked: an element from one column into another.
     *
     * The old handler required src.coli === coli, so this was dropped on the floor
     * without a word — the single most obvious thing to try in a tree view.
     */
    public function test_an_element_moves_between_columns(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 1, 'eli' => 0],
        ]]);

        $this->assertTrue($r['allowed']);
        $this->assertSame(['e01'], $this->elements($r['after'], 0, 0), 'the element did not leave its column');
        $this->assertSame(['e00', 'e10', 'r0'], $this->elements($r['after'], 0, 1),
            'the element did not land where it was dropped');
    }

    /** Reordering inside one column is the case that always worked, and still does. */
    public function test_reordering_inside_a_column_still_works(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 1],
            'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
        ]]);

        $this->assertSame(['e01', 'e00'], $this->elements($r['after'], 0, 0));
    }

    /**
     * Dropping onto the parent kind means "go inside", which is the only way to reach a
     * branch that has nothing in it yet — a column with no elements has no element row to
     * aim at, so without this it can never be filled from the tree.
     */
    public function test_an_element_dropped_on_a_column_goes_inside_it(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            'dst' => ['type' => 'nested-column', 'ci' => 0, 'coli' => 1, 'eli' => 1, 'ncoli' => 1],
        ]]);

        $this->assertTrue($r['allowed']);
        $this->assertSame(['e01'], $this->elements($r['after'], 0, 0));

        // In that column, element 1 is the nested row and its column 1 is the empty one.
        $arrived = $r['after'][0]['columns'][1]['nested'][1][1]['elements'];
        $this->assertSame(['e00'], $arrived, 'the element never arrived in the empty nested column');
    }

    /** A column into a container that has none. */
    public function test_a_column_moves_into_an_empty_container(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'column', 'ci' => 0, 'coli' => 0],
            'dst' => ['type' => 'container', 'ci' => 1],
        ]]);

        $this->assertTrue($r['allowed']);
        $this->assertCount(1, $r['after'][0]['columns'], 'the column did not leave the first container');
        $this->assertSame('c0k0', $r['after'][1]['columns'][0]['id'],
            'the column did not arrive in the empty container');
    }

    /** Containers reorder among themselves. */
    public function test_containers_reorder(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'container', 'ci' => 1],
            'dst' => ['type' => 'container', 'ci' => 0],
        ]]);

        $this->assertSame(['c1', 'c0'], array_column($r['after'], 'id'));
    }

    /** A node dropped on itself changes nothing, and says nothing either. */
    public function test_dropping_a_node_on_itself_does_nothing(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
        ]]);

        $this->assertFalse($r['allowed'], 'the tree offers a drop that would do nothing');
        $this->assertSame(['e00', 'e01'], $this->elements($r['after'], 0, 0));
        $this->assertSame([], $r['messages'], 'a no-op drag should not complain');
    }

    /**
     * A container dropped into one of its own columns would detach the branch from the
     * page: the layout still holds it, through the node that is no longer in the layout.
     * Everything under it disappears from the editor and from the site.
     */
    public function test_a_node_cannot_be_dropped_inside_itself(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'column', 'ci' => 0, 'coli' => 1],
            'dst' => ['type' => 'nested-column', 'ci' => 0, 'coli' => 1, 'eli' => 1, 'ncoli' => 0],
        ]]);

        $this->assertFalse($r['allowed'], 'the tree offers a drop that would detach the branch');
        $this->assertCount(2, $r['after'][0]['columns'], 'the column moved inside itself');
    }

    /**
     * A nested column renders elements, never another nested row. One saved there draws
     * as nothing at all, so it is refused with a reason rather than accepted and lost.
     */
    public function test_a_nested_row_cannot_go_into_a_nested_column(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 1, 'eli' => 1],
            'dst' => ['type' => 'nested-element', 'ci' => 0, 'coli' => 1, 'eli' => 1, 'ncoli' => 0, 'neli' => 0],
        ]]);

        $this->assertFalse($r['allowed']);
        $this->assertSame(['e10', 'r0'], $this->elements($r['after'], 0, 1), 'the nested row moved anyway');
        $this->assertNotSame([], $r['messages'], 'the drag was refused without saying why');
    }

    /** A pairing with no sensible answer is refused rather than guessed at. */
    public function test_an_element_dropped_on_a_container_does_nothing(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            'dst' => ['type' => 'container', 'ci' => 1],
        ]]);

        $this->assertFalse($r['allowed']);
        $this->assertSame(['e00', 'e01'], $this->elements($r['after'], 0, 0));
    }

    /** A stale drag — the node was deleted while it was being dragged — moves nothing. */
    public function test_a_drag_from_somewhere_that_is_gone_moves_nothing(): void
    {
        [$r] = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 9],
            'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 1, 'eli' => 0],
        ]]);

        $this->assertFalse($r['allowed']);
        $this->assertSame(['e00', 'e01'], $this->elements($r['after'], 0, 0));
        $this->assertSame(['e10', 'r0'], $this->elements($r['after'], 0, 1));
    }

    /**
     * navCanDrop() draws the drop line, so it has to answer exactly what the drop will
     * do — a line where nothing happens is worse than no line, because it says the move
     * was made.
     */
    public function test_the_drop_line_appears_only_where_the_drop_happens(): void
    {
        $results = $this->drag([
            // Allowed, and each one moves something.
            [
                'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
                'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 1, 'eli' => 0],
            ],
            [
                'src' => ['type' => 'column', 'ci' => 0, 'coli' => 0],
                'dst' => ['type' => 'container', 'ci' => 1],
            ],
            // Refused, and each one leaves the page as it was.
            [
                'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
                'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            ],
            [
                'src' => ['type' => 'element', 'ci' => 0, 'coli' => 1, 'eli' => 1],
                'dst' => ['type' => 'nested-column', 'ci' => 0, 'coli' => 1, 'eli' => 1, 'ncoli' => 0],
            ],
        ]);

        $before = $this->drag([[
            'src' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
            'dst' => ['type' => 'element', 'ci' => 0, 'coli' => 0, 'eli' => 0],
        ]])[0]['after'];

        foreach ($results as $i => $r) {
            $moved = $r['after'] !== $before;
            $this->assertSame($r['allowed'], $moved,
                "case {$i}: the drop line and what the drop actually does disagree");
        }
    }

    /**
     * The move above is a copy of navDrop()'s body, because navDrop() is a Vue handler
     * that ends by calling navDragEnd() and cannot be lifted on its own. A copy can drift
     * from what it copies, and a drifted copy would keep passing while the builder was
     * broken — so the real handler is checked for the same decisions, in the same order.
     */
    public function test_the_real_handler_still_makes_these_decisions(): void
    {
        $scripts = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $start = strpos($scripts, 'const navDrop = ');
        $this->assertNotFalse($start, 'navDrop is gone');
        $body = substr($scripts, $start, (int) strpos($scripts, "\n".self::INDENT.'};', $start) - $start);

        foreach ([
            'navSlot(src)' => 'the handler no longer asks where the dragged node lives',
            'navDropTarget(src, dst)' => 'the handler no longer asks where the drop lands',
            'target.list === from.list && target.index === from.index' => 'a drop on itself is no longer a no-op',
            "moved.type === 'row'" => 'a nested row can be dropped into a nested column again',
            'navHolds(moved, target.list)' => 'a node can be dropped inside itself again',
            'from.list.splice(from.index, 1)' => 'the handler no longer removes the node from where it was',
            'target.list.splice(target.index, 0, moved)' => 'the handler no longer inserts the node where it lands',
        ] as $needle => $why) {
            $this->assertStringContainsString($needle, $body, $why);
        }

        // Removing before inserting matters when both are the same list: inserting first
        // shifts the index the removal then uses, and the wrong node disappears.
        $this->assertLessThan(
            strpos($body, 'target.list.splice'),
            strpos($body, 'from.list.splice'),
            'the node is inserted before it is removed, which corrupts a move within one list'
        );
    }

    /**
     * Lift one `const name = (…) => { … };` out of the builder's script, bounded by
     * indentation — see TextAnimationTest for why not by counting brackets.
     */
    private const INDENT = '            ';

    private function liftConst(string $source, string $name): string
    {
        $open = "\n".self::INDENT.'const '.$name.' = ';
        $start = strpos($source, $open);
        $this->assertNotFalse($start, "the builder no longer defines {$name} where the test can find it");

        // Two shapes end these: a plain arrow function closes with `};`, one wrapped in
        // computed() with `});`. Whichever comes first is this definition's end.
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

        $end = strpos($source, "\n", $start + 1);

        return substr($source, $start, $end - $start);
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
