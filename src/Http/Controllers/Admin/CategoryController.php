<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use FalconCms\Core\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->query('lang');
        $query = Category::withCount('posts');
        
        if ($lang && $lang !== 'all') {
            $query->where('lang_code', $lang);
        }
        
        $query->latest();
        
        if ($request->has('s')) {
            $query->where('name', 'like', '%' . $request->s . '%');
            $categories = $query->paginate(10);
        } else {
            $allCategories = $query->get();
            $tree = collect();
            $visitedIds = [];

            $buildTree = function($parentId, $level) use (&$buildTree, $allCategories, &$tree, &$visitedIds) {
                foreach ($allCategories->where('parent_id', $parentId) as $cat) {
                    if (in_array($cat->id, $visitedIds)) continue; // Prevent infinite loops
                    $visitedIds[] = $cat->id;
                    $cat->level = $level;
                    $tree->push($cat);
                    $buildTree($cat->id, $level + 1);
                }
            };
            
            // Build tree starting from root categories
            $buildTree(null, 0);

            // If there are still categories not in the tree (orphans or circular loops)
            if ($tree->count() < $allCategories->count()) {
                $orphans = $allCategories->whereNotIn('id', $visitedIds);
                foreach ($orphans as $orphan) {
                    if (in_array($orphan->id, $visitedIds)) continue;
                    $orphan->level = 0; // Show orphans at root level
                    $tree->push($orphan);
                    $visitedIds[] = $orphan->id;
                    $buildTree($orphan->id, 1);
                }
            }

            $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
            $perPage = 10;
            $fullTree = $tree;
            $categories = new \Illuminate\Pagination\LengthAwarePaginator(
                $tree->forPage($page, $perPage),
                $tree->count(),
                $perPage,
                $page,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        }

        return view('falcon-cms::admin.categories.index', compact('categories', 'fullTree'));
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action') !== '-1' ? $request->input('action') : $request->input('action2');
        $ids = $request->input('ids');

        if (($action === 'delete') && !empty($ids)) {
            Category::whereIn('id', $ids)->delete();
            return back()->with('success', 'Selected categories deleted.');
        }

        return back()->with('error', 'Please select an action and at least one item.');
    }

    public function store(Request $request)
    {
        $lang = $request->input('lang_code');
        if (!$lang || $lang === 'all') {
            $lang = app()->getLocale();
        }
        
        $baseSlug = $request->slug ?: $request->name;
        $request->merge([
            'slug' => Category::generateUniqueSlug($baseSlug, 0, $lang),
            'lang_code' => $lang
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'lang_code' => 'required|string|max:10',
            'origin_id' => 'nullable|integer',
        ]);

        $category = Category::create($validated);
        lazy_log_activity('created', "Created a new category: {$category->name}", $category);

        return redirect()->route('admin.categories.index', ['type' => 'post'])->with('success', 'Category added.');
    }

    public function edit(Category $category)
    {
        $allCategories = Category::where('lang_code', $category->lang_code)
            ->where('id', '!=', $category->id)
            ->latest()
            ->get();
        $fullTree = collect();
        $visitedIds = [];

        $buildTree = function($parentId, $level) use (&$buildTree, $allCategories, &$fullTree, &$visitedIds) {
            foreach ($allCategories->where('parent_id', $parentId) as $cat) {
                if (in_array($cat->id, $visitedIds)) continue;
                $visitedIds[] = $cat->id;
                $cat->level = $level;
                $fullTree->push($cat);
                $buildTree($cat->id, $level + 1);
            }
        };
        $buildTree(null, 0);

        return view('falcon-cms::admin.categories.edit', compact('category', 'fullTree'));
    }

    public function update(Request $request, Category $category)
    {
        $baseSlug = $request->slug ?: $request->name;
        $request->merge(['slug' => Category::generateUniqueSlug($baseSlug, $category->id)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ($value == $category->id) {
                        $fail('A category cannot be its own parent.');
                        return;
                    }
                    if ($value) {
                        $parent = \FalconCms\Core\Models\Category::find($value);
                        while ($parent) {
                            if ($parent->id == $category->id) {
                                $fail('Circular reference detected: The selected parent is already a sub-category of this category.');
                                break;
                            }
                            $parent = $parent->parent;
                        }
                    }
                },
            ],
            'description' => 'nullable|string',
            'lang_code' => 'required|string|max:10',
        ]);

        $category->update($validated);

        // Multilingual Copy Logic
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $targetLang) {
                $exists = Category::where('origin_id', $category->id)->where('lang_code', $targetLang)->exists();
                if ($exists) continue;

                $clone = $category->replicate();
                $clone->lang_code = $targetLang;
                $clone->origin_id = $category->id;
                
                $clone->name = lazy_translate($category->name, $targetLang);
                $clone->slug = Category::generateUniqueSlug($clone->name, 0, $targetLang);
                if ($category->description) {
                    $clone->description = lazy_translate($category->description, $targetLang);
                }
                $clone->save();
            }
        }

        lazy_log_activity('updated', "Updated category: {$category->name}", $category);

        return redirect()->route('admin.categories.index', ['type' => 'post'])->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $name = $category->name;
        $category->delete();
        lazy_log_activity('deleted', "Deleted category: {$name}", $category);
        return redirect()->route('admin.categories.index', ['type' => 'post'])->with('success', 'Category deleted.');
    }
    public function ajax(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'lang_code' => 'nullable|string'
        ]);
        
        $lang = $request->lang_code ?: app()->getLocale();
        $slug = Category::generateUniqueSlug($request->name, 0, $lang);
        $category = Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'parent_id' => $request->parent_id,
            'lang_code' => $lang
        ]);
        return response()->json($category);
    }
}
