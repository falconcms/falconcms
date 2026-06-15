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
        $raw = get_cms_option('lazy_post_cards', null);
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
        update_cms_option('lazy_post_cards', json_encode($cards));
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
        update_cms_option('lazy_post_cards', json_encode($cards));
        return response()->json(['success' => true]);
    }

    public function updatePostCard(Request $request, string $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $cards = $this->getPostCards();
        foreach ($cards as &$card) {
            if ($card['id'] === $id) { $card['name'] = $request->input('name'); break; }
        }
        update_cms_option('lazy_post_cards', json_encode($cards));
        return response()->json(['success' => true]);
    }

    public function deletePostCard(string $id)
    {
        $cards = $this->getPostCards();
        $cards = array_values(array_filter($cards, fn($c) => $c['id'] !== $id));
        update_cms_option('lazy_post_cards', json_encode($cards));
        return response()->json(['success' => true]);
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
