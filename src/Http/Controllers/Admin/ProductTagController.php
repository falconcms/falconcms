<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use FalconCms\Core\Models\ProductTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductTagController extends Controller
{
    public function index(Request $request)
    {
        $lang = $request->query('lang');
        $query = ProductTag::withCount('posts');

        if ($lang && $lang !== 'all') {
            $query->where('lang_code', $lang);
        }

        $query->latest();

        if ($request->has('s')) {
            $query->where('name', 'like', '%' . $request->s . '%');
        }

        $tags = $query->paginate(10);
        return view('falcon-cms::admin.product-tags.index', compact('tags'));
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action') !== '-1' ? $request->input('action') : $request->input('action2');
        $ids = $request->input('ids');

        if (($action === 'delete') && !empty($ids)) {
            ProductTag::whereIn('id', $ids)->delete();
            return back()->with('success', 'Selected tags deleted.');
        }

        return back()->with('error', 'Please select an action and at least one item.');
    }

    public function store(Request $request)
    {
        $lang = $request->input('lang_code', app()->getLocale());
        $baseSlug = $request->slug ?: $request->name;
        $request->merge([
            'slug' => ProductTag::generateUniqueSlug($baseSlug, 0, $lang),
            'lang_code' => $lang
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lang_code' => 'required|string|max:10',
            'origin_id' => 'nullable|integer',
        ]);

        $tag = ProductTag::create($validated);
        falcon_log_activity('created', "Created a new product tag: {$tag->name}", $tag);

        return redirect()->route('admin.product-tags.index')->with('success', 'Tag added.');
    }

    public function edit(ProductTag $product_tag)
    {
        $tag = $product_tag;
        return view('falcon-cms::admin.product-tags.edit', compact('tag'));
    }

    public function update(Request $request, ProductTag $product_tag)
    {
        $tag = $product_tag;
        $baseSlug = $request->slug ?: $request->name;
        $request->merge(['slug' => ProductTag::generateUniqueSlug($baseSlug, $tag->id)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'lang_code' => 'required|string|max:10',
        ]);

        $tag->update($validated);

        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $targetLang) {
                $exists = ProductTag::where('origin_id', $tag->id)->where('lang_code', $targetLang)->exists();
                if ($exists) continue;

                $clone = $tag->replicate();
                $clone->lang_code = $targetLang;
                $clone->origin_id = $tag->id;
                $clone->name = falcon_translate($tag->name, $targetLang);
                $clone->slug = ProductTag::generateUniqueSlug($clone->name, 0, $targetLang);
                if ($tag->description) {
                    $clone->description = falcon_translate($tag->description, $targetLang);
                }
                $clone->save();
            }
        }

        falcon_log_activity('updated', "Updated product tag: {$tag->name}", $tag);

        return redirect()->route('admin.product-tags.index')->with('success', 'Tag updated.');
    }

    public function destroy(ProductTag $product_tag)
    {
        $name = $product_tag->name;
        $product_tag->delete();
        falcon_log_activity('deleted', "Deleted product tag: {$name}", $product_tag);
        return redirect()->route('admin.product-tags.index')->with('success', 'Tag deleted.');
    }

    public function ajax(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $slug = ProductTag::generateUniqueSlug($request->name);
        $tag = ProductTag::create([
            'name' => $request->name,
            'slug' => $slug,
        ]);
        return response()->json($tag);
    }
}
