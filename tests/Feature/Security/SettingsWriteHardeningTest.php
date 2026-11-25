<?php

namespace FalconCms\Core\Tests\Feature\Security;

use App\Models\User;
use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Pro\LicenseGateway;
use FalconCms\Core\Support\AdminMenu;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\Doubles\LicensedGateway;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * SEO → Settings and Settings → General store their own settings and nothing else.
 *
 * Before: updateSeoSettings() and updateSettings() wrote every key of the request into
 * cms_settings, skipping only the licence keys. Anyone with SEO Settings (access_seo_settings, a
 * permission meant for SEO staff) could write theme_footer_script — printed unescaped on every
 * page, so script in front of every signed-in administrator — or Falcon Security's settings, the
 * lockdown, the default role, registration, the active theme. Settings → General did the same
 * for whoever held manage_settings, around every screen that owns those settings.
 */
class SettingsWriteHardeningTest extends TestCase
{
    use MakesShopFixtures;

    private const ORIGINAL = [
        'theme_footer_script' => '',
        'theme_head_script' => '',
        'theme_head_html' => '',
        'header_ad_code' => '',
        'theme_custom_css' => '',
        'theme_footer_copyright' => '© Site',
        'security_lockdown' => '',
        'security_admin_allowlist_on' => '1',
        'security_firewall' => '1',
        'default_role' => 'subscriber',
        'users_can_register' => '0',
        'require_email_verification' => '1',
        'login_url' => 'my-login',
        'register_url' => 'my-register',
        'allow_multi_device' => '0',
        'max_devices' => '3',
        'magic_login_enabled' => '0',
        'enable_rest_api' => '1',
        'activity_log_enabled' => '1',
        'active_theme' => 'falcon-theme',
        'ai_writer_roles' => 'administrator',
        'admin_email' => 'owner@site.test',
        'turnstile_secret_key' => 'original-secret',
        'falcon_license_key' => 'ORIGINAL-KEY',
        'falcon_freemium_state' => 'original',
        'site_title' => 'Original Site',
        'tagline' => 'Original tagline',
        'seo_separator' => '-',
        'robots_txt' => 'User-agent: *',
        'perf_minify_html' => '0',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        (new MenuSeeder)->run();
        $this->setCmsOptions(self::ORIGINAL);
    }

    private function role(string $slug, array $permissions): int
    {
        $id = (int) DB::table('roles')->insertGetId(['name' => ucfirst($slug), 'slug' => $slug.'-'.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions as $p) {
            $pid = DB::table('permissions')->where('slug', $p)->value('id')
                ?: DB::table('permissions')->insertGetId(['name' => $p, 'slug' => $p, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insert(['role_id' => $id, 'permission_id' => $pid]);
        }

        return $id;
    }

    /** SEO staff: SEO → Settings and nothing else. */
    private function seoUser(): User
    {
        return $this->makeUser(['role_id' => $this->role('seo-staff', ['access_dashboard', 'access_seo', 'access_seo_settings'])]);
    }

    /** A non-admin with Settings → General. */
    private function settingsManager(): User
    {
        return $this->makeUser(['role_id' => $this->role('settings-manager', ['access_dashboard', 'manage_settings', 'access_general_settings'])]);
    }

    private function admin(): User
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function superAdmin(): User
    {
        // The test database seeds no Super Admin role; one is made the way an install has it.
        $id = (int) DB::table('roles')->where('slug', 'super-admin')->value('id')
            ?: (int) DB::table('roles')->insertGetId(['name' => 'Super Admin', 'slug' => 'super-admin', 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->makeUser(['role_id' => $id]);
        $this->assertTrue($user->isAdmin() && $user->hasRole('super-admin'));

        return $user;
    }

    /** Multi-device and Magic Login are Pro: a licensed site, so they can be changed at all. */
    private function licensed(): void
    {
        $this->app->instance(LicenseGateway::class, new LicensedGateway);
    }

    private function option(string $key): ?string
    {
        $value = DB::table('cms_settings')->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    private function assertUntouched(?array $keys = null): void
    {
        foreach ($keys ?? array_keys(self::ORIGINAL) as $key) {
            $this->assertSame(self::ORIGINAL[$key], $this->option($key), "{$key} was changed");
        }
    }

    private function seoSave(User $user, array $payload)
    {
        return $this->actingAs($user)->from('/admin/seo/settings')->post('/admin/seo/settings', $payload);
    }

    private function generalSave(User $user, array $payload)
    {
        return $this->actingAs($user)->from('/admin/settings')->post('/admin/settings', $payload);
    }

    /** Options no settings screen but their own may write (code, access, protected). */
    public static function foreignOptions(): array
    {
        return [
            'footer script' => ['theme_footer_script', '<script>fetch("/admin/users")</script>'],
            'head script' => ['theme_head_script', 'alert(1)'],
            'head HTML' => ['theme_head_html', '<script src="//evil.test/x.js"></script>'],
            'ad code' => ['header_ad_code', '<img src=x onerror=alert(1)>'],
            'custom CSS' => ['theme_custom_css', 'body{background:url(//evil.test)}'],
            'footer copyright (raw HTML)' => ['theme_footer_copyright', '<script>alert(1)</script>'],
            'Falcon Security lockdown' => ['security_lockdown', '{"on":true}'],
            'Falcon Security allowlist' => ['security_admin_allowlist_on', '0'],
            'Falcon Security firewall' => ['security_firewall', '0'],
            'active theme' => ['active_theme', 'other-theme'],
            'AI writer roles' => ['ai_writer_roles', 'subscriber'],
            'Turnstile secret' => ['turnstile_secret_key', 'attacker'],
            'licence key' => ['falcon_license_key', 'FORGED'],
            'freemium state' => ['falcon_freemium_state', 'forged'],
        ];
    }

    /** General's own access settings: General may write them, SEO may not. */
    public static function generalAccessOptions(): array
    {
        return [
            'default role' => ['default_role', 'editor'],
            'registration' => ['users_can_register', '1'],
            'magic login' => ['magic_login_enabled', '1'],
            'administration email' => ['admin_email', 'attacker@evil.test'],
        ];
    }

    // ── SEO → Settings ────────────────────────────────────────────────────

    #[DataProvider('foreignOptions')]
    #[DataProvider('generalAccessOptions')]
    public function test_seo_staff_cannot_write_a_setting_seo_does_not_own(string $key, string $value): void
    {
        // A legitimate SEO change rides along: the request is refused whole, so it is not saved either.
        $this->seoSave($this->seoUser(), ['seo_separator' => '|', $key => $value])
            ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'Nothing was saved'));

        $this->assertUntouched();
    }

    public function test_seo_staff_cannot_write_through_a_nested_or_malformed_key(): void
    {
        $user = $this->seoUser();
        $this->seoSave($user, ['seo_separator' => '|', 'theme_footer_script' => ['<script>1</script>']])->assertSessionHas('error');
        $this->seoSave($user, ['seo_separator' => '|', 'THEME_FOOTER_SCRIPT' => '<script>1</script>'])->assertSessionHas('error');
        $this->seoSave($user, ['seo_separator' => ['|']])->assertSessionHas('error');

        $this->assertUntouched();
        $this->assertNull($this->option('THEME_FOOTER_SCRIPT'));
    }

    public function test_settings_seo_does_not_store_are_left_out(): void
    {
        $this->seoSave($this->seoUser(), [
            'seo_separator' => '|',
            'site_title' => 'Taken over', 'perf_minify_html' => '1', 'some_unknown_setting' => 'x',
            'stripe_secret' => 'sk_live_x',
        ])->assertSessionMissing('error');

        $this->assertSame('|', $this->option('seo_separator'));
        $this->assertUntouched(['site_title', 'perf_minify_html']);
        $this->assertNull($this->option('some_unknown_setting'));
        $this->assertNull($this->option('stripe_secret'));
    }

    public function test_seo_staff_still_save_every_seo_setting(): void
    {
        $bing = '0123456789abcdef0123456789abcdef';
        $this->seoSave($this->seoUser(), [
            'seo_separator' => '|', 'robots_txt' => "User-agent: *\nDisallow: /tmp", 'twitter_handle' => '@site',
            'twitter_card_type' => 'summary', 'seo_default_image' => 'media/share.png', 'category_base' => 'topics',
            'sitemap_include_categories' => '1', 'noindex' => '1',
            // Falcon SEO's own sections: organisation, keywords, the Bing key, a checkbox.
            'seo_org_name' => 'Site Ltd', 'keyword_country' => 'bd', 'bing_webmaster_api_key' => $bing, 'seo_404_log' => '1',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->assertSame('|', $this->option('seo_separator'));
        $this->assertSame("User-agent: *\nDisallow: /tmp", $this->option('robots_txt'));
        $this->assertSame('@site', $this->option('twitter_handle'));
        $this->assertSame('topics', $this->option('category_base'));
        $this->assertSame('category', $this->option('category_base_previous'));
        $this->assertSame('1', $this->option('sitemap_include_categories'));
        $this->assertSame('0', $this->option('sitemap_include_tags'), 'an unticked box is saved off');
        $this->assertSame('1', $this->option('noindex'));
        $this->assertSame('Site Ltd', $this->option('seo_org_name'));
        $this->assertSame('bd', $this->option('keyword_country'));
        $this->assertSame('1', $this->option('seo_404_log'));
        $this->assertSame($bing, Crypt::decryptString((string) $this->option('bing_webmaster_api_key')), 'the key is stored encrypted');
    }

    public function test_a_plugin_filter_may_add_seo_values_but_not_foreign_ones(): void
    {
        // The way Falcon News works: it reads its ticked lists from the request and puts values back.
        add_falcon_filter('falcon_seo_settings_data', function ($data, $request = null) {
            $data = (array) $data;
            if ($request && $request->has('news_sitemap_section')) {
                $data['news_sitemap_post_types'] = implode(',', (array) $request->input('news_sitemap_post_types', []));
            }

            return $data;
        });
        $this->seoSave($this->seoUser(), ['seo_separator' => '|', 'news_sitemap_section' => '1', 'news_sitemap_post_types' => ['post', 'page']])
            ->assertSessionMissing('error');
        $this->assertSame('post,page', $this->option('news_sitemap_post_types'));
        $this->assertNull($this->option('news_sitemap_section'), 'the section marker is not a setting');

        // A filter that puts a foreign option back is held to the same line, and nothing is saved.
        add_falcon_filter('falcon_seo_settings_data', fn ($data) => (array) $data + ['security_lockdown' => '{"on":true}']);
        $this->seoSave($this->seoUser(), ['seo_separator' => '+'])->assertSessionHas('error');
        $this->assertUntouched(['security_lockdown']);
        $this->assertSame('|', $this->option('seo_separator'));
    }

    public function test_a_plugin_checkbox_named_after_a_foreign_option_is_never_written(): void
    {
        // A checkbox is saved whether or not it is posted (unticked = 0). Before, a plugin naming
        // default_role or security_lockdown here let SEO staff overwrite them just by saving.
        add_falcon_filter('falcon_seo_settings_checkboxes', fn ($boxes) => array_merge((array) $boxes, ['default_role', 'security_lockdown', 'seo_breadcrumbs']));

        $this->seoSave($this->seoUser(), ['seo_separator' => '|', 'seo_breadcrumbs' => '1', 'noindex' => '1'])
            ->assertSessionMissing('error');
        $this->assertUntouched(['default_role', 'security_lockdown']);
        $this->assertSame('|', $this->option('seo_separator'));
        $this->assertSame('1', $this->option('seo_breadcrumbs'), 'a plugin\'s own checkbox still saves');
        $this->assertSame('1', $this->option('noindex'));
        $this->assertSame('0', $this->option('sitemap_include_tags'), 'an unticked box is still saved off');

        // Posting them ticked changes nothing either.
        $this->seoSave($this->seoUser(), ['seo_separator' => '+', 'default_role' => '1', 'security_lockdown' => '1']);
        $this->assertUntouched(['default_role', 'security_lockdown']);
    }

    public function test_fields_registered_for_the_seo_screen_save_there(): void
    {
        falcon_add_settings_field(['id' => 'gsc_token', 'label' => 'Search Console', 'screen' => 'seo']);
        falcon_add_settings_field(['id' => 'seo_extra_tags', 'label' => 'Tags', 'type' => 'tags', 'screen' => 'seo']);

        $this->seoSave($this->seoUser(), ['gsc_token' => 'tok-123', 'seo_extra_tags' => ['a', 'b']])->assertSessionMissing('error');
        $this->assertSame('tok-123', $this->option('gsc_token'));
        $this->assertSame('["a","b"]', $this->option('seo_extra_tags'));
    }

    public function test_a_field_registered_under_a_foreign_option_name_is_still_refused(): void
    {
        falcon_add_settings_field(['id' => 'theme_footer_script', 'label' => 'Tracking', 'screen' => 'seo']);
        falcon_add_settings_field(['id' => 'security_firewall', 'label' => 'Firewall', 'screen' => 'general']);

        $this->seoSave($this->seoUser(), ['theme_footer_script' => '<script>1</script>'])->assertSessionHas('error');
        $this->generalSave($this->admin(), ['site_title' => 'New', 'security_firewall' => '0'])->assertSessionHas('error');

        $this->assertUntouched();
    }

    public function test_seo_settings_stay_closed_to_everyone_else(): void
    {
        $this->seoSave($this->makeUser(['role_id' => $this->role('writer', ['access_dashboard', 'manage_posts'])]), ['seo_separator' => '|'])
            ->assertForbidden();
        $this->assertUntouched(['seo_separator']);
    }

    // ── Settings → General ────────────────────────────────────────────────

    #[DataProvider('foreignOptions')]
    public function test_a_settings_manager_cannot_write_a_setting_general_does_not_own(string $key, string $value): void
    {
        $this->generalSave($this->settingsManager(), ['site_title' => 'Changed', $key => $value])
            ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'Nothing was saved'));

        $this->assertUntouched();
    }

    #[DataProvider('foreignOptions')]
    public function test_an_administrator_cannot_either_those_settings_have_their_own_screens(string $key, string $value): void
    {
        $this->generalSave($this->admin(), ['site_title' => 'Changed', $key => $value])->assertSessionHas('error');

        $this->assertUntouched();
    }

    public function test_settings_general_does_not_store_are_left_out(): void
    {
        $this->generalSave($this->settingsManager(), [
            'site_title' => 'Changed', 'perf_minify_html' => '1', 'robots_txt' => 'Disallow: /', 'some_unknown_setting' => 'x',
            'bing_webmaster_api_key' => 'abc', 'activity_log_form' => '1',
        ])->assertSessionMissing('error');

        $this->assertSame('Changed', $this->option('site_title'));
        $this->assertUntouched(['perf_minify_html', 'robots_txt']);
        $this->assertNull($this->option('some_unknown_setting'));
        $this->assertNull($this->option('bing_webmaster_api_key'));
        $this->assertNull($this->option('activity_log_form'), 'the form marker is not a setting');
    }

    public function test_general_settings_still_save(): void
    {
        $this->generalSave($this->settingsManager(), [
            'settings_screen' => 'general', 'site_title' => 'New Site', 'tagline' => 'New tagline', 'timezone' => 'Asia/Dhaka',
            'activity_log_enabled' => 'on', 'activity_log_retention' => '48',
        ])->assertRedirect()->assertSessionMissing('error');

        $this->assertSame('New Site', $this->option('site_title'));
        $this->assertSame('New tagline', $this->option('tagline'));
        $this->assertSame('Asia/Dhaka', $this->option('timezone'));
        $this->assertSame('1', $this->option('activity_log_enabled'));
        $this->assertSame('48', $this->option('activity_log_retention'));
        // The page's access fields are not sent by a non-admin (they are disabled), and the
        // boxes among them are not read as "unticked" for one: all ten stay as they were.
        $this->assertUntouched(self::ACCESS_KEYS);
    }

    // ── The ten General access settings: administrators only ──────────────

    private const ACCESS_KEYS = [
        'users_can_register', 'require_email_verification', 'default_role', 'login_url', 'register_url',
        'allow_multi_device', 'max_devices', 'magic_login_enabled', 'enable_rest_api', 'admin_email',
    ];

    /** Each of the ten, with a value different from the stored one. */
    public static function accessChanges(): array
    {
        return [
            'registration' => ['users_can_register', '1'],
            'email verification' => ['require_email_verification', '0'],
            'default role' => ['default_role', 'editor'],
            'login address' => ['login_url', 'new-login'],
            'registration address' => ['register_url', 'new-register'],
            'device limit switch' => ['allow_multi_device', '1'],
            'device limit' => ['max_devices', '9'],
            'magic login' => ['magic_login_enabled', '1'],
            'REST API' => ['enable_rest_api', '0'],
            'administration email' => ['admin_email', 'attacker@evil.test'],
        ];
    }

    #[DataProvider('accessChanges')]
    public function test_a_settings_manager_cannot_change_an_access_setting(string $key, string $value): void
    {
        $this->licensed();
        $manager = $this->settingsManager();

        // From either screen, and with the value it already has: the field is not theirs to send.
        foreach (['general', 'api', null] as $screen) {
            foreach ([$value, self::ORIGINAL[$key]] as $sent) {
                $payload = ['site_title' => 'Changed', $key => $sent] + ($screen ? ['settings_screen' => $screen] : []);
                $this->generalSave($manager, $payload)
                    ->assertSessionHas('error', fn ($m) => str_contains((string) $m, 'Only an administrator') && str_contains((string) $m, 'Nothing was saved'));
            }
        }

        $this->assertUntouched();
    }

    public function test_the_general_page_does_not_offer_access_settings_to_a_settings_manager(): void
    {
        $html = $this->actingAs($this->settingsManager())->get('/admin/settings')->assertOk()->getContent();
        foreach (['admin_email', 'users_can_register', 'require_email_verification', 'register_url', 'login_url', 'default_role', 'allow_multi_device', 'max_devices', 'magic_login_enabled'] as $name) {
            $this->assertMatchesRegularExpression('/<(input|select)[^>]*name="'.$name.'"[^>]*disabled/s', $html, "{$name} is offered to a non-admin");
        }
        $this->assertStringContainsString('can only be changed by an administrator', $html);

        $admin = $this->actingAs($this->admin())->get('/admin/settings')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<(input|select)[^>]*name="login_url"[^>]*disabled/s', $admin);
    }

    public static function administrators(): array
    {
        return ['an administrator' => ['admin'], 'a Super Admin' => ['superAdmin']];
    }

    #[DataProvider('administrators')]
    public function test_administrators_change_every_access_setting(string $who): void
    {
        $this->licensed();
        $user = $this->{$who}();

        // The General form, as the browser sends it: ticked boxes only.
        $this->generalSave($user, [
            'settings_screen' => 'general', 'activity_log_form' => '1', 'activity_log_enabled' => 'on',
            'site_title' => 'Admin Site', 'admin_email' => 'team@site.test',
            'users_can_register' => 'on', /* require_email_verification unticked */ 'default_role' => 'editor',
            'login_url' => 'New Login', 'register_url' => 'new-register',
            'allow_multi_device' => 'on', 'max_devices' => '9', 'magic_login_enabled' => 'on',
        ])->assertSessionMissing('error');

        $this->assertSame('Admin Site', $this->option('site_title'));
        $this->assertSame('team@site.test', $this->option('admin_email'));
        $this->assertSame('1', $this->option('users_can_register'));
        $this->assertSame('0', $this->option('require_email_verification'));
        $this->assertSame('editor', $this->option('default_role'));
        $this->assertSame('new-login', $this->option('login_url'));
        $this->assertSame('new-register', $this->option('register_url'));
        $this->assertSame('1', $this->option('allow_multi_device'));
        $this->assertSame('9', $this->option('max_devices'));
        $this->assertSame('1', $this->option('magic_login_enabled'));
        $this->assertSame('1', $this->option('enable_rest_api'), 'the General form does not touch the REST API switch');

        // The API form: off, then on again.
        $this->generalSave($user, ['settings_screen' => 'api'])->assertSessionMissing('error');
        $this->assertSame('0', $this->option('enable_rest_api'));
        $this->generalSave($user, ['settings_screen' => 'api', 'enable_rest_api' => '1'])->assertSessionMissing('error');
        $this->assertSame('1', $this->option('enable_rest_api'));
    }

    // ── API settings save in isolation ────────────────────────────────────

    public function test_saving_the_api_screen_leaves_every_general_setting_alone(): void
    {
        $this->licensed();
        $on = ['users_can_register' => '1', 'require_email_verification' => '1', 'allow_multi_device' => '1', 'magic_login_enabled' => '1',
            'activity_log_enabled' => '1', 'activity_log_autoprune' => '1', 'max_devices' => '7', 'default_role' => 'editor'];
        $this->setCmsOptions($on);

        foreach ([$this->admin(), $this->superAdmin()] as $user) {
            $this->generalSave($user, ['settings_screen' => 'api'])->assertSessionMissing('error');
            $this->generalSave($user, ['settings_screen' => 'api', 'enable_rest_api' => '1'])->assertSessionMissing('error');
            foreach ($on as $key => $value) {
                $this->assertSame($value, $this->option($key), "saving the API screen changed {$key}");
            }
            $this->assertUntouched(['site_title', 'tagline', 'login_url', 'register_url', 'admin_email', 'security_lockdown']);
        }
    }

    public function test_the_api_screen_is_known_by_its_marker_and_a_general_save_never_switches_the_api_off(): void
    {
        $admin = $this->admin();

        // The old condition never matched (the form posts to /admin/settings): switching off did nothing.
        $this->actingAs($admin)->from('/admin/settings/api')->post('/admin/settings', ['settings_screen' => 'api'])->assertSessionMissing('error');
        $this->assertSame('0', $this->option('enable_rest_api'));

        // The General form (new or old, known by its activity log marker) leaves it alone.
        $this->generalSave($admin, ['settings_screen' => 'general', 'site_title' => 'A'])->assertSessionMissing('error');
        $this->generalSave($admin, ['activity_log_form' => '1', 'site_title' => 'B'])->assertSessionMissing('error');
        $this->assertSame('0', $this->option('enable_rest_api'));
        $this->assertSame('B', $this->option('site_title'));

        // An unknown screen name is no screen: nothing is read as unticked.
        $this->setCmsOptions(['users_can_register' => '1']);
        $this->generalSave($admin, ['settings_screen' => 'shop', 'site_title' => 'C'])->assertSessionMissing('error');
        $this->assertSame('1', $this->option('users_can_register'));
        $this->assertSame('0', $this->option('enable_rest_api'));
    }

    public function test_a_settings_manager_saving_the_api_screen_changes_nothing_they_may_not(): void
    {
        $this->setCmsOptions(['enable_rest_api' => '1', 'users_can_register' => '1']);
        // The disabled REST API box is not sent; that is not "switch it off".
        $this->generalSave($this->settingsManager(), ['settings_screen' => 'api'])->assertSessionMissing('error');
        $this->generalSave($this->settingsManager(), ['settings_screen' => 'general', 'site_title' => 'X'])->assertSessionMissing('error');

        $this->assertSame('1', $this->option('enable_rest_api'));
        $this->assertSame('1', $this->option('users_can_register'));
        $this->assertSame('X', $this->option('site_title'));
    }

    public function test_an_administrator_keeps_the_registration_and_sign_in_settings(): void
    {
        $this->generalSave($this->admin(), [
            'site_title' => 'Admin Site', 'users_can_register' => '1', 'default_role' => 'editor', 'login_url' => 'Team Login',
            'require_email_verification' => '1',
        ])->assertSessionMissing('error');

        $this->assertSame('Admin Site', $this->option('site_title'));
        $this->assertSame('1', $this->option('users_can_register'));
        $this->assertSame('editor', $this->option('default_role'));
        $this->assertSame('team-login', $this->option('login_url'));
    }

    public function test_fields_registered_for_general_save_there(): void
    {
        falcon_add_settings_field(['id' => 'google_maps_key', 'label' => 'Google Maps API Key']);
        falcon_add_settings_field(['id' => 'site_badges', 'label' => 'Badges', 'type' => 'multiselect', 'options' => ['a' => 'A', 'b' => 'B']]);

        $this->generalSave($this->settingsManager(), ['site_title' => 'Site', 'google_maps_key' => 'maps-1', 'site_badges' => ['a', 'b']])
            ->assertSessionMissing('error');
        $this->assertSame('maps-1', $this->option('google_maps_key'));
        $this->assertSame('["a","b"]', $this->option('site_badges'));

        // A field that is not a list cannot be sent one.
        $this->generalSave($this->settingsManager(), ['site_title' => ['x']])->assertSessionHas('error');
        $this->assertSame('Site', $this->option('site_title'));
    }

    // ── The other screens that write settings ─────────────────────────────

    public function test_the_customizer_import_still_refuses_sensitive_options(): void
    {
        foreach (['default_role', 'users_can_register', 'security_lockdown', 'turnstile_secret_key', 'falcon_license_key', 'gsc_token', 'bing_webmaster_api_key', 'admin_email'] as $key) {
            $this->assertTrue(falcon_is_sensitive_option($key), "{$key} is sensitive");
        }
        // The Customizer owns its code options, so its import keeps them (as before).
        foreach (['theme_footer_script', 'theme_head_html', 'site_title', 'theme_site_width'] as $key) {
            $this->assertFalse(falcon_is_sensitive_option($key), "{$key} is not sensitive");
        }
        $this->assertSame('code', falcon_option_guard('theme_footer_script'));
        $this->assertSame('access', falcon_option_guard('security_lockdown'));
        $this->assertSame('protected', falcon_option_guard('falcon_license_key'));
        $this->assertSame('secret', falcon_option_guard('gsc_token'));
        $this->assertNull(falcon_option_guard('seo_separator'));
    }

    public function test_the_customizer_save_writes_only_its_own_fields(): void
    {
        $this->actingAs($this->admin())->from('/admin/customizer')
            ->post('/admin/customizer', ['_section' => 'layout', 'security_lockdown' => '{"on":true}', 'default_role' => 'editor', 'falcon_license_key' => 'FORGED'])
            ->assertRedirect();

        $this->assertUntouched(['security_lockdown', 'default_role', 'falcon_license_key']);
    }

    /**
     * Fields registered in code under a guarded option's name. The name comes from code, but the
     * screen may be open to a non-administrator: before, only the licence keys were skipped.
     *
     * @return array<string, string>
     */
    private function guardedFieldPost(): array
    {
        return [
            'default_role' => 'editor',                       // access
            'theme_footer_script' => '<script>1</script>',    // code
            'falcon_license_key' => 'FORGED',                  // protected
            'myplugin_api_key' => 'plugin-secret',             // secret: the plugin's own key
            'myplugin_note' => 'hello',                        // ordinary
        ];
    }

    private function registerGuardedFields(array $where): void
    {
        foreach (array_keys($this->guardedFieldPost()) as $name) {
            falcon_add_settings_field(['id' => $name, 'label' => $name] + $where);
        }
    }

    private function assertGuardedFieldsSaved(bool $byAdmin): void
    {
        $this->assertSame('plugin-secret', $this->option('myplugin_api_key'), 'a plugin\'s own API key still saves');
        $this->assertSame('hello', $this->option('myplugin_note'));
        $this->assertUntouched(['falcon_license_key']);
        if ($byAdmin) {
            $this->assertSame('editor', $this->option('default_role'));
            $this->assertSame('<script>1</script>', $this->option('theme_footer_script'));
        } else {
            $this->assertUntouched(['default_role', 'theme_footer_script']);
        }
    }

    public static function fieldWriters(): array
    {
        return ['a settings manager' => [false], 'an administrator' => [true]];
    }

    #[DataProvider('fieldWriters')]
    public function test_a_settings_tab_field_named_after_a_guarded_option(bool $byAdmin): void
    {
        falcon_add_settings_tab(['id' => 'extras', 'label' => 'Extras']);
        $this->registerGuardedFields(['tab' => 'extras']);

        $this->actingAs($byAdmin ? $this->admin() : $this->settingsManager())->from('/admin/settings/extras')
            ->post('/admin/settings/extras', $this->guardedFieldPost())->assertRedirect();

        $this->assertGuardedFieldsSaved($byAdmin);
    }

    #[DataProvider('fieldWriters')]
    public function test_an_integrations_field_named_after_a_guarded_option(bool $byAdmin): void
    {
        $this->registerGuardedFields(['screen' => 'integrations']);

        $user = $byAdmin ? $this->admin()
            : $this->makeUser(['role_id' => $this->role('integrations-manager', ['access_dashboard', 'manage_settings', 'access_integrations_settings'])]);
        $this->actingAs($user)->from('/admin/settings/integrations')
            ->post('/admin/settings/integrations', $this->guardedFieldPost())->assertRedirect();

        $this->assertGuardedFieldsSaved($byAdmin);
    }

    #[DataProvider('fieldWriters')]
    public function test_an_options_page_field_named_after_a_guarded_option(bool $byAdmin): void
    {
        // A fresh registry: it fires the registration hook once and caches.
        $registry = new AdminMenu;
        $this->app->instance(AdminMenu::class, $registry);
        $registry->addOptionsPage([
            'slug' => 'myplugin',
            'menu_title' => 'My Plugin',
            'fields' => array_map(fn ($name) => ['name' => $name, 'label' => $name, 'type' => 'text'], array_keys($this->guardedFieldPost())),
        ]);

        $user = $byAdmin ? $this->admin()
            : $this->makeUser(['role_id' => $this->role('options-manager', ['access_dashboard', 'manage_settings', 'manage_options_myplugin'])]);
        $this->actingAs($user)->from('/admin/options/myplugin')
            ->post('/admin/options/myplugin', $this->guardedFieldPost())->assertRedirect()->assertSessionMissing('error');

        $this->assertGuardedFieldsSaved($byAdmin);
    }

    public function test_a_settings_tab_and_integrations_write_only_their_registered_fields(): void
    {
        falcon_add_settings_tab(['id' => 'extras', 'label' => 'Extras']);
        falcon_add_settings_field(['id' => 'extras_note', 'label' => 'Note', 'tab' => 'extras']);
        $admin = $this->admin();

        $this->actingAs($admin)->from('/admin/settings/extras')
            ->post('/admin/settings/extras', ['extras_note' => 'hello', 'theme_footer_script' => '<script>1</script>', 'security_lockdown' => '{"on":true}'])
            ->assertRedirect();
        $this->assertSame('hello', $this->option('extras_note'));

        $this->actingAs($admin)->from('/admin/settings/integrations')
            ->post('/admin/settings/integrations', ['theme_footer_script' => '<script>1</script>', 'security_firewall' => '0'])
            ->assertRedirect();

        $this->assertUntouched(['theme_footer_script', 'security_lockdown', 'security_firewall']);
    }
}
