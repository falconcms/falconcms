<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Where a visit came from (Analytics → Traffic Sources and the Visitor Log's Referrer column).
 */
class ReferrerSourceTest extends TestCase
{
    public function test_named_sources_are_matched_on_whole_name_parts(): void
    {
        $source = fn (?string $ref) => falcon_referrer_source($ref, 'mysite.test');

        $this->assertSame(['Google', 'google.com'], $source('https://www.google.com.bd/'));
        $this->assertSame(['Google', 'google.com'], $source('https://news.google.com/x'));
        $this->assertSame(['Google', 'google.com'], $source('android-app://com.google.android.googlequicksearchbox'));
        $this->assertSame(['Facebook', 'facebook.com'], $source('https://l.facebook.com/l.php?u=x'));
        $this->assertSame(['X (Twitter)', 'x.com'], $source('https://t.co/abc'));
        $this->assertSame(['X (Twitter)', 'x.com'], $source('https://x.com/someone'));
        $this->assertSame(['WhatsApp', 'whatsapp.com'], $source('https://wa.me/123'));

        // Substrings of other names are other sites.
        $this->assertSame(['fast.company.com', 'fast.company.com'], $source('https://fast.company.com/story'));
        $this->assertSame(['fox.com', 'fox.com'], $source('https://www.fox.com/'));
        $this->assertSame(['mygoogleblog.com', 'mygoogleblog.com'], $source('https://mygoogleblog.com/'));

        $this->assertSame(['Direct', null], $source(null));
        $this->assertSame(['Direct', null], $source('https://mysite.test/about'));
    }

    public function test_the_visitor_log_links_only_web_addresses(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'ref-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
        foreach (['https://www.google.com/search?q=x', 'javascript://example.com/%0Aalert(document.cookie)'] as $ref) {
            Analytics::create(['ip_address' => '203.0.113.9', 'url' => 'http://localhost/', 'referrer' => $ref, 'user_agent' => 'Mozilla/5.0', 'browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'desktop']);
        }
        $this->withProLicensed();

        $html = $this->actingAs($admin)->get(route('admin.analytics'))->assertOk()->getContent();

        $this->assertStringContainsString('href="https://www.google.com/search?q=x"', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_a_post_has_no_admin_show_address(): void
    {
        $this->assertFalse(app('router')->has('admin.posts.show'));
        $this->assertFalse(app('router')->has('admin.pages.show'));
    }
}
