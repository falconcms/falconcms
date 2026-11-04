<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Word spaces that went missing: "Published:3 hours ago", "CATEGORY:Flagship Phones".
 *
 * - A builder layout lost every trailing space on save: Laravel trims each string of a
 *   request, and a layout arrives as nested JSON, so a Before text "Published: " was stored
 *   as "Published:".
 * - Minify HTML removed all whitespace between tags, the word space between an inline label
 *   and its value included ("<span>Category:</span> <a>Phones</a>").
 */
class WordSpacesTest extends TestCase
{
    use MakesShopFixtures;

    private function builderUser(): User
    {
        (new MenuSeeder)->run();
        $role = Role::create(['name' => 'Pages', 'slug' => 'pages-'.uniqid()]);
        $role->permissions()->sync(array_map(
            fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id,
            ['manage_pages', 'access_all_pages_pages', 'access_add_new_pages']
        ));

        return $this->makeUser(['role_id' => $role->id]);
    }

    public function test_a_builder_layout_is_saved_with_its_spaces(): void
    {
        $user = $this->builderUser();
        $page = Post::create(['user_id' => $user->id, 'title' => 'P', 'slug' => 'p-'.uniqid(), 'type' => 'page', 'status' => 'draft', 'lang_code' => 'en', 'content' => '[]']);
        $layout = ['layout' => [['id' => 'c', 'settings' => [], 'columns' => [['id' => 'k', 'settings' => [], 'elements' => [
            ['id' => 'e', 'type' => 'title', 'settings' => ['title' => '  Spaced  ', 'dynamic_source' => 'post_date', 'dynamic_before' => 'Published: ', 'dynamic_after' => ' ago']],
        ]]]]]];

        $this->actingAs($user)->postJson("/admin/falcon-builder/{$page->id}/save", $layout)->assertOk();

        $s = json_decode($page->fresh()->content, true)[0]['columns'][0]['elements'][0]['settings'];
        $this->assertSame('Published: ', $s['dynamic_before']);
        $this->assertSame(' ago', $s['dynamic_after']);
        $this->assertSame('  Spaced  ', $s['title']);
    }

    public function test_other_requests_are_still_trimmed(): void
    {
        $this->assertTrue(true === (new \ReflectionClass(\Illuminate\Foundation\Http\Middleware\TrimStrings::class))->hasMethod('skipWhen'));
        $trim = new \Illuminate\Foundation\Http\Middleware\TrimStrings;
        $seen = null;
        $trim->handle(Request::create('/admin/posts', 'POST', ['title' => '  Hello  ']), function ($r) use (&$seen) {
            $seen = $r->input('title');

            return new Response('');
        });
        $this->assertSame('Hello', $seen);

        $trim->handle(Request::create('/admin/falcon-builder/5/save', 'POST', ['x' => 'Published: ']), function ($r) use (&$seen) {
            $seen = $r->input('x');

            return new Response('');
        });
        $this->assertSame('Published: ', $seen);
    }

    private function minify(string $html): string
    {
        $this->setCmsOptions(['perf_minify_html' => '1']);

        return (new HtmlOptimizeMiddleware)->handle(Request::create('/x', 'GET'),
            fn () => new Response($html, 200, ['Content-Type' => 'text/html']))->getContent();
    }

    public function test_minify_keeps_the_space_between_inline_tags(): void
    {
        $out = $this->minify("<p><span class=\"l\">Category:</span>\n    <a href=\"/c\">Flagship Phones</a></p>");
        $this->assertStringContainsString('<span class="l">Category:</span> <a href="/c">Flagship Phones</a>', $out);

        $out = $this->minify('<p>Published: <time datetime="x">3 hours ago</time></p>');
        $this->assertStringContainsString('Published: <time', $out);

        $out = $this->minify("<span>View:</span>\n<b>1,234</b> <i>views</i>");
        $this->assertStringContainsString('<span>View:</span> <b>1,234</b> <i>views</i>', $out);
    }

    public function test_minify_still_removes_whitespace_around_blocks(): void
    {
        $out = $this->minify("<div>\n   <p>Hi</p>\n   <ul>\n <li><a>A</a></li>\n <li>B</li>\n</ul>\n</div>");
        $this->assertSame('<div><p>Hi</p><ul><li><a>A</a></li><li>B</li></ul></div>', $out);

        // A block on either side is enough.
        $this->assertSame('<span>a</span><div>b</div>', $this->minify("<span>a</span>\n  <div>b</div>"));
    }
}
