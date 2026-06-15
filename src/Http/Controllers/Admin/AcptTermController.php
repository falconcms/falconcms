<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use FalconCms\Core\Models\CustomTaxonomy;
use FalconCms\Core\Models\TaxonomyTerm;
use Illuminate\Support\Str;

class AcptTermController extends Controller
{
    public function index(Request $request, $taxonomySlug)
    {
        $lang = $request->query('lang');
        $taxonomy = CustomTaxonomy::where('slug', $taxonomySlug)->firstOrFail();
        $query = TaxonomyTerm::where('taxonomy_slug', $taxonomySlug);

        if ($lang && $lang !== 'all') {
            $query->where('lang_code', $lang);
        }
        
        if ($request->filled('cpt')) {
            $query->where('cpt_slug', $request->cpt);
        }

        if ($request->filled('s')) {
            $query->where('name', 'like', '%' . $request->s . '%');
            $terms = $query->withCount('posts')->latest()->paginate(10)->withQueryString();
            $fullTree = collect();
        } else {
            $allTerms = $query->withCount('posts')->latest()->get();
            $tree = collect();
            $visitedIds = [];

            $buildTree = function($parentId, $level) use (&$buildTree, $allTerms, &$tree, &$visitedIds) {
                foreach ($allTerms->where('parent_id', $parentId) as $term) {
                    if (in_array($term->id, $visitedIds)) continue;
                    $visitedIds[] = $term->id;
                    $term->level = $level;
                    $tree->push($term);
                    $buildTree($term->id, $level + 1);
                }
            };
            
            $buildTree(null, 0);

            // Handle orphans/loops
            if ($tree->count() < $allTerms->count()) {
                $orphans = $allTerms->whereNotIn('id', $visitedIds);
                foreach ($orphans as $orphan) {
                    if (in_array($orphan->id, $visitedIds)) continue;
                    $orphan->level = 0;
                    $tree->push($orphan);
                    $visitedIds[] = $orphan->id;
                    $buildTree($orphan->id, 1);
                }
            }

            $fullTree = $tree;
            $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
            $perPage = 10;
            $terms = new \Illuminate\Pagination\LengthAwarePaginator(
                $tree->forPage($page, $perPage),
                $tree->count(),
                $perPage,
                $page,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        }
        
        return view('falcon-cms::admin.acpt.taxonomies.terms.index', compact('taxonomy', 'terms', 'fullTree'));
    }

    public function store(Request $request, $taxonomySlug)
    {
        $taxonomy = CustomTaxonomy::where('slug', $taxonomySlug)->firstOrFail();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'cpt_slug' => 'nullable|string', // We'll try to fallback if null
        ]);

        $cptSlug = $request->cpt_slug;
        if (empty($cptSlug) && !empty($taxonomy->post_types)) {
            $cptSlug = $taxonomy->post_types[0];
        }

        if (empty($cptSlug)) {
            return redirect()->back()->with('error', 'Post type (CPT) is required to manage taxonomy terms.');
        }

        $lang = $request->input('lang_code', app()->getLocale());
        $slug = $request->slug ? TaxonomyTerm::generateUniqueSlug($request->slug, 0, $cptSlug, $lang) : TaxonomyTerm::generateUniqueSlug($request->name, 0, $cptSlug, $lang);

        TaxonomyTerm::create([
            'name' => $request->name,
            'slug' => $slug,
            'taxonomy_slug' => $taxonomySlug,
            'cpt_slug' => $cptSlug,
            'description' => $request->description,
            'parent_id' => $request->parent_id ?: null,
            'lang_code' => $lang,
            'origin_id' => $request->origin_id ?: null,
        ]);

        return redirect()->back()->with('success', 'Term added successfully!');
    }

    public function bulk(Request $request, $taxonomySlug)
    {
        $ids = $request->input('ids');
        $action = $request->input('action');

        \Illuminate\Support\Facades\Log::info("Bulk action: Action=$action, IDs=" . json_encode($ids));

        if (!$ids || !is_array($ids) || $action === '-1') {
            return redirect()->back()->with('error', 'Please select items and an action.');
        }

        if ($action === 'delete') {
            TaxonomyTerm::whereIn('id', $ids)->delete();
            return redirect()->back()->with('success', 'Selected terms deleted.');
        }

        return redirect()->back();
    }

    public function destroy($taxonomySlug, $id)
    {
        if (!is_numeric($id)) {
            return redirect()->back()->with('error', 'Invalid term ID.');
        }
        $term = TaxonomyTerm::findOrFail($id);
        $term->delete();
        return redirect()->back()->with('success', 'Term deleted successfully!');
    }

    public function edit($taxonomySlug, $id)
    {
        $taxonomy = CustomTaxonomy::where('slug', $taxonomySlug)->firstOrFail();
        $term = TaxonomyTerm::findOrFail($id);
        $allTerms = TaxonomyTerm::where('taxonomy_slug', $taxonomySlug)
            ->where('lang_code', $term->lang_code)
            ->where('id', '!=', $id)
            ->orderBy('name')
            ->get();
            
        $fullTree = collect();
        $visitedIds = [];
        $buildTree = function($parentId, $level) use (&$buildTree, $allTerms, &$fullTree, &$visitedIds) {
            foreach ($allTerms->where('parent_id', $parentId) as $t) {
                if (in_array($t->id, $visitedIds)) continue;
                $visitedIds[] = $t->id;
                $t->level = $level;
                $fullTree->push($t);
                $buildTree($t->id, $level + 1);
            }
        };
        $buildTree(null, 0);

        return view('falcon-cms::admin.acpt.taxonomies.terms.edit', compact('taxonomy', 'term', 'fullTree'));
    }

    public function update(Request $request, $taxonomySlug, $id)
    {
        $term = TaxonomyTerm::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
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
            'lang_code' => 'required|string|max:10',
        ]);

        $slug = $request->slug ? TaxonomyTerm::generateUniqueSlug($request->slug, $term->id, $term->cpt_slug, $request->lang_code) : $term->slug;

        $term->update([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'parent_id' => $request->parent_id ?: null,
            'lang_code' => $request->lang_code,
        ]);

        // Multilingual Copy Logic
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $targetLang) {
                $exists = TaxonomyTerm::where('origin_id', $term->id)->where('lang_code', $targetLang)->exists();
                if ($exists) continue;

                $clone = $term->replicate();
                $clone->lang_code = $targetLang;
                $clone->origin_id = $term->id;
                
                $clone->name = lazy_translate($term->name, $targetLang);
                $clone->slug = TaxonomyTerm::generateUniqueSlug($clone->name, 0, $term->cpt_slug, $targetLang);
                if ($term->description) {
                    $clone->description = lazy_translate($term->description, $targetLang);
                }
                $clone->save();
            }
        }

        return redirect()->route('admin.acpt.terms.index', $taxonomySlug)->with('success', 'Term updated successfully!');
    }

    public function ajax(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'taxonomy_slug' => 'required|string',
            'lang_code' => 'nullable|string'
        ]);

        $lang = $request->input('lang_code', app()->getLocale());
        $slug = TaxonomyTerm::generateUniqueSlug($request->name, 0, $request->cpt_slug, $lang);

        $term = TaxonomyTerm::create([
            'name' => $request->name,
            'slug' => $slug,
            'taxonomy_slug' => $request->taxonomy_slug,
            'cpt_slug' => $request->cpt_slug,
            'parent_id' => $request->parent_id,
            'lang_code' => $lang,
        ]);

        return response()->json($term);
    }
}
