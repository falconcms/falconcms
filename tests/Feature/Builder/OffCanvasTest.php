<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use Carbon\Carbon;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\OffCanvas;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Off-canvas panels from the Builder Library.
 *
 * A panel is a builder layout plus panel settings, kept in the `falcon_off_canvases` option.
 * The part worth guarding is the front end: a page carries only the panels it can open —
 * the ones something on it links to, plus the ones that open by themselves — so a site with
 * a dozen panels does not ship a dozen hidden drawers on every page.
 */
class OffCanvasTest extends TestCase
{
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        // The Library's write routes are Pro (builder_pro). Whether that gate is right is
        // tested with the gate itself; these tests are about what the panels do.
        $this->withProLicensed();
    }

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function layout(string $title): array
    {
        return [[
            'columns' => [['elements' => [['type' => 'heading', 'settings' => ['title' => $title, 'tag' => 'h3']]]]],
        ]];
    }

    /** Store a panel directly, the way the Library would have. */
    private function panel(string $name, array $settings = [], string $title = 'Inside the panel', bool $enabled = true): array
    {
        $panel = OffCanvas::make($name, ['layout' => $this->layout($title), 'settings' => $settings]);
        $panel['enabled'] = $enabled;
        $items = OffCanvas::all();
        $items[] = $panel;
        OffCanvas::store($items);
        forget_cms_options_cache();

        return $panel;
    }

    private function page(string $content): Post
    {
        return Post::create([
            'user_id' => 1, 'title' => 'Landing', 'slug' => 'landing', 'type' => 'page',
            'status' => 'published', 'lang_code' => 'en', 'content' => $content,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_the_library_creates_a_panel_and_hands_back_its_builder(): void
    {
        $res = $this->actingAs($this->administrator())
            ->postJson('/admin/falcon-builder-library/off-canvas', ['name' => 'Mobile Menu', 'position' => 'left'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $panel = OffCanvas::find($res->json('offcanvas.id'));
        $this->assertSame('mobile-menu', $panel['slug']);
        $this->assertSame('left', $panel['config']['settings']['position']);
        $this->assertStringEndsWith('/off-canvas/'.$panel['id'].'/builder', $res->json('builder_url'));

        $this->actingAs($this->administrator())->get($res->json('builder_url'))
            ->assertOk()
            ->assertSee('Panel Options', false)
            ->assertSee('#offcanvas-mobile-menu', false);
    }

    public function test_the_library_page_lists_panels_with_their_trigger(): void
    {
        $this->panel('Cart Drawer');

        $this->actingAs($this->administrator())->get('/admin/falcon-builder-library?tab=off_canvas')
            ->assertOk()
            ->assertSee('Cart Drawer', false)
            ->assertSee('#offcanvas-cart-drawer', false);
    }

    public function test_slugs_stay_unique_and_survive_a_rename(): void
    {
        $a = $this->panel('Promo');
        $b = $this->panel('Promo');
        $this->assertSame('promo', $a['slug']);
        $this->assertSame('promo-2', $b['slug']);

        $this->actingAs($this->administrator())
            ->patchJson('/admin/falcon-builder-library/off-canvas/'.$a['id'], ['name' => 'Summer Promo'])
            ->assertOk();

        $this->assertSame('promo', OffCanvas::find($a['id'])['slug'], 'renaming must not break existing links');
    }

    public function test_settings_are_clamped_and_unknown_keys_dropped(): void
    {
        $panel = $this->panel('Popup');

        $this->actingAs($this->administrator())
            ->postJson('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/settings', ['settings' => [
                'position' => 'sideways', 'width' => 99999, 'bg_color' => 'red;}</style><script>', 'overlay' => true,
                'evil' => 'x',
            ]])
            ->assertOk();

        $s = OffCanvas::find($panel['id'])['config']['settings'];
        $this->assertSame('right', $s['position']);
        $this->assertSame(3000, $s['width']);
        $this->assertSame('#ffffff', $s['bg_color']);
        $this->assertSame('1', $s['overlay']);
        $this->assertArrayNotHasKey('evil', $s);
    }

    public function test_a_page_that_links_to_a_panel_renders_it(): void
    {
        $this->panel('Mobile Menu', [], 'Hello from the drawer');
        $this->page('<p><a href="#offcanvas-mobile-menu">Menu</a></p>');

        $this->get('/landing')->assertOk()
            ->assertSee('id="falcon-oc-mobile-menu"', false)
            ->assertSee('Hello from the drawer', false)
            ->assertSee('window.FalconOffCanvas', false);
    }

    public function test_a_page_that_does_not_link_to_a_panel_carries_none_of_it(): void
    {
        $this->panel('Mobile Menu', [], 'Hello from the drawer');
        $this->page('<p>No drawers here.</p>');

        $this->get('/landing')->assertOk()
            ->assertDontSee('falcon-oc-mobile-menu', false)
            ->assertDontSee('window.FalconOffCanvas', false);
    }

    public function test_a_disabled_panel_never_renders(): void
    {
        $this->panel('Mobile Menu', [], 'Hello from the drawer', false);
        $this->page('<p><a href="#offcanvas-mobile-menu">Menu</a></p>');

        $this->get('/landing')->assertOk()->assertDontSee('falcon-oc-mobile-menu', false);
    }

    public function test_an_auto_opening_panel_renders_where_its_page_rule_matches(): void
    {
        $this->panel('Newsletter', ['position' => 'center', 'trigger' => 'load', 'auto_pages' => 'include', 'auto_paths' => "/landing\n/blog/*"]);
        $this->page('<p>Nothing links here.</p>');

        $this->get('/landing')->assertOk()
            ->assertSee('id="falcon-oc-newsletter"', false)
            ->assertSee('&quot;trigger&quot;:&quot;load&quot;', false);

        $this->assertFalse(OffCanvas::pageMatches(OffCanvas::settings(['auto_pages' => 'home'])), 'not the home page');
    }

    public function test_a_specific_page_target_opens_only_there(): void
    {
        $page = $this->page('<p>Nothing links here.</p>');
        $this->panel('Promo', ['trigger' => 'load', 'auto_pages' => 'include', 'auto_targets' => ['post:'.$page->id]]);

        $this->get('/landing')->assertOk()->assertSee('id="falcon-oc-promo"', false);

        Post::create([
            'user_id' => 1, 'title' => 'Other', 'slug' => 'other', 'type' => 'page', 'status' => 'published',
            'lang_code' => 'en', 'content' => '<p>x</p>', 'published_at' => now()->subDay(),
        ]);
        $this->get('/other')->assertOk()->assertDontSee('id="falcon-oc-promo"', false);
    }

    public function test_an_excluded_post_type_keeps_the_panel_away(): void
    {
        $this->page('<p>Nothing links here.</p>');
        $this->panel('Promo', ['trigger' => 'load', 'auto_pages' => 'exclude', 'auto_targets' => ['all:page']]);

        $this->get('/landing')->assertOk()->assertDontSee('id="falcon-oc-promo"', false);
    }

    public function test_old_home_rule_and_path_list_become_targets(): void
    {
        $s = OffCanvas::settings(['auto_pages' => 'home']);
        $this->assertSame('include', $s['auto_pages']);
        $this->assertSame(['home'], $s['auto_targets']);

        $s = OffCanvas::settings(['auto_pages' => 'include', 'auto_paths' => "/blog/*\nshop"]);
        $this->assertSame(['path:/blog/*', 'path:/shop'], $s['auto_targets']);

        $s = OffCanvas::settings(['auto_targets' => ['post:3', 'term:category:2', 'path:/x/*', '<script>', 'nope:1']]);
        $this->assertSame(['post:3', 'term:category:2', 'path:/x/*'], $s['auto_targets']);
    }

    public function test_the_target_picker_searches_pages_and_terms(): void
    {
        $page = $this->page('<p>x</p>');
        Category::create(['name' => 'Landing News', 'slug' => 'landing-news', 'lang_code' => 'en']);

        $items = $this->actingAs($this->administrator())
            ->getJson('/admin/falcon-builder-library/off-canvas-targets?q=Landing')
            ->assertOk()->json('items');

        $values = array_column($items, 'value');
        $this->assertContains('post:'.$page->id, $values);
        $this->assertTrue(collect($values)->contains(fn ($v) => str_starts_with($v, 'term:category:')));
    }

    public function test_the_builder_labels_saved_targets(): void
    {
        $page = $this->page('<p>x</p>');
        $panel = $this->panel('Promo', ['trigger' => 'load', 'auto_pages' => 'include', 'auto_targets' => ['post:'.$page->id, 'path:/blog/*']]);

        $this->actingAs($this->administrator())->get('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/builder')
            ->assertOk()
            ->assertSee('"text":"Landing"', false)
            ->assertSee('"text":"URL: \/blog\/*"', false)
            ->assertSee('Front Page', false);
    }

    public function test_the_picker_lists_specific_entries_without_searching(): void
    {
        $page = $this->page('<p>x</p>');
        $panel = $this->panel('Promo');

        $this->actingAs($this->administrator())->get('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/builder')
            ->assertOk()
            ->assertSee('{"value":"post:'.$page->id.'","text":"Landing","optgroup":"Pages"}', false);
    }

    public function test_device_overrides_are_kept_checked_and_cascade(): void
    {
        $s = OffCanvas::settings([
            'position' => 'right', 'width' => 400, 'width_unit' => 'px',
            'width_tablet' => 320,
            'position_mobile' => 'bottom', 'width_unit_mobile' => '%', 'width_mobile' => 250,
            'height_mobile' => '', 'radius_tablet' => null, 'position_tablet' => 'sideways',
        ]);

        $this->assertSame(320, $s['width_tablet']);
        $this->assertSame(100, $s['width_mobile'], 'clamped as a percentage, the unit mobile uses');
        $this->assertSame('bottom', $s['position_mobile']);
        $this->assertArrayNotHasKey('position_tablet', $s, 'an unknown position is no override — tablet inherits');
        $this->assertArrayNotHasKey('height_mobile', $s, 'empty means inherit, nothing stored');
        $this->assertArrayNotHasKey('radius_tablet', $s);

        $mobile = OffCanvas::forDevice($s, 'mobile');
        $this->assertSame('bottom', $mobile['position']);
        $this->assertSame('%', $mobile['width_unit']);
        $this->assertSame(320, OffCanvas::forDevice(['width' => 400, 'width_tablet' => 320] + $s, 'tablet')['width']);
    }

    public function test_the_stylesheet_switches_geometry_per_device(): void
    {
        $s = OffCanvas::settings(['position' => 'right', 'width' => 400, 'position_mobile' => 'bottom', 'height_mobile' => 60, 'height_unit_mobile' => 'vh']);
        $css = OffCanvas::panelCss('#p', $s, 800, 1100);

        $this->assertStringContainsString('#p .falcon-oc__panel{top:0;right:0;bottom:0;left:auto;width:min(400px, 100vw)', $css);
        $this->assertStringNotContainsString('@media (max-width:1100px)', $css, 'tablet is the same as desktop — no rule');
        $this->assertStringContainsString('@media (max-width:800px){#p .falcon-oc__panel{top:auto;right:0;bottom:0;left:0;width:auto;height:60vh', $css);
        $this->assertStringContainsString('transform:translateY(100%)', $css, 'a bottom sheet slides up from the bottom');

        $flat = OffCanvas::panelCss('#p', OffCanvas::settings([]), 800, 1100);
        $this->assertStringNotContainsString('@media', $flat);
    }

    public function test_a_page_carries_the_device_rules(): void
    {
        $this->panel('Menu', ['position' => 'left', 'position_mobile' => 'bottom']);
        $this->page('<a href="#offcanvas-menu">Menu</a>');

        $this->get('/landing')->assertOk()
            ->assertSee('#falcon-oc-menu .falcon-oc__panel{', false)
            ->assertSee('@media (max-width:', false);
    }

    public function test_a_panel_linked_only_from_another_panel_is_rendered_too(): void
    {
        $inner = $this->panel('Inner');
        $outer = OffCanvas::make('Outer', ['layout' => [[
            'columns' => [['elements' => [['type' => 'button', 'settings' => ['text' => 'More', 'linkUrl' => '#offcanvas-inner']]]]],
        ]]]);
        OffCanvas::store(array_merge(OffCanvas::all(), [$outer]));
        forget_cms_options_cache();

        $needed = OffCanvas::needed('<a href="/landing/#offcanvas-outer">Open</a>');
        $this->assertEqualsCanonicalizing([$inner['id'], $outer['id']], array_column($needed, 'id'));
    }

    public function test_the_open_off_canvas_link_source_resolves_to_the_trigger(): void
    {
        $panel = $this->panel('Cart Drawer');

        $this->assertSame('#offcanvas-cart-drawer', falcon_resolve_dynamic_value('offcanvas_open', null,
            falcon_dynamic_config(['dynamic_link_offcanvas' => $panel['id']], 'link')));
        $this->assertSame('#offcanvas-close', falcon_resolve_dynamic_value('offcanvas_close'));
    }

    public function test_an_icon_box_can_open_a_panel(): void
    {
        $panel = $this->panel('Cart Drawer');

        $html = view('falcon-cms::frontend.builder.elements.icon-box', ['el' => ['settings' => [
            'title' => 'Cart', 'linkUrl' => '', 'link_dynamic_source' => 'offcanvas_open', 'dynamic_link_offcanvas' => $panel['id'],
        ]]])->render();

        $this->assertStringContainsString('href="#offcanvas-cart-drawer"', $html);
    }

    public function test_the_builder_offers_off_canvas_in_the_text_menu(): void
    {
        $script = file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');

        $this->assertStringContainsString("'Open Off-Canvas on Click'", $script);
        $this->assertStringContainsString('s.link_dynamic_source = key;', $script, 'an action writes the link, not the text');
    }

    public function test_export_and_import_round_trip(): void
    {
        $panel = $this->panel('Cart Drawer', ['position' => 'left', 'width' => 320]);

        $json = $this->actingAs($this->administrator())
            ->get('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/export')
            ->assertOk()->getContent();
        $this->assertSame('falcon_off_canvas', json_decode($json, true)['_type']);

        $file = UploadedFile::fake()->createWithContent('cart.json', $json);
        $this->actingAs($this->administrator())
            ->post('/admin/falcon-builder-library/off-canvas-import', ['library_file' => $file])
            ->assertRedirect();

        $imported = collect(OffCanvas::all())->firstWhere('slug', 'cart-drawer-2');
        $this->assertNotNull($imported, 'an import gets its own slug');
        $this->assertSame(320, $imported['config']['settings']['width']);
    }

    // ── Schedule: on and off by date and time ───────────────────────────────

    /** The site runs on Dhaka time (UTC+6), so a wrong timezone shows up as a six-hour error. */
    private function dhaka(): void
    {
        update_cms_option('timezone', 'Asia/Dhaka');
        forget_cms_options_cache();
    }

    public function test_schedule_times_are_kept_to_the_minute_and_junk_dropped(): void
    {
        $s = OffCanvas::settings(['schedule_start' => '2026-11-01T00:00:45', 'schedule_end' => '2026-02-30T10:00']);
        $this->assertSame('2026-11-01T00:00', $s['schedule_start']);
        $this->assertSame('', $s['schedule_end'], '30 February is no date');

        $s = OffCanvas::settings(['schedule_end' => '"><script>', 'schedule_start' => '2026-11-01 25:00']);
        $this->assertSame(['', ''], [$s['schedule_start'], $s['schedule_end']]);
        $this->assertSame(['', ''], [OffCanvas::settings([])['schedule_start'], OffCanvas::settings([])['schedule_end']]);
    }

    public function test_the_times_mean_the_site_timezone(): void
    {
        $this->dhaka();
        // 23:59 on 30 November in Dhaka is 17:59 UTC.
        $this->assertSame(
            Carbon::parse('2026-11-30 17:59:00', 'UTC')->getTimestamp(),
            OffCanvas::scheduleTimestamp('2026-11-30T23:59')
        );
    }

    public function test_the_state_follows_the_clock(): void
    {
        $this->dhaka();
        $s = OffCanvas::settings(['schedule_start' => '2026-11-01T00:00', 'schedule_end' => '2026-11-30T23:59']);
        $at = fn (string $utc) => Carbon::parse($utc, 'UTC')->getTimestamp();

        $this->assertSame('scheduled', OffCanvas::scheduleState($s, $at('2026-10-31 17:59:59')));
        $this->assertSame('active', OffCanvas::scheduleState($s, $at('2026-10-31 18:00:00')), 'on from the first second');
        $this->assertSame('active', OffCanvas::scheduleState($s, $at('2026-11-30 17:58:59')));
        $this->assertSame('ended', OffCanvas::scheduleState($s, $at('2026-11-30 17:59:00')), 'off on the minute');

        $this->assertSame('always', OffCanvas::scheduleState(OffCanvas::settings([])));
        $this->assertSame('active', OffCanvas::scheduleState(OffCanvas::settings(['schedule_end' => '2099-01-01T00:00'])));
        $this->assertSame('invalid', OffCanvas::scheduleState(OffCanvas::settings(['schedule_start' => '2026-12-01T00:00', 'schedule_end' => '2026-11-30T23:59'])));
    }

    public function test_a_panel_past_its_end_is_off_everywhere(): void
    {
        $this->dhaka();
        $this->panel('Promo', ['position' => 'center', 'trigger' => 'load', 'schedule_end' => '2026-11-30T23:59'], 'Thirty percent off');
        $this->page('<p><a href="#offcanvas-promo">Open</a></p>');

        $this->travelTo(Carbon::parse('2026-11-30 17:58:00', 'UTC'));
        $html = $this->get('/landing')->assertOk()->assertSee('Thirty percent off', false)->getContent();
        // The end travels with the panel, so a cached copy of the page is still held shut.
        preg_match('/id="falcon-oc-promo"[^>]*data-oc="([^"]+)"/', $html, $m);
        $conf = json_decode(html_entity_decode($m[1] ?? ''), true);
        $this->assertSame(Carbon::parse('2026-11-30 17:59:00', 'UTC')->getTimestamp() * 1000, $conf['until'] ?? null);
        $this->assertNull($conf['from']);

        $this->travelTo(Carbon::parse('2026-11-30 18:05:00', 'UTC'));
        $this->get('/landing')->assertOk()
            ->assertDontSee('falcon-oc-promo', false)
            ->assertDontSee('Thirty percent off', false);
    }

    public function test_a_panel_not_started_yet_is_carried_but_held_shut_until_its_start(): void
    {
        $this->dhaka();
        $this->panel('Promo', ['position' => 'center', 'trigger' => 'load', 'schedule_start' => '2026-12-01T09:00']);
        $this->page('<p>Nothing links here.</p>');

        $this->travelTo(Carbon::parse('2026-11-20 00:00:00', 'UTC'));
        $html = $this->get('/landing')->assertOk()->assertSee('id="falcon-oc-promo"', false)->getContent();
        preg_match('/id="falcon-oc-promo"[^>]*data-oc="([^"]+)"/', $html, $m);
        $conf = json_decode(html_entity_decode($m[1] ?? ''), true);
        $this->assertSame(Carbon::parse('2026-12-01 03:00:00', 'UTC')->getTimestamp() * 1000, $conf['from'] ?? null);
        // The script refuses to open a panel outside its window, whatever asks.
        $this->assertStringContainsString('!live(conf(p))', $html);
    }

    public function test_a_window_that_can_never_open_renders_nothing(): void
    {
        $this->panel('Promo', ['schedule_start' => '2026-12-01T00:00', 'schedule_end' => '2026-11-30T00:00']);
        $this->page('<p><a href="#offcanvas-promo">Open</a></p>');

        $this->get('/landing')->assertOk()->assertDontSee('falcon-oc-promo', false);
    }

    public function test_crossing_a_start_or_end_clears_the_page_cache_once(): void
    {
        $this->dhaka();
        $this->travelTo(Carbon::parse('2026-11-10 00:00:00', 'UTC'));
        $this->panel('Promo', ['schedule_start' => '2026-11-15T00:00', 'schedule_end' => '2026-11-30T23:59']);
        $start = Carbon::parse('2026-11-14 18:00:00', 'UTC')->getTimestamp();
        $end = Carbon::parse('2026-11-30 17:59:00', 'UTC')->getTimestamp();
        $this->assertSame((string) $start, (string) get_cms_option(OffCanvas::NEXT_CHANGE_OPTION));

        $this->assertFalse(OffCanvas::maintainSchedule($start - 1), 'nothing to do before the start');

        Cache::put('falcon-page-cache-probe', 1, 600);
        $this->assertTrue(OffCanvas::maintainSchedule($start + 30));
        $this->assertFalse(Cache::has('falcon-page-cache-probe'), 'the page cache was cleared');
        forget_cms_options_cache();
        $this->assertSame((string) $end, (string) get_cms_option(OffCanvas::NEXT_CHANGE_OPTION), 'the end is next');
        $this->assertFalse(OffCanvas::maintainSchedule($start + 60), 'once per change');

        $this->assertTrue(OffCanvas::maintainSchedule($end));
        forget_cms_options_cache();
        $this->assertSame('', (string) get_cms_option(OffCanvas::NEXT_CHANGE_OPTION), 'nothing left to wait for');
    }

    public function test_the_builder_saves_the_schedule_and_the_library_shows_it(): void
    {
        $this->dhaka();
        $panel = $this->panel('Promo');
        $this->travelTo(Carbon::parse('2026-11-10 00:00:00', 'UTC'));

        $this->actingAs($this->administrator())
            ->postJson('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/settings', ['settings' => ['schedule_end' => '2026-11-30T23:59']])
            ->assertOk()->assertJsonPath('settings.schedule_end', '2026-11-30T23:59');
        forget_cms_options_cache();

        $this->actingAs($this->administrator())->get('/admin/falcon-builder-library?tab=off_canvas')
            ->assertOk()->assertSee('Turns off 30 Nov 2026, 23:59', false);

        $this->actingAs($this->administrator())->get('/admin/falcon-builder-library/off-canvas/'.$panel['id'].'/builder')
            ->assertOk()->assertSee('timezone: "Asia\/Dhaka"', false)->assertSee('Turn Off At', false);
    }
}
