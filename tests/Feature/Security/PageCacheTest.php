<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;

/**
 * What the static page cache is allowed to keep.
 *
 * The cache stores a finished page under a key made of nothing but its URL, and hands that
 * same HTML to the next visitor who asks for the address. For an article or a category
 * listing that is exactly right. For a page whose content depends on who is asking it is a
 * disclosure bug of the worst kind — one visitor's basket, addresses or order shown to a
 * stranger — and it is silent, because every response is a perfectly valid 200.
 *
 * Off by default, so this only reaches a site that turned static caching on. These tests are
 * the line between "shared" and "personal", drawn on the routes as they are actually grouped.
 */
class PageCacheTest extends TestCase
{
    use MakesShopFixtures;

    private function enableStaticCaching(): void
    {
        $this->setCmsOptions(['performance_static_caching' => '1']);
    }

    /** Put a named product in this session's basket. */
    private function basketWith(string $title): void
    {
        $product = $this->makeProduct([], ['title' => $title]);

        session()->put('falcon_cart', [
            'k'.$product->id => [
                'id' => $product->id,
                'name' => $title,
                'slug' => $product->slug,
                'price' => 1000,
                'sale_price' => null,
                'quantity' => 1,
                'thumbnail' => null,
                'variation_id' => null,
                'sku' => null,
                'meta' => [],
            ],
        ]);
    }

    // ── the switch itself ────────────────────────────────────────────────────

    public function test_nothing_is_cached_while_the_setting_is_off(): void
    {
        // The default. A site that never turned this on must never serve a stored page.
        $this->get('/')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
        $this->get('/')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_a_shared_page_is_stored_and_then_served_from_the_cache(): void
    {
        $this->enableStaticCaching();

        $this->get('/')->assertOk()->assertHeader('X-Lazy-Cache', 'MISS');
        $this->get('/')->assertOk()->assertHeader('X-Lazy-Cache', 'HIT');
    }

    public function test_a_signed_in_visitor_is_never_served_a_stored_page(): void
    {
        $this->enableStaticCaching();
        $this->get('/')->assertHeader('X-Lazy-Cache', 'MISS');

        $this->actingAs(\App\Models\User::create([
            'name' => 'Reader', 'email' => 'reader@example.test', 'password' => bcrypt('x'),
        ]));

        // A logged-in visitor's page can carry their name, their bar, their drafts.
        $this->get('/')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_two_addresses_do_not_share_an_entry(): void
    {
        $this->enableStaticCaching();

        $this->get('/')->assertHeader('X-Lazy-Cache', 'MISS');
        $this->get('/?page=2')->assertOk()->assertHeader('X-Lazy-Cache', 'MISS');
    }

    // ── the line between shared and personal ─────────────────────────────────

    public function test_one_visitors_basket_is_never_served_to_another(): void
    {
        $this->enableStaticCaching();

        $this->basketWith('Aminas Private Purchase');
        $this->get('/cart')->assertOk()->assertSee('Aminas Private Purchase', false);

        // A second visitor, same address, nothing of their own in the session.
        $this->flushSession();

        $this->get('/cart')->assertOk()
            ->assertDontSee('Aminas Private Purchase', false);
    }

    public function test_the_cart_page_is_never_stored_at_all(): void
    {
        $this->enableStaticCaching();
        $this->basketWith('Something In A Basket');

        // Not merely "the leak did not happen this time" — the page must not enter the
        // cache, or the leak returns the moment the rendering changes.
        $this->get('/cart')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
        $this->get('/cart')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_the_checkout_page_is_never_stored(): void
    {
        $this->enableStaticCaching();
        $this->basketWith('Something To Buy');

        // Checkout carries the basket, the address form and a one-time form token. Serving
        // a stored copy leaks the first two and makes the third reject the next order.
        $this->get('/checkout')->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_an_empty_cart_page_is_still_not_stored(): void
    {
        $this->enableStaticCaching();

        // "Empty basket" looks shareable and is not: cache it once and the next shopper
        // with items in their basket is told their basket is empty.
        $this->get('/cart')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_an_ordinary_page_is_not_stored_while_the_visitor_is_carrying_a_basket(): void
    {
        $this->enableStaticCaching();
        $this->basketWith('In The Header');

        // The home page is shared — but the header on it shows this visitor's item count,
        // so the copy rendered for them is not the copy to keep.
        $this->get('/')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_a_page_stored_for_a_stranger_is_still_not_served_to_a_shopper(): void
    {
        $this->enableStaticCaching();

        $this->get('/')->assertHeader('X-Lazy-Cache', 'MISS');

        $this->flushSession();
        $this->basketWith('Not In That Copy');

        // The stored copy has an empty header. A shopper must get a freshly rendered page,
        // not one that tells them their basket is empty.
        $this->get('/')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    public function test_the_shared_pages_a_site_turns_this_on_for_are_still_cached(): void
    {
        $this->enableStaticCaching();

        // The point of the setting. Narrowing what may be stored must not quietly turn the
        // whole feature off for the pages it was bought for.
        foreach (['/', '/search?q=anything'] as $url) {
            $this->get($url)->assertOk()->assertHeader('X-Lazy-Cache', 'MISS');
            $this->get($url)->assertOk()->assertHeader('X-Lazy-Cache', 'HIT');
        }
    }
}
