<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\Post;

/**
 * Where "previous" and "next" come from.
 *
 * The links themselves are the easy part of this element. The question that decides
 * whether it is any good is where the ORDER comes from, and the obvious answer — let
 * the author type both links — is the one that does not survive contact with a real
 * documentation site: fifty pages is a hundred links to keep in step, and the first
 * time a section is reordered every one of them is wrong with nothing to say so.
 *
 * A site that has a documentation sidebar has already written the order down, in the
 * menu that draws it. So that is what this reads: the menu is flattened the way a
 * reader goes through it — each item, then its children, then the next item — the
 * current page is found in that list, and its neighbours are the answer. Reordering the
 * sidebar reorders every Previous and Next on the site, because there is only one list.
 *
 * Where there is no menu, pages of the same type are put in the order the type itself
 * implies. And either can be overridden per side, for the one page in a set that has
 * somewhere else to go.
 */
class PrevNext
{
    /** How a set of pages can be ordered when no menu says otherwise. */
    public const ORDERS = ['menu_order', 'date', 'title'];

    /**
     * Visual presets. As everywhere in the builder, a preset only supplies defaults and
     * every value stays editable.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            'cards' => [
                'name' => 'Cards',
                'bg' => '#FFFFFF', 'borderColor' => '#E2E8EE', 'borderWidth' => 1,
                'radius' => 10, 'padY' => 16, 'padX' => 18, 'gap' => 16,
                'labelColor' => '#79838F', 'labelSize' => 11,
                'titleColor' => '#171C23', 'titleSize' => 15, 'titleWeight' => '600',
                'arrowColor' => '#B9720F', 'hoverBorder' => '#B9720F',
            ],
            'minimal' => [
                'name' => 'Minimal',
                'bg' => 'transparent', 'borderColor' => '#E2E8EE', 'borderWidth' => 0,
                'radius' => 0, 'padY' => 8, 'padX' => 0, 'gap' => 20,
                'labelColor' => '#79838F', 'labelSize' => 11,
                'titleColor' => '#B9720F', 'titleSize' => 15, 'titleWeight' => '600',
                'arrowColor' => '#B9720F', 'hoverBorder' => 'transparent',
            ],
            'split' => [
                'name' => 'Split rule',
                'bg' => 'transparent', 'borderColor' => '#E2E8EE', 'borderWidth' => 1,
                'radius' => 0, 'padY' => 18, 'padX' => 0, 'gap' => 24,
                'labelColor' => '#79838F', 'labelSize' => 11,
                'titleColor' => '#171C23', 'titleSize' => 16, 'titleWeight' => '600',
                'arrowColor' => '#79838F', 'hoverBorder' => 'transparent',
            ],
            'soft' => [
                'name' => 'Soft fill',
                'bg' => '#F7F9FB', 'borderColor' => 'transparent', 'borderWidth' => 0,
                'radius' => 10, 'padY' => 16, 'padX' => 18, 'gap' => 14,
                'labelColor' => '#79838F', 'labelSize' => 11,
                'titleColor' => '#171C23', 'titleSize' => 15, 'titleWeight' => '600',
                'arrowColor' => '#B9720F', 'hoverBorder' => 'transparent',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function presetOptions(): array
    {
        return array_map(static fn ($p) => $p['name'], self::presets());
    }

    /**
     * One preset, always — an unknown key falls back rather than rendering unstyled.
     *
     * @return array<string, mixed>
     */
    public static function preset(?string $key): array
    {
        $all = self::presets();

        return $all[$key ?? ''] ?? $all['cards'];
    }

    /**
     * A menu, flattened into the order a reader goes through it.
     *
     * Depth first: an item, then everything under it, then the next item. That is what
     * a sidebar means — clicking down the list and continuing into each section — and
     * it is the only reading of a nested menu that produces a sequence at all.
     *
     * Items that do not go anywhere are left out. A parent whose only job is to open a
     * submenu is saved with "#" or nothing as its URL, and stepping onto it would take
     * the reader nowhere.
     *
     * @return array<int, array{title: string, url: string}>
     */
    public static function flatten(?int $menuId): array
    {
        if (!$menuId) {
            return [];
        }

        $menu = NavigationMenu::with(['allItems'])->find($menuId);
        if (!$menu) {
            return [];
        }

        $byParent = [];
        foreach ($menu->allItems as $item) {
            $byParent[$item->parent_id ?? 0][] = $item;
        }

        foreach ($byParent as &$group) {
            usort($group, static fn ($a, $b) => ($a->order ?? 0) <=> ($b->order ?? 0));
        }
        unset($group);

        $out = [];
        // Iterative rather than recursive: a menu whose parent_id points at itself, or
        // at one of its own descendants, is a cycle, and the seen list is what stops
        // that turning a bad row in the database into an exhausted stack.
        $seen = [];
        $walk = function ($parentId) use (&$walk, &$out, &$seen, $byParent) {
            foreach ($byParent[$parentId] ?? [] as $item) {
                if (isset($seen[$item->id])) {
                    continue;
                }
                $seen[$item->id] = true;

                $url = trim((string) ($item->url ?? ''));
                if ($url !== '' && $url !== '#') {
                    $out[] = ['title' => (string) $item->title, 'url' => $url];
                }

                $walk($item->id);
            }
        };
        $walk(0);

        return $out;
    }

    /**
     * The pages of one type, in the order that type implies.
     *
     * menu_order is the field an author actually drags things around with, so it leads;
     * pages that share a position fall back to the title, which at least is stable —
     * an order that changes between two page loads would move Previous and Next around
     * under the reader.
     *
     * @return array<int, array{title: string, url: string, id: int}>
     */
    public static function siblings(string $type, string $order = 'menu_order', string $direction = 'asc', ?string $lang = null): array
    {
        $query = Post::query()
            ->where('type', $type)
            ->where('status', 'published');

        if ($lang !== null && $lang !== '') {
            $query->where('lang_code', $lang);
        }

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        match ($order) {
            'date' => $query->orderBy('published_at', $direction)->orderBy('id', $direction),
            'title' => $query->orderBy('title', $direction)->orderBy('id', $direction),
            default => $query->orderBy('menu_order', $direction)->orderBy('title', 'asc')->orderBy('id', 'asc'),
        };

        // lang_code comes along because the permalink is built from it — without it
        // every URL on a translated site points at the default language.
        return $query->get(['id', 'title', 'slug', 'type', 'lang_code'])
            ->map(static fn ($p) => [
                'title' => (string) $p->title,
                'url' => self::urlFor($p),
                'id' => (int) $p->id,
            ])
            ->all();
    }

    /**
     * The neighbours of one entry in a list.
     *
     * The list is matched on path rather than on the whole URL, because a menu holds
     * whatever an editor pasted — absolute one day, relative the next — while the page
     * being rendered always knows its own. Comparing the two as strings almost never
     * matched.
     *
     * @param  array<int, array{title: string, url: string}>  $list
     * @return array{prev: array{title: string, url: string}|null, next: array{title: string, url: string}|null}
     */
    public static function neighbours(array $list, string $currentUrl): array
    {
        $list = array_values($list);
        $at = null;

        foreach ($list as $i => $entry) {
            if (falcon_same_page($entry['url'], $currentUrl)) {
                $at = $i;
                break;
            }
        }

        // A page that is not in the list has no neighbours in it. Guessing — the first
        // two entries, say — would put a Previous and Next on a page that is not part
        // of the sequence at all, which is worse than showing nothing.
        if ($at === null) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $list[$at - 1] ?? null,
            'next' => $list[$at + 1] ?? null,
        ];
    }

    /**
     * Everything the builder canvas needs to show the real links rather than a sample.
     *
     * The canvas cannot work the neighbours out for itself — the menus and the sibling
     * pages are database rows, and the ordering is this class. So the answers are worked
     * out here, once, for every source the author might switch to, and handed over as
     * JSON. That is a few hundred bytes and it means the preview is the page, rather
     * than a picture of what the page might look like.
     *
     * @return array<string, mixed>
     */
    public static function previewFor(?Post $post): array
    {
        if (!$post) {
            return ['menus' => [], 'types' => []];
        }

        $currentUrl = self::urlFor($post);

        $menus = [];
        try {
            foreach (NavigationMenu::all(['id']) as $menu) {
                $menus[(string) $menu->id] = self::neighbours(self::flatten((int) $menu->id), $currentUrl);
            }
        } catch (\Throwable $e) {
            $menus = [];
        }

        $types = [];
        try {
            foreach (self::ORDERS as $order) {
                foreach (['asc', 'desc'] as $direction) {
                    $types[$order.':'.$direction] = self::neighbours(
                        self::siblings((string) $post->type, $order, $direction, (string) $post->lang_code),
                        $currentUrl
                    );
                }
            }
        } catch (\Throwable $e) {
            $types = [];
        }

        return ['menus' => $menus, 'types' => $types];
    }

    /**
     * Resolve the pair for a page, from whichever source the element is set to.
     *
     * A link set by hand always wins its own side. That is the escape hatch for the one
     * page in a set that goes somewhere else — the last page of a tutorial pointing at
     * the reference, say — and it has to be per side, because wanting to override one
     * of them is not wanting to type both.
     *
     * @param  array<string, mixed>  $settings
     * @return array{prev: array{title: string, url: string}|null, next: array{title: string, url: string}|null}
     */
    public static function resolve(array $settings, ?Post $post): array
    {
        $source = $settings['source'] ?? 'menu';
        $pair = ['prev' => null, 'next' => null];

        if ($source !== 'manual' && $post) {
            $currentUrl = self::urlFor($post);

            if ($source === 'menu' && !empty($settings['menuId'])) {
                $pair = self::neighbours(self::flatten((int) $settings['menuId']), $currentUrl);
            } elseif ($source === 'type') {
                $pair = self::neighbours(
                    self::siblings(
                        (string) $post->type,
                        (string) ($settings['orderBy'] ?? 'menu_order'),
                        (string) ($settings['orderDir'] ?? 'asc'),
                        (string) $post->lang_code
                    ),
                    $currentUrl
                );
            }
        }

        foreach (['prev', 'next'] as $side) {
            $url = trim((string) ($settings[$side.'Url'] ?? ''));
            $title = trim((string) ($settings[$side.'Title'] ?? ''));

            if ($url !== '') {
                $pair[$side] = ['title' => $title !== '' ? $title : $url, 'url' => $url];
            } elseif ($title !== '' && $pair[$side]) {
                // A title on its own renames whatever was found, rather than replacing it.
                $pair[$side]['title'] = $title;
            }
        }

        return $pair;
    }

    /**
     * Does this page carry a Table of Contents?
     *
     * Previous/Next steps through a sequence of documentation pages, and what marks a
     * page as one of those is that it has a contents list. On a landing page there is no
     * sequence, so the element is inert there — and the page has to agree with the
     * builder about that, or an author sees it greyed out in one and rendered in the
     * other.
     *
     * The content is read as text rather than parsed. It is builder JSON on a saved page
     * and shortcodes on one that arrived from the editor, this is asked once per render,
     * and both spellings of the answer are unmistakable. Parsing either format properly
     * to find out whether a substring is in it would be work for no more certainty.
     */
    public static function pageHasToc(?Post $post): bool
    {
        $content = (string) ($post->content ?? '');

        if ($content === '') {
            return false;
        }

        return str_contains($content, '"type":"toc"')
            || str_contains($content, '"type": "toc"')
            || str_contains($content, '[falcon_toc');
    }

    /** A post's own URL, which is what the menu is matched against. */
    public static function urlFor(Post $post): string
    {
        try {
            return (string) get_falcon_permalink($post);
        } catch (\Throwable $e) {
            return '/'.ltrim((string) $post->slug, '/');
        }
    }
}
