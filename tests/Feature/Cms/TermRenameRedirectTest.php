<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Redirect;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Renaming a term leaves a redirect behind, the way renaming a page always has.
 *
 * A category or tag slug is an address: /category/family gets linked to, bookmarked and
 * indexed. Editing it rewrote the address and left nothing at the old one, so every one of
 * those links 404'd, with nothing in the admin to say it had happened. Pages have recorded
 * the move since the redirect table existed; terms never did.
 *
 * These assert the row that gets written. Serving it is RedirectMiddleware's job and is not
 * reachable from here — Testbench's HTTP kernel replaces the `web` group with its own when it
 * bootstraps, so no middleware this package pushes onto that group runs inside a test request.
 */
class TermRenameRedirectTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Term Admin',
            'email' => 'term-admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** @param array<string, string> $fields */
    private function saveCategory(Category $category, array $fields): void
    {
        $this->actingAs($this->administrator())
            ->put(route('admin.categories.update', $category), $fields + ['lang_code' => 'en'])
            ->assertSessionHasNoErrors();
    }

    public function test_renaming_a_category_records_the_move(): void
    {
        $category = Category::create(['name' => 'Family', 'slug' => 'family', 'lang_code' => 'en']);

        $this->saveCategory($category, ['name' => 'Family Cooking', 'slug' => 'family-cooking']);

        $this->assertDatabaseHas('cms_redirects', [
            'old_url' => '/category/family',
            'new_url' => '/category/family-cooking',
            'status_code' => 301,
        ]);
    }

    public function test_renaming_a_tag_records_the_move(): void
    {
        $tag = Tag::create(['name' => 'Habits', 'slug' => 'habits', 'lang_code' => 'en']);

        $this->actingAs($this->administrator())
            ->put(route('admin.tags.update', $tag), [
                'name' => 'Daily Habits',
                'slug' => 'daily-habits',
                'lang_code' => 'en',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cms_redirects', [
            'old_url' => '/tag/habits',
            'new_url' => '/tag/daily-habits',
        ]);
    }

    public function test_saving_without_changing_the_slug_records_nothing(): void
    {
        $category = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'lang_code' => 'en']);

        $this->saveCategory($category, ['name' => 'Wellness', 'slug' => 'wellness']);

        $this->assertSame(0, Redirect::where('old_url', '/category/wellness')->count());
    }

    public function test_renaming_back_does_not_leave_the_two_pointing_at_each_other(): void
    {
        $category = Category::create(['name' => 'Fitness', 'slug' => 'fitness', 'lang_code' => 'en']);

        $this->saveCategory($category, ['name' => 'Fitness', 'slug' => 'training']);
        $this->saveCategory($category->fresh(), ['name' => 'Fitness', 'slug' => 'fitness']);

        // Both directions stored at once is a loop the browser gives up on.
        $this->assertSame(0, Redirect::where('old_url', '/category/fitness')->count());
        $this->assertSame(
            '/category/fitness',
            Redirect::where('old_url', '/category/training')->value('new_url')
        );
    }

    public function test_a_second_rename_moves_the_first_address_on_rather_than_stranding_it(): void
    {
        $category = Category::create(['name' => 'Budget', 'slug' => 'budget', 'lang_code' => 'en']);

        $this->saveCategory($category, ['name' => 'Budget', 'slug' => 'cheap-eats']);
        $this->saveCategory($category->fresh(), ['name' => 'Budget', 'slug' => 'eating-cheaply']);

        // The very first address has to end up at the current one, not at a dead middle step.
        $this->assertSame(
            '/category/eating-cheaply',
            Redirect::where('old_url', '/category/budget')->value('new_url')
        );
    }

    public function test_the_redirect_follows_the_configured_base(): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => 'category_base'], ['value' => 'topics']);
        forget_cms_options_cache();

        $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks', 'lang_code' => 'en']);
        $this->saveCategory($category, ['name' => 'Snacks', 'slug' => 'small-plates']);

        $this->assertDatabaseHas('cms_redirects', [
            'old_url' => '/topics/snacks',
            'new_url' => '/topics/small-plates',
        ]);

        DB::table('cms_settings')->where('key', 'category_base')->delete();
        forget_cms_options_cache();
    }
}
