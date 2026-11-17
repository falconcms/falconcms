<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\DualButton;
use FalconCms\Core\Tests\TestCase;

/**
 * The Dual Button element: two calls to action drawn as one pair.
 *
 * Most of what can go wrong here is a setting meaning one thing in one place and another
 * somewhere else — a cleared colour growing back on save, the canvas holding a default the
 * page does not. So these check the three states of a setting through the shortcode, and
 * that the builder is fed from the same tables the page reads.
 */
class DualButtonTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../resources/views';

    /**
     * Dual Button is a Pro element, and the builder decides that from one list. Left out
     * of it the element would be free on every site while the pricing page said otherwise
     * — and the same list stops a locked element being edited or dragged.
     */
    public function test_it_is_gated_behind_pro(): void
    {
        $scripts = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/scripts.blade.php');

        preg_match('/const proElementTypes = \[(.*?)\];/s', $scripts, $m);
        $this->assertNotEmpty($m, 'the Pro element list is gone');
        $this->assertStringContainsString("'dual_button'", $m[1], 'the Dual Button element is not gated behind Pro');
    }

    // ── the shortcode round trip ─────────────────────────────────────────────

    public function test_a_fresh_element_is_a_short_shortcode_and_comes_back_whole(): void
    {
        $shortcode = $this->shortcode(DualButton::defaults());

        // Everything is at its default, so nothing but the tag needs writing.
        $this->assertMatchesRegularExpression('/\[falcon_dual_button id="e1" \/\]/', $shortcode);

        $back = $this->roundTrip(DualButton::defaults());
        foreach (DualButton::defaults() as $key => $value) {
            $this->assertEquals($value, $back[$key] ?? null, $key);
        }
    }

    public function test_every_setting_survives_the_round_trip(): void
    {
        $settings = [
            'primaryText' => 'Buy "now"', 'primaryUrl' => '/pricing?plan=pro&ref=hero', 'primaryTarget' => '_blank',
            'primaryIcon' => 'fa fa-cart-shopping', 'primaryIconPosition' => 'right',
            'secondaryText' => 'Docs', 'secondaryUrl' => 'https://example.com/docs', 'secondaryIcon' => 'fa fa-book',
            'showConnector' => false, 'connectorText' => 'VS',
            'gap' => 12, 'align' => 'left', 'align_tablet' => 'center', 'align_mobile' => 'right',
            'fullWidth' => true, 'equalWidth' => false, 'stackOn' => 'mobile', 'hoverEffect' => 'lift',
            'primaryBg' => '#ff0000', 'primaryColor' => 'rgba(255, 255, 255, 0.9)', 'primaryBorderColor' => '#aa0000',
            'primaryHoverBg' => '#cc0000', 'primaryHoverColor' => '#fff', 'primaryHoverBorderColor' => '#880000',
            'secondaryBg' => '#00ff00', 'secondaryHoverBg' => '#00cc00',
            'connectorBg' => '#000000', 'connectorColor' => '#ffffff', 'connectorSize' => 36,
            'borderWidth' => 1, 'radius' => 999, 'iconGap' => 10,
            'paddingTop' => 1.5, 'paddingTopUnit' => 'rem', 'paddingLeft' => 'calc(1rem + 4px)',
            'paddingTop_mobile' => 8, 'paddingTopUnit_mobile' => 'px', 'paddingRight_tablet' => 20,
            'marginTop' => 0, 'marginBottom' => 24, 'marginBottom_mobile' => 12,
            'db_family' => 'Inter, sans-serif', 'db_weight' => '700', 'db_size' => '18', 'db_transform' => 'uppercase',
            'cssClass' => 'hero-cta', 'cssId' => 'cta-1',
        ];

        $back = $this->roundTrip($settings);

        foreach ($settings as $key => $value) {
            $this->assertEquals($value, $back[$key] ?? null, $key);
        }
    }

    /**
     * Cleared is not the same as never set. A cleared label hides its button and a cleared
     * background is transparent — that is how an outline button is made — and if either
     * came back as its default, saving the page would undo the author's design.
     */
    public function test_a_cleared_setting_stays_cleared(): void
    {
        $shortcode = $this->shortcode(['secondaryText' => '', 'secondaryBg' => '', 'connectorText' => '', 'radius' => '']);

        $this->assertStringContainsString('secondary_text=""', $shortcode);
        $this->assertStringContainsString('secondary_bg=""', $shortcode);

        $back = $this->roundTrip(['secondaryText' => '', 'secondaryBg' => '', 'connectorText' => '', 'radius' => '']);

        $this->assertSame(['', '', '', ''], [$back['secondaryText'], $back['secondaryBg'], $back['connectorText'], $back['radius']]);
        // …while one that was never set is the default.
        $this->assertSame('Get Started', $back['primaryText']);
        $this->assertSame('#2271b1', $back['primaryBg']);
    }

    public function test_visibility_survives_the_round_trip(): void
    {
        $back = $this->roundTrip(['visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => true]]);

        $this->assertSame(['mobile' => false, 'tablet' => true, 'desktop' => true], $back['visibility']);
    }

    /** The classic editor wraps shortcodes in <p> and <br>; the element has to come through it. */
    public function test_it_survives_the_rich_editor(): void
    {
        $shortcode = BuilderShortcodeConverter::jsonToShortcodes($this->layout(['primaryText' => 'Start free', 'gap' => 16]));
        $mangled = '<p>'.str_replace("\n", "<br />\n", $shortcode).'</p>';

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($mangled), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? null;

        $this->assertSame('dual_button', $el['type'] ?? null);
        $this->assertSame('Start free', $el['settings']['primaryText']);
        $this->assertSame(16, $el['settings']['gap']);
    }

    // ── rendering ────────────────────────────────────────────────────────────

    public function test_it_renders_both_buttons_and_the_connector(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('fdb--joined', $html, 'no gap is a joined pair');
        $this->assertStringContainsString('fdb--equal', $html);
        $this->assertStringContainsString('class="fdb-btn fdb-btn--primary"', $html);
        $this->assertStringContainsString('class="fdb-btn fdb-btn--secondary"', $html);
        $this->assertStringContainsString('>Get Started</span>', $html);
        $this->assertStringContainsString('>Learn More</span>', $html);
        $this->assertStringContainsString('<span class="fdb-or" aria-hidden="true">OR</span>', $html);
        $this->assertStringContainsString('--fdb-p-bg:#2271b1', $html);
        $this->assertStringContainsString('role="group"', $html);
    }

    public function test_a_gap_separates_the_pair(): void
    {
        $html = $this->render(['gap' => 12]);

        $this->assertStringNotContainsString('fdb--joined', $html);
        $this->assertStringContainsString('--fdb-gap:12px', $html);
    }

    /**
     * A side with neither label nor icon is not drawn, and what is left is an ordinary
     * single button: no connector, no group, and both "first" and "last" so every corner
     * is rounded.
     */
    public function test_a_cleared_button_leaves_a_single_button(): void
    {
        $html = $this->render(['secondaryText' => '']);

        $this->assertStringNotContainsString('fdb-btn--secondary', $html);
        $this->assertStringNotContainsString('class="fdb-or"', $html);
        $this->assertStringNotContainsString('role="group"', $html);
        $this->assertStringContainsString('fdb-cell fdb-cell--first fdb-cell--last', $html);

        // An icon alone is still a button.
        $html = $this->render(['secondaryText' => '', 'secondaryIcon' => 'fa fa-arrow-right']);
        $this->assertStringContainsString('fdb-btn--secondary', $html);
        $this->assertStringContainsString('<i class="fa fa-arrow-right" aria-hidden="true"></i>', $html);
    }

    public function test_with_both_buttons_cleared_it_renders_nothing(): void
    {
        $this->assertSame('', trim($this->render(['primaryText' => '', 'secondaryText' => ''])));
    }

    public function test_the_connector_can_be_switched_off_or_cleared(): void
    {
        $this->assertStringNotContainsString('class="fdb-or"', $this->render(['showConnector' => false]));
        $this->assertStringNotContainsString('class="fdb-or"', $this->render(['connectorText' => ' ']));
    }

    public function test_a_cleared_colour_sets_no_property_so_the_stylesheet_decides(): void
    {
        $html = $this->render(['secondaryBg' => '', 'secondaryBorderColor' => '#2271b1']);

        $this->assertStringNotContainsString('--fdb-s-bg', $html);
        $this->assertStringContainsString('--fdb-s-border:#2271b1', $html);
        // …and the stylesheet's answer for no background is transparent.
        $this->assertStringContainsString('--_bg:var(--fdb-s-bg,transparent)', DualButton::css());
    }

    public function test_links_open_as_set_and_a_button_without_one_is_not_a_link(): void
    {
        $html = $this->render([
            'primaryUrl' => 'https://example.com/a?x=1&amp;y=2', 'primaryTarget' => '_blank',
            'secondaryUrl' => '',
        ]);

        // Decoded once, escaped once: "&amp;" in, "&amp;" out — not "&amp;amp;".
        $this->assertStringContainsString('href="https://example.com/a?x=1&amp;y=2"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        $this->assertMatchesRegularExpression('/<span class="fdb-btn fdb-btn--secondary"\s*>/', $html);
    }

    public function test_only_real_links_reach_the_href(): void
    {
        $this->assertSame('', DualButton::safeHref('javascript:alert(1)'));
        $this->assertSame('', DualButton::safeHref('data:text/html,x'));
        foreach (['#plans', '/pricing', '?tab=2', 'https://example.com', 'mailto:hi@example.com', 'tel:+8801700000000'] as $url) {
            $this->assertSame($url, DualButton::safeHref($url), $url);
        }

        $this->assertStringNotContainsString('javascript:', $this->render(['primaryUrl' => 'javascript:alert(1)']));
    }

    public function test_settings_cannot_break_out_of_the_markup(): void
    {
        $html = $this->render([
            'primaryText' => '<script>alert(1)</script>',
            'primaryIcon' => 'fa fa-star" onmouseover="alert(2)',
            'primaryBg' => 'red;}</style><script>alert(3)</script>',
            'radius' => '6px;background:url(x)',
            'align_mobile' => 'left}</style><script>alert(4)</script>',
            'cssClass' => '"><script>alert(5)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('</style><', str_replace('</style><div', '', $html));
        $this->assertStringNotContainsString('onmouseover="', $html);
        $this->assertStringNotContainsString(';background:url', $html);
    }

    public function test_stacking_and_width_are_classes_and_alignment_a_property(): void
    {
        $html = $this->render(['stackOn' => 'tablet', 'fullWidth' => true, 'equalWidth' => false, 'align' => 'right', 'hoverEffect' => 'grow']);

        $this->assertStringContainsString('fdb--stack-tablet', $html);
        $this->assertStringContainsString('fdb--full', $html);
        $this->assertStringContainsString('fdb--grow', $html);
        $this->assertStringNotContainsString('fdb--equal', $html);
        $this->assertStringContainsString('--fdb-justify:flex-end', $html);

        // The canvas is told its device instead of waiting for a media query.
        $this->assertContains('fdb--stacked', DualButton::classes(['stackOn' => 'tablet'], 'mobile'));
        $this->assertContains('fdb--stacked', DualButton::classes(['stackOn' => 'tablet'], 'tablet'));
        $this->assertNotContains('fdb--stacked', DualButton::classes(['stackOn' => 'mobile'], 'tablet'));
        $this->assertNotContains('fdb--stacked', DualButton::classes(['stackOn' => 'tablet'], 'desktop'));
    }

    public function test_tablet_and_mobile_values_are_media_queries(): void
    {
        $html = $this->render([
            'paddingTop_mobile' => 8, 'marginBottom_tablet' => 30, 'marginBottomUnit_tablet' => 'px',
            'align_tablet' => 'left',
        ]);

        // An !important custom property is what lets a media query beat the inline value.
        $this->assertMatchesRegularExpression('/@media\(max-width:\d+px\)\{\.fdb-e1\{[^}]*--fdb-pt:8px!important\}\}/', $html);
        $this->assertStringContainsString('margin-bottom:30px!important', $html);
        $this->assertStringContainsString('--fdb-justify:flex-start!important', $html);
        // Mobile follows tablet when it has no alignment of its own.
        $this->assertSame(2, substr_count($html, '--fdb-justify:flex-start!important'));
    }

    public function test_spacing_typography_and_visibility_reach_the_wrapper(): void
    {
        $html = $this->render([
            'paddingTop' => 1.5, 'paddingTopUnit' => 'rem', 'paddingLeft' => '', 'marginTop' => '', 'marginBottom' => 0,
            'db_size' => '18', 'db_weight' => '700',
            'visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => true],
            'cssClass' => 'hero-cta', 'cssId' => 'cta-1',
        ]);

        $this->assertStringContainsString('--fdb-pt:1.5rem', $html);
        $this->assertStringContainsString('--fdb-pl:28px', $html, 'a blank side is the default its placeholder shows');
        $this->assertStringContainsString('margin-top:10px', $html, 'a blank margin is the default');
        $this->assertStringContainsString('margin-bottom:0px', $html, 'a typed 0 is no margin');
        $this->assertStringContainsString('font-size: 18px', $html);
        $this->assertStringContainsString('font-weight: 700', $html);
        $this->assertStringContainsString('falcon-hide-mobile', $html);
        $this->assertStringContainsString('hero-cta', $html);
        $this->assertStringContainsString('id="cta-1"', $html);
        $this->assertStringContainsString('width:100%', $html);
    }

    /**
     * Border width, corners and icon gap are text boxes like Padding: any CSS size, a bare
     * number is px, and each can be set per device.
     */
    public function test_shape_sizes_take_any_css_size_per_device(): void
    {
        $settings = [
            'borderWidth' => '0.2rem', 'radius' => 12, 'iconGap' => '1em',
            'borderWidth_tablet' => 3, 'radius_mobile' => '50px', 'iconGap_mobile' => 4,
        ];

        $back = $this->roundTrip($settings);
        foreach ($settings as $key => $value) {
            $this->assertEquals($value, $back[$key] ?? null, $key);
        }

        $html = $this->render($settings);
        $this->assertStringContainsString('--fdb-bw:0.2rem', $html);
        $this->assertStringContainsString('--fdb-radius:12px', $html);
        $this->assertStringContainsString('--fdb-icon-gap:1em', $html);
        $this->assertMatchesRegularExpression('/@media\(min-width:\d+px\) and \(max-width:\d+px\)\{\.fdb-e1\{[^}]*--fdb-bw:3px!important/', $html);
        $this->assertMatchesRegularExpression('/@media\(max-width:\d+px\)\{\.fdb-e1\{[^}]*--fdb-radius:50px!important/', $html);
        $this->assertMatchesRegularExpression('/@media\(max-width:\d+px\)\{\.fdb-e1\{[^}]*--fdb-icon-gap:4px!important/', $html);
    }

    public function test_a_device_size_cannot_end_the_rule(): void
    {
        $html = $this->render(['radius_mobile' => '4px;}body{display:none', 'paddingTop_tablet' => '2px}*{color:red']);

        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringNotContainsString('}*{', $html);
    }

    /** The border colours sit with the border width, so a width never looks like it did nothing. */
    public function test_border_colours_sit_beside_the_border_width(): void
    {
        $design = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/components/elements/dual-button-design.blade.php');
        $shape = substr($design, (int) strpos($design, '══ SHAPE ══'));

        $this->assertStringContainsString("['BorderColor', 'Border color'", $shape);
        $this->assertStringContainsString("['HoverBorderColor', 'Border color on hover'", $shape);
        $this->assertStringNotContainsString('type="number"', $design, 'the size fields are text boxes, like Padding');

        $html = $this->render(['primaryBorderColor' => '#ff0000', 'primaryHoverBorderColor' => '#00ff00', 'borderWidth' => 4]);
        $this->assertStringContainsString('--fdb-p-border:#ff0000', $html);
        $this->assertStringContainsString('--fdb-p-hborder:#00ff00', $html);
        $this->assertStringContainsString('--fdb-bw:4px', $html);
    }

    // ── dynamic sources ──────────────────────────────────────────────────────

    /**
     * Each button keeps its dynamic source and options in an object of its own. Kept flat
     * on the element, the second button's options (dynamic_before …) would overwrite the
     * first's, since the picker writes them under the same names.
     */
    public function test_each_buttons_dynamic_source_survives_the_round_trip_on_its_own(): void
    {
        $settings = [
            'primaryDynamic' => ['dynamic_source' => 'post_date', 'dynamic_before' => 'On "the" day ', 'dynamic_date_format' => 'Y'],
            'secondaryDynamic' => ['dynamic_source' => 'current_year', 'dynamic_before' => 'Since ',
                'link_dynamic_source' => 'taxonomy_url', 'dynamic_link_tax_slug' => 'category'],
        ];

        $shortcode = $this->shortcode($settings);
        $this->assertStringContainsString('primary_dyn_dynamic_source="post_date"', $shortcode);
        $this->assertStringContainsString('secondary_dyn_link_dynamic_source="taxonomy_url"', $shortcode);

        $back = $this->roundTrip($settings);
        $this->assertEquals($settings['primaryDynamic'], $back['primaryDynamic']);
        $this->assertEquals($settings['secondaryDynamic'], $back['secondaryDynamic']);
        $this->assertArrayNotHasKey('primary_dyn_dynamic_source', $back, 'no attribute leaks out as a setting of its own');
        $this->assertArrayNotHasKey('dynamic_source', $back);
    }

    public function test_a_dynamic_label_and_link_render_and_fall_back_to_what_was_typed(): void
    {
        $html = $this->render([
            'primaryText' => 'Typed', 'primaryUrl' => '/typed',
            'primaryDynamic' => ['dynamic_source' => 'current_year', 'dynamic_before' => 'Since ', 'link_dynamic_source' => 'site_url'],
            'secondaryText' => 'Docs',
            'secondaryDynamic' => ['dynamic_source' => 'acpt_custom', 'dynamic_acpt_slug' => 'no_such_field'],
        ]);

        $this->assertStringContainsString('>Since '.date('Y').'</span>', $html);
        $this->assertStringContainsString('href="'.url('/'), $html);
        $this->assertStringNotContainsString('/typed', $html);
        // A source with nothing to say leaves the typed label in place.
        $this->assertStringContainsString('>Docs</span>', $html);
    }

    public function test_the_content_tab_matches_the_button_element(): void
    {
        $content = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/components/elements/dual-button-content.blade.php');

        $this->assertStringContainsString("openDynSrcMenu(fcDbDyn(editingElement, '{{ \$side }}'), 'dynamic_source', 'text'", $content);
        $this->assertStringContainsString("openDynSrcMenu(fcDbDyn(editingElement, '{{ \$side }}'), 'link_dynamic_source', 'link'", $content);
        $this->assertStringContainsString('<option value="_blank">New Window</option>', $content);
        $this->assertStringContainsString('fields.icon', $content);
        $this->assertStringNotContainsString('Choose icon', $content, 'the icon grid is open, as on the Button');

        // Blade copies an escaped mustache out as it stands, so a Blade echo inside one
        // reaches Vue unexpanded and the whole canvas fails to compile.
        foreach (['dual-button', 'dual-button-content', 'dual-button-design'] as $partial) {
            $source = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/components/elements/'.$partial.'.blade.php');
            $this->assertDoesNotMatchRegularExpression('/@\{\{(?:(?!\}\}).)*\{\{/s', $source, $partial.': a Blade echo inside an escaped mustache');
        }
    }

    // ── the stylesheet ───────────────────────────────────────────────────────

    public function test_the_stylesheet_ships_only_with_a_layout_that_uses_the_element(): void
    {
        $with = json_decode($this->layout([]), true);
        $without = json_decode($this->layout([], 'button'), true);

        $this->assertTrue(DualButton::layoutUses($with));
        $this->assertFalse(DualButton::layoutUses($without));

        $this->assertStringContainsString('.fdb-group{', view('falcon-cms::frontend.builder.render', ['layout' => $with])->render());
        $this->assertStringNotContainsString('.fdb-group{', view('falcon-cms::frontend.builder.render', ['layout' => $without])->render());
    }

    public function test_stacking_follows_the_themes_breakpoints(): void
    {
        $css = DualButton::css(640, 1024);

        $this->assertStringContainsString('@media(max-width:640px){.fdb--stack-mobile ', $css);
        $this->assertStringContainsString('@media(max-width:1024px){.fdb--stack-tablet ', $css);
        $this->assertStringContainsString('.fdb--stacked .fdb-group{grid-auto-flow:row}', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    /** Every property a setting feeds has to be one the stylesheet actually reads. */
    public function test_every_custom_property_is_read_by_the_stylesheet(): void
    {
        $css = DualButton::css();

        foreach (DualButton::VARS as $key => [$prop]) {
            $this->assertArrayHasKey($key, DualButton::FIELDS, $key.' has a property but is not a setting');
            $this->assertStringContainsString('var('.$prop, $css, $prop.' is set but never read');
        }
        foreach (DualButton::PADDING as [$prop]) {
            $this->assertStringContainsString('var('.$prop, $css);
        }
        $this->assertStringContainsString('var(--fdb-justify', $css);
    }

    // ── the builder ──────────────────────────────────────────────────────────

    /**
     * The canvas and the page are the same stylesheet and the same tables. If the builder
     * stopped including either, the preview would quietly become a guess.
     */
    public function test_the_builder_is_fed_from_the_same_source_as_the_page(): void
    {
        $partials = self::VIEWS.'/admin/falcon-builder/partials';
        $scripts = (string) file_get_contents($partials.'/scripts.blade.php');

        $this->assertStringContainsString("@include('falcon-cms::components.frontend.dual-button-styles')",
            (string) file_get_contents($partials.'/styles.blade.php'));
        $this->assertStringContainsString('DualButton::builderConfig()', $scripts);
        $this->assertStringContainsString("{ type: 'dual_button', name: 'Dual Button'", $scripts, 'not in the element palette');
        $this->assertStringContainsString("type === 'dual_button' ? JSON.parse(JSON.stringify(FC_DB.defaults))", $scripts);

        // Each helper the templates call is handed to them by the app's setup().
        preg_match('/fcDbPresets: FC_DB\.presets.*?\n.*?\n/', $scripts, $returned);
        $this->assertNotEmpty($returned);
        foreach (['dual-button', 'dual-button-content', 'dual-button-design'] as $partial) {
            $source = (string) file_get_contents($partials.'/components/elements/'.$partial.'.blade.php');
            preg_match_all('/\bfcDb[A-Za-z]+/', $source, $used);
            foreach (array_unique($used[0]) as $helper) {
                $this->assertStringContainsString($helper, $returned[0], $partial.' calls '.$helper.', which setup() does not return');
            }
        }

        foreach (['column/col', 'nested/row'] as $host) {
            $source = (string) file_get_contents($partials.'/components/'.$host.'.blade.php');
            $this->assertStringContainsString('components.elements.dual-button', $source, $host.' does not draw it');
            $this->assertStringContainsString("'dual_button'", $source);
        }

        $sidebar = (string) file_get_contents($partials.'/sidebar.blade.php');
        $this->assertStringContainsString('elements.dual-button-content', $sidebar);
        $this->assertStringContainsString('elements.dual-button-design', $sidebar);
    }

    /** The builder config is what JS mirrors; a preset must only write real settings. */
    public function test_presets_only_write_settings_that_exist(): void
    {
        $config = DualButton::builderConfig();

        $this->assertSame(DualButton::defaults(), $config['defaults']);
        foreach (DualButton::PRESETS as $slug => $preset) {
            $this->assertNotEmpty($preset['name'] ?? '', $slug);
            foreach (array_keys($preset) as $key) {
                if ($key !== 'name') {
                    $this->assertArrayHasKey($key, DualButton::FIELDS, "{$slug} writes {$key}, which is not a setting");
                }
            }
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function layout(array $settings, string $type = 'dual_button'): string
    {
        return json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => $type, 'settings' => $settings]]]],
        ]]);
    }

    private function shortcode(array $settings): string
    {
        return BuilderShortcodeConverter::jsonToShortcodes($this->layout($settings));
    }

    private function roundTrip(array $settings): array
    {
        $json = BuilderShortcodeConverter::shortcodesToJson($this->shortcode($settings));

        return json_decode($json, true)[0]['columns'][0]['elements'][0]['settings'];
    }

    private function render(array $settings): string
    {
        return view('falcon-cms::frontend.builder.elements.dual-button', ['el' => [
            'id' => 'e1', 'type' => 'dual_button', 'settings' => $settings,
        ]])->render();
    }
}
