<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;

/**
 * The product archive's filters.
 *
 * Everything here comes off the query string, which is to say: from anyone. The tests
 * split into two halves — the filters doing what the shopper asked, and the parser
 * refusing to be talked into anything by a hand-edited URL. The second half is the one
 * that matters; `product_cat[][]=x` used to reach the view and take the page down with
 * a 500, and `max_price=1e400` used to become INF and poison every comparison after it.
 */
class ArchiveFilterTest extends TestCase
{
    use MakesShopFixtures;

    private Post $cheap;

    private Post $middle;

    private Post $dear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cheap = $this->makeProduct(['price' => 100], ['title' => 'Cotton shirt']);
        $this->middle = $this->makeProduct(['price' => 500], ['title' => 'Silk scarf']);
        $this->dear = $this->makeProduct(['price' => 900], ['title' => 'Wool coat']);
    }

    /** @param array<string, mixed> $query */
    private function idsFor(array $query): array
    {
        return $this->filteredProductIds($query);
    }

    // ---- doing what was asked --------------------------------------------------

    public function test_no_filters_returns_everything(): void
    {
        $this->assertCount(3, $this->idsFor([]));
    }

    public function test_a_price_range_narrows_the_set(): void
    {
        $ids = $this->idsFor(['min_price' => '200', 'max_price' => '800']);

        $this->assertSame([$this->middle->id], $ids);
    }

    public function test_an_open_ended_price_range_works_from_either_side(): void
    {
        $this->assertSame(
            [$this->middle->id, $this->dear->id],
            $this->idsFor(['min_price' => '500'])
        );

        $this->assertSame(
            [$this->cheap->id, $this->middle->id],
            $this->idsFor(['max_price' => '500'])
        );
    }

    /** A shopper who drags the slider backwards meant the range, not an empty page. */
    public function test_a_reversed_price_range_is_swapped_rather_than_matching_nothing(): void
    {
        $this->assertSame(
            [$this->middle->id],
            $this->idsFor(['min_price' => '800', 'max_price' => '200'])
        );
    }

    public function test_search_matches_the_title(): void
    {
        $this->assertSame([$this->middle->id], $this->idsFor(['s' => 'Silk']));
        $this->assertSame([], $this->idsFor(['s' => 'Linen']));
    }

    public function test_filters_combine_rather_than_replace_one_another(): void
    {
        $this->assertSame(
            [],
            $this->idsFor(['s' => 'Silk', 'min_price' => '800']),
            'the silk scarf costs 500, so the price filter must exclude it'
        );
    }

    public function test_in_stock_and_on_sale_are_only_applied_when_set_to_one(): void
    {
        $active = falcon_product_filters_active();
        $this->assertIsArray($active);

        $this->filteredProductIds(['in_stock' => '0', 'on_sale' => 'yes']);
        $flags = falcon_product_filters_active();

        $this->assertFalse($flags['in_stock'], 'in_stock=0 is not a filter');
        $this->assertFalse($flags['on_sale'], 'only the literal "1" turns these on');
    }

    // ---- refusing to be talked into anything -----------------------------------

    /**
     * `?product_cat[][]=x`. The nested array used to survive parsing, reach the view and
     * hit htmlspecialchars(), which is a 500 on a public page for anyone who can type.
     */
    public function test_a_nested_category_array_is_dropped_not_stringified(): void
    {
        $this->assertCount(3, $this->idsFor(['product_cat' => [['x']]]));

        $this->assertSame([], falcon_product_filters_active()['categories'],
            'the nested value must not survive as a category slug');
    }

    /** `?max_price=1e400` becomes INF, and every comparison after it is nonsense. */
    public function test_a_non_finite_price_is_discarded(): void
    {
        $this->filteredProductIds(['min_price' => '1e400', 'max_price' => '1e400']);
        $active = falcon_product_filters_active();

        $this->assertNull($active['min_price']);
        $this->assertNull($active['max_price']);
    }

    public function test_prices_that_are_not_numbers_are_discarded(): void
    {
        foreach (['abc', '', ['x'], 'NaN'] as $value) {
            $this->filteredProductIds(['min_price' => $value]);
            $this->assertNull(falcon_product_filters_active()['min_price'],
                'min_price='.json_encode($value));
        }
    }

    public function test_a_negative_price_is_clamped_to_zero(): void
    {
        $this->filteredProductIds(['min_price' => '-500']);

        $this->assertSame(0.0, falcon_product_filters_active()['min_price']);
    }

    public function test_a_search_term_is_capped_in_length(): void
    {
        $this->filteredProductIds(['s' => str_repeat('a', 5000)]);

        $this->assertSame(120, mb_strlen(falcon_product_filters_active()['search']));
    }

    public function test_a_search_term_cannot_smuggle_sql(): void
    {
        $ids = $this->idsFor(['s' => "' OR 1=1--"]);

        $this->assertSame([], $ids, 'a bound parameter matches no title, it does not open the table');
    }

    public function test_a_non_scalar_search_term_is_ignored(): void
    {
        $this->assertCount(3, $this->idsFor(['s' => ['array']]));
        $this->assertSame('', falcon_product_filters_active()['search']);
    }

    // ---- sorting ---------------------------------------------------------------

    public function test_sorting_by_price_runs_both_ways(): void
    {
        $ascending = Post::query()->where('posts.type', 'product')->where('posts.status', 'published');
        falcon_apply_product_sorting($ascending, 'price');
        $this->assertSame(
            [$this->cheap->id, $this->middle->id, $this->dear->id],
            $ascending->pluck('posts.id')->map(fn ($id) => (int) $id)->all()
        );

        $descending = Post::query()->where('posts.type', 'product')->where('posts.status', 'published');
        falcon_apply_product_sorting($descending, 'price-desc');
        $this->assertSame(
            [$this->dear->id, $this->middle->id, $this->cheap->id],
            $descending->pluck('posts.id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_an_unknown_sort_key_does_not_break_the_query(): void
    {
        $query = Post::query()->where('posts.type', 'product')->where('posts.status', 'published');
        falcon_apply_product_sorting($query, 'price); DROP TABLE posts;--');

        $this->assertCount(3, $query->get(), 'an unrecognised orderby falls through to the default');
    }

    public function test_sorting_by_price_uses_the_sale_price_when_one_is_running(): void
    {
        // The coat is dearest at list price but cheapest once its sale is applied.
        $this->makeProduct(['price' => 900, 'sale_price' => 50], ['title' => 'Discounted cape']);

        $query = Post::query()->where('posts.type', 'product')->where('posts.status', 'published');
        falcon_apply_product_sorting($query, 'price');

        $first = (int) $query->pluck('posts.id')->first();
        $this->assertSame(50.0, (float) Post::findOrFail($first)->shopData->active_sale_price);
    }
}
