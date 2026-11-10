<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Page;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\Breadcrumbs;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Parent pages: a page can sit under another one, and then lives at /parent-page/child-page.
 *
 * The column was always there (posts.parent_id) and the editor even validated it, but there
 * was no box to choose a parent, the model dropped the value on save ('post_parent' was in
 * $fillable instead), the permalink ignored it and the router answered /parent/child with a
 * 404, reading "parent" as a post type. These tests pin each of those down.
 */
class ParentPageTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'pp-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function page(string $slug, ?int $parentId = null, string $status = 'published'): Page
    {
        return Page::create([
            'user_id' => 1, 'title' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'status' => $status,
            'lang_code' => 'en', 'content' => '<p>Body of '.$slug.'</p>', 'editor_type' => 'rich',
            'parent_id' => $parentId, 'published_at' => now()->subDay(),
        ]);
    }

    private function path(string $url): string
    {
        return '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');
    }

    public function test_the_parent_is_saved_and_the_permalink_follows_it(): void
    {
        $about = $this->page('about');
        $team = $this->page('team', $about->id);
        $lead = $this->page('leadership', $team->id);

        $this->assertSame($about->id, (int) $team->fresh()->parent_id, 'parent_id survives mass assignment');
        $this->assertSame('/about', $this->path(get_falcon_permalink($about)));
        $this->assertSame('/about/team', $this->path(get_falcon_permalink($team->fresh())));
        $this->assertSame('/about/team/leadership', $this->path(get_falcon_permalink($lead->fresh())));
    }

    public function test_a_child_page_answers_at_its_nested_address(): void
    {
        $about = $this->page('about');
        $team = $this->page('team', $about->id);
        $this->page('leadership', $team->id);

        $this->get('/about/team')->assertOk()->assertSee('Body of team', false);
        $this->get('/about/team/leadership')->assertOk()->assertSee('Body of leadership', false);
        $this->get('/about')->assertOk()->assertSee('Body of about', false);
    }

    public function test_old_and_wrong_addresses_redirect_to_the_real_one(): void
    {
        $about = $this->page('about');
        $this->page('team', $about->id);

        $this->get('/team')->assertRedirect(url('/about/team'))->assertStatus(301);
        $this->get('/team?ref=x')->assertRedirect(url('/about/team').'?ref=x');
        $this->get('/somewhere-else/team')->assertRedirect(url('/about/team'))->assertStatus(301);
        $this->get('/about/nothing-here')->assertNotFound();
    }

    public function test_renaming_the_parent_moves_the_child_and_keeps_old_links_alive(): void
    {
        $about = $this->page('about');
        $this->page('team', $about->id);

        $about->update(['slug' => 'company']);

        $this->get('/company/team')->assertOk()->assertSee('Body of team', false);
        $this->get('/about/team')->assertRedirect(url('/company/team'));
    }

    public function test_a_page_without_a_parent_is_unchanged(): void
    {
        $this->page('contact');

        $this->assertSame('/contact', $this->path(get_falcon_permalink(Page::where('slug', 'contact')->first())));
        $this->get('/contact')->assertOk()->assertSee('Body of contact', false);
    }

    public function test_the_editor_offers_a_parent_box_without_the_page_or_its_children(): void
    {
        $about = $this->page('about');
        $team = $this->page('team', $about->id);
        $lead = $this->page('leadership', $team->id);
        $this->page('contact');

        $html = $this->actingAs($this->administrator())->get("/admin/pages/{$team->id}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('name="parent_id"', $html);
        $this->assertStringContainsString('Page Attributes', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$about->id.'" data-path="about"\s+selected/', $html, 'the current parent is chosen');
        $this->assertStringNotContainsString('value="'.$team->id.'"', $html, 'not itself');
        $this->assertStringNotContainsString('value="'.$lead->id.'"', $html, 'not its own sub-page');
        // The box sits between Publish and Featured image.
        $this->assertTrue(strpos($html, 'Page Attributes') > strpos($html, '<span>Publish</span>')
            && strpos($html, 'Page Attributes') < strpos($html, '<span>Featured image</span>'));
        // The new page screen has it too.
        $this->actingAs($this->administrator())->get('/admin/pages/create')->assertOk()->assertSee('name="parent_id"', false);
    }

    public function test_the_editor_saves_a_parent_and_refuses_a_loop(): void
    {
        $about = $this->page('about');
        $team = $this->page('team');
        $admin = $this->administrator();
        $payload = fn (Page $p, $parent) => ['title' => $p->title, 'slug' => $p->slug, 'status' => 'published', 'content' => $p->content, 'editor_type' => 'rich', 'parent_id' => $parent];

        $this->actingAs($admin)->put("/admin/pages/{$team->id}", $payload($team, $about->id))->assertRedirect();
        $this->assertSame($about->id, (int) $team->fresh()->parent_id);

        // about under team, while team is already under about: a loop.
        $this->actingAs($admin)->put("/admin/pages/{$about->id}", $payload($about, $team->id))->assertSessionHasErrors('parent_id');
        $this->assertNull($about->fresh()->parent_id);
        // A page cannot be its own parent either.
        $this->actingAs($admin)->put("/admin/pages/{$about->id}", $payload($about, $about->id))->assertSessionHasErrors('parent_id');

        // Clearing the parent puts the page back at the top.
        $this->actingAs($admin)->put("/admin/pages/{$team->id}", $payload($team->fresh(), ''))->assertRedirect();
        $this->assertNull($team->fresh()->parent_id);

        // A new page can be created straight under a parent.
        $this->actingAs($admin)->post('/admin/pages', ['title' => 'History', 'status' => 'published', 'content' => 'x', 'parent_id' => $about->id])->assertRedirect();
        $this->assertSame('/about/history', $this->path(get_falcon_permalink(Page::where('slug', 'history')->first())));
    }

    public function test_a_post_cannot_be_chosen_as_a_parent(): void
    {
        $post = Post::create(['user_id' => 1, 'title' => 'News', 'slug' => 'news', 'type' => 'post', 'status' => 'published', 'lang_code' => 'en', 'content' => 'x']);
        $team = $this->page('team');

        $this->actingAs($this->administrator())->put("/admin/pages/{$team->id}", ['title' => 'Team', 'slug' => 'team', 'status' => 'published', 'content' => 'x', 'editor_type' => 'rich', 'parent_id' => $post->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_breadcrumbs_list_the_parents(): void
    {
        $about = $this->page('about');
        $team = $this->page('team', $about->id);

        $this->get('/about/team')->assertOk();
        $titles = array_column(Breadcrumbs::trail($team->fresh()), 'title');

        $this->assertSame(['Home', 'About', 'Team'], $titles);
    }

    public function test_a_broken_parent_chain_never_takes_the_page_down(): void
    {
        $a = $this->page('a');
        $b = $this->page('b', $a->id);
        // A loop saved by hand in the database: a under b, b under a.
        DB::table('posts')->where('id', $a->id)->update(['parent_id' => $b->id]);
        app()->forgetInstance('falcon.memo.page_parents');

        $this->assertSame('/a/b', $this->path(get_falcon_permalink(Page::find($b->id))), 'the chain stops where it would repeat');
        $this->get('/a/b')->assertOk()->assertSee('Body of b', false);

        // A parent that was deleted simply drops out of the address.
        $c = $this->page('c', $b->id);
        Page::find($b->id)->delete();
        app()->forgetInstance('falcon.memo.page_parents');
        $this->assertSame('/c', $this->path(get_falcon_permalink(Page::find($c->id))));
    }
}
