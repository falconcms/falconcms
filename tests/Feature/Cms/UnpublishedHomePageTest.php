<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Taking the home page out of publication.
 *
 * Every other page answers 404 the moment it stops being published. The home page did not:
 * its lookup asked for a published post, correctly found nothing, and then fell into a
 * translation fallback that asked again without mentioning status at all — and finished by
 * handing back the very post the first query had just refused.
 *
 *     $post = Post::where('id', $homePageId)->where('status', 'published')->first();
 *     if (!$post) {
 *         $originalPost = Post::find($homePageId);        // no status
 *         $post = Post::where('origin_id', ...)->first(); // no status
 *         if (!$post) { $post = $originalPost; }          // the draft itself
 *     }
 *
 * The fallback is there for translations: a home page may be missing from this locale and
 * present in another. What it forgot is that a page can also be missing because somebody
 * unpublished it, and those two look identical from inside a query that never asks.
 *
 * So "set it to draft" did nothing to the front page, which is the one page where that is
 * most likely to be noticed and least likely to be believed.
 */
class UnpublishedHomePageTest extends TestCase
{
    private function page(string $slug, string $status, array $extra = []): Post
    {
        return Post::create($extra + [
            'title' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'page',
            'status' => $status,
            'content' => '<p>The '.$slug.' page</p>',
            'lang_code' => 'en',
            'user_id' => User::forceCreate([
                'name' => 'A', 'email' => uniqid().'@example.test', 'password' => 'secret-password',
                'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            ])->id,
        ]);
    }

    private function makeHome(string $status): Post
    {
        $home = $this->page('home', $status);
        update_cms_option('home_page_id', (string) $home->id);
        forget_cms_options_cache();

        return $home;
    }

    public function test_a_published_home_page_is_served(): void
    {
        // The premise. Without this the assertion below could pass because nothing works.
        $this->makeHome('published');

        $this->get('/')->assertOk()->assertSee('The home page', false);
    }

    public function test_a_drafted_home_page_is_not_served(): void
    {
        $this->makeHome('draft');

        $this->assertStringNotContainsString('The home page', $this->get('/')->getContent(),
            'the front page still shows a page that is no longer published');
    }

    public function test_the_same_holds_for_every_other_status(): void
    {
        foreach (['draft', 'pending', 'private', 'trash'] as $status) {
            // A fresh slug each time: delete() is soft here, so the old row keeps its slug
            // and the unique index on (slug, type, lang_code) would refuse the next one.
            $home = $this->page('home-'.$status, $status);
            update_cms_option('home_page_id', (string) $home->id);
            forget_cms_options_cache();

            $this->assertStringNotContainsString('The home-'.$status.' page', $this->get('/')->getContent(),
                "a home page marked {$status} is still being served");
        }
    }

    public function test_the_site_still_has_a_front_page_to_show(): void
    {
        // Not serving the draft must not mean serving an error. Falling through to the
        // theme's own index is what happens when no home page is assigned at all, and it is
        // the right answer here too.
        $this->makeHome('draft');

        $this->get('/')->assertOk();
    }

    public function test_a_translation_is_still_found_when_the_home_page_is_published(): void
    {
        // The fallback this bug was hiding in exists for a reason: a home page may be missing
        // from the current locale and present in another. That has to keep working.
        // The assigned home page lives in another language; its translation is the one in the
        // locale being served. Only the fallback can find it.
        $original = $this->page('accueil', 'published', ['lang_code' => 'fr']);
        update_cms_option('home_page_id', (string) $original->id);
        forget_cms_options_cache();

        $this->page('home-en', 'published', ['lang_code' => 'en', 'origin_id' => $original->id]);

        $this->assertStringContainsString('The home-en page', $this->get('/')->getContent(),
            'the published translation is no longer found, so the fix went too far');
    }
}
