<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use FalconCms\Core\Models\TaxonomyTerm;
use FalconCms\Core\Models\CustomTaxonomy;

class TaxonomyTermController extends Controller
{
    public function index(Request $request, $taxonomySlug)
    {
        $lang = $request->query('lang', app()->getLocale());
        $taxonomy = CustomTaxonomy::where('slug', $taxonomySlug)->firstOrFail();
        $cptSlug  = $request->query('cpt');
        
        $query = TaxonomyTerm::where('taxonomy_slug', $taxonomySlug)
            ->where('cpt_slug', $cptSlug)
            ->where('lang_code', $lang);

        if ($request->filled('s')) {
            $query->where('name', 'like', '%' . $request->s . '%');
        }

        $terms   = $query->withCount('posts')->latest()->paginate(20);
        $allTerms = TaxonomyTerm::where('taxonomy_slug', $taxonomySlug)
            ->where('cpt_slug', $cptSlug)
            ->latest()
            ->get();
            
        $fullParents = collect();
        $visitedIds = [];
        $buildTree = function($parentId, $level) use (&$buildTree, $allTerms, &$fullParents, &$visitedIds) {
            foreach ($allTerms->where('parent_id', $parentId) as $term) {
                if (in_array($term->id, $visitedIds)) continue;
                $visitedIds[] = $term->id;
                $term->level = $level;
                $fullParents->push($term);
                $buildTree($term->id, $level + 1);
            }
        };
        $buildTree(null, 0);

        return view('falcon-cms::admin.acpt.taxonomies.terms', compact('taxonomy', 'terms', 'fullParents', 'cptSlug'));
    }

    public function ajaxStore(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'taxonomy_slug' => 'required|string',
            'cpt_slug'      => 'required|string',
            'parent_id'     => 'nullable|exists:taxonomy_terms,id',
        ]);

        $taxonomySlug = $request->taxonomy_slug;
        $cptSlug      = $request->cpt_slug;
        $name         = trim($request->name);

        // Check if exists
        $term = TaxonomyTerm::where('taxonomy_slug', $taxonomySlug)
            ->where('cpt_slug', $cptSlug)
            ->where('name', $name)
            ->first();

        if ($term) {
            return response()->json($term);
        }

        $slug = TaxonomyTerm::generateUniqueSlug($name, 0, $cptSlug);

        $term = TaxonomyTerm::create([
            'taxonomy_slug' => $taxonomySlug,
            'cpt_slug'      => $cptSlug,
            'name'          => $name,
            'slug'          => $slug,
            'parent_id'     => $request->parent_id ?: null,
        ]);

        return response()->json($term);
    }

    public function store(Request $request, $taxonomySlug)
    {
        $cptSlug = $request->input('cpt_slug');

        $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:taxonomy_terms,id',
        ]);

        $lang = $request->input('lang_code', app()->getLocale());
        $baseName = $request->slug ? $request->slug : $request->name;
        $slug = TaxonomyTerm::generateUniqueSlug($baseName, 0, $cptSlug, $lang);

        TaxonomyTerm::create([
            'taxonomy_slug' => $taxonomySlug,
            'cpt_slug'      => $cptSlug,
            'name'          => $request->name,
            'slug'          => $slug,
            'description'   => $request->description,
            'parent_id'     => $request->parent_id ?: null,
            'lang_code'     => $lang,
            'origin_id'     => $request->origin_id ?: null,
        ]);

        return redirect()->back()->with('success', '"' . $request->name . '" added successfully.');
    }

    public function edit(Request $request, $taxonomySlug, $id)
    {
        $taxonomy = CustomTaxonomy::where('slug', $taxonomySlug)->firstOrFail();
        $term = TaxonomyTerm::findOrFail($id);
        $cptSlug = $request->query('cpt');

        $allTerms = TaxonomyTerm::where('taxonomy_slug', $taxonomySlug)
            ->where('cpt_slug', $cptSlug)
            ->where('id', '!=', $id)
            ->get();
            
        $fullParents = collect();
        $visitedIds = [];
        $buildTree = function($parentId, $level) use (&$buildTree, $allTerms, &$fullParents, &$visitedIds) {
            foreach ($allTerms->where('parent_id', $parentId) as $t) {
                if (in_array($t->id, $visitedIds)) continue;
                $visitedIds[] = $t->id;
                $t->level = $level;
                $fullParents->push($t);
                $buildTree($t->id, $level + 1);
            }
        };
        $buildTree(null, 0);

        return view('falcon-cms::admin.acpt.taxonomies.term_edit', compact('taxonomy', 'term', 'fullParents', 'cptSlug'));
    }

    public function update(Request $request, $taxonomySlug, $id)
    {
        $term = TaxonomyTerm::findOrFail($id);
        $cptSlug = $request->input('cpt_slug');

        $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'nullable|string|max:255',
            'parent_id' => [
                'nullable',
                'exists:taxonomy_terms,id',
                function ($attribute, $value, $fail) use ($term) {
                    if ($value == $term->id) {
                        $fail('A term cannot be its own parent.');
                        return;
                    }
                    if ($value) {
                        $parent = TaxonomyTerm::find($value);
                        while ($parent) {
                            if ($parent->id == $term->id) {
                                $fail('Circular reference detected: The selected parent is already a sub-term of this term.');
                                break;
                            }
                            $parent = $parent->parent;
                        }
                    }
                },
            ],
        ]);

        $baseName = $request->slug ? $request->slug : $request->name;
        $slug = TaxonomyTerm::generateUniqueSlug($baseName, $term->id, $cptSlug);

        $term->update([
            'name'        => $request->name,
            'slug'        => $slug,
            'description' => $request->description,
            'parent_id'   => $request->parent_id ?: null,
        ]);

        return redirect()->route('admin.acpt.terms.index', [$taxonomySlug, 'cpt' => $cptSlug])->with('success', 'Term updated successfully.');
    }

    public function destroy(Request $request, $taxonomySlug, $id)
    {
        $term = TaxonomyTerm::findOrFail($id);
        $term->delete();
        return redirect()->back()->with('success', 'Term deleted.');
    }

    public function bulk(Request $request, $taxonomySlug)
    {
        $action = $request->input('action');
        if (empty($action) || $action === '-1') {
            $action = $request->input('action2');
        }
        $ids = $request->input('ids', []);

        if ($action === 'delete' && !empty($ids)) {
            TaxonomyTerm::whereIn('id', $ids)->delete();
        }

        return redirect()->back()->with('success', 'Action applied.');
    }
}
