<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Http\Controllers\Admin\PostController;
use FalconCms\Core\Tests\TestCase;
use ReflectionMethod;

/**
 * The page builder previews the header and footer in small iframes. The Falcon theme outputs just
 * the requested part, but a theme that does not understand $builderFramePart returns its whole
 * page — header, (empty) content and footer — in every frame, which showed a doubled, empty chrome.
 * sliceFramePart() keeps only the <header> (or <footer>) of that page, with its <head> so the
 * theme's CSS still styles it. These hold it to that.
 */
class FrameSliceTest extends TestCase
{
    private function slice(string $html, string $part): string
    {
        $m = new ReflectionMethod(PostController::class, 'sliceFramePart');
        $m->setAccessible(true);

        return $m->invoke(new PostController, $html, $part);
    }

    private const PAGE = '<!DOCTYPE html><html><head><style>.x{color:red}</style></head>'
        .'<body class="theme"><header class="site-head">HEAD</header>'
        .'<main>PAGE CONTENT</main>'
        .'<footer class="site-foot">FOOT</footer></body></html>';

    public function test_the_header_frame_keeps_only_the_header(): void
    {
        $out = $this->slice(self::PAGE, 'header');

        $this->assertStringContainsString('<header class="site-head">HEAD</header>', $out);
        $this->assertStringNotContainsString('PAGE CONTENT', $out, 'the page body is dropped');
        $this->assertStringNotContainsString('<footer', $out, 'the footer is dropped');
        $this->assertStringContainsString('.x{color:red}', $out, 'the head CSS is kept');
        $this->assertStringContainsString('<body class="theme">', $out, 'the body tag (and its classes) is kept');
        $this->assertStringContainsString('falconFrame:"header"', $out, 'the self-measure script reports as the header frame');
        $this->assertStringContainsString('parent.postMessage', $out, 'so the iframe can auto-size and be visible');
    }

    public function test_the_footer_frame_keeps_only_the_footer(): void
    {
        $out = $this->slice(self::PAGE, 'footer');

        $this->assertStringContainsString('<footer class="site-foot">FOOT</footer>', $out);
        $this->assertStringNotContainsString('PAGE CONTENT', $out);
        $this->assertStringNotContainsString('<header', $out);
        $this->assertStringContainsString('.x{color:red}', $out);
    }

    public function test_the_footer_frame_takes_the_last_footer_not_a_stray_earlier_one(): void
    {
        $html = '<html><head></head><body><footer>WIDGET</footer><main>x</main><footer>REAL</footer></body></html>';
        $out = $this->slice($html, 'footer');

        $this->assertStringContainsString('<footer>REAL</footer>', $out);
        $this->assertStringNotContainsString('WIDGET', $out);
    }

    public function test_a_missing_part_collapses_to_an_empty_body(): void
    {
        // No <header> on the page → the frame shows nothing rather than repeating the whole chrome.
        $html = '<html><head><style>.y{}</style></head><body><main>only content</main></body></html>';
        $out = $this->slice($html, 'header');

        $this->assertStringNotContainsString('only content', $out);
        $this->assertStringNotContainsString('<header', $out, 'no header is placed in the body');
        $this->assertStringContainsString('.y{}', $out, 'the head CSS is still kept');
    }
}
