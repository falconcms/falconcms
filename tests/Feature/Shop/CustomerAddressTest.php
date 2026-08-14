<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use App\Models\User;
use FalconCms\Core\Http\Controllers\ShopFrontendController;
use FalconCms\Core\Models\CustomerAddress;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;

/**
 * The customer address book.
 *
 * Half of this is convenience — defaults, checkout pre-fill, not saving the same address
 * twice — and half is access control. Every one of these actions takes an address id from
 * the request, so each one has to prove it belongs to the person asking. Those are the
 * tests worth keeping: a missing ownership check here is one customer editing another
 * customer's address.
 */
class CustomerAddressTest extends TestCase
{
    use MakesShopFixtures;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = $this->makeUser(['name' => 'Alice']);
        $this->bob = $this->makeUser(['name' => 'Bob']);
    }

    /** @return array<string, mixed> */
    private function addressInput(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Alice',
            'last_name' => 'A',
            'country' => 'Bangladesh',
            'address_1' => '12 Road 5',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postcode' => '1207',
            'phone' => '01700000000',
            'email' => 'alice@example.test',
        ], $overrides);
    }

    /**
     * Call a controller action as a given user, the way a posted form would.
     *
     * @param  array<string, mixed>  $input
     * @return mixed 'VALIDATION_FAILED' when the request was rejected
     */
    private function actingAsUser(User $user, string $method, array $input, mixed ...$args): mixed
    {
        auth()->setUser($user);

        $request = Request::create('/x', 'POST', $input);
        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        try {
            return (new ShopFrontendController)->{$method}($request, ...$args);
        } catch (ValidationException $e) {
            return 'VALIDATION_FAILED';
        }
    }

    private function addressesOf(User $user): Collection
    {
        return CustomerAddress::where('user_id', $user->id)->orderBy('id')->get();
    }

    // ---- defaults --------------------------------------------------------------

    public function test_the_first_address_becomes_the_default_for_both_billing_and_shipping(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Home']));

        $address = $this->addressesOf($this->alice)->first();

        $this->assertNotNull($address);
        $this->assertTrue((bool) $address->is_default_billing);
        $this->assertTrue((bool) $address->is_default_shipping);
    }

    public function test_a_second_address_does_not_steal_the_defaults(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Home']));
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Office', 'address_1' => '99 Gulshan']));

        $addresses = $this->addressesOf($this->alice);

        $this->assertCount(2, $addresses);
        $this->assertFalse((bool) $addresses[1]->is_default_billing);
    }

    public function test_making_one_address_default_clears_the_previous_one(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Home']));
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Office', 'address_1' => '99 Gulshan']));

        [$home, $office] = $this->addressesOf($this->alice)->all();

        $this->actingAsUser($this->alice, 'setDefaultAddress', ['type' => 'shipping'], $office->id);

        $this->assertTrue((bool) $office->fresh()->is_default_shipping);
        $this->assertFalse((bool) $home->fresh()->is_default_shipping);
        $this->assertTrue((bool) $home->fresh()->is_default_billing, 'billing is a separate default');
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Home']));
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Office', 'address_1' => '99 Gulshan']));

        $default = CustomerAddress::where('user_id', $this->alice->id)->where('is_default_billing', true)->firstOrFail();

        $this->actingAsUser($this->alice, 'deleteAddress', [], $default->id);

        $this->assertNull(CustomerAddress::find($default->id));
        $this->assertTrue(
            CustomerAddress::where('user_id', $this->alice->id)->where('is_default_billing', true)->exists(),
            'the customer must not be left with no default'
        );
    }

    // ---- access control --------------------------------------------------------

    public function test_a_customer_cannot_edit_someone_elses_address(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput());
        $alicesAddress = $this->addressesOf($this->alice)->first();

        $this->actingAsUser($this->bob, 'saveAddress', $this->addressInput([
            'first_name' => 'Bob',
            'address_id' => $alicesAddress->id,
        ]));

        $this->assertSame('Alice', $alicesAddress->fresh()->first_name, "Bob overwrote Alice's address");
        $this->assertCount(0, $this->addressesOf($this->bob),
            'a rejected edit must not silently become a new address for Bob either');
    }

    public function test_a_customer_cannot_delete_someone_elses_address(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput());
        $alicesAddress = $this->addressesOf($this->alice)->first();

        $this->actingAsUser($this->bob, 'deleteAddress', [], $alicesAddress->id);

        $this->assertNotNull(CustomerAddress::find($alicesAddress->id));
    }

    public function test_a_customer_cannot_change_someone_elses_defaults(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput());
        $alicesAddress = $this->addressesOf($this->alice)->first();

        $this->actingAsUser($this->bob, 'setDefaultAddress', ['type' => 'billing'], $alicesAddress->id);

        $this->assertTrue((bool) $alicesAddress->fresh()->is_default_billing);
    }

    public function test_a_customer_can_edit_their_own_address(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput());
        $address = $this->addressesOf($this->alice)->first();

        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput([
            'address_id' => $address->id,
            'first_name' => 'Alicia',
        ]));

        $this->assertSame('Alicia', $address->fresh()->first_name);
    }

    // ---- validation and limits -------------------------------------------------

    public function test_an_address_without_a_street_is_rejected(): void
    {
        $this->assertSame('VALIDATION_FAILED',
            $this->actingAsUser($this->alice, 'saveAddress', ['first_name' => 'X']));
    }

    public function test_a_malformed_email_is_rejected(): void
    {
        $this->assertSame('VALIDATION_FAILED', $this->actingAsUser($this->alice, 'saveAddress',
            $this->addressInput(['email' => 'not-an-email'])));
    }

    /** An address book is not free storage; the cap keeps one account from filling the table. */
    public function test_the_address_book_is_capped(): void
    {
        for ($i = 0; $i < 20; $i++) {
            CustomerAddress::create([
                'user_id' => $this->alice->id, 'first_name' => "F{$i}", 'address_1' => "A{$i}",
            ]);
        }
        $this->assertCount(20, $this->addressesOf($this->alice));

        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['address_1' => 'One too many']));

        $this->assertCount(20, $this->addressesOf($this->alice));
    }

    // ---- checkout integration --------------------------------------------------

    public function test_checkout_fields_are_prefilled_from_the_defaults(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Home']));
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput(['label' => 'Office', 'address_1' => '99 Gulshan']));

        $office = $this->addressesOf($this->alice)->last();
        $this->actingAsUser($this->alice, 'setDefaultAddress', ['type' => 'shipping'], $office->id);

        auth()->setUser($this->alice);

        $billing = collect(falcon_get_checkout_fields('billing'))->keyBy('name');
        $this->assertSame('Dhaka', $billing['billing_city']['default'] ?? null);
        $this->assertSame('12 Road 5', $billing['billing_address_1']['default'] ?? null);

        $shipping = collect(falcon_get_checkout_fields('shipping'))->keyBy('name');
        $this->assertSame('99 Gulshan', $shipping['shipping_address_1']['default'] ?? null,
            'shipping pre-fills from the shipping default, not the billing one');
    }

    public function test_to_checkout_fields_prefixes_and_drops_what_shipping_has_no_use_for(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput());
        $address = $this->addressesOf($this->alice)->first();

        $this->assertSame('Dhaka', $address->toCheckoutFields('billing')['billing_city'] ?? null);
        $this->assertArrayNotHasKey('shipping_email', $address->toCheckoutFields('shipping'));
    }

    /**
     * Checkout offers to remember the address it was given. Doing that naively means a
     * repeat customer accumulates a copy of the same address on every order.
     */
    public function test_checkout_does_not_store_an_address_the_customer_already_has(): void
    {
        $this->actingAsUser($this->alice, 'saveAddress', $this->addressInput());
        auth()->setUser($this->alice);

        $checkout = [
            'billing_first_name' => 'Alice', 'billing_last_name' => 'A',
            'billing_country' => 'Bangladesh', 'billing_address_1' => '12 Road 5',
            'billing_city' => 'Dhaka', 'billing_state' => 'Dhaka', 'billing_postcode' => '1207',
            'billing_phone' => '01700000000', 'billing_email' => 'alice@example.test',
        ];

        $remember = function (array $input) {
            $controller = new ShopFrontendController;
            $method = (new \ReflectionClass($controller))->getMethod('rememberCustomerAddress');
            $method->setAccessible(true);
            $method->invoke($controller, Request::create('/x', 'POST', $input));
        };

        $before = $this->addressesOf($this->alice)->count();

        $remember($checkout);
        $this->assertCount($before, $this->addressesOf($this->alice), 'identical address');

        $remember(array_merge($checkout, ['billing_address_1' => '  12   ROAD 5  ']));
        $this->assertCount($before, $this->addressesOf($this->alice), 'same address, different spacing and case');

        $remember(array_merge($checkout, ['billing_address_1' => '77 New Street']));
        $this->assertCount($before + 1, $this->addressesOf($this->alice), 'a genuinely new address is stored');
    }

    public function test_a_guest_has_no_address_book(): void
    {
        auth()->logout();

        $this->assertCount(0, falcon_customer_addresses());
        $this->assertNull(falcon_default_customer_address('billing'));
    }
}
