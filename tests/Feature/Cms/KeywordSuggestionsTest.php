<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\KeywordSuggestions;
use Illuminate\Support\Facades\DB;

/**
 * Keyword ideas from a page's own words (SEO → Keywords): the phrases it says most that the
 * rest of the site says least, each with where it is still missing. No outside service.
 */
class KeywordSuggestionsTest extends TestCase
{
    private function make(string $title, string $slug, string $content): Post
    {
        return Post::forceCreate(['title' => $title, 'slug' => $slug, 'type' => 'post', 'status' => 'published', 'content' => $content, 'lang_code' => 'en']);
    }

    private const PAGE = '<h2>Why choose a Laravel CMS</h2>'
        .'<p>Our team builds every site with care. A Laravel CMS gives developers clean code. Our team supports you.</p>'
        .'<p>The Laravel CMS pricing is simple: one-time payment, open-source core. Our team answers questions.</p>'
        .'<p>Compare Laravel CMS pricing with monthly plans and the e-commerce add-on. E-commerce works out of the box.</p>';

    public function test_phrases_that_mark_the_page_out_rank_first_and_say_where_they_are_missing(): void
    {
        // "our team" is on every page of this site, so it says nothing about this one.
        foreach (['about', 'contact', 'jobs'] as $slug) {
            $this->make(ucfirst($slug), $slug, '<p>Our team is here. Our team is friendly.</p>');
        }
        $post = $this->make('Pricing', 'pricing', self::PAGE);

        $result = KeywordSuggestions::for(['post_id' => $post->id, 'title' => 'Pricing', 'slug' => 'pricing']);
        $phrases = array_column($result['phrases'], 'phrase');

        $this->assertSame('laravel cms', $phrases[0]);
        $this->assertContains('laravel cms pricing', $phrases);
        $this->assertContains('e-commerce', $phrases, 'a hyphenated word stays whole');
        $this->assertNotContains('our team', $phrases, 'said on every page of the site');
        $this->assertNotContains('the', $phrases);

        $top = $result['phrases'][0];
        $this->assertSame(4, $top['count']);
        $this->assertTrue($top['in']['headings']);
        $this->assertTrue($top['in']['intro']);
        $this->assertFalse($top['in']['title'], 'the title is just "Pricing"');
        $this->assertFalse($top['in']['url']);
        $this->assertSame(100, $top['score']);
    }

    public function test_unsaved_editor_content_is_analysed_before_the_saved_one(): void
    {
        $post = $this->make('Draft', 'draft', '<p>Nothing yet.</p>');

        $phrases = array_column(KeywordSuggestions::for([
            'post_id' => $post->id,
            'content' => '<p>Bangla recipes for beginners. Bangla recipes made simple. Easy bangla recipes.</p>',
        ])['phrases'], 'phrase');

        $this->assertSame('bangla recipes', $phrases[0]);
    }

    public function test_builder_json_headings_and_text_are_read(): void
    {
        $json = json_encode([['type' => 'container', 'children' => [
            ['type' => 'title', 'settings' => ['title' => 'Organic coffee beans', 'htmlTag' => 'h1']],
            ['type' => 'text_block', 'settings' => ['content' => '<p>Organic coffee beans roasted weekly. Buy organic coffee beans online.</p>']],
        ]]]);
        $post = $this->make('Shop', 'shop', $json);

        $top = KeywordSuggestions::for(['post_id' => $post->id])['phrases'][0];

        $this->assertSame('organic coffee beans', $top['phrase']);
        $this->assertTrue($top['in']['headings']);
    }

    public function test_bangla_text_is_split_into_words(): void
    {
        $post = $this->make('রান্না', 'ranna', '<p>সহজ রান্নার রেসিপি এখানে। সহজ রান্নার রেসিপি সবার জন্য। এবং সহজ রান্নার রেসিপি।</p>');

        $phrases = array_column(KeywordSuggestions::for(['post_id' => $post->id])['phrases'], 'phrase');

        $this->assertSame('সহজ রান্নার রেসিপি', $phrases[0]);
    }

    public function test_the_editor_endpoint_answers_and_plugins_can_add_to_it(): void
    {
        $post = $this->make('Pricing', 'pricing', self::PAGE);
        add_falcon_filter('falcon_keyword_suggestions', function ($result, $context) {
            $result['google'] = [['query' => 'from a plugin', 'post' => $context['post_id']]];

            return $result;
        });
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $this->actingAs($admin)->postJson(route('admin.seo.keyword-suggestions'), ['post_id' => $post->id, 'title' => 'Pricing'])
            ->assertOk()
            ->assertJsonPath('phrases.0.phrase', 'laravel cms')
            ->assertJsonPath('google.0.query', 'from a plugin')
            ->assertJsonPath('google.0.post', $post->id);
    }
}
