<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Support\MenuPlacement;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Where a custom post type lands in the admin sidebar.
 *
 * Shop and Products are one thing read two ways — Products is the Shop's own post type — and
 * a custom post type wedged between them has now broken that twice. Both times the cause was
 * the same: `order` was computed from the post type's id (`40 + id` on three code paths,
 * `60 + id` on three others), so a type with the wrong id collided with the pair, and
 * renumbering the pair only moved the collision somewhere else.
 *
 * These tests hold the pair together through every path that writes a menu row, and cover the
 * position the user picks on the way.
 */
class SidebarMenuOrderTest extends TestCase
{
    private ?User $admin = null;

    /**
     * The sidebar is seeded at install rather than by a migration, so a Testbench database
     * arrives with almost no menus. These tests are about the seeded arrangement, so they
     * start from it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = null;
        (new MenuSeeder)->run();
    }

    /** One administrator per test — several of these post more than once. */
    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** Top-level Main menu titles, in the order the sidebar draws them. */
    private function sidebarOrder(): array
    {
        return Menu::whereNull('parent_id')->where('group', 'Main')
            ->orderBy('order')->orderBy('id')
            ->pluck('title')->all();
    }

    private function menuId(string $title): int
    {
        return (int) Menu::whereNull('parent_id')->where('title', $title)->value('id');
    }

    /** Assert Shop is immediately above Products with nothing in between. */
    private function assertPairIntact(string $because): void
    {
        $titles = $this->sidebarOrder();
        $shop = array_search('Shop', $titles, true);
        $products = array_search('Products', $titles, true);

        $this->assertNotFalse($shop, 'Shop is missing from the sidebar.');
        $this->assertNotFalse($products, 'Products is missing from the sidebar.');
        $this->assertSame($shop + 1, $products, $because.' — got: '.implode(' > ', $titles));
    }

    private function createPostType(array $overrides = [])
    {
        return $this->actingAs($this->administrator())->post('/admin/acpt/cpt', array_merge([
            'plural_label' => 'Courses',
            'singular_label' => 'Course',
            'post_type_key' => 'course',
            'supports' => ['title'],
            'show_in_menu' => '1',
        ], $overrides));
    }

    public function test_products_sits_directly_under_shop_out_of_the_box(): void
    {
        $this->assertPairIntact('A freshly seeded sidebar already has the pair together');
    }

    public function test_a_new_post_type_lands_at_the_bottom_by_default(): void
    {
        $this->createPostType()->assertRedirect();

        $titles = $this->sidebarOrder();
        $this->assertSame('Courses', end($titles), 'A post type with no chosen position goes to the bottom.');
        $this->assertPairIntact('Appending to the bottom leaves the pair alone');
    }

    public function test_choosing_shop_places_the_type_under_the_pair_never_inside_it(): void
    {
        // The one case the old code got wrong in the most visible way: asking for a position
        // next to Shop must not mean "between Shop and its own products".
        $this->createPostType(['menu_after' => $this->menuId('Shop')])->assertRedirect();

        $this->assertPairIntact('Anchoring to Shop places the type below the whole block');

        $titles = $this->sidebarOrder();
        $this->assertSame(
            array_search('Products', $titles, true) + 1,
            array_search('Courses', $titles, true),
            'The type belongs immediately below the block it was anchored to.'
        );
    }

    public function test_a_chosen_position_is_honoured_and_remembered(): void
    {
        $this->createPostType(['menu_after' => $this->menuId('Comments')])->assertRedirect();

        $titles = $this->sidebarOrder();
        $this->assertSame(
            array_search('Comments', $titles, true) + 1,
            array_search('Courses', $titles, true),
            'The type sits directly below the menu it was placed after.'
        );

        $this->assertSame(
            $this->menuId('Comments'),
            (int) PostType::where('slug', 'course')->value('menu_after'),
            'The choice is stored, so re-opening the form shows it.'
        );
    }

    public function test_the_pair_survives_editing_duplicating_and_reactivating(): void
    {
        $this->createPostType(['menu_after' => $this->menuId('Comments')])->assertRedirect();
        $postType = PostType::where('slug', 'course')->firstOrFail();
        $admin = $this->administrator();

        $this->actingAs($admin)->put('/admin/acpt/cpt/'.$postType->id, [
            'plural_label' => 'Programmes',
            'singular_label' => 'Programme',
            'post_type_key' => 'course',
            'supports' => ['title', 'editor'],
            'show_in_menu' => '1',
            'is_public' => '1',
            'menu_after' => $this->menuId('Shop'),
        ])->assertRedirect();
        $this->assertPairIntact('Editing a post type must not split the pair');

        $this->assertTrue(
            Menu::whereNull('parent_id')->where('title', 'Courses')->doesntExist(),
            'A rename moves the existing row rather than leaving the old one behind.'
        );
        $this->assertSame(
            ['All Programmes', 'Add New'],
            Menu::where('parent_id', $this->menuId('Programmes'))->orderBy('order')->pluck('title')->all()
        );

        $this->actingAs($admin)->post('/admin/acpt/cpt/'.$postType->id.'/toggle-status')->assertRedirect();
        $this->assertPairIntact('Deactivating a post type must not split the pair');

        $this->actingAs($admin)->post('/admin/acpt/cpt/'.$postType->id.'/toggle-status')->assertRedirect();
        $this->assertPairIntact('Re-activating a post type must not split the pair');

        $this->actingAs($admin)->post('/admin/acpt/cpt/'.$postType->id.'/duplicate')->assertRedirect();
        $this->assertPairIntact('Duplicating a post type must not split the pair');
    }

    public function test_several_post_types_never_share_a_position(): void
    {
        // The original bug was a tie: two menus with the same `order` came back from the
        // database in whatever order it felt like, so one of them appeared above Shop on one
        // page load and below it on the next.
        foreach (['Courses' => 'course', 'Recipes' => 'recipe', 'Webinars' => 'webinar'] as $plural => $key) {
            $this->createPostType([
                'plural_label' => $plural,
                'singular_label' => rtrim($plural, 's'),
                'post_type_key' => $key,
                'menu_after' => $this->menuId('Shop'),
            ])->assertRedirect();
        }

        $this->assertPairIntact('Three post types anchored to Shop still leave the pair intact');

        $orders = Menu::whereNull('parent_id')->where('group', 'Main')->pluck('order')->all();
        $this->assertSame(count($orders), count(array_unique($orders)), 'No two top-level menus share an order.');
    }

    public function test_products_is_not_offered_as_a_position_of_its_own(): void
    {
        // "After Products" is not a distinct place to be: it is inside the Shop block, which
        // is exactly what must not be offered.
        $labels = MenuPlacement::anchorOptions()->values()->all();

        $this->assertContains('Shop', $labels);
        $this->assertNotContains('Products', $labels);
    }
}
