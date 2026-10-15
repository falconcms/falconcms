<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * The shortcode registry: attributes (quoted values with spaces included) and enclosing pairs.
 */
class ShortcodeTest extends TestCase
{
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->registered = $GLOBALS['__falcon_shortcodes'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['__falcon_shortcodes'] = $this->registered;
        parent::tearDown();
    }

    public function test_attributes_keep_spaces_inside_quotes(): void
    {
        $this->assertSame(
            ['title' => 'Trade login', 'apply' => 'no', 'redirect' => '/wholesale/portal', 'note' => "It's here"],
            falcon_parse_shortcode_atts(' title="Trade login" apply=no redirect=\'/wholesale/portal\' note=&quot;It&#039;s here&quot;')
        );
        // Curly quotes from a word processor.
        $this->assertSame(['title' => 'Hello world'], falcon_parse_shortcode_atts(' title=“Hello world”'));
        $this->assertSame([], falcon_parse_shortcode_atts(''));
    }

    public function test_a_shortcode_written_as_a_pair_gets_its_content(): void
    {
        add_falcon_shortcode('test_box', fn ($atts, $content = null) => '<div class="'.($atts['class'] ?? 'box').'">'.($content ?? '·').'</div>');

        $this->assertSame(
            '<p>A</p><div class="note">Inside <b>here</b></div><p>B</p><div class="box">·</div>',
            falcon_do_shortcodes('<p>A</p>[test_box class="note"]Inside <b>here</b>[/test_box]<p>B</p>[test_box]')
        );
        // Across lines, and two pairs in one text.
        $this->assertSame('<div class="box">1
2</div> <div class="box">3</div>', falcon_do_shortcodes("[test_box]1\n2[/test_box] [test_box]3[/test_box]"));
    }

    public function test_a_one_argument_callback_still_works_as_a_pair(): void
    {
        add_falcon_shortcode('test_hello', fn ($atts) => 'Hello '.($atts['name'] ?? 'you'));

        $this->assertSame('Hello Rahim and Hello you', falcon_do_shortcodes('[test_hello name="Rahim"]ignored[/test_hello] and [test_hello]'));
    }
}
