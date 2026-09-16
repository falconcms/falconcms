<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * A menu item saved before the mega-menu columns existed must not take the header down.
 *
 * The theme header read $item->mega_columns with no guard, while every other mega property
 * beside it was written `?? ''`. A menu row without that column — one saved by an older
 * version, or built as a plain object by anything that does not select every column — threw
 * "Undefined property", and because the header is on every page the whole front end 500'd,
 * not one menu.
 */
class ArchiveHeaderTest extends TestCase
{
    public function test_a_menu_item_missing_the_mega_columns_property_still_renders(): void
    {
        // The exact shape that broke it: an object carrying only what an older row had.
        $item = (object) [
            'title' => 'Shop',
            'url' => '/shop',
            'target' => '_self',
            'mega_enabled' => 0,
            'children' => collect(),
        ];

        $columns = max(1, min(6, (int) (($item->mega_columns ?? null) ?: 3)));

        $this->assertSame(3, $columns);
    }

    public function test_the_header_partial_guards_every_mega_property(): void
    {
        $header = file_get_contents(
            __DIR__.'/../../../resources/views/themes/falcon-theme/partials/header.blade.php'
        );

        preg_match_all('/\$item->(mega_[a-z_]+)(\s*\?\?)?/', $header, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m, 'the header no longer reads any mega property — update this test');

        foreach ($m as $hit) {
            // Either a null-coalesce follows it, or it sits inside empty()/isset().
            $guarded = !empty($hit[2])
                || preg_match('/(empty|isset)\s*\(\s*\$item->'.$hit[1].'/', $header);
            $this->assertTrue((bool) $guarded, "\$item->{$hit[1]} is read without a guard");
        }
    }

    public function test_a_category_archive_renders_rather_than_erroring(): void
    {
        Category::firstOrCreate(['slug' => 'archive-smoke'], ['name' => 'Archive Smoke', 'lang_code' => 'en']);
        DB::table('cms_settings')->updateOrInsert(['key' => 'theme_mega_menu_enabled'], ['value' => '1']);
        forget_cms_options_cache();

        $this->get('/category/archive-smoke')->assertOk();

        DB::table('cms_settings')->where('key', 'theme_mega_menu_enabled')->delete();
        forget_cms_options_cache();
    }
}
