<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;

/**
 * The extra icon sets (Bootstrap, Remix, Boxicons, Lucide) reach a page as just the rules for
 * the icons it shows, inline, rather than as whole render-blocking stylesheets: a page with two
 * Remix icons used to wait on 135 KB of CSS for them.
 */
class IconInlineCssTest extends TestCase
{
    public function test_only_the_icons_a_page_uses_are_included(): void
    {
        $css = falcon_icon_set_inline_css('<i class="ri-home-office-line"></i><i class="bi bi-file-earmark-post"></i>');

        $this->assertStringContainsString('.ri-home-office-line', $css);
        $this->assertStringContainsString('.bi-file-earmark-post', $css);
        $this->assertStringNotContainsString('.ri-home-line', $css, 'an icon the page does not show');
        $this->assertStringNotContainsString('lucide', $css, 'a set the page does not use');
        $this->assertLessThan(5000, strlen($css), 'a few icons should cost a few kilobytes, not a whole set');
    }

    public function test_the_font_is_declared_with_swap_and_an_absolute_url(): void
    {
        $css = falcon_icon_set_inline_css('<span class="lui-webhook"></span>');

        $this->assertMatchesRegularExpression('/@font-face\{font-display:swap;[^}]*font-family:\s*"?lucide/i', $css);
        $this->assertStringContainsString(asset('vendor/falcon-cms/webfonts/lucide.woff2'), $css);
        $this->assertStringNotContainsString('../webfonts/', $css, 'a relative URL would resolve against the page, not the stylesheet');
        $this->assertStringContainsString("font-family: 'lucide' !important", $css, 'the base rule that applies the font');
    }

    public function test_an_icon_named_outside_a_class_attribute_is_kept(): void
    {
        // an accordion swapping its open/closed icon through Alpine
        $css = falcon_icon_set_inline_css('<span :class="open ? \'ri-subtract-line\' : \'ri-add-line\'"></span>');

        $this->assertStringContainsString('.ri-subtract-line', $css);
        $this->assertStringContainsString('.ri-add-line', $css);
    }

    public function test_words_that_only_look_like_icon_classes_pull_nothing_in(): void
    {
        $this->assertSame('', falcon_icon_set_inline_css('<p>We meet bi-weekly in the big-box store.</p>'));
        $this->assertSame('', falcon_icon_set_inline_css('<i class="fa-solid fa-house"></i>'), 'Font Awesome is handled on its own');
    }

    public function test_a_page_inlines_its_icons_instead_of_linking_the_sets(): void
    {
        Post::create(['user_id' => 1, 'title' => 'Icons', 'slug' => 'icons', 'type' => 'page', 'status' => 'published', 'lang_code' => 'en',
            'content' => '<p><i class="ri-briefcase-2-line"></i> Work</p>']);

        $html = $this->get('/icons')->assertOk()->getContent();

        $this->assertStringContainsString('<style id="falcon-icon-css">', $html);
        $this->assertStringContainsString('.ri-briefcase-2-line', $html);
        $this->assertStringNotContainsString('remixicon.min.css', $html);
    }

    public function test_switching_inlining_off_links_the_sets_as_before(): void
    {
        add_falcon_filter('falcon_icon_inline_css', fn () => false);
        Post::create(['user_id' => 1, 'title' => 'Icons', 'slug' => 'icons', 'type' => 'page', 'status' => 'published', 'lang_code' => 'en',
            'content' => '<p><i class="ri-briefcase-2-line"></i> Work</p>']);

        $html = $this->get('/icons')->assertOk()->getContent();

        $this->assertStringNotContainsString('falcon-icon-css', $html);
        $this->assertStringContainsString('remixicon.min.css', $html);
    }
}
