<?php

namespace Acme\CmsDashboard\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Acme\CmsDashboard\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Acme\CmsDashboard\Models\ProductData;
use Acme\CmsDashboard\Services\BuilderShortcodeConverter;

class PostController extends Controller
{
    public function builder($id)
    {
        $post = Post::findOrFail($id);
        $customElements = apply_lazy_filters('lazy_builder_elements', []);

        $bodyRaw    = get_cms_option('theme_typography_body');
        $headingRaw = get_cms_option('theme_typography_h1');
        $bodyFont    = is_array($bodyRaw)    ? $bodyRaw    : json_decode((string)$bodyRaw,    true);
        $headingFont = is_array($headingRaw) ? $headingRaw : json_decode((string)$headingRaw, true);
        $themeBodyFont    = $bodyFont['family']    ?? null;
        $themeHeadingFont = $headingFont['family'] ?? null;

        return view('cms-dashboard::admin.lazy-builder.index', compact(
            'post', 'customElements', 'themeBodyFont', 'themeHeadingFont'
        ));
    }

    public function saveBuilder(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        $post->update([
            'content' => json_encode($request->input('layout')),
            'editor_type' => 'builder'
        ]);

        clear_page_cache();

        return response()->json(['success' => true, 'message' => 'Page layout saved successfully.']);
    }

    public function previewBuilder($id)
    {
        $post = Post::findOrFail($id);
        // This would typically return a front-end view that renders the builder JSON
        return view('cms-dashboard::admin.lazy-builder.preview', compact('post'));
    }

    public function __construct()
    {
        // We could use middleware, but for simplicity in this controller:
    }

    protected function checkTypeActive($slug)
    {
        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $slug)->first();
        if ($postType && !$postType->is_active) {
            abort(404, "This post type is deactivated.");
        }
    }

    protected function generateUniqueSlug($title, $id = 0, $type = 'post', $langCode = 'en')
    {
        // If string contains non-ascii characters OR lang is not english, use native slug logic
        if ($langCode !== 'en' || preg_match('/[^\x00-\x7F]/', $title)) {
            // For non-english, we want to keep the native characters but remove symbols
            $slug = mb_strtolower($title, 'UTF-8');
            $slug = str_replace(' ', '-', trim($slug));
            // Keep letters (\p{L}), marks/vowels (\p{M}), numbers (\p{N}), and dashes. Everything else goes.
            $slug = preg_replace('/[^\p{L}\p{M}\p{N}\-]+/u', '', $slug);
            $slug = preg_replace('/-+/', '-', $slug); // Remove duplicate dashes
            $slug = trim($slug, '-');
        } else {
            $slug = Str::slug($title);
        }
        
        if (empty($slug)) {
            $slug = 'post-' . time();
        }

        $originalSlug = $slug;
        $count = 1;
        while (Post::withTrashed()
            ->where('slug', $slug)
            ->where('type', $type)
            ->where('id', '!=', $id)
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }
        return $slug;
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'post');
        $this->checkTypeActive($type);
        
        // Dynamic permission check
        $p = 'manage_' . $type;
        $a = 'access_' . $type;
        $all = 'access_all_' . $type;
        
        if ($type === 'page') { $p = 'manage_pages'; }
        elseif ($type === 'post') { $p = 'manage_posts'; }

        if (!auth()->user()->hasPermission($p) && !auth()->user()->hasPermission($a) && !auth()->user()->hasPermission($all)) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to manage {$label}.");
        }
        
        $status = $request->query('status');
        
        $lang = $request->query('lang');
        $query = Post::with(['categories', 'tags', 'taxonomyTerms'])->where('type', $type);

        if ($lang && $lang !== 'all') {
            $query->where('lang_code', $lang);
        }

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('s')) {
            $query->where('title', 'like', '%' . $request->s . '%');
        }

        if ($request->filled('cat') && $request->cat != '-1') {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->cat);
            });
        }

        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        if ($request->filled('term_id')) {
            $query->whereHas('taxonomyTerms', function($q) use ($request) {
                $q->where('taxonomy_terms.id', $request->term_id);
            });
        }

        if ($request->filled('m') && $request->m != '-1') {
            $year = substr($request->m, 0, 4);
            $month = substr($request->m, 4, 2);
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        }

        if ($request->filled('author')) {
            $query->where('user_id', $request->author);
        }

        // Ownership Check: Author and Contributor can only see their own posts
        if (auth()->user()->hasRole('author') || auth()->user()->hasRole('contributor')) {
            $query->where('user_id', auth()->id());
        }

        $posts = $query->latest()->paginate(10)->withQueryString();
        $categories = \Acme\CmsDashboard\Models\Category::orderBy('name')->get();
        $dates = Post::where('type', $type)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        $countQuery = Post::where('type', $type);
        if ($lang && $lang !== 'all') {
            $countQuery->where('lang_code', $lang);
        }

        $allCount = (clone $countQuery)->count();
        $publishedCount = (clone $countQuery)->where('status', 'published')->count();
        $draftCount = (clone $countQuery)->where('status', 'draft')->count();
        $scheduledCount = (clone $countQuery)->where('status', 'scheduled')->count();
        $trashCount = (clone $countQuery)->onlyTrashed()->count();

        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $type)->first();
        
        $assignedTaxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)
            ->whereJsonContains('post_types', $type)
            ->get();

        $overriddenTaxonomies = $assignedTaxonomies->whereIn('slug', ['categories', 'tags'])->pluck('slug')->toArray();

        return view('cms-dashboard::admin.posts.index', compact('posts', 'type', 'categories', 'dates', 'allCount', 'publishedCount', 'draftCount', 'scheduledCount', 'trashCount', 'postType', 'assignedTaxonomies', 'overriddenTaxonomies'));
    }

    public function create(Request $request)
    {
        $type = $request->query('type', 'post');
        $this->checkTypeActive($type);

        // Dynamic permission check
        $p = 'manage_' . $type;
        $a = 'access_' . $type;
        $all = 'access_all_' . $type;
        $add = 'access_add_new_' . Str::slug($type, '_');
        
        if ($type === 'page') { $p = 'manage_pages'; }
        elseif ($type === 'post') { $p = 'manage_posts'; }

        if (!auth()->user()->hasPermission($p) && !auth()->user()->hasPermission($a) && !auth()->user()->hasPermission($all) && !auth()->user()->hasPermission($add) && !auth()->user()->hasPermission('access_add_new')) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to create {$label}.");
        }
        
        $pages = Post::where('type', 'page')->orderBy('title')->get();
        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $type)->first();
        $supports = $postType ? ($postType->supports ?? ['title', 'editor', 'excerpt', 'featured_image']) : ['title', 'editor', 'excerpt', 'featured_image'];

        $assignedTaxonomies = [];
        
        // Detect custom taxonomies that override built-in ones
        $overriddenTaxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)
            ->whereJsonContains('post_types', $type)
            ->whereIn('slug', ['categories', 'tags'])
            ->pluck('slug')
            ->toArray();

        $taxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)->get();
        foreach ($taxonomies as $tax) {
            if (is_array($tax->post_types) && in_array($type, $tax->post_types)) {
                $slugLower = strtolower($tax->slug);
                if (in_array($slugLower, ['categories', 'tags', 'category', 'post_tag']) && !in_array($slugLower, $overriddenTaxonomies) && $type !== 'product') continue;
                
                $tax->terms = \Acme\CmsDashboard\Models\TaxonomyTerm::where('taxonomy_slug', $tax->slug)
                    ->where('cpt_slug', $type)
                    ->get();
                $assignedTaxonomies[] = $tax;
            }
        }
        
        // Custom Fields
        $fieldGroups = \Acme\CmsDashboard\Models\FieldGroup::where('is_active', true)
            ->where(function($q) use ($type) {
                $q->whereJsonContains('rules->post_type', $type);
            })
            ->with('fields')
            ->orderBy('order')
            ->get();

        $post = new Post();

        return view('cms-dashboard::admin.posts.create', compact('post', 'type', 'pages', 'supports', 'assignedTaxonomies', 'fieldGroups', 'postType', 'overriddenTaxonomies'));
    }

    public function store(Request $request)
    {
        $type = $request->input('type', 'post');
        $this->checkTypeActive($type);
        
        // Dynamic permission check
        $p = 'manage_' . $type;
        $a = 'access_' . $type;
        $all = 'access_all_' . $type;
        $add = 'access_add_new_' . Str::slug($type, '_');
        
        if ($type === 'page') { $p = 'manage_pages'; }
        elseif ($type === 'post') { $p = 'manage_posts'; }

        if (!auth()->user()->hasPermission($p) && !auth()->user()->hasPermission($a) && !auth()->user()->hasPermission($all) && !auth()->user()->hasPermission($add) && !auth()->user()->hasPermission('access_add_new')) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to store {$label}.");
        }
        
        $this->validateCustomFields($request);

        $status = $request->input('status', 'draft');

        $rules = [
            'title'   => ($status === 'draft' ? 'nullable' : 'required') . '|string|max:255',
            'slug'    => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'type'    => 'required|string',
            'status'  => 'required|string|in:draft,published,scheduled',
            'published_at'   => 'nullable|date',
            'featured_image' => 'nullable',
            'parent_id'      => 'nullable|exists:posts,id',
            'template'       => 'nullable|string',
            'menu_order'     => 'nullable|integer',
            'editor_type'    => 'nullable|string|in:rich,builder',
            'lang_code'      => 'nullable|string|max:10',
            'seo'            => 'nullable|array',
            'gallery'        => 'nullable|array',
        ];

        if ($type === 'product') {
            $rules['price'] = 'required|numeric|min:0';
            $rules['sale_price'] = 'nullable|numeric|min:0|lt:price';
            $rules['sku'] = 'nullable|string|max:100';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['stock_status'] = 'nullable|string|in:instock,outofstock,onbackorder';
            $rules['manage_stock'] = 'nullable|boolean';
            $rules['short_description'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        // Separate Post data and Product data
        $postData = $validated;
        $productData = [];

        if (isset($postData['seo'])) {
            $postData['seo_meta'] = $postData['seo'];
            unset($postData['seo']);
        }

        $productFieldKeys = ['price', 'sale_price', 'sku', 'stock_quantity', 'stock_status', 'manage_stock', 'short_description'];
        foreach ($productFieldKeys as $key) {
            if (array_key_exists($key, $postData)) {
                $productData[$key] = $postData[$key];
                unset($postData[$key]);
            } elseif ($key === 'manage_stock' && $type === 'product') {
                $productData[$key] = 0;
            }
        }

        $lang = $postData['lang_code'] ?? null;
        if (!$lang || $lang === 'all') {
            $lang = app()->getLocale();
            $postData['lang_code'] = $lang;
        }

        $slugSource = !empty($postData['slug']) ? $postData['slug'] : (!empty($postData['title']) ? $postData['title'] : 'no-title');
        $postData['slug'] = $this->generateUniqueSlug($slugSource, 0, $postData['type'], $lang);
        if (empty($postData['title'])) $postData['title'] = '(no title)';
        $postData['user_id'] = auth()->id();

        if ($request->hasFile('featured_image')) {
            $postData['featured_image'] = $request->file('featured_image')->store('posts', 'public');
        } elseif ($request->filled('featured_image')) {
            $postData['featured_image'] = $request->input('featured_image');
        }

        if (empty($postData['template']) || $postData['template'] === 'default') {
            $postData['template'] = 'site-width';
        }

        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $postData['type'])->first();
        $overriddenTaxonomies = [];
        if ($postType) {
            $overriddenTaxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)
                ->whereJsonContains('post_types', $postData['type'])
                ->pluck('slug')
                ->toArray();
        }

        $post = Post::create($postData);

        // Save Product Data
        if ($type === 'product') {
            $post->shopData()->create($productData);
        }

        // Sync Built-in Categories
        if ($request->has('categories')) {
            $post->categories()->sync($request->categories);
        }

        // Sync Custom Taxonomies (for CPTs or assigned tags)
        if ($request->has('tax_terms')) {
            $post->taxonomyTerms()->sync($request->tax_terms);
        }

        if ($request->tags && !in_array('tags', $overriddenTaxonomies)) {
            $tagIds = [];
            $tags = array_map('trim', explode(',', $request->tags));
            foreach($tags as $tagName) {
                if(empty($tagName)) continue;
                $tag = \Acme\CmsDashboard\Models\Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        // Save Custom Fields
        if ($request->has('custom_fields')) {
            foreach ($request->custom_fields as $fieldId => $value) {
                DB::table('post_custom_field_values')->insert([
                    'post_id' => $post->id,
                    'field_id' => $fieldId,
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'created_at' => now(), 'updated_at' => now()
                ]);
            }
        }

        lazy_log_activity('created', "Created a new {$postData['type']}: {$post->title}", $post);

        // Multilingual Copy Logic
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $langCode) {
                $clone = $post->replicate();
                $clone->lang_code = $langCode;
                $clone->origin_id = $post->origin_id ?: $post->id;
                $clone->slug = $post->slug;
                
                $clone->title = lazy_translate($post->title, $langCode);
                
                // Generate translated slug
                $clone->slug = $this->generateUniqueSlug($clone->title, 0, $post->type, $langCode);
                // but let's translate simple text if it's rich editor
                if ($post->editor_type === 'rich') {
                    $clone->content = lazy_translate($post->content, $langCode);
                }
                
                if ($post->excerpt) {
                    $clone->excerpt = lazy_translate($post->excerpt, $langCode);
                }
                
                $clone->save();

                // Sync relationships for the clone
                if ($request->has('categories')) {
                    $clone->categories()->sync($request->categories);
                }
                if ($request->has('tax_terms')) {
                    $clone->taxonomyTerms()->sync($request->tax_terms);
                }
                if ($request->tags && !in_array('tags', $overriddenTaxonomies)) {
                    $clone->tags()->sync($tagIds ?? []);
                }
                
                // Copy custom fields
                if ($request->has('custom_fields')) {
                    foreach ($request->custom_fields as $fieldId => $value) {
                        DB::table('post_custom_field_values')->insert([
                            'post_id' => $clone->id,
                            'field_id' => $fieldId,
                            'value' => is_array($value) ? json_encode($value) : $value,
                            'created_at' => now(), 'updated_at' => now()
                        ]);
                    }
                }
            }
        }

        if ($request->has('redirect_to_builder')) {
            clear_page_cache();
            return redirect()->route('admin.lazy-builder', $post->id)->with('success', ucfirst($postData['type']) . ' created successfully.');
        }

        clear_page_cache();
        return redirect()->route('admin.posts.edit', $post)->with('success', ucfirst($postData['type']) . ' created successfully.');
    }

    public function edit(Post $post)
    {
        $type = $post->type;
        $this->checkTypeActive($post->type);

        // Dynamic permission check
        $p = 'manage_' . $type;
        $a = 'access_' . $type;
        $all = 'access_all_' . $type;
        
        if ($type === 'page') { $p = 'manage_pages'; }
        elseif ($type === 'post') { $p = 'manage_posts'; }

        if (!auth()->user()->hasPermission($p) && !auth()->user()->hasPermission($a) && !auth()->user()->hasPermission($all)) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to edit {$label}.");
        }

        // Ownership Check: Author and Contributor can only edit their own posts
        if ((auth()->user()->hasRole('author') || auth()->user()->hasRole('contributor')) && $post->user_id !== auth()->id()) {
            abort(403, "You can only edit your own posts.");
        }

        $locale = request('locale');
        if ($locale && $locale !== app()->getLocale()) {
            $translation = $post->translations()->where('locale', $locale)->first();
            if ($translation) {
                $post->title = $translation->title;
                $post->content = $translation->content;
                $post->excerpt = $translation->excerpt;
                // We could also merge SEO meta from translation here if needed
            } else {
                // For new translation, start with empty content but keep metadata
                $post->title = '';
                $post->content = '';
                $post->excerpt = '';
            }
        }
        $this->checkTypeActive($post->type);
        $type = $post->type;
        $pages = Post::where('type', 'page')->where('id', '!=', $post->id)->orderBy('title')->get();
        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $type)->first();
        $supports = $postType ? ($postType->supports ?? ['title', 'editor', 'excerpt', 'featured_image']) : ['title', 'editor', 'excerpt', 'featured_image'];

        // Detect custom taxonomies that override built-in ones
        $overriddenTaxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)
            ->whereJsonContains('post_types', $type)
            ->whereIn('slug', ['categories', 'tags'])
            ->pluck('slug')
            ->toArray();

        $assignedTaxonomies = [];
        $taxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)->get();
        foreach ($taxonomies as $tax) {
            if (is_array($tax->post_types) && in_array($type, $tax->post_types)) {
                $slugLower = strtolower($tax->slug);
                if (in_array($slugLower, ['categories', 'tags', 'category', 'post_tag']) && !in_array($slugLower, $overriddenTaxonomies)) continue;

                $tax->terms = \Acme\CmsDashboard\Models\TaxonomyTerm::where('taxonomy_slug', $tax->slug)
                    ->where('cpt_slug', $type)
                    ->get();
                $tax->selected_ids = $post->taxonomyTerms()->where('taxonomy_slug', $tax->slug)->pluck('taxonomy_terms.id')->toArray();
                $assignedTaxonomies[] = $tax;
            }
        }

        // Fetch applicable custom field groups
        $fieldGroups = \Acme\CmsDashboard\Models\FieldGroup::where('is_active', true)
            ->where(function($q) use ($post) {
                $q->whereJsonContains('rules->post_type', $post->type);
            })
            ->with('fields')
            ->orderBy('order')
            ->get();

        // Get existing field values
        $fieldValues = DB::table('post_custom_field_values')
            ->where('post_id', $post->id)
            ->pluck('value', 'field_id')
            ->toArray();

        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $post->type)->first();

        // Convert builder JSON → shortcodes for display in the rich editor.
        // The save path (BuilderShortcodeMiddleware) converts them back to JSON automatically.
        if (!empty($post->content) && BuilderShortcodeConverter::isBuilderJson($post->content)) {
            $post->content = BuilderShortcodeConverter::jsonToShortcodes($post->content);
        }

        return view('cms-dashboard::admin.posts.edit', compact('post', 'pages', 'type', 'supports', 'assignedTaxonomies', 'fieldGroups', 'fieldValues', 'postType', 'overriddenTaxonomies'));
    }

    public function update(Request $request, Post $post)
    {
        $type = $post->type;
        $this->checkTypeActive($post->type);

        // Dynamic permission check
        $p = 'manage_' . $type;
        $a = 'access_' . $type;
        $all = 'access_all_' . $type;
        
        if ($type === 'page') { $p = 'manage_pages'; }
        elseif ($type === 'post') { $p = 'manage_posts'; }

        if (!auth()->user()->hasPermission($p) && !auth()->user()->hasPermission($a) && !auth()->user()->hasPermission($all)) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to update {$label}.");
        }

        // Ownership Check: Author and Contributor can only update their own posts
        if ((auth()->user()->hasRole('author') || auth()->user()->hasRole('contributor')) && $post->user_id !== auth()->id()) {
            abort(403, "You can only update your own posts.");
        }

        $this->checkTypeActive($post->type);
        
        $this->validateCustomFields($request);

        $status = $request->input('status', 'draft');

        $rules = [
            'title'   => ($status === 'draft' ? 'nullable' : 'required') . '|string|max:255',
            'slug'    => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'type'    => 'required|string',
            'status'  => 'required|string|in:draft,published,scheduled',
            'published_at'   => 'nullable|date',
            'featured_image' => 'nullable',
            'parent_id'      => 'nullable|exists:posts,id',
            'template'       => 'nullable|string',
            'menu_order'     => 'nullable|integer',
            'editor_type'    => 'nullable|string|in:rich,builder',
            'lang_code'      => 'nullable|string|max:10',
            'seo'            => 'nullable|array',
            'gallery'        => 'nullable|array',
        ];

        if ($type === 'product') {
            $rules['price'] = 'required|numeric|min:0';
            $rules['sale_price'] = 'nullable|numeric|min:0|lt:price';
            $rules['sku'] = 'nullable|string|max:100';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['stock_status'] = 'nullable|string|in:instock,outofstock,onbackorder';
            $rules['manage_stock'] = 'nullable|boolean';
            $rules['short_description'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        // Separate Post data and Product data
        $postData = $validated;
        $productData = [];

        if (isset($postData['seo'])) {
            $postData['seo_meta'] = $postData['seo'];
            unset($postData['seo']);
        }

        $productFieldKeys = ['price', 'sale_price', 'sku', 'stock_quantity', 'stock_status', 'manage_stock', 'short_description'];
        foreach ($productFieldKeys as $key) {
            if (array_key_exists($key, $postData)) {
                $productData[$key] = $postData[$key];
                unset($postData[$key]);
            } elseif ($key === 'manage_stock' && $post->type === 'product') {
                $productData[$key] = 0;
            }
        }

        $slugSource = !empty($postData['slug']) ? $postData['slug'] : (!empty($postData['title']) ? $postData['title'] : 'no-title');
        $postData['slug'] = $this->generateUniqueSlug($slugSource, $post->id, $post->type, $postData['lang_code'] ?? $post->lang_code);
        if (empty($postData['title'])) $postData['title'] = '(no title)';

        if ($request->hasFile('featured_image')) {
            $postData['featured_image'] = $request->file('featured_image')->store('posts', 'public');
        } elseif ($request->input('remove_featured_image') === '1') {
            $postData['featured_image'] = null;
        } elseif ($request->filled('featured_image')) {
            $postData['featured_image'] = $request->input('featured_image');
        }

        if (empty($postData['template']) || $postData['template'] === 'default') {
            $postData['template'] = 'site-width';
        }

        $postType = \Acme\CmsDashboard\Models\PostType::where('slug', $postData['type'])->first();
        $overriddenTaxonomies = [];
        if ($postType) {
            $overriddenTaxonomies = \Acme\CmsDashboard\Models\CustomTaxonomy::where('is_active', true)
                ->whereJsonContains('post_types', $postData['type'])
                ->pluck('slug')
                ->toArray();
        }

        $oldSlug = $post->getOriginal('slug');
        $prefix = ($post->type === 'post' || $post->type === 'page') ? '' : $post->type . '/';
        $oldUrl = '/' . ltrim($prefix . $oldSlug, '/');

        // Ensure editor_type is never set to null, which would trigger the DB default 'rich'
        if (empty($postData['editor_type'])) {
            $postData['editor_type'] = $post->editor_type ?: 'rich';
        }

        // Robust Protection: Prevent builder content from being overwritten by empty/HTML content from standard editor
        $currentContent = $post->content;
        $isCurrentBuilder = $post->editor_type === 'builder' || (is_string($currentContent) && (str_starts_with($currentContent, '[') || str_starts_with($currentContent, '{')));
        
        $targetEditorType = $postData['editor_type'] ?? $post->editor_type;
        $incomingContent = $postData['content'] ?? '';
        $isIncomingBuilder = is_string($incomingContent) && (Str::startsWith($incomingContent, '[') || Str::startsWith($incomingContent, '{'));

        // If we are currently in builder mode, and we're staying in builder mode, protect the content
        if ($isCurrentBuilder && $targetEditorType === 'builder' && !$isIncomingBuilder) {
            unset($postData['content']);
        }

        $locale = $request->input('locale');
        if ($locale && $locale !== app()->getLocale()) {
            // Save as translation instead of main post
            $translationData = [
                'slug'    => Str::slug($postData['title']),
                'title'   => $postData['title'],
                'excerpt' => $postData['excerpt'],
                'meta_title' => $postData['seo_meta']['title'] ?? null,
                'meta_description' => $postData['seo_meta']['description'] ?? null,
                'updated_at' => now(),
            ];

            // Only update content in translation if it's not a builder page OR if we're sending JSON
            if (isset($postData['content'])) {
                $translationData['content'] = $postData['content'];
            }
            
            // Also preserve editor_type in translation
            $translationData['editor_type'] = $targetEditorType;

            $post->translations()->updateOrInsert(
                ['locale' => $locale],
                $translationData
            );
            
            lazy_log_activity('updated', "Updated {$locale} translation for {$post->type}: {$post->title}", $post);
            clear_page_cache();
            return redirect()->back()->with('success', ucfirst($post->type) . ' translation updated successfully.');
        }

        $post->update($postData);

        // Update Product Data
        if ($post->type === 'product') {
            $post->shopData()->updateOrCreate(
                ['post_id' => $post->id],
                $productData
            );
        }

        // Multilingual Copy Logic (on Update)
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $langCode) {
                // Check if already exists to avoid duplicates
                $rootId = $post->origin_id ?: $post->id;
                $exists = Post::where('origin_id', $rootId)->where('lang_code', $langCode)->exists();
                if ($exists) continue;

                $clone = $post->replicate();
                $clone->lang_code = $langCode;
                $clone->origin_id = $rootId;
                $clone->slug = $post->slug;

                // Auto Translate
                $clone->title = lazy_translate($post->title, $langCode);
                
                // Generate translated slug
                $clone->slug = $this->generateUniqueSlug($clone->title, 0, $post->type, $langCode);
                if ($post->editor_type === 'rich') {
                    $clone->content = lazy_translate($post->content, $langCode);
                }

                if ($post->excerpt) {
                    $clone->excerpt = lazy_translate($post->excerpt, $langCode);
                }

                $clone->save();

                // Sync relationships
                if ($request->has('categories')) $clone->categories()->sync($request->categories);
                if ($request->has('tax_terms')) $clone->taxonomyTerms()->sync($request->tax_terms);
                
                // Copy custom fields
                $originalFields = DB::table('post_custom_field_values')->where('post_id', $post->id)->get();
                foreach ($originalFields as $field) {
                    DB::table('post_custom_field_values')->insert([
                        'post_id' => $clone->id,
                        'field_id' => $field->field_id,
                        'value' => $field->value,
                        'created_at' => now(), 'updated_at' => now()
                    ]);
                }
            }
        }

        // Automatic Redirection Logic
        if ($oldSlug !== $post->slug) {
            $newUrl = '/' . ltrim($prefix . $post->slug, '/');

            if ($oldUrl !== $newUrl) {
                \Acme\CmsDashboard\Models\Redirect::updateOrCreate(
                    ['old_url' => $oldUrl],
                    ['new_url' => $newUrl, 'status_code' => 301]
                );
            }
        }

        // Sync Built-in Categories
        if ($request->has('categories')) {
            $post->categories()->sync($request->categories);
        }

        // Sync Custom Taxonomies
        if ($request->has('tax_terms')) {
            $post->taxonomyTerms()->sync($request->tax_terms);
        } else {
            // If it's a CPT and no terms sent, clear only terms for assigned taxonomies
            if ($post->type !== 'post') {
                $post->taxonomyTerms()->detach();
            }
        }

        if ($request->has('tags') && !in_array('tags', $overriddenTaxonomies)) {
            $tagIds = [];
            $tags = array_map('trim', explode(',', $request->tags));
            foreach($tags as $tagName) {
                if(empty($tagName)) continue;
                $tag = \Acme\CmsDashboard\Models\Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        // Update Custom Fields
        if ($request->has('custom_fields')) {
            foreach ($request->custom_fields as $fieldId => $value) {
                DB::table('post_custom_field_values')->updateOrInsert(
                    ['post_id' => $post->id, 'field_id' => $fieldId],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                        'updated_at' => now()
                    ]
                );
            }
        }
        
        lazy_log_activity('updated', "Updated {$post->type}: {$post->title}", $post);

        clear_page_cache();
        
        return redirect()->back()->with('success', ucfirst($post->type) . ' updated successfully.');
    }

    public function destroy(Post $post)
    {
        $type = $post->type;
        $this->checkTypeActive($post->type);

        // Dynamic permission check
        $p = 'manage_' . $type;
        $a = 'access_' . $type;
        $all = 'access_all_' . $type;
        
        if ($type === 'page') { $p = 'manage_pages'; }
        elseif ($type === 'post') { $p = 'manage_posts'; }

        if (!auth()->user()->hasPermission($p) && !auth()->user()->hasPermission($a) && !auth()->user()->hasPermission($all)) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to delete {$label}.");
        }

        // Ownership Check: Author and Contributor can only delete their own posts
        if ((auth()->user()->hasRole('author') || auth()->user()->hasRole('contributor')) && $post->user_id !== auth()->id()) {
            abort(403, "You can only delete your own posts.");
        }

        $type = $post->type;
        $title = $post->title;
        $post->delete();
        lazy_log_activity('deleted', "Moved {$type} to trash: {$title}", $post);
        clear_page_cache();
        return redirect()->route('admin.posts.index', ['type' => $type])->with('success', 'Moved to trash.');
    }

    public function restore($id)
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $post->restore();
        clear_page_cache();
        return redirect()->back()->with('success', 'Restored successfully.');
    }

    public function forceDelete($id)
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        
        $post->forceDelete();
        clear_page_cache();
        return redirect()->back()->with('success', 'Deleted permanently.');
    }

    public function bulk(Request $request)
    {
        $ids = $request->input('post_ids');
        $action = $request->input('action') !== '-1' ? $request->input('action') : $request->input('action2');

        if (!$ids || $action === '-1') return redirect()->back()->with('error', 'Please select items and an action.');

        if ($action === 'trash') {
            $posts = Post::whereIn('id', $ids)->get();
            foreach ($posts as $post) {
                $post->delete();
                lazy_log_activity('deleted', "Moved {$post->type} to trash: {$post->title}", $post);
            }
            clear_page_cache();
            return redirect()->back()->with('success', 'Selected items moved to trash.');
        }

        if ($action === 'restore') {
            $posts = Post::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($posts as $post) {
                $post->restore();
                lazy_log_activity('restored', "Restored {$post->type} from trash: {$post->title}", $post);
            }
            clear_page_cache();
            return redirect()->back()->with('success', 'Selected items restored.');
        }

        if ($action === 'delete') {
            $posts = Post::onlyTrashed()->whereIn('id', $ids)->get();
            foreach($posts as $p) {
                $title = $p->title;
                $type = $p->type;
                $p->forceDelete();
                lazy_log_activity('deleted', "Deleted {$type} permanently: {$title}", $p);
            }
            clear_page_cache();
            return redirect()->back()->with('success', 'Selected items deleted permanently.');
        }

        if (in_array($action, ['draft', 'published'])) {
            $posts = Post::whereIn('id', $ids)->get();
            foreach ($posts as $post) {
                $post->update(['status' => $action]);
                lazy_log_activity('updated', "Updated {$post->type} status to {$action}: {$post->title}", $post);
            }
            clear_page_cache();
            return redirect()->back()->with('success', 'Selected items updated.');
        }

        clear_page_cache();
        return redirect()->back();
    }

    protected function validateCustomFields(Request $request)
    {
        $type = $request->input('type');
        $status = $request->input('status');
        
        // If saving as draft, skip required validation for custom fields
        if (!$type || $status === 'draft') return;

        $fieldGroups = \Acme\CmsDashboard\Models\FieldGroup::where('is_active', true)
            ->whereJsonContains('rules->post_type', $type)
            ->with('fields')
            ->get();

        $rules = [];
        $messages = [];
        foreach ($fieldGroups as $group) {
            foreach ($group->fields as $field) {
                if ($field->required) {
                    $rules["custom_fields.{$field->id}"] = 'required';
                    $messages["custom_fields.{$field->id}.required"] = "The {$field->label} field is required.";
                }
            }
        }

        if (!empty($rules)) {
            $request->validate($rules, $messages);
        }
    }
}
