<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\PostType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FalconBuilderController extends Controller
{
    /** The section slots a layout is composed of, and the post type that backs each. */
    public const SLOTS = [
        'header'         => ['type' => 'falcon_header',  'label' => 'Header',         'renders' => true],
        'page_title_bar' => ['type' => 'falcon_ptb',     'label' => 'Page Title Bar', 'renders' => false],
        'content'        => ['type' => 'falcon_content', 'label' => 'Content',        'renders' => false],
        'footer'         => ['type' => 'falcon_footer',  'label' => 'Footer',         'renders' => true],
    ];

    private function authorize(): void
    {
        if (!auth()->user()->hasPermission('manage_settings')) {
            abort(403);
        }
    }

    /** Flush the full-page cache so layout/section changes show immediately. */
    private function bustCache(): void
    {
        if (function_exists('clear_page_cache')) {
            clear_page_cache();
        }
    }

    // ── Global Layout assignment (option: falcon_layout_global) ──────────────

    private function globalAssignments(): array
    {
        $raw = get_cms_option('falcon_layout_global', null);
        $a = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($a) ? $a : [];
    }

    // ── Custom layouts (option: falcon_layouts) ──────────────────────────────

    private function customLayouts(): array
    {
        $raw = get_cms_option('falcon_layouts', null);
        $l = is_string($raw) ? json_decode($raw, true) : $raw;
        return is_array($l) ? array_values(array_filter($l, 'is_array')) : [];
    }

    private function saveCustomLayouts(array $layouts): void
    {
        update_cms_option('falcon_layouts', json_encode(array_values($layouts)));
        $this->bustCache();
    }

    private function assignmentsFor(string $layout): array
    {
        if ($layout === 'global') return $this->globalAssignments();
        foreach ($this->customLayouts() as $l) {
            if (($l['id'] ?? null) === $layout) {
                return is_array($l['assignments'] ?? null) ? $l['assignments'] : [];
            }
        }
        return [];
    }

    /**
     * Normalise a stored assignment value into ['id'=>int,'active'=>bool].
     * Legacy assignments were a bare section id (always active); new ones store an
     * ['id','active'] pair so each layout's slot has its own on/off state.
     */
    public static function assignEntry($value): ?array
    {
        if (is_array($value) && !empty($value['id'])) {
            return ['id' => (int) $value['id'], 'active' => !array_key_exists('active', $value) || (bool) $value['active']];
        }
        if (is_numeric($value) && (int) $value > 0) {
            return ['id' => (int) $value, 'active' => true];
        }
        return null;
    }

    private function assign(string $slot, ?int $sectionId, string $layout = 'global'): void
    {
        if ($layout === 'global') {
            $a = $this->globalAssignments();
            if ($sectionId) $a[$slot] = ['id' => $sectionId, 'active' => true]; else unset($a[$slot]);
            update_cms_option('falcon_layout_global', json_encode($a));
            $this->bustCache();
            return;
        }

        $layouts = $this->customLayouts();
        foreach ($layouts as &$l) {
            if (($l['id'] ?? null) === $layout) {
                if (!isset($l['assignments']) || !is_array($l['assignments'])) $l['assignments'] = [];
                if ($sectionId) $l['assignments'][$slot] = ['id' => $sectionId, 'active' => true]; else unset($l['assignments'][$slot]);
            }
        }
        unset($l);
        $this->saveCustomLayouts($layouts);
    }

    /** Flip the active (on/off) state of one layout's slot — independent of other layouts. */
    private function setSlotActive(string $layout, string $slot, ?bool $active = null): bool
    {
        if ($layout === 'global') {
            $a = $this->globalAssignments();
            $entry = self::assignEntry($a[$slot] ?? null);
            if (!$entry) return false;
            $entry['active'] = $active ?? !$entry['active'];
            $a[$slot] = $entry;
            update_cms_option('falcon_layout_global', json_encode($a));
            $this->bustCache();
            return $entry['active'];
        }

        $newState = false;
        $layouts = $this->customLayouts();
        foreach ($layouts as &$l) {
            if (($l['id'] ?? null) === $layout && is_array($l['assignments'] ?? null)) {
                $entry = self::assignEntry($l['assignments'][$slot] ?? null);
                if ($entry) {
                    $entry['active'] = $active ?? !$entry['active'];
                    $l['assignments'][$slot] = $entry;
                    $newState = $entry['active'];
                }
            }
        }
        unset($l);
        $this->saveCustomLayouts($layouts);
        return $newState;
    }

    private function activePostTypes()
    {
        return PostType::query()
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('post_types', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('id')->get();
    }

    /** Custom taxonomies available on the site (built-ins + ACPT), for archive conditions. */
    private function taxonomyCatalogue(): array
    {
        $tax = [];
        $tax[] = ['key' => 'category', 'name' => 'Categories'];
        $tax[] = ['key' => 'post_tag', 'name' => 'Tags'];
        if (\Illuminate\Support\Facades\Schema::hasTable('product_categories')) {
            $tax[] = ['key' => 'product_category', 'name' => 'Product Categories'];
            $tax[] = ['key' => 'product_tag', 'name' => 'Product Tags'];
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('custom_taxonomies')) {
            foreach (\FalconCms\Core\Models\CustomTaxonomy::query()
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('custom_taxonomies', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->orderBy('name')->get() as $t) {
                if (in_array($t->slug, ['product_category', 'product_tag', 'category', 'post_tag'], true)) continue;
                $tax[] = ['key' => $t->slug, 'name' => $t->name];
            }
        }
        return $tax;
    }

    /**
     * The tabbed catalogue of condition targets for the Layout Conditions modal.
     * Each tab: ['key','label','blocks'=>[…]] where a block is either
     *   ['type'=>'toggle','target','label'] or
     *   ['type'=>'group','label','source'=>['kind','key'],'search'=>bool].
     * All options are generated dynamically from the site's post types & taxonomies.
     */
    private function conditionTabs(): array
    {
        $tabs = [];
        $byName = [];
        foreach ($this->activePostTypes() as $pt) $byName[$pt->slug] = $pt;

        $page = $byName['page'] ?? null;
        $post = $byName['post'] ?? null;

        $toggle = fn ($t, $l) => ['type' => 'toggle', 'target' => $t, 'label' => $l];
        $group  = fn ($label, $kind, $key = null, $search = true) => ['type' => 'group', 'label' => $label, 'source' => ['kind' => $kind, 'key' => $key], 'search' => $search];

        // Pages
        $tabs[] = ['key' => 'page', 'label' => $page->name ?? 'Pages', 'blocks' => [
            $toggle('all:page', 'All ' . ($page->name ?? 'Pages')),
            $toggle('home', 'Front Page'),
            $group('Specific ' . ($page->name ?? 'Pages'), 'post_type', 'page'),
        ]];
        // Posts
        $tabs[] = ['key' => 'post', 'label' => $post->name ?? 'Posts', 'blocks' => [
            $toggle('all:post', 'All ' . ($post->name ?? 'Posts')),
            $toggle('archive:post', ($post->name ?? 'Posts') . ' Archive'),
            $group('Specific ' . ($post->name ?? 'Posts'), 'post_type', 'post'),
        ]];

        // Archives (fully dynamic)
        $archiveBlocks = [$toggle('all_archives', 'All Archives Pages')];
        foreach ($byName as $slug => $pt) {
            if ($slug === 'page') continue; // pages aren't archived
            $archiveBlocks[] = $toggle('archive:' . $slug, $pt->name . ' Archive Types');
        }
        $archiveBlocks[] = $toggle('author_archive', 'All Author Pages');
        $archiveBlocks[] = $group('Specific Author Page', 'author');
        foreach ($this->taxonomyCatalogue() as $t) {
            $archiveBlocks[] = $toggle('tax:' . $t['key'], 'All ' . $t['name']);
            $archiveBlocks[] = $group('Specific ' . $t['name'], 'taxonomy', $t['key']);
        }
        $archiveBlocks[] = $toggle('search', 'Search Results');
        $tabs[] = ['key' => 'archives', 'label' => 'Archives', 'blocks' => $archiveBlocks];

        // One tab per remaining custom post type.
        foreach ($byName as $slug => $pt) {
            if (in_array($slug, ['page', 'post'], true)) continue;
            $tabs[] = ['key' => $slug, 'label' => $pt->name, 'blocks' => [
                $toggle('all:' . $slug, 'All ' . $pt->name),
                $toggle('archive:' . $slug, $pt->name . ' Archive'),
                $group('Specific ' . $pt->name, 'post_type', $slug),
            ]];
        }

        // Other
        $tabs[] = ['key' => 'other', 'label' => 'Other', 'blocks' => [
            $toggle('entire_site', 'Entire Site'),
            $toggle('search', 'Search Results'),
            $toggle('404', '404 Not Found Page'),
        ]];

        return $tabs;
    }

    /** Human label for a stored condition target (resolves specific post/term/author titles). */
    private function targetLabel(string $target): string
    {
        static $ptNames = null, $taxNames = null;
        if ($ptNames === null) {
            $ptNames = $this->activePostTypes()->pluck('name', 'slug')->toArray();
            $taxNames = [];
            foreach ($this->taxonomyCatalogue() as $t) $taxNames[$t['key']] = $t['name'];
        }
        if ($target === 'entire_site')   return 'Entire Site';
        if ($target === 'home')          return 'Front Page';
        if ($target === 'search')        return 'Search Results';
        if ($target === '404')           return '404 Not Found Page';
        if ($target === 'all_archives')  return 'All Archives';
        if ($target === 'author_archive')return 'All Author Pages';
        if (str_starts_with($target, 'all:'))     return 'All ' . ($ptNames[substr($target, 4)] ?? ucfirst(substr($target, 4)));
        if (str_starts_with($target, 'archive:')) return ($ptNames[substr($target, 8)] ?? ucfirst(substr($target, 8))) . ' Archive';
        if (str_starts_with($target, 'post:'))    return optional(Post::find((int) substr($target, 5)))->title ?: ('Item #' . substr($target, 5));
        if (str_starts_with($target, 'author:'))  return $this->authorName((int) substr($target, 7));
        if (str_starts_with($target, 'tax:'))     return 'All ' . ($taxNames[substr($target, 4)] ?? ucfirst(substr($target, 4)));
        if (str_starts_with($target, 'term:')) {
            [$tax, $id] = array_pad(explode(':', substr($target, 5), 2), 2, null);
            return $this->termName((string) $tax, (int) $id);
        }
        return $target;
    }

    private function authorName(int $id): string
    {
        $model = config('auth.providers.users.model', \App\Models\User::class);
        return class_exists($model) ? (optional($model::find($id))->name ?: ('User #' . $id)) : ('User #' . $id);
    }

    private function termName(string $taxonomy, int $id): string
    {
        try {
            return match ($taxonomy) {
                'category'         => optional(\FalconCms\Core\Models\Category::find($id))->name,
                'post_tag'         => optional(\FalconCms\Core\Models\Tag::find($id))->name,
                'product_category' => optional(\FalconCms\Core\Models\ProductCategory::find($id))->name,
                'product_tag'      => optional(\FalconCms\Core\Models\ProductTag::find($id))->name,
                default            => optional(\FalconCms\Core\Models\TaxonomyTerm::find($id))->name,
            } ?: ('Term #' . $id);
        } catch (\Throwable $e) {
            return 'Term #' . $id;
        }
    }

    /** Normalise + attach labels to a layout's stored conditions for the UI. */
    private function conditionsForUi($conditions): array
    {
        $out = [];
        foreach (falcon_normalize_conditions($conditions) as $c) {
            $out[] = ['mode' => $c['mode'], 'target' => $c['target'], 'label' => $this->targetLabel($c['target'])];
        }
        return $out;
    }

    // ── Screen ───────────────────────────────────────────────────────────────

    public function index()
    {
        $this->authorize();

        // Sections available per slot (for the pickers).
        $sections = [];
        foreach (self::SLOTS as $slot => $meta) {
            $sections[$slot] = Post::where('type', $meta['type'])->orderBy('title')->get(['id', 'title', 'status', 'updated_at']);
        }

        // Build the layout list: Global first, then customs. Each slot resolves to
        // the assigned section (or null) plus this layout's own active flag for it.
        $resolveAssigned = function (array $assignments, bool $withFallback) use ($sections) {
            $assignedOut = [];
            $activeOut = [];
            foreach (self::SLOTS as $slot => $meta) {
                $current = null;
                $active = true;
                $entry = self::assignEntry($assignments[$slot] ?? null);
                if ($entry) {
                    $current = $sections[$slot]->firstWhere('id', $entry['id']);
                    $active = $entry['active'];
                }
                if (!$current && $withFallback) {
                    // Legacy fallback: adopt the first published section (shown active).
                    $current = $sections[$slot]->firstWhere('status', 'published');
                }
                $assignedOut[$slot] = $current;
                $activeOut[$slot] = $active;
            }
            return ['assigned' => $assignedOut, 'active' => $activeOut];
        };

        $layouts = [];
        $g = $resolveAssigned($this->globalAssignments(), true);
        $layouts[] = [
            'id'         => 'global',
            'name'       => 'Global Layout',
            'is_global'  => true,
            'assigned'   => $g['assigned'],
            'active'     => $g['active'],
            'conditions' => [],
        ];
        foreach ($this->customLayouts() as $l) {
            $r = $resolveAssigned(is_array($l['assignments'] ?? null) ? $l['assignments'] : [], false);
            $layouts[] = [
                'id'            => $l['id'] ?? '',
                'name'          => $l['name'] ?? 'Layout',
                'is_global'     => false,
                'assigned'      => $r['assigned'],
                'active'        => $r['active'],
                'conditions'    => is_array($l['conditions'] ?? null) ? $l['conditions'] : [],
                'conditions_ui' => $this->conditionsForUi($l['conditions'] ?? []),
            ];
        }

        $conditionTabs = $this->conditionTabs();

        return view('falcon-cms::admin.falcon-builder.sections', compact('layouts', 'sections', 'conditionTabs'));
    }

    // ── Sections (New / Existing) ─────────────────────────────────────────────

    public function createSection(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'slot'   => 'required|in:' . implode(',', array_keys(self::SLOTS)),
            'layout' => 'nullable|string|max:64',
            'name'   => 'required|string|max:255',
        ]);
        $layout = $data['layout'] ?: 'global';
        $meta = self::SLOTS[$data['slot']];

        $base = Str::slug($data['name']) ?: $data['slot'];
        $slug = $base;
        $i = 1;
        while (Post::where('type', $meta['type'])->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $section = Post::create([
            'title'       => $data['name'],
            'slug'        => $slug,
            'type'        => $meta['type'],
            'status'      => 'published',
            'user_id'     => auth()->id(),
            'editor_type' => 'builder',
            'lang_code'   => app()->getLocale(),
        ]);

        $this->assign($data['slot'], $section->id, $layout);

        // AJAX: create in place (no navigation). The UI shows the new section
        // assigned to the slot; the user clicks it later to open the builder.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'section' => [
                    'id'       => $section->id,
                    'title'    => $section->title,
                    'status'   => $section->status,
                    'edit_url' => route('admin.falcon-builder', $section->id),
                ],
                'message' => 'Section “' . $section->title . '” created.',
            ]);
        }

        return redirect()->route('admin.falcon-builder', $section->id);
    }

    public function assignSection(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'slot'       => 'required|in:' . implode(',', array_keys(self::SLOTS)),
            'layout'     => 'nullable|string|max:64',
            'section_id' => 'required|integer|exists:posts,id',
        ]);
        $layout = $data['layout'] ?: 'global';
        $meta = self::SLOTS[$data['slot']];

        $section = Post::where('id', $data['section_id'])->where('type', $meta['type'])->firstOrFail();
        if ($section->status !== 'published') {
            $section->update(['status' => 'published']);
        }
        $this->assign($data['slot'], $section->id, $layout);

        return back()->with('success', $meta['label'] . ' section “' . $section->title . '” is now active.');
    }

    public function clearSection(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'slot'   => 'required|in:' . implode(',', array_keys(self::SLOTS)),
            'layout' => 'nullable|string|max:64',
        ]);
        $this->assign($data['slot'], null, $data['layout'] ?: 'global');

        return back()->with('success', self::SLOTS[$data['slot']]['label'] . ' reset.');
    }

    /** Permanently delete a section and detach it from every layout that used it. */
    public function deleteSection(Request $request)
    {
        $this->authorize();

        $data = $request->validate(['section_id' => 'required|integer|exists:posts,id']);
        $types = array_column(self::SLOTS, 'type');
        $section = Post::whereIn('type', $types)->find($data['section_id']);
        if (!$section) {
            return back()->with('error', 'That is not a layout section.');
        }

        $this->unassignEverywhere($section->id);
        $title = $section->title;
        $section->delete();

        return back()->with('success', 'Section “' . $title . '” deleted.');
    }

    /** Remove a section id from the Global Layout and every custom layout. */
    private function unassignEverywhere(int $sectionId): void
    {
        $matches = fn ($v) => optional(self::assignEntry($v))['id'] === $sectionId;

        $global = array_filter($this->globalAssignments(), fn ($v) => !$matches($v));
        update_cms_option('falcon_layout_global', json_encode($global));

        $layouts = $this->customLayouts();
        foreach ($layouts as &$l) {
            if (!empty($l['assignments']) && is_array($l['assignments'])) {
                $l['assignments'] = array_filter($l['assignments'], fn ($v) => !$matches($v));
            }
        }
        unset($l);
        $this->saveCustomLayouts($layouts);
    }

    // ── Custom layouts CRUD ────────────────────────────────────────────────────

    public function createLayout(Request $request)
    {
        $this->authorize();

        $data = $request->validate(['name' => 'required|string|max:255']);
        $layouts = $this->customLayouts();
        $layouts[] = [
            'id'          => 'lay_' . Str::lower(Str::random(8)),
            'name'        => $data['name'],
            'conditions'  => [],
            'assignments' => [],
        ];
        $this->saveCustomLayouts($layouts);

        return back()->with('success', 'Layout “' . $data['name'] . '” created. Assign sections and set its conditions.');
    }

    /** AJAX: rename a custom layout (inline edit). Returns JSON. */
    public function renameLayout(Request $request)
    {
        $this->authorize();

        $data = $request->validate(['id' => 'required|string', 'name' => 'required|string|max:255']);
        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['ok' => false, 'message' => 'Name cannot be empty.'], 422);
        }

        $found = false;
        $layouts = $this->customLayouts();
        foreach ($layouts as &$l) {
            if (($l['id'] ?? null) === $data['id']) { $l['name'] = $name; $found = true; }
        }
        unset($l);
        if (!$found) return response()->json(['ok' => false, 'message' => 'Layout not found.'], 404);
        $this->saveCustomLayouts($layouts);

        return response()->json(['ok' => true, 'name' => $name]);
    }

    public function deleteLayout(Request $request)
    {
        $this->authorize();

        $data = $request->validate(['id' => 'required|string']);
        $layouts = array_filter($this->customLayouts(), fn ($l) => ($l['id'] ?? null) !== $data['id']);
        $this->saveCustomLayouts($layouts);

        return back()->with('success', 'Layout deleted.');
    }

    /** AJAX: replace a layout's condition set (each: {mode, target}). Returns JSON. */
    public function saveConditions(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'id'             => 'required|string',
            'conditions'     => 'array',
            'conditions.*.mode'   => 'required|in:include,exclude',
            'conditions.*.target' => ['required', 'string', 'regex:/^(entire_site|home|search|404|all_archives|author_archive|all:[a-z0-9_\-]+|archive:[a-z0-9_\-]+|taxonomy:[a-z0-9_\-]+|tax:[a-z0-9_\-]+|post:\d+|author:\d+|term:[a-z0-9_\-]+:\d+)$/i'],
        ]);

        // De-duplicate on (mode,target).
        $seen = [];
        $conditions = [];
        foreach ($data['conditions'] ?? [] as $c) {
            $key = $c['mode'] . '|' . $c['target'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $conditions[] = ['mode' => $c['mode'], 'target' => $c['target']];
        }

        $found = false;
        $layouts = $this->customLayouts();
        foreach ($layouts as &$l) {
            if (($l['id'] ?? null) === $data['id']) { $l['conditions'] = $conditions; $found = true; }
        }
        unset($l);
        if (!$found) return response()->json(['ok' => false, 'message' => 'Layout not found.'], 404);
        $this->saveCustomLayouts($layouts);

        return response()->json([
            'ok'         => true,
            'conditions' => $this->conditionsForUi($conditions),
        ]);
    }

    /**
     * AJAX: search individual items for the "Specific …" condition lists.
     * kind = post_type | author | taxonomy (with key = post-type slug / taxonomy slug).
     */
    public function conditionItems(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'kind' => 'nullable|string',
            'key'  => 'nullable|string',
            's'    => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $kind = $data['kind'] ?? 'post_type';
        $key  = $data['key'] ?? ($request->input('post_type', 'post'));
        $s    = $data['s'] ?? '';
        $page = $data['page'] ?? 1;
        $perPage = 12;

        if ($kind === 'author') {
            $model = config('auth.providers.users.model', \App\Models\User::class);
            $query = $model::query();
            if ($s !== '') $query->where('name', 'like', '%' . $s . '%');
            $paginator = $query->orderBy('name')->paginate($perPage, ['id', 'name'], 'page', $page);
            $items = collect($paginator->items())->map(fn ($u) => ['target' => 'author:' . $u->id, 'label' => $u->name ?: ('User #' . $u->id)])->all();
        } elseif ($kind === 'taxonomy') {
            $paginator = $this->taxonomyTermQuery($key, $s)->paginate($perPage, ['id', 'name'], 'page', $page);
            $items = collect($paginator->items())->map(fn ($t) => ['target' => 'term:' . $key . ':' . $t->id, 'label' => $t->name ?: ('Term #' . $t->id)])->all();
        } else { // post_type
            $query = Post::where('type', $key);
            if ($s !== '') $query->where('title', 'like', '%' . $s . '%');
            $paginator = $query->orderBy('title')->paginate($perPage, ['id', 'title'], 'page', $page);
            $items = collect($paginator->items())->map(fn ($p) => ['target' => 'post:' . $p->id, 'label' => $p->title ?: ('Item #' . $p->id)])->all();
        }

        return response()->json(['items' => $items, 'has_more' => $paginator->hasMorePages()]);
    }

    private function taxonomyTermQuery(string $taxonomy, string $s)
    {
        $q = match ($taxonomy) {
            'category'         => \FalconCms\Core\Models\Category::query(),
            'post_tag'         => \FalconCms\Core\Models\Tag::query(),
            'product_category' => \FalconCms\Core\Models\ProductCategory::query(),
            'product_tag'      => \FalconCms\Core\Models\ProductTag::query(),
            default            => \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $taxonomy),
        };
        if ($s !== '') $q->where('name', 'like', '%' . $s . '%');
        return $q->orderBy('name');
    }


    /**
     * Toggle a single layout-slot on/off. This is stored PER LAYOUT, so turning a
     * slot off in the Global Layout never affects a custom layout that happens to
     * use the same section (and vice-versa).
     */
    public function toggleSlot(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'layout' => 'required|string|max:64',
            'slot'   => 'required|in:' . implode(',', array_keys(self::SLOTS)),
        ]);

        $active = $this->setSlotActive($data['layout'], $data['slot']);
        $label = self::SLOTS[$data['slot']]['label'];
        $message = $label . ($active ? ' activated for this layout.' : ' deactivated for this layout.');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'active' => $active, 'message' => $message]);
        }
        return back()->with('success', $message);
    }
}
