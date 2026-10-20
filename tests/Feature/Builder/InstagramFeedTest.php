<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Pro\LicenseGateway;
use FalconCms\Core\Pro\NullLicenseGateway;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\Instagram;
use FalconCms\Core\Support\InstagramFeedElement;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The Instagram Feed element (Pro): connecting the account under Appearance → Customizer →
 * Instagram, keeping its posts and pictures on the site, surviving Instagram outages, renewing
 * the token, and the element itself.
 */
class InstagramFeedTest extends TestCase
{
    private const TOKEN = 'IGAAtesttoken1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withProLicensed();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Instagram::disconnect();
        parent::tearDown();
    }

    private function administrator(): User
    {
        return User::where('email', 'ig-admin@example.test')->first() ?? User::forceCreate([
            'name' => 'Admin', 'email' => 'ig-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** Replace every earlier fake: Http::fake() adds to the stubs it already has, first match wins. */
    private function refake($stubs): void
    {
        $this->app->instance(Factory::class, new Factory);
        Http::clearResolvedInstances();
        Http::fake($stubs);
    }

    private function fakeInstagram(array $overrides = []): void
    {
        $this->refake($overrides + [
            'graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'IGAArenewed000000000000000', 'expires_in' => 5183944]),
            'graph.instagram.com/me/media*' => Http::response(['data' => [
                ['id' => '111', 'caption' => 'Rally in Dhaka #news', 'media_type' => 'IMAGE', 'media_url' => 'https://cdn.example/111.jpg', 'permalink' => 'https://www.instagram.com/p/AAA/', 'timestamp' => '2026-10-19T10:00:00+0000'],
                ['id' => '222', 'caption' => 'Behind the scenes', 'media_type' => 'VIDEO', 'media_url' => 'https://cdn.example/222.mp4', 'thumbnail_url' => 'https://cdn.example/222-thumb.jpg', 'permalink' => 'https://www.instagram.com/p/BBB/'],
                ['id' => '333', 'caption' => '', 'media_type' => 'CAROUSEL_ALBUM', 'media_url' => 'https://cdn.example/333.jpg', 'permalink' => 'https://www.instagram.com/p/CCC/'],
                ['id' => '444', 'media_type' => 'IMAGE'], // no picture: skipped
            ]]),
            'graph.instagram.com/me*' => Http::response(['user_id' => '17841400000', 'username' => 'dailyfalcon', 'name' => 'The Daily Falcon',
                'profile_picture_url' => 'https://cdn.example/avatar.jpg', 'followers_count' => 15400, 'media_count' => 812]),
            'cdn.example/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_a_token_saved_in_the_customizer_connects_the_account(): void
    {
        $this->fakeInstagram();
        $this->actingAs($this->administrator());

        $this->get(route('admin.customizer.index', ['section' => 'instagram']))->assertOk()
            ->assertSee('Generate token')->assertSee('developers.facebook.com/apps', false);

        $this->postJson(route('admin.customizer.save'), ['_section' => 'instagram', 'instagram_access_token' => self::TOKEN, 'instagram_cache_minutes' => '30', 'instagram_local_images' => '1'],
            ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['success' => true, 'reload' => true, 'message' => 'Settings saved. Connected to Instagram as @dailyfalcon.']);

        $this->assertNull(get_cms_option('instagram_access_token'), 'the pasted token is not kept as a setting');
        $this->assertSame(self::TOKEN, Crypt::decryptString(get_cms_option('instagram_token')));
        $this->assertSame('30', get_cms_option('instagram_cache_minutes'));
        $this->assertSame('dailyfalcon', Instagram::account()['username']);

        $media = Instagram::media();
        $this->assertSame(['111', '222', '333'], array_column($media, 'id'));
        $this->assertSame(['IMAGE', 'VIDEO', 'CAROUSEL_ALBUM'], array_column($media, 'type'));
        $this->assertSame('/storage/falcon-instagram/111.jpg', $media[0]['image']);
        Storage::disk('public')->assertExists(['falcon-instagram/111.jpg', 'falcon-instagram/222.jpg', 'falcon-instagram/333.jpg']);
        // The video's picture is its thumbnail, never the video file.
        Http::assertSent(fn (Request $r) => $r->url() === 'https://cdn.example/222-thumb.jpg');
        Http::assertNotSent(fn (Request $r) => $r->url() === 'https://cdn.example/222.mp4');

        $this->get(route('admin.customizer.index', ['section' => 'instagram']))->assertOk()
            ->assertSee('Connected to <strong>@dailyfalcon</strong>', false)->assertSee('3 posts saved')->assertSee('Disconnect Instagram');
    }

    public function test_a_refused_token_is_explained_and_not_kept(): void
    {
        $this->refake(['graph.instagram.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token - Cannot parse access token', 'code' => 190]], 400)]);
        $this->actingAs($this->administrator());

        $this->postJson(route('admin.customizer.save'), ['instagram_access_token' => 'IGAAbad-token-0000000000000'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['success' => false, 'message' => 'Settings saved, but Instagram did not accept the token: Instagram: Invalid OAuth access token - Cannot parse access token']);
        $this->assertFalse(Instagram::connected());
    }

    public function test_fetch_now_and_disconnect_from_the_customizer(): void
    {
        $this->fakeInstagram();
        Instagram::connect(self::TOKEN);
        $this->actingAs($this->administrator());

        $this->postJson(route('admin.customizer.action', 'instagramSync'))->assertOk()->assertJson(['success' => true, 'message' => 'Fetched 3 Instagram posts.']);

        $this->postJson(route('admin.customizer.action', 'instagramDisconnect'))->assertOk()->assertJson(['success' => true]);
        $this->assertFalse(Instagram::connected());
        $this->assertSame([], Instagram::media());
        Storage::disk('public')->assertMissing('falcon-instagram/111.jpg');

        $this->postJson(route('admin.customizer.action', 'instagramSync'))->assertJson(['success' => false]);
    }

    public function test_an_outage_keeps_the_last_good_posts(): void
    {
        $this->fakeInstagram();
        Instagram::connect(self::TOKEN);

        $this->refake(['*' => Http::response(['error' => ['message' => 'Service temporarily unavailable']], 503)]);
        $this->assertFalse(Instagram::sync());

        $this->assertCount(3, Instagram::media(), 'the saved copy keeps showing');
        $this->assertSame('Instagram: Service temporarily unavailable', Instagram::lastError());
    }

    public function test_pages_never_wait_for_instagram(): void
    {
        $this->fakeInstagram();
        Instagram::connect(self::TOKEN);
        $this->assertFalse(Instagram::stale());

        $this->refake(fn () => throw new \RuntimeException('a page must not call Instagram'));
        $this->assertCount(3, Instagram::media());

        $html = view('falcon-cms::frontend.builder.elements.instagram-feed', ['el' => ['id' => 'ig1', 'settings' => ['source' => 'account', 'count' => 2]]])->render();
        $this->assertSame(2, substr_count($html, 'class="fni-item"'));
        $this->assertStringContainsString('&#64;dailyfalcon</a>', $html);
        $this->assertStringContainsString('The Daily Falcon · 15.4K followers', $html);
        $this->assertStringContainsString('href="https://www.instagram.com/p/AAA/"', $html);
        $this->assertStringContainsString('src="http://localhost/storage/falcon-instagram/111.jpg"', $html);
        $this->assertStringContainsString('href="https://www.instagram.com/dailyfalcon/"', $html);
    }

    public function test_the_token_is_renewed_weekly(): void
    {
        $this->fakeInstagram();
        Instagram::connect(self::TOKEN);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'refresh_access_token'));

        update_cms_option('instagram_token_refreshed_at', now()->subDays(8)->toIso8601String());
        forget_cms_options_cache();
        $this->artisan('falcon:instagram-sync', ['--force' => true])->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'refresh_access_token'));
        $this->assertSame('IGAArenewed000000000000000', Instagram::token());
    }

    public function test_a_picture_that_leaves_the_feed_is_kept_while_cached_pages_may_show_it(): void
    {
        $this->fakeInstagram();
        Instagram::connect(self::TOKEN);

        // Post 111 is deleted on Instagram.
        $this->fakeInstagram(['graph.instagram.com/me/media*' => Http::response(['data' => [
            ['id' => '222', 'media_type' => 'IMAGE', 'media_url' => 'https://cdn.example/222.jpg', 'permalink' => 'https://www.instagram.com/p/BBB/'],
        ]])]);
        $this->assertTrue(Instagram::sync());
        $this->assertSame(['222'], array_column(Instagram::media(), 'id'));
        Storage::disk('public')->assertExists('falcon-instagram/111.jpg');

        $this->travel(3)->days();
        $this->assertTrue(Instagram::sync());
        Storage::disk('public')->assertMissing('falcon-instagram/111.jpg');
        Storage::disk('public')->assertExists('falcon-instagram/222.jpg');
    }

    public function test_the_element_without_an_account_without_pictures_and_without_a_licence(): void
    {
        $render = fn (array $s, bool $preview = false) => trim(view('falcon-cms::frontend.builder.elements.instagram-feed', ['el' => ['id' => 'x', 'settings' => $s], 'builderPreview' => $preview])->render());

        // Readers see nothing; the builder says what is missing and links to the setup.
        $this->assertSame('', $render(['source' => 'account']));
        $preview = $render(['source' => 'account'], true);
        $this->assertStringContainsString('Connect your Instagram account in Appearance → Customizer → Instagram', $preview);
        $this->assertStringContainsString('customizer?section=instagram', $preview);

        $manual = ['source' => 'manual', 'username' => '@dailyfalcon', 'layout' => 'carousel', 'columns' => '2', 'items' => [
            ['image' => 'https://img.example/a.jpg', 'link' => 'https://www.instagram.com/p/X/', 'caption' => 'হ্যালো <b>world</b>'],
            ['image' => '/storage/b.jpg'],
            ['image' => 'https://img.example/c.jpg'],
            ['image' => ''],
        ]];
        $html = $render($manual);
        $this->assertSame(3, substr_count($html, 'class="fni-item"'));
        $this->assertSame(2, substr_count($html, 'class="fni-arrow'), 'more pictures than columns: arrows');
        $this->assertStringContainsString('src="http://localhost/storage/b.jpg"', $html);
        $this->assertStringContainsString('হ্যালো world', $html);
        $this->assertStringNotContainsString('<b>world', $html);

        $this->app->instance(LicenseGateway::class, new NullLicenseGateway);
        config(['falcon-options.freemium_grace_until' => null]);
        $this->assertSame('', $render($manual));
        $this->assertStringContainsString('Pro element', $render($manual, true));
    }

    public function test_post_links_are_read_the_way_instagram_shares_them(): void
    {
        $this->assertSame('https://www.instagram.com/p/C9xYz12AbcD/', Instagram::embedPermalink('https://www.instagram.com/p/C9xYz12AbcD/?igsh=MTc4MmM1YmI2Ng=='));
        $this->assertSame('https://www.instagram.com/reel/C9xYz12AbcD/', Instagram::embedPermalink('instagram.com/reels/C9xYz12AbcD'));
        $this->assertSame('https://www.instagram.com/p/C9xYz12AbcD/', Instagram::embedPermalink('https://www.instagram.com/dailyfalcon/p/C9xYz12AbcD/'));
        $this->assertSame('https://www.instagram.com/tv/C9xYz12AbcD/', Instagram::embedPermalink('http://instagr.am/tv/C9xYz12AbcD'));
        $this->assertNull(Instagram::embedPermalink('https://www.instagram.com/dailyfalcon/'));
        $this->assertNull(Instagram::embedPermalink('https://evil.example/p/C9xYz12AbcD/'));
        $this->assertNull(Instagram::embedPermalink('"><script>alert(1)</script>'));
    }

    public function test_post_links_show_instagrams_own_embed_without_any_token(): void
    {
        $this->refake(fn () => throw new \RuntimeException('embedded posts need no API call'));
        $this->assertFalse(Instagram::connected());

        $settings = ['source' => 'embed', 'username' => 'personal.page', 'columns' => '3', 'caption' => 'hover', 'embed_posts' => [
            ['url' => 'https://www.instagram.com/p/AAAAAA1/?igsh=xyz'],
            ['url' => 'not a link'],
            ['url' => 'https://www.instagram.com/reel/BBBBBB2/'],
        ]];
        $render = fn (array $s, bool $preview = false) => trim(view('falcon-cms::frontend.builder.elements.instagram-feed', ['el' => ['id' => 'e1', 'settings' => $s], 'builderPreview' => $preview])->render());

        $page = $render($settings);
        $this->assertSame(2, substr_count($page, 'class="instagram-media"'));
        $this->assertStringContainsString('data-instgrm-permalink="https://www.instagram.com/p/AAAAAA1/?utm_source=ig_embed&amp;utm_campaign=loading"', $page);
        $this->assertStringContainsString('data-instgrm-captioned', $page);
        $this->assertStringContainsString("s.src='https://www.instagram.com/embed.js'", $page);
        $this->assertSame(1, substr_count($page, 'embed.js'), 'the script once per feed, and only once per page');
        $this->assertStringContainsString('href="https://www.instagram.com/personal.page/"', $page, 'the header and Follow button use the username');

        $this->assertStringNotContainsString('data-instgrm-captioned', $render(['caption' => 'none'] + $settings));

        // The canvas cannot run Instagram's script: stand-in cards, no script.
        $canvas = $render($settings, true);
        $this->assertSame(2, substr_count($canvas, 'class="fni-embed-ph"'));
        $this->assertStringContainsString('instagram.com/reel/BBBBBB2/', $canvas);
        $this->assertStringNotContainsString('embed.js', $canvas);

        // No links yet.
        $this->assertSame('', $render(['source' => 'embed']));
        $this->assertStringContainsString('Post links', $render(['source' => 'embed'], true));
    }

    public function test_the_element_is_registered_and_survives_the_builder_shortcode_round_trip(): void
    {
        $this->assertArrayHasKey(InstagramFeedElement::TYPE, falcon_builder_element_defs(true));
        $this->assertSame('server', falcon_builder_element_defs(true)[InstagramFeedElement::TYPE]['preview']);

        $settings = [
            'source' => 'manual', 'username' => 'dailyfalcon', 'count' => 6, 'layout' => 'carousel',
            'columns' => '5', 'columns_tablet' => '3', 'columns_mobile' => '1', 'ratio' => 'portrait', 'gap' => 12,
            'arrows' => 'hide', 'header' => 'hide', 'caption' => 'below', 'caption_length' => 40, 'hover' => 'zoom',
            'link_target' => '_self', 'radius' => 0, 'overlay_color' => 'rgba(10,20,30,0.5)', 'button_style' => 'outline',
            'caption_typo_size' => 15, 'margin_top' => 20, 'margin_top_mobile' => 8,
            'items' => [['image' => 'https://img.example/a.jpg', 'link' => 'https://www.instagram.com/p/X/', 'caption' => 'One']],
            'embed_posts' => [['url' => 'https://www.instagram.com/p/AAAAAA1/'], ['url' => 'https://www.instagram.com/reel/BBBBBB2/']],
        ];
        $layout = [['type' => 'container', 'settings' => [], 'columns' => [['settings' => [], 'elements' => [
            ['id' => 'igx', 'type' => InstagramFeedElement::TYPE, 'settings' => $settings],
        ]]]]];

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout))), true);
        $restored = $back[0]['columns'][0]['elements'][0]['settings'];

        foreach ($settings as $key => $value) {
            $this->assertEquals($value, $restored[$key] ?? null, "setting {$key} survives");
        }
    }
}
