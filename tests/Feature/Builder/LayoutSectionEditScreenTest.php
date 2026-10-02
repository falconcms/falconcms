<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * A Layout section — header, footer, page title bar, content — opens on the same edit screen
 * as a page before the builder: title, a rich editor showing the builder's shortcodes, and the
 * Page Builder. What only a public page has (permalink, SEO, excerpt, featured image, trash)
 * is left out, and a Layout box says where the section is used.
 */
class LayoutSectionEditScreenTest extends TestCase
{
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withProLicensed(); // creating a section is a Layout Builder (Pro) write
    }

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function layout(): string
    {
        // Shaped as the builder saves it — ids on the container, column and element.
        return json_encode([[
            'id' => 'c1', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [
                ['id' => 1, 'type' => 'heading', 'settings' => ['title' => 'Site Header Text', 'tag' => 'h3']],
            ]]],
        ]]);
    }

    private function createSection(string $slot = 'header', string $name = 'Main Header'): Post
    {
        $res = $this->actingAs($this->administrator())
            ->postJson('/admin/falcon-builder-sections/section', ['slot' => $slot, 'name' => $name])
            ->assertOk();
        $section = Post::findOrFail($res->json('section.id'));
        $this->assertSame(route('admin.posts.edit', $section->id), $res->json('section.edit_url'), 'Layouts opens the edit screen, not the builder');
        $section->update(['content' => $this->layout()]);

        return $section;
    }

    public function test_a_section_opens_like_a_page(): void
    {
        $section = $this->createSection();

        $html = $this->actingAs($this->administrator())->get('/admin/posts/'.$section->id.'/edit')
            ->assertOk()
            ->assertSee('Edit Header Section', false)
            ->assertSee('Rich Editor', false)
            ->assertSee('Page Builder', false)
            ->assertSee(route('admin.falcon-builder', $section->id), false)
            ->assertSee('Back to Layouts', false)
            ->assertSee('Global Layout', false)
            ->getContent();

        // The rich editor shows the layout as shortcodes, exactly as a page's does.
        $this->assertStringContainsString(e(BuilderShortcodeConverter::jsonToShortcodes($this->layout())), $html);

        foreach (['id="permalink-container"', 'SEO Settings', 'Move to Trash', 'id="fi-path-hidden"', 'name="excerpt"'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent.' has no place on a section');
        }
    }

    public function test_saving_from_the_edit_screen_keeps_the_section_a_builder_layout(): void
    {
        $section = $this->createSection('footer', 'Footer');
        $shortcodes = BuilderShortcodeConverter::jsonToShortcodes($this->layout());

        $this->actingAs($this->administrator())
            ->from('/admin/posts/'.$section->id.'/edit')
            ->put('/admin/posts/'.$section->id, [
                'title' => 'Site Footer', 'type' => 'falcon_footer', 'status' => 'published',
                'editor_type' => 'builder', 'content' => $shortcodes,
            ])
            ->assertRedirect('/admin/posts/'.$section->id.'/edit')
            ->assertSessionHas('success', 'Footer section updated successfully.');

        $fresh = $section->fresh();
        $this->assertSame('Site Footer', $fresh->title);
        $this->assertSame('falcon_footer', $fresh->type);
        $this->assertSame('Site Header Text', json_decode($fresh->content, true)[0]['columns'][0]['elements'][0]['settings']['title'] ?? null,
            'the shortcodes came back as builder JSON');
    }

    public function test_the_builder_returns_to_the_section_edit_screen(): void
    {
        $section = $this->createSection('page_title_bar', 'Title Bar');

        $this->actingAs($this->administrator())->get('/admin/falcon-builder/'.$section->id)
            ->assertOk()
            ->assertSee('href="'.route('admin.posts.edit', $section->id).'"', false);
    }

    public function test_the_layouts_menu_is_lit_on_a_section_edit_screen(): void
    {
        $section = $this->createSection();
        $this->actingAs($this->administrator())->get('/admin/posts/'.$section->id.'/edit');

        $this->assertTrue(\FalconCms\Core\View\Components\Admin\Sidebar::isUrlActive(url('admin/falcon-builder-sections')));
    }

    public function test_only_layout_builder_users_may_edit_sections(): void
    {
        $section = $this->createSection();
        $editor = User::forceCreate([
            'name' => 'Ed', 'email' => 'editor@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);

        $this->actingAs($editor)->get('/admin/posts/'.$section->id.'/edit')->assertForbidden();
    }

    public function test_a_page_still_edits_as_before(): void
    {
        $page = Post::create(['user_id' => 1, 'title' => 'About', 'slug' => 'about', 'type' => 'page', 'status' => 'published', 'lang_code' => 'en', 'content' => '<p>x</p>']);

        $this->actingAs($this->administrator())->get('/admin/posts/'.$page->id.'/edit')
            ->assertOk()
            ->assertSee('Edit Page', false)
            ->assertSee('Move to Trash', false)
            ->assertSee('id="permalink-container"', false)
            ->assertDontSee('Back to Layouts', false);
    }
}
