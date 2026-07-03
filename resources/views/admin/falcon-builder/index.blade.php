<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $post->title }} | {{ get_cms_option('site_title', 'Falcon Builder') }}</title>
    
    <!-- Meta -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/font-awesome.all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/material-symbols.css') }}" />

    <!-- Tailwind -->
    <script src="{{ asset('vendor/falcon-cms/js/tailwind.min.js') }}"></script>

    <!-- Pickr Color Picker -->
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/pickr.classic.min.css') }}"/>
    <script src="{{ asset('vendor/falcon-cms/js/pickr.min.js') }}"></script>
    <script src="{{ asset('vendor/falcon-cms/js/tinymce.min.js') }}"></script>
    <script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>

    <!-- Tom Select -->
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/tom-select.default.min.css') }}">
    <script src="{{ asset('vendor/falcon-cms/js/tom-select.complete.min.js') }}"></script>
    <style>
        .ts-wrapper { font-size: 13px; }
        .ts-control { border-color: #e2e8f0 !important; border-radius: 6px !important; padding: 4px 8px !important; min-height: 36px; box-shadow: none !important; }
        .ts-control:focus-within { border-color: #0091ea !important; }
        .ts-control .item { background: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #bae6fd !important; border-radius: 4px !important; font-size: 11px !important; font-weight: 600 !important; padding: 1px 6px !important; }
        .ts-control .item .remove { color: #0369a1 !important; border-left: 1px solid #bae6fd !important; }
        .ts-dropdown { border-color: #e2e8f0 !important; border-radius: 6px !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; font-size: 13px !important; z-index: 99999 !important; }
        .ts-dropdown .option:hover, .ts-dropdown .option.active { background: #e0f2fe !important; color: #0369a1 !important; }
        .ts-dropdown .option.selected { background: #f0f9ff !important; color: #0369a1 !important; }
    </style>

    <script>
        window.builderBreakpoints = {
            small: {{ get_cms_option('theme_small_screen_breakpoint', '800') }},
            medium: {{ get_cms_option('theme_medium_screen_breakpoint', '1100') }}
        };

        window.builderPagePadding = {
            top: '{{ get_cms_option('theme_page_padding_top', '60px') }}',
            bottom: '{{ get_cms_option('theme_page_padding_bottom', '60px') }}'
        };

        @php
            // Resolve each dynamic source to the REAL value for the post being edited, so the
            // builder canvas previews actual content (title, excerpt, price, sku…) instead of a
            // generic sample. Empty values fall back to a generic sample client-side.
            $__dynKeys = [
                'post_title','post_excerpt','post_date','post_reading_time','post_id','post_type',
                'post_author','author_bio','site_name','site_tagline','current_date','current_year','user_name',
                'product_price','product_regular_price','product_sale_price','product_sku','product_stock_status','product_stock_quantity',
                'feature_image','author_avatar','logo',
            ];
            $__dynPreview = [];
            foreach ($__dynKeys as $__dk) {
                try { $__dynPreview[$__dk] = (string) (function_exists('falcon_resolve_dynamic_value') ? falcon_resolve_dynamic_value($__dk, $post, []) : ''); }
                catch (\Throwable $e) { $__dynPreview[$__dk] = ''; }
            }
            // When editing a Layout template (PTB/Header/Footer/Content) or a post with no
            // featured image, the Feature Image source resolves to empty — so the canvas can't
            // preview the image layer (e.g. a gradient layered over the feature image). Rather
            // than show an unrelated real post's image (which looks like a "wrong" image), use a
            // neutral placeholder that clearly reads as "feature image" so the layering is still
            // visible. On the frontend each page uses its own real featured image.
            if (empty($__dynPreview['feature_image'])) {
                $__phSvg = "<svg xmlns='http://www.w3.org/2000/svg' width='1600' height='900' viewBox='0 0 1600 900'>"
                    . "<rect width='1600' height='900' fill='#d7dde5'/>"
                    . "<g fill='#a7b1c2'><circle cx='610' cy='330' r='58'/>"
                    . "<path d='M360 650 L690 385 L905 560 L1120 360 L1360 650 Z'/></g>"
                    . "<text x='800' y='770' font-family='Arial, Helvetica, sans-serif' font-size='46' font-weight='700' fill='#8a95a8' text-anchor='middle'>Feature Image</text>"
                    . "</svg>";
                $__dynPreview['feature_image'] = 'data:image/svg+xml;base64,' . base64_encode($__phSvg);
            }
        @endphp
        window.builderDynPreview = {!! json_encode($__dynPreview, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

        @php
            try {
                $allMenus = \Illuminate\Support\Facades\DB::table('navigation_menus')->get();
                $allItems = \Illuminate\Support\Facades\DB::table('navigation_menu_items')
                    ->orderBy('order')
                    ->get();
                
                $menuData = [];
                $menuNames = [];
                foreach($allMenus as $m) {
                    $menuNames[$m->id] = $m->name;
                    $menuItems = $allItems->where('navigation_menu_id', $m->id);
                    $grouped = $menuItems->groupBy('parent_id');
                    
                    $topLevel = $grouped->get(null, collect([]))->map(function($item) use ($grouped) {
                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'url' => $item->url,
                            'icon' => $item->icon ?? '',
                            'show_only_icon' => (bool)($item->show_only_icon ?? false),
                            'children' => $grouped->get($item->id, collect([]))->map(function($child) use ($grouped) {
                                return [
                                    'id' => $child->id,
                                    'title' => $child->title,
                                    'url' => $child->url,
                                    'icon' => $child->icon ?? '',
                                    'show_only_icon' => (bool)($child->show_only_icon ?? false),
                                    'children' => $grouped->get($child->id, collect([]))->map(function($gchild) {
                                        return [
                                            'id' => $gchild->id,
                                            'title' => $gchild->title,
                                            'url' => $gchild->url,
                                            'icon' => $gchild->icon ?? '',
                                            'show_only_icon' => (bool)($gchild->show_only_icon ?? false)
                                        ];
                                    })->values()->toArray()
                                ];
                            })->values()->toArray()
                        ];
                    })->values()->toArray();
                    
                    $menuData[$m->id] = $topLevel;
                }
                $menuDataJson = json_encode($menuData, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
                $menuNamesJson = json_encode($menuNames, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
            } catch(\Exception $e) {
                $menuDataJson = '{}';
                $menuNamesJson = '{}';
            }
        @endphp
        window.falconMenuData = {!! $menuDataJson !!};
        window.falconMenusList = {!! $menuNamesJson !!};
        @php
            $builderPostCards = json_decode(get_cms_option('falcon_post_cards', '[]'), true) ?: [];
        @endphp
        window.falconPostCards = {!! json_encode($builderPostCards, JSON_HEX_TAG) !!};
        @php
            // Layout Builder sections (header/footer/page-title-bar/content) get the dynamic
            // Post elements (Content, Post Meta, Product Meta), just like post cards do.
            $__isLayoutSection = in_array($post->type ?? '', ['falcon_header', 'falcon_footer', 'falcon_ptb', 'falcon_content'], true);
        @endphp
        window.falconLayoutMode = {{ $__isLayoutSection ? 'true' : 'false' }};
        @php
            try {
                $__previewPosts = get_falcon_posts(['limit' => 6, 'order' => 'desc', 'orderby' => 'created_at']);
                $__previewPostsData = $__previewPosts->map(function($p) {
                    $img = $p->featured_image ?? null;
                    if ($img && !str_starts_with($img, 'http')) $img = asset('storage/' . $img);
                    $raw = $p->content ?? '';
                    $excerpt = $p->excerpt ?? (is_array(json_decode($raw, true)) ? '' : mb_substr(strip_tags($raw), 0, 80));
                    return ['title' => $p->title ?? 'Post', 'image' => $img, 'excerpt' => $excerpt];
                })->values()->toArray();
            } catch(\Exception $e) { $__previewPostsData = []; }
        @endphp
        window.falconRecentPosts = {!! json_encode($__previewPostsData, JSON_HEX_TAG) !!};
        @php
            // Built-ins are declared outside try so they survive any DB exception
            $__taxonomies   = [
                ['slug' => 'category', 'name' => 'Category', 'type' => 'built_in'],
                ['slug' => 'tag',      'name' => 'Tag',      'type' => 'built_in'],
            ];
            $__taxonomyTerms = [];
            $__customTaxos   = collect();
            try {
                $__taxonomyTerms['category'] = \FalconCms\Core\Models\Category::select('id','name','slug')->orderBy('name')->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values()->toArray();
                $__taxonomyTerms['tag']      = \FalconCms\Core\Models\Tag::select('id','name','slug')->orderBy('name')->get()->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->toArray();
                $__customTaxos = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->get();
                foreach ($__customTaxos as $__tax) {
                    $__taxonomies[] = ['slug' => $__tax->slug, 'name' => $__tax->name, 'type' => 'custom'];
                    $__taxonomyTerms[$__tax->slug] = \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $__tax->slug)->select('id','name','slug')->orderBy('name')->get()->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->toArray();
                }
            } catch (\Exception $e) { /* built-ins already set above */ }
        @endphp
        window.falconTaxonomies    = {!! json_encode($__taxonomies, JSON_HEX_TAG) !!};
        window.falconTaxonomyTerms = {!! json_encode($__taxonomyTerms, JSON_HEX_TAG) !!};
        @php
            try {
                $__cptList = \FalconCms\Core\Models\PostType::where('is_builtin', false)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get()
                    ->map(fn($c) => ['slug' => $c->slug, 'name' => $c->name])
                    ->values()->toArray();
                // Build post_type → taxonomy slugs mapping
                $__cptTaxonomies = ['post' => ['category', 'tag'], 'page' => [], 'product' => []];
                foreach ($__customTaxos as $__tax) {
                    foreach (($__tax->post_types ?? []) as $__ptSlug) {
                        if (!isset($__cptTaxonomies[$__ptSlug])) $__cptTaxonomies[$__ptSlug] = [];
                        if (!in_array($__tax->slug, $__cptTaxonomies[$__ptSlug])) {
                            $__cptTaxonomies[$__ptSlug][] = $__tax->slug;
                        }
                    }
                }
            } catch (\Exception $e) { $__cptList = []; $__cptTaxonomies = ['post' => ['category','tag']]; }
        @endphp
        window.falconCptList        = {!! json_encode($__cptList, JSON_HEX_TAG) !!};
        window.falconCptTaxonomies  = {!! json_encode($__cptTaxonomies, JSON_HEX_TAG) !!};
    </script>

    @include('falcon-cms::admin.falcon-builder.partials.styles')
</head>
<body class="bg-[#f1f1f1]">

    <div id="lazy-builder-app" class="builder-wrapper" :class="{ 'is-preview': isPreview, 'dragging-no-transition': isDragging }" v-cloak>
        
        <!-- Toast Container -->
        <div class="fixed top-14 right-5 z-[9999] flex flex-col gap-2 pointer-events-none">
            <transition-group name="toast">
                <div v-for="toast in toasts" :key="toast.id" 
                     class="px-5 py-3 rounded shadow-2xl text-white font-bold text-sm pointer-events-auto flex items-center gap-3 min-w-[200px]"
                     :class="toast.type === 'success' ? 'bg-[#00a32a]' : 'bg-[#d63638]'">
                    <i class="fa" :class="toast.type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
                    @{{ toast.message }}
                </div>
            </transition-group>
        </div>
        
        <!-- Topbar -->
        <header class="builder-topbar">
            @include('falcon-cms::admin.falcon-builder.partials.topbar_content')
        </header>

        <!-- Sidebar -->
        <template v-if="!isPreview">
            @include('falcon-cms::admin.falcon-builder.partials.sidebar')
        </template>

        <!-- Canvas -->
        @include('falcon-cms::admin.falcon-builder.partials.canvas')

        <!-- Modals -->
        @include('falcon-cms::admin.falcon-builder.partials.modals.column-select')
        @include('falcon-cms::admin.falcon-builder.partials.modals.element-select')
        @include('falcon-cms::admin.falcon-builder.partials.modals.library')
        @include('falcon-cms::admin.falcon-builder.partials.modals.context-menu')
        @include('falcon-cms::admin.falcon-builder.partials.modals.global-section')
    </div>

    @include('falcon-cms::components.admin.media-modal')

    <!-- Scripts (Corrected Paths) -->
    <script src="{{ asset('vendor/falcon-cms/js/vue.global.js') }}"></script>
    <script src="{{ asset('vendor/falcon-cms/js/purify.min.js') }}"></script>

    @include('falcon-cms::admin.falcon-builder.partials.scripts')
</body>
</html>
