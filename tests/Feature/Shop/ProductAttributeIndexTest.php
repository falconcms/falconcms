<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Attributes are stored on the product as one JSON blob, which cannot be filtered on.
 * shop_product_attribute_values is the flattened index the archive filters actually
 * query, rebuilt by falcon_sync_product_attribute_index() on every save. These tests
 * pin the shape of that index and prove the sidebar's slugs match what it holds.
 */
class ProductAttributeIndexTest extends TestCase
{
    use MakesShopFixtures;

    private Post $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = $this->makeProduct();
    }

    /**
     * @param  array<int, mixed>  $attributes
     */
    private function sync(array $attributes, string $type = 'simple'): void
    {
        DB::table('shop_products')
            ->where('post_id', $this->product->id)
            ->update(['attributes_data' => json_encode($attributes), 'type' => $type, 'product_type' => $type]);

        falcon_sync_product_attribute_index($this->shopRow($this->product));
    }

    /**
     * The index as "name_slug:value_slug" pairs.
     *
     * @return array<int, string>
     */
    private function indexed(): array
    {
        return DB::table('shop_product_attribute_values')
            ->where('post_id', $this->product->id)
            ->orderBy('name_slug')->orderBy('value_slug')
            ->get()
            ->map(fn ($row) => $row->name_slug.':'.$row->value_slug)
            ->all();
    }

    /** @param array<string, mixed> $query */
    private function matchesFilter(array $query): bool
    {
        return in_array($this->product->id, $this->filteredProductIds($query), true);
    }

    // ---- the basics ------------------------------------------------------------

    public function test_declared_values_are_indexed_and_filterable(): void
    {
        $this->sync([['name' => 'Material', 'values' => 'Cotton | Silk | Wool', 'visible' => '1', 'variation' => '0']]);

        $this->assertSame(['material:cotton', 'material:silk', 'material:wool'], $this->indexed());
        $this->assertTrue($this->matchesFilter(['attr' => ['material' => ['silk']]]));
        $this->assertFalse($this->matchesFilter(['attr' => ['material' => ['linen']]]));
    }

    public function test_show_in_filters_off_removes_the_attribute_from_the_index(): void
    {
        $this->sync([['name' => 'Material', 'values' => 'Cotton | Silk', 'visible' => '1', 'variation' => '0', 'filterable' => '0']]);

        $this->assertSame([], $this->indexed());
        $this->assertFalse($this->matchesFilter(['attr' => ['material' => ['silk']]]));
    }

    /** Products saved before the toggle existed have no key at all — they stay filterable. */
    public function test_a_missing_filterable_key_defaults_to_on(): void
    {
        $this->sync([['name' => 'Brand', 'values' => 'Acme', 'visible' => '1', 'variation' => '0']]);

        $this->assertSame(['brand:acme'], $this->indexed());
    }

    public function test_the_index_is_replaced_not_appended_to(): void
    {
        $this->sync([['name' => 'Brand', 'values' => 'Acme', 'visible' => '1', 'variation' => '0']]);
        $this->sync([['name' => 'Brand', 'values' => 'Zenith', 'visible' => '1', 'variation' => '0']]);

        $this->assertSame(['brand:zenith'], $this->indexed());
        $this->assertFalse($this->matchesFilter(['attr' => ['brand' => ['acme']]]));
    }

    // ---- variable products index what can actually be bought -------------------

    public function test_a_variable_product_indexes_its_variations_not_its_declarations(): void
    {
        $this->sync([['name' => 'Color', 'values' => 'Red | Green | Blue', 'visible' => '1', 'variation' => '1']], 'variable');

        foreach ([['Color' => 'Red'], ['Color' => 'Blue']] as $attributes) {
            $this->makeVariation($this->product, ['attributes_data' => json_encode($attributes)]);
        }

        falcon_sync_product_attribute_index($this->shopRow($this->product));

        $this->assertSame(['color:blue', 'color:red'], $this->indexed());
        $this->assertFalse($this->matchesFilter(['attr' => ['color' => ['green']]]), 'green cannot be bought');
        $this->assertTrue($this->matchesFilter(['attr' => ['color' => ['blue']]]));
    }

    public function test_a_variable_product_with_no_variations_falls_back_to_the_declared_list(): void
    {
        $this->sync([['name' => 'Color', 'values' => 'Red | Green | Blue', 'visible' => '1', 'variation' => '1']], 'variable');

        $this->assertSame(['color:blue', 'color:green', 'color:red'], $this->indexed());
    }

    // ---- slugs -----------------------------------------------------------------

    public function test_case_and_whitespace_variants_collapse_to_one_row(): void
    {
        $this->sync([['name' => 'Color', 'values' => 'Red | red | RED |  Red  ', 'visible' => '1', 'variation' => '0']]);

        $this->assertCount(1, $this->indexed());
    }

    /**
     * The bug: Str::slug() turns both "XL" and "XL+" into "xl", so one of the two sizes
     * silently vanished from the sidebar. Collisions are disambiguated with -2, -3 while
     * the label the shopper reads stays exactly as typed.
     */
    public function test_values_that_slug_identically_stay_separate(): void
    {
        $this->sync([['name' => 'Size', 'values' => 'XL | XL+ | XL++', 'visible' => '1', 'variation' => '0']]);

        $this->assertSame(['size:xl', 'size:xl-2', 'size:xl-3'], $this->indexed());

        $labels = DB::table('shop_product_attribute_values')
            ->where('post_id', $this->product->id)
            ->orderBy('value_slug')->pluck('value')->all();
        $this->assertSame(['XL', 'XL+', 'XL++'], $labels);

        $this->assertTrue($this->matchesFilter(['attr' => ['size' => ['xl']]]));
        $this->assertTrue($this->matchesFilter(['attr' => ['size' => ['xl-2']]]));
    }

    public function test_non_latin_values_are_indexed_and_keep_their_label(): void
    {
        $this->sync([['name' => 'রং', 'values' => 'নীল | সবুজ', 'visible' => '1', 'variation' => '0']]);

        $this->assertCount(2, $this->indexed());
        $this->assertTrue($this->matchesFilter(['attr' => ['rng' => ['neel']]]));
        $this->assertFalse($this->matchesFilter(['attr' => ['rng' => ['laal']]]));

        $label = DB::table('shop_product_attribute_values')
            ->where('post_id', $this->product->id)->where('value_slug', 'neel')->value('value');
        $this->assertSame('নীল', $label, 'the shopper reads the original, not the slug');
    }

    /** Scripts Laravel cannot transliterate must not all collapse into one empty slug. */
    public function test_untransliterable_values_stay_distinct(): void
    {
        $this->sync([['name' => 'Color', 'values' => '蓝色 | 🔵', 'visible' => '1', 'variation' => '0']]);

        $this->assertCount(2, $this->indexed());
        $this->assertTrue($this->matchesFilter(['attr' => ['color' => ['蓝色']]]));
    }

    // ---- hostile and malformed input -------------------------------------------

    public function test_malformed_attribute_rows_are_skipped_and_long_ones_truncated(): void
    {
        $this->sync([
            ['name' => '', 'values' => 'x'],
            ['name' => 'Ok', 'values' => ''],
            'not-an-array',
            ['name' => str_repeat('N', 200), 'values' => str_repeat('V', 400)],
        ]);

        $rows = DB::table('shop_product_attribute_values')->where('post_id', $this->product->id)->get();

        $this->assertCount(1, $rows, 'only the long-but-valid row survives');
        $this->assertSame(60, mb_strlen($rows->first()->name));
        $this->assertSame(120, mb_strlen($rows->first()->value));
    }

    public function test_hostile_query_strings_are_ignored_rather_than_obeyed(): void
    {
        $this->sync([['name' => 'Color', 'values' => 'Blue', 'visible' => '1', 'variation' => '0']]);

        $this->assertFalse($this->matchesFilter(['attr' => ["' OR 1=1--" => ["' OR 1=1--"]]]),
            'injection attempt matches nothing');

        // An unusable filter is dropped the way min_price=abc is, so the product still
        // comes back — the point is that nothing crashes and nothing leaks.
        $this->assertTrue($this->matchesFilter(['attr' => ['color' => [['x']]]]), 'nested array');
        $this->assertTrue($this->matchesFilter(['attr' => 'notanarray']), 'attr is a scalar');

        $this->assertFalse($this->matchesFilter(['attr' => ['color' => 'red']]),
            'a scalar value is still applied as a filter');
    }

    public function test_absurdly_large_filter_input_is_capped(): void
    {
        $this->sync([['name' => 'Color', 'values' => 'Blue', 'visible' => '1', 'variation' => '0']]);

        $this->assertIsBool($this->matchesFilter(['attr' => ['color' => array_fill(0, 500, 'x')]]));

        $manyAttributes = [];
        for ($i = 0; $i < 100; $i++) {
            $manyAttributes['a'.$i] = ['x'];
        }
        $this->assertIsBool($this->matchesFilter(['attr' => $manyAttributes]));

        $this->filteredProductIds(['attr' => $manyAttributes]);
        $this->assertCount(12, falcon_product_filters_active()['attributes'],
            'no more than twelve attributes are ever turned into joins');
    }

    /**
     * The index is derived data, so a deleted product must stop being filterable — but the
     * cascade cannot be relied on to do it. SQLite refuses to add a foreign key to a table
     * that already exists, so the migration catches that failure and the index instead
     * stays safe by construction: everything reads it through the live product ids.
     *
     * This asserts the guarantee that holds on every engine, and the cascade on top of it
     * wherever the database accepted the constraint.
     */
    public function test_a_deleted_product_stops_being_filterable(): void
    {
        $this->sync([['name' => 'Color', 'values' => 'Red | Blue', 'visible' => '1', 'variation' => '0']]);
        $this->assertCount(2, $this->indexed());
        $this->assertTrue($this->matchesFilter(['attr' => ['color' => ['red']]]));

        DB::table('posts')->where('id', $this->product->id)->delete();

        $this->assertFalse($this->matchesFilter(['attr' => ['color' => ['red']]]),
            'a deleted product must not come back under a filter');

        $options = falcon_product_filter_options(fn () => Post::query()
            ->where('posts.type', 'product')
            ->where('posts.status', 'published'));
        $colours = collect($options['attributes'] ?? [])
            ->firstWhere('slug', 'color')['values'] ?? [];
        $this->assertEmpty($colours, 'the sidebar must not count a product that is gone');
    }
}
