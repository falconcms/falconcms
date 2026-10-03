<?php

namespace FalconCms\Core\Tests\Feature\Performance;

use FalconCms\Core\Tests\TestCase;

/**
 * get_falcon_builder_font_weights() reports only the weights a layout actually uses, so the
 * theme asks Google Fonts for those instead of all nine (100–900) per family. The result is
 * always a subset of the full range, so a weight in use can never go missing — verified here
 * for numeric, named, nested and non-weight keys, and for the URL builder that consumes it.
 */
class FontWeightTrimTest extends TestCase
{
    public function test_numeric_weights_are_collected_and_deduped(): void
    {
        $w = get_falcon_builder_font_weights([
            'fontWeight' => '600',
            'children' => [
                ['settings' => ['titleWeight' => 800, 'subWeight' => '600']],
            ],
            'variant' => '400',
        ]);
        sort($w);
        $this->assertSame([400, 600, 800], $w);
    }

    public function test_named_weights_map_to_their_css_number(): void
    {
        $w = get_falcon_builder_font_weights([
            'fontWeight' => 'Light',          // 300
            'headingWeight' => 'Semi Bold',   // 600
            'ctaWeight' => 'bold',            // 700
            'noteWeight' => 'normal',         // 400
            'heroWeight' => 'Black',          // 900
        ]);
        sort($w);
        $this->assertSame([300, 400, 600, 700, 900], $w);
    }

    public function test_non_weight_keys_and_out_of_range_values_are_ignored(): void
    {
        $w = get_falcon_builder_font_weights([
            'color' => '#fff',
            'fontSize' => '16px',
            'borderWidth' => '2',    // not a font weight, but ends in "Width" not "Weight"
            'fontWeight' => '1000',  // out of range
            'lineWeight' => '350',   // valid-looking key, kept (we err toward keeping)
        ]);
        sort($w);
        $this->assertSame([350], $w, 'only in-range weight-named keys survive');
    }

    public function test_empty_or_unparseable_layout_yields_no_weights(): void
    {
        $this->assertSame([], get_falcon_builder_font_weights([]));
        $this->assertSame([], get_falcon_builder_font_weights('not an array'));
    }

    public function test_url_builder_honours_the_trimmed_weight_string(): void
    {
        $url = falcon_google_font_url(['Roboto'], '400;700');
        $this->assertStringContainsString('family=Roboto:wght@400;700', $url);
        $this->assertStringNotContainsString('100;200;300', $url, 'untrimmed weights are not requested');
        $this->assertStringContainsString('display=swap', $url);
    }
}
