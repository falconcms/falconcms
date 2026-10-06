<?php

namespace FalconCms\Core\Tests\Feature\Performance;

use FalconCms\Core\Http\Middleware\HtmlOptimizeMiddleware;
use FalconCms\Core\Http\Middleware\PageCacheMiddleware;
use FalconCms\Core\Support\LocalGoogleFonts;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * "Host Google Fonts Locally" (perf_local_google_fonts): the fonts come from the site itself,
 * their @font-face rules inline, and the visitor's browser never contacts Google.
 */
class LocalGoogleFontsTest extends TestCase
{
    private const URL = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap';

    private const CSS = "@font-face{font-family:'Inter';font-style:normal;font-weight:400;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/aaa.woff2) format('woff2');unicode-range:U+0000-00FF}\n"
        ."@font-face{font-family:'Inter';font-style:normal;font-weight:700;font-display:swap;src:url(https://fonts.gstatic.com/s/inter/v20/bbb.woff2) format('woff2');unicode-range:U+0000-00FF}";

    protected function setUp(): void
    {
        parent::setUp();
        LocalGoogleFonts::clear();
    }

    protected function tearDown(): void
    {
        LocalGoogleFonts::clear();
        parent::tearDown();
    }

    private function fakeGoogle(int $status = 200): void
    {
        Http::fake([
            'fonts.googleapis.com/*' => Http::response(self::CSS, $status),
            'fonts.gstatic.com/*' => Http::response('wOF2-font-bytes', 200),
        ]);
    }

    private function page(): string
    {
        return '<html><head><link rel="preconnect" href="https://fonts.googleapis.com">'
            .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link href="'.htmlspecialchars(self::URL).'" rel="stylesheet"></head><body>Hi</body></html>';
    }

    private function optimise(string $html): Response
    {
        $this->setCmsOptions(['perf_local_google_fonts' => '1']);

        return (new HtmlOptimizeMiddleware)->handle(Request::create('/a-page', 'GET'),
            fn () => new Response($html, 200, ['Content-Type' => 'text/html']));
    }

    public function test_a_stylesheet_and_its_fonts_are_copied_to_the_site(): void
    {
        $this->fakeGoogle();

        $this->assertTrue(LocalGoogleFonts::download(self::URL));

        $css = LocalGoogleFonts::css(self::URL);
        $this->assertNotNull($css);
        $this->assertStringNotContainsString('fonts.gstatic.com', $css);
        $this->assertSame(2, substr_count($css, asset(LocalGoogleFonts::DIR).'/'));
        $this->assertStringContainsString('font-display:swap', $css);
        $this->assertCount(3, glob(public_path(LocalGoogleFonts::DIR.'/*/*')), 'two fonts and the stylesheet');
    }

    public function test_the_first_visit_keeps_googles_link_and_is_not_page_cached(): void
    {
        $this->fakeGoogle();

        $response = $this->optimise($this->page());

        $this->assertStringContainsString('fonts.googleapis.com/css2', $response->getContent(), 'nothing is downloaded while a visitor waits');
        $this->assertTrue($response->headers->has(HtmlOptimizeMiddleware::PROVISIONAL_HEADER));
        Http::assertNothingSent();

        // after the response: the copy is made
        app()->terminate();
        $this->assertNotNull(LocalGoogleFonts::css(self::URL));
    }

    public function test_after_that_the_fonts_are_inline_and_google_is_never_contacted(): void
    {
        $this->fakeGoogle();
        LocalGoogleFonts::download(self::URL);

        $response = $this->optimise($this->page());
        $html = $response->getContent();

        $this->assertStringContainsString('<style class="falcon-local-fonts">@font-face', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html, 'the preconnect hints go too');
        $this->assertFalse($response->headers->has(HtmlOptimizeMiddleware::PROVISIONAL_HEADER));
    }

    public function test_a_failed_download_keeps_googles_fonts_and_waits_an_hour_to_retry(): void
    {
        $this->fakeGoogle(500);

        $this->assertFalse(LocalGoogleFonts::download(self::URL));
        $this->assertNull(LocalGoogleFonts::css(self::URL));
        $this->assertSame([], glob(public_path(LocalGoogleFonts::DIR.'/*')), 'no half-written copy left behind');

        LocalGoogleFonts::scheduleDownload(self::URL);
        $this->assertTrue(Cache::has('falcon_gfonts_try_'.substr(sha1(self::URL), 0, 16)));
    }

    public function test_only_google_fonts_stylesheets_are_touched(): void
    {
        $this->assertFalse(LocalGoogleFonts::isGoogleCss('https://example.com/css2?family=Inter'));
        $this->assertFalse(LocalGoogleFonts::isGoogleCss('https://fonts.googleapis.com.evil.test/css2?family=Inter'));
        $this->assertTrue(LocalGoogleFonts::isGoogleCss('//fonts.googleapis.com/css2?family=Inter&amp;display=swap'));
        $this->assertFalse(LocalGoogleFonts::download('https://example.com/fonts.css'));
    }

    public function test_the_page_cache_does_not_keep_a_stand_in(): void
    {
        $this->setCmsOptions(['performance_static_caching' => '1']);
        $request = Request::create('/cached-page', 'GET');
        $standIn = function () {
            $r = new Response('<html>stand-in</html>', 200, ['Content-Type' => 'text/html']);
            $r->headers->set(HtmlOptimizeMiddleware::PROVISIONAL_HEADER, '1');

            return $r;
        };

        $response = (new PageCacheMiddleware)->handle($request, $standIn);

        $this->assertFalse(Cache::has('page_cache_'.md5($request->fullUrl())));
        $this->assertFalse($response->headers->has(HtmlOptimizeMiddleware::PROVISIONAL_HEADER), 'the marker never reaches the visitor');
    }
}
