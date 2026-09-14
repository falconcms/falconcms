<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Menu;
use Illuminate\Support\Collection;

/**
 * Where a top-level admin sidebar menu sits.
 *
 * Every custom post type used to pick its own `order` from a magic band — `40 + id` in three
 * places, `60 + id` in three others — and the two bands overlapped the pair that has to stay
 * together, so a post type with the wrong id silently wedged itself between Shop and Products.
 * Renumbering the pair only ever moved the collision; the bands were the bug.
 *
 * So nothing computes an order from an id any more. A menu is inserted *after* another menu:
 * the position is taken from the neighbour that is actually there, and everything below it
 * shifts down by one. Orders therefore stay unique, and a locked block (below) is treated as a
 * single item, so "after Shop" means after the whole Shop → Products pair rather than inside it.
 */
class MenuPlacement
{
    /**
     * Menus that belong together, in order, and may never be split.
     *
     * Products is the Shop's own post type: reading them apart makes neither one make sense,
     * so the sidebar always shows Shop with Products directly beneath it.
     */
    public const LOCKED_BLOCKS = [
        ['Shop', 'Products'],
    ];

    /** Gap left between menus when a whole group has to be renumbered. */
    private const STEP = 1;

    /**
     * The menus a new item can be placed after, as `id => label`, in sidebar order.
     *
     * Menus inside a locked block other than its head are left out: they are not a position of
     * their own, since anything "after Products" is really after the Shop block.
     */
    public static function anchorOptions(?int $excludeMenuId = null): Collection
    {
        $locked = array_merge(...array_map(fn ($b) => array_slice($b, 1), self::LOCKED_BLOCKS));

        return self::topLevel()
            ->reject(fn ($m) => $excludeMenuId && (int) $m->id === (int) $excludeMenuId)
            ->reject(fn ($m) => in_array($m->title, $locked, true))
            ->mapWithKeys(fn ($m) => [$m->id => $m->title.($m->group && $m->group !== 'Main' ? ' — '.$m->group : '')]);
    }

    /**
     * Put `$menu` directly after `$anchorId`, or at the very bottom of its group when the
     * anchor is null, gone, or is the menu itself.
     *
     * The anchor's group wins: choosing an anchor under "System" moves the menu there too,
     * because a position that is not next to the thing it was placed after is not a position.
     */
    public static function placeAfter(Menu $menu, $anchorId = null): void
    {
        $anchor = $anchorId ? self::topLevel()->firstWhere('id', (int) $anchorId) : null;
        if ($anchor && (int) $anchor->id === (int) $menu->id) {
            $anchor = null;
        }

        $group = $anchor->group ?? $menu->group ?? 'Main';

        if (!$anchor) {
            self::appendTo($menu, $group);

            return;
        }

        // A locked block is one item: the insert goes below its last member, never inside it.
        $after = self::blockTail($anchor, $group);
        $target = (int) $after->order + self::STEP;

        Menu::whereNull('parent_id')
            ->where('group', $group)
            ->where('id', '!=', $menu->id)
            ->where('order', '>=', $target)
            ->increment('order', self::STEP);

        $menu->forceFill(['group' => $group, 'order' => $target])->save();
    }

    /** Move `$menu` to the bottom of `$group`. */
    public static function appendTo(Menu $menu, string $group = 'Main'): void
    {
        $max = (int) Menu::whereNull('parent_id')
            ->where('group', $group)
            ->where('id', '!=', $menu->id)
            ->max('order');

        $menu->forceFill(['group' => $group, 'order' => $max + self::STEP])->save();
    }

    /**
     * The order a brand-new menu should get when it is created before it can be placed —
     * the bottom of the group, so it is never born inside a locked block.
     */
    public static function nextOrder(string $group = 'Main'): int
    {
        return (int) Menu::whereNull('parent_id')->where('group', $group)->max('order') + self::STEP;
    }

    /**
     * The menu whose position a locked block ends at. For a menu that heads a block this is
     * its last present member; for anything else it is the menu itself.
     */
    private static function blockTail(Menu $anchor, string $group): Menu
    {
        foreach (self::LOCKED_BLOCKS as $block) {
            if ($anchor->title !== $block[0]) {
                continue;
            }
            $tail = self::topLevel()
                ->where('group', $group)
                ->filter(fn ($m) => in_array($m->title, $block, true))
                ->sortByDesc('order')
                ->first();

            return $tail ?: $anchor;
        }

        return $anchor;
    }

    /** Top-level menus in sidebar order. `id` breaks ties so the order is never arbitrary. */
    private static function topLevel(): Collection
    {
        return Menu::whereNull('parent_id')->orderBy('order')->orderBy('id')->get();
    }
}
