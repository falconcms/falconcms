<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\ProductCategory;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * The admin-side hooks a shop plugin builds its screens on: a tab and panel in the product
 * editor, fields on every variation (kept through the delete-and-recreate save), fields on
 * product categories and on users, and the save actions that hand those fields over.
 */
class ShopAdminHooksTest extends TestCase
{
    use MakesShopFixtures;

    /** @var array<string, array<int, array<int, mixed>>> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withProLicensed();
        $this->actingAs($this->makeUser([
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]));
    }

    private function record(string $tag): void
    {
        add_falcon_action($tag, function (...$args) use ($tag) {
            $this->calls[$tag][] = $args;
        });
    }

    // ── product editor ─────────────────────────────────────────────────────

    public function test_product_data_tabs_and_panels_render_in_the_editor(): void
    {
        $product = $this->makeProduct();

        add_falcon_filter('falcon_admin_product_data_tabs', fn (array $tabs, $post) => array_merge($tabs, [
            ['id' => 'wholesale', 'label' => 'Wholesale', 'icon' => 'storefront', 'show' => "productType !== 'variable'"],
            ['id' => 'general', 'label' => 'Shadowing a built-in tab'],
            ['id' => 'Bad Id!', 'label' => 'Broken'],
        ]));
        add_falcon_action('falcon_admin_product_data_panels', function ($post) {
            echo '<div x-show="activeTab === \'wholesale\'" id="wholesale-panel">PANEL FOR '.(int) $post->id.'</div>';
        });

        $html = $this->get(route('admin.posts.edit', $product->id))->assertOk()->getContent();

        $this->assertStringContainsString("activeTab = 'wholesale'", $html);
        $this->assertStringContainsString('>storefront<', $html);
        $this->assertStringContainsString('PANEL FOR '.$product->id, $html);
        $this->assertStringNotContainsString('Shadowing a built-in tab', $html);
        $this->assertStringNotContainsString('Broken', $html);
    }

    public function test_variation_fields_render_inside_the_variation_rows(): void
    {
        $product = $this->makeVariableProduct([['price' => 100]]);
        add_falcon_action('falcon_admin_variation_fields', function ($post) {
            echo '<input id="wholesale-variation-field" x-model="variation.meta.wholesale">';
        });

        $html = $this->get(route('admin.posts.edit', $product->id))->assertOk()->getContent();

        $start = strpos($html, 'x-for="(variation, vIndex) in variations"');
        $field = strpos($html, 'wholesale-variation-field');
        $this->assertNotFalse($start);
        $this->assertNotFalse($field);
        $this->assertGreaterThan($start, $field, 'printed inside the variation loop');
    }

    public function test_variation_meta_survives_the_full_product_save(): void
    {
        $seen = [];
        add_falcon_filter('falcon_admin_variation_meta', function (array $meta, array $input, $shopData) use (&$seen) {
            $seen[] = $input['price'] ?? null;
            if (isset($meta['falcon_wholesale'])) {
                $meta['falcon_wholesale'] = array_map('floatval', (array) $meta['falcon_wholesale']);
            }

            return $meta;
        });

        $this->post(route('admin.posts.store'), [
            'title' => 'Meta phone', 'type' => 'product', 'status' => 'published', 'product_type' => 'variable',
            'attributes_data' => [['name' => 'Size', 'values' => 'S | M', 'visible' => '1', 'variation' => '1']],
            'variations' => [
                ['attributes' => ['Size' => 'S'], 'price' => '100', 'meta' => ['falcon_wholesale' => ['1' => '80']]],
                ['attributes' => ['Size' => 'M'], 'price' => '120'],
            ],
        ])->assertSessionHasNoErrors();

        $variations = Post::where('title', 'Meta phone')->firstOrFail()->shopData->variations()->orderBy('price')->get();

        // Stored as JSON, so the 80.0 the filter made comes back as 80.
        $this->assertEquals(['falcon_wholesale' => ['1' => 80]], $variations[0]->meta);
        $this->assertNull($variations[1]->meta, 'no meta stays null');
        $this->assertSame(['100', '120'], $seen);
    }

    public function test_variation_meta_a_plugin_printed_no_field_for_is_kept_on_a_full_save(): void
    {
        $this->post(route('admin.posts.store'), [
            'title' => 'Kept phone', 'type' => 'product', 'status' => 'published', 'product_type' => 'variable',
            'attributes_data' => [['name' => 'Size', 'values' => 'S', 'visible' => '1', 'variation' => '1']],
            'variations' => [[
                'attributes' => ['Size' => 'S'], 'price' => '100',
                // What the editor round-trips, plus one field a plugin printed this time.
                'meta_json' => json_encode(['other_plugin' => 'keep me', 'falcon_wholesale' => ['1' => 70]]),
                'meta' => ['falcon_wholesale' => ['1' => '65']],
            ]],
        ])->assertSessionHasNoErrors();

        $meta = Post::where('title', 'Kept phone')->firstOrFail()->shopData->variations()->firstOrFail()->meta;
        $this->assertSame('keep me', $meta['other_plugin']);
        $this->assertEquals(65, $meta['falcon_wholesale']['1'], 'the printed field wins');
    }

    public function test_variation_meta_survives_the_ajax_variation_save(): void
    {
        $product = $this->makeVariableProduct([['price' => 100]]);

        $this->postJson(route('admin.posts.variations.ajax-save', $product->id), [
            'attributes_data' => [['name' => 'size', 'values' => 'M', 'variation' => true]],
            'variations' => [[
                'attributes_data' => ['size' => 'M'], 'price' => 100, 'sale_price' => null,
                'meta' => ['falcon_wholesale' => ['1' => 75]],
            ]],
        ])->assertOk();

        $variation = $product->fresh()->shopData->variations()->firstOrFail();
        $this->assertSame(['falcon_wholesale' => ['1' => 75]], $variation->meta);

        // And the editor hands it back, so the next save sends it again.
        $html = $this->get(route('admin.posts.edit', $product->id))->getContent();
        $this->assertStringContainsString('falcon_wholesale', $html);
    }

    // ── product categories ─────────────────────────────────────────────────

    public function test_product_category_fields_render_and_the_save_action_fires(): void
    {
        add_falcon_action('falcon_admin_product_category_fields', function ($category, string $context) {
            echo '<div id="cat-field-'.$context.'">wholesale discount for '.($category->name ?? 'new').'</div>';
        });
        $this->record('falcon_admin_product_category_saved');

        $this->assertStringContainsString('cat-field-add', $this->get(route('admin.product-categories.index'))->assertOk()->getContent());

        $this->post(route('admin.product-categories.store'), ['name' => 'Bulk', 'wholesale_discount' => '15']);
        $category = ProductCategory::where('name', 'Bulk')->firstOrFail();

        [$saved, $request, $mode] = $this->calls['falcon_admin_product_category_saved'][0];
        $this->assertSame($category->id, $saved->id);
        $this->assertSame('15', $request->input('wholesale_discount'));
        $this->assertSame('create', $mode);

        $html = $this->get(route('admin.product-categories.edit', $category))->assertOk()->getContent();
        $this->assertStringContainsString('cat-field-edit', $html);
        $this->assertStringContainsString('wholesale discount for Bulk', $html);

        $this->put(route('admin.product-categories.update', $category), ['name' => 'Bulk', 'slug' => 'bulk', 'lang_code' => $category->getAttribute('lang_code')]);
        $this->assertSame('update', $this->calls['falcon_admin_product_category_saved'][1][2]);
    }

    // ── users ──────────────────────────────────────────────────────────────

    public function test_user_form_fields_render_and_the_save_action_fires(): void
    {
        add_falcon_action('falcon_admin_user_form_fields', function ($user) {
            echo '<tr id="user-field"><th>Level</th><td>'.($user ? 'editing '.$user->getKey() : 'new user').'</td></tr>';
        });
        add_falcon_action('falcon_admin_user_role_cell', function ($user) {
            echo '<span class="role-badge">WS-'.$user->getKey().'</span>';
        });
        $this->record('falcon_admin_user_saved');

        $this->assertStringContainsString('new user', $this->get(route('admin.users.create'))->assertOk()->getContent());

        $roleId = (int) DB::table('roles')->where('slug', 'customer')->value('id')
            ?: (int) DB::table('roles')->where('slug', '!=', 'super-admin')->value('id');
        $this->post(route('admin.users.store'), [
            'username' => 'dealer1', 'name' => 'Dealer One', 'email' => 'dealer1@example.test',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
            'roles' => [$roleId], 'wholesale_level' => '2',
        ])->assertSessionHasNoErrors();

        [$user, $request, $mode] = $this->calls['falcon_admin_user_saved'][0];
        $this->assertSame('dealer1@example.test', DB::table('users')->where('id', $user->getKey())->value('email'));
        $this->assertSame('2', $request->input('wholesale_level'));
        $this->assertSame('create', $mode);

        $this->assertStringContainsString('editing '.$user->getKey(), $this->get(route('admin.users.edit', $user->getKey()))->assertOk()->getContent());
        $this->assertStringContainsString('WS-'.$user->getKey(), $this->get(route('admin.users.index'))->assertOk()->getContent());

        $this->put(route('admin.users.update', $user->getKey()), [
            'username' => 'dealer1', 'name' => 'Dealer One', 'email' => 'dealer1@example.test', 'roles' => [$roleId],
        ]);
        $this->assertSame('update', $this->calls['falcon_admin_user_saved'][1][2] ?? null);
    }
}
