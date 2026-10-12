<?php

namespace FalconCms\Core\Http\Controllers;

use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Support\SearchVisibility;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Throwable;

class SitemapController extends Controller
{
    /**
     * Addresses per file. A small site keeps one sitemap.xml; past this, sitemap.xml becomes an
     * index of sitemap-1.xml, sitemap-2.xml… (the protocol allows 50,000 per file, but a file of
     * that size is slow to build on shared hosting and slow for a crawler to fetch).
     */
    public const PER_FILE = 1000;

    /** Images listed per address, the featured image first. */
    private const IMAGES_PER_URL = 10;

    /**
     * The sitemap is what search engines fetch, so it has to survive whatever is in the
     * database. A single row without a slug — or a post type whose archive route a site
     * has removed — must not take the whole file down with a 500.
     */
    public function index()
    {
        // A site hidden from search engines has nothing to offer them.
        abort_if(SearchVisibility::hidden(), 404);

        $entries = $this->entries();
        if (count($entries) <= self::PER_FILE) {
            return $this->xml('falcon-cms::frontend.sitemap', ['entries' => $this->withImages($entries)]);
        }

        $files = [];
        foreach (array_chunk($entries, self::PER_FILE) as $i => $chunk) {
            $dates = array_filter(array_column($chunk, 'lastmod'));
            $files[] = ['loc' => route('frontend.sitemap.part', $i + 1), 'lastmod' => $dates ? max($dates) : null];
        }

        return $this->xml('falcon-cms::frontend.sitemap-index', ['files' => $files]);
    }

    /** One file of a large site's sitemap: sitemap-2.xml holds addresses 1001 to 2000. */
    public function part(int $part)
    {
        abort_if(SearchVisibility::hidden(), 404);

        $chunk = array_slice($this->entries(), ($part - 1) * self::PER_FILE, self::PER_FILE);
        abort_if($part < 1 || $chunk === [], 404);

        return $this->xml('falcon-cms::frontend.sitemap', ['entries' => $this->withImages($chunk)]);
    }

    /**
     * Every address, in order: home, posts of each included type (newest first), categories,
     * tags. Light: no content is read here.
     *
     * @return list<array{loc: string, lastmod: ?string, changefreq: string, priority: string, post_id?: int, images?: list<string>}>
     */
    private function entries(): array
    {
        $entries = [['loc' => url('/'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '1.0']];
        // The home page is the sitemap's first entry already, at its real address (/).
        $homePageId = (int) get_cms_option('home_page_id', 0);

        // All active post types (builtin + custom) — dynamic
        foreach (PostType::where('is_active', true)->get() as $pt) {
            // A plugin-owned type (products) is left out while its plugin is off.
            if (get_cms_option('sitemap_include_'.$pt->slug, '1') != '1' || !falcon_post_type_available($pt->slug)) {
                continue;
            }
            $posts = Post::where('type', $pt->slug)
                ->where('status', 'published')
                // A row with no slug would emit the site root as its <loc>, listing the
                // home page once per such row.
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                // Only what the address needs: a sitemap on a large site should not hydrate
                // every column of every post. type and lang_code build the address the site
                // itself links to (and names as canonical): /post/slug, /bn/slug.
                ->select('id', 'slug', 'type', 'lang_code', 'seo_meta', 'updated_at', 'created_at')
                ->latest()
                ->get();

            foreach ($posts as $post) {
                // A page that asks not to be indexed does not belong in the list of pages to index.
                if ((($post->seo_meta['noindex'] ?? '0') == '1') || apply_falcon_filters('falcon_seo_noindex', false, $post)
                    || ($homePageId && (int) $post->id === $homePageId)) {
                    continue;
                }
                // lastmod is optional in the sitemap spec, and a row can reach here without
                // timestamps (imported content, a raw INSERT). Emitted only when there is one.
                $modified = $post->updated_at ?? $post->created_at;
                $entries[] = ['loc' => get_falcon_permalink($post), 'lastmod' => $modified?->toAtomString(),
                    'changefreq' => 'weekly', 'priority' => '0.8', 'post_id' => (int) $post->id];
            }
        }

        if (get_cms_option('sitemap_include_categories', '1') == '1' && Route::has('frontend.category')) {
            foreach (Category::has('posts')->whereNotNull('slug')->where('slug', '!=', '')->get() as $category) {
                $entries[] = ['loc' => route('frontend.category', $category->slug), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.6'];
            }
        }

        if (get_cms_option('sitemap_include_tags', '0') == '1' && Route::has('frontend.tag')) {
            foreach (Tag::has('posts')->whereNotNull('slug')->where('slug', '!=', '')->get() as $tag) {
                $entries[] = ['loc' => route('frontend.tag', $tag->slug), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.4'];
            }
        }

        return $entries;
    }

    /**
     * The images on each post's page, for Google Images: the featured image, then images in the
     * content (HTML <img>, and image fields of builder JSON). Read only for the addresses in
     * this file.
     */
    private function withImages(array $entries): array
    {
        $ids = array_filter(array_column($entries, 'post_id'));
        if ($ids === []) {
            return $entries;
        }
        try {
            $posts = Post::whereIn('id', $ids)->get(['id', 'featured_image', 'content'])->keyBy('id');
        } catch (Throwable $e) {
            return $entries;
        }

        foreach ($entries as &$entry) {
            $post = $posts[$entry['post_id'] ?? 0] ?? null;
            if ($post) {
                $entry['images'] = $this->images($post);
            }
        }

        return $entries;
    }

    private function images($post): array
    {
        $images = [];
        if (!empty($post->featured_image)) {
            $images[] = asset('storage/'.ltrim((string) $post->featured_image, '/'));
        }
        $content = str_replace('\\/', '/', (string) $post->content);
        $ext = '\.(?:jpe?g|png|webp|gif|avif)(?:\?[^"\'\s]*)?';
        preg_match_all('#<img\b[^>]*?\bsrc=["\']([^"\']+'.$ext.')["\']#i', $content, $html);
        preg_match_all('#"(?:src|url|image|imageUrl|img|bgImage|backgroundImage)"\s*:\s*"([^"]+'.$ext.')"#i', $content, $json);
        foreach (array_merge($html[1], $json[1]) as $src) {
            $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($src, 'data:')) {
                continue;
            }
            if (!preg_match('#^https?://#i', $src)) {
                $src = str_starts_with($src, '//') ? 'https:'.$src : (str_starts_with($src, '/') || str_starts_with($src, 'storage/') ? url($src) : asset('storage/'.$src));
            }
            $images[] = $src;
        }

        return array_slice(array_values(array_unique($images)), 0, self::IMAGES_PER_URL);
    }

    private function xml(string $view, array $data)
    {
        return Response::make(view($view, $data)->render(), 200, ['Content-Type' => 'application/xml']);
    }
}
