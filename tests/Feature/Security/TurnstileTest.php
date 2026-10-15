<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Core\HookManager;
use FalconCms\Core\Database\Seeders\SystemSyncSeeder;
use FalconCms\Core\Support\Turnstile;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile on the site's own forms: off until the keys are saved and a form is
 * ticked; then checked before the form does anything; let through only when Cloudflare is down.
 */
class TurnstileTest extends TestCase
{
    private array $hooks = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = HookManager::snapshot();
    }

    protected function tearDown(): void
    {
        HookManager::restore($this->hooks);
        parent::tearDown();
    }

    private function setOptions(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }
        forget_cms_options_cache();
        falcon_request_memo('turnstile')->exchangeArray([]);
    }

    private function switchOn(string ...$locations): void
    {
        $values = ['turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret_key' => 'secret-key'];
        foreach ($locations as $location) {
            $values['turnstile_on_'.$location] = '1';
        }
        $this->setOptions($values);
    }

    /** What the fake Cloudflare answers next (a fake registered once reads it each time). */
    private ?bool $answer = null;

    private function cloudflare(bool $success): void
    {
        if ($this->answer === null) {
            Http::fake([Turnstile::VERIFY_URL => fn () => Http::response(['success' => $this->answer, 'error-codes' => $this->answer ? [] : ['invalid-input-response']])]);
        }
        $this->answer = $success;
    }

    private function user(string $email = 'karim@example.test'): User
    {
        return User::forceCreate(['name' => 'Karim', 'email' => $email, 'password' => bcrypt('secret-password'), 'email_verified_at' => now(),
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    public function test_it_is_off_until_the_keys_are_saved_and_the_form_is_ticked(): void
    {
        $this->setOptions(['turnstile_on_login' => '1']);
        $this->assertSame('', falcon_turnstile('login'), 'no keys');

        $this->switchOn();
        $this->setOptions(['turnstile_on_login' => '0']);
        $this->assertSame('', falcon_turnstile('login'), 'keys, but the form is not ticked');
        $this->assertNull(falcon_turnstile_check(request(), 'login'));
    }

    public function test_the_widget_and_its_script_once_per_page(): void
    {
        $this->switchOn('login', 'register');

        $first = falcon_turnstile('login');
        $this->assertStringContainsString('class="falcon-turnstile cf-turnstile"', $first);
        $this->assertStringContainsString('data-sitekey="1x00000000000000000000AA"', $first);
        $this->assertStringContainsString('data-action="login"', $first);
        $this->assertStringContainsString('data-size="flexible"', $first, 'stretches with the form');
        $this->assertStringContainsString(Turnstile::SCRIPT_URL, $first);
        $this->assertStringNotContainsString(Turnstile::SCRIPT_URL, falcon_turnstile('register'), 'the script once');
    }

    public function test_the_answer_is_checked_with_cloudflare(): void
    {
        $this->switchOn('login');

        $this->assertSame('Please complete the security check and try again.', falcon_turnstile_check(request(), 'login'), 'no answer at all');

        $this->cloudflare(true);
        request()->merge(['cf-turnstile-response' => 'token-ok']);
        $this->assertNull(falcon_turnstile_check(request(), 'login'));
        Http::assertSent(fn ($r) => $r->url() === Turnstile::VERIFY_URL && $r['secret'] === 'secret-key' && $r['response'] === 'token-ok');

        $this->cloudflare(false);
        $this->assertNotNull(falcon_turnstile_check(request(), 'login'), 'an answer Cloudflare rejects');
    }

    public function test_an_outage_lets_the_form_through(): void
    {
        $this->switchOn('login');
        request()->merge(['cf-turnstile-response' => 'token']);

        Http::fake([Turnstile::VERIFY_URL => Http::response('Bad gateway', 502)]);
        $this->assertNull(falcon_turnstile_check(request(), 'login'));

        Http::fake([Turnstile::VERIFY_URL => fn () => throw new ConnectionException('timed out')]);
        $this->assertNull(falcon_turnstile_check(request(), 'login'));
    }

    public function test_the_login_page_asks_and_checks(): void
    {
        $this->switchOn('login');
        $this->user();

        $this->get(route('admin.login'))->assertOk()->assertSee('cf-turnstile', false);

        $this->cloudflare(false);
        $this->post(route('admin.login'), ['email' => 'karim@example.test', 'password' => 'secret-password', 'cf-turnstile-response' => 'bad'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->cloudflare(true);
        $this->post(route('admin.login'), ['email' => 'karim@example.test', 'password' => 'secret-password', 'cf-turnstile-response' => 'good']);
        $this->assertAuthenticated();
    }

    public function test_registration_and_forgot_password_are_checked(): void
    {
        $this->switchOn('register', 'password_reset');
        $this->setOptions(['users_can_register' => '1']);
        (new SystemSyncSeeder)->run();
        $this->cloudflare(false);

        $this->get(route('admin.register'))->assertOk()->assertSee('data-action="register"', false);
        $this->post(route('admin.register'), ['name' => 'New', 'email' => 'new@example.test', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'])
            ->assertSessionHasErrors('email');
        $this->assertFalse(User::where('email', 'new@example.test')->exists(), 'no account made');

        $this->get(route('admin.password.request'))->assertOk()->assertSee('data-action="password_reset"', false);
        $this->post(route('admin.password.email'), ['email' => 'karim@example.test'])->assertSessionHasErrors('email');
    }

    public function test_the_shops_account_sign_in_is_checked(): void
    {
        $this->switchOn('shop_account');
        $this->user('shopper@example.test');
        $this->cloudflare(false);

        $this->post(route('shop.account.login'), ['account_email' => 'shopper@example.test', 'account_password' => 'secret-password', 'cf-turnstile-response' => 'bad'])
            ->assertSessionHasErrors('account_email');
        $this->assertGuest();

        $this->setOptions(['magic_login_enabled' => '1']);
        $this->post(route('shop.magic.request'), ['magic_email' => 'shopper@example.test'])->assertSessionHasErrors('magic_email');
        $this->assertSame(0, DB::table('magic_login_tokens')->count(), 'no link was made');

        $this->cloudflare(true);
        $this->post(route('shop.account.login'), ['account_email' => 'shopper@example.test', 'account_password' => 'secret-password', 'cf-turnstile-response' => 'good']);
        $this->assertAuthenticated();
    }

    public function test_the_settings_screen_switches_forms_on_including_a_plugins(): void
    {
        add_falcon_filter('falcon_turnstile_locations', fn ($l) => $l + ['plugin_form' => 'A plugin form']);
        $this->switchOn();
        $admin = $this->user('admin@example.test');

        $html = $this->actingAs($admin)->get(route('admin.settings.integrations'))->assertOk()->getContent();
        $this->assertStringContainsString('name="turnstile_on[login]"', $html);
        $this->assertStringContainsString('name="turnstile_on[plugin_form]"', $html);

        $this->post(route('admin.settings.integrations.update'), [
            'turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret_key' => 'secret-key',
            'turnstile_locations_present' => '1', 'turnstile_on' => ['login' => '1', 'plugin_form' => '1'],
        ])->assertRedirect();

        $this->assertSame('1', get_cms_option('turnstile_on_login'));
        $this->assertSame('0', get_cms_option('turnstile_on_register'));
        $this->assertSame('1', get_cms_option('turnstile_on_plugin_form'));
        $this->assertTrue(Turnstile::enabled('plugin_form'));
    }
}
