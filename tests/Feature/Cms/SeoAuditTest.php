<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\SeoAudit;
use Illuminate\Support\Facades\DB;

/**
 * The site-wide SEO audit: each finding names the page and links to its editor; it only reads.
 */
class SeoAuditTest extends TestCase
{
    private function make(string $slug, array $attrs = []): Post
    {
        return Post::forceCreate($attrs + ['title' => ucfirst($slug), 'slug' => $slug, 'type' => 'post', 'status' => 'published',
            'content' => '<p>'.str_repeat('word ', 400).'</p>', 'lang_code' => 'en', 'featured_image' => 'f.jpg',
            'seo_meta' => ['description' => 'About '.$slug]]);
    }

    private function check(array $audit, string $key): ?array
    {
        return collect($audit['checks'])->firstWhere('key', $key);
    }

    public function test_findings_name_the_page_and_link_to_its_editor(): void
    {
        $a = $this->make('alpha', ['seo_meta' => []]);
        $this->make('beta', ['seo_meta' => ['title' => 'Same title', 'description' => 'x']]);
        $this->make('gamma', ['seo_meta' => ['title' => 'Same title', 'description' => 'y']]);
        $this->make('short', ['content' => '<p>Too short.</p><img src="/a.jpg">']);
        $this->make('long', ['seo_meta' => ['title' => str_repeat('Very long title ', 6), 'description' => 'z']]);
        $this->make('hidden', ['seo_meta' => ['noindex' => '1']]);

        $audit = SeoAudit::run();

        $missing = $this->check($audit, 'missing_description');
        $titles = array_column($missing['items'], 'title');
        $this->assertContains('Alpha', $titles);
        $this->assertNotContains('Beta', $titles);
        $alpha = collect($missing['items'])->firstWhere('title', 'Alpha');
        $this->assertSame(route('admin.posts.edit', $a->id), $alpha['edit']);
        $this->assertCount(2, $this->check($audit, 'duplicate_title')['items']);
        $this->assertSame(['Short'], array_column($this->check($audit, 'thin_content')['items'], 'title'));
        $this->assertSame(['Short'], array_column($this->check($audit, 'missing_alt')['items'], 'title'));
        $this->assertSame(['Long'], array_column($this->check($audit, 'long_title')['items'], 'title'));
        $this->assertSame(['Hidden'], array_column($this->check($audit, 'noindex')['items'], 'title'));
        $this->assertLessThan(100, $audit['score']);
    }

    public function test_a_page_linked_from_content_or_a_menu_is_not_an_orphan(): void
    {
        $this->make('linked-by-post');
        $this->make('linked-by-menu');
        $menuTarget = Post::where('slug', 'linked-by-menu')->first();
        $this->make('alone');
        $this->make('hub', ['content' => '<p>'.str_repeat('word ', 400).'</p><a href="/post/linked-by-post">read</a>']);
        // Builder JSON stores links as "linkUrl":"https:\/\/site\/post\/…".
        $this->make('json-linked');
        $this->make('builder-hub', ['content' => '{"sections":[{"linkUrl":"https:\/\/example.test\/post\/json-linked","text":"'.str_repeat('word ', 400).'"}]}']);
        $menu = DB::table('navigation_menus')->insertGetId(['name' => 'Main', 'location' => 'primary', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('navigation_menu_items')->insert(['navigation_menu_id' => $menu, 'title' => 'M', 'type' => 'post', 'object_id' => $menuTarget->id, 'created_at' => now(), 'updated_at' => now()]);

        $orphans = array_column($this->check(SeoAudit::run(), 'orphan')['items'], 'title');

        $this->assertContains('Alone', $orphans);
        $this->assertNotContains('Linked-by-post', $orphans);
        $this->assertNotContains('Linked-by-menu', $orphans);
        $this->assertNotContains('Json-linked', $orphans);
    }

    public function test_the_audit_page_renders_for_settings_managers(): void
    {
        $this->make('alpha', ['seo_meta' => []]);
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $this->actingAs($admin)->get('/admin/settings/seo-audit')
            ->assertOk()->assertSee('SEO Audit')->assertSee('Missing meta descriptions')->assertSee('Alpha');

        $this->assertSame(1, DB::table('posts')->where('slug', 'alpha')->count(), 'nothing is changed');
    }

    public function test_published_pages_with_nothing_on_them_are_flagged_as_soft_404s(): void
    {
        $this->make('empty-page', ['type' => 'page', 'content' => null]);
        $this->make('slider-only', ['type' => 'page', 'content' => '<p>[falcon_slider id="3"]</p>']);
        $home = $this->make('home', ['type' => 'page', 'content' => null]);
        $blog = $this->make('blog', ['type' => 'page', 'content' => null]);
        $this->setCmsOptions(['home_page_id' => (string) $home->id, 'blog_page_id' => (string) $blog->id]);

        $check = $this->check(SeoAudit::run(), 'empty_page');

        $this->assertSame('error', $check['severity']);
        $this->assertSame(['Empty-page'], array_column($check['items'], 'title'), 'a slider page, the home and the blog page are not empty');
    }
}
