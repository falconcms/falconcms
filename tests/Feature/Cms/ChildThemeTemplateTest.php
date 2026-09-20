<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Controllers\FrontendController;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * A child theme inherits its parent's templates.
 *
 * make:theme --child scaffolds only theme.json and functions.php and tells the developer it
 * "inherits every template from the parent" — but the view resolver asked for
 * themes.{child}.{view}, found nothing, and fell straight through to falcon-theme. Activating a
 * child therefore threw away the parent it was made from, and the only way to see the parent
 * again was to copy every template into the child by hand.
 */
class ChildThemeTemplateTest extends TestCase
{
    private string $parentDir;

    private string $childDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parentDir = resource_path('views/themes/test-parent');
        $this->childDir = resource_path('views/themes/test-child');

        File::ensureDirectoryExists($this->parentDir);
        File::ensureDirectoryExists($this->childDir);

        File::put($this->parentDir.'/theme.json', json_encode(['name' => 'Test Parent']));
        File::put($this->parentDir.'/page.blade.php', 'PARENT PAGE TEMPLATE');
        File::put($this->parentDir.'/single.blade.php', 'PARENT SINGLE TEMPLATE');

        File::put($this->childDir.'/theme.json', json_encode([
            'name' => 'Test Child',
            'parent' => 'test-parent',
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->parentDir);
        File::deleteDirectory($this->childDir);
        parent::tearDown();
    }

    private function activate(string $slug): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => 'active_theme'], ['value' => $slug]);
        cache()->flush();
    }

    /** Resolve a view name the way the frontend controller does. */
    private function resolve(string $view): string
    {
        $controller = new FrontendController;
        $method = (new \ReflectionClass($controller))->getMethod('resolveThemeView');
        $method->setAccessible(true);

        return $method->invoke($controller, $view);
    }

    public function test_a_child_theme_falls_back_to_its_parents_template(): void
    {
        $this->activate('test-child');

        $this->assertSame('themes.test-parent.page', $this->resolve('page'));
        $this->assertSame('themes.test-parent.single', $this->resolve('single'));
    }

    public function test_the_childs_own_template_wins_over_the_parents(): void
    {
        File::put($this->childDir.'/page.blade.php', 'CHILD PAGE TEMPLATE');
        $this->activate('test-child');

        $this->assertSame('themes.test-child.page', $this->resolve('page'));
        // …and only that one: single still comes from the parent.
        $this->assertSame('themes.test-parent.single', $this->resolve('single'));
    }

    public function test_a_theme_with_no_parent_is_unaffected(): void
    {
        $this->activate('test-parent');

        $this->assertSame('themes.test-parent.page', $this->resolve('page'));
    }

    public function test_a_template_neither_theme_has_still_falls_back_to_the_base_theme(): void
    {
        $this->activate('test-child');

        $this->assertSame('falcon-cms::themes.falcon-theme.404', $this->resolve('404'));
    }
}
