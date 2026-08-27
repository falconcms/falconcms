<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The Cart / Search / Wishlist "special" menu items.
 *
 * The Menu Item Options modal lets an editor pick a FontAwesome icon for any item, special
 * ones included, and the modal's own preview shows it correctly the moment it's picked. The
 * menu itself never read it: lazy_render_special_menu_item() always drew one of three fixed
 * SVGs keyed by item type, so a chosen icon was saved — and stayed saved, nothing was lost —
 * it just never reached the page. Only the special items were affected: an ordinary link's
 * icon is rendered by the same class the modal writes, with nothing in between to override it.
 *
 * The count badge next to Cart/Wishlist had the same shape of bug one layer down: its
 * background was a literal #0091ea rather than the Customizer's Primary Color, so it matched
 * every other themed colour on a stock install and silently stopped matching the moment a
 * site picked its own Primary Color — exactly what the theme header's own badge avoids by
 * using the `bg-primary` Tailwind class instead of a fixed hex.
 */
class SpecialMenuItemIconTest extends TestCase
{
    private function specialItem(string $type, ?string $icon): object
    {
        return (object) [
            'type' => $type,
            'title' => 'Item',
            'icon' => $icon,
        ];
    }

    public function test_a_custom_icon_is_used_for_the_cart_item(): void
    {
        $html = lazy_render_special_menu_item($this->specialItem('special_cart', 'fa fa-shopping-basket'));

        $this->assertStringContainsString('<i class="fa fa-shopping-basket"></i>', $html,
            'the cart item does not render its own chosen icon');
        $this->assertStringNotContainsString('<svg', $html,
            'the cart item still renders the fixed SVG alongside (or instead of) the chosen icon');
    }

    public function test_a_custom_icon_is_used_for_the_search_item(): void
    {
        $html = lazy_render_special_menu_item($this->specialItem('special_search', 'fa fa-magnifying-glass-plus'));

        $this->assertStringContainsString('<i class="fa fa-magnifying-glass-plus"></i>', $html,
            'the search item does not render its own chosen icon');
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_a_custom_icon_is_used_for_the_wishlist_item(): void
    {
        $html = lazy_render_special_menu_item($this->specialItem('special_wishlist', 'fa fa-heart-circle-plus'));

        $this->assertStringContainsString('<i class="fa fa-heart-circle-plus"></i>', $html,
            'the wishlist item does not render its own chosen icon');
        $this->assertStringNotContainsString('<svg', $html);
    }

    /** An item nobody has ever picked an icon for must look exactly as it did before. */
    public function test_the_default_svg_is_kept_when_no_icon_is_chosen(): void
    {
        foreach (['special_cart', 'special_search', 'special_wishlist'] as $type) {
            $html = lazy_render_special_menu_item($this->specialItem($type, null));

            $this->assertStringContainsString('<svg', $html,
                "{$type} with no chosen icon no longer falls back to its default SVG");
        }
    }

    /** An icon string reaches the page as literal HTML class text — it must be escaped. */
    public function test_the_chosen_icon_is_escaped(): void
    {
        $html = lazy_render_special_menu_item($this->specialItem('special_cart', '"><script>alert(1)</script>'));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html,
            'a menu item icon value can break out of its attribute and inject markup');
    }

    /**
     * The count badge's background was hardcoded to #0091ea — which happens to be the
     * *default* Primary Color, so a site running on the default looked correctly themed by
     * coincidence and a site that changed it in the Customizer got left behind, same as the
     * header's own cart badge would have if it used a fixed colour instead of `bg-primary`.
     */
    public function test_the_badge_defaults_to_the_stock_primary_colour(): void
    {
        $html = lazy_render_special_menu_item($this->specialItem('special_cart', null));

        $this->assertStringContainsString('background:#0091ea', $html,
            'a site that has never touched the Customizer must still see the original badge colour');
    }

    public function test_the_badge_follows_the_customizer_primary_colour(): void
    {
        $this->setCmsOptions(['theme_primary_color' => '#ff4500']);

        $html = lazy_render_special_menu_item($this->specialItem('special_cart', null));

        $this->assertStringContainsString('background:#ff4500', $html,
            'the badge did not pick up the Customizer\'s Primary Color');
        $this->assertStringNotContainsString('background:#0091ea', $html,
            'the badge is still hardcoded to the default colour instead of reading the Customizer setting');
    }
}
