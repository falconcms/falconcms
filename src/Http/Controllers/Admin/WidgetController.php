<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Widget;
use FalconCms\Core\Models\PostType;
use Illuminate\Http\Request;

class WidgetController extends Controller
{
    public function index()
    {
        $widgetAreas = [
            'primary-sidebar' => 'Primary Sidebar',
            'footer-1' => 'Footer Column 1',
            'footer-2' => 'Footer Column 2',
            'footer-3' => 'Footer Column 3',
            'footer-4' => 'Footer Column 4',
        ];

        $availableWidgets = [
            'search' => [
                'name' => 'Search Bar',
                'description' => 'A simple search form for your site.',
                'settings' => ['placeholder' => 'Search...']
            ],
            'recent_posts' => [
                'name' => 'Recent Posts',
                'description' => 'Displays a list of your most recent posts.',
                'settings' => ['limit' => 5]
            ],
            'categories' => [
                'name' => 'Categories List',
                'description' => 'Displays a list of post categories.',
                'settings' => []
            ],
            'custom_html' => [
                'name' => 'Custom HTML',
                'description' => 'Add arbitrary HTML code.',
                'settings' => ['content' => '']
            ],
            'social_media' => [
                'name' => 'Social Media',
                'description' => 'Display links to your social media profiles.',
                'settings' => []
            ],
            'text' => [
                'name' => 'Text',
                'description' => 'Display rich formatted text content.',
                'settings' => ['content' => '']
            ],
            'nav_menu' => [
                'name' => 'Navigation Menu',
                'description' => 'Display any navigation menu in a widget area.',
                'settings' => ['menu_id' => '']
            ],
            'image' => [
                'name' => 'Image',
                'description' => 'Display an image with an optional clickable link.',
                'settings' => ['image_url' => '', 'link_url' => '', 'link_target' => '_self', 'alt_text' => '', 'caption' => '']
            ],
        ];

        // Scan active theme for custom widgets
        $activeTheme = get_cms_option('active_theme', 'lazy-theme');
        $themeWidgetPath = base_path("vendor/tareqcodex/lazy-cms-rebuild/resources/views/themes/{$activeTheme}/widgets");
        
        if (is_dir($themeWidgetPath)) {
            $files = scandir($themeWidgetPath);
            foreach ($files as $file) {
                if (str_ends_with($file, '.blade.php')) {
                    $slug = str_replace('.blade.php', '', $file);
                    if (!isset($availableWidgets[$slug])) {
                        $availableWidgets[$slug] = [
                            'name' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                            'description' => "Custom widget provided by {$activeTheme} theme.",
                            'settings' => []
                        ];
                    }
                }
            }
        }

        $activeWidgets = Widget::orderBy('order')->get()->groupBy('area');

        // Active hierarchical (category) taxonomies for CPTs
        $cptCatTaxonomies = \Illuminate\Support\Facades\DB::table('custom_taxonomies')
            ->where('hierarchical', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get();

        $allActivePostTypes = PostType::where('is_active', true)
            ->whereNotIn('slug', ['page', 'lazy_header', 'lazy_footer'])
            ->orderBy('name')
            ->pluck('name', 'slug');

        // Categories widget: only show post types that have active categories assigned
        $postTypesWithCategories = PostType::where('is_active', true)
            ->whereNotIn('slug', ['page', 'lazy_header', 'lazy_footer'])
            ->orderBy('name')
            ->get()
            ->filter(function ($pt) use ($cptCatTaxonomies) {
                if ($pt->slug === 'post') {
                    return \FalconCms\Core\Models\Category::exists();
                }
                if ($pt->slug === 'product') {
                    return \FalconCms\Core\Models\ProductCategory::exists();
                }
                $taxSlugs = $cptCatTaxonomies
                    ->filter(fn($t) => in_array($pt->slug, json_decode($t->post_types ?? '[]', true)))
                    ->pluck('slug');
                if ($taxSlugs->isEmpty()) return false;
                return \FalconCms\Core\Models\TaxonomyTerm::whereIn('taxonomy_slug', $taxSlugs)->exists();
            })
            ->pluck('name', 'slug');

        $menus = \FalconCms\Core\Models\NavigationMenu::orderBy('name')->get(['id', 'name', 'slug']);

        return view('falcon-cms::admin.widgets.index', compact(
            'widgetAreas', 'availableWidgets', 'activeWidgets',
            'allActivePostTypes', 'postTypesWithCategories', 'menus'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'area' => 'required|string',
            'type' => 'required|string',
        ]);

        $order = Widget::where('area', $request->area)->max('order') + 1;

        $widget = Widget::create([
            'area' => $request->area,
            'type' => $request->type,
            'title' => ucwords(str_replace('_', ' ', $request->type)),
            'settings' => [],
            'order' => $order,
        ]);

        return back()->with('success', 'Widget added successfully!');
    }

    public function update(Request $request, Widget $widget)
    {
        $data = $request->only(['title', 'settings', 'order']);
        $data['is_active'] = $request->boolean('is_active') ? 1 : 0;
        $widget->update($data);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status' => 'success', 'message' => 'Widget updated successfully!']);
        }

        return back()->with('success', 'Widget updated successfully!');
    }

    public function destroy(Widget $widget)
    {
        $widget->delete();
        return back()->with('success', 'Widget removed successfully!');
    }

    public function updateOrder(Request $request)
    {
        foreach ($request->widgets as $data) {
            Widget::where('id', $data['id'])->update(['order' => $data['order'], 'area' => $data['area']]);
        }
        return response()->json(['status' => 'success']);
    }
}
