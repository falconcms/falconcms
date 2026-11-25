<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\SocialShare;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The Social Share element (Pro): what it shares wherever it is placed, the links it
 * builds, its designs and floating bar, the canvas preview and the shortcode round trip.
 */
class SocialShareTest extends TestCase
{
    private function render(array $s, array $context = [], bool $preview = false): string
    {
        return view('falcon-cms::frontend.builder.elements.social-share.render', $context + [
            'el' => ['id' => 'ss1', 'type' => 'social_share', 'settings' => $s],
            's' => $s,
            'builderPreview' => $preview,
        ])->render();
    }

    private function makePost(array $attrs = []): Post
    {
        $author = (int) DB::table('users')->insertGetId([
            'name' => 'A', 'email' => uniqid().'@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Post::create($attrs + [
            'title' => 'Shared story', 'slug' => 'shared-story-'.uniqid(), 'type' => 'post', 'status' => 'published',
            'content' => '<p>Hi</p>', 'published_at' => now()->subDay(), 'user_id' => $author,
        ]);
    }

    public function test_it_is_registered_as_a_pro_element_with_a_server_preview(): void
    {
        $def = falcon_builder_element_defs(true)[SocialShare::TYPE] ?? null;
        $this->assertNotNull($def);
        $this->assertSame('server', $def['preview']);
        $this->assertTrue(view()->exists($def['template']));
        $this->assertSame(['facebook', 'x_twitter', 'linkedin', 'whatsapp', 'copy'], $def['fields']['networks']['default']);

        $scripts = file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');
        $this->assertMatchesRegularExpression("/const proElementTypes = \[[^\]]*'social_share'/", $scripts);
    }

    public function test_the_links_carry_the_page_encoded_for_each_network(): void
    {
        $target = ['url' => 'https://site.test/blog/hello?a=1&b=2', 'title' => 'Hello & "welcome"', 'image' => 'https://site.test/storage/p.jpg'];
        $buttons = SocialShare::buttons(['networks' => array_keys(SocialShare::NETWORKS), 'hashtags' => 'laravel, cms, <bad>', 'via' => '@falconcms'], $target);
        $href = array_column($buttons, 'href', 'key');

        $u = rawurlencode($target['url']);
        $t = rawurlencode($target['title']);
        $this->assertSame('https://www.facebook.com/sharer/sharer.php?u='.$u, $href['facebook']);
        $this->assertSame('https://x.com/intent/post?url='.$u.'&text='.$t.'&hashtags=laravel%2Ccms%2Cbad&via=falconcms', $href['x_twitter']);
        $this->assertSame('https://api.whatsapp.com/send?text='.rawurlencode($target['title'].' '.$target['url']), $href['whatsapp']);
        $this->assertSame('https://pinterest.com/pin/create/button/?url='.$u.'&media='.rawurlencode($target['image']).'&description='.$t, $href['pinterest']);
        $this->assertSame('mailto:?subject='.$t.'&body='.$u, $href['email']);
        $this->assertSame('', $href['copy'], 'copy, print and the share menu are buttons, not links');
        $this->assertSame(array_keys(SocialShare::NETWORKS), array_keys($href), 'in the fixed order');
    }

    public function test_it_shares_the_post_a_card_hands_down_then_the_viewed_post_then_the_page(): void
    {
        // Inside a Post Card / Layout: the post the template is drawing.
        $card = SocialShare::target([], ['postPermalink' => 'https://site.test/card-post', 'postTitle' => 'Card post', 'postFeaturedImage' => 'https://cdn.test/c.jpg']);
        $this->assertSame(['url' => 'https://site.test/card-post', 'title' => 'Card post', 'image' => 'https://cdn.test/c.jpg'], $card);

        // On a single post: that post.
        $post = $this->makePost(['featured_image' => 'uploads/f.jpg']);
        view()->share('current_post', $post);
        $viewed = SocialShare::target([]);
        $this->assertSame(get_falcon_permalink($post), $viewed['url']);
        $this->assertSame('Shared story', $viewed['title']);
        $this->assertSame(asset('storage/uploads/f.jpg'), $viewed['image']);
        view()->share('current_post', null);
        view()->share('post', null);

        // Anywhere else (a header on an archive): the page's own address.
        $this->get('/');
        $this->assertSame(url()->current(), SocialShare::target([])['url']);

        // Custom text wins over the title; the home page and a typed link.
        $this->assertSame('Read this!', SocialShare::target(['share_text' => 'Read this!'], ['postPermalink' => 'https://x.test/a', 'postTitle' => 'A'])['title']);
        $this->assertSame(url('/'), SocialShare::target(['share_source' => 'site'])['url']);
        $this->assertSame(url('/promo'), SocialShare::target(['share_source' => 'custom', 'custom_url' => '/promo'])['url']);
        $this->assertSame(url('/'), SocialShare::target(['share_source' => 'custom', 'custom_url' => 'javascript:alert(1)'])['url'], 'not a web address: the home page instead');
    }

    public function test_the_page_markup_is_safe_and_wired(): void
    {
        $html = $this->render(
            ['networks' => ['facebook', 'copy', 'native'], 'heading' => 'Share <b>this</b>', 'share_text' => '"><script>alert(1)</script>'],
            ['postPermalink' => 'https://site.test/p', 'postTitle' => 'P']
        );
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('<span class="fss-heading">Share &lt;b&gt;this&lt;/b&gt;</span>', $html);
        $this->assertStringContainsString('href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fsite.test%2Fp"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener nofollow" data-fss-open', $html);
        $this->assertMatchesRegularExpression('/data-fss-action="copy"\s+data-fss-url="https:\/\/site.test\/p"/', $html);
        $this->assertStringContainsString('<li  hidden data-fss-native >', $html, 'the share menu waits for the browser to have one');
        $this->assertStringContainsString('window.__falconShare', $html);
        $this->assertStringContainsString('style="--fss-c:#1877F2;"', $html, 'brand colours by default');

        // Custom colours replace the brand ones and reach the stylesheet cleaned.
        $custom = $this->render(['networks' => ['facebook'], 'color_mode' => 'custom', 'btn_color' => '#111;}</style><script>x()</script>', 'btn_hover' => '#333'], ['postPermalink' => 'https://site.test/p']);
        $this->assertStringNotContainsString('--fss-c:#1877F2', $custom);
        $this->assertStringNotContainsString('<script>x()', $custom);
        $this->assertStringContainsString('--fss-hbg:#333;', $custom);
        $this->assertStringContainsString('fss--custom', $custom);

        $this->assertSame('', trim($this->render(['networks' => []])), 'no buttons, nothing on the page');
    }

    public function test_designs_floating_bar_and_typography(): void
    {
        foreach (SocialShare::DESIGNS as $design) {
            $this->assertStringContainsString('fss--'.$design, $this->render(['design' => $design], ['postPermalink' => 'https://a.test/x']));
        }
        $icons = $this->render(['design' => 'icons'], ['postPermalink' => 'https://a.test/x']);
        $this->assertStringContainsString('fss--icons-only', $icons);
        $this->assertStringNotContainsString('class="fss-label"', $icons);
        $this->assertStringContainsString('title="Facebook"', $icons);

        $float = $this->render(['position' => 'float_right', 'label_typo_size' => '15', 'label_typo_weight' => '700'], ['postPermalink' => 'https://a.test/x']);
        $this->assertStringContainsString('fss--float fss--float-right', $float);
        $this->assertStringContainsString('role="complementary"', $float);
        $this->assertStringContainsString('#fss-ss1 .fss-btn{font-weight: 700; font-size: 15px;}', $float);

        // The canvas shows a floating bar in place, unwired, and says so.
        $preview = $this->render(['position' => 'float_left', 'networks' => ['facebook', 'native']], [], true);
        $this->assertMatchesRegularExpression('/<div id="fss-ss1" class="fss fss--brand"\s/', $preview, 'not floating in the canvas');
        $this->assertStringNotContainsString('data-fss-open', $preview);
        $this->assertStringNotContainsString('<script>', $preview);
        $this->assertStringContainsString('floats at the left edge', $preview);
        $this->assertStringContainsString('"More" appears only on devices with a share menu', $preview);
        $this->assertStringContainsString('No share buttons chosen', $this->render(['networks' => []], [], true));
    }

    public function test_inside_a_post_grid_card_each_card_shares_its_own_post(): void
    {
        $post = $this->makePost(['title' => 'Grid story']);
        $permalink = get_falcon_permalink($post);
        $html = view('falcon-cms::frontend.builder.render', ['layout' => [[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'sx', 'type' => 'social_share', 'settings' => ['networks' => ['linkedin']],
            ]]]],
        ]], 'postPermalink' => $permalink, 'postTitle' => 'Grid story'])->render();

        $this->assertStringContainsString('linkedin.com/sharing/share-offsite/?url='.rawurlencode($permalink), $html);
        $this->assertStringContainsString('falcon-custom-block', $html);
    }

    public function test_every_setting_survives_the_builder_shortcode_round_trip(): void
    {
        $settings = [
            'networks' => ['x_twitter', 'telegram', 'email', 'print'], 'share_source' => 'custom', 'custom_url' => 'https://example.com/a?b=1&c=2',
            'share_text' => 'Look ঘর', 'hashtags' => 'a,b', 'via' => 'brand', 'heading' => 'Share:', 'labels' => 'hide',
            'copied_text' => 'Done', 'open_in' => 'tab', 'design' => 'outline', 'color_mode' => 'custom', 'btn_color' => '#101010',
            'btn_text' => '#fefefe', 'btn_hover' => '#202020', 'position' => 'float_left', 'layout' => 'column', 'stretch' => 'fill',
            'align' => 'center', 'align_tablet' => 'flex-end', 'size' => 'large', 'shape' => 'pill', 'gap' => 4,
            'heading_color' => '#333333', 'heading_typo_size' => '18', 'label_typo_family' => 'Inter, sans-serif', 'margin_bottom' => 24,
        ];
        $layout = [['type' => 'container', 'settings' => [], 'columns' => [['settings' => [], 'elements' => [
            ['id' => 'ssx', 'type' => SocialShare::TYPE, 'settings' => $settings],
        ]]]]];

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout))), true);
        $restored = $back[0]['columns'][0]['elements'][0]['settings'];

        foreach ($settings as $key => $value) {
            $this->assertEquals($value, $restored[$key] ?? null, "setting {$key} survives");
        }
    }
}
