<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\LinkSuggestions;
use Illuminate\Support\Facades\DB;

/**
 * Internal link suggestions, both ways: related pages to link out to, and pages that mention
 * this one's keyword without linking to it.
 */
class LinkSuggestionsTest extends TestCase
{
    private function make(string $title, string $slug, string $content = '', string $type = 'post'): Post
    {
        return Post::forceCreate(['title' => $title, 'slug' => $slug, 'type' => $type, 'status' => 'published', 'content' => $content, 'lang_code' => 'en']);
    }

    public function test_outbound_suggestions_rank_by_shared_words_and_say_why(): void
    {
        $self = $this->make('Laravel CMS pricing', 'pricing');
        $this->make('Best Laravel CMS for agencies', 'agencies');
        $this->make('Pricing a Laravel project', 'project');
        $this->make('Gardening tips', 'garden');
        $this->make('Header layout', 'header', '', 'falcon_header');

        $out = LinkSuggestions::for('Laravel CMS pricing', 'laravel cms', $self->id)['outbound'];
        $titles = array_column($out, 'title');

        $this->assertSame('Best Laravel CMS for agencies', $titles[0], 'it shares the focus keyword');
        $this->assertContains('Pricing a Laravel project', $titles);
        $this->assertNotContains('Gardening tips', $titles);
        $this->assertNotContains('Header layout', $titles, 'layout pieces are not pages');
        $this->assertNotContains('Laravel CMS pricing', $titles, 'never itself');
        $this->assertStringStartsWith('Shares:', $out[1]['reason']);
        $this->assertSame(get_falcon_permalink(Post::where('slug', 'agencies')->first()), $out[0]['url']);
    }

    public function test_inbound_suggestions_are_pages_mentioning_the_keyword_without_a_link(): void
    {
        $self = $this->make('Laravel CMS pricing', 'pricing');
        $this->make('Why we built a laravel cms', 'why', '<p>Our laravel cms is free.</p>');
        $this->make('Already linking', 'linked', '<p>See the laravel cms <a href="/post/pricing">prices</a>.</p>');
        $this->make('Unrelated', 'other', '<p>Nothing here.</p>');

        $in = LinkSuggestions::for('Laravel CMS pricing', 'laravel cms', $self->id)['inbound'];

        $this->assertSame(['Why we built a laravel cms'], array_column($in, 'title'));
        $this->assertStringContainsString('does not link here', $in[0]['reason']);
    }

    public function test_the_editor_endpoint_returns_both_lists(): void
    {
        $self = $this->make('Laravel CMS pricing', 'pricing');
        $this->make('Best Laravel CMS', 'best');
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);

        $this->actingAs($admin)->getJson('/admin/seo/related-posts?s=Laravel+CMS+pricing&keyword=laravel+cms&exclude='.$self->id)
            ->assertOk()->assertJsonStructure(['outbound' => [['title', 'url', 'reason']], 'inbound'])
            ->assertJsonPath('outbound.0.title', 'Best Laravel CMS');
    }
}
