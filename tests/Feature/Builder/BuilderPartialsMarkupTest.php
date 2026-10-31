<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The builder's panels are one Vue template stitched together from many Blade partials. One
 * missing </div> in any of them — a Table of Contents setting added in 2.8.16 lost one — breaks
 * the v-if / v-else-if chain of every panel after it: each element's settings then showed the
 * fields of a dozen other elements, and its Design and Extra tabs came up empty. Nothing fails
 * on the server, so it is caught here.
 */
class BuilderPartialsMarkupTest extends TestCase
{
    public function test_every_builder_partial_closes_the_divs_it_opens(): void
    {
        $files = glob(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/{,*/,*/*/}*.blade.php', GLOB_BRACE);
        $this->assertNotEmpty($files);

        // Opens its wrapper in both arms of @if($pcMode) … @else and closes it once.
        $twoArmed = ['container/row.blade.php' => 1];

        foreach ($files as $file) {
            $name = basename(dirname($file)).'/'.basename($file);
            $markup = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file));
            $markup = preg_replace('/<!--.*?-->/s', '', $markup);

            $this->assertSame(
                preg_match_all('/<div\b/', $markup) - ($twoArmed[$name] ?? 0),
                preg_match_all('#</div>#', $markup),
                $name.' opens and closes a different number of <div>s'
            );
        }
    }
}
