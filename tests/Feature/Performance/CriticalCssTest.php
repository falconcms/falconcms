<?php

namespace FalconCms\Core\Tests\Feature\Performance;

use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Support\CriticalCss;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * "Critical CSS" (perf_critical_css): the compiled stylesheet's rules for a page go inline and
 * the whole file loads without holding up the first paint.
 */
class CriticalCssTest extends TestCase
{
    private string $file;

    private bool $published;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = public_path('vendor/falcon-cms/css/falcon-tailwind.css');
        $this->published = is_file($this->file);
        if (!$this->published) {
            File::ensureDirectoryExists(dirname($this->file));
            File::copy(__DIR__.'/../../../public/assets/css/falcon-tailwind.css', $this->file);
        }
    }

    protected function tearDown(): void
    {
        if (!$this->published) {
            File::delete($this->file);
        }
        parent::tearDown();
    }

    private function optimise(string $html, string $on = '1'): string
    {
        $this->setCmsOptions(['perf_critical_css' => $on]);

        return (new HtmlOptimizeMiddleware)->handle(Request::create('/a-page', 'GET'),
            fn () => new Response($html, 200, ['Content-Type' => 'text/html']))->getContent();
    }

    private function page(string $body): string
    {
        return '<html><head><link rel="stylesheet" href="'.asset('vendor/falcon-cms/css/falcon-tailwind.css').'?v=1"></head><body>'.$body.'</body></html>';
    }

    public function test_class_names_are_read_from_selectors_the_way_tailwind_escapes_them(): void
    {
        $this->assertSame(['md:flex'], CriticalCss::classesOf('.md\:flex'));
        $this->assertSame(['w-1/2'], CriticalCss::classesOf('.w-1\/2'));
        $this->assertSame(['hover:bg-blue-500'], CriticalCss::classesOf('.hover\:bg-blue-500:hover'));
        $this->assertSame(['group', 'group-hover:block'], CriticalCss::classesOf('.group:hover .group-hover\:block'));
        $this->assertSame(['2xl:text-lg'], CriticalCss::classesOf('.\32 xl\:text-lg'), 'a leading digit is hex-escaped');
        $this->assertSame(['space-y-4'], CriticalCss::classesOf('.space-y-4>:not([hidden])~:not([hidden])'));
        $this->assertSame(['prose'], CriticalCss::classesOf('.prose :where(a):not(.not-prose)'), 'what :not() names is not required');
        $this->assertSame([], CriticalCss::classesOf('html,body'));
    }

    public function test_the_page_gets_its_own_rules_inline_and_the_file_without_blocking(): void
    {
        $out = $this->optimise($this->page('<div class="flex md:hidden"><p class="text-center">Hi</p></div>'));

        $this->assertMatchesRegularExpression('/<style id="falcon-critical-css">.*\.flex\{display:flex\}/s', $out);
        $this->assertStringContainsString('.text-center{', $out);
        $this->assertStringContainsString('.md\:hidden{display:none}', $out, 'responsive rules come inside their @media');
        $this->assertStringNotContainsString('.grid{', $out, 'a class the page never uses');
        $this->assertStringContainsString('rel="preload"', $out);
        $this->assertStringContainsString("onload=\"this.onload=null;this.rel='stylesheet'\"", $out);
        $this->assertStringContainsString('<noscript><link rel="stylesheet"', $out);
        $this->assertDoesNotMatchRegularExpression('/<link rel="stylesheet" href="[^"]*falcon-tailwind[^>]*>(?!<\/noscript>)/', $out, 'no blocking link left outside <noscript>');
    }

    public function test_the_reset_is_always_kept(): void
    {
        $out = $this->optimise($this->page('<p>Plain.</p>'));

        $this->assertStringContainsString('--tw-border-spacing-x', $out, 'the *, ::before, ::after defaults');
        $this->assertLessThan(20000, strlen($out), 'a plain page carries a fraction of the file');
    }

    public function test_classes_written_in_alpine_or_scripts_count(): void
    {
        $out = $this->optimise($this->page('<div x-bind:class="open ? \'grid\' : \'hidden\'"></div><script>el.classList.add("translate-x-0")</script>'));

        $this->assertStringContainsString('.grid{', $out);
        $this->assertStringContainsString('.hidden{', $out);
        $this->assertStringContainsString('.translate-x-0{', $out);
    }

    public function test_the_bodys_styles_move_to_the_head_in_their_order(): void
    {
        $out = $this->optimise($this->page('<nav class="flex">Menu</nav><style id="one">.a{color:red}</style><p>Text</p><style id="two">.a{color:blue}</style>'));

        $head = substr($out, 0, (int) strpos($out, '</head>'));
        $this->assertStringContainsString('<style id="one">', $head);
        $this->assertStringContainsString('<style id="two">', $head);
        $this->assertLessThan(strpos($head, 'id="two"'), strpos($head, 'id="one"'), 'the later rule still wins');
        $this->assertGreaterThan(strpos($head, 'falcon-critical-css'), strpos($head, 'id="one"'), 'after the compiled rules, as they were');
        $this->assertStringContainsString('<nav class="flex">Menu</nav><p>Text</p>', $out);
    }

    public function test_styles_that_are_not_the_pages_own_stay_where_they_are(): void
    {
        $kept = [
            '<template><style id="k">.a{}</style></template>',
            '<noscript><style id="k">.a{}</style></noscript>',
            '<svg><style id="k">.a{}</style></svg>',
            '<textarea><style id="k">.a{}</style></textarea>',
            '<pre><style id="k">.a{}</style></pre>',
            '<script>var s = "<style id=\"k\">.a{}</style>";</script>',
            '<!-- <style id="k">.a{}</style> -->',
        ];
        foreach ($kept as $markup) {
            $out = $this->optimise($this->page('<div class="flex"></div>'.$markup));
            $this->assertGreaterThan(strpos($out, '</head>'), strpos($out, 'id=', (int) strpos($out, '</head>')), $markup);
            $this->assertStringContainsString($markup, $out);
        }
    }

    public function test_a_style_after_a_stylesheet_in_the_body_keeps_its_place_in_the_cascade(): void
    {
        $body = '<style id="before">.a{}</style><link rel="stylesheet" href="/late.css"><style id="after">.a{}</style>';
        $out = $this->optimise($this->page('<div class="flex"></div>'.$body));

        $headEnd = (int) strpos($out, '</head>');
        $this->assertLessThan($headEnd, strpos($out, 'id="before"'));
        $this->assertGreaterThan(strpos($out, '/late.css'), strpos($out, 'id="after"'));
    }

    public function test_the_styles_stay_in_the_body_with_critical_css_off(): void
    {
        $html = $this->page('<style id="one">.a{}</style>');
        $this->assertSame($html, $this->optimise($html, '0'));
    }

    public function test_off_by_default_and_other_stylesheets_are_left_alone(): void
    {
        $html = $this->page('<div class="flex"></div>');
        $this->assertSame($html, $this->optimise($html, '0'));

        $theirs = '<html><head><link rel="stylesheet" href="/css/theme.css"></head><body class="flex"></body></html>';
        $this->assertSame($theirs, $this->optimise($theirs), 'only the compiled Falcon stylesheet is cut down');
    }
}
