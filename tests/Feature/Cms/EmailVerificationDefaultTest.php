<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Whether a new user has to confirm their email address before signing in.
 *
 * Two things pull in opposite directions here, and this file is mostly about keeping
 * them apart.
 *
 * A site five minutes old has no mail server configured, so requiring confirmation means
 * nobody can get in — including whoever just installed it. So a NEW install has it off.
 *
 * A site that has been running for a year may have people who registered and never
 * confirmed. Turning verification off under it would let all of them in on the next
 * update, which is not a change to make on a site owner's behalf. So the fallback, which
 * is what an existing site is using, stays on.
 *
 * The difference between the two is that a new install WRITES the setting rather than
 * relying on the fallback.
 */
class EmailVerificationDefaultTest extends TestCase
{
    /** An existing site that never chose keeps verification on. */
    public function test_the_fallback_is_on(): void
    {
        DB::table('cms_settings')->where('key', 'require_email_verification')->delete();
        forget_cms_options_cache();

        $this->assertTrue(falcon_email_verification_required(),
            'an existing site that never chose would have verification switched off under it');
    }

    /** And whatever a site has actually chosen is what it gets, either way. */
    public function test_the_setting_is_obeyed_once_it_is_set(): void
    {
        foreach (['1' => true, '0' => false] as $value => $expected) {
            DB::table('cms_settings')->updateOrInsert(
                ['key' => 'require_email_verification'],
                ['value' => (string) $value]
            );
            forget_cms_options_cache();

            $this->assertSame($expected, falcon_email_verification_required(), "with the setting at {$value}");
        }
    }

    /**
     * A new install writes it off, rather than leaving it to the fallback.
     *
     * Written out is the whole mechanism: it is what separates a site that has chosen
     * nothing (fallback, on) from a site that was just created (written, off).
     */
    public function test_a_new_install_switches_it_off_explicitly(): void
    {
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Console/Commands/InstallFalconCms.php'
        );

        $this->assertMatchesRegularExpression(
            "/'require_email_verification' => '0'/",
            $installer,
            'a fresh install no longer turns email verification off, so nobody can sign in to it'
        );
    }

    /**
     * The administrator the installer creates is verified.
     *
     * That account was typed in at the console by whoever owns the site; there is nobody
     * to confirm it to. Left unverified it is how a site locks its own administrator out
     * the first time verification is switched on — with the reset mail going through the
     * mail server they were trying to configure.
     */
    public function test_the_installed_administrator_is_verified(): void
    {
        $installer = (string) file_get_contents(
            __DIR__.'/../../../src/Console/Commands/InstallFalconCms.php'
        );

        $this->assertStringContainsString("'email_verified_at' => \$user->email_verified_at ?? now()", $installer,
            'the administrator the installer creates cannot sign in once verification is turned on');
    }

    /**
     * And what it means at the login screen, which is the only place it matters.
     *
     * Reading the setting is not the same as acting on it, so this goes through the real
     * route: an account that never confirmed its address is turned away when the site
     * requires it and let in when it does not — which is what a new install now gets.
     */
    public function test_an_unconfirmed_account_is_let_in_only_when_verification_is_off(): void
    {
        $email = 'never-confirmed@example.test';
        $user = $this->unverifiedUser($email);

        $this->setVerification('1');
        $this->post($this->loginUrl(), ['email' => $email, 'password' => 'secret-password'])
            ->assertRedirect(route('admin.verify.notice'));
        // Turned away: it is the verification notice, not the dashboard.
        $this->assertGuest();

        $this->setVerification('0');
        $this->post($this->loginUrl(), ['email' => $email, 'password' => 'secret-password']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    private function setVerification(string $value): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => 'require_email_verification'], ['value' => $value]);
        forget_cms_options_cache();
    }

    /**
     * The login URL as registered, not as rebuilt from the option.
     *
     * The slug is read while routes are being registered, so a test that changes the
     * option afterwards and builds its own path posts at a URL nothing is listening on.
     * The POST route shares its URI with the named GET one.
     */
    private function loginUrl(): string
    {
        return route('admin.login');
    }

    private function unverifiedUser(string $email): User
    {
        return User::create([
            'name' => 'Never Confirmed',
            'email' => $email,
            'password' => Hash::make('secret-password'),
        ]);
    }

    /**
     * One answer, in one place.
     *
     * It used to be read in four — both controllers that act on it and the screen that
     * draws the checkbox — each spelling out the default for itself. A settings screen
     * showing the box ticked while the login screen lets everyone through is not a
     * difference anyone would go looking for.
     */
    public function test_nothing_spells_out_the_default_for_itself(): void
    {
        $root = __DIR__.'/../../../';

        foreach ([
            'src/Http/Controllers/Admin/LoginController.php',
            'src/Http/Controllers/Admin/RegisterController.php',
            'resources/views/admin/settings/index.blade.php',
        ] as $file) {
            $source = (string) file_get_contents($root.$file);

            $this->assertStringContainsString('falcon_email_verification_required', $source,
                "{$file} does not use the shared answer");
            $this->assertStringNotContainsString("get_cms_option('require_email_verification'", $source,
                "{$file} still carries its own copy of the default");
        }
    }
}
