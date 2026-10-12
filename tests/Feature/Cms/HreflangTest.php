<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Language;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * hreflang on a multilingual site.
 *
 * Translations were linked only by the language switcher, so Google had to guess which
 * version to show a Bangla searcher. Every page now announces its published translations,
 * from one shared group so the links always point both ways, with x-default on the default
 * language's version.
 */
class HreflangTest extends TestCase
{
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        Language::updateOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'status' => true]);
        Language::updateOrCreate(['code' => 'bn'], ['name' => 'Bangla', 'is_default' => false, 'status' => true]);
        $this->author = User::forceCreate(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);
    }

    private function page(string $slug, string $lang, ?int $origin = null, array $seo = [], string $status = 'published'): Post
    {
        return Post::forceCreate(['title' => $slug, 'slug' => $slug, 'type' => 'page', 'status' => $status, 'content' => '',
            'lang_code' => $lang, 'origin_id' => $origin, 'seo_meta' => $seo, 'user_id' => $this->author->id]);
    }

    private function headFor(Post $post): string
    {
        return view('falcon-cms::components.frontend.seo-meta', ['post' => $post])->render();
    }

    public function test_each_translation_lists_every_version_and_points_back(): void
    {
        $en = $this->page('pricing', 'en');
        $bn = $this->page('mullo', 'bn', $en->id);

        foreach ([$en, $bn] as $post) {
            $html = $this->headFor($post);
            $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="'.get_falcon_permalink($en).'">', $html);
            $this->assertStringContainsString('<link rel="alternate" hreflang="bn" href="'.get_falcon_permalink($bn).'">', $html);
            $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="'.get_falcon_permalink($en).'">', $html);
        }
    }

    public function test_drafts_noindex_versions_and_inactive_languages_are_left_out(): void
    {
        DB::table('cms_languages')->insert(['name' => 'French', 'code' => 'fr', 'is_default' => false, 'status' => false]);
        $en = $this->page('about', 'en');
        $this->page('about-bn', 'bn', $en->id, [], 'draft');
        $this->page('about-fr', 'fr', $en->id);

        $this->assertStringNotContainsString('hreflang', $this->headFor($en), 'with no other live version there is nothing to announce');

        $bn = $this->page('about-bn2', 'bn', $en->id, ['noindex' => '1']);
        $this->assertStringNotContainsString('hreflang', $this->headFor($en));
        $this->assertStringNotContainsString('hreflang', $this->headFor($bn), 'a noindex page announces nothing');
    }

    public function test_a_single_language_site_has_no_hreflang(): void
    {
        DB::table('cms_languages')->where('code', 'bn')->update(['status' => false]);
        $en = $this->page('solo', 'en');

        $this->assertSame([], falcon_hreflang_links($en));
    }
}
