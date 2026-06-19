<?php

namespace FalconCms\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use Illuminate\Http\Request;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Models\TaxonomyTerm;


class FrontendController extends Controller
{
    protected function resolveThemeView($view, $fallback = null)
    {
        $activeTheme = get_cms_option('active_theme', 'falcon-theme');
        
        // 1. Try App-level theme
        $appView = "themes.{$activeTheme}.{$view}";
        if (view()->exists($appView)) {
            return $appView;
        }

        // 2. Try Package-level theme
        $packageView = "falcon-cms::themes.{$activeTheme}.{$view}";
        if (view()->exists($packageView)) {
            return $packageView;
        }

        // 3. Fallback to Lazy Theme (Package)
        $falconView = "falcon-cms::themes.falcon-theme.{$view}";
        if (view()->exists($falconView)) {
            return $falconView;
        }

        // 4. If still not found and we have a fallback, try resolving the fallback
        if ($fallback && $fallback !== $view) {
            return $this->resolveThemeView($fallback);
        }

        // Final desperation: Return the falconView name anyway, but it might still fail if even the base doesn't exist
        return $falconView;
    }

    public function index($locale = null)
    {
        try {
            $supportedLocales = \FalconCms\Core\Models\Language::where('status', true)->pluck('code')->toArray();
            if ($locale && in_array($locale, $supportedLocales)) {
                app()->setLocale($locale);
            }
        } catch (\Exception $e) {}
        
        $homePageId = get_cms_option('home_page_id');
        
        if ($homePageId) {
            $post = Post::where('id', $homePageId)
                ->where('lang_code', app()->getLocale())
                ->where('status', 'published')
                ->first();
            
            // If not found in current locale, try to find the linked post in this locale
            if (!$post) {
                $originalPost = Post::find($homePageId);
                if ($originalPost) {
                    $post = Post::where('origin_id', $originalPost->id)
                        ->where('lang_code', app()->getLocale())
                        ->first();
                    
                    // Final fallback to original if still not found
                    if (!$post) $post = $originalPost;
                }
            }
            if ($post) {
                view()->share('current_post', $post);
                // If the home page is also the assigned Blog page, show the blog listing.
                $blogPageId = get_cms_option('blog_page_id');
                if ($blogPageId && $post->id == $blogPageId) {
                    return view($this->resolveThemeView('index'), compact('post'));
                }
                $viewName = ($post->type === 'page') ? 'page' : 'single';
                return view($this->resolveThemeView($viewName), compact('post'));
            }
        }

        return view($this->resolveThemeView('index'));
    }

    public function archive($slug)
    {
        try {
            $supportedLocales = \FalconCms\Core\Models\Language::where('status', true)->pluck('code')->toArray();
            $firstSegment = request()->segment(1);
            if (in_array($firstSegment, $supportedLocales)) {
                app()->setLocale($firstSegment);
            }
        } catch (\Exception $e) {}
        
        $routeName       = request()->route()->getName();
        $items           = collect();
        $title           = '';
        $archivePostType = 'post'; // 'post' or 'product'

        if (in_array($routeName, ['frontend.category', 'frontend.category.locale'])) {
            $slugs    = explode('/', urldecode($slug));
            $lastSlug = end($slugs);
            try {
                $category   = Category::where('slug', $lastSlug)->firstOrFail();
                $postsQuery = $category->posts()->where('status', 'published');
                $title      = $category->name;
            } catch (\Throwable $e) {
                $term = TaxonomyTerm::where('slug', $lastSlug)->first();
                abort_if(!$term, 404);
                $postsQuery = $term->posts()->where('status', 'published');
                $title      = $term->name;
            }
            $archivePostType = 'post';

        } elseif (in_array($routeName, ['frontend.tag', 'frontend.tag.locale'])) {
            $tagSlugs = explode('/', urldecode($slug));
            $lastSlug = end($tagSlugs);
            try {
                $tag        = Tag::where('slug', $lastSlug)->firstOrFail();
                $postsQuery = $tag->posts()->where('status', 'published');
                $title      = $tag->name;
            } catch (\Throwable $e) {
                $term = TaxonomyTerm::where('slug', $lastSlug)->first();
                abort_if(!$term, 404);
                $postsQuery = $term->posts()->where('status', 'published');
                $title      = $term->name;
            }
            $archivePostType = 'post';

        } elseif (in_array($routeName, ['frontend.product_category', 'frontend.product_category.locale'])) {
            $slugs    = explode('/', urldecode($slug));
            $lastSlug = end($slugs);

            // Try the dedicated ProductCategory table first, fall back to ACPT taxonomy_terms
            try {
                $category   = \FalconCms\Core\Models\ProductCategory::where('slug', $lastSlug)->firstOrFail();
                $postsQuery = $category->posts()->where('status', 'published');
                $title      = $category->name;
            } catch (\Throwable $e) {
                $term = TaxonomyTerm::where('slug', $lastSlug)
                    ->where('taxonomy_slug', 'like', 'product%')
                    ->first();
                abort_if(!$term, 404);
                $postsQuery = $term->posts()->where('status', 'published');
                $title      = $term->name;
            }
            $archivePostType = 'product';

        } elseif (in_array($routeName, ['frontend.product_tag', 'frontend.product_tag.locale'])) {
            // Try the dedicated ProductTag table first, fall back to ACPT taxonomy_terms
            try {
                $tag        = \FalconCms\Core\Models\ProductTag::where('slug', $slug)->firstOrFail();
                $postsQuery = $tag->posts()->where('status', 'published');
                $title      = $tag->name;
            } catch (\Throwable $e) {
                $term = TaxonomyTerm::where('slug', $slug)
                    ->where(function ($q) {
                        $q->where('taxonomy_slug', 'like', 'product%tag%')
                          ->orWhere('taxonomy_slug', 'like', 'product%');
                    })
                    ->first();
                abort_if(!$term, 404);
                $postsQuery = $term->posts()->where('status', 'published');
                $title      = $term->name;
            }
            $archivePostType = 'product';
        }

        if (isset($postsQuery)) {
            if (request()->has('orderby') && $archivePostType === 'product') {
                $orderby = request('orderby', 'latest');
                switch ($orderby) {
                    case 'price':
                        $postsQuery->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                            ->orderByRaw('COALESCE(shop_products.sale_price, shop_products.price) ASC')
                            ->select('posts.*');
                        break;
                    case 'price-desc':
                        $postsQuery->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                            ->orderByRaw('COALESCE(shop_products.sale_price, shop_products.price) DESC')
                            ->select('posts.*');
                        break;
                    case 'rating':
                        $postsQuery->withCount(['reviews as average_rating' => fn ($q) => $q->select(\Illuminate\Support\Facades\DB::raw('avg(rating)'))])
                            ->orderBy('average_rating', 'desc');
                        break;
                    case 'popularity':
                        $postsQuery->withCount('reviews')->orderBy('reviews_count', 'desc');
                        break;
                    default:
                        $postsQuery->latest();
                }
            } else {
                $postsQuery->latest();
            }
            $items = $postsQuery->paginate(12)->withQueryString();
        }

        return view($this->resolveThemeView('archive'), [
            'posts'           => $items,
            'title'           => $title,
            'type'            => ucfirst($archivePostType),
            'archivePostType' => $archivePostType,
        ]);
    }

    public function authorArchive($id)
    {
        $user            = \App\Models\User::findOrFail($id);
        $archivePostType = request('type', 'post');

        $posts = Post::where('user_id', $id)
            ->where('type', $archivePostType)
            ->where('status', 'published')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view($this->resolveThemeView('archive'), [
            'posts'           => $posts,
            'title'           => $user->name,
            'type'            => 'Author',
            'archivePostType' => $archivePostType,
        ]);
    }

    public function single($typeOrSlug, $slug = null)
    {
        $supportedLocales = [];
        try {
            $supportedLocales = \FalconCms\Core\Models\Language::where('status', true)->pluck('code')->toArray();
        } catch (\Exception $e) {}

        $firstSegment = request()->segment(1);
        $isLocale = in_array($firstSegment, $supportedLocales);
        
        $type = null;
        $postSlug = null;

        if ($isLocale) {
            app()->setLocale($firstSegment);
            $secondSegment = request()->segment(2);
            $thirdSegment = request()->segment(3);
            
            if ($thirdSegment) {
                // URL: /bn/post/slug
                $type = $secondSegment;
                $postSlug = urldecode($thirdSegment);
            } else {
                // URL: /bn/slug
                $postSlug = urldecode($secondSegment);
            }
        } else {
            // URL: /post/slug or /slug
            if ($slug) {
                $type = $typeOrSlug;
                $postSlug = urldecode($slug);
            } else {
                $postSlug = urldecode($typeOrSlug);
            }
        }

        if (!$postSlug) abort(404);

        $homePageId = get_cms_option('home_page_id');

        if ($type && !in_array($type, ['post', 'page'])) {
            // Check if it's a Custom Taxonomy first
            $customTaxonomy = \FalconCms\Core\Models\CustomTaxonomy::where('slug', $type)->first();
            if ($customTaxonomy) {
                $slugs = explode('/', $postSlug);
                $lastSlug = end($slugs);
                $term = \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $type)
                    ->where('slug', $lastSlug)
                    ->firstOrFail();
                $posts           = $term->posts()->where('status', 'published')->where('lang_code', app()->getLocale())->latest()->paginate(12)->withQueryString();
                $title           = $term->name;
                $archivePostType = $term->cpt_slug ?? 'post';
                return view($this->resolveThemeView('archive'), [
                    'posts'           => $posts,
                    'title'           => $title,
                    'type'            => $customTaxonomy->name,
                    'archivePostType' => $archivePostType,
                ]);
            }

            $postType = PostType::where('slug', $type)->first();
            if (!$postType || !$postType->is_active || !$postType->is_public) {
                abort(404);
            }
            
            $post = Post::where('type', $type)
                ->where('slug', $postSlug)
                ->where('lang_code', app()->getLocale())
                ->where('status', 'published')
                ->firstOrFail();
        } else {
            // 1. Check if it's a CPT archive first (e.g. /dramas)
            if (!$type) {
                $postType = PostType::where('slug', $postSlug)->first();
                if ($postType && $postType->is_active && $postType->is_public) {
                $postsQuery = Post::with('productCategories', 'shopData')->where('posts.type', $postType->slug)
                    ->where('posts.lang_code', app()->getLocale())
                    ->where('posts.status', 'published');

                // Dynamic Sorting Logic
                $orderby = request('orderby', 'latest');
                switch ($orderby) {
                    case 'price':
                        $postsQuery->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                            ->orderByRaw('COALESCE(shop_products.sale_price, shop_products.price) ASC')
                            ->select('posts.*');
                        break;
                    case 'price-desc':
                        $postsQuery->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                            ->orderByRaw('COALESCE(shop_products.sale_price, shop_products.price) DESC')
                            ->select('posts.*');
                        break;
                    case 'rating':
                        $postsQuery->withCount(['reviews as average_rating' => function($query) {
                            $query->select(\Illuminate\Support\Facades\DB::raw('avg(rating)'));
                        }])->orderBy('average_rating', 'desc');
                        break;
                    case 'popularity':
                        $postsQuery->withCount('reviews')->orderBy('reviews_count', 'desc');
                        break;
                    case 'latest':
                    default:
                        $postsQuery->latest();
                        break;
                }

                $posts = $postsQuery->paginate(12)->withQueryString();
                    $title = $postType->name;
                    $type = $postType->name;

                    $archiveView = 'archive-' . $postType->slug;
                    $resolvedArchiveView = $this->resolveThemeView($archiveView);
                    
                    if (!view()->exists($resolvedArchiveView)) {
                        $resolvedArchiveView = $this->resolveThemeView('archive');
                    }
                    
                    return view($resolvedArchiveView, compact('posts', 'title', 'type'));
                }
            }

            // 2. If not a CPT archive, check if it's a single post or page
            $postQuery = Post::where('slug', $postSlug)
                ->where('lang_code', app()->getLocale())
                ->where('status', 'published');
            
            if ($type) {
                $postQuery->where('type', $type);
            }
            
            $post = $postQuery->first();
                
            if (!$post) {
                // Try finding by translation slug (old system or linked posts)
                $post = Post::where('slug', $postSlug)
                    ->where('status', 'published')
                    ->first();
                
                if ($post) {
                    // If found in another language, check if there's a translation in current language
                    $translation = Post::where('origin_id', $post->id)
                        ->where('lang_code', app()->getLocale())
                        ->where('status', 'published')
                        ->first();
                    
                    if ($translation) {
                        $post = $translation;
                    } elseif ($post->lang_code !== app()->getLocale()) {
                        // If no translation and codes don't match, still a 404 for this locale
                        $post = null;
                    }
                }
            }

            if (!$post || $post->status !== 'published') {
                abort(404);
            }

            // Validate CPT status if it's a CPT
            if (!in_array($post->type, ['post', 'page'])) {
                $postType = $post->postTypeDefinition;
                if ($postType && (!$postType->is_active || !$postType->is_public)) {
                     abort(404);
                }
            }
        }

        // Redirect to home if this page is set as the static home page (and it's not the locale root)
        if ($homePageId && $post->id == $homePageId && !$isLocale) {
            return redirect('/', 301);
        }

        // 1. Determine base view name (page or single)
        $baseView = ($post->type === 'page') ? 'page' : 'single';
        $viewName = $baseView;

        // 2. If it's a Custom Post Type, try single-{type} first
        if ($post->type !== 'page' && $post->type !== 'post') {
            $viewName = "single-{$post->type}";
            
            // Special check for variable products — detect the "variable" flag in either column
            // (shopData stores it under `type` or `product_type` depending on how it was saved).
            if ($post->type === 'product' && $post->shopData) {
                $sd = $post->shopData;
                if (($sd->type ?? null) === 'variable' || ($sd->product_type ?? null) === 'variable') {
                    $viewName = 'single-product-variable';
                }
            }
        }

        // 3. Resolve the view with fallback
        $fallback = ($post->type === 'product') ? 'single-product' : $baseView;
        $view = $this->resolveThemeView($viewName, $fallback);
        
        // 4. Final override check for slug-specific view (e.g. themes/falcon-theme/my-custom-page-slug.blade.php)
        if (preg_match('/^[a-z0-9-]+$/', $post->slug) && view()->exists($post->slug)) {
            $view = $post->slug;
        }

        view()->share('current_post', $post);

        // Blog page — the assigned page renders the blog post listing (index template)
        $blogPageId = get_cms_option('blog_page_id');
        if ($blogPageId && $post->id == $blogPageId) {
            return view($this->resolveThemeView('index'), compact('post'));
        }

        // Check if this page is assigned as a special Shop Page
        $shopPageId = get_shop_option('shop_shop_page_id');
        $cartPageId = get_shop_option('shop_cart_page_id');
        $checkoutPageId = get_shop_option('shop_checkout_page_id');
        $accountPageId = get_shop_option('shop_account_page_id');

        if ($post->id == $shopPageId) {
            $postsQuery = Post::with('productCategories', 'shopData')->where('posts.type', 'product')
                ->where('posts.lang_code', app()->getLocale())
                ->where('posts.status', 'published');

            $orderby = request('orderby', 'latest');
            switch ($orderby) {
                case 'price':
                    $postsQuery->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                        ->orderByRaw('COALESCE(shop_products.sale_price, shop_products.price) ASC')
                        ->select('posts.*');
                    break;
                case 'price-desc':
                    $postsQuery->join('shop_products', 'posts.id', '=', 'shop_products.post_id')
                        ->orderByRaw('COALESCE(shop_products.sale_price, shop_products.price) DESC')
                        ->select('posts.*');
                    break;
                case 'rating':
                    $postsQuery->withCount(['reviews as average_rating' => function($query) {
                        $query->select(\Illuminate\Support\Facades\DB::raw('avg(rating)'));
                    }])->orderBy('average_rating', 'desc');
                    break;
                case 'popularity':
                    $postsQuery->withCount('reviews')->orderBy('reviews_count', 'desc');
                    break;
                case 'latest':
                default:
                    $postsQuery->latest();
                    break;
            }

            $posts = $postsQuery->paginate(12)->withQueryString();
            $title = $post->title;
            $type = 'Shop';
            return view($this->resolveThemeView('archive-product', 'archive'), compact('posts', 'title', 'type', 'post'));
        }

        if ($post->id == $cartPageId) {
            $cart = session()->get('falcon_cart', []);
            return view($this->resolveThemeView('ecommerce.cart'), compact('cart', 'post'));
        }

        if ($post->id == $checkoutPageId) {
            $cart = session()->get('falcon_cart', []);
            return view($this->resolveThemeView('ecommerce.checkout'), compact('cart', 'post'));
        }

        if ($post->id == $accountPageId) {
            if (!auth()->check()) {
                return view($this->resolveThemeView('ecommerce.account'), [
                    'orders' => null,
                    'post'   => $post,
                ]);
            }
            $ordersQuery = \FalconCms\Core\Models\Order::with(['items.product'])->where('user_id', auth()->id());
            if (request()->filled('s')) {
                $s = request('s');
                $ordersQuery->where(function ($q) use ($s) {
                    $q->where('order_number', 'like', "%{$s}%")
                      ->orWhere('status', 'like', "%{$s}%");
                });
            }
            $orders = $ordersQuery->latest()->paginate(8)->withQueryString();
            return view($this->resolveThemeView('ecommerce.account'), compact('orders', 'post'));
        }

        $post->load('comments.replies');

        return view($view, compact('post'));
    }

    public function search(Request $request)
    {
        try {
            $supportedLocales = \FalconCms\Core\Models\Language::where('status', true)->pluck('code')->toArray();
            $firstSegment = request()->segment(1);
            if (in_array($firstSegment, $supportedLocales)) {
                app()->setLocale($firstSegment);
            }
        } catch (\Exception $e) {}
        
        $query = $request->input('s');
        $title = 'Search results for: ' . ($query ?: 'All');

        $postsQuery = Post::where('status', 'published')->where('lang_code', app()->getLocale());

        // Optional scoping from the Advanced Search element (post types + category).
        $postTypeRaw = (string) $request->input('post_type', '');
        $types = $postTypeRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $postTypeRaw)))) : [];
        $cat = $request->input('cat');
        if (!empty($types)) {
            $postsQuery->whereIn('type', $types);
        }
        if ($cat) {
            if (in_array('product', $types, true)) {
                $postsQuery->whereHas('productCategories', fn($c) => $c->where('product_categories.id', $cat));
            } else {
                $postsQuery->whereHas('categories', fn($c) => $c->where('categories.id', $cat));
            }
        }

        if ($query) {
            $postsQuery->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%");
            });
        }

        $posts = $postsQuery->latest()->paginate(12)->withQueryString();
            
        $type = 'Search';
        return view($this->resolveThemeView('archive'), compact('posts', 'title', 'type'));
    }

    /**
     * Live (AJAX) search for the Advanced Search builder element.
     * Returns up to 8 matching published posts as JSON, optionally scoped to a
     * post type and a category.
     */
    public function liveSearch(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $postTypeRaw = (string) $request->input('post_type', '');
        $types = $postTypeRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $postTypeRaw)))) : [];
        $cat   = $request->input('cat');

        $query = Post::where('status', 'published')
            ->where('title', 'like', '%' . $q . '%');

        if (!empty($types)) {
            $query->whereIn('type', $types);
        }

        if ($cat) {
            if (in_array('product', $types, true)) {
                $query->whereHas('productCategories', fn($c) => $c->where('product_categories.id', $cat));
            } else {
                $query->whereHas('categories', fn($c) => $c->where('categories.id', $cat));
            }
        }

        $results = $query->latest()->limit(8)->get()->map(function ($p) {
            return [
                'title' => $p->title,
                'url'   => get_falcon_permalink($p),
                'type'  => $p->type,
                'image' => $p->featured_image ? get_falcon_image_url($p->featured_image) : null,
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function storeComment(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'comment' => 'required|string|min:3|max:3000',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        $userId = auth()->id();
        $email = auth()->check() ? auth()->user()->email : ($validated['email'] ?? null);
        $name = auth()->check() ? auth()->user()->name : ($validated['name'] ?? 'Guest');

        // Check if this user/email already has at least one approved comment
        $isApproved = false;
        $query = \FalconCms\Core\Models\Comment::where('is_approved', true);
        
        if ($userId) {
            $isApproved = (clone $query)->where('user_id', $userId)->exists();
        } elseif ($email) {
            $isApproved = (clone $query)->where('email', $email)->exists();
        }

        \FalconCms\Core\Models\Comment::create([
            'post_id' => $validated['post_id'],
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'comment' => $validated['comment'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_approved' => $isApproved
        ]);

        $message = $isApproved ? 'Comment posted successfully.' : 'Your comment is awaiting moderation.';
        return back()->with('success', $message);
    }
    public function robots()
    {
        $content = get_cms_option('robots_txt');
        
        if (!$content) {
            $content = "User-agent: *\nDisallow: /admin/\nAllow: /\n\nSitemap: " . url('/sitemap.xml');
        }

        return response($content, 200)->header('Content-Type', 'text/plain');
    }

    public function setLocale($locale)
    {
        $supportedLocales = \FalconCms\Core\Models\Language::where('status', true)->pluck('code')->toArray();
        if (in_array($locale, $supportedLocales)) {
            session(['locale' => $locale]);
            app()->setLocale($locale);

            // Get previous URL
            $previousUrl = url()->previous();
            $baseUrl = url('/');
            $path = str_replace($baseUrl, '', $previousUrl);
            $path = ltrim($path, '/');
            
            // Find actual default language from DB
            $defaultLang = 'en';
            try {
                $dbDefault = \Illuminate\Support\Facades\DB::table('cms_languages')->where('is_default', true)->value('code');
                if ($dbDefault) $defaultLang = $dbDefault;
            } catch (\Exception $e) {}

            $segments = explode('/', $path);
            if (isset($segments[0]) && in_array($segments[0], $supportedLocales)) {
                // Replace existing locale prefix
                $segments[0] = $locale;
                return redirect($baseUrl . '/' . implode('/', $segments));
            } else {
                // Add new locale prefix if not present (except for root /)
                if (empty($path)) {
                    return redirect($baseUrl . ($locale === $defaultLang ? '' : '/' . $locale));
                }
                return redirect($baseUrl . '/' . $locale . '/' . $path);
            }
        }
        return back();
    }

    public function submitForm(\Illuminate\Http\Request $request)
    {
        try {
            $form = \FalconCms\Core\Models\Form::findOrFail($request->input('form_id'));

            // Turnstile verification
            if (!empty($form->settings['turnstile_enabled'])) {
                $secretKey = get_cms_option('turnstile_secret_key', '');
                if ($secretKey) {
                    $token = $request->input('cf-turnstile-response', '');
                    if (!$token) {
                        return response()->json(['success' => false, 'message' => 'Please complete the security check.'], 422);
                    }
                    $verify = \Illuminate\Support\Facades\Http::asForm()->post(
                        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                        ['secret' => $secretKey, 'response' => $token, 'remoteip' => $request->ip()]
                    );
                    if (!($verify->json('success') ?? false)) {
                        return response()->json(['success' => false, 'message' => 'Security check failed. Please try again.'], 422);
                    }
                }
            }

            // Honeypot check — bots fill in this hidden field, real users never see it
            if ($request->filled('_lf_hp_' . $form->id)) {
                return response()->json([
                    'success' => true,
                    'message' => $form->settings['success_message'] ?? 'Thank you! Your message has been sent.',
                ]);
            }

            // Build submission data from all fields except internal fields
            $data = $request->except(['_token', 'form_id', '_lf_hp_' . $form->id, 'cf-turnstile-response']);

            // Handle file uploads — store and replace value with path
            foreach ($request->allFiles() as $key => $file) {
                try {
                    $data[$key] = $file->store('form-uploads', 'public');
                } catch (\Exception $e) {
                    $data[$key] = null;
                }
            }

            \FalconCms\Core\Models\FormSubmission::create([
                'form_id'    => $form->id,
                'data'       => $data,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send email notification if configured
            $notifyEmail = $form->settings['notify_email'] ?? null;
            if ($notifyEmail && filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $submittedAt = now()->format('d M Y, H:i');
                    $ip          = $request->ip();

                    // Build label map from form field definitions
                    $labelMap = [];
                    foreach ($form->fields ?? [] as $f) {
                        if (!empty($f['name'])) {
                            $labelMap[$f['name']] = $f['label'] ?? ucwords(str_replace('_', ' ', $f['name']));
                        }
                    }

                    // Build rows array for the email view
                    $rows = [];
                    foreach ($data as $key => $value) {
                        $label  = $labelMap[$key] ?? ucwords(str_replace('_', ' ', $key));
                        $val    = is_array($value) ? implode(', ', $value) : (string) $value;
                        $isFile = str_starts_with($val, 'form-uploads/');
                        $rows[] = [
                            'label'    => $label,
                            'is_file'  => $isFile,
                            'is_empty' => !$isFile && trim($val) === '',
                            'display'  => $isFile
                                ? url('storage/' . $val)
                                : nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8')),
                        ];
                    }

                    $tplData    = json_decode(get_cms_option('email_template_form_notification', '{}'), true) ?: [];
                    $subjectTpl = $tplData['subject'] ?? 'New Submission: {{form_name}}';
                    $subject    = str_replace('{{form_name}}', $form->title, $subjectTpl);
                    $introText  = $tplData['intro'] ?? 'You have received a new submission. Review the details below to follow up promptly.';
                    $footerText = $tplData['footer'] ?? 'This is an automated notification — no reply is needed.';

                    \Illuminate\Support\Facades\Mail::send(
                        'falcon-cms::emails.form.notification',
                        compact('form', 'rows', 'submittedAt', 'ip', 'introText', 'footerText'),
                        function ($msg) use ($notifyEmail, $subject) {
                            $msg->to($notifyEmail)->subject($subject);
                        }
                    );
                } catch (\Exception $e) {}
            }

            return response()->json([
                'success' => true,
                'message' => $form->settings['success_message'] ?? 'Thank you! Your message has been sent.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }
}
