<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\MotionVisual\MotionVisuals;
use FalconCms\Core\Support\MotionVisual\Presets;
use FalconCms\Core\Support\MotionVisual\Shapes;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Motion Visual: moving dots, shapes and pictures across a column or container
 * (Column / Container → Extra → Motion Visual).
 *
 * How it moves was checked in a real browser (the dots, every animation, the pointer, the
 * builder's drag / resize / turn handles). These pin down what breaks quietly: settings that
 * survive a shortcode round trip, values that cannot become markup, and the script reaching
 * only the pages that need it.
 */
class MotionVisualTest extends TestCase
{
    protected function tearDown(): void
    {
        Shapes::flush();
        Presets::flush();
        parent::tearDown();
    }

    private function settings(array $items = [], array $more = []): array
    {
        return $more + [
            'mv_preset' => 'custom', 'mv_layer' => 'behind', 'mv_mouse' => false, 'mv_speed' => 2,
            'mv_primary' => '#005ab4', 'mv_accent' => '#f59e0b', 'mv_opacity' => 80,
            'mv_dots' => true, 'mv_dots_count' => 90, 'mv_dots_links' => true, 'mv_dots_mouse' => 'attract',
            'mv_mobile' => 'hide',
            'mv_items' => MotionVisuals::encodeItems($items ?: [
                ['id' => 'a1', 'kind' => 'shape', 'shape' => 'star', 'x' => 20, 'y' => 30, 'size' => 10, 'rot' => 45, 'color' => 'accent', 'anim' => 'orbit', 'speed' => 12, 'dist' => 30, 'blur' => 4],
                ['id' => 'b2', 'kind' => 'image', 'src' => '/storage/media/logo.svg', 'x' => 70, 'y' => 60, 'size' => 15, 'anim' => 'pulse'],
            ]),
        ];
    }

    private function column(array $settings): string
    {
        return view('falcon-cms::frontend.builder.column', [
            'column' => ['id' => 'c1', 'basis' => '100%', 'settings' => $settings, 'elements' => []],
            'container' => ['settings' => [], 'columns' => [['basis' => '100%']]],
        ])->render();
    }

    public function test_a_column_draws_its_dots_and_items(): void
    {
        $html = $this->column($this->settings());

        $this->assertStringContainsString('falcon-mv-host', $html);
        $this->assertStringContainsString('data-falcon-mv="custom"', $html);
        $this->assertStringContainsString('<canvas class="falcon-mv__dots">', $html);
        $this->assertStringContainsString('data-mv-dots-count="90"', $html);
        $this->assertStringContainsString('data-mv-dots-links="1"', $html);
        $this->assertStringContainsString('data-mv-dots-mouse="attract"', $html);
        $this->assertStringContainsString('opacity:0.8', $html);
        // the star: placed, sized, turned, colored with the accent, orbiting at speed 12 / 2
        $this->assertStringContainsString('data-mv-item="a1"', $html);
        $this->assertStringContainsString('left:20%;top:30%;width:10%', $html);
        $this->assertStringContainsString('falcon-mv__anim--orbit', $html);
        $this->assertStringContainsString('--mv-dur:6s', $html);
        $this->assertStringContainsString('rotate:45deg;color:#f59e0b', $html);
        $this->assertStringContainsString('filter:blur(4px)', $html);
        // the picture is an <img>, never inlined
        $this->assertStringContainsString('<img src="/storage/media/logo.svg"', $html);
        $this->assertMatchesRegularExpression('/@media \(max-width:\d+px\)\{#fmv-[0-9a-f]{8}\{display:none!important\}\}/', $html);
    }

    /** Switched off: nothing at all reaches the page, and nothing is forgotten. */
    public function test_switched_off_it_draws_and_loads_nothing_but_keeps_its_settings(): void
    {
        foreach ([false, 'false', '0', 0] as $off) {
            $settings = $this->settings([], ['mv_enabled' => $off]);
            $html = $this->column($settings);

            $this->assertStringNotContainsString('data-falcon-mv', $html, 'off as '.var_export($off, true));
            $this->assertStringNotContainsString('falcon-mv', $html, 'no styles either');
            $this->assertSame('', MotionVisuals::assetsForPage($html), 'and so no script');
            $this->assertSame('', MotionVisuals::render($settings, 'column', true), 'the builder canvas draws nothing too');
        }

        // The switch survives a shortcode round trip, and so does everything it switched off.
        $settings = $this->settings([], ['mv_enabled' => false]);
        $json = (string) json_encode([['id' => 's1', 'type' => 'section', 'settings' => [], 'columns' => [
            ['id' => 'c1', 'basis' => '100%', 'settings' => $settings, 'elements' => []],
        ]]]);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true)[0]['columns'][0]['settings'];
        $this->assertFalse($back['mv_enabled']);
        $this->assertSame($settings['mv_items'], $back['mv_items']);

        // Switched back on, it is all there again.
        $back['mv_enabled'] = true;
        $this->assertStringContainsString('data-mv-item="a1"', $this->column($back));
    }

    public function test_a_visual_saved_before_the_switch_existed_stays_on(): void
    {
        $settings = $this->settings();
        unset($settings['mv_enabled']);

        $this->assertStringContainsString('data-falcon-mv', $this->column($settings));
    }

    /** The builder's picker can add an alpha channel: #rrggbbaa is a color too. */
    public function test_colors_from_the_builder_picker_keep_their_alpha(): void
    {
        $mv = MotionVisuals::resolve($this->settings([['shape' => 'heart', 'color' => '#9C27B080']], ['mv_primary' => '#1678DAFF', 'mv_dots_color' => '#00000040']));

        $this->assertSame('#1678daff', $mv['mv_primary']);
        $this->assertSame('#00000040', $mv['mv_dots_color']);
        $this->assertSame('#9c27b080', $mv['items'][0]['color']);
    }

    /**
     * Every canvas that draws a column or container shows its visual live. The nested column
     * has a template of its own, and was missed once: the page showed the visual, the
     * builder did not.
     */
    public function test_every_canvas_template_draws_the_live_layer(): void
    {
        $base = __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/';
        foreach (['column/col.blade.php' => 'column', 'nested/row.blade.php' => 'ncol', 'container/row.blade.php' => 'container'] as $file => $node) {
            $src = (string) file_get_contents($base.$file);
            $this->assertMatchesRegularExpression('/<falcon-mv-preview v-if="'.$node.'\.settings\.mv_preset && '.$node.'\.settings\.mv_enabled !== false" :settings="'.$node.'\.settings"/', $src, $file);
            $this->assertStringContainsString(':editing="!isPreview && editingContext.type', $src, $file.' shows no handles');
        }
    }

    /** Random: beside Main and Accent, a color that keeps changing by itself — everywhere. */
    public function test_random_color_reaches_shapes_text_and_dots(): void
    {
        $settings = $this->settings([
            ['id' => 'r1', 'kind' => 'shape', 'shape' => 'heart', 'color' => 'random'],
            ['id' => 'r2', 'kind' => 'text', 'text' => 'Hi', 'style' => 'gradient', 'color' => 'random'],
            ['id' => 'r3', 'kind' => 'text', 'text' => 'Yo', 'style' => 'outline', 'color' => 'random'],
            ['id' => 'k1', 'kind' => 'shape', 'shape' => 'star', 'color' => 'accent'],
        ], ['mv_dots_color' => 'random']);
        $html = $this->column($settings);

        // shapes and text take their color from the animated custom property
        $this->assertSame(3, substr_count($html, 'falcon-mv__shape falcon-mv__shape--random'));
        $this->assertStringContainsString('color:var(--mv-rc)', $html);
        $this->assertStringContainsString('--mv-c1:var(--mv-rc);--mv-c2:var(--mv-rc2)', $html, 'both ends of a gradient keep changing');
        $this->assertStringContainsString('@property --mv-rc{syntax:\'<color>\'', $html);
        $this->assertStringContainsString('@keyframes fmv-hue', $html);
        // the item that is not random is left alone
        $this->assertStringContainsString('color:#f59e0b', $html);
        // the dots are told, and the script does the rest
        $this->assertStringContainsString('data-mv-dots-color="random"', $html);
        // still, paused and reduced motion stop it too
        $this->assertStringContainsString('.falcon-mv.is-static .falcon-mv__anim,.falcon-mv.is-static .falcon-mv__shape--random{animation:none}', $html);

        // each item its own pace and start, the same on every load
        $mv = MotionVisuals::resolve($settings);
        $this->assertNotEquals([$mv['items'][0]['rdur'], $mv['items'][0]['rdelay']], [$mv['items'][1]['rdur'], $mv['items'][1]['rdelay']]);
        $this->assertSame($mv['items'], MotionVisuals::resolve($settings)['items']);
    }

    public function test_random_survives_the_round_trip(): void
    {
        $settings = $this->settings([['id' => 'r1', 'shape' => 'heart', 'color' => 'random']], ['mv_dots_color' => 'random']);
        $json = (string) json_encode([['id' => 's1', 'type' => 'section', 'settings' => [], 'columns' => [
            ['id' => 'c1', 'basis' => '100%', 'settings' => $settings, 'elements' => []],
        ]]]);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true)[0]['columns'][0]['settings'];

        $this->assertSame('random', $back['mv_dots_color']);
        $this->assertSame('random', MotionVisuals::decodeItems($back['mv_items'])[0]['color']);
    }

    /**
     * Pulse and swing once did nothing at all: they computed scale and rotate from the
     * distance in px — calc(1 + 24px / 100) and calc(24px * .5deg) are invalid, so the
     * browser dropped both animations. They read the unitless --mv-d now.
     */
    public function test_pulse_and_swing_read_a_plain_number(): void
    {
        $html = $this->column($this->settings([
            ['id' => 'p1', 'shape' => 'heart', 'anim' => 'pulse', 'dist' => 30],
            ['id' => 's1', 'shape' => 'leaf', 'anim' => 'swing', 'dist' => 40],
        ]));

        $this->assertStringContainsString('--mv-dist:30px;--mv-d:30;', $html);
        $this->assertStringContainsString('@keyframes fmv-pulse{0%,100%{scale:1}50%{scale:calc(1 + var(--mv-d) / 100)}}', $html);
        $this->assertStringContainsString('@keyframes fmv-swing{0%,100%{rotate:calc(var(--mv-d) * -.5deg)}50%{rotate:calc(var(--mv-d) * .5deg)}}', $html);
        $this->assertStringContainsString('.falcon-mv__anim--swing{animation-name:fmv-swing;transform-origin:50% 0}', $html, 'a swing hangs from the top');
        // nothing scales or turns by a length
        $this->assertDoesNotMatchRegularExpression('/(scale|rotate):calc\([^)]*--mv-dist/', $html);
    }

    public function test_a_column_without_one_is_unchanged(): void
    {
        $html = $this->column([]);

        $this->assertStringNotContainsString('data-falcon-mv', $html);
        $this->assertStringNotContainsString('falcon-mv-host', $html);
    }

    public function test_an_empty_visual_draws_nothing_on_the_page(): void
    {
        $this->assertSame('', MotionVisuals::render(['mv_preset' => 'custom']));
        $this->assertNotSame('', MotionVisuals::render(['mv_preset' => 'custom'], 'column', true), 'the builder still draws it, so its handles have a layer');
    }

    public function test_a_container_covers_the_whole_section(): void
    {
        $html = view('falcon-cms::frontend.builder.container', ['container' => [
            'id' => 's1', 'settings' => $this->settings(),
            'columns' => [['id' => 'c1', 'basis' => '100%', 'settings' => [], 'elements' => []]],
        ]])->render();

        $this->assertMatchesRegularExpression('/class="lazy-container falcon-mv-host[^"]*"[^>]*>\s*(<style>.*?<\/style>\s*)*<style>/s', $html);
        $this->assertStringContainsString('falcon-mv--container', $html);
    }

    /** Whatever is typed into the panel stays a number, a color, a name or an address. */
    public function test_settings_cannot_become_markup(): void
    {
        $html = $this->column($this->settings([
            ['id' => '"><script>alert(1)</script>', 'kind' => 'shape', 'shape' => '<script>', 'color' => 'red;}</style><script>alert(2)</script>',
                'x' => '1%;background:url(javascript:alert(3))', 'anim' => 'x;animation:evil', 'speed' => 'NaN'],
            ['kind' => 'image', 'src' => 'javascript:alert(4)'],
            ['kind' => 'image', 'src' => '/ok.png" onerror="alert(5)'],
        ], ['mv_layer' => '"><svg onload=alert(6)>', 'mv_dots_color' => 'url(javascript:alert(7))', 'mv_preset' => '"><b>']));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onload=', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('animation:evil', $html);
        $this->assertStringContainsString('falcon-mv--behind', $html, 'an unknown layer falls back to behind');
        $this->assertStringContainsString('left:50%', $html, 'a broken position falls back to the middle');
    }

    public function test_numbers_are_kept_to_their_range(): void
    {
        $mv = MotionVisuals::resolve(['mv_preset' => 'custom', 'mv_dots_count' => 99999, 'mv_speed' => -4,
            'mv_items' => MotionVisuals::encodeItems(array_fill(0, 80, ['shape' => 'circle', 'size' => 9999, 'opacity' => -20]))]);

        $this->assertSame(250.0, $mv['mv_dots_count']);
        $this->assertSame(0.25, $mv['mv_speed']);
        $this->assertCount(MotionVisuals::MAX_ITEMS, $mv['items']);
        $this->assertSame(120.0, $mv['items'][0]['size']);
        $this->assertSame(0.0, $mv['items'][0]['opacity']);
    }

    /** Flat scalars and one URL-encoded list: the shortcode converter keeps all of it. */
    public function test_settings_survive_the_shortcode_round_trip(): void
    {
        $settings = $this->settings();
        $json = (string) json_encode([[
            'id' => 's1', 'type' => 'section', 'settings' => $settings,
            'columns' => [['id' => 'c1', 'basis' => '100%', 'settings' => $settings, 'elements' => []]],
        ]]);

        $sc = BuilderShortcodeConverter::jsonToShortcodes($json);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);

        foreach ([$back[0]['settings'], $back[0]['columns'][0]['settings']] as $got) {
            foreach ($settings as $key => $value) {
                $this->assertEquals($value, $got[$key] ?? null, "{$key} was lost");
            }
        }
        $this->assertSame('star', MotionVisuals::decodeItems($back[0]['columns'][0]['settings']['mv_items'])[0]['shape']);
    }

    public function test_every_preset_and_shape_draws(): void
    {
        foreach (Presets::all() as $key => $preset) {
            $s = $preset['settings'];
            $s['mv_items'] = MotionVisuals::encodeItems($s['items'] ?? []);
            unset($s['items']);
            $html = MotionVisuals::render(['mv_preset' => $key] + $s, 'column', true);
            $this->assertStringContainsString('data-falcon-mv="'.$key.'"', $html, "preset {$key}");
        }
        $items = array_map(fn ($k) => ['shape' => $k], array_keys(Shapes::all()));
        $html = MotionVisuals::render(['mv_preset' => 'custom', 'mv_items' => MotionVisuals::encodeItems($items)]);
        $this->assertSame(count($items), substr_count($html, '<svg viewBox="0 0 100 100"'));
    }

    public function test_a_text_item_is_sized_by_the_layer_and_drawn_in_its_style(): void
    {
        $html = $this->column($this->settings([
            ['id' => 't1', 'kind' => 'text', 'text' => "Build\nfaster", 'style' => 'outline', 'font' => 'mono', 'weight' => 950, 'upper' => true, 'size' => 12, 'spacing' => 0.1],
            ['id' => 't2', 'kind' => 'text', 'text' => 'Laravel', 'style' => 'gradient', 'color' => 'accent'],
        ]));

        $this->assertStringContainsString('falcon-mv__item--text', $html);
        $this->assertStringContainsString('font-size:12cqw', $html, 'a text item is sized as a share of the layer, not in pixels');
        $this->assertStringContainsString('falcon-mv__text--outline', $html);
        $this->assertStringContainsString('font-weight:900', $html, 'weight is kept to 100–900');
        $this->assertStringContainsString('text-transform:uppercase', $html);
        $this->assertStringContainsString('letter-spacing:0.1em', $html);
        $this->assertStringContainsString('ui-monospace', $html);
        $this->assertStringContainsString(">Build\nfaster</span>", $html, 'line breaks stay (white-space: pre)');
        // a gradient runs from the item's color into the other of the two
        $this->assertStringContainsString('--mv-c1:#f59e0b;--mv-c2:#005ab4', $html);
    }

    public function test_text_stays_text(): void
    {
        $html = $this->column($this->settings([
            ['kind' => 'text', 'text' => '<img src=x onerror=alert(1)><b>"Hi" & bye</b>', 'font' => 'Comic"; x:url(', 'style' => 'evil', 'weight' => 'bold'],
        ]));

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('&quot;Hi&quot; &amp; bye', $html, 'tags are dropped, the words are printed escaped');
        $this->assertStringContainsString('font-family:inherit', $html, 'an unknown font falls back to the page font');
        $this->assertStringContainsString('falcon-mv__text--fill', $html);
        $this->assertStringContainsString('font-weight:800', $html);
    }

    public function test_quotes_and_line_breaks_in_text_survive_the_round_trip(): void
    {
        $settings = $this->settings([['id' => 'q1', 'kind' => 'text', 'text' => "Say \"hi\" [falcon_section]\nsecond line"]]);
        $json = (string) json_encode([['id' => 's1', 'type' => 'section', 'settings' => [], 'columns' => [
            ['id' => 'c1', 'basis' => '100%', 'settings' => $settings, 'elements' => []],
        ]]]);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true);

        $this->assertSame("Say \"hi\" [falcon_section]\nsecond line", MotionVisuals::decodeItems($back[0]['columns'][0]['settings']['mv_items'])[0]['text']);
    }

    /** Big Word sizes the site's name so the whole word fits across the layer. */
    public function test_big_word_fits_any_site_name(): void
    {
        foreach (['Ace' => 24.0, 'FalconCMS' => 13.6, 'The Very Long Company Name' => 4.7] as $name => $size) {
            $this->setCmsOptions(['site_title' => $name]);
            Presets::flush();
            $word = Presets::all()['big-word']['settings']['items'][0];
            $this->assertSame($name, $word['text']);
            $this->assertEqualsWithDelta($size, $word['size'], 0.1, "size for “{$name}”");
            $this->assertLessThanOrEqual(90, mb_strlen($name) * 0.72 * $word['size'] + 0.1, "“{$name}” runs past the layer");
        }
    }

    public function test_the_script_reaches_only_pages_that_have_a_visual(): void
    {
        $this->assertSame('', MotionVisuals::assetsForPage('<div class="lazy-column"></div>'));

        $tag = MotionVisuals::assetsForPage($this->column($this->settings()));
        $this->assertStringContainsString('vendor/falcon-cms/js/motion-visual.js', $tag);
        $this->assertStringContainsString('defer', $tag);
    }

    public function test_the_builder_preview_draws_the_settings_being_edited(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'mv-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.falcon-builder.motion-visual-preview'), [
                'settings' => $this->settings() + ['not_mv' => '<b>x</b>'],
                'where' => 'column',
            ])
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'data-mv-item="a1"')
                && str_contains($html, 'falcon-mv--editor')
                && str_contains($html, 'data-mv-mouse="0"')
                && str_contains($html, 'data-mv-dots-mouse="none"')
                && !str_contains($html, '<b>x</b>'));
    }

    public function test_the_builder_preview_is_closed_to_visitors(): void
    {
        // The admin hides itself from visitors, as it does on every admin address.
        $res = $this->postJson(route('admin.falcon-builder.motion-visual-preview'), ['settings' => $this->settings()]);

        $this->assertNotSame(200, $res->getStatusCode());
        $this->assertStringNotContainsString('data-falcon-mv', (string) $res->getContent());
    }

    public function test_plugins_can_add_shapes_and_presets(): void
    {
        add_falcon_filter('falcon_motion_visual_shapes', fn ($s) => $s + ['leaf' => ['name' => 'Leaf', 'svg' => '<path d="M0 0h1"/>'], 'Bad Key!' => ['svg' => 'x']]);
        add_falcon_filter('falcon_motion_visual_presets', fn ($p) => $p + ['autumn' => ['name' => 'Autumn', 'settings' => ['items' => [['shape' => 'leaf']]]]]);
        Shapes::flush();
        Presets::flush();

        $cfg = MotionVisuals::editorConfig();
        $this->assertArrayHasKey('leaf', $cfg['shapes']);
        $this->assertArrayNotHasKey('Bad Key!', $cfg['shapes']);
        $this->assertArrayHasKey('autumn', $cfg['presets']);
        $this->assertStringContainsString('leaf', rawurldecode($cfg['presets']['autumn']['settings']['mv_items']));
    }
}
