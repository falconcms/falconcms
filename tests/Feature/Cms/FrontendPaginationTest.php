<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Middleware\FrontendPagination;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * One pagination on every page of the site: the blog, archives, custom post types, the shop
 * and builder grids all render components/frontend/pagination, and so does a theme's or a
 * plugin's plain `->links()`. The admin keeps its own.
 */
class FrontendPaginationTest extends TestCase
{
    protected function tearDown(): void
    {
        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        parent::tearDown();
    }

    private function page(int $current, int $total = 300): LengthAwarePaginator
    {
        return new LengthAwarePaginator(range(1, 10), $total, 10, $current, ['path' => url('/blog')]);
    }

    /** A plain `->links()` while the request for $path is handled. */
    private function linksOn(string $path, int $current): string
    {
        return (new FrontendPagination)->handle(Request::create($path), fn () => $this->page($current)->links()->toHtml());
    }

    public function test_the_site_is_registered_for_every_web_page(): void
    {
        $this->assertContains(FrontendPagination::class, app('router')->getMiddlewareGroups()['web']);
    }

    public function test_a_plain_links_call_on_a_site_page_renders_the_site_pagination(): void
    {
        $html = $this->linksOn('/blog', 15);

        $this->assertStringContainsString('class="falcon-pagination"', $html);
        $this->assertStringContainsString('<li aria-current="page"><span>15</span></li>', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('&hellip;', $html, 'a long run of pages is shortened');
        $this->assertStringNotContainsString('Showing', $html, 'the page says how many results on its own');
    }

    public function test_the_first_and_last_pages_disable_the_way_out(): void
    {
        $first = $this->linksOn('/blog', 1);
        $this->assertStringContainsString('<li class="fpg-off" aria-disabled="true">', $first);
        $this->assertStringNotContainsString('rel="prev"', $first);

        $this->assertStringNotContainsString('rel="next"', $this->linksOn('/blog', 30));
    }

    public function test_the_admin_keeps_its_own_pagination(): void
    {
        $this->assertStringNotContainsString('falcon-pagination', $this->linksOn('/admin/posts', 2));
    }

    public function test_the_helper_renders_it_anywhere_and_nothing_for_one_page(): void
    {
        $this->assertStringContainsString('falcon-pagination', (string) the_falcon_pagination($this->page(2)));
        $this->assertSame('', trim((string) the_falcon_pagination($this->page(1, 5))));
    }

    public function test_the_shop_and_the_theme_render_it(): void
    {
        foreach (['plugins/falcon-shop/resources/views/frontend/archive-product.blade.php', 'resources/views/themes/falcon-theme/archive.blade.php',
            'resources/views/themes/falcon-theme/index.blade.php', 'resources/views/frontend/builder/elements/card.blade.php'] as $view) {
            $source = file_get_contents(dirname(__DIR__, 3).'/'.$view);
            $this->assertStringContainsString('the_falcon_pagination(', $source, $view);
            $this->assertStringNotContainsString('->links()', $source, $view);
        }
    }
}
