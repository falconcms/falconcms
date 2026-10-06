<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Console\Commands\UpdateFalconCms;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\File;
use ReflectionMethod;

/**
 * falcon:update clears the shop templates earlier releases published into the parent theme.
 *
 * The shop's templates moved into the shop plugin, so the parent theme stopped shipping them
 * and its force-publish stopped refreshing the copies sites already had. Left alone, a site's
 * copy outranks the plugin's template and serves an old cart and checkout indefinitely.
 */
class UpdateRetiresShopTemplatesTest extends TestCase
{
    private string $parent;

    private string $child;

    private bool $parentExisted;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = resource_path('views/themes/falcon-theme');
        $this->child = resource_path('views/themes/test-retire-child');
        $this->parentExisted = is_dir($this->parent);
    }

    protected function tearDown(): void
    {
        if (!$this->parentExisted) {
            File::deleteDirectory($this->parent);
        }
        File::deleteDirectory($this->child);

        parent::tearDown();
    }

    private function write(string $file, string $contents = 'x'): void
    {
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $contents);
    }

    private function retire(): int
    {
        $m = new ReflectionMethod(UpdateFalconCms::class, 'removeRetiredShopTemplates');
        $m->setAccessible(true);

        return $m->invoke(new UpdateFalconCms);
    }

    public function test_the_parent_themes_old_shop_copies_go_and_everything_else_stays(): void
    {
        if ($this->parentExisted) {
            $this->markTestSkipped('a real falcon-theme is published here; not touching it');
        }

        foreach (['archive-product', 'single-product', 'ecommerce/cart', 'ecommerce/checkout', 'shop/cart'] as $old) {
            $this->write("{$this->parent}/{$old}.blade.php");
        }
        $this->write("{$this->parent}/page.blade.php", 'page');
        $this->write("{$this->parent}/ecommerce/gift-card.blade.php", 'mine');
        $this->write("{$this->child}/ecommerce/cart.blade.php", 'child cart');

        $this->assertSame(5, $this->retire());

        $this->assertFileDoesNotExist("{$this->parent}/archive-product.blade.php");
        $this->assertFileDoesNotExist("{$this->parent}/ecommerce/cart.blade.php");
        $this->assertDirectoryDoesNotExist("{$this->parent}/shop", 'an emptied folder goes too');
        $this->assertFileExists("{$this->parent}/page.blade.php");
        $this->assertFileExists("{$this->parent}/ecommerce/gift-card.blade.php", 'only files the theme used to ship are removed');
        $this->assertFileExists("{$this->child}/ecommerce/cart.blade.php", 'a child theme is never touched');

        $this->assertSame(0, $this->retire(), 'a second update has nothing left to do');
    }
}
