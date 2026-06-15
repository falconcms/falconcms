<?php

namespace FalconCms\Core\Http\Controllers\Api\V1;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Http\Resources\PostResource;
use FalconCms\Core\Models\Category;
use Illuminate\Support\Facades\DB;

class CmsApiController extends Controller
{
    public function __construct()
    {
        if (get_cms_option('enable_rest_api', '1') !== '1') {
            abort(403, 'REST API is disabled in settings.');
        }
    }

    /**
     * Get list of posts
     */
    public function posts(Request $request)
    {
        // Clamp the page size so a request like ?limit=999999 can't dump the whole table.
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 100));
        $type = $request->query('type', 'post');

        $posts = Post::where('type', $type)
            ->where('status', 'published')
            ->with(['user', 'categories', 'tags'])
            ->latest()
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => PostResource::collection($posts->getCollection())->resolve(),
            'meta'    => [
                'current_page' => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
                'last_page'    => $posts->lastPage(),
            ],
        ]);
    }

    /**
     * Get single post by slug
     */
    public function singlePost($slug)
    {
        $post = Post::where('slug', $slug)
            ->where('status', 'published')
            ->with(['user', 'categories', 'tags'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => (new PostResource($post))->resolve(),
        ]);
    }

    /**
     * Get settings
     */
    public function settings()
    {
        $settings = DB::table('cms_settings')->pluck('value', 'key');
        
        // Filter out sensitive settings if needed
        $publicSettings = $settings->only([
            'site_title', 'tagline', 'timezone', 'home_page_id'
        ]);

        return response()->json([
            'success' => true,
            'data' => $publicSettings
        ]);
    }

    /**
     * Get navigation menus
     */
    public function menus()
    {
        // Front-end navigation menus (not the admin sidebar) with their nested items.
        $menus = \FalconCms\Core\Models\NavigationMenu::with('items.children')->get();

        return response()->json([
            'success' => true,
            'data'    => $menus,
        ]);
    }

    /** Shape a product (post + shopData) for the API. */
    private function transformProduct($post): array
    {
        $s = $post->shopData;
        return [
            'id'             => $post->id,
            'title'          => $post->title,
            'slug'           => $post->slug,
            'excerpt'        => ($s && $s->short_description) ? $s->short_description : get_lazy_excerpt($post, 160),
            'price'          => $s && $s->price !== null ? (float) $s->price : null,
            'sale_price'     => $s && $s->sale_price !== null ? (float) $s->sale_price : null,
            'sku'            => $s->sku ?? null,
            'in_stock'       => (bool) $post->is_in_stock,
            'stock_quantity' => ($s && $s->manage_stock) ? (int) $s->stock_quantity : null,
            'product_type'   => $s->product_type ?? 'simple',
            'featured_image' => $post->featured_image ? url('storage/' . $post->featured_image) : null,
            'categories'     => $post->productCategories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values(),
            'url'            => url('product/' . $post->slug),
            // Dynamic custom fields assigned to the product type — auto-detected from the DB.
            'custom_fields'  => get_post_custom_fields($post),
        ];
    }

    /** List published products (optionally filtered by ?category=slug). */
    public function products(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 10), 100));

        $query = Post::where('type', 'product')->where('status', 'published')
            ->with(['shopData', 'productCategories']);

        if ($cat = $request->query('category')) {
            $query->whereHas('productCategories', fn ($q) => $q->where('slug', $cat));
        }

        $products = $query->latest()->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => collect($products->items())->map(fn ($p) => $this->transformProduct($p))->values(),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'last_page'    => $products->lastPage(),
            ],
        ]);
    }

    /** Single product by slug. */
    public function singleProduct($slug)
    {
        $post = Post::where('type', 'product')->where('slug', $slug)->where('status', 'published')
            ->with(['shopData', 'productCategories', 'productTags'])->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->transformProduct($post)]);
    }

    /** Post categories. */
    public function categories()
    {
        $cats = \FalconCms\Core\Models\Category::orderBy('name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'parent_id' => $c->parent_id]);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    /** Post tags. */
    public function tags()
    {
        $tags = \FalconCms\Core\Models\Tag::orderBy('name')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]);

        return response()->json(['success' => true, 'data' => $tags]);
    }

    /** Search published content (?q=) across posts, pages and products. */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $results = Post::where('status', 'published')
            ->whereIn('type', ['post', 'page', 'product'])
            ->where('title', 'like', '%' . $term . '%')
            ->limit(20)->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'title' => $p->title,
                'slug'  => $p->slug,
                'type'  => $p->type,
                'url'   => $p->type === 'product' ? url('product/' . $p->slug) : url($p->slug),
            ])->values();

        return response()->json(['success' => true, 'data' => $results]);
    }

    // ── Write endpoints (require a Bearer API token + the matching permission) ──────

    /** Whether the token's user may manage the given post type. */
    private function canWrite($user, string $type): bool
    {
        if (!$user) return false;
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return true;
        if ($type === 'page') return $user->hasPermission('manage_pages');
        if ($type === 'post') return $user->hasPermission('manage_posts');
        foreach (array_unique([$type, \Illuminate\Support\Str::plural($type)]) as $t) {
            if ($user->hasPermission('manage_' . $t) || $user->hasPermission('access_all_' . $t)) return true;
        }
        return false;
    }

    /** Create a post/page/CPT entry. */
    public function storePost(Request $request)
    {
        $type = $request->input('type', 'post');
        if (!$this->canWrite($request->user(), $type)) {
            return response()->json(['success' => false, 'message' => "You do not have permission to create {$type}."], 403);
        }

        $v = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'status'  => 'nullable|in:draft,published,pending',
            'slug'    => 'nullable|string|max:255',
        ]);

        $slug = $v['slug'] ?? \Illuminate\Support\Str::slug($v['title']);
        if (Post::where('slug', $slug)->exists()) {
            $slug .= '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5));
        }

        $post = Post::create([
            'user_id'     => $request->user()->id,
            'title'       => $v['title'],
            'slug'        => $slug,
            'content'     => $v['content'] ?? '',
            'excerpt'     => $v['excerpt'] ?? null,
            'type'        => $type,
            'status'      => $v['status'] ?? 'draft',
            'editor_type' => 'classic',
        ]);

        return response()->json([
            'success' => true,
            'data'    => (new PostResource($post->load(['user', 'categories', 'tags'])))->resolve(),
        ], 201);
    }

    /** Update an existing post. */
    public function updatePost(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        if (!$this->canWrite($request->user(), $post->type)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to update this item.'], 403);
        }

        $v = $request->validate([
            'title'   => 'sometimes|required|string|max:255',
            'content' => 'sometimes|nullable|string',
            'excerpt' => 'sometimes|nullable|string',
            'status'  => 'sometimes|nullable|in:draft,published,pending',
            'slug'    => 'sometimes|nullable|string|max:255|unique:posts,slug,' . $post->id,
        ]);

        $post->update(array_filter($v, fn ($val, $k) => $request->has($k), ARRAY_FILTER_USE_BOTH));

        return response()->json([
            'success' => true,
            'data'    => (new PostResource($post->fresh()->load(['user', 'categories', 'tags'])))->resolve(),
        ]);
    }

    /** Delete a post. */
    public function destroyPost(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        if (!$this->canWrite($request->user(), $post->type)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to delete this item.'], 403);
        }

        $post->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}
