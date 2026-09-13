<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Page;
use FalconCms\Core\Models\Redirect;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Two things the address of a page has to get right.
 *
 * The first is the permalink editor on Add New. It has always been on that screen, and the
 * slug it posted was thrown away: store() validated no `slug` key, so the value never reached
 * the controller, which then built one from the title. Nothing said so — the address simply
 * came out different from the one that had just been typed. Pages was the only screen with
 * this hole; posts, categories, tags and terms all read the submitted slug already.
 *
 * The second is what happens to the redirect table when an address changes. A rename writes
 * old → new, which is right on its own and wrong in company: rename back, or hand the old
 * address to another page later, and the table ends up holding both directions at once. A
 * visitor then bounces between the two until the browser stops them. These tests pin the
 * three cases that produce that shape.
 */
class SlugAndRedirectTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'slug-admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function createPage(array $extra = []): void
    {
        $this->actingAs($this->administrator())->post('/admin/pages', array_merge([
            'title' => 'Our Approach To Nutrition Consultation',
            'content' => '<p>Hello.</p>',
            'status' => 'published',
        ], $extra));
    }

    public function test_a_slug_typed_on_the_create_screen_is_the_one_that_is_saved(): void
    {
        $this->createPage(['slug' => 'approach']);

        $this->assertSame('approach', Page::latest('id')->first()->slug);
    }

    public function test_an_empty_slug_still_falls_back_to_the_title(): void
    {
        $this->createPage(['slug' => '']);

        $this->assertSame('our-approach-to-nutrition-consultation', Page::latest('id')->first()->slug);
    }

    public function test_a_typed_slug_is_still_made_unique(): void
    {
        // By title, not by id: the install ships with pages of its own, so "the first row"
        // is not this test's page.
        $this->createPage(['slug' => 'approach']);
        $first = Page::where('title', 'Our Approach To Nutrition Consultation')->first();

        $this->createPage(['title' => 'Another Page', 'slug' => 'approach']);
        $second = Page::where('title', 'Another Page')->first();

        $this->assertSame('approach', $first->slug);
        $this->assertNotSame('approach', $second->slug, 'two pages cannot share one address');
    }

    public function test_renaming_back_does_not_leave_a_redirect_loop(): void
    {
        Redirect::recordMove('/a', '/b');
        Redirect::recordMove('/b', '/a');

        $this->assertSame(0, Redirect::where('old_url', '/a')->count(),
            '/a is where the page lives again, so nothing may redirect away from it');
        $this->assertSame('/a', Redirect::where('old_url', '/b')->value('new_url'));
    }

    public function test_a_chain_is_flattened_rather_than_followed(): void
    {
        Redirect::recordMove('/x', '/a');
        Redirect::recordMove('/a', '/b');

        $this->assertSame('/b', Redirect::where('old_url', '/x')->value('new_url'),
            'a visitor should reach the page in one hop, not two');
        $this->assertSame('/b', Redirect::where('old_url', '/a')->value('new_url'));
    }

    public function test_an_address_that_is_live_again_stops_redirecting(): void
    {
        Redirect::recordMove('/old-page', '/new-page');

        // The old address is reused by something else, which then moves on in its turn.
        Redirect::recordMove('/old-page', '/third-page');

        $this->assertSame('/third-page', Redirect::where('old_url', '/old-page')->value('new_url'));
        $this->assertSame(1, Redirect::where('old_url', '/old-page')->count());
    }

    public function test_a_move_to_the_same_address_records_nothing(): void
    {
        Redirect::recordMove('/same', '/same');

        $this->assertSame(0, Redirect::count());
    }

    public function test_renaming_a_page_records_the_move(): void
    {
        $this->createPage(['slug' => 'first-address']);
        $page = Page::latest('id')->first();

        $this->actingAs($this->administrator())->put('/admin/pages/'.$page->id, [
            'title' => $page->title,
            'slug' => 'second-address',
            'content' => '<p>Hello.</p>',
            'status' => 'published',
        ]);

        $this->assertSame('second-address', $page->fresh()->slug);
        $this->assertSame('/second-address', Redirect::where('old_url', '/first-address')->value('new_url'));
    }
}
