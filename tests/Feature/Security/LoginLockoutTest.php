<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Models\BlockedIp;
use FalconCms\Core\Support\LoginGuard;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * One lockout for every login form. The shop's My Account form signs in any account —
 * administrators too — so it gets the dashboard login's five-try lock (shared, so switching
 * forms buys no extra guesses), a per-address cool-down, and a rate limit; and it never says
 * whether an email has an account.
 */
class LoginLockoutTest extends TestCase
{
    private function user(string $email, string $role = 'customer'): User
    {
        $roleId = (int) (DB::table('roles')->where('slug', $role)->value('id')
            ?: DB::table('roles')->insertGetId(['slug' => $role, 'name' => ucfirst($role), 'created_at' => now(), 'updated_at' => now()]));

        return User::forceCreate(['name' => 'Shopper', 'email' => $email, 'password' => bcrypt('secret-password'), 'email_verified_at' => now(), 'role_id' => $roleId]);
    }

    private function shopLogin(string $email, string $password): TestResponse
    {
        return $this->post(route('shop.account.login'), ['account_email' => $email, 'account_password' => $password]);
    }

    public function test_five_wrong_passwords_lock_the_account_on_the_shop_form(): void
    {
        $this->user('shopper@example.test');

        for ($i = 1; $i <= 4; $i++) {
            $this->shopLogin('shopper@example.test', 'wrong')->assertSessionHasErrors(['account_email' => 'Invalid email or password. Please try again.']);
        }
        $this->shopLogin('shopper@example.test', 'wrong')
            ->assertSessionHasErrors(['account_email' => 'Too many failed attempts. Your account is locked for 30 minutes.']);

        // Locked: the right password does not get in, on this form or the dashboard's.
        $this->shopLogin('shopper@example.test', 'secret-password')->assertSessionHasErrors('account_email');
        $this->assertGuest();
        $this->post(route('admin.login'), ['email' => 'shopper@example.test', 'password' => 'secret-password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Once the lock is over, it signs in and the count starts over.
        DB::table('users')->where('email', 'shopper@example.test')->update(['blocked_until' => now()->subMinute()]);
        $this->shopLogin('shopper@example.test', 'secret-password');
        $this->assertAuthenticated();
        $this->assertSame(0, (int) DB::table('users')->where('email', 'shopper@example.test')->value('login_attempts'));
    }

    public function test_an_administrator_cannot_be_guessed_through_the_shop_form(): void
    {
        $this->user('boss@example.test', 'administrator');
        for ($i = 0; $i < 5; $i++) {
            $this->shopLogin('boss@example.test', 'guess'.$i);
        }

        $this->assertNotNull(DB::table('users')->where('email', 'boss@example.test')->value('blocked_until'));
        $this->post(route('admin.login'), ['email' => 'boss@example.test', 'password' => 'secret-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_attempts_count_across_both_forms(): void
    {
        $this->user('both@example.test');
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('admin.login'), ['email' => 'both@example.test', 'password' => 'wrong']);
        }
        $this->shopLogin('both@example.test', 'wrong');
        $this->shopLogin('both@example.test', 'wrong')->assertSessionHasErrors(['account_email' => 'Too many failed attempts. Your account is locked for 30 minutes.']);
    }

    public function test_the_shop_form_does_not_say_whether_an_email_has_an_account(): void
    {
        $this->user('real@example.test');

        $known = $this->shopLogin('real@example.test', 'wrong');
        $unknown = $this->shopLogin('nobody@example.test', 'wrong');

        $known->assertSessionHasErrors(['account_email' => 'Invalid email or password. Please try again.']);
        $unknown->assertSessionHasErrors(['account_email' => 'Invalid email or password. Please try again.']);
    }

    public function test_one_address_trying_many_accounts_has_to_wait(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->user('victim@example.test');
        for ($i = 0; $i < LoginGuard::ADDRESS_MAX_FAILURES; $i++) {
            $this->shopLogin("someone{$i}@example.test", 'guess');
        }

        $this->shopLogin('victim@example.test', 'secret-password')->assertSessionHasErrors('account_email');
        $this->assertGuest();
        $this->assertStringContainsString('from your network', session('errors')->first('account_email'));
    }

    public function test_an_address_the_dashboard_blocked_is_refused_on_the_shop_form(): void
    {
        $this->user('shopper2@example.test');
        BlockedIp::create(['ip_address' => '127.0.0.1', 'attempts' => 5]);

        $this->shopLogin('shopper2@example.test', 'secret-password')->assertSessionHasErrors('account_email');
        $this->assertGuest();
    }

    public function test_the_shop_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->shopLogin("x{$i}@example.test", 'guess');
        }
        $this->shopLogin('x@example.test', 'guess')->assertStatus(429);
    }

    public function test_the_dashboard_login_still_behaves_as_before(): void
    {
        $this->user('admin2@example.test', 'administrator');

        $this->post(route('admin.login'), ['email' => 'admin2@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Invalid credentials. You have 4 attempts left before your account is blocked.']);
        DB::table('users')->where('email', 'admin2@example.test')->update(['login_attempts' => 4]);
        $this->post(route('admin.login'), ['email' => 'admin2@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Too many failed attempts. Your account and IP have been blocked for 30 minutes.']);

        DB::table('users')->where('email', 'admin2@example.test')->update(['is_blocked' => 1, 'blocked_until' => null]);
        $this->post(route('admin.login'), ['email' => 'admin2@example.test', 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'Your account has been permanently blocked.']);
    }
}
