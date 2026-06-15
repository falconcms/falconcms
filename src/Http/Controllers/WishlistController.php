<?php

namespace FalconCms\Core\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use FalconCms\Core\Models\Wishlist;
use FalconCms\Core\Models\Product;

class WishlistController extends Controller
{
    /**
     * Add / remove a product from the logged-in user's wishlist (AJAX).
     */
    public function toggle(Request $request)
    {
        if (!auth()->check()) {
            return response()->json([
                'success'       => false,
                'requires_login' => true,
                'login_url'     => route('admin.login'),
                'message'       => 'Please log in to use your wishlist.',
            ], 200);
        }

        $request->validate(['product_id' => 'required|integer']);
        $productId = (int) $request->product_id;
        $userId    = auth()->id();

        $existing = Wishlist::where('user_id', $userId)->where('product_id', $productId)->first();
        if ($existing) {
            $existing->delete();
            $added = false;
        } else {
            Wishlist::create(['user_id' => $userId, 'product_id' => $productId]);
            $added = true;
        }

        return response()->json([
            'success' => true,
            'added'   => $added,
            'count'   => Wishlist::where('user_id', $userId)->count(),
            'message' => $added ? 'Added to your wishlist.' : 'Removed from your wishlist.',
        ]);
    }

    /**
     * Wishlist page — saved products for the logged-in user.
     */
    public function index()
    {
        if (!auth()->check()) {
            session()->put('url.intended', url()->current());
            return redirect()->route('admin.login')->with('error', 'Please log in to view your wishlist.');
        }

        $productIds = Wishlist::where('user_id', auth()->id())->latest()->pluck('product_id')->all();

        // Preserve wishlist order (newest first) while only keeping published products.
        $products = collect();
        if (!empty($productIds)) {
            $found = Product::where('type', 'product')->where('status', 'published')
                ->whereIn('id', $productIds)->with('shopData')->get()->keyBy('id');
            $products = collect($productIds)->map(fn ($id) => $found->get($id))->filter()->values();
        }

        $post = null;
        return view($this->resolveWishlistView(), compact('products', 'post'));
    }

    /**
     * Remove a single product from the wishlist (non-AJAX form on the wishlist page).
     */
    public function remove(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('admin.login');
        }
        $request->validate(['product_id' => 'required|integer']);
        Wishlist::where('user_id', auth()->id())->where('product_id', (int) $request->product_id)->delete();
        return redirect()->route('shop.wishlist')->with('success', 'Removed from your wishlist.');
    }

    private function resolveWishlistView(): string
    {
        $theme = get_cms_option('active_theme', 'falcon-theme');
        $app   = "themes.{$theme}.ecommerce.wishlist";
        if (view()->exists($app)) return $app;
        $pkg = "falcon-cms::themes.{$theme}.ecommerce.wishlist";
        if (view()->exists($pkg)) return $pkg;
        return 'falcon-cms::themes.falcon-theme.ecommerce.wishlist';
    }
}
