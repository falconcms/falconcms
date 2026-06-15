<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use FalconCms\Core\Models\ProductData;
use FalconCms\Core\Models\Revision;
use FalconCms\Core\Services\BuilderShortcodeConverter;

class PostController extends Controller
{
    public function builder($id)
    {
        $post = Post::findOrFail($id);
        $customElements = apply_falcon_filters('falcon_builder_elements', []);

        $bodyRaw    = get_cms_option('theme_typography_body');
        $headingRaw = get_cms_option('theme_typography_h1');
        $bodyFont    = is_array($bodyRaw)    ? $bodyRaw    : json_decode((string)$bodyRaw,    true);
        $headingFont = is_array($headingRaw) ? $headingRaw : json_decode((string)$headingRaw, true);
        $themeBodyFont    = $bodyFont['family']    ?? null;
        $themeHeadingFont = $headingFont['family'] ?? null;

        // Detect a pending autosave newer than the saved content (for the recovery banner)
        $pendingAutosave = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('cms_revisions')) {
                $auto = Revision::where('revisionable_type', $post->getMorphClass())
                    ->where('revisionable_id', $post->getKey())
                    ->where('type', 'autosave')->first();
                if ($auto && $post->updated_at && $auto->updated_at->gt($post->updated_at)) {
                    $pendingAutosave = ['id' => $auto->id, 'time' => $auto->updated_at->format('M j, Y g:i A')];
                }
            }
        } catch (\Throwable $e) {}

        return view('falcon-cms::admin.falcon-builder.index', compact(
            'post', 'customElements', 'themeBodyFont', 'themeHeadingFont', 'pendingAutosave'
        ));
    }

    public function saveBuilder(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        // Snapshot the PRIOR state BEFORE overwriting, so restoring the latest revision
        // brings back the version from before this save (true undo).
        Revision::snapshot($post, 'revision');

        $post->update([
            'content' => json_encode($request->input('layout')),
            'editor_type' => 'builder'
        ]);
        Revision::clearAutosave($post);

        clear_page_cache();

        return response()->json(['success' => true, 'message' => 'Page layout saved successfully.']);
    }

    /**
     * Autosave the builder layout into a recoverable revision WITHOUT touching the live post content.
     */
    public function autosaveBuilder(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        // Build a transient clone-like model carrying the in-progress content (do not persist to post).
        $draft = clone $post;
        $draft->content = json_encode($request->input('layout'));

        $rev = Revision::snapshot($draft, 'autosave');

        return response()->json([
            'success'  => (bool) $rev,
            'saved_at' => $rev ? $rev->updated_at->toIso8601String() : null,
            'time'     => $rev ? $rev->updated_at->format('g:i:s A') : null,
        ]);
    }

    /** List revisions (newest first) for the builder revisions panel. */
    public function revisions($id)
    {
        $post = Post::findOrFail($id);
        $rows = Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->orderByDesc('id')
            ->with('user:id,name')
            ->limit(60)
            ->get()
            ->map(fn($r) => [
                'id'       => $r->id,
                'type'     => $r->type,
                'user'     => $r->user->name ?? 'System',
                'time'     => $r->created_at->format('M j, Y g:i A'),
                'ago'      => $r->created_at->diffForHumans(),
                'is_autosave' => $r->type === 'autosave',
            ]);

        return response()->json(['success' => true, 'revisions' => $rows]);
    }

    /** Restore a revision's content onto the post (snapshots current state first). */
    public function restoreRevision(Request $request, $id, $revisionId)
    {
        $post = Post::findOrFail($id);
        $rev  = Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->findOrFail($revisionId);

        // Preserve the current state as a revision before overwriting.
        Revision::snapshot($post, 'revision');

        $post->update(['content' => $rev->content, 'editor_type' => 'builder']);
        Revision::clearAutosave($post);
        clear_page_cache();

        // Return the layout so the builder can reload it live.
        $layout = json_decode($rev->content, true);
        return response()->json(['success' => true, 'layout' => is_array($layout) ? $layout : [], 'message' => 'Revision restored.']);
    }

    /** Delete a single revision from the builder panel (JSON response). */
    public function deleteRevisionBuilder($id, $revisionId)
    {
        $post = Post::findOrFail($id);
        Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->where('id', $revisionId)
            ->delete();

        return response()->json(['success' => true]);
    }

    /** Classic editor autosave — snapshots title + content without touching the live post. */
    public function autosaveClassic(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        $draft = clone $post;
        $draft->title   = $request->input('title', $post->title);
        $draft->content = $request->input('content', $post->content);

        $rev = Revision::snapshot($draft, 'autosave');

        return response()->json([
            'success' => (bool) $rev,
            'time'    => $rev ? $rev->updated_at->format('g:i:s A') : null,
        ]);
    }

    /** Classic editor restore — persists a revision's title + content, then reloads the edit screen. */
    public function restoreRevisionClassic(Request $request, $id, $revisionId)
    {
        $post = Post::findOrFail($id);
        $rev  = Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->findOrFail($revisionId);

        Revision::snapshot($post, 'revision'); // preserve current first
        $post->update([
            'title'   => $rev->title ?: $post->title,
            'content' => $rev->content,
        ]);
        Revision::clearAutosave($post);
        clear_page_cache();

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Revision restored.');
    }

    /** Delete a single revision. */
    public function deleteRevision(Request $request, $id, $revisionId)
    {
        $post = Post::findOrFail($id);
        Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->where('id', $revisionId)
            ->delete();

        return redirect()->route('admin.posts.revisions', $post->id)->with('success', 'Revision deleted.');
    }

    /** Delete all revisions for a post (keeps the live content untouched). */
    public function clearRevisions(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->delete();

        return redirect()->route('admin.posts.revisions', $post->id)->with('success', 'All revisions cleared.');
    }

    public function ajaxSaveVariations(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        $request->validate([
            'variations' => 'nullable|array',
            'variations.*.price' => 'required|numeric|min:0',
            'variations.*.sale_price' => 'nullable|numeric|min:0|lt:variations.*.price',
            'attributes_data' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $shopData = $post->shopData;
            if (!$shopData) {
                return response()->json(['success' => false, 'message' => 'Product data not found.'], 404);
            }

            // Update attributes if provided
            if ($request->has('attributes_data')) {
                $shopData->update(['attributes_data' => $request->attributes_data]);
            }

            // Always clear and recreate for variable products
            $shopData->variations()->delete();
            if ($request->has('variations')) {
                foreach ($request->variations as $vData) {
                        $shopData->variations()->create([
                            'attributes_data' => $vData['attributes_data'] ?? [],
                            'price' => $vData['price'] ?? null,
                            'sale_price' => $vData['sale_price'] ?? null,
                            'sku' => $vData['sku'] ?? null,
                            'weight' => $vData['weight'] ?? null,
                            'length' => $vData['length'] ?? null,
                            'width' => $vData['width'] ?? null,
                            'height' => $vData['height'] ?? null,
                            'stock_status' => $vData['stock_status'] ?? 'instock',
                            'stock_quantity' => $vData['stock_quantity'] ?? 0,
                            'manage_stock' => $vData['manage_stock'] ?? false,
                            'image' => $vData['image'] ?? null,
                        ]);
                }
            }

            DB::commit();

            // After commit, sync parent stock status based on variations
            $anyInStock = $shopData->variations()->where('stock_status', 'instock')->exists();
            $shopData->update(['stock_status' => $anyInStock ? 'instock' : 'outofstock']);

            clear_page_cache();

            return response()->json(['success' => true, 'message' => 'Variations saved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function previewBuilder($id)
    {
        $post = Post::findOrFail($id);
        // This would typically return a front-end view that renders the builder JSON
        return view('falcon-cms::admin.falcon-builder.preview', compact('post'));
    }

    public function __construct()
    {
        // We could use middleware, but for simplicity in this controller:
    }

    protected function checkTypeActive($slug)
    {
        // Admin users can always access any CPT regardless of is_active status.
        // is_active controls frontend visibility only, not admin management access.
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

    /**
     * Whether the current user may manage the given post type. Permission slugs are
     * derived from the (often pluralised) type/title — e.g. the "product" CPT uses
     * access_products / access_all_products — so we accept both the type slug and its
     * plural form to avoid singular/plural mismatches. $extra adds gate-specific perms
     * (e.g. add-new permissions for create/store).
     */
    private function userCanManageType(string $type, array $extra = []): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        $perms = [];
        foreach (array_unique([$type, Str::plural($type)]) as $t) {
            $perms[] = 'manage_' . $t;
            $perms[] = 'access_' . $t;
            $perms[] = 'access_all_' . $t;
        }
        if ($type === 'page') $perms[] = 'manage_pages';
        if ($type === 'post') $perms[] = 'manage_posts';

        foreach (array_merge($perms, $extra) as $p) {
            if ($user->hasPermission($p)) return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'post');
        $this->checkTypeActive($type);

        if (!$this->userCanManageType($type)) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to manage {$label}.");
        }
        
        $status = $request->query('status');
        
        $lang = $request->query('lang');
        $eagerLoad = ['categories', 'tags', 'taxonomyTerms'];
        if ($type === 'product') {
            $eagerLoad[] = 'shopData';
        }
        $query = Post::with($eagerLoad)->where('type', $type);

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

        // Dedicated Product Category / Tag filters (products)
        if ($request->filled('product_cat')) {
            $query->whereHas('productCategories', function($q) use ($request) {
                $q->where('product_categories.id', $request->product_cat);
            });
        }

        if ($request->filled('product_tag')) {
            $query->whereHas('productTags', function($q) use ($request) {
                $q->where('product_tags.id', $request->product_tag);
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
        $categories = \FalconCms\Core\Models\Category::orderBy('name')->get();
        $driver = \DB::connection()->getDriverName();
        $yearCol  = $driver === 'sqlite' ? "strftime('%Y', created_at)" : 'YEAR(created_at)';
        $monthCol = $driver === 'sqlite' ? "strftime('%m', created_at)" : 'MONTH(created_at)';
        $dates = Post::where('type', $type)
            ->selectRaw("$yearCol as year, $monthCol as month")
            ->groupByRaw("$yearCol, $monthCol")
            ->orderByRaw("$yearCol DESC, $monthCol DESC")
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

        $postType = \FalconCms\Core\Models\PostType::where('slug', $type)->first();
        
        $assignedTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)
            ->whereJsonContains('post_types', $type)
            ->get();

        $overriddenTaxonomies = $assignedTaxonomies->whereIn('slug', ['categories', 'tags'])->pluck('slug')->toArray();

        return view('falcon-cms::admin.posts.index', compact('posts', 'type', 'categories', 'dates', 'allCount', 'publishedCount', 'draftCount', 'scheduledCount', 'trashCount', 'postType', 'assignedTaxonomies', 'overriddenTaxonomies'));
    }

    /**
     * Sync the dedicated Product Categories (checkbox ids) and Product Tags
     * (comma-separated names) onto a product post. Used by store/update/clone.
     */
    private function syncProductTaxonomies(Post $post, Request $request): void
    {
        $catIds = array_filter((array) $request->input('product_categories', []));
        $post->productCategories()->sync($catIds);

        $tagIds = [];
        foreach (array_map('trim', explode(',', (string) $request->input('product_tags', ''))) as $name) {
            if ($name === '') continue;
            $tag = \FalconCms\Core\Models\ProductTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $tagIds[] = $tag->id;
        }
        $post->productTags()->sync($tagIds);
    }

    public function create(Request $request)
    {
        $type = $request->query('type', 'post');
        $this->checkTypeActive($type);

        // Dynamic permission check
        if (!$this->userCanManageType($type, ['access_add_new_' . Str::slug($type, '_'), 'access_add_new_' . Str::plural($type), 'access_add_new'])) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to create {$label}.");
        }
        
        $pages = Post::where('type', 'page')->orderBy('title')->get();
        $postType = \FalconCms\Core\Models\PostType::where('slug', $type)->first();
        $supports = $postType ? ($postType->supports ?? ['title', 'editor', 'excerpt', 'featured_image']) : ['title', 'editor', 'excerpt', 'featured_image'];

        $assignedTaxonomies = [];
        
        // Detect custom taxonomies that override built-in ones
        $overriddenTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)
            ->whereJsonContains('post_types', $type)
            ->whereIn('slug', ['categories', 'tags'])
            ->pluck('slug')
            ->toArray();

        $taxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->get();
        foreach ($taxonomies as $tax) {
            if (is_array($tax->post_types) && in_array($type, $tax->post_types)) {
                $slugLower = strtolower($tax->slug);
                if (in_array($slugLower, ['categories', 'tags', 'category', 'post_tag']) && !in_array($slugLower, $overriddenTaxonomies) && $type !== 'product') continue;
                
                $tax->terms = \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $tax->slug)
                    ->where('cpt_slug', $type)
                    ->get();
                $assignedTaxonomies[] = $tax;
            }
        }
        
        // Custom Fields
        $fieldGroups = \FalconCms\Core\Models\FieldGroup::where('is_active', true)
            ->where(function($q) use ($type) {
                $q->whereJsonContains('rules->post_type', $type);
            })
            ->with('fields')
            ->orderBy('order')
            ->get();

        $post = new Post();

        return view('falcon-cms::admin.posts.create', compact('post', 'type', 'pages', 'supports', 'assignedTaxonomies', 'fieldGroups', 'postType', 'overriddenTaxonomies'));
    }

    public function store(Request $request)
    {
        $type = $request->input('type', 'post');
        $this->checkTypeActive($type);
        
        // Dynamic permission check
        if (!$this->userCanManageType($type, ['access_add_new_' . Str::slug($type, '_'), 'access_add_new_' . Str::plural($type), 'access_add_new'])) {
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
            $rules['attributes_data'] = 'nullable|array';
            $rules['product_type'] = 'required|string|in:simple,variable';
            $rules['price'] = 'required_if:product_type,simple|nullable|numeric|min:0';
            $rules['sale_price'] = 'nullable|numeric|min:0|lt:price';
            $rules['sale_ends_at'] = 'nullable|date';
            $rules['is_downloadable'] = 'nullable|boolean';
            $rules['download_expiry_days'] = 'nullable|integer|min:1|max:3650';
            $rules['sku'] = 'nullable|string|max:100';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['stock_status'] = 'nullable|string|in:instock,outofstock,onbackorder';
            $rules['manage_stock'] = 'nullable|boolean';
            $rules['short_description'] = 'nullable|string';
            $rules['variations'] = 'nullable|array';
            $rules['variations.*.price'] = 'required|numeric|min:0';
            $rules['variations.*.sale_price'] = 'nullable|numeric|min:0|lt:variations.*.price';
        }

        $validated = $request->validate($rules);

        // Separate Post data and Product data
        $postData = $validated;
        $productData = [];

        if (isset($postData['seo'])) {
            $postData['seo_meta'] = $postData['seo'];
            unset($postData['seo']);
        }

        $productFieldKeys = ['price', 'sale_price', 'sale_ends_at', 'is_downloadable', 'download_expiry_days', 'sku', 'stock_quantity', 'stock_status', 'manage_stock', 'short_description', 'attributes_data'];
        
        // Map product_type to type in database
        if (isset($postData['product_type'])) {
            $productData['type'] = $postData['product_type'];
            unset($postData['product_type']);
        }

        foreach ($productFieldKeys as $key) {
            if (array_key_exists($key, $postData)) {
                $productData[$key] = $postData[$key];
                unset($postData[$key]);
            } elseif ($key === 'manage_stock' && $type === 'product') {
                $productData[$key] = 0;
            }
        }

        // Explicitly remove variations from post data as it's handled separately
        unset($postData['variations']);

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
            $file = $request->file('featured_image');
            $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp','image/avif'];
            $allowedExts  = ['jpg','jpeg','png','gif','webp','avif'];
            if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExts) || !in_array($file->getMimeType(), $allowedMimes)) {
                return redirect()->back()->withErrors(['featured_image' => 'Featured image must be a valid image file (JPG, PNG, GIF, WebP, AVIF).'])->withInput();
            }
            $postData['featured_image'] = $file->store('posts', 'public');
        } elseif ($request->filled('featured_image')) {
            $postData['featured_image'] = $request->input('featured_image');
        }

        if (empty($postData['template']) || $postData['template'] === 'default') {
            $postData['template'] = 'site-width';
        }

        $postType = \FalconCms\Core\Models\PostType::where('slug', $postData['type'])->first();
        $overriddenTaxonomies = [];
        if ($postType) {
            $overriddenTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)
                ->whereJsonContains('post_types', $postData['type'])
                ->pluck('slug')
                ->toArray();
        }

        // Interpret the publish date in the CMS timezone → store UTC, and decide status on the server.
        $postData = lazy_normalize_publish($postData);

        if ($type === 'product') {
            $productData = apply_falcon_filters('lazy_admin_before_save_product', $productData, null, $request);
        }

        $post = Post::create($postData);

        // Save Product Data
        if ($type === 'product') {
            $shopData = $post->shopData()->create($productData);
            
            // Save Variations if product type is variable
            if ($productData['type'] === 'variable' && $request->has('variations')) {
                foreach ($request->variations as $vData) {
                    $shopData->variations()->create([
                        'attributes_data' => $vData['attributes'] ?? [],
                        'price' => $vData['price'] ?? null,
                        'sale_price' => $vData['sale_price'] ?? null,
                        'sku' => $vData['sku'] ?? null,
                        'stock_status' => $vData['stock_status'] ?? 'instock',
                        'stock_quantity' => $vData['stock_quantity'] ?? 0,
                        'manage_stock' => ($vData['stock_quantity'] ?? 0) > 0,
                        'image' => $vData['image'] ?? null,
                    ]);
                }
            }
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
                $tag = \FalconCms\Core\Models\Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        // Sync dedicated Product Categories & Tags (products only)
        if ($type === 'product') {
            $this->syncProductTaxonomies($post, $request);
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

        if ($type === 'product') {
            do_falcon_action('lazy_admin_after_save_product', $post, $post->shopData, $request, 'create');
        }

        falcon_log_activity('created', "Created a new {$postData['type']}: {$post->title}", $post);

        // Multilingual Copy Logic
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $langCode) {
                $clone = $post->replicate();
                $clone->lang_code = $langCode;
                $clone->origin_id = $post->origin_id ?: $post->id;
                $clone->slug = $post->slug;
                
                $clone->title = falcon_translate($post->title, $langCode);
                
                // Generate translated slug
                $clone->slug = $this->generateUniqueSlug($clone->title, 0, $post->type, $langCode);
                // but let's translate simple text if it's rich editor
                if ($post->editor_type === 'rich') {
                    $clone->content = falcon_translate($post->content, $langCode);
                }
                
                if ($post->excerpt) {
                    $clone->excerpt = falcon_translate($post->excerpt, $langCode);
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
                if ($type === 'product') {
                    $this->syncProductTaxonomies($clone, $request);
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
            return redirect()->route('admin.falcon-builder', $post->id)->with('success', ucfirst($postData['type']) . ' created successfully.');
        }

        clear_page_cache();
        return redirect()->route('admin.posts.edit', $post)->with('success', ucfirst($postData['type']) . ' created successfully.');
    }

    public function edit(Post $post)
    {
        $type = $post->type;
        $this->checkTypeActive($post->type);

        if (!$this->userCanManageType($type)) {
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
        $postType = \FalconCms\Core\Models\PostType::where('slug', $type)->first();
        $supports = $postType ? ($postType->supports ?? ['title', 'editor', 'excerpt', 'featured_image']) : ['title', 'editor', 'excerpt', 'featured_image'];

        // Detect custom taxonomies that override built-in ones
        $overriddenTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)
            ->whereJsonContains('post_types', $type)
            ->whereIn('slug', ['categories', 'tags'])
            ->pluck('slug')
            ->toArray();

        $assignedTaxonomies = [];
        $taxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->get();
        foreach ($taxonomies as $tax) {
            if (is_array($tax->post_types) && in_array($type, $tax->post_types)) {
                $slugLower = strtolower($tax->slug);
                if (in_array($slugLower, ['categories', 'tags', 'category', 'post_tag']) && !in_array($slugLower, $overriddenTaxonomies)) continue;

                $tax->terms = \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $tax->slug)
                    ->where('cpt_slug', $type)
                    ->get();
                $tax->selected_ids = $post->taxonomyTerms()->where('taxonomy_slug', $tax->slug)->pluck('taxonomy_terms.id')->toArray();
                $assignedTaxonomies[] = $tax;
            }
        }

        // Fetch applicable custom field groups
        $fieldGroups = \FalconCms\Core\Models\FieldGroup::where('is_active', true)
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

        $postType = \FalconCms\Core\Models\PostType::where('slug', $post->type)->first();

        // Convert builder JSON → shortcodes for display in the rich editor.
        // The save path (BuilderShortcodeMiddleware) converts them back to JSON automatically.
        if (!empty($post->content) && BuilderShortcodeConverter::isBuilderJson($post->content)) {
            $post->content = BuilderShortcodeConverter::jsonToShortcodes($post->content);
        }

        // Pending autosave (classic editor recovery banner) + revision count for the Revisions button
        $pendingAutosave = null;
        $revisionCount = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('cms_revisions')) {
                $base = Revision::where('revisionable_type', $post->getMorphClass())
                    ->where('revisionable_id', $post->getKey());
                $revisionCount = (clone $base)->where('type', 'revision')->count();
                $auto = (clone $base)->where('type', 'autosave')->first();
                if ($auto && $post->updated_at && $auto->updated_at->gt($post->updated_at)) {
                    $pendingAutosave = ['id' => $auto->id, 'time' => $auto->updated_at->format('M j, Y g:i A')];
                }
            }
        } catch (\Throwable $e) {}

        return view('falcon-cms::admin.posts.edit', compact('post', 'pages', 'type', 'supports', 'assignedTaxonomies', 'fieldGroups', 'fieldValues', 'postType', 'overriddenTaxonomies', 'pendingAutosave', 'revisionCount'));
    }

    /** Full revisions comparison page (classic editor) — before/after diff + restore. */
    public function revisionsPage(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $revs = Revision::where('revisionable_type', $post->getMorphClass())
            ->where('revisionable_id', $post->getKey())
            ->orderByDesc('id')
            ->with('user:id,name')
            ->limit(80)
            ->get();

        // Build selectable entries: the live "current" version + every stored revision.
        $entries = [];
        $entries['current'] = [
            'key'     => 'current',
            'label'   => 'Current Version (live)',
            'title'   => $post->title,
            'content' => $post->content,
            'meta'    => 'Currently published',
            'type'    => 'current',
        ];
        foreach ($revs as $r) {
            $entries[(string) $r->id] = [
                'key'     => (string) $r->id,
                'label'   => ($r->type === 'autosave' ? 'Autosave' : 'Revision') . ' — ' . $r->created_at->format('M j, Y g:i A'),
                'title'   => $r->title,
                'content' => $r->content,
                'meta'    => ($r->user->name ?? 'System') . ' · ' . $r->created_at->diffForHumans(),
                'type'    => $r->type,
            ];
        }

        // Resolve which two versions to compare (defaults: latest revision → current)
        $to   = (string) $request->get('to', 'current');
        $from = (string) $request->get('from', $revs->count() ? (string) $revs->first()->id : 'current');
        if (!isset($entries[$to]))   $to = 'current';
        if (!isset($entries[$from])) $from = $to;

        $diff = lazy_revision_diff($entries[$from]['content'] ?? '', $entries[$to]['content'] ?? '');

        return view('falcon-cms::admin.posts.revisions', compact('post', 'entries', 'from', 'to', 'diff'));
    }

    public function update(Request $request, Post $post)
    {
        $type = $post->type;
        $this->checkTypeActive($post->type);

        if (!$this->userCanManageType($type)) {
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
            $rules['attributes_data'] = 'nullable|array';
            $rules['product_type'] = 'required|string|in:simple,variable';
            $rules['price'] = 'required_if:product_type,simple|nullable|numeric|min:0';
            $rules['sale_price'] = 'nullable|numeric|min:0|lt:price';
            $rules['sale_ends_at'] = 'nullable|date';
            $rules['is_downloadable'] = 'nullable|boolean';
            $rules['download_expiry_days'] = 'nullable|integer|min:1|max:3650';
            $rules['sku'] = 'nullable|string|max:100';
            $rules['stock_quantity'] = 'nullable|integer|min:0';
            $rules['stock_status'] = 'nullable|string|in:instock,outofstock,onbackorder';
            $rules['manage_stock'] = 'nullable|boolean';
            $rules['short_description'] = 'nullable|string';
            $rules['variations'] = 'nullable|array';
            $rules['variations.*.price'] = 'required|numeric|min:0';
            $rules['variations.*.sale_price'] = 'nullable|numeric|min:0|lt:variations.*.price';
        }

        $validated = $request->validate($rules);

        // Separate Post data and Product data
        $postData = $validated;
        $productData = [];

        if (isset($postData['seo'])) {
            $postData['seo_meta'] = $postData['seo'];
            unset($postData['seo']);
        }

        $productFieldKeys = ['price', 'sale_price', 'sale_ends_at', 'is_downloadable', 'download_expiry_days', 'sku', 'stock_quantity', 'stock_status', 'manage_stock', 'short_description', 'attributes_data'];

        // Map product_type to type in database
        if (isset($postData['product_type'])) {
            $productData['type'] = $postData['product_type'];
            unset($postData['product_type']);
        }

        foreach ($productFieldKeys as $key) {
            if (array_key_exists($key, $postData)) {
                $productData[$key] = $postData[$key];
                unset($postData[$key]);
            } elseif ($key === 'manage_stock' && $post->type === 'product') {
                $productData[$key] = 0;
            }
        }

        // Explicitly remove variations from post data as it's handled separately
        unset($postData['variations']);

        $slugSource = !empty($postData['slug']) ? $postData['slug'] : (!empty($postData['title']) ? $postData['title'] : 'no-title');
        $postData['slug'] = $this->generateUniqueSlug($slugSource, $post->id, $post->type, $postData['lang_code'] ?? $post->lang_code);
        if (empty($postData['title'])) $postData['title'] = '(no title)';

        if ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');
            $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp','image/avif'];
            $allowedExts  = ['jpg','jpeg','png','gif','webp','avif'];
            if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExts) || !in_array($file->getMimeType(), $allowedMimes)) {
                return redirect()->back()->withErrors(['featured_image' => 'Featured image must be a valid image file (JPG, PNG, GIF, WebP, AVIF).'])->withInput();
            }
            $postData['featured_image'] = $file->store('posts', 'public');
        } elseif ($request->input('remove_featured_image') === '1') {
            $postData['featured_image'] = null;
        } elseif ($request->filled('featured_image')) {
            $postData['featured_image'] = $request->input('featured_image');
        }

        if (empty($postData['template']) || $postData['template'] === 'default') {
            $postData['template'] = 'site-width';
        }

        $postType = \FalconCms\Core\Models\PostType::where('slug', $postData['type'])->first();
        $overriddenTaxonomies = [];
        if ($postType) {
            $overriddenTaxonomies = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)
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

        // Protect builder content ONLY when staying in builder mode with non-builder (empty/HTML) content —
        // this prevents an accidental wipe when saving from the builder placeholder. When the editor is
        // explicitly in RICH mode, the edit is always saved so rich-editor changes appear on the front-end.
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
            
            falcon_log_activity('updated', "Updated {$locale} translation for {$post->type}: {$post->title}", $post);
            clear_page_cache();
            return redirect()->back()->with('success', ucfirst($post->type) . ' translation updated successfully.');
        }

        // Snapshot the PRIOR state BEFORE overwriting (true undo on restore)
        Revision::snapshot($post, 'revision');

        // Interpret the publish date in the CMS timezone → store UTC, and decide status on the server.
        $postData = lazy_normalize_publish($postData);

        if ($post->type === 'product') {
            $productData = apply_falcon_filters('lazy_admin_before_save_product', $productData, $post, $request);
        }

        $post->update($postData);
        Revision::clearAutosave($post);

        // Update Product Data
        if ($post->type === 'product') {
            $shopData = $post->shopData()->updateOrCreate(
                ['post_id' => $post->id],
                $productData
            );

            // Handle Variations
            if ($productData['type'] === 'variable') {
                // Always clear and recreate for variable products
                $shopData->variations()->delete();
                if ($request->has('variations')) {
                    foreach ($request->variations as $vData) {
                        $shopData->variations()->create([
                            'attributes_data' => $vData['attributes'] ?? [],
                            'price' => $vData['price'] ?? null,
                            'sale_price' => $vData['sale_price'] ?? null,
                            'sku' => $vData['sku'] ?? null,
                            'weight' => $vData['weight'] ?? null,
                            'length' => $vData['length'] ?? null,
                            'width' => $vData['width'] ?? null,
                            'height' => $vData['height'] ?? null,
                            'stock_status' => $vData['stock_status'] ?? 'instock',
                            'stock_quantity' => $vData['stock_quantity'] ?? 0,
                            'manage_stock' => ($vData['stock_quantity'] ?? 0) > 0,
                            'image' => $vData['image'] ?? null,
                        ]);
                    }
                }
                
                // After variations are saved, sync parent stock status
                $anyInStock = $shopData->variations()->where('stock_status', 'instock')->exists();
                $shopData->update(['stock_status' => $anyInStock ? 'instock' : 'outofstock']);
            } else {
                // If not variable, ensure no variations exist
                $shopData->variations()->delete();
            }
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
                $clone->title = falcon_translate($post->title, $langCode);
                
                // Generate translated slug
                $clone->slug = $this->generateUniqueSlug($clone->title, 0, $post->type, $langCode);
                if ($post->editor_type === 'rich') {
                    $clone->content = falcon_translate($post->content, $langCode);
                }

                if ($post->excerpt) {
                    $clone->excerpt = falcon_translate($post->excerpt, $langCode);
                }

                $clone->save();

                // Sync relationships
                if ($request->has('categories')) $clone->categories()->sync($request->categories);
                if ($request->has('tax_terms')) $clone->taxonomyTerms()->sync($request->tax_terms);
                if ($post->type === 'product') $this->syncProductTaxonomies($clone, $request);
                
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
                \FalconCms\Core\Models\Redirect::updateOrCreate(
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
                $tag = \FalconCms\Core\Models\Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $post->tags()->sync($tagIds);
        }

        // Sync dedicated Product Categories & Tags (products only)
        if ($type === 'product') {
            $this->syncProductTaxonomies($post, $request);
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
        
        if ($post->type === 'product') {
            do_falcon_action('lazy_admin_after_save_product', $post, $post->fresh()->shopData, $request, 'update');
        }

        falcon_log_activity('updated', "Updated {$post->type}: {$post->title}", $post);

        clear_page_cache();

        return redirect()->back()->with('success', ucfirst($post->type) . ' updated successfully.');
    }

    public function destroy(Post $post)
    {
        $type = $post->type;
        $this->checkTypeActive($post->type);

        if (!$this->userCanManageType($type)) {
            $label = Str::plural($type);
            abort(403, "You do not have permission to delete {$label}.");
        }

        // Ownership Check: Author and Contributor can only delete their own posts
        if ((auth()->user()->hasRole('author') || auth()->user()->hasRole('contributor')) && $post->user_id !== auth()->id()) {
            abort(403, "You can only delete your own posts.");
        }

        $type = $post->type;
        $title = $post->title;

        if ($type === 'product') {
            do_falcon_action('lazy_admin_before_delete_product', $post);
        }

        $post->delete();

        if ($type === 'product') {
            do_falcon_action('lazy_admin_after_delete_product', $post->id, $title);
        }

        falcon_log_activity('deleted', "Moved {$type} to trash: {$title}", $post);
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
                falcon_log_activity('deleted', "Moved {$post->type} to trash: {$post->title}", $post);
            }
            clear_page_cache();
            return redirect()->back()->with('success', 'Selected items moved to trash.');
        }

        if ($action === 'restore') {
            $posts = Post::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($posts as $post) {
                $post->restore();
                falcon_log_activity('restored', "Restored {$post->type} from trash: {$post->title}", $post);
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
                falcon_log_activity('deleted', "Deleted {$type} permanently: {$title}", $p);
            }
            clear_page_cache();
            return redirect()->back()->with('success', 'Selected items deleted permanently.');
        }

        if (in_array($action, ['draft', 'published'])) {
            $posts = Post::whereIn('id', $ids)->get();
            foreach ($posts as $post) {
                $post->update(['status' => $action]);
                falcon_log_activity('updated', "Updated {$post->type} status to {$action}: {$post->title}", $post);
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

        $fieldGroups = \FalconCms\Core\Models\FieldGroup::where('is_active', true)
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
