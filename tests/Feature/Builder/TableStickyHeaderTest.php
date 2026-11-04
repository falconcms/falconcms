<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * Table → "Header stays visible while scrolling".
 *
 * The header had `position: sticky` inside the table's horizontal-scroll box, which is a scroll
 * container: it could only stick inside that box, and without a max height the box never
 * scrolls — so on the page the header never stuck. Without a max height a script now keeps it
 * in view as the page scrolls; with one, CSS sticky in the box is kept.
 */
class TableStickyHeaderTest extends TestCase
{
    private function render(array $s): string
    {
        $rows = [['A', 'B'], ['1', '2'], ['3', '4']];

        return view('falcon-cms::frontend.builder.elements.table', ['el' => ['id' => 'ts', 'type' => 'table',
            'settings' => $s + ['rows' => $rows, 'headerRow' => true]]])->render();
    }

    public function test_without_a_max_height_the_header_follows_the_page(): void
    {
        $html = $this->render(['stickyHeader' => true]);
        $this->assertStringContainsString('data-fc-sticky-head', $html);
        $this->assertStringContainsString('window.__falconTableSticky', $html);
        $this->assertStringNotContainsString('position: sticky', $html, 'CSS sticky cannot follow the page from inside the scroll box');
    }

    public function test_with_a_max_height_css_sticky_works_in_the_box(): void
    {
        $html = $this->render(['stickyHeader' => true, 'maxHeight' => 300]);
        $this->assertStringContainsString('position: sticky; top: 0', $html);
        $this->assertStringNotContainsString('data-fc-sticky-head', $html);
        $this->assertStringNotContainsString('__falconTableSticky', $html);
    }

    public function test_off_means_neither(): void
    {
        $html = $this->render([]);
        $this->assertStringNotContainsString('position: sticky', $html);
        $this->assertStringNotContainsString('data-fc-sticky-head', $html);
    }
}
