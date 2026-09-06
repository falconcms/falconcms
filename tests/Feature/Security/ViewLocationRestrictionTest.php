<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Http\Middleware\RedirectMiddleware;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * RedirectMiddleware refuses to render a view that lives under resources/views but
 * outside the directories a site is allowed to render from.
 *
 * Plugins moved into resources/views/plugins in v2.6.7 and were not on that list, so
 * every plugin view aborted mid-render — which took the front-end of any site with an
 * active plugin down with a 500. It only fired where resources/views/vendor exists:
 * realpath() returns false without it, and str_starts_with($path, false) compares
 * against '' and matches everything, silently switching the check off. That is why it
 * reached production unnoticed, so the fixture below creates that directory.
 */
class ViewLocationRestrictionTest extends TestCase
{
    /** @var list<string> */
    private array $made = [];

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(resource_path('views/vendor'));
    }

    protected function tearDown(): void
    {
        foreach ($this->made as $path) {
            File::delete($path);
        }
        parent::tearDown();
    }

    private function make(string $relative): string
    {
        $full = resource_path('views/'.$relative);
        File::ensureDirectoryExists(dirname($full));
        File::put($full, 'rendered');
        $this->made[] = $full;

        return $full;
    }

    private function renders(string $viewFile): bool
    {
        $middleware = new RedirectMiddleware;
        $middleware->handle(Request::create('/'), fn ($r) => response('ok'));

        try {
            return view()->file($viewFile)->render() === 'rendered';
        } catch (HttpException $e) {
            return false;
        }
    }

    public function test_a_plugin_view_renders(): void
    {
        $path = $this->make('plugins/demo-plugin/resources/views/frontend/render.blade.php');

        $this->assertTrue($this->renders($path), 'a plugin must be able to render its own views');
    }

    public function test_a_theme_view_renders(): void
    {
        $this->assertTrue($this->renders($this->make('themes/demo-theme/index.blade.php')));
    }

    public function test_a_published_vendor_view_renders(): void
    {
        $this->assertTrue($this->renders($this->make('vendor/falcon-cms/frontend/x.blade.php')));
    }

    public function test_a_loose_view_under_resources_views_is_still_refused(): void
    {
        $this->assertFalse(
            $this->renders($this->make('sneaky.blade.php')),
            'the restriction must still hold for everything outside themes, vendor and plugins'
        );
    }
}
