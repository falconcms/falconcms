<?php

namespace FalconCms\Core\Tests\Feature\Performance;

use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function test_lazy_images_skips_the_first_and_respects_existing(): void
    {
        $html = '<img src="/hero.jpg"><img src="/b.jpg"><img src="/c.jpg" loading="eager">';
        $out = $this->opt($html, ['perf_lazy_images' => '1']);

        $this->assertStringContainsString('<img src="/hero.jpg">', $out, 'first image stays eager');
        $this->assertStringContainsString('<img src="/b.jpg" loading="lazy" decoding="async">', $out);
        $this->assertStringContainsString('loading="eager"', $out, 'an explicit loading is left alone');
        $this->assertSame(1, substr_count($out, 'loading="lazy"'));
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
        $user = \App\Models\User::forceCreate([
            'name' => 'U', 'email' => 'perf@example.test', 'password' => 'secret',
            'role_id' => (int) \Illuminate\Support\Facades\DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);
        $this->actingAs($user);

        $out = $this->opt('<div>   <p>Hi</p>   </div>', ['perf_minify_html' => '1']);
        $this->assertStringContainsString('<div><p>Hi</p></div>', $out);
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
