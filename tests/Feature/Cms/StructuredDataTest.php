<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use FalconSeo\Support\StructuredData;
use Illuminate\Support\ViewErrorBag;

/**
 * The page's JSON-LD: the organization behind the site, the site, the page and its author as
 * one linked @graph, plus the author box that shows the same person to readers.
 */
class StructuredDataTest extends TestCase
{
    private function graphFor(?Post $post = null, string $type = 'website'): array
    {
        $html = view('falcon-cms::components.frontend.seo-meta', $post ? ['post' => $post] : [])->render();
        preg_match('#<script type="application/ld\+json">\s*(.*?)\s*</script>#s', $html, $m);

        return json_decode($m[1], true);
    }

    private function byType(array $schema, string $type): ?array
    {
        foreach ($schema['@graph'] as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }

    private function author(array $extra = []): User
    {
        return User::forceCreate($extra + ['name' => 'Tareq Islam', 'email' => 'tareq@example.test', 'password' => 'x',
            'bio' => 'Builds FalconCMS.', 'twitter_url' => 'https://x.com/tareq', 'avatar' => 'avatars/t.jpg']);
    }

    public function test_every_page_names_the_organization_and_the_site(): void
    {
        $this->setCmsOptions(['site_title' => 'FalconCMS', 'seo_org_same_as' => "https://facebook.com/falconcms\nnot a url\nhttps://github.com/falconcms", 'theme_site_logo' => 'logo.png']);

        $schema = $this->graphFor();
        $org = $this->byType($schema, 'Organization');

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('FalconCMS', $org['name']);
        $this->assertSame(['https://facebook.com/falconcms', 'https://github.com/falconcms'], $org['sameAs']);
        $this->assertStringEndsWith('/storage/logo.png', $org['logo']['url']);
        $this->assertSame(['@id' => $org['@id']], $this->byType($schema, 'WebSite')['publisher']);
        $this->assertNotNull($this->byType($schema, 'WebPage'));
    }

    public function test_a_local_business_carries_its_address_and_contact(): void
    {
        $this->setCmsOptions(['seo_org_type' => 'Restaurant', 'seo_org_name' => 'Falcon Café', 'seo_org_phone' => '+880 1700 000000',
            'seo_org_city' => 'Dhaka', 'seo_org_country' => 'BD', 'seo_org_price_range' => '$$']);

        $biz = $this->byType($this->graphFor(), 'Restaurant');

        $this->assertSame('Falcon Café', $biz['name']);
        $this->assertSame('+880 1700 000000', $biz['telephone']);
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Dhaka', 'addressCountry' => 'BD'], $biz['address']);
        $this->assertSame('$$', $biz['priceRange']);
    }

    public function test_a_post_names_its_real_author_as_a_linked_person(): void
    {
        $author = $this->author();
        $post = Post::forceCreate(['title' => 'Hello', 'slug' => 'hello', 'type' => 'post', 'status' => 'published', 'content' => '',
            'lang_code' => 'en', 'user_id' => $author->id, 'published_at' => '2026-10-01 10:00:00']);

        $schema = $this->graphFor($post->fresh());
        $article = $this->byType($schema, 'BlogPosting');
        $person = $this->byType($schema, 'Person');

        $this->assertSame('Tareq Islam', $person['name'], 'the post author, not "Admin"');
        $this->assertSame(route('frontend.author', $author->id), $person['url']);
        $this->assertSame('Builds FalconCMS.', $person['description']);
        $this->assertSame(['https://x.com/tareq'], $person['sameAs']);
        $this->assertSame(['@id' => $person['@id']], $article['author']);
        $this->assertStringStartsWith('2026-10-01', $article['datePublished']);
    }

    public function test_an_author_archive_is_a_profile_page(): void
    {
        $author = $this->author();
        falcon_layout_context(['kind' => 'archive', 'archive_type' => 'author', 'author_id' => $author->id]);

        $page = $this->byType($this->graphFor(), 'ProfilePage');
        falcon_layout_context(['kind' => null]);

        $this->assertSame('Tareq Islam', $page['mainEntity']['name']);
    }

    public function test_elements_can_add_nodes_to_the_page(): void
    {
        StructuredData::add(['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => 'Tour']);

        $video = $this->byType($this->graphFor(), 'VideoObject');

        $this->assertSame('Tour', $video['name']);
        $this->assertArrayNotHasKey('@context', $video);
        $this->assertSame([], StructuredData::takeExtra(), 'printed once, then forgotten');
    }

    public function test_the_author_box_appears_only_for_an_author_with_a_bio(): void
    {
        $withBio = $this->author();
        $post = Post::forceCreate(['title' => 'Hi', 'slug' => 'hi', 'type' => 'post', 'status' => 'published', 'content' => '', 'lang_code' => 'en', 'user_id' => $withBio->id]);

        $html = view('falcon-cms::themes.falcon-theme.partials.single-author', ['post' => $post->fresh(), 'errors' => new ViewErrorBag])->render();
        $this->assertStringContainsString('Written by', $html);
        $this->assertStringContainsString('Builds FalconCMS.', $html);
        $this->assertStringContainsString('https://x.com/tareq', $html);

        $noBio = User::forceCreate(['name' => 'Quiet', 'email' => 'q@example.test', 'password' => 'x']);
        $post2 = Post::forceCreate(['title' => 'Hi2', 'slug' => 'hi2', 'type' => 'post', 'status' => 'published', 'content' => '', 'lang_code' => 'en', 'user_id' => $noBio->id]);
        $this->assertSame('', trim(view('falcon-cms::themes.falcon-theme.partials.single-author', ['post' => $post2->fresh()])->render()));
    }

    public function test_videos_in_a_post_become_video_objects(): void
    {
        $content = '[falcon_video url="https://www.youtube.com/watch?v=dQw4w9WgXcQ"] <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
            .' <p>also https://youtu.be/abcdefghijk</p>';
        $post = Post::forceCreate(['title' => 'Tour', 'slug' => 'tour', 'type' => 'page', 'status' => 'published', 'content' => $content,
            'lang_code' => 'en', 'published_at' => '2026-09-01 10:00:00']);

        $videos = array_values(array_filter($this->graphFor($post->fresh())['@graph'], fn ($n) => ($n['@type'] ?? '') === 'VideoObject'));

        $this->assertCount(2, $videos, 'the same video twice is one video');
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $videos[0]['embedUrl']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $videos[0]['thumbnailUrl']);
        $this->assertStringStartsWith('2026-09-01', $videos[0]['uploadDate']);
        $this->assertSame('Tour', $videos[0]['name']);
    }

    public function test_a_vimeo_video_without_any_thumbnail_is_skipped(): void
    {
        $post = Post::forceCreate(['title' => 'V', 'slug' => 'v', 'type' => 'page', 'status' => 'published',
            'content' => '<iframe src="https://player.vimeo.com/video/123456789"></iframe>', 'lang_code' => 'en']);

        $this->assertNull($this->byType($this->graphFor($post->fresh()), 'VideoObject'));
    }
}
