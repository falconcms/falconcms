<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Support\OffCanvas;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class BuilderLibraryController extends Controller
{
    const OPTION_KEY = 'falcon_builder_library';

    const GLOBAL_SECTIONS_KEY = 'falcon_global_sections';

    const MEGA_MENUS_KEY = 'falcon_mega_menus';

    /**
     * What these settings were called before the rename.
     *
     * A migration moves the rows across, but a site can be running this code before its
     * migrations have run — composer pulls the package down first. Reading falls back to the
     * old key so a saved library, a set of global sections or a mega menu never disappears
     * during that window. Writing only ever uses the new key.
     */
    const LEGACY_KEYS = [
        self::OPTION_KEY => 'lazy_builder_library',
        self::GLOBAL_SECTIONS_KEY => 'lazy_global_sections',
        self::MEGA_MENUS_KEY => 'lazy_mega_menus',
    ];

    /** Read a setting by its current name, falling back to the name it used to have. */
    private static function option(string $key, $default = null)
    {
        $value = get_cms_option($key, null);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $legacy = self::LEGACY_KEYS[$key] ?? null;

        return $legacy ? get_cms_option($legacy, $default) : $default;
    }

    private function getLibrary(): array
    {
        $raw = self::option(self::OPTION_KEY);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['containers' => [], 'columns' => [], 'nested_columns' => [], 'elements' => []];
    }

    public function index()
    {
        return response()->json($this->getLibrary());
    }

    private function getPostCards(): array
    {
        $raw = get_cms_option('falcon_post_cards', null);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function getMegaMenus(): array
    {
        $raw = self::option(self::MEGA_MENUS_KEY);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function page()
    {
        $library = $this->getLibrary();
        $postCards = $this->getPostCards();
        $megaMenus = $this->getMegaMenus();
        $offCanvases = OffCanvas::all();

        return view('falcon-cms::admin.falcon-builder.library', compact('library', 'postCards', 'megaMenus', 'offCanvases'));
    }

    public function saveMegaMenu(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'config' => 'nullable|array',
        ]);

        $menus = $this->getMegaMenus();
        $menu = [
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'config' => $request->input('config') ?? [],
            'created_at' => now()->format('Y-m-d H:i'),
        ];
        array_unshift($menus, $menu);
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));

        return response()->json(['success' => true, 'menu' => $menu]);
    }

    public function editMegaMenuBuilder(string $id)
    {
        // Editing needs Pro (licensed or grace, not merely grandfathered) — backstop for a
        // direct URL; the Library's Edit button is also disabled in the locked preview.
        if (!falcon_pro_editable('builder_pro')) {
            return response()->view('falcon-cms::pro-required', ['message' => 'This feature is available in the Pro version.'], 200);
        }

        $menus = $this->getMegaMenus();
        $megaMenu = collect($menus)->firstWhere('id', $id);
        if (!$megaMenu) {
            abort(404);
        }

        $customElements = apply_falcon_filters('falcon_builder_elements', []);
        $bodyRaw = get_cms_option('theme_typography_body');
        $headingRaw = get_cms_option('theme_typography_h1');
        $navRaw = get_cms_option('theme_typography_nav');
        $bodyFont = is_array($bodyRaw) ? $bodyRaw : json_decode((string) $bodyRaw, true);
        $headingFont = is_array($headingRaw) ? $headingRaw : json_decode((string) $headingRaw, true);
        $navFont = is_array($navRaw) ? $navRaw : json_decode((string) $navRaw, true);
        $themeBodyFont = $bodyFont['family'] ?? null;
        $themeHeadingFont = $headingFont['family'] ?? null;
        $themeNavFont = $navFont['family'] ?? $themeBodyFont;

        return view('falcon-cms::admin.falcon-builder.mega-menu-builder', compact(
            'megaMenu', 'customElements', 'themeBodyFont', 'themeHeadingFont', 'themeNavFont'
        ));
    }

    public function saveMegaMenuLayout(Request $request, string $id)
    {
        $request->validate(['layout' => 'required|array']);
        $menus = $this->getMegaMenus();
        foreach ($menus as &$menu) {
            if ($menu['id'] === $id) {
                $menu['config']['layout'] = $request->input('layout');
                break;
            }
        }
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));

        return response()->json(['success' => true]);
    }

    public function saveMegaMenuSettings(Request $request, string $id)
    {
        $validated = $request->validate([
            'width_type' => 'required|in:site_width,full_width,custom',
            'custom_width' => 'nullable|integer|min:200|max:3000',
        ]);
        $menus = $this->getMegaMenus();
        foreach ($menus as &$menu) {
            if ($menu['id'] === $id) {
                $menu['config']['settings'] = [
                    'width_type' => $validated['width_type'],
                    'custom_width' => (int) ($validated['custom_width'] ?? 1200),
                ];
                break;
            }
        }
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));

        return response()->json(['success' => true]);
    }

    public function updateMegaMenu(Request $request, string $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $menus = $this->getMegaMenus();
        foreach ($menus as &$menu) {
            if ($menu['id'] === $id) {
                $menu['name'] = $request->input('name');
                break;
            }
        }
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));

        return response()->json(['success' => true]);
    }

    public function deleteMegaMenu(string $id)
    {
        $menus = $this->getMegaMenus();
        $menus = array_values(array_filter($menus, fn ($m) => $m['id'] !== $id));
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));

        return response()->json(['success' => true]);
    }

    public function savePostCard(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'config' => 'nullable|array',
        ]);

        $cards = $this->getPostCards();
        $card = [
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'config' => $request->input('config') ?? [],
            'created_at' => now()->format('Y-m-d H:i'),
        ];
        array_unshift($cards, $card);
        update_cms_option('falcon_post_cards', json_encode($cards));

        return response()->json(['success' => true, 'card' => $card]);
    }

    public function editPostCardBuilder(string $id)
    {
        // Editing needs Pro (licensed or grace, not merely grandfathered) — backstop for a
        // direct URL; the Library's Edit button is also disabled in the locked preview.
        if (!falcon_pro_editable('builder_pro')) {
            return response()->view('falcon-cms::pro-required', ['message' => 'This feature is available in the Pro version.'], 200);
        }

        $cards = $this->getPostCards();
        $postCard = collect($cards)->firstWhere('id', $id);
        if (!$postCard) {
            abort(404);
        }

        $customElements = apply_falcon_filters('falcon_builder_elements', []);
        $bodyRaw = get_cms_option('theme_typography_body');
        $headingRaw = get_cms_option('theme_typography_h1');
        $bodyFont = is_array($bodyRaw) ? $bodyRaw : json_decode((string) $bodyRaw, true);
        $headingFont = is_array($headingRaw) ? $headingRaw : json_decode((string) $headingRaw, true);
        $themeBodyFont = $bodyFont['family'] ?? null;
        $themeHeadingFont = $headingFont['family'] ?? null;

        return view('falcon-cms::admin.falcon-builder.post-card-builder', compact(
            'postCard', 'customElements', 'themeBodyFont', 'themeHeadingFont'
        ));
    }

    public function savePostCardLayout(Request $request, string $id)
    {
        $request->validate(['layout' => 'required|array']);
        $cards = $this->getPostCards();
        foreach ($cards as &$card) {
            if ($card['id'] === $id) {
                $card['config']['layout'] = $request->input('layout');
                break;
            }
        }
        update_cms_option('falcon_post_cards', json_encode($cards));

        return response()->json(['success' => true]);
    }

    public function updatePostCard(Request $request, string $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $cards = $this->getPostCards();
        foreach ($cards as &$card) {
            if ($card['id'] === $id) {
                $card['name'] = $request->input('name');
                break;
            }
        }
        update_cms_option('falcon_post_cards', json_encode($cards));

        return response()->json(['success' => true]);
    }

    public function deletePostCard(string $id)
    {
        $cards = $this->getPostCards();
        $cards = array_values(array_filter($cards, fn ($c) => $c['id'] !== $id));
        update_cms_option('falcon_post_cards', json_encode($cards));

        return response()->json(['success' => true]);
    }

    // ───────────────────────── Off-Canvas ─────────────────────────

    public function saveOffCanvas(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|in:'.implode(',', OffCanvas::POSITIONS),
        ]);

        // A centred panel is a popup: give it popup proportions from the start rather than
        // a 400px-wide sliver, so the first look in the builder is already sensible.
        $position = $request->input('position', 'right');
        $settings = ['position' => $position];
        if ($position === 'center') {
            $settings += ['width' => 600, 'radius' => 12, 'animation' => 'zoom'];
        } elseif (in_array($position, ['top', 'bottom'], true)) {
            $settings += ['width' => 100, 'width_unit' => '%'];
        }

        $panel = OffCanvas::make($request->input('name'), ['settings' => $settings]);
        $items = OffCanvas::all();
        array_unshift($items, $panel);
        OffCanvas::store($items);

        return response()->json([
            'success' => true,
            'offcanvas' => $panel,
            'builder_url' => route('admin.falcon-builder.off-canvas.builder', $panel['id']),
        ]);
    }

    public function editOffCanvasBuilder(string $id)
    {
        // Editing needs Pro (licensed or grace, not merely grandfathered) — backstop for a
        // direct URL; the Library's Edit button is also disabled in the locked preview.
        if (!falcon_pro_editable('builder_pro')) {
            return response()->view('falcon-cms::pro-required', ['message' => 'This feature is available in the Pro version.'], 200);
        }

        $offCanvas = OffCanvas::find($id);
        if (!$offCanvas) {
            abort(404);
        }
        $offCanvas['config']['settings'] = OffCanvas::settings($offCanvas['config']['settings'] ?? []);
        $targetPicker = $this->offCanvasTargetOptions($offCanvas['config']['settings']['auto_targets']);

        $customElements = apply_falcon_filters('falcon_builder_elements', []);
        $bodyRaw = get_cms_option('theme_typography_body');
        $headingRaw = get_cms_option('theme_typography_h1');
        $navRaw = get_cms_option('theme_typography_nav');
        $bodyFont = is_array($bodyRaw) ? $bodyRaw : json_decode((string) $bodyRaw, true);
        $headingFont = is_array($headingRaw) ? $headingRaw : json_decode((string) $headingRaw, true);
        $navFont = is_array($navRaw) ? $navRaw : json_decode((string) $navRaw, true);
        $themeBodyFont = $bodyFont['family'] ?? null;
        $themeHeadingFont = $headingFont['family'] ?? null;
        $themeNavFont = $navFont['family'] ?? $themeBodyFont;

        return view('falcon-cms::admin.falcon-builder.off-canvas-builder', compact(
            'offCanvas', 'targetPicker', 'customElements', 'themeBodyFont', 'themeHeadingFont', 'themeNavFont'
        ));
    }

    /**
     * The "On Pages" picker's fixed entries — the same catalogue the Layout Builder's
     * conditions offer (Front Page, All Pages, Post Archive, All Categories…), flattened into
     * TomSelect options grouped by their tab — plus the entries already chosen, labelled, so
     * a saved "About Us" shows as "About Us" and not "post:12".
     */
    private function offCanvasTargetOptions(array $selected): array
    {
        $conditions = app(FalconBuilderController::class);
        $options = [];
        $groups = [];
        foreach ($conditions->conditionTabs() as $tab) {
            foreach ($tab['blocks'] as $block) {
                if ($block['type'] !== 'toggle' || isset($options[$block['target']])) {
                    continue;
                }
                $groups[$tab['label']] = ['value' => $tab['label'], 'label' => $tab['label']];
                $options[$block['target']] = ['value' => $block['target'], 'text' => $block['label'], 'optgroup' => $tab['label']];
            }
        }
        unset($options['entire_site']); // that is the "Entire site" rule, not a page

        // The entries themselves, under their own type's heading (All Pages, Front Page, then
        // each page), so a specific page, post or product can be picked without searching.
        // Capped per type; typing searches the rest.
        $tabLabels = array_column($conditions->conditionTabs(), 'label', 'key');
        // Only the post types the conditions offer (the active ones) — each has a tab of its own.
        foreach ($tabLabels as $type => $group) {
            if (in_array($type, ['archives', 'other'], true)) {
                continue;
            }
            $groups[$group] ??= ['value' => $group, 'label' => $group];
            \FalconCms\Core\Models\Post::where('type', $type)
                ->orderBy('title')->limit(50)->get(['id', 'title'])
                ->each(function ($p) use (&$options, $group) {
                    $options['post:'.$p->id] ??= ['value' => 'post:'.$p->id, 'text' => $p->title ?: 'Item #'.$p->id, 'optgroup' => $group];
                });
        }

        foreach ($selected as $target) {
            if (isset($options[$target])) {
                continue;
            }
            $options[$target] = str_starts_with($target, 'path:')
                ? ['value' => $target, 'text' => 'URL: '.substr($target, 5), 'optgroup' => 'Custom URL']
                : ['value' => $target, 'text' => $conditions->targetLabel($target), 'optgroup' => 'Selected'];
        }
        $groups['Selected'] = ['value' => 'Selected', 'label' => 'Specific'];
        $groups['Custom URL'] = ['value' => 'Custom URL', 'label' => 'Custom URL'];

        return ['options' => array_values($options), 'optgroups' => array_values($groups)];
    }

    /** Typeahead for the "On Pages" picker: specific entries of any post type, and terms. */
    public function offCanvasTargets(Request $request)
    {
        $q = trim((string) ($request->validate(['q' => 'nullable|string|max:100'])['q'] ?? ''));
        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $conditions = app(FalconBuilderController::class);
        $typeNames = \FalconCms\Core\Models\PostType::pluck('name', 'slug')->all();
        $items = [];

        \FalconCms\Core\Models\Post::query()
            ->where('title', 'like', '%'.$q.'%')
            ->orderBy('title')->limit(25)->get(['id', 'title', 'type'])
            ->each(function ($p) use (&$items, $typeNames) {
                $items[] = ['value' => 'post:'.$p->id, 'text' => $p->title ?: 'Item #'.$p->id,
                    'optgroup' => $typeNames[$p->type] ?? ucfirst((string) $p->type)];
            });

        foreach ($conditions->taxonomyCatalogue() as $tax) {
            try {
                $conditions->taxonomyTermQuery($tax['key'], $q)->limit(10)->get(['id', 'name'])
                    ->each(function ($t) use (&$items, $tax) {
                        $items[] = ['value' => 'term:'.$tax['key'].':'.$t->id, 'text' => $t->name, 'optgroup' => $tax['name']];
                    });
            } catch (\Throwable $e) {
                // A taxonomy whose table is missing on this site simply offers nothing.
            }
        }

        return response()->json(['items' => $items]);
    }

    public function saveOffCanvasLayout(Request $request, string $id)
    {
        $request->validate(['layout' => 'present|array']);
        $found = OffCanvas::modify($id, function ($panel) use ($request) {
            $panel['config']['layout'] = $request->input('layout', []);

            return $panel;
        });

        return response()->json(['success' => $found], $found ? 200 : 404);
    }

    public function saveOffCanvasSettings(Request $request, string $id)
    {
        $request->validate(['settings' => 'required|array']);
        $settings = OffCanvas::settings($request->input('settings'));
        $found = OffCanvas::modify($id, function ($panel) use ($settings) {
            $panel['config']['settings'] = $settings;

            return $panel;
        });

        return response()->json(['success' => $found, 'settings' => $settings], $found ? 200 : 404);
    }

    public function updateOffCanvas(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'enabled' => 'sometimes|boolean',
        ]);
        $found = OffCanvas::modify($id, function ($panel) use ($request) {
            if ($request->has('name')) {
                $panel['name'] = $request->input('name');
            }
            if ($request->has('enabled')) {
                $panel['enabled'] = $request->boolean('enabled');
            }

            return $panel;
        });

        return response()->json(['success' => $found], $found ? 200 : 404);
    }

    public function duplicateOffCanvas(string $id)
    {
        $source = OffCanvas::find($id);
        if (!$source) {
            return response()->json(['success' => false], 404);
        }
        $copy = OffCanvas::make(($source['name'] ?? 'Off-Canvas').' (Copy)', $source['config'] ?? []);
        $copy['enabled'] = $source['enabled'] ?? true;
        $items = OffCanvas::all();
        array_unshift($items, $copy);
        OffCanvas::store($items);

        return response()->json(['success' => true, 'offcanvas' => $copy]);
    }

    public function deleteOffCanvas(string $id)
    {
        OffCanvas::store(array_filter(OffCanvas::all(), fn ($p) => ($p['id'] ?? null) !== $id));

        return response()->json(['success' => true]);
    }

    public function exportOffCanvas(string $id)
    {
        $panel = OffCanvas::find($id);
        if (!$panel) {
            abort(404);
        }

        return $this->downloadLibraryItem('falcon_off_canvas', $panel, 'off-canvas');
    }

    public function importOffCanvas(Request $request)
    {
        $item = $this->readLibraryItem($request, 'falcon_off_canvas');
        if ($item === null) {
            return redirect()->route('admin.falcon-builder.library', ['tab' => 'off_canvas'])
                ->with('error', 'That file is not a valid off-canvas export.');
        }
        $name = trim((string) ($item['name'] ?? '')) ?: 'Imported';
        $config = is_array($item['config'] ?? null) ? $item['config'] : [];
        $items = OffCanvas::all();
        array_unshift($items, OffCanvas::make($name, $config));
        OffCanvas::store($items);

        return redirect()->route('admin.falcon-builder.library', ['tab' => 'off_canvas'])
            ->with('success', 'Off-canvas imported successfully.');
    }

    // ───────────────────────── Import / Export ─────────────────────────

    public function exportPostCard(string $id)
    {
        $card = collect($this->getPostCards())->firstWhere('id', $id);
        if (!$card) {
            abort(404);
        }

        return $this->downloadLibraryItem('falcon_post_card', $card, 'post-card');
    }

    public function importPostCard(Request $request)
    {
        $item = $this->readLibraryItem($request, 'falcon_post_card');
        if ($item === null) {
            return redirect()->route('admin.falcon-builder.library', ['tab' => 'post_cards'])
                ->with('error', 'That file is not a valid post-card export.');
        }
        $cards = $this->getPostCards();
        array_unshift($cards, $this->newLibraryItem($item));
        update_cms_option('falcon_post_cards', json_encode($cards));

        return redirect()->route('admin.falcon-builder.library', ['tab' => 'post_cards'])
            ->with('success', 'Post card imported successfully.');
    }

    public function exportMegaMenu(string $id)
    {
        $menu = collect($this->getMegaMenus())->firstWhere('id', $id);
        if (!$menu) {
            abort(404);
        }

        return $this->downloadLibraryItem('falcon_mega_menu', $menu, 'mega-menu');
    }

    public function importMegaMenu(Request $request)
    {
        $item = $this->readLibraryItem($request, 'falcon_mega_menu');
        if ($item === null) {
            return redirect()->route('admin.falcon-builder.library', ['tab' => 'mega_menus'])
                ->with('error', 'That file is not a valid mega-menu export.');
        }
        $menus = $this->getMegaMenus();
        array_unshift($menus, $this->newLibraryItem($item));
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));

        return redirect()->route('admin.falcon-builder.library', ['tab' => 'mega_menus'])
            ->with('success', 'Mega menu imported successfully.');
    }

    /** Stream a library item (name + config only — no id/timestamps) as a .json download. */
    private function downloadLibraryItem(string $type, array $item, string $suffix)
    {
        $payload = [
            '_type' => $type,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'item' => [
                'name' => $item['name'] ?? '',
                'config' => $item['config'] ?? [],
            ],
        ];
        $filename = (Str::slug($item['name'] ?? $suffix) ?: $suffix).'-'.$suffix.'.json';

        return response()->json(
            $payload,
            200,
            ['Content-Disposition' => 'attachment; filename="'.$filename.'"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /** Validate + parse an uploaded library export. Returns the item array, or null if invalid. */
    private function readLibraryItem(Request $request, string $expectedType): ?array
    {
        $request->validate([
            'library_file' => [
                'required', 'file', 'max:5120',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'json') {
                        $fail('Please upload a .json export file.');
                    }
                },
            ],
        ], ['library_file.max' => 'The file is too large (max 5 MB).']);

        $data = json_decode((string) file_get_contents($request->file('library_file')->getRealPath()), true);

        if (!is_array($data) || ($data['_type'] ?? null) !== $expectedType || empty($data['item']) || !is_array($data['item'])) {
            return null;
        }

        return $data['item'];
    }

    /** Build a fresh library entry (new id + timestamp) from an imported item. */
    private function newLibraryItem(array $item): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => trim((string) ($item['name'] ?? '')) ?: 'Imported',
            'config' => is_array($item['config'] ?? null) ? $item['config'] : [],
            'created_at' => now()->format('Y-m-d H:i'),
        ];
    }

    public function save(Request $request)
    {
        $request->validate([
            'type' => 'required|in:containers,columns,nested_columns,elements',
            'name' => 'required|string|max:255',
            'data' => 'required|array',
        ]);

        $library = $this->getLibrary();
        $type = $request->input('type');

        $item = [
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'created_at' => now()->format('Y-m-d H:i'),
            'data' => $request->input('data'),
        ];

        array_unshift($library[$type], $item);
        update_cms_option(self::OPTION_KEY, json_encode($library));

        return response()->json(['success' => true, 'item' => $item]);
    }

    // ── Global Sections ──────────────────────────────────────────────────────

    private function getGlobalSections(): array
    {
        $raw = self::option(self::GLOBAL_SECTIONS_KEY);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function listGlobalSections()
    {
        return response()->json($this->getGlobalSections());
    }

    public function saveGlobalSection(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'data' => 'required|array',
        ]);

        $sections = $this->getGlobalSections();
        $section = [
            'id' => (string) Str::uuid(),
            'name' => $request->input('name'),
            'data' => $request->input('data'),
            'created_at' => now()->format('Y-m-d H:i'),
        ];
        array_unshift($sections, $section);
        update_cms_option(self::GLOBAL_SECTIONS_KEY, json_encode($sections));

        return response()->json(['success' => true, 'section' => $section]);
    }

    public function updateGlobalSection(Request $request, string $id)
    {
        $sections = $this->getGlobalSections();
        foreach ($sections as &$section) {
            if ($section['id'] === $id) {
                if ($request->has('name')) {
                    $section['name'] = $request->input('name');
                }
                if ($request->has('data')) {
                    $section['data'] = $request->input('data');
                }
                break;
            }
        }
        update_cms_option(self::GLOBAL_SECTIONS_KEY, json_encode($sections));

        return response()->json(['success' => true]);
    }

    public function deleteGlobalSection(string $id)
    {
        $sections = $this->getGlobalSections();
        $sections = array_values(array_filter($sections, fn ($s) => $s['id'] !== $id));
        update_cms_option(self::GLOBAL_SECTIONS_KEY, json_encode($sections));

        return response()->json(['success' => true]);
    }

    // ── Library ──────────────────────────────────────────────────────────────

    public function delete(string $type, string $id)
    {
        if (!in_array($type, ['containers', 'columns', 'nested_columns', 'elements'])) {
            return response()->json(['success' => false], 422);
        }

        $library = $this->getLibrary();
        $library[$type] = array_values(array_filter($library[$type], fn ($i) => $i['id'] !== $id));
        update_cms_option(self::OPTION_KEY, json_encode($library));

        return response()->json(['success' => true]);
    }
}
