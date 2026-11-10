<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Models\Page;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The builder's "View page" link, between Preview and Save: the live address of what is being
 * edited, in a new tab. Saving clears the page cache, so after Save it shows the update.
 */
class BuilderViewLinkTest extends TestCase
{
    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'bvl-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function builder(Post $post): string
    {
        return $this->actingAs($this->administrator())->get("/admin/falcon-builder/{$post->id}")->assertOk()->getContent();
    }

    private function page(string $slug, string $status = 'published', ?int $parent = null): Page
    {
        return Page::create(['user_id' => 1, 'title' => ucfirst($slug), 'slug' => $slug, 'status' => $status,
            'lang_code' => 'en', 'content' => '[]', 'editor_type' => 'builder', 'parent_id' => $parent, 'published_at' => now()->subDay()]);
    }

    public function test_a_published_page_links_to_its_live_address_in_a_new_tab(): void
    {
        $about = $this->page('about');
        $team = $this->page('team', 'published', $about->id);

        $html = $this->builder($team);

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/about/team'), '#').'" target="_blank" rel="noopener" class="topbar-icon"#', $html);
        $this->assertStringContainsString('fa-external-link-alt', $html);
        // It sits between Preview and Save.
        $link = strpos($html, 'fa-external-link-alt');
        $this->assertTrue($link > strpos($html, 'title="Preview"') && $link < strpos($html, 'class="btn-save"'));
    }

    public function test_an_unpublished_page_shows_the_link_disabled(): void
    {
        $html = $this->builder($this->page('draft-page', 'draft'));

        $this->assertStringContainsString('Not published yet', $html);
        $this->assertStringNotContainsString('href="'.url('/draft-page').'" target="_blank"', $html);
    }

    public function test_a_layout_section_has_no_view_link(): void
    {
        $section = Post::create(['user_id' => 1, 'title' => 'Header', 'slug' => 'header-x', 'type' => 'falcon_header',
            'status' => 'published', 'lang_code' => 'en', 'content' => '[]', 'editor_type' => 'builder']);

        $this->assertStringNotContainsString('fa-external-link-alt', $this->builder($section));
    }
}
