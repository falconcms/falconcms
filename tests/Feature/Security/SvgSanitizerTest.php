<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Support\SvgSanitizer;
use FalconCms\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What survives an SVG on its way into the media library.
 *
 * An SVG is a document that happens to look like a picture: it can carry <script>, inline
 * event handlers and javascript: links, and once it is in the library it is served from the
 * site's own origin and embedded in pages every visitor loads. That is stored XSS with an
 * image icon next to it.
 *
 * SVG is off by default for exactly this reason; a site that turns it on gets the file
 * rewritten through here first. Every rule is pinned, and so is the part that matters just
 * as much — that a legitimate icon comes out the other side still usable.
 */
class SvgSanitizerTest extends TestCase
{
    private const HONEST = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
        .'<path d="M4 4h16v16H4z" fill="#d68a2a"/><circle cx="12" cy="12" r="5"/></svg>';

    // ── what must not survive ────────────────────────────────────────────────

    public function test_a_script_element_is_removed(): void
    {
        $out = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>'
        );

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringContainsString('<path', $out, 'the drawing itself should survive');
    }

    public function test_a_self_closing_script_tag_is_removed(): void
    {
        $out = SvgSanitizer::clean('<svg><script src="https://evil.test/x.js" /><path d="M0 0"/></svg>');

        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('evil.test', $out);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function executableElements(): array
    {
        return [
            'script' => ['script'],
            'foreignObject' => ['foreignObject'],
            'iframe' => ['iframe'],
            'object' => ['object'],
            'embed' => ['embed'],
            'handler' => ['handler'],
        ];
    }

    #[DataProvider('executableElements')]
    public function test_every_executable_element_is_removed(string $element): void
    {
        $out = SvgSanitizer::clean(
            '<svg><'.$element.' foo="bar">payload</'.$element.'><path d="M0 0"/></svg>'
        );

        $this->assertStringNotContainsStringIgnoringCase('<'.$element, $out, $element.' survived');
        $this->assertStringNotContainsString('payload', $out);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function eventHandlers(): array
    {
        return [
            'double quoted' => ['<svg><path onload="alert(1)" d="M0 0"/></svg>'],
            'single quoted' => ["<svg><path onclick='alert(1)' d='M0 0'/></svg>"],
            'unquoted' => ['<svg><path onmouseover=alert(1) d="M0 0"/></svg>'],
            'mixed case' => ['<svg><path OnLoad="alert(1)" d="M0 0"/></svg>'],
            'spaced' => ['<svg><path onload = "alert(1)" d="M0 0"/></svg>'],
        ];
    }

    #[DataProvider('eventHandlers')]
    public function test_inline_event_handlers_are_removed(string $svg): void
    {
        $out = SvgSanitizer::clean($svg);

        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $out);
    }

    public function test_a_javascript_link_is_removed(): void
    {
        $out = SvgSanitizer::clean('<svg><a href="javascript:alert(1)"><path d="M0 0"/></a></svg>');

        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_a_javascript_xlink_is_removed(): void
    {
        $out = SvgSanitizer::clean('<svg><a xlink:href="javascript:alert(1)"><path d="M0 0"/></a></svg>');

        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_several_payloads_at_once_all_go(): void
    {
        $out = SvgSanitizer::clean(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<script>one()</script>'
            .'<path onload="two()" d="M0 0"/>'
            .'<a href="javascript:three()"><circle r="1"/></a>'
            .'<foreignObject><body>four</body></foreignObject>'
            .'</svg>'
        );

        foreach (['one()', 'two()', 'three()', 'four', '<script', 'javascript:', 'foreignObject'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $out, $gone.' survived');
        }
        $this->assertStringContainsString('<path', $out);
        $this->assertStringContainsString('<circle', $out);
    }

    // ── what must survive ────────────────────────────────────────────────────

    public function test_an_honest_icon_comes_through_intact(): void
    {
        $out = SvgSanitizer::clean(self::HONEST);

        $this->assertStringContainsString('<svg', $out);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $out);
        $this->assertStringContainsString('fill="#d68a2a"', $out);
        $this->assertStringContainsString('<circle', $out);
    }

    public function test_an_ordinary_link_is_left_alone(): void
    {
        $out = SvgSanitizer::clean('<svg><a href="https://falconcms.com"><path d="M0 0"/></a></svg>');

        $this->assertStringContainsString('https://falconcms.com', $out);
    }

    public function test_the_drawing_attributes_are_all_left_alone(): void
    {
        $out = SvgSanitizer::clean(
            '<svg viewBox="0 0 10 10"><stop offset="0.5" stop-color="#fff"/>'
            .'<path d="M0 0" fill="none" stroke="#000" stroke-width="2" opacity=".5" '
            .'transform="rotate(45)" style="mix-blend-mode:multiply"/></svg>'
        );

        foreach (['offset="0.5"', 'stop-color', 'fill="none"', 'stroke-width="2"',
            'opacity=".5"', 'transform="rotate(45)"', 'mix-blend-mode'] as $kept) {
            $this->assertStringContainsString($kept, $out, $kept.' was eaten');
        }
    }

    public function test_any_attribute_named_like_a_handler_is_stripped_even_if_it_is_not_one(): void
    {
        // The handler rule is `on<letters>=`, so an invented attribute like once="1" goes too.
        // That over-reach is deliberate and kept: in SVG every real attribute beginning with
        // "on" IS an event handler, so nothing legitimate is lost — and the alternative, an
        // allow-list of known handler names, silently lets through whatever a future browser
        // adds. Pinned here so the breadth is not later "fixed" into a hole.
        $out = SvgSanitizer::clean('<svg><path once="1" d="M0 0"/></svg>');

        $this->assertStringNotContainsString('once', $out);
        $this->assertStringContainsString('d="M0 0"', $out, 'only the on* attribute should go');
    }

    // ── what is refused outright ─────────────────────────────────────────────

    public function test_anything_that_is_not_an_svg_comes_back_empty(): void
    {
        foreach ([null, '', 'not markup at all', '<div>hello</div>', '<svg>unclosed'] as $input) {
            $this->assertSame('', SvgSanitizer::clean($input),
                'a caller must be able to tell "nothing usable here" from "cleaned"');
        }
    }

    public function test_markup_wrapped_round_an_svg_is_dropped_down_to_the_svg(): void
    {
        $out = SvgSanitizer::clean('<html><body>before<svg><path d="M0 0"/></svg>after</body></html>');

        $this->assertStringStartsWith('<svg', $out);
        $this->assertStringEndsWith('</svg>', $out);
        $this->assertStringNotContainsString('before', $out);
        $this->assertStringNotContainsString('after', $out);
    }

    public function test_cleaning_its_own_output_changes_nothing(): void
    {
        // Every door into the library runs this, and a file can go through more than one.
        $once = SvgSanitizer::clean('<svg><script>x()</script><path onload="y()" d="M0 0"/></svg>');

        $this->assertSame($once, SvgSanitizer::clean($once));
    }
}
