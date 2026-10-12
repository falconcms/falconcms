<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use FalconShop\Lifecycle;
use FalconShop\StorefrontPages;
use Illuminate\Support\Facades\DB;

/**
 * The shop makes its own pages (Shop, Cart, Checkout, Account, Wishlist): when it is activated,
 * and once on its own for a site that had it on from install. A page the owner deletes later
 * stays deleted until they press "Create missing pages", which brings back what is missing
 * without ever making a second page for a slot that has one.
 */
class StorefrontPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // A clean slate: no shop page, no assignment, never initialised.
        Post::withoutGlobalScopes()->where('type', 'page')->whereIn('slug', ['product', 'cart', 'checkout', 'account', 'wishlist'])->forceDelete();
        DB::table('cms_settings')->whereIn('key', array_merge(array_column(StorefrontPages::PAGES, 'option'), [StorefrontPages::DONE]))->delete();
        forget_cms_options_cache();
    }

    private function pageFor(string $key): ?Post
    {
        $id = (int) get_cms_option(StorefrontPages::PAGES[$key]['option'], 0);

        return $id ? Post::withoutGlobalScopes()->find($id) : null;
    }

    public function test_activating_the_shop_makes_all_five_pages_and_assigns_them(): void
    {
        (new Lifecycle)->activate();

        foreach (StorefrontPages::PAGES as $key => $def) {
            $page = $this->pageFor($key);
            $this->assertNotNull($page, "$key page missing");
            $this->assertSame([$def['slug'], 'published', 'page'], [$page->slug, $page->status, $page->type]);
        }
        $this->assertSame('1', get_cms_option(StorefrontPages::DONE));
        $this->assertSame(5, Post::where('type', 'page')->whereIn('slug', ['product', 'cart', 'checkout', 'account', 'wishlist'])->count());
    }

    public function test_the_first_run_happens_once_so_a_deleted_page_stays_deleted(): void
    {
        StorefrontPages::ensureOnce();
        $cart = $this->pageFor('cart');
        $cart->delete();

        StorefrontPages::ensureOnce();
        do_falcon_action('falcon_install_defaults');

        $this->assertTrue(Post::withoutGlobalScopes()->find($cart->id)->trashed(), 'an update must not undo a deliberate delete');
        $this->assertSame('trashed', StorefrontPages::status()['cart']['state']);
        $this->assertTrue(StorefrontPages::needsAttention());
    }

    public function test_create_missing_restores_from_the_trash_publishes_and_recreates(): void
    {
        StorefrontPages::ensure();
        $cart = $this->pageFor('cart');
        $cart->delete();                                              // in the trash
        $this->pageFor('checkout')->update(['status' => 'draft']);    // unpublished
        $wishlist = $this->pageFor('wishlist');
        $wishlist->forceDelete();                                      // gone for good

        $done = StorefrontPages::ensure();

        $this->assertSame('restored', $done['cart']);
        $this->assertSame($cart->id, $this->pageFor('cart')->id, 'the same page comes back, with its content');
        $this->assertFalse($this->pageFor('cart')->trashed());
        $this->assertSame('created', $done['wishlist']);
        $this->assertNotSame($wishlist->id, $this->pageFor('wishlist')->id);
        // A draft the owner assigned is theirs to publish: reported, not changed.
        $this->assertSame('draft', StorefrontPages::status()['checkout']['state']);
    }

    public function test_an_existing_page_at_the_usual_address_is_assigned_not_duplicated(): void
    {
        $mine = Post::forceCreate(['title' => 'My basket', 'slug' => 'cart', 'type' => 'page', 'status' => 'published', 'lang_code' => 'en', 'content' => '<p>Hi</p>']);

        $done = StorefrontPages::ensure();

        $this->assertSame('linked', $done['cart']);
        $this->assertSame($mine->id, $this->pageFor('cart')->id);
        $this->assertSame('My basket', $this->pageFor('cart')->title, 'its title and content are left alone');
        $this->assertSame(1, Post::where('type', 'page')->where('slug', 'cart')->count());
    }

    public function test_the_settings_screen_shows_what_is_missing_and_the_button_fixes_it(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'shop-pages@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
        update_cms_option(StorefrontPages::DONE, '1'); // past the first run: nothing is made unasked

        $this->actingAs($admin)->get(route('admin.shop.settings', ['tab' => 'products']))->assertOk()
            ->assertSee('5 shop pages need attention')->assertSee('Create missing pages')->assertSee('Wishlist page');

        $this->actingAs($admin)->post(route('admin.shop.settings.pages'))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Cart page created'));

        $this->assertFalse(StorefrontPages::needsAttention());
    }

    public function test_storefront_links_skip_a_page_that_is_not_live(): void
    {
        StorefrontPages::ensure();
        $this->pageFor('checkout')->update(['status' => 'draft']);
        forget_cms_options_cache();

        $this->assertSame(route('shop.checkout'), get_falcon_checkout_url());
        $this->assertSame(url('/cart'), get_falcon_cart_url());
    }
}
