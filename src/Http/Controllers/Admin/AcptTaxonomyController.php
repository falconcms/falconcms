<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use FalconCms\Core\Models\CustomTaxonomy;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\Menu;

class AcptTaxonomyController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomTaxonomy::query();
        
        if ($request->filled('s')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->s . '%')
                  ->orWhere('slug', 'like', '%' . $request->s . '%');
            });
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $query->where('is_active', 1)->withoutTrashed();
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0)->withoutTrashed();
        } elseif ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        $taxonomies = $query->latest()->paginate(10)->withQueryString();
        
        $allCount = CustomTaxonomy::withoutTrashed()->count();
        $activeCount = CustomTaxonomy::withoutTrashed()->where('is_active', 1)->count();
        $inactiveCount = CustomTaxonomy::withoutTrashed()->where('is_active', 0)->count();
        $trashCount = CustomTaxonomy::onlyTrashed()->count();

        return view('falcon-cms::admin.acpt.taxonomies.index', compact('taxonomies', 'allCount', 'activeCount', 'inactiveCount', 'trashCount'));
    }

    public function create()
    {
        $postTypes = PostType::where('is_builtin', false)->get();
        return view('falcon-cms::admin.acpt.taxonomies.create', compact('postTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'plural_label'   => 'required|string|max:255',
            'singular_label' => 'required|string|max:255',
            'taxonomy_key'   => 'required|string|max:32|unique:custom_taxonomies,slug',
            'post_types'     => 'nullable|array',
            'hierarchical'   => 'required|in:0,1',
            'description'    => 'nullable|string',
        ]);

        $taxonomy = CustomTaxonomy::create([
            'name'          => $request->plural_label,
            'singular_name' => $request->singular_label,
            'slug'          => $request->taxonomy_key,
            'post_types'    => $request->post_types ?? [],
            'hierarchical'  => (bool) $request->hierarchical,
            'description'   => $request->description,
            'is_active'     => true,
        ]);

        $this->syncTaxonomyMenus($taxonomy);

        return redirect()->route('admin.acpt.taxonomies.index')->with('success', 'Taxonomy created successfully!');
    }

    public function edit($id)
    {
        $taxonomy = CustomTaxonomy::findOrFail($id);
        $postTypes = PostType::where('is_builtin', false)->get();
        return view('falcon-cms::admin.acpt.taxonomies.edit', compact('taxonomy', 'postTypes'));
    }

    public function update(Request $request, $id)
    {
        $taxonomy = CustomTaxonomy::findOrFail($id);

        $request->validate([
            'plural_label'   => 'required|string|max:255',
            'singular_label' => 'required|string|max:255',
            'taxonomy_key'   => 'required|string|max:32|unique:custom_taxonomies,slug,' . $taxonomy->id,
            'post_types'     => 'nullable|array',
            'hierarchical'   => 'required|in:0,1',
            'description'    => 'nullable|string',
        ]);

        $this->removeTaxonomyMenus($taxonomy);

        $taxonomy->update([
            'name'          => $request->plural_label,
            'singular_name' => $request->singular_label,
            'slug'          => $request->taxonomy_key,
            'post_types'    => $request->post_types ?? [],
            'hierarchical'  => (bool) $request->hierarchical,
            'description'   => $request->description,
        ]);

        $this->syncTaxonomyMenus($taxonomy->fresh());

        return redirect()->route('admin.acpt.taxonomies.index')->with('success', 'Taxonomy updated successfully!');
    }

    public function destroy($id)
    {
        $taxonomy = CustomTaxonomy::findOrFail($id);
        $this->removeTaxonomyMenus($taxonomy);
        $taxonomy->delete();
        return redirect()->route('admin.acpt.taxonomies.index')->with('success', 'Taxonomy deleted.');
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action');
        if (empty($action) || $action === 'none') {
            $action = $request->input('action2');
        }
        $ids = $request->input('taxonomies', []);

        if (empty($ids) || empty($action) || $action === 'none') {
            return redirect()->back()->with('success', 'No action or taxonomies selected.');
        }

        if ($action === 'trash') {
            $taxonomies = CustomTaxonomy::whereIn('id', $ids)->get();
            foreach ($taxonomies as $taxonomy) {
                $this->removeTaxonomyMenus($taxonomy);
                $taxonomy->delete();
            }
            return redirect()->back()->with('success', 'Selected Taxonomies moved to trash.');
        }

        if ($action === 'restore') {
            $taxonomies = CustomTaxonomy::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($taxonomies as $taxonomy) {
                $taxonomy->restore();
                if ($taxonomy->is_active) {
                    $this->syncTaxonomyMenus($taxonomy);
                }
            }
            return redirect()->back()->with('success', 'Selected Taxonomies restored.');
        }

        if ($action === 'delete') {
            $taxonomies = CustomTaxonomy::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($taxonomies as $taxonomy) {
                $this->removeTaxonomyMenus($taxonomy);
                $taxonomy->forceDelete();
            }
            return redirect()->back()->with('success', 'Selected Taxonomies permanently deleted.');
        }

        if ($action === 'deactivate') {
            $taxonomies = CustomTaxonomy::whereIn('id', $ids)->get();
            foreach ($taxonomies as $taxonomy) {
                $taxonomy->is_active = 0;
                $taxonomy->save();
                $this->removeTaxonomyMenus($taxonomy);
            }
            return redirect()->back()->with('success', 'Selected Taxonomies deactivated.');
        }

        if ($action === 'activate') {
            $taxonomies = CustomTaxonomy::whereIn('id', $ids)->get();
            foreach ($taxonomies as $taxonomy) {
                $taxonomy->is_active = 1;
                $taxonomy->save();
                $this->syncTaxonomyMenus($taxonomy);
            }
            return redirect()->back()->with('success', 'Selected Taxonomies activated.');
        }

        return redirect()->back();
    }

    public function syncTaxonomyMenus(CustomTaxonomy $taxonomy): void
    {
        $assignedSlugs = is_array($taxonomy->post_types) ? $taxonomy->post_types : [];

        foreach ($assignedSlugs as $cptSlug) {
            $postType = PostType::where('slug', $cptSlug)->first();
            if (!$postType) continue;

            $cptMenu = Menu::where(function($q) use ($postType, $cptSlug) {
                    $q->where('title', $postType->name)
                      ->orWhere('route', 'like', '%/admin/posts?type=' . $cptSlug . '%')
                      ->orWhere('route', 'like', '%posts?type=' . $cptSlug . '%');
                })
                ->whereNull('parent_id')
                ->first();

            if (!$cptMenu) continue;

            $correctRoute = '/admin/acpt/tax-terms/' . $taxonomy->slug . '?cpt=' . $cptSlug;

            $existing = Menu::where('parent_id', $cptMenu->id)
                ->where('title', $taxonomy->name)
                ->first();

            if (!$existing) {
                $maxOrder = Menu::where('parent_id', $cptMenu->id)->max('order') ?? 2;
                Menu::create([
                    'parent_id' => $cptMenu->id,
                    'title'     => $taxonomy->name,
                    'route'     => $correctRoute,
                    'order'     => $maxOrder + 1,
                ]);
            } else {
                if ($existing->route !== $correctRoute) {
                    $existing->update(['route' => $correctRoute]);
                }
            }
        }
    }

    public function removeTaxonomyMenus(CustomTaxonomy $taxonomy): void
    {
        Menu::where('title', $taxonomy->name)
            ->whereNotNull('parent_id')
            ->where(function($q) use ($taxonomy) {
                $q->where('route', 'like', '%/acpt/taxonomies/' . $taxonomy->slug . '%')
                  ->orWhere('route', 'like', '%/acpt/tax-terms/' . $taxonomy->slug . '%')
                  ->orWhere('route', 'like', '%/acpt/taxonomies/' . $taxonomy->slug . '%');
            })
            ->delete();
    }
}
