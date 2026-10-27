<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The places a plugin can extend the builder's own elements, containers and columns (Pro's
 * Scroll Effects is the first): their markup on the page, their Extra tab, and their wrapper in
 * the canvas.
 */
class BuilderExtensionHooksTest extends TestCase
{
    private function container(array $elements): string
    {
        return view('falcon-cms::frontend.builder.container', ['container' => [
            'id' => 'x1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'settings' => [], 'elements' => $elements]],
        ]])->render();
    }

    private function title(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'title', 'settings' => ['title' => $text, 'htmlTag' => 'h2', 'titleColor' => '#111827']];
    }

    public function test_a_plugin_can_rework_an_elements_html_on_the_page(): void
    {
        $seen = [];
        add_falcon_filter('falcon_builder_element_html', function ($html, $el) use (&$seen) {
            $seen[] = $el['id'];

            return $el['id'] === 'h1' ? '<div class="wrapped">'.$html.'</div>' : $html;
        });

        $html = $this->container([$this->title('h1', 'First'), $this->title('h2', 'Second')]);

        $this->assertSame(['h1', 'h2'], $seen, 'every element, with its own settings');
        $this->assertMatchesRegularExpression('/<div class="wrapped">.*First.*<\/div>/s', $html);
        $this->assertStringContainsString('Second', $html);
        $this->assertSame(1, substr_count($html, 'class="wrapped"'));
    }

    public function test_without_a_filter_the_page_is_as_before(): void
    {
        $html = $this->container([$this->title('h1', 'Plain heading')]);

        $this->assertStringContainsString('Plain heading', $html);
        $this->assertFalse(has_falcon_filter('falcon_builder_element_html'));
    }

    public function test_a_plugin_can_add_attributes_to_containers_and_columns(): void
    {
        $seen = [];
        add_falcon_filter('falcon_builder_node_attributes', function ($attrs, $node, $kind) use (&$seen) {
            $seen[] = $kind.':'.$node['id'];

            return $attrs.' data-test-'.$kind.'="'.e($node['id']).'"';
        });

        $html = $this->container([$this->title('h1', 'Inside')]);

        $this->assertSame(['container:x1', 'column:c1'], $seen);
        $this->assertMatchesRegularExpression('/<\w+ id="[^"]*" class="lazy-container[^>]*data-test-container="x1">/', $html);
        $this->assertMatchesRegularExpression('/class="lazy-column [^>]*data-test-column="c1">/s', $html);
    }

    public function test_the_builder_offers_plugins_the_extra_tab_and_the_canvas(): void
    {
        $views = __DIR__.'/../../../resources/views/admin/falcon-builder';

        $this->assertStringContainsString("do_falcon_action('falcon_builder_element_extras')", (string) file_get_contents($views.'/partials/sidebar.blade.php'));
        $index = (string) file_get_contents($views.'/index.blade.php');
        $this->assertLessThan(strpos($index, "partials.scripts')"), strpos($index, "do_falcon_action('falcon_builder_assets')"), 'before the app mounts');
        $this->assertStringContainsString('v-bind="fcCanvasAttrs(el)"', (string) file_get_contents($views.'/partials/components/column/col.blade.php'));
        $this->assertStringContainsString('v-bind="fcCanvasAttrs(nestedEl)"', (string) file_get_contents($views.'/partials/components/nested/row.blade.php'));
        $this->assertStringContainsString("v-bind=\"fcCanvasAttrs(ncol, 'column')\"", (string) file_get_contents($views.'/partials/components/nested/row.blade.php'));
        $this->assertStringContainsString("v-bind=\"fcCanvasAttrs(column, 'column')\"", (string) file_get_contents($views.'/partials/components/column/col.blade.php'));
        $this->assertStringContainsString("v-bind=\"fcCanvasAttrs(container, 'container')\"", (string) file_get_contents($views.'/partials/components/container/row.blade.php'));
        $this->assertStringContainsString("do_falcon_action('falcon_builder_container_extras', \$base)", (string) file_get_contents($views.'/partials/components/container/edit-panel.blade.php'));
        $this->assertStringContainsString("do_falcon_action('falcon_builder_column_extras', 'editingColumn')", (string) file_get_contents($views.'/partials/components/column/edit-panel.blade.php'));
        $scripts = (string) file_get_contents($views.'/partials/scripts.blade.php');
        $this->assertStringContainsString('for (const fn of (window.falconBuilderCanvas || []))', $scripts);
        $this->assertStringContainsString('if (out.style && a.style) a.style = Object.assign({}, out.style, a.style);', $scripts, 'two plugins on one wrapper add up');
        $this->assertStringContainsString('elementLocked, isElementPro, fcCanvasAttrs,', $scripts);
    }
}
