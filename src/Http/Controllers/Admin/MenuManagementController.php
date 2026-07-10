<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuManagementController extends Controller
{
    public function index(Request $request)
    {
        $menus = NavigationMenu::all();
        $menu = null;

        if ($request->filled('menu')) {
            $menu = NavigationMenu::with(['allItems'])->find($request->menu);
        } elseif ($menus->isNotEmpty()) {
            $menu = $menus->first()->load(['allItems']);
        }

        $pages = Post::where('type', 'page')->where('status', 'published')->latest()->take(20)->get();
        $posts = Post::where('type', 'post')->where('status', 'published')->latest()->take(20)->get();
        $categories = Category::all();

        // Dynamic CPTs for Menu Builder (Only Active, excluding static 'post' and 'page')
        $customPostTypes = \FalconCms\Core\Models\PostType::where('is_active', true)
            ->whereNotIn('slug', ['post', 'page'])
            ->get();
        
        $cptData = [];
        foreach ($customPostTypes as $type) {
            $cptData[] = [
                'key' => 'cpt_' . $type->slug,
                'label' => $type->name,
                'items' => Post::where('type', $type->slug)->where('status', 'published')->latest()->take(20)->get(),
                'type' => $type->slug
            ];
        }

        // Dynamic Custom Taxonomies for Menu Builder (Grouped by Active CPTs)
        $customTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)
            ->where('hierarchical', true)
            ->get();
        $taxonomyData = [];
        
        $allPostTypes = \FalconCms\Core\Models\PostType::where('is_active', true)->get();
        
        foreach ($allPostTypes as $pt) {
            foreach ($customTaxonomies as $tax) {
                if (is_array($tax->post_types) && in_array($pt->slug, $tax->post_types)) {
                    $taxonomyData[] = [
                        'key' => 'tax_' . $pt->slug . '_' . $tax->slug,
                        'label' => $tax->name,
                        'items' => \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $tax->slug)->orderBy('name')->get(),
                        'slug' => $tax->slug
                    ];
                }
            }
        }

        // Pre-build FLAT JSON (depth-based) to avoid Blade parsing issues
        $menuItemsJson = '[]';
        if ($menu) {
            // Collect all object_ids grouped by type to detect orphans efficiently
            $allItems = $menu->allItems->flatMap(function($item) {
                return collect([$item])->concat($item->children->flatMap(fn($c) => collect([$c])->concat($c->children)));
            });

            $postIds     = $allItems->whereNotIn('type', ['category', 'custom'])->pluck('object_id')->filter()->unique()->values()->all();
            $categoryIds = $allItems->where('type','category')->pluck('object_id')->filter()->unique()->values()->all();

            $postsData = $postIds ? \FalconCms\Core\Models\Post::withTrashed()->whereIn('id', $postIds)->get(['id', 'status', 'deleted_at'])->keyBy('id') : collect();
            
            // Fetch all terms and their taxonomies for status check
            $termsData = $categoryIds ? \FalconCms\Core\Models\TaxonomyTerm::whereIn('id', $categoryIds)->get()->keyBy('id') : collect();
            $standardCatsData = $categoryIds ? \FalconCms\Core\Models\Category::whereIn('id', $categoryIds)->get()->keyBy('id') : collect();
            $activeTaxSlugs = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->pluck('slug')->toArray();

            $buildItem = function($item, $depth) use ($postsData, $termsData, $standardCatsData, $activeTaxSlugs) {
                $type      = $item->type ?? 'custom';
                $objectId  = $item->object_id ? (string)$item->object_id : null;
                $orphaned  = false;
                $isDraft   = false;
                $isTrashed = false;
                $isInactiveTax = false;

                if ($type !== 'category' && $type !== 'custom') {
                    if ($objectId) {
                        $post = $postsData->get($objectId);
                        if (!$post) {
                            $orphaned = true;
                        } else {
                            if ($post->deleted_at) $isTrashed = true;
                            if ($post->status === 'draft') $isDraft = true;
                        }
                    }
                } elseif ($type === 'category' && $objectId) {
                    $term = $termsData->get($objectId);
                    $standardCat = $standardCatsData->get($objectId);

                    if (!$term && !$standardCat) {
                        $orphaned = true;
                    } else {
                        // If it's a custom taxonomy term, check if its taxonomy is active
                        if ($term) {
                            if (!in_array($term->taxonomy_slug, $activeTaxSlugs) && $term->taxonomy_slug !== 'category') {
                                $isInactiveTax = true;
                            }
                        }
                        // Standard categories are always considered active unless deleted
                    }
                }

                // Determine source label for categories/taxonomies
                $sourceLabel = null;
                if ($type === 'category' && $objectId) {
                    $term = $termsData->get($objectId);
                    if ($term && $term->taxonomy_slug !== 'category') {
                        $tax = \FalconCms\Core\Models\CustomTaxonomy::where('slug', $term->taxonomy_slug)->first();
                        $pts = $tax ? $tax->post_types : null;
                        $ptSlug = is_array($pts) ? reset($pts) : null;
                        $pt = \FalconCms\Core\Models\PostType::where('slug', $ptSlug)->first();
                        if ($pt && $tax) {
                            $sourceLabel = $pt->singular_name . ' ' . $tax->singular_name;
                        }
                    }
                }

                return [
                    'id'          => (string)$item->id,
                    'title'       => $item->title,
                    'url'         => $item->url ?? '#',
                    'type'        => $type,
                    'object_id'   => $objectId,
                    'icon'        => $item->icon ?? '',
                    'show_only_icon' => (bool)($item->show_only_icon ?? false),
                    'target'      => $item->target ?? '_self',
                    'mega_menu_id' => $item->mega_menu_id ?? null,
                    'depth'       => $depth,
                    'orphaned'    => $orphaned || $isTrashed || $isInactiveTax,
                    'is_draft'    => $isDraft,
                    'is_inactive_tax' => $isInactiveTax,
                    'source_label' => $sourceLabel
                ];
            };

            $flat     = [];
            $topItems = $menu->allItems->where('parent_id', null)->sortBy('order')->values();
            foreach ($topItems as $item) {
                $flat[] = $buildItem($item, 0);
                foreach ($item->children->sortBy('order') as $child) {
                    $flat[] = $buildItem($child, 1);
                    foreach ($child->children->sortBy('order') as $grandchild) {
                        $flat[] = $buildItem($grandchild, 2);
                    }
                }
            }
            $menuItemsJson = json_encode($flat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        }

        $productCategories = \FalconCms\Core\Models\ProductCategory::orderBy('name')->get();

        return view('falcon-cms::admin.menus.index', compact('menus', 'menu', 'pages', 'posts', 'categories', 'menuItemsJson', 'cptData', 'taxonomyData', 'productCategories'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        
        $isHeader = $request->has('is_header');
        $isFooter = $request->has('is_footer');

        if ($isHeader) {
            NavigationMenu::where('is_header', true)->update(['is_header' => false]);
        }
        if ($isFooter) {
            NavigationMenu::where('is_footer', true)->update(['is_footer' => false]);
        }

        $menu = NavigationMenu::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'is_header' => $isHeader,
            'is_footer' => $isFooter,
        ]);

        falcon_log_activity('created', "Created a new menu: {$menu->name}", $menu);

        forget_nav_menu_cache();
        return redirect()->route('admin.menus.index', ['menu' => $menu->id])->with('success', 'Menu created successfully.');
    }

    public function update(Request $request, $id)
    {
        $menu = NavigationMenu::findOrFail($id);

        $isHeader = $request->has('is_header');
        $isFooter = $request->has('is_footer');

        if ($isHeader) {
            NavigationMenu::where('is_header', true)->update(['is_header' => false]);
        }
        if ($isFooter) {
            NavigationMenu::where('is_footer', true)->update(['is_footer' => false]);
        }

        $menu->update([
            'name' => $request->filled('name') ? $request->name : $menu->name,
            'is_header' => $isHeader,
            'is_footer' => $isFooter,
        ]);

        if ($request->has('menu_items')) {
            $items = json_decode($request->menu_items, true);

            // Hard-delete ALL existing items for this menu using direct DB query
            NavigationMenuItem::where('navigation_menu_id', $menu->id)->delete();

            // Only save if there are items to save
            if (is_array($items) && count($items) > 0) {
                $this->saveItems($menu->id, $items);
            }
        }

        forget_nav_menu_cache();
        falcon_log_activity('updated', "Updated menu structure: {$menu->name}", $menu);

        return redirect()
            ->route('admin.menus.index', ['menu' => $menu->id])
            ->with('success', 'Menu saved successfully.');
    }

    private function saveItems($menuId, $items, $parentId = null)
    {
        foreach ($items as $index => $item) {
            $newItem = NavigationMenuItem::create([
                'navigation_menu_id' => $menuId,
                'parent_id'   => $parentId,
                'title'       => $item['title'] ?? 'Item',
                'url'         => $item['url'] ?? '#',
                'type'        => $item['type'] ?? 'custom',
                'object_id'   => $item['object_id'] ?? null,
                'icon'        => $item['icon'] ?? null,
                'show_only_icon' => !empty($item['show_only_icon']),
                'target'      => $item['target'] ?? '_self',
                'mega_menu_id' => $item['mega_menu_id'] ?? null,
                'order'       => $index,
            ]);

            if (!empty($item['children'])) {
                $this->saveItems($menuId, $item['children'], $newItem->id);
            }
        }
    }

    public function destroy($id)
    {
        $menu = NavigationMenu::findOrFail($id);
        $name = $menu->name;
        $menu->delete();
        forget_nav_menu_cache();
        falcon_log_activity('deleted', "Deleted menu: {$name}");
        return redirect()->route('admin.menus.index')->with('success', 'Menu deleted.');
    }

    public function duplicate($id)
    {
        $menu = NavigationMenu::findOrFail($id);

        // The copy is a plain menu — it must never inherit the header/footer slot
        // (only one menu can hold each, and duplicating shouldn't steal it).
        $newMenu = NavigationMenu::create([
            'name'      => $menu->name . ' (Copy)',
            'slug'      => Str::slug($menu->name) . '-copy-' . Str::lower(Str::random(5)),
            'is_header' => false,
            'is_footer' => false,
        ]);

        // Recreate every item, preserving the full parent/child hierarchy & order.
        $this->copyItems($menu->id, $newMenu->id, null, null);

        forget_nav_menu_cache();
        falcon_log_activity('created', "Duplicated menu: {$menu->name} → {$newMenu->name}", $newMenu);

        return redirect()
            ->route('admin.menus.index', ['menu' => $newMenu->id])
            ->with('success', 'Menu duplicated successfully.');
    }

    private function copyItems($sourceMenuId, $newMenuId, $sourceParentId, $newParentId)
    {
        $query = NavigationMenuItem::where('navigation_menu_id', $sourceMenuId);
        $query = is_null($sourceParentId)
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $sourceParentId);

        foreach ($query->orderBy('order')->get() as $item) {
            // replicate() copies every actual column (schema-agnostic), so new
            // item fields stay in sync automatically as the model evolves.
            $newItem = $item->replicate();
            $newItem->navigation_menu_id = $newMenuId;
            $newItem->parent_id          = $newParentId;
            $newItem->save();

            // Recurse into this item's children.
            $this->copyItems($sourceMenuId, $newMenuId, $item->id, $newItem->id);
        }
    }
}
