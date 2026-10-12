<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The page's place in the site, Home first: Home › Blog › Category › Post. One trail for every
 * breadcrumb the site shows (theme title bar, <x-falcon-breadcrumbs>, the builder element) and
 * for the BreadcrumbList in the page's structured data, so what Google reads is what visitors
 * see. Plugins adjust it through the `falcon_breadcrumbs` filter.
 */
final class Breadcrumbs
{
    /**
     * @return list<array{title: string, url: ?string}> the last item is the current page; empty on the home page
     */
    public static function trail($post = null, ?string $title = null, ?string $homeLabel = null): array
    {
        $path = trim(request()->path(), '/');
        $homeId = (int) get_cms_option('home_page_id', 0);
        if ($path === '' || ($post && $homeId && (int) ($post->id ?? 0) === $homeId)) {
            return [];
        }

        $items = [['title' => $homeLabel ?? __('Home'), 'url' => url('/')]];

        if ($post) {
            $type = (string) ($post->type ?? 'post');
            if ($type === 'post') {
                // The blog page, when the site has one: Home › Blog › …
                $blogId = (int) get_cms_option('blog_page_id', 0);
                if ($blogId && $blog = self::post($blogId)) {
                    $items[] = ['title' => (string) $blog->title, 'url' => get_falcon_permalink($blog)];
                }
                try {
                    $category = $post->categories?->first();
                    if ($category && Route::has('frontend.category')) {
                        $items[] = ['title' => (string) $category->name, 'url' => route('frontend.category', $category->slug)];
                    }
                } catch (Throwable $e) {
                }
            } elseif ($type !== 'page') {
                try {
                    $postType = PostType::where('slug', $type)->first();
                    if ($postType && $postType->is_public) {
                        $items[] = ['title' => (string) $postType->name, 'url' => url('/'.$postType->slug)];
                    }
                } catch (Throwable $e) {
                }
            }
            $items[] = ['title' => (string) ($post->title ?? ''), 'url' => url()->current()];
        } elseif ($title !== null && trim($title) !== '') {
            $items[] = ['title' => trim($title), 'url' => url()->current()];
        } else {
            return [];
        }

        $items = (array) apply_falcon_filters('falcon_breadcrumbs', $items, $post);

        return array_values(array_filter($items, fn ($i) => is_array($i) && trim((string) ($i['title'] ?? '')) !== ''));
    }

    /** schema.org BreadcrumbList for the trail, or null when there is no trail. */
    public static function schema(array $items): ?array
    {
        if (count($items) < 2) {
            return null;
        }
        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => html_entity_decode(strip_tags((string) $item['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'item' => $item['url'] ?? null]);
        }

        return ['@type' => 'BreadcrumbList', '@id' => url()->current().'#breadcrumb', 'itemListElement' => $list];
    }

    private static function post(int $id)
    {
        try {
            return Post::where('id', $id)->where('status', 'published')->first(['id', 'title', 'slug', 'type', 'lang_code']);
        } catch (Throwable $e) {
            return null;
        }
    }
}
