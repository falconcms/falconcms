<?php

namespace FalconCms\Core\Http\Controllers;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\Tag;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

class SitemapController extends Controller
{
    /**
     * The sitemap is what search engines fetch, so it has to survive whatever is in the
     * database. A single row without a slug — or a post type whose archive route a site
     * has removed — must not take the whole file down with a 500.
     */
    public function index()
    {
        $posts = collect();
        $categories = collect();
        $tags = collect();

        // All active post types (builtin + custom) — dynamic
        $allPostTypes = PostType::where('is_active', true)->get();
        foreach ($allPostTypes as $pt) {
            if (get_cms_option('sitemap_include_'.$pt->slug, '1') == '1') {
                $ptPosts = Post::where('type', $pt->slug)
                    ->where('status', 'published')
                    // A row with no slug would emit the site root as its <loc>, listing the
                    // home page once per such row.
                    ->whereNotNull('slug')
                    ->where('slug', '!=', '')
                    // Only what the template reads: a sitemap on a large site should not
                    // hydrate every column of every post.
                    ->select('id', 'slug', 'updated_at', 'created_at')
                    ->latest()
                    ->get();
                $posts = $posts->merge($ptPosts);
            }
        }

        // 3. Categories
        if (get_cms_option('sitemap_include_categories', '1') == '1' && Route::has('frontend.category')) {
            $categories = Category::has('posts')->whereNotNull('slug')->where('slug', '!=', '')->get();
        }

        // 4. Tags
        if (get_cms_option('sitemap_include_tags', '0') == '1' && Route::has('frontend.tag')) {
            $tags = Tag::has('posts')->whereNotNull('slug')->where('slug', '!=', '')->get();
        }

        $xml = view('falcon-cms::frontend.sitemap', compact('posts', 'categories', 'tags'))->render();

        return Response::make($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
