<?php

namespace FalconCms\Core\Http\Controllers;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Tag;
use Illuminate\Support\Facades\Response;

class SitemapController extends Controller
{
    public function index()
    {
        $posts = collect();
        $categories = collect();
        $tags = collect();

        // All active post types (builtin + custom) — dynamic
        $allPostTypes = PostType::where('is_active', true)->get();
        foreach ($allPostTypes as $pt) {
            if (get_cms_option('sitemap_include_' . $pt->slug, '1') == '1') {
                $ptPosts = Post::where('type', $pt->slug)
                    ->where('status', 'published')
                    ->latest()
                    ->get();
                $posts = $posts->merge($ptPosts);
            }
        }

        // 3. Categories
        if (get_cms_option('sitemap_include_categories', '1') == '1') {
            $categories = Category::has('posts')->get();
        }

        // 4. Tags
        if (get_cms_option('sitemap_include_tags', '0') == '1') {
            $tags = Tag::has('posts')->get();
        }

        $xml = view('falcon-cms::frontend.sitemap', compact('posts', 'categories', 'tags'))->render();

        return Response::make($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
