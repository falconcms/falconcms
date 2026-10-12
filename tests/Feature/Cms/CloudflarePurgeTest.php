<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Support\Cloudflare;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Settings → Integrations → Cloudflare cache: wherever the CMS clears its own page cache,
 * Cloudflare's is purged too, once per request and after the response.
 */
class CloudflarePurgeTest extends TestCase
{
    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'cf@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function configure(bool $enabled = true): void
    {
        $this->setCmsOptions([Cloudflare::ENABLED => $enabled ? '1' : '0', Cloudflare::ZONE => 'zone123', Cloudflare::TOKEN => Crypt::encryptString('tok')]);
    }

    public function test_clearing_the_page_cache_purges_cloudflare_once_after_the_request(): void
    {
        Http::swap(new Factory);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true, 'errors' => []])]);
        $this->configure();

        clear_page_cache();
        clear_page_cache();
        Http::assertNothingSent();

        $this->app->terminate();

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/purge_cache'
            && $r['purge_everything'] === true && $r->hasHeader('Authorization', 'Bearer tok'));
        $this->assertNull(Cloudflare::last()['error']);
    }

    public function test_nothing_is_sent_while_purging_is_off(): void
    {
        Http::swap(new Factory);
        Http::fake();
        $this->configure(false);

        clear_page_cache();
        $this->app->terminate();

        Http::assertNothingSent();
    }

    public function test_a_refused_token_is_explained(): void
    {
        Http::swap(new Factory);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['code' => 10000, 'message' => 'Authentication error']]], 403)]);
        $this->configure();

        $error = Cloudflare::purgeEverything();

        $this->assertStringContainsString('Cache Purge', $error);
        $this->assertSame($error, Cloudflare::last()['error']);
    }

    public function test_the_settings_store_the_token_encrypted_and_the_purge_button_works(): void
    {
        Http::swap(new Factory);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.integrations.update'), [
            'cloudflare_purge_enabled' => '1', 'cloudflare_zone_id' => ' zone9 ', 'cloudflare_api_token' => 'secret',
        ])->assertRedirect();
        $this->assertNotSame('secret', DB::table('cms_settings')->where('key', Cloudflare::TOKEN)->value('value'));
        $this->assertSame('secret', Cloudflare::token());
        $this->assertSame('zone9', Cloudflare::zone());
        $this->assertTrue(Cloudflare::enabled());

        // An empty token field keeps the saved one.
        $this->actingAs($admin)->post(route('admin.settings.integrations.update'), ['cloudflare_zone_id' => 'zone9'])->assertRedirect();
        $this->assertSame('secret', Cloudflare::token());

        $this->actingAs($admin)->get(route('admin.settings.integrations'))->assertOk()->assertSee('Purge everything now');
        $this->actingAs($admin)->post(route('admin.settings.integrations.cloudflare-purge'))
            ->assertRedirect()->assertSessionHas('success', 'Cloudflare cache purged.');
    }
}
