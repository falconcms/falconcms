<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Inline Only the Icons in Use" (perf_inline_icons): the icon libraries reach a page as just the
 * rules for the icons it shows, inline, rather than as whole render-blocking stylesheets. A page
 * with two Remix icons used to wait on 135 KB of CSS for them.
 */
class IconInlineCssTest extends TestCase
{
    private function optimise(string $html, string $inline = '1'): string
    {
        $this->setCmsOptions(['perf_inline_icons' => $inline]);
        $res = (new HtmlOptimizeMiddleware)->handle(Request::create('/a-page', 'GET'),
            fn () => new Response($html, 200, ['Content-Type' => 'text/html']));

        return $res->getContent();
    }

    private function link(string $file): string
    {
        return '<link rel="stylesheet" href="'.asset('vendor/falcon-cms/css/'.$file).'">';
    }

    // ---- the helper ------------------------------------------------------------

    public function test_only_the_icons_used_are_included(): void
    {
        $css = falcon_icon_set_inline_css('<i class="ri-home-office-line"></i><i class="bi bi-file-earmark-post"></i>');

        $this->assertStringContainsString('.ri-home-office-line', $css);
        $this->assertStringContainsString('.bi-file-earmark-post', $css);
        $this->assertStringNotContainsString('.ri-home-line', $css, 'an icon the page does not show');
        $this->assertStringNotContainsString('lucide', $css, 'a set the page does not use');
        $this->assertLessThan(5000, strlen($css), 'a few icons should cost a few kilobytes, not a whole set');
    }

    public function test_font_awesome_is_cut_down_the_same_way(): void
    {
        $css = falcon_icon_set_inline_css('<i class="fa-solid fa-house"></i><i class="fa fa-reply"></i>', ['fontawesome']);

        $this->assertStringContainsString('.fa-house{', $css);
        $this->assertStringContainsString('.fa-reply', $css, 'an alias shares its rule with the current name');
        $this->assertStringNotContainsString('.fa-helicopter', $css);
        $this->assertStringContainsString('content:var(--fa)', $css, 'the base rule that draws every icon');
        $this->assertStringContainsString('.fa-spin', $css, 'helpers such as spin and sizes stay');
        $this->assertLessThan(15000, strlen($css));
    }

    public function test_the_font_is_declared_with_swap_and_an_absolute_url(): void
    {
        $css = falcon_icon_set_inline_css('<span class="lui-webhook"></span>');

        $this->assertMatchesRegularExpression('/@font-face\{font-display:swap;[^}]*font-family:\s*"?lucide/i', $css);
        $this->assertStringContainsString(asset('vendor/falcon-cms/webfonts/lucide.woff2'), $css);
        $this->assertStringNotContainsString('../webfonts/', $css, 'a relative URL would resolve against the page, not the stylesheet');
    }

    public function test_an_icon_named_outside_a_class_attribute_is_kept(): void
    {
        // an accordion swapping its open/closed icon through Alpine or an inline script
        $css = falcon_icon_set_inline_css('<span :class="open ? \'ri-subtract-line\' : \'ri-add-line\'"></span><script>i.className = "fa-solid fa-minus";</script>');

        $this->assertStringContainsString('.ri-subtract-line', $css);
        $this->assertStringContainsString('.ri-add-line', $css);
        $this->assertStringContainsString('.fa-minus', $css);
    }

    public function test_words_that_only_look_like_icon_classes_pull_nothing_in(): void
    {
        $this->assertSame('', falcon_icon_set_inline_css('<p>We meet bi-weekly in the big-box store.</p>'));
    }

    // ---- the page --------------------------------------------------------------

    public function test_the_stylesheets_become_one_inline_style(): void
    {
        $html = '<html><head>'.$this->link('font-awesome.all.min.css').$this->link('remixicon.min.css').'</head>'
            .'<body><i class="fa-solid fa-check"></i><i class="ri-briefcase-2-line"></i></body></html>';

        $out = $this->optimise($html);

        $this->assertSame(1, substr_count($out, '<style id="falcon-icon-css">'));
        $this->assertStringNotContainsString('font-awesome.all.min.css', $out);
        $this->assertStringNotContainsString('remixicon.min.css', $out);
        $this->assertStringContainsString('.fa-check{', $out);
        $this->assertStringContainsString('.ri-briefcase-2-line', $out);
        $this->assertLessThan(strpos($out, '</head>'), strpos($out, 'falcon-icon-css'), 'in the head, where the first link was');
    }

    /** A glyph code such as "\30" (fa-0) must reach the page as written, not read as a back-reference. */
    public function test_glyph_codes_with_digits_survive(): void
    {
        $out = $this->optimise('<html><head>'.$this->link('font-awesome.all.min.css').'</head><body><i class="fa-solid fa-0"></i><i class="fa-solid fa-at"></i></body></html>');

        $this->assertStringContainsString('.fa-0{--fa:"\30"}', $out);
        $this->assertStringContainsString('.fa-at{--fa:"\40"}', $out);
    }

    public function test_a_library_the_page_does_not_use_is_dropped(): void
    {
        $out = $this->optimise('<html><head>'.$this->link('font-awesome.all.min.css').'</head><body><p>Text only.</p></body></html>');

        $this->assertStringNotContainsString('font-awesome', $out);
        $this->assertStringNotContainsString('falcon-icon-css', $out);
    }

    public function test_off_by_default_and_nothing_else_is_touched(): void
    {
        $html = '<html><head>'.$this->link('remixicon.min.css').'<link rel="stylesheet" href="/css/site.css"></head><body><i class="ri-home-line"></i></body></html>';

        $this->assertSame($html, $this->optimise($html, '0'));
        $this->assertStringContainsString('/css/site.css', $this->optimise($html), "a theme's own stylesheet stays");
    }

    public function test_a_real_page_with_the_option_on(): void
    {
        $this->setCmsOptions(['perf_inline_icons' => '1']);
        Post::create(['user_id' => 1, 'title' => 'Icons', 'slug' => 'icons', 'type' => 'page', 'status' => 'published', 'lang_code' => 'en',
            'content' => '<p><i class="ri-briefcase-2-line"></i> Work</p>']);

        $html = $this->get('/icons')->assertOk()->getContent();

        $this->assertStringContainsString('<style id="falcon-icon-css">', $html);
        $this->assertStringContainsString('.ri-briefcase-2-line', $html);
        $this->assertStringNotContainsString('remixicon.min.css', $html);
        $this->assertStringNotContainsString('font-awesome.all.min.css', $html);
    }
}
