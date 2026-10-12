<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Settings → Bulk SEO: every page's SEO title, meta description, focus keyword and noindex on
 * one screen, filtered to what is missing. Only the rows changed are saved, through the model,
 * so whatever listens to a post being saved (IndexNow, Search Console's change log) still hears it.
 */
class BulkSeoController extends Controller
{
    public const PER_PAGE = 50;

    /** Types that are never pages of their own (layouts, sections, cards…). */
    private const LAYOUT_TYPES = ['falcon_content', 'falcon_header', 'falcon_footer', 'falcon_ptb', 'lazy_header', 'lazy_footer'];

    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('manage_settings'), 403);

        $types = $this->types();
        $type = (string) $request->query('type', '');
        $missing = (string) $request->query('missing', '');
        $search = trim((string) $request->query('q', ''));

        $posts = Post::query()
            ->whereIn('status', ['published', 'draft', 'scheduled'])
            ->whereIn('type', $type !== '' && isset($types[$type]) ? [$type] : array_keys($types))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            // seo_meta is JSON written by the model: an empty or absent key has no '"key":"' + a character.
            ->when(in_array($missing, ['title', 'description', 'focus_keyword'], true),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('seo_meta')->orWhere('seo_meta', 'not like', '%"'.$missing.'":"_%')))
            ->when($missing === 'any', fn ($q) => $q->where(fn ($w) => $w->whereNull('seo_meta')
                ->orWhere('seo_meta', 'not like', '%"title":"_%')
                ->orWhere('seo_meta', 'not like', '%"description":"_%')))
            ->orderByDesc('updated_at')
            ->select(['id', 'title', 'slug', 'type', 'status', 'lang_code', 'seo_meta', 'updated_at'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('falcon-cms::admin.settings.seo-bulk', compact('posts', 'types', 'type', 'missing', 'search'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('manage_settings'), 403);

        $data = $request->validate([
            'rows' => 'array|max:'.self::PER_PAGE,
            'rows.*.seo_title' => 'nullable|string|max:255',
            'rows.*.description' => 'nullable|string|max:500',
            'rows.*.focus_keyword' => 'nullable|string|max:191',
            'rows.*.noindex' => 'nullable|in:0,1',
        ]);

        $saved = 0;
        foreach ($data['rows'] ?? [] as $id => $row) {
            $post = Post::find((int) $id);
            if (!$post) {
                continue;
            }
            $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
            $before = $seo;
            foreach (['seo_title' => 'title', 'description' => 'description', 'focus_keyword' => 'focus_keyword'] as $field => $key) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                $value = trim((string) $row[$field]);
                // Empty means "use the default" (the page title, the excerpt), as in the editor.
                if ($value === '') {
                    unset($seo[$key]);
                } else {
                    $seo[$key] = $value;
                }
            }
            if (array_key_exists('noindex', $row)) {
                if ($row['noindex'] === '1') {
                    $seo['noindex'] = '1';
                } else {
                    unset($seo['noindex']);
                }
            }
            if ($seo !== $before) {
                $post->seo_meta = $seo;
                $post->save();
                $saved++;
            }
        }

        return back()->with('success', $saved === 1 ? '1 page saved.' : $saved.' pages saved.');
    }

    /** @return array<string, string> slug => name, for every type that has pages of its own */
    private function types(): array
    {
        $types = ['page' => 'Pages', 'post' => 'Posts'];
        try {
            foreach (PostType::where('is_active', true)->where('is_public', true)->orderBy('name')->get(['slug', 'name']) as $pt) {
                if (!in_array($pt->slug, self::LAYOUT_TYPES, true) && falcon_post_type_available($pt->slug)) {
                    $types[$pt->slug] = $pt->name;
                }
            }
        } catch (Throwable $e) {
        }

        return $types;
    }
}
