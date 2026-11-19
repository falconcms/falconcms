<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Customizer → Import / Export writes only the Customizer's own settings.
 *
 * Before: import() wrote every key of the uploaded JSON straight into cms_settings. Whoever
 * could open the Customizer could set the registration form's default role to administrator
 * and switch registration on, overwrite the licence state (around falcon_is_protected_option,
 * which only the Settings save asked), or replace any other setting.
 */
class CustomizerImportHardeningTest extends TestCase
{
    private function admin(): User
    {
        static $n = 0;
        $n++;

        return User::forceCreate([
            'name' => "Admin {$n}", 'email' => "admin{$n}@example.test", 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function import(mixed $payload): TestResponse
    {
        $json = is_string($payload) ? $payload : json_encode($payload);

        return $this->actingAs($this->admin())
            ->from(route('admin.customizer.index'))
            ->post(route('admin.customizer.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('theme-options.json', $json),
            ]);
    }

    private function option(string $key): ?string
    {
        $value = DB::table('cms_settings')->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    private const ORIGINAL = [
        'default_role' => 'subscriber',
        'users_can_register' => '0',
        'require_email_verification' => '1',
        'falcon_license_key' => 'ORIGINAL-KEY',
        'falcon_license_state' => '{"status":"inactive"}',
        'falcon_grandfathered_features' => '[]',
        'login_url' => 'my-login',
        'security_lockdown' => '0',
        'turnstile_secret_key' => 'original-secret',
        'site_name' => 'Original Site',
        'theme_site_width' => '1240px',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCmsOptions(self::ORIGINAL);
    }

    private function assertUntouched(array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertSame(self::ORIGINAL[$key], $this->option($key), "{$key} was overwritten by an import");
        }
    }

    public function test_arbitrary_settings_cannot_be_written(): void
    {
        $this->import([
            'theme_site_width' => '1100px',
            'site_name' => 'Hacked',
            'totally_unrelated_key' => 'x',
        ])->assertSessionHas('success');

        $this->assertSame('1100px', $this->option('theme_site_width'), 'the Customizer field was not imported');
        $this->assertUntouched(['site_name']);
        $this->assertNull($this->option('totally_unrelated_key'));
    }

    public function test_the_default_role_and_registration_cannot_be_changed(): void
    {
        $this->import([
            'theme_site_width' => '1100px',
            'default_role' => 'administrator',
            'users_can_register' => '1',
            'require_email_verification' => '0',
            'login_url' => 'elsewhere',
        ]);

        $this->assertUntouched(['default_role', 'users_can_register', 'require_email_verification', 'login_url']);
    }

    public function test_licence_options_cannot_be_overwritten(): void
    {
        $this->import([
            'theme_site_width' => '1100px',
            'falcon_license_key' => 'FORGED',
            'falcon_license_state' => '{"status":"active","plan":"agency"}',
            'FALCON_LICENSE_STATE' => 'forged',
            'falcon_license_anything_new' => 'forged',
            'falcon_grandfathered_features' => '["everything"]',
        ]);

        $this->assertUntouched(['falcon_license_key', 'falcon_license_state', 'falcon_grandfathered_features']);
        $this->assertNull($this->option('falcon_license_anything_new'));
        $this->assertNull($this->option('FALCON_LICENSE_STATE'));
    }

    public function test_other_protected_and_sensitive_options_stay_protected(): void
    {
        $this->import([
            'theme_site_width' => '1100px',
            'security_lockdown' => '1',
            'turnstile_secret_key' => 'attacker',
            'enable_rest_api' => '1',
            'active_theme' => 'evil',
            'admin_email' => 'attacker@example.test',
        ]);

        $this->assertUntouched(['security_lockdown', 'turnstile_secret_key']);
        foreach (['enable_rest_api', 'active_theme', 'admin_email'] as $key) {
            $this->assertNull($this->option($key), "{$key} was written by an import");
        }
    }

    public function test_a_plugin_field_named_after_a_sensitive_option_is_still_refused(): void
    {
        // falcon_customizer_sections lets a plugin add fields; one called default_role must not
        // turn the import into a way to set it.
        add_falcon_filter('falcon_customizer_sections', function ($sections) {
            $sections['layout']['fields']['default_role'] = ['type' => 'text', 'label' => 'Role', 'default' => ''];

            return $sections;
        });

        $this->import(['theme_site_width' => '1100px', 'default_role' => 'administrator']);

        $this->assertUntouched(['default_role']);
    }

    public function test_nested_payloads_cannot_bypass_the_restrictions(): void
    {
        $this->import([
            'theme_site_width' => ['value' => '1px', 'default_role' => 'administrator'],
            'theme_layout_type' => ['wide'],
            'default_role' => ['administrator'],
            'settings' => ['default_role' => 'administrator', 'falcon_license_key' => 'FORGED'],
            'theme_page_padding_top' => '70px',
        ])->assertSessionHas('success');

        $this->assertUntouched(['theme_site_width', 'default_role', 'falcon_license_key']);
        $this->assertNull($this->option('theme_layout_type'), 'an array was stored as a setting');
        $this->assertNull($this->option('settings'));
        $this->assertSame('70px', $this->option('theme_page_padding_top'));
    }

    public function test_a_list_or_a_payload_with_no_customizer_settings_imports_nothing(): void
    {
        $this->import([['default_role' => 'administrator'], ['theme_site_width' => '1px']])->assertSessionHas('error');
        $this->import(['default_role' => 'administrator', 'site_name' => 'Hacked'])->assertSessionHas('error');
        $this->import('not json')->assertSessionHas('error');

        $this->assertUntouched(['default_role', 'site_name', 'theme_site_width']);
    }

    public function test_an_export_imports_back_unchanged(): void
    {
        $this->setCmsOptions([
            'theme_layout_type' => 'wide',
            'theme_site_width' => '1180px',
            'theme_page_padding_top' => '40px',
        ]);

        $export = $this->actingAs($this->admin())->get(route('admin.customizer.export'));
        $export->assertOk();
        $data = json_decode((string) $export->getContent(), true);

        $this->assertSame('wide', $data['theme_layout_type'] ?? null);
        $this->assertSame('1180px', $data['theme_site_width'] ?? null);
        foreach (array_keys($data) as $key) {
            $this->assertFalse(falcon_is_sensitive_option($key), "the export carries the sensitive option {$key}");
        }

        $this->setCmsOptions(['theme_layout_type' => 'boxed', 'theme_site_width' => '900px', 'theme_page_padding_top' => '1px']);

        $this->import($data)->assertSessionHas('success');

        $this->assertSame('wide', $this->option('theme_layout_type'));
        $this->assertSame('1180px', $this->option('theme_site_width'));
        $this->assertSame('40px', $this->option('theme_page_padding_top'));
    }

    public function test_scalars_are_stored_the_way_the_customizer_stores_them(): void
    {
        $this->import(['theme_site_width' => 1200, 'to_top_enabled' => true, 'to_top_hide_mobile' => false]);

        $this->assertSame('1200', $this->option('theme_site_width'));
        $this->assertSame('1', $this->option('to_top_enabled'));
        $this->assertSame('0', $this->option('to_top_hide_mobile'));
    }

    public function test_custom_fonts_go_through_their_own_checks(): void
    {
        // Not a Media Library font: CustomFonts::save() drops it, as the Customizer screen would.
        $this->import([
            'theme_site_width' => '1100px',
            'custom_fonts' => json_encode([['family' => 'Evil', 'files' => ['woff2' => '../../.env']]]),
        ])->assertSessionHas('success');

        $this->assertStringNotContainsString('.env', (string) $this->option('custom_fonts'));
    }

    public function test_only_settings_managers_may_import(): void
    {
        $subscriber = User::forceCreate([
            'name' => 'Sub', 'email' => 'sub@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);

        $this->actingAs($subscriber)->post(route('admin.customizer.import'), [
            'import_file' => UploadedFile::fake()->createWithContent('t.json', json_encode(['theme_site_width' => '1px'])),
        ])->assertForbidden();

        $this->assertUntouched(['theme_site_width']);
    }
}
