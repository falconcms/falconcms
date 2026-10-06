<?php

namespace FalconCms\Core\Tests\Feature\Performance;

use App\Models\User;
use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The optional front-end HTML optimisations — lazy images, deferred scripts, minified HTML.
 * Each is off by default and driven by a Customizer → Performance toggle, and the work is done
 * on the finished HTML so it applies the same to the default theme and to a builder page.
 */
class HtmlOptimizeTest extends TestCase
{
    private function opt(string $html, array $options): string
    {
        $this->setCmsOptions($options);
        $mw = new HtmlOptimizeMiddleware;
        $req = Request::create('/some-page', 'GET');
        $res = $mw->handle($req, fn () => new Response($html, 200, ['Content-Type' => 'text/html']));

        return $res->getContent();
    }

    public function test_nothing_changes_when_every_toggle_is_off(): void
    {
        $html = '<html>  <body>   <img src="/a.jpg">  <script src="/x.js"></script>  </body></html>';
        $this->assertSame($html, $this->opt($html, [
            'perf_lazy_images' => '0', 'perf_defer_js' => '0', 'perf_minify_html' => '0',
        ]));
    }

    public function test_lazy_images_skips_the_first_three_and_respects_existing(): void
    {
        // logo, hero, the image beside the hero: above the fold, so never lazy
        $html = '<img src="/logo.png"><img src="/hero.jpg"><img src="/side.jpg"><img src="/b.jpg"><img src="/c.jpg" loading="eager">';
        $out = $this->opt($html, ['perf_lazy_images' => '1']);

        $this->assertStringContainsString('<img src="/logo.png">', $out);
        $this->assertStringContainsString('<img src="/hero.jpg">', $out, 'the hero, the usual largest paint, stays eager');
        $this->assertStringContainsString('<img src="/side.jpg">', $out);
        $this->assertStringContainsString('<img src="/b.jpg" loading="lazy" decoding="async">', $out);
        $this->assertStringContainsString('loading="eager"', $out, 'an explicit loading is left alone');
        $this->assertSame(1, substr_count($out, 'loading="lazy"'));
    }

    /** A file moved out of an inline <script> opts out, so it still runs where the inline one did. */
    public function test_a_script_marked_data_no_defer_is_never_deferred(): void
    {
        $html = '<script data-no-defer src="/plugin-assets/falcon-shop/frontend/js/wishlist.js?v=1"></script>';
        $out = $this->opt($html, ['perf_defer_js' => '1']);

        $this->assertStringContainsString($html, $out);
        $this->assertStringNotContainsString('<script defer', $out);
    }

    public function test_defer_js_adds_defer_except_to_must_run_early_scripts(): void
    {
        $html = '<script src="/vendor/falcon-cms/js/tailwind.min.js"></script>'
            .'<script src="/app.js"></script>'
            .'<script src="/already.js" defer></script>'
            .'<script>inline()</script>';
        $out = $this->opt($html, ['perf_defer_js' => '1']);

        $this->assertStringContainsString('tailwind.min.js"></script>', $out, 'tailwind is never deferred');
        $this->assertStringNotContainsString('defer src="/vendor/falcon-cms/js/tailwind', $out);
        $this->assertStringContainsString('<script defer src="/app.js">', $out);
        $this->assertSame(1, substr_count($out, 'defer src="/app.js"'), 'not doubled');
        $this->assertStringContainsString('<script src="/already.js" defer></script>', $out, 'already-deferred left as-is');
    }

    public function test_minify_collapses_whitespace_but_preserves_pre_and_script(): void
    {
        $html = "<div>   <p>Hi</p>\n\n   <pre>a\n   b</pre>   <script>var x =   1;</script></div>";
        $out = $this->opt($html, ['perf_minify_html' => '1']);

        $this->assertStringContainsString('<div><p>Hi</p>', $out);
        $this->assertStringContainsString("<pre>a\n   b</pre>", $out, 'pre whitespace is untouched');
        $this->assertStringContainsString('var x =   1;', $out, 'script whitespace is untouched');
    }

    public function test_css_minify_runs_independently_of_html_minify(): void
    {
        $html = "<style>\n  .box {\n    color: red;\n    margin: 0;\n  }\n  /* a comment */\n  .b , .c { padding : 2px ; }\n</style><div>  x  </div>";

        // CSS on, HTML off: the <style> is minified, the whitespace between tags is kept.
        $cssOnly = $this->opt($html, ['perf_minify_css' => '1', 'perf_minify_html' => '0']);
        $this->assertStringContainsString('.box{color:red;margin:0}', $cssOnly);
        $this->assertStringNotContainsString('/* a comment */', $cssOnly);
        $this->assertStringContainsString('.b,.c{padding:2px}', $cssOnly);
        $this->assertStringContainsString('<div>  x  </div>', $cssOnly, 'HTML whitespace untouched when only CSS minify is on');

        // HTML on, CSS off: tags collapse, the CSS keeps its comment and spacing.
        $htmlOnly = $this->opt($html, ['perf_minify_html' => '1', 'perf_minify_css' => '0']);
        $this->assertStringContainsString('/* a comment */', $htmlOnly, 'CSS untouched when only HTML minify is on');
    }

    public function test_optimisation_applies_to_logged_in_visitors_too(): void
    {
        // Minify/defer/lazy change delivery, not content, so a logged-in reader benefits as well.
        $user = User::forceCreate([
            'name' => 'U', 'email' => 'perf@example.test', 'password' => 'secret',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);
        $this->actingAs($user);

        $out = $this->opt('<div>   <p>Hi</p>   </div>', ['perf_minify_html' => '1']);
        $this->assertStringContainsString('<div><p>Hi</p></div>', $out);
    }

    public function test_known_lucide_icons_become_inline_svg_and_the_library_is_dropped(): void
    {
        $html = '<i data-lucide="user" class="w-5 h-5"></i>'
            .'<script src="/vendor/falcon-cms/js/lucide.min.js"></script>';
        $out = $this->opt($html, ['perf_conditional_assets' => '1']);

        $this->assertStringContainsString('<svg', $out);
        $this->assertStringContainsString('lucide lucide-user', $out);
        $this->assertStringContainsString('class="w-5 h-5 lucide lucide-user"', $out, 'sizing class carried onto the SVG');
        $this->assertStringNotContainsString('data-lucide', $out);
        $this->assertStringNotContainsString('lucide.min.js', $out, 'library dropped when every icon is inlined');
    }

    public function test_an_unknown_lucide_icon_keeps_the_library(): void
    {
        $html = '<i data-lucide="user"></i><i data-lucide="some-unknown-icon"></i>'
            .'<script src="/vendor/falcon-cms/js/lucide.min.js"></script>';
        $out = $this->opt($html, ['perf_conditional_assets' => '1']);

        $this->assertStringContainsString('lucide lucide-user', $out, 'known one is still inlined');
        $this->assertStringContainsString('data-lucide="some-unknown-icon"', $out, 'unknown left for the library');
        $this->assertStringContainsString('lucide.min.js', $out, 'library kept to draw the unknown');
    }

    public function test_lucide_is_untouched_when_the_toggle_is_off(): void
    {
        $html = '<i data-lucide="user"></i><script src="/x/lucide.min.js"></script>';
        $out = $this->opt($html, ['perf_conditional_assets' => '0']);
        $this->assertSame($html, $out);
    }

    public function test_sweetalert_is_dropped_on_a_page_that_never_calls_it(): void
    {
        $html = '<head><!--falcon-swal--><script src="/x/sweetalert2.all.min.js"></script>'
            .'<script>/*patch*/</script><!--/falcon-swal--></head><body><p>Just content</p></body>';
        $out = $this->opt($html, ['perf_conditional_assets' => '1']);

        $this->assertStringNotContainsString('sweetalert2', $out, 'bundle removed when unused');
        $this->assertStringNotContainsString('falcon-swal', $out, 'marker comments removed');
        $this->assertStringContainsString('Just content', $out);
    }

    public function test_lazy_loader_is_kept_and_does_not_pin_the_eager_bundle(): void
    {
        // The always-present lazy loader references Swal and the bundle URL; on a page with no other
        // Swal use the eager bundle is still dropped, but the lazy loader stays so the mini-cart
        // toast can fetch it on demand.
        $html = '<head>'
            .'<!--falcon-swal--><script src="/x/sweetalert2.all.min.js"></script><!--/falcon-swal-->'
            .'<!--falcon-swal-lazy--><script>window.falconToast=function(){/* loads sweetalert2 then Swal.fire */}</script><!--/falcon-swal-lazy-->'
            .'</head><body><p>content only</p></body>';
        $out = $this->opt($html, ['perf_conditional_assets' => '1']);

        $this->assertStringNotContainsString('src="/x/sweetalert2.all.min.js"', $out, 'eager bundle dropped');
        $this->assertStringContainsString('window.falconToast', $out, 'lazy loader kept');
        $this->assertStringNotContainsString('falcon-swal', $out, 'all marker comments removed');
    }

    public function test_eager_bundle_kept_when_a_page_uses_swal_alongside_the_lazy_loader(): void
    {
        $html = '<head>'
            .'<!--falcon-swal--><script src="/x/sweetalert2.all.min.js"></script><!--/falcon-swal-->'
            .'<!--falcon-swal-lazy--><script>window.falconToast=function(){}</script><!--/falcon-swal-lazy-->'
            .'</head><body><button onclick="Swal.fire({})">buy</button></body>';
        $out = $this->opt($html, ['perf_conditional_assets' => '1']);

        $this->assertStringContainsString('src="/x/sweetalert2.all.min.js"', $out, 'eager bundle kept for a page that uses Swal');
        $this->assertStringContainsString('window.falconToast', $out, 'lazy loader kept too');
        $this->assertStringNotContainsString('falcon-swal', $out, 'marker comments removed');
    }

    public function test_sweetalert_is_kept_when_the_page_calls_it(): void
    {
        $html = '<head><!--falcon-swal--><script src="/x/sweetalert2.all.min.js"></script><!--/falcon-swal--></head>'
            .'<body><button onclick="Swal.fire({text:\'hi\'})">x</button></body>';
        $out = $this->opt($html, ['perf_conditional_assets' => '1']);

        $this->assertStringContainsString('sweetalert2.all.min.js', $out, 'bundle kept when used');
        $this->assertStringNotContainsString('falcon-swal', $out, 'marker comments still removed');
    }

    public function test_sweetalert_markers_are_left_alone_when_the_toggle_is_off(): void
    {
        // Only the conditional-assets toggle governs this; with it off the block (and its markers)
        // pass through untouched.
        $html = '<head><!--falcon-swal--><script src="/x/sweetalert2.all.min.js"></script><!--/falcon-swal--></head><body>x</body>';
        $out = $this->opt($html, ['perf_conditional_assets' => '0', 'perf_minify_html' => '0']);
        $this->assertSame($html, $out);
    }

    public function test_non_html_and_admin_are_left_alone(): void
    {
        $this->setCmsOptions(['perf_minify_html' => '1']);
        $mw = new HtmlOptimizeMiddleware;

        $json = $mw->handle(Request::create('/x', 'GET'), fn () => new Response('{"a":  1}', 200, ['Content-Type' => 'application/json']));
        $this->assertSame('{"a":  1}', $json->getContent());

        $admin = $mw->handle(Request::create('/admin/x', 'GET'), fn () => new Response('<div>  <b>x</b></div>', 200, ['Content-Type' => 'text/html']));
        $this->assertSame('<div>  <b>x</b></div>', $admin->getContent());
    }
}
