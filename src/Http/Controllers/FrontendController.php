<?php

namespace Acme\CmsDashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Acme\CmsDashboard\Models\Post;
use Acme\CmsDashboard\Models\PostType;
use Illuminate\Http\Request;

use Acme\CmsDashboard\Models\Category;
use Acme\CmsDashboard\Models\Tag;


class FrontendController extends Controller
{
    protected function resolveThemeView($view, $fallback = null)
    {
        $activeTheme = get_cms_option('active_theme', 'lazy-theme');
        
        // 1. Try App-level theme
        $appView = "themes.{$activeTheme}.{$view}";
        if (view()->exists($appView)) {
            return $appView;
        }

        // 2. Try Package-level theme
        $packageView = "cms-dashboard::themes.{$activeTheme}.{$view}";
        if (view()->exists($packageView)) {
            return $packageView;
        }

        // 3. Fallback to Lazy Theme (Package)
        $lazyView = "cms-dashboard::themes.lazy-theme.{$view}";
        if (view()->exists($lazyView)) {
            return $lazyView;
        }

        // 4. If still not found and we have a fallback, try resolving the fallback
        if ($fallback && $fallback !== $view) {
            return $this->resolveThemeView($fallback);
        }

        // Final desperation: Return the lazyView name anyway, but it might still fail if even the base doesn't exist
        return $lazyView;
    }

    public function index($locale = null)
    {
        try {
            $supportedLocales = \Acme\CmsDashboard\Models\Language::where('status', true)->pluck('code')->toArray();
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
                $viewName = ($post->type === 'page') ? 'page' : 'single';
                view()->share('current_post', $post);
                return view($this->resolveThemeView($viewName), compact('post'));
            }
        }

        return view($this->resolveThemeView('index'));
    }

    public function archive($slug)
    {
        try {
            $supportedLocales = \Acme\CmsDashboard\Models\Language::where('status', true)->pluck('code')->toArray();
            $firstSegment = request()->segment(1);
            if (in_array($firstSegment, $supportedLocales)) {
                app()->setLocale($firstSegment);
            }
        } catch (\Exception $e) {}
        
        $routeName = request()->route()->getName();
        $items = collect();
        $title = '';

        if ($routeName === 'frontend.category') {
            $slugs = explode('/', urldecode($slug));
            $lastSlug = end($slugs);
            $category = Category::where('slug', $lastSlug)->firstOrFail();
            $postsQuery = $category->posts()->where('status', 'published');
            $title = 'Category: ' . $category->name;
        } elseif ($routeName === 'frontend.tag') {
            $tag = Tag::where('slug', $slug)->firstOrFail();
            $postsQuery = $tag->posts()->where('status', 'published');
            $title = 'Tag: ' . $tag->name;
        }

        if (isset($postsQuery)) {
            // Dynamic Sorting Logic for Products in Archives
            if (request()->has('orderby')) {
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
                        $postsQuery->latest();
                        break;
                }
            } else {
                $postsQuery->latest();
            }
            $items = $postsQuery->paginate(12)->withQueryString();
        }

        $type = ($routeName === 'frontend.category') ? 'Category' : 'Tag';
        return view($this->resolveThemeView('archive'), [
            'posts' => $items,
            'title' => $title,
            'type' => $type
        ]);
    }

    public function single($typeOrSlug, $slug = null)
    {
        $supportedLocales = [];
        try {
            $supportedLocales = \Acme\CmsDashboard\Models\Language::where('status', true)->pluck('code')->toArray();
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
            $customTaxonomy = \Acme\CmsDashboard\Models\CustomTaxonomy::where('slug', $type)->first();
            if ($customTaxonomy) {
                $slugs = explode('/', $postSlug);
                $lastSlug = end($slugs);
                $term = \Acme\CmsDashboard\Models\TaxonomyTerm::where('taxonomy_slug', $type)
                    ->where('slug', $lastSlug)
                    ->firstOrFail();
                
                $posts = $term->posts()->where('status', 'published')->where('lang_code', app()->getLocale())->latest()->paginate(12);
                $title = $customTaxonomy->name . ': ' . $term->name;
                $type = $customTaxonomy->name;
                return view($this->resolveThemeView('archive'), compact('posts', 'title', 'type'));
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
                $postsQuery = Post::where('type', $postType->slug)
                    ->where('lang_code', app()->getLocale())
                    ->where('status', 'published');

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
            
            // Special check for variable products
            if ($post->type === 'product' && $post->shopData && $post->shopData->type === 'variable') {
                $viewName = 'single-product-variable';
            }
        }

        // 3. Resolve the view with fallback
        $fallback = ($post->type === 'product') ? 'single-product' : $baseView;
        $view = $this->resolveThemeView($viewName, $fallback);
        
        // 4. Final override check for slug-specific view (e.g. themes/lazy-theme/my-custom-page-slug.blade.php)
        if (preg_match('/^[a-z0-9-]+$/', $post->slug) && view()->exists($post->slug)) {
            $view = $post->slug;
        }

        view()->share('current_post', $post);

        // Check if this page is assigned as a special Shop Page
        $shopPageId = get_shop_option('shop_shop_page_id');
        $cartPageId = get_shop_option('shop_cart_page_id');
        $checkoutPageId = get_shop_option('shop_checkout_page_id');
        $accountPageId = get_shop_option('shop_account_page_id');

        if ($post->id == $shopPageId) {
            $postsQuery = Post::where('type', 'product')
                ->where('lang_code', app()->getLocale())
                ->where('status', 'published');

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
            $cart = session()->get('lazy_cart', []);
            return view($this->resolveThemeView('ecommerce.cart'), compact('cart', 'post'));
        }

        if ($post->id == $checkoutPageId) {
            $cart = session()->get('lazy_cart', []);
            return view($this->resolveThemeView('ecommerce.checkout'), compact('cart', 'post'));
        }

        if ($post->id == $accountPageId) {
            if (!auth()->check()) {
                session()->put('url.intended', url()->current());
                return redirect()->route('admin.login')->with('error', 'Please login to view your account details.');
            }
            $orders = \Acme\CmsDashboard\Models\Order::with(['items.product'])->where('user_id', auth()->id())->latest()->get();
            return view($this->resolveThemeView('ecommerce.account'), compact('orders', 'post'));
        }

        return view($view, compact('post'));
    }

    public function search(Request $request)
    {
        try {
            $supportedLocales = \Acme\CmsDashboard\Models\Language::where('status', true)->pluck('code')->toArray();
            $firstSegment = request()->segment(1);
            if (in_array($firstSegment, $supportedLocales)) {
                app()->setLocale($firstSegment);
            }
        } catch (\Exception $e) {}
        
        $query = $request->input('s');
        $title = 'Search results for: ' . ($query ?: 'All');
        
        $postsQuery = Post::where('status', 'published')->where('lang_code', app()->getLocale());
        
        if ($query) {
            $postsQuery->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%");
            });
        }

        $posts = $postsQuery->latest()->paginate(12);
            
        $type = 'Search';
        return view($this->resolveThemeView('archive'), compact('posts', 'title', 'type'));
    }

    public function storeComment(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'comment' => 'required|string|min:3',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        $userId = auth()->id();
        $email = auth()->check() ? auth()->user()->email : ($validated['email'] ?? null);
        $name = auth()->check() ? auth()->user()->name : ($validated['name'] ?? 'Guest');

        // Check if this user/email already has at least one approved comment
        $isApproved = false;
        $query = \Acme\CmsDashboard\Models\Comment::where('is_approved', true);
        
        if ($userId) {
            $isApproved = (clone $query)->where('user_id', $userId)->exists();
        } elseif ($email) {
            $isApproved = (clone $query)->where('email', $email)->exists();
        }

        \Acme\CmsDashboard\Models\Comment::create([
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
        $supportedLocales = \Acme\CmsDashboard\Models\Language::where('status', true)->pluck('code')->toArray();
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
            $form = \Acme\CmsDashboard\Models\Form::findOrFail($request->input('form_id'));

            // Build submission data from all fields except _token and form_id
            $data = $request->except(['_token', 'form_id']);

            // Handle file uploads — store and replace value with path
            foreach ($request->allFiles() as $key => $file) {
                try {
                    $data[$key] = $file->store('form-uploads', 'public');
                } catch (\Exception $e) {
                    $data[$key] = null;
                }
            }

            \Acme\CmsDashboard\Models\FormSubmission::create([
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
                    $rows = '';
                    foreach ($data as $key => $value) {
                        $label = ucwords(str_replace('_', ' ', $key));
                        $val   = is_array($value) ? implode(', ', $value) : (string) $value;
                        $isFile = str_starts_with($val, 'form-uploads/');
                        $display = $isFile
                            ? '<a href="' . url('storage/' . $val) . '" style="color:#2563eb;">Download File</a>'
                            : nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
                        $rows .= '<tr><td style="padding:8px 12px;font-size:12px;font-weight:600;color:#6b7280;background:#f9fafb;border:1px solid #e5e7eb;width:35%">' . htmlspecialchars($label) . '</td>'
                               . '<td style="padding:8px 12px;font-size:13px;color:#111827;border:1px solid #e5e7eb">' . $display . '</td></tr>';
                    }
                    $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f3f4f6;padding:24px;margin:0">'
                        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)">'
                        . '<div style="background:#2563eb;padding:20px 24px">'
                        . '<h2 style="margin:0;color:#fff;font-size:16px">New Form Submission</h2>'
                        . '<p style="margin:4px 0 0;color:#bfdbfe;font-size:13px">' . htmlspecialchars($form->title) . '</p>'
                        . '</div>'
                        . '<div style="padding:20px 24px">'
                        . '<p style="margin:0 0 4px;font-size:12px;color:#9ca3af">Submitted: <strong style="color:#374151">' . $submittedAt . '</strong> &nbsp;·&nbsp; IP: <strong style="color:#374151">' . $ip . '</strong></p>'
                        . '<table style="width:100%;border-collapse:collapse;margin-top:16px">' . $rows . '</table>'
                        . '</div>'
                        . '<div style="padding:12px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af">Sent by Lazy CMS — do not reply to this email.</div>'
                        . '</div></body></html>';
                    \Illuminate\Support\Facades\Mail::html($html, function ($msg) use ($notifyEmail, $form) {
                        $msg->to($notifyEmail)->subject('New Submission: ' . $form->title);
                    });
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
