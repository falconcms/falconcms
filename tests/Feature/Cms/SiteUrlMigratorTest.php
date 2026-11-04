<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Support\SiteUrlMigrator;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * A database restored from another site links to this one: falconcms.com's data restored on a
 * local copy kept its menus pointing at falconcms.com.
 */
class SiteUrlMigratorTest extends TestCase
{
    private function menuItem(string $url, string $type = 'custom'): int
    {
        $menu = DB::table('navigation_menus')->insertGetId(['name' => 'M'.uniqid(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('navigation_menu_items')->insertGetId(['navigation_menu_id' => $menu, 'title' => 'x', 'url' => $url, 'type' => $type,
            'order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function url(int $id): string
    {
        return (string) DB::table('navigation_menu_items')->where('id', $id)->value('url');
    }

    public function test_the_address_is_read_from_a_backup_and_normalised(): void
    {
        $this->assertSame('https://falconcms.com', SiteUrlMigrator::fromDump("-- Falcon CMS Backup\n-- Database: x\n-- Site URL: https://FalconCMS.com/\n-- Date: now"));
        $this->assertNull(SiteUrlMigrator::fromDump("-- Falcon CMS Backup\n-- Date: now"));
        $this->assertSame('http://localhost:8000', SiteUrlMigrator::normalize('http://localhost:8000/some/path'));
        $this->assertNull(SiteUrlMigrator::normalize('not a url'));
    }

    public function test_links_to_the_old_site_move_and_nothing_else_does(): void
    {
        $page = $this->menuItem('https://falconcms.com/pricing');
        $home = $this->menuItem('https://falconcms.com');
        $www = $this->menuItem('http://www.falconcms.com/blog?x=1#top');
        $other = $this->menuItem('https://falconcms.com.au/pricing');
        $mail = $this->menuItem('mailto:info@falconcms.com');
        $ext = $this->menuItem('https://github.com/falconcms/falconcms');

        $post = DB::table('posts')->insertGetId(['title' => 'P', 'slug' => 'p-'.uniqid(), 'type' => 'page', 'status' => 'published', 'lang_code' => 'en',
            'content' => json_encode([['settings' => ['linkUrl' => 'https://falconcms.com/demo', 'text' => 'Visit falconcms.com today', 'img' => 'https://falconcms.com/storage/media/a.webp']]]),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('cms_settings')->insert([
            ['key' => 'mig_plain', 'value' => 'https://falconcms.com/storage/logo.png'],
            ['key' => 'mig_serialized', 'value' => serialize(['logo' => 'https://falconcms.com/storage/x.png', 'n' => 3])],
        ]);
        DB::table('cms_analytics')->insert(['ip_address' => '1.1.1.1', 'url' => 'https://falconcms.com/pricing', 'created_at' => now()]);

        $stats = SiteUrlMigrator::migrate('https://falconcms.com', 'http://falconcms.test');

        $this->assertSame('http://falconcms.test/pricing', $this->url($page));
        $this->assertSame('http://falconcms.test', $this->url($home));
        $this->assertSame('http://falconcms.test/blog?x=1#top', $this->url($www));
        $this->assertSame('https://falconcms.com.au/pricing', $this->url($other), 'another domain that starts the same');
        $this->assertSame('mailto:info@falconcms.com', $this->url($mail));
        $this->assertSame('https://github.com/falconcms/falconcms', $this->url($ext));

        $content = (string) DB::table('posts')->where('id', $post)->value('content');
        $this->assertStringContainsString('http:\/\/falconcms.test\/demo', $content, 'JSON-escaped links move too');
        $this->assertStringContainsString('http:\/\/falconcms.test\/storage\/media\/a.webp', $content);
        $this->assertStringContainsString('Visit falconcms.com today', $content, 'text without a scheme is left alone');
        $this->assertNotNull(json_decode($content, true));

        $this->assertSame('http://falconcms.test/storage/logo.png', DB::table('cms_settings')->where('key', 'mig_plain')->value('value'));
        $ser = unserialize(DB::table('cms_settings')->where('key', 'mig_serialized')->value('value'));
        $this->assertSame(['logo' => 'http://falconcms.test/storage/x.png', 'n' => 3], $ser, 'serialized lengths stay valid');

        $this->assertSame('https://falconcms.com/pricing', DB::table('cms_analytics')->value('url'), 'visit history is not rewritten');
        // 3 menu links, the post's content and 2 settings
        $this->assertSame(6, $stats['values']);
    }

    /** The old site's history (visits, logs, Search Console figures, reports) is not rewritten. */
    public function test_history_tables_are_left_alone(): void
    {
        foreach (['gsc_page_daily', 'gsc_inspections', 'cms_not_found_log', 'security_csp_reports', 'hello_falcon_logs', 'news_post_view_days', 'cms_seo_page_scores', 'cms_analytics', 'sessions'] as $t) {
            $this->assertTrue(SiteUrlMigrator::skips($t), $t);
        }
        foreach (['posts', 'navigation_menu_items', 'cms_settings', 'cms_revisions', 'widgets', 'media', 'shop_products'] as $t) {
            $this->assertFalse(SiteUrlMigrator::skips($t), $t);
        }
    }

    public function test_restoring_a_site_onto_itself_changes_nothing(): void
    {
        $id = $this->menuItem('https://falconcms.com/pricing');
        $this->assertSame(0, SiteUrlMigrator::migrate('https://falconcms.com', 'https://www.falconcms.com')['values']);
        $this->assertSame('https://falconcms.com/pricing', $this->url($id));
    }

    public function test_an_old_backup_without_the_address_is_read_from_the_data(): void
    {
        $this->menuItem('https://falconcms.com/about', 'page');
        $this->menuItem('https://falconcms.com/pricing', 'page');
        $this->menuItem('https://github.com/x', 'custom'); // a custom link says nothing about the site
        $this->assertSame('https://falconcms.com', SiteUrlMigrator::guess());
    }

    public function test_the_command_moves_links_to_app_url(): void
    {
        config(['app.url' => 'http://falconcms.test']);
        $id = $this->menuItem('https://falconcms.com/pricing', 'page');
        $this->artisan('falcon:replace-url', ['--force' => true])->assertSuccessful();
        $this->assertSame('http://falconcms.test/pricing', $this->url($id));

        $this->artisan('falcon:replace-url', ['from' => 'http://falconcms.test', 'to' => 'http://falconcms.test', '--force' => true])
            ->expectsOutputToContain('Nothing to do')->assertSuccessful();
    }

    /** --relative: the old address goes and the path stays, so links work under any address. */
    public function test_relative_keeps_only_the_path(): void
    {
        $page = $this->menuItem('http://falconcms.test/about');
        $root = $this->menuItem('http://falconcms.test');
        $query = $this->menuItem('http://falconcms.test/?s=x');
        $post = DB::table('posts')->insertGetId(['title' => 'P', 'slug' => 'p-'.uniqid(), 'type' => 'page', 'status' => 'published', 'lang_code' => 'en',
            'content' => json_encode([['linkUrl' => 'http://falconcms.test/laravel-cms/', 'home' => 'http://falconcms.test']]).' <a href="http://falconcms.test">Home</a>',
            'created_at' => now(), 'updated_at' => now()]);

        SiteUrlMigrator::migrate('http://falconcms.test', null);

        $this->assertSame('/about', $this->url($page));
        $this->assertSame('/', $this->url($root));
        $this->assertSame('/?s=x', $this->url($query));
        $content = (string) DB::table('posts')->where('id', $post)->value('content');
        $this->assertStringContainsString('"linkUrl":"\/laravel-cms\/"', $content);
        $this->assertStringContainsString('"home":"\/"', $content);
        $this->assertStringContainsString('<a href="/">Home</a>', $content);
        $this->assertSame('/laravel-cms/', json_decode(substr($content, 0, strpos($content, ' <a')), true)[0]['linkUrl']);
    }

    public function test_a_link_to_this_site_becomes_a_path(): void
    {
        config(['app.url' => 'http://falconcms.test']);
        $this->assertSame('/about', falcon_local_url('http://localhost/about'), 'the request host');
        $this->assertSame('/about?x=1', falcon_local_url('http://falconcms.test/about?x=1'), 'APP_URL');
        $this->assertSame('/', falcon_local_url('http://falconcms.test'));
        $this->assertSame('https://github.com/x', falcon_local_url('https://github.com/x'));
        $this->assertSame('/already', falcon_local_url('/already'));
        $this->assertSame('#plans', falcon_local_url('#plans'));
    }

    /** A Menu element's page item links to the page as it is now, not to the address saved with it. */
    public function test_a_menu_items_page_link_is_worked_out_from_the_page(): void
    {
        $page = DB::table('posts')->insertGetId(['title' => 'About', 'slug' => 'about-us', 'type' => 'page', 'status' => 'published', 'lang_code' => 'en',
            'content' => '', 'created_at' => now(), 'updated_at' => now()]);
        $item = (object) ['id' => 1, 'type' => 'page', 'object_id' => $page, 'url' => 'https://old-site.example/about-old', 'title' => 'About'];
        $custom = (object) ['id' => 2, 'type' => 'custom', 'object_id' => null, 'url' => 'https://github.com/x', 'title' => 'GitHub'];
        $missing = (object) ['id' => 3, 'type' => 'page', 'object_id' => 999999, 'url' => '/kept', 'title' => 'Gone'];

        $out = falcon_menu_item_urls([$item, $custom, $missing])->keyBy('id');
        $this->assertSame('/about-us', $out[1]->url);
        $this->assertSame('https://github.com/x', $out[2]->url);
        $this->assertSame('/kept', $out[3]->url, 'a stored URL is better than a guess');
        $this->assertSame('https://old-site.example/about-old', $item->url, 'the row passed in is not changed');
    }
}
