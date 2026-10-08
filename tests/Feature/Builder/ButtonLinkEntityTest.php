<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * A button link pasted from an HTML snippet.
 *
 * Lemon Squeezy's checkout embed gives the URL as "?embed=1&amp;media=0". Stored that way and
 * escaped again by Blade, the page printed "&amp;amp;media=0", so the browser sent a parameter
 * called "amp;media" and the checkout ignored it.
 */
class ButtonLinkEntityTest extends TestCase
{
    private function render(string $url): string
    {
        return view('falcon-cms::frontend.builder.elements.button', ['el' => [
            'id' => 'b1', 'type' => 'button', 'settings' => ['text' => 'Buy', 'linkUrl' => $url],
        ]])->render();
    }

    public function test_an_entity_encoded_url_is_encoded_once(): void
    {
        $html = $this->render('https://example.test/buy?embed=1&amp;media=0');

        $this->assertStringContainsString('href="https://example.test/buy?embed=1&amp;media=0"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_a_plain_url_is_unchanged(): void
    {
        $html = $this->render('https://example.test/buy?embed=1&media=0');

        $this->assertStringContainsString('href="https://example.test/buy?embed=1&amp;media=0"', $html);
    }

    public function test_a_decoded_quote_cannot_break_out_of_the_attribute(): void
    {
        $html = $this->render('https://example.test/&quot; onmouseover=&quot;alert(1)');

        $this->assertStringNotContainsString('" onmouseover="', $html);
    }
}
