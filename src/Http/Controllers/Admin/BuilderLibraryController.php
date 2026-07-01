<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BuilderLibraryController extends Controller
{
    const OPTION_KEY = 'lazy_builder_library';
    const GLOBAL_SECTIONS_KEY = 'lazy_global_sections';
    const MEGA_MENUS_KEY = 'lazy_mega_menus';

    private function getLibrary(): array
    {
        $raw = get_cms_option(self::OPTION_KEY, null);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
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
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    private function getMegaMenus(): array
    {
        $raw = get_cms_option(self::MEGA_MENUS_KEY, null);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

    public function page()
    {
        $library    = $this->getLibrary();
        $postCards  = $this->getPostCards();
        $megaMenus  = $this->getMegaMenus();
        return view('falcon-cms::admin.falcon-builder.library', compact('library', 'postCards', 'megaMenus'));
    }

    public function saveMegaMenu(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'config' => 'nullable|array',
        ]);

        $menus = $this->getMegaMenus();
        $menu  = [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'name'       => $request->input('name'),
            'config'     => $request->input('config') ?? [],
            'created_at' => now()->format('Y-m-d H:i'),
        ];
        array_unshift($menus, $menu);
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));
        return response()->json(['success' => true, 'menu' => $menu]);
    }

    public function editMegaMenuBuilder(string $id)
    {
        $menus    = $this->getMegaMenus();
        $megaMenu = collect($menus)->firstWhere('id', $id);
        if (!$megaMenu) abort(404);

        $customElements   = apply_falcon_filters('falcon_builder_elements', []);
        $bodyRaw          = get_cms_option('theme_typography_body');
        $headingRaw       = get_cms_option('theme_typography_h1');
        $bodyFont         = is_array($bodyRaw)    ? $bodyRaw    : json_decode((string)$bodyRaw,    true);
        $headingFont      = is_array($headingRaw) ? $headingRaw : json_decode((string)$headingRaw, true);
        $themeBodyFont    = $bodyFont['family']    ?? null;
        $themeHeadingFont = $headingFont['family'] ?? null;

        return view('falcon-cms::admin.falcon-builder.mega-menu-builder', compact(
            'megaMenu', 'customElements', 'themeBodyFont', 'themeHeadingFont'
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
            'width_type'   => 'required|in:site_width,full_width,custom',
            'custom_width' => 'nullable|integer|min:200|max:3000',
        ]);
        $menus = $this->getMegaMenus();
        foreach ($menus as &$menu) {
            if ($menu['id'] === $id) {
                $menu['config']['settings'] = [
                    'width_type'   => $validated['width_type'],
                    'custom_width' => (int)($validated['custom_width'] ?? 1200),
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
            if ($menu['id'] === $id) { $menu['name'] = $request->input('name'); break; }
        }
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));
        return response()->json(['success' => true]);
    }

    public function deleteMegaMenu(string $id)
    {
        $menus = $this->getMegaMenus();
        $menus = array_values(array_filter($menus, fn($m) => $m['id'] !== $id));
        update_cms_option(self::MEGA_MENUS_KEY, json_encode($menus));
        return response()->json(['success' => true]);
    }

    public function savePostCard(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'config' => 'nullable|array',
        ]);

        $cards  = $this->getPostCards();
        $card   = [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'name'       => $request->input('name'),
            'config'     => $request->input('config') ?? [],
            'created_at' => now()->format('Y-m-d H:i'),
        ];
        array_unshift($cards, $card);
        update_cms_option('falcon_post_cards', json_encode($cards));
        return response()->json(['success' => true, 'card' => $card]);
    }

    public function editPostCardBuilder(string $id)
    {
        $cards = $this->getPostCards();
        $postCard = collect($cards)->firstWhere('id', $id);
        if (!$postCard) abort(404);

        $customElements  = apply_falcon_filters('falcon_builder_elements', []);
        $bodyRaw         = get_cms_option('theme_typography_body');
        $headingRaw      = get_cms_option('theme_typography_h1');
        $bodyFont        = is_array($bodyRaw)    ? $bodyRaw    : json_decode((string)$bodyRaw,    true);
        $headingFont     = is_array($headingRaw) ? $headingRaw : json_decode((string)$headingRaw, true);
        $themeBodyFont   = $bodyFont['family']    ?? null;
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
            if ($card['id'] === $id) { $card['name'] = $request->input('name'); break; }
        }
        update_cms_option('falcon_post_cards', json_encode($cards));
        return response()->json(['success' => true]);
    }

    public function deletePostCard(string $id)
    {
        $cards = $this->getPostCards();
        $cards = array_values(array_filter($cards, fn($c) => $c['id'] !== $id));
        update_cms_option('falcon_post_cards', json_encode($cards));
        return response()->json(['success' => true]);
    }

    // ───────────────────────── Import / Export ─────────────────────────

    public function exportPostCard(string $id)
    {
        $card = collect($this->getPostCards())->firstWhere('id', $id);
        if (!$card) abort(404);
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
        if (!$menu) abort(404);
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
            '_type'       => $type,
            'version'     => 1,
            'exported_at' => now()->toIso8601String(),
            'item'        => [
                'name'   => $item['name']   ?? '',
                'config' => $item['config'] ?? [],
            ],
        ];
        $filename = (\Illuminate\Support\Str::slug($item['name'] ?? $suffix) ?: $suffix) . '-' . $suffix . '.json';

        return response()->json(
            $payload,
            200,
            ['Content-Disposition' => 'attachment; filename="' . $filename . '"'],
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
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'name'       => trim((string) ($item['name'] ?? '')) ?: 'Imported',
            'config'     => is_array($item['config'] ?? null) ? $item['config'] : [],
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
        $type    = $request->input('type');

        $item = [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'name'       => $request->input('name'),
            'created_at' => now()->format('Y-m-d H:i'),
            'data'       => $request->input('data'),
        ];

        array_unshift($library[$type], $item);
        update_cms_option(self::OPTION_KEY, json_encode($library));

        return response()->json(['success' => true, 'item' => $item]);
    }

    // ── Global Sections ──────────────────────────────────────────────────────

    private function getGlobalSections(): array
    {
        $raw = get_cms_option(self::GLOBAL_SECTIONS_KEY, null);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
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
        $section  = [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'name'       => $request->input('name'),
            'data'       => $request->input('data'),
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
                if ($request->has('name')) $section['name'] = $request->input('name');
                if ($request->has('data')) $section['data'] = $request->input('data');
                break;
            }
        }
        update_cms_option(self::GLOBAL_SECTIONS_KEY, json_encode($sections));
        return response()->json(['success' => true]);
    }

    public function deleteGlobalSection(string $id)
    {
        $sections = $this->getGlobalSections();
        $sections = array_values(array_filter($sections, fn($s) => $s['id'] !== $id));
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
        $library[$type] = array_values(array_filter($library[$type], fn($i) => $i['id'] !== $id));
        update_cms_option(self::OPTION_KEY, json_encode($library));

        return response()->json(['success' => true]);
    }
}
