<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Pro\LicenseGateway;
use FalconCms\Core\Tests\Doubles\LicensedGateway;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Analytics → Visitor Log: the per-visit record — time, IP, country/city, page — behind the
 * charts. Pro (analytics) like the rest of the page, filterable by country and search, and
 * exportable to CSV.
 */
class VisitorLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Analytics is Pro; the log shows real data only when it is unlocked.
        $this->app->instance(LicenseGateway::class, new LicensedGateway);
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function visit(array $a = []): Analytics
    {
        // created_at is not fillable on Analytics (timestamps are off), so set it afterwards.
        $when = $a['created_at'] ?? now()->subHours(2);
        unset($a['created_at']);
        $row = Analytics::create(array_merge([
            'ip_address' => '203.0.113.5', 'url' => 'https://site.test/', 'referrer' => null,
            'user_agent' => 'Mozilla/5.0', 'browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'desktop',
            'country' => 'Bangladesh', 'country_code' => 'BD', 'city' => 'Dhaka',
        ], $a));
        Analytics::where('id', $row->id)->update(['created_at' => $when]);

        return $row->fresh();
    }

    public function test_the_log_lists_visits_with_ip_and_country(): void
    {
        $this->visit(['ip_address' => '198.51.100.9', 'country' => 'France', 'country_code' => 'FR', 'city' => 'Paris', 'url' => 'https://site.test/pricing']);

        $this->actingAs($this->admin())->get('/admin/analytics/visitors?range=7')
            ->assertOk()
            ->assertSee('Visitor Log', false)
            ->assertSee('198.51.100.9', false)
            ->assertSee('France', false)
            ->assertSee('Paris', false)
            ->assertSee('/pricing', false);
    }

    public function test_it_filters_by_country_and_search(): void
    {
        $this->visit(['ip_address' => '203.0.113.1', 'country' => 'Bangladesh', 'country_code' => 'BD']);
        $this->visit(['ip_address' => '198.51.100.2', 'country' => 'France', 'country_code' => 'FR']);

        $byCountry = $this->actingAs($this->admin())->get('/admin/analytics/visitors?range=30&country=FR');
        $byCountry->assertOk()->assertSee('198.51.100.2', false)->assertDontSee('203.0.113.1', false);

        $bySearch = $this->actingAs($this->admin())->get('/admin/analytics/visitors?range=30&q=203.0.113.1');
        $bySearch->assertOk()->assertSee('203.0.113.1', false)->assertDontSee('198.51.100.2', false);
    }

    public function test_only_visits_in_the_window_are_shown(): void
    {
        $this->visit(['ip_address' => '11.11.11.11', 'created_at' => now()->subHours(3)]);     // today
        $this->visit(['ip_address' => '22.22.22.22', 'created_at' => now()->subDays(40)]);     // older

        $this->actingAs($this->admin())->get('/admin/analytics/visitors?range=1')
            ->assertOk()->assertSee('11.11.11.11', false)->assertDontSee('22.22.22.22', false);
    }

    public function test_csv_export_returns_the_filtered_rows(): void
    {
        $this->visit(['ip_address' => '203.0.113.7', 'country' => 'Bangladesh', 'country_code' => 'BD', 'url' => 'https://site.test/contact']);

        $res = $this->actingAs($this->admin())->get('/admin/analytics/visitors?range=7&export=csv');
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('Date/Time', $body);
        $this->assertStringContainsString('IP Address', $body);
        $this->assertStringContainsString('203.0.113.7', $body);
        $this->assertStringContainsString('/contact', $body);
    }

    public function test_an_unlicensed_site_is_sent_back_to_analytics(): void
    {
        $this->app->instance(LicenseGateway::class, new class extends LicensedGateway
        {
            public function active(?string $f = null): bool
            {
                return false;
            }

            public function licensed(): bool
            {
                return false;
            }
        });
        DB::table('cms_settings')->updateOrInsert(['key' => 'falcon_freemium_grace_until'], ['value' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin())->get('/admin/analytics/visitors?range=7')
            ->assertRedirect(route('admin.analytics'));
    }

    public function test_it_is_closed_without_the_analytics_permission(): void
    {
        $sub = User::forceCreate([
            'name' => 'Sub', 'email' => 'sub@example.test', 'password' => 'secret',
            'role_id' => (int) DB::table('roles')->where('slug', 'subscriber')->value('id'),
        ]);
        $this->actingAs($sub)->get('/admin/analytics/visitors')->assertForbidden();
    }
}
