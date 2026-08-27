<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The page builder's Menu element.
 *
 * Three separate reasons a colour setting could appear to "do nothing", all of which had
 * to be true at once for the menu to look right, and none of which threw an error:
 * the field might not exist, the rule might not be written, or it might be written and
 * then lost to a stylesheet with more weight.
 */
class MenuElementTest extends TestCase
{
    /** @return array<string, mixed> */
    private function menuFields(): array
    {
        $elements = apply_falcon_filters('falcon_builder_elements', []);

        $this->assertArrayHasKey('menu', $elements, 'the Menu element is not registered');

        return $elements['menu']['fields'];
    }

    // ---- the settings that must exist ------------------------------------------

    public function test_the_menu_offers_a_colour_for_each_state(): void
    {
        $fields = $this->menuFields();

        foreach ([
            'itemColor', 'itemBgColor',
            'itemColorHover', 'itemBgColorHover',
            'itemColorActive', 'itemBgColorActive',
        ] as $field) {
            $this->assertArrayHasKey($field, $fields, "missing setting: {$field}");
            $this->assertSame('color', $fields[$field]['type'], $field);
        }
    }

    /**
     * The active colours default to empty on purpose: an existing menu that has never had
     * them set must keep looking exactly as it does now.
     */
    public function test_the_active_colours_default_to_unset(): void
    {
        $fields = $this->menuFields();

        $this->assertSame('', $fields['itemColorActive']['default']);
        $this->assertSame('', $fields['itemBgColorActive']['default']);
    }

    /**
     * PHP keeps the last of a repeated array key, so a duplicate silently overrides the
     * original — which is how two controls ended up on a tab nobody expected to find them
     * on. Counting the raw declarations catches a reintroduced duplicate; reading the
     * built array cannot, because by then the duplicate has already won.
     */
    public function test_no_menu_setting_is_declared_twice(): void
    {
        $source = file_get_contents(__DIR__.'/../../../src/helpers.php');
        $start = strpos($source, "\$elements['menu'] = [");
        $this->assertNotFalse($start, 'the Menu element definition has moved');

        $end = strpos($source, "\$elements['", $start + 10);
        $block = substr($source, $start, $end ? $end - $start : 20000);

        preg_match_all("/^\s{12}'([a-zA-Z0-9_]+)' =>/m", $block, $matches);

        $counts = array_count_values($matches[1]);
        $duplicates = array_keys(array_filter($counts, fn ($n) => $n > 1));

        $this->assertSame([], $duplicates,
            'declared more than once: '.implode(', ', $duplicates));
    }

    // ---- the rules that must be written ----------------------------------------

    private function template(): string
    {
        return file_get_contents(
            __DIR__.'/../../../resources/views/frontend/builder/elements/menu.blade.php'
        );
    }

    /**
     * The colours must be rules in the stylesheet, not declarations in the style attribute.
     *
     * Both ways of putting them inline are wrong, in opposite directions. Without
     * !important a theme rule that has it wins and the colour looks dead. With !important
     * the inline declaration outranks every stylesheet rule — including :hover, which is
     * how adding it killed the hover colour outright. As a rule beside :hover both work,
     * because :hover is simply the more specific selector.
     */
    public function test_the_colours_are_rules_rather_than_inline_declarations(): void
    {
        $template = $this->template();

        foreach (['$mainLinkStyle', '$subLinkStyle', '$mobileLinkStyle'] as $var) {
            $this->assertStringNotContainsString($var." .= ' color: '", $template,
                "{$var} still sets a colour inline");
            $this->assertStringNotContainsString($var." .= ' background-color: '", $template,
                "{$var} still sets a background inline");
        }
    }

    public function test_the_resting_and_hover_colours_are_both_written_as_rules(): void
    {
        $template = $this->template();

        // Resting first, then hover — the more specific selector of the two.
        $resting = strpos($template, ".falcon-menu-link { color: {{ \$s['itemColor']");
        $hover = strpos($template, ".falcon-menu-link:hover { color: {{ \$s['itemColorHover']");

        $this->assertNotFalse($resting, 'no rule sets the resting colour');
        $this->assertNotFalse($hover, 'no rule sets the hover colour');

        foreach (['itemBgColor', 'itemBgColorHover'] as $setting) {
            $this->assertStringContainsString($setting, $template, $setting);
        }
    }

    public function test_the_active_state_has_a_rule_of_its_own(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('.falcon-menu-item.active > .falcon-menu-link', $template,
            'nothing styles the item for the current page');
        $this->assertStringContainsString('itemColorActive', $template);
        $this->assertStringContainsString('itemBgColorActive', $template);
    }

    /**
     * There is no "Active" picker in the design panel — only the four fields the
     * screenshot shows: BG Color, BG Hover, Text Color, Text Hover. itemColorActive and
     * itemBgColorActive exist for a future panel, but nothing can set them today, so a
     * rule that only fired when they were non-empty could never fire at all: the current
     * page kept its resting colours forever, indistinguishable from any other item.
     *
     * The active item is defined to look like the hover state by default — the ordinary
     * meaning of "this is where you are" — while still preferring itemColorActive /
     * itemBgColorActive if a later panel ever sets them.
     */
    public function test_the_active_state_falls_back_to_the_hover_colours(): void
    {
        $template = $this->template();

        // Null-coalesced before the Elvis check: itemColorActive/itemBgColorActive did not
        // exist when older menus were saved, so the key can be genuinely absent rather than
        // merely empty. `$s['x'] ?: ...` still evaluates `$s['x']` first and throws on a
        // missing key exactly where `?? ''` does not — this is pinned by an actual render
        // in test_a_menu_saved_before_the_active_fallback_existed_still_renders() below,
        // which is the only way this class of bug is ever caught.
        $this->assertStringContainsString(
            "(\$s['itemColorActive'] ?? '') ?: (\$s['itemColorHover']",
            $template,
            'the active text colour no longer falls back to the hover colour safely'
        );
        $this->assertStringContainsString(
            "(\$s['itemBgColorActive'] ?? '') ?: (\$s['itemBgColorHover']",
            $template,
            'the active background no longer falls back to the hover colour safely'
        );
    }

    /** Hover still has to win over the active state, or the menu stops responding. */
    public function test_hover_still_overrides_the_active_state(): void
    {
        $this->assertStringContainsString(
            '.falcon-menu-item.active > .falcon-menu-link:hover',
            $this->template()
        );
    }

    // ---- deciding which item is current ----------------------------------------

    /**
     * Menu items are stored however they were entered — "/", an absolute URL, or "#" —
     * while the current URL is always absolute. Comparing whole strings meant "/" never
     * equalled "http://example.com", so the Home item was never marked active on the home
     * page and no active styling could ever have shown, whatever colours were set.
     */
    public function test_the_current_item_is_matched_on_path_not_on_the_whole_url(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('PHP_URL_PATH', $template,
            'the current item is still matched by comparing whole URLs');
        $this->assertStringNotContainsString(
            "\$isActive = (rtrim(\$currentUrl, '/') == rtrim(\$item->url, '/'));",
            $template,
            'the whole-URL comparison is back'
        );
    }

    /** A bare anchor is not a page, and a link to another site is not the page you are on. */
    public function test_anchors_and_external_links_are_never_the_current_item(): void
    {
        $template = $this->template();

        $this->assertStringContainsString("'#'", $template, 'anchors are not excluded');
        $this->assertStringContainsString('PHP_URL_HOST', $template,
            'a link to another host would be treated as the current page');
    }

    // ---- rendering with data an old menu could actually have --------------------

    /**
     * itemColorActive and itemBgColorActive were added after menus already existed in the
     * wild, so a menu saved before that has a settings array that does not merely have
     * them empty — it does not have the keys at all. `?:` still evaluates its left side,
     * so `$s['itemColorActive'] ?: ...` throws on a genuinely missing key exactly the way
     * `??` does not; this shipped in the same commit that added the fallback and crashed
     * every page carrying an old menu. Only a real render catches it — no amount of
     * string-matching the source would have.
     */
    public function test_a_menu_saved_before_the_active_fallback_existed_still_renders(): void
    {
        $settings = [
            'menuId' => null,
            'itemColor' => '#333333',
            'itemBgColor' => 'transparent',
            'itemColorHover' => '#0091ea',
            'itemBgColorHover' => 'transparent',
            // itemColorActive / itemBgColorActive deliberately absent.
        ];

        $html = view('falcon-cms::frontend.builder.elements.menu', [
            'el' => ['id' => 'test-menu-1', 'settings' => $settings],
        ])->render();

        $this->assertStringContainsString('color: #0091ea', $html,
            'a menu with no active colours of its own must fall back to the hover colour');
    }

    // ---- the canvas must lay items out the way the front end will --------------

    /**
     * The `<li>` on the canvas hardcoded alignItems: 'stretch' for desktop, ignoring
     * el.settings.alignItems entirely — while the front end always honoured the setting,
     * defaulting to 'center'. A stretched item is exactly as tall as the row, which is not
     * what its own padding says; a WYSIWYG canvas that silently overrides a setting the
     * front end respects is a bug on its own, whatever it looks like on screen.
     */
    public function test_the_canvas_honours_the_align_items_setting_instead_of_forcing_stretch(): void
    {
        $canvas = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/menu.blade.php'
        );

        $this->assertStringNotContainsString("alignItems: 'stretch'", $canvas,
            'the canvas still forces every item to the full row height');
        $this->assertStringContainsString('el.settings.alignItems', $canvas,
            'the canvas does not read the alignItems setting at all');
    }

    /**
     * A saved font is "Josefin Sans, sans-serif" — the fallback stack, not just the Google
     * Fonts name. The front end strips to the first name before building the css2 URL
     * (explode(',', $ff)[0]); the canvas built the same URL straight from the raw setting,
     * asking Google Fonts for the family "Josefin Sans, sans-serif", which it doesn't
     * recognise. The custom font silently failed to load and the canvas fell back to a
     * system font — same padding as the front end, visibly different box, because the
     * glyphs it was measuring were not the same glyphs. This is what the user's "canvas
     * padding looks narrower" screenshots actually were: a font that never loaded.
     */
    public function test_the_canvas_strips_the_font_family_before_requesting_it_from_google(): void
    {
        $canvas = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/menu.blade.php'
        );

        foreach (['el.settings.fontFamily', 'el.settings.submenuFontFamily', 'el.settings.mobileMenuFontFamily'] as $setting) {
            $this->assertStringNotContainsString($setting.'.replace(/ /g', $canvas,
                "{$setting} is still built into the Google Fonts URL without stripping the fallback stack first");
            $this->assertStringContainsString('googleFontFamily(('.$setting, $canvas,
                "{$setting} does not go through googleFontFamily() before being requested");
        }
    }

    /** The helper itself has to do what the front end's explode(',', $ff)[0] does. */
    public function test_the_google_font_family_helper_strips_the_fallback_stack(): void
    {
        $scripts = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        $start = strpos($scripts, 'const googleFontFamily = ');
        $this->assertNotFalse($start, 'the googleFontFamily helper is missing');

        $body = substr($scripts, $start, 400);
        $this->assertStringContainsString("split(',')[0]", $body,
            'the helper no longer isolates the first font in the stack');
    }

    /**
     * "Inherit" resolves to two different fonts depending on where you're looking. On the
     * real site the theme styles `body nav` with its own Navigation typography setting from
     * the customizer — not the body font — so that's what a menu left on "Inherit" actually
     * renders as there. The canvas has no theme stylesheet at all, so "inherit" fell through
     * to the admin panel's own UI font (Inter/Outfit) instead: identical padding, visibly
     * different box, because the two were measuring different glyphs. This — not a padding
     * bug — is what a user seeing "the same menu looks narrower in the canvas" was actually
     * looking at; confirmed by rendering both with a real browser and comparing computed
     * styles, which showed matching padding down to the pixel and only the font differing.
     *
     * themeBodyFont resolves to the site's *body* font (e.g. headings/paragraphs), which is
     * a different customizer setting from Navigation and would reproduce the same class of
     * mismatch with a different font swapped in — so it is deliberately not an acceptable
     * fallback here, only themeNavFont is.
     */
    public function test_the_canvas_falls_back_to_the_theme_nav_font_not_the_body_font(): void
    {
        $canvas = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components/elements/menu.blade.php'
        );

        $this->assertStringNotContainsString('themeBodyFont', $canvas,
            'the menu canvas reads the body font, not the Navigation typography setting the theme actually styles it with');

        foreach (['el.settings.fontFamily', 'el.settings.submenuFontFamily', 'el.settings.mobileMenuFontFamily'] as $setting) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($setting, '/').'.*?!== "inherit"\\).*?themeNavFont/',
                $canvas,
                "{$setting} does not fall back to themeNavFont when left on Inherit"
            );
        }
    }

    /** The controllers have to actually supply what the canvas now reads. */
    public function test_the_builder_controllers_pass_the_theme_nav_font_to_the_view(): void
    {
        foreach ([
            'src/Http/Controllers/Admin/PostController.php',
            'src/Http/Controllers/Admin/BuilderLibraryController.php',
        ] as $file) {
            $source = file_get_contents(__DIR__.'/../../../'.$file);

            $this->assertStringContainsString('theme_typography_nav', $source,
                "{$file} no longer reads the Navigation typography setting");
            $this->assertStringContainsString('themeNavFont', $source,
                "{$file} no longer computes themeNavFont for the view");
        }
    }
}
