<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\IndexNow;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * IndexNow: published, changed and removed pages are announced to Bing and the other
 * participating engines in one ping after the response, with the site's key served at
 * /{key}.txt. Off by default, and never from a local host.
 */
class IndexNowTest extends TestCase
{
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        IndexNow::reset();
        config(['app.url' => 'https://example.com']);
        url()->forceRootUrl('https://example.com');
        url()->forceScheme('https');
        $this->author = User::forceCreate(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);
    }

    private function makePost(array $attrs = []): Post
    {
        return Post::forceCreate($attrs + ['title' => 'Hello', 'slug' => 'hello', 'type' => 'page', 'status' => 'published',
            'content' => '', 'lang_code' => 'en', 'user_id' => $this->author->id]);
    }

    public function test_nothing_is_sent_while_it_is_off(): void
    {
        $this->makePost();

        $this->assertNull(IndexNow::flush());
        Http::assertNothingSent();
    }

    public function test_a_published_page_is_announced_once_with_the_key(): void
    {
        $this->setCmsOptions(['indexnow_enabled' => '1']);
        $post = $this->makePost();
        $post->update(['title' => 'Hello again']);

        $this->assertSame(202, IndexNow::flush());

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === IndexNow::ENDPOINT
            && $r['host'] === 'example.com'
            && $r['key'] === IndexNow::key()
            && $r['keyLocation'] === 'https://example.com/'.IndexNow::key().'.txt'
            && $r['urlList'] === ['https://example.com/hello']);
        $this->assertSame(202, IndexNow::last()['status']);
    }

    public function test_a_moved_page_announces_both_addresses_and_drafts_none(): void
    {
        $this->setCmsOptions(['indexnow_enabled' => '1']);
        $post = $this->makePost();
        IndexNow::reset();

        $post->update(['slug' => 'hello-world']);
        $this->makePost(['slug' => 'draft', 'status' => 'draft']);
        IndexNow::flush();

        Http::assertSent(fn ($r) => $r['urlList'] === ['https://example.com/hello-world', 'https://example.com/hello']);
    }

    public function test_an_unpublished_or_deleted_page_is_announced_as_gone(): void
    {
        $this->setCmsOptions(['indexnow_enabled' => '1']);
        $a = $this->makePost(['slug' => 'a']);
        $b = $this->makePost(['slug' => 'b']);
        IndexNow::reset();

        $a->update(['status' => 'draft']);
        $b->delete();
        IndexNow::flush();

        Http::assertSent(fn ($r) => $r['urlList'] === ['https://example.com/a', 'https://example.com/b']);
    }

    public function test_a_noindex_page_is_not_announced(): void
    {
        $this->setCmsOptions(['indexnow_enabled' => '1']);
        $this->makePost(['seo_meta' => ['noindex' => '1']]);

        $this->assertNull(IndexNow::flush());
    }

    public function test_local_hosts_are_never_announced(): void
    {
        $this->setCmsOptions(['indexnow_enabled' => '1']);
        url()->forceRootUrl('http://my-site.test');

        $this->assertFalse(IndexNow::enabled());
    }

    public function test_the_key_file_answers_only_for_the_sites_own_key_while_on(): void
    {
        $key = IndexNow::key();
        $this->get('/'.$key.'.txt')->assertNotFound();

        $this->setCmsOptions(['indexnow_enabled' => '1']);
        $this->get('/'.$key.'.txt')->assertOk()->assertSee($key);
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }
}
