<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * The <title> and meta tags a page prints from its SEO fields.
 *
 * An SEO title of "Free, Pro & Agency Plans" came out of the page source as
 * "Pro &amp; Agency" in the <title>, og:title and twitter:title: Blade's {{ }} encodes every
 * "&". That is valid HTML, but it is not what the editor typed, and a value that already held
 * an entity (an excerpt or imported title with "&amp;") was escaped again into "&amp;amp;",
 * which every reader shows literally. The same title also ended "| FalconCMS - FalconCMS",
 * because the site name was appended to a title that already carried it.
 */
class SeoMetaEscapingTest extends TestCase
{
    private function render(array $seo, array $post = []): string
    {
        update_cms_option('site_title', 'FalconCMS');
        update_cms_option('seo_separator', '-');

        $post = (object) array_merge([
            'type' => 'page',
            'title' => 'Pricing',
            'excerpt' => null,
            'content' => '',
            'featured_image' => null,
            'seo_meta' => $seo,
        ], $post);

        return view('falcon-cms::components.frontend.seo-meta', ['post' => $post])->render();
    }

    public function test_an_ampersand_in_the_seo_fields_stays_literal(): void
    {
        $html = $this->render([
            'title' => 'Laravel CMS Pricing – Free, Pro & Agency Plans',
            'description' => 'Compare Free, Pro & Agency plans.',
        ]);

        $this->assertStringContainsString('<title>Laravel CMS Pricing – Free, Pro & Agency Plans - FalconCMS</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Compare Free, Pro & Agency plans.">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Laravel CMS Pricing – Free, Pro & Agency Plans">', $html);
        $this->assertStringContainsString('<meta property="twitter:title" content="Laravel CMS Pricing – Free, Pro & Agency Plans">', $html);
        $this->assertStringNotContainsString('&amp;', $html);
    }

    public function test_a_value_that_already_holds_an_entity_is_not_escaped_twice(): void
    {
        $html = $this->render([], ['title' => 'Tips &amp; Tricks', 'excerpt' => 'Fast &amp; simple']);

        $this->assertStringContainsString('<title>Tips & Tricks - FalconCMS</title>', $html);
        $this->assertStringContainsString('content="Fast & simple"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_markup_and_quotes_are_still_escaped(): void
    {
        $html = $this->render([
            'title' => '"><script>alert(1)</script>',
            'description' => 'AT&T &lt;b&gt; "quoted"',
        ]);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
        // "&T" could read as a character reference, so that one "&" is still encoded.
        $this->assertStringContainsString('content="AT&amp;T &lt;b&gt; &quot;quoted&quot;"', $html);
    }

    public function test_the_site_name_is_not_appended_twice(): void
    {
        $html = $this->render(['title' => 'Laravel CMS Pricing | FalconCMS']);

        $this->assertStringContainsString('<title>Laravel CMS Pricing | FalconCMS</title>', $html);
        $this->assertStringContainsString('<title>Pricing - FalconCMS</title>', $this->render([]));
    }

    public function test_the_twitter_handle_is_printed_not_the_blade_source(): void
    {
        update_cms_option('twitter_handle', 'falconcms');

        $html = $this->render([]);

        $this->assertStringContainsString('<meta property="twitter:site" content="@falconcms">', $html);
    }
}
