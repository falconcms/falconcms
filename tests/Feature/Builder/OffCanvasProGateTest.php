<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\OffCanvas;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Off-canvas is Pro (builder_pro), on the same "browse but locked" terms as the rest of the
 * Builder Library: without a licence nothing can be created, edited or deleted, but a panel
 * that already exists keeps working on the site.
 */
class OffCanvasProGateTest extends TestCase
{
    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function storedPanel(): array
    {
        $panel = OffCanvas::make('Mobile Menu', ['layout' => [[
            'columns' => [['elements' => [['type' => 'heading', 'settings' => ['title' => 'Inside', 'tag' => 'h3']]]]],
        ]]]);
        OffCanvas::store([$panel]);
        forget_cms_options_cache();

        return $panel;
    }

    public function test_an_unlicensed_site_cannot_create_or_edit_panels(): void
    {
        $this->assertFalse(falcon_pro_editable('builder_pro'));
        $admin = $this->administrator();

        $this->actingAs($admin)->postJson('/admin/falcon-builder-library/off-canvas', ['name' => 'Nope'])
            ->assertJson(['success' => false, 'pro' => true]);
        $this->assertSame([], OffCanvas::all());

        $panel = $this->storedPanel();
        $this->actingAs($admin)->postJson('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/settings', ['settings' => ['width' => 10]])
            ->assertJson(['pro' => true]);
        $this->actingAs($admin)->deleteJson('/admin/falcon-builder-library/off-canvas/'.$panel['id'])
            ->assertJson(['pro' => true]);
        $this->actingAs($admin)->get('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/builder')
            ->assertOk()->assertSee('available in the Pro version', false)->assertDontSee('Panel Options', false);

        $this->assertSame(400, OffCanvas::find($panel['id'])['config']['settings']['width']);
    }

    public function test_the_library_shows_it_locked(): void
    {
        $this->storedPanel();
        $admin = $this->administrator();

        $this->actingAs($admin)->get('/admin/falcon-builder-library?tab=off_canvas')
            ->assertOk()->assertSee('Mobile Menu', false)->assertSee('viewing the Library in preview', false);
    }

    public function test_an_existing_panel_still_works_on_the_site(): void
    {
        $this->storedPanel();
        Post::create([
            'user_id' => 1, 'title' => 'Landing', 'slug' => 'landing', 'type' => 'page', 'status' => 'published',
            'lang_code' => 'en', 'content' => '<a href="#offcanvas-mobile-menu">Menu</a>', 'published_at' => now()->subDay(),
        ]);

        $this->get('/landing')->assertOk()->assertSee('id="falcon-oc-mobile-menu"', false);
    }
}
