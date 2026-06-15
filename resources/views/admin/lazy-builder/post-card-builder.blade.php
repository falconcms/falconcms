@php
    $builderTitle   = $postCard['name'];
    $builderContent = json_encode($postCard['config']['layout'] ?? []);
    $builderSaveUrl = route('admin.falcon-builder.post-cards.save-layout', $postCard['id']);
    $builderBackUrl = route('admin.falcon-builder.library') . '?tab=post_cards';
    $postCardMode   = true;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $postCard['name'] }} | Post Card Builder</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('vendor/cms-dashboard/css/font-awesome.all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cms-dashboard/css/material-symbols.css') }}" />

    <script src="{{ asset('vendor/cms-dashboard/js/tailwind.min.js') }}"></script>

    <link rel="stylesheet" href="{{ asset('vendor/cms-dashboard/css/pickr.classic.min.css') }}"/>
    <script src="{{ asset('vendor/cms-dashboard/js/pickr.min.js') }}"></script>
    <script src="{{ asset('vendor/cms-dashboard/js/tinymce.min.js') }}"></script>
    <script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>

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
            try {
                $allMenus = \Illuminate\Support\Facades\DB::table('navigation_menus')->get();
                $allItems = \Illuminate\Support\Facades\DB::table('navigation_menu_items')->orderBy('order')->get();
                $menuData = []; $menuNames = [];
                foreach($allMenus as $m) {
                    $menuNames[$m->id] = $m->name;
                    $menuItems = $allItems->where('navigation_menu_id', $m->id);
                    $grouped   = $menuItems->groupBy('parent_id');
                    $topLevel  = $grouped->get(null, collect([]))->map(function($item) use ($grouped) {
                        return ['id'=>$item->id,'title'=>$item->title,'url'=>$item->url,
                            'children'=>$grouped->get($item->id,collect([]))->map(function($child) use ($grouped){
                                return ['id'=>$child->id,'title'=>$child->title,'url'=>$child->url,
                                    'children'=>$grouped->get($child->id,collect([]))->map(function($gc){
                                        return ['id'=>$gc->id,'title'=>$gc->title,'url'=>$gc->url];
                                    })->values()->toArray()];
                            })->values()->toArray()];
                    })->values()->toArray();
                    $menuData[$m->id] = $topLevel;
                }
                $menuDataJson  = json_encode($menuData);
                $menuNamesJson = json_encode($menuNames);
            } catch(\Exception $e) { $menuDataJson='{}'; $menuNamesJson='{}'; }
        @endphp
        window.lazyMenuData  = {!! $menuDataJson !!};
        window.lazyMenusList = {!! $menuNamesJson !!};
        @php $builderPostCards = json_decode(get_cms_option('lazy_post_cards','[]'),true) ?: []; @endphp
        window.lazyPostCards = {!! json_encode($builderPostCards) !!};
        window.lazyPostCardMode = true;
        @php
            $__pcTaxonomies   = [
                ['slug' => 'category', 'name' => 'Category', 'type' => 'built_in'],
                ['slug' => 'tag',      'name' => 'Tag',      'type' => 'built_in'],
            ];
            $__pcTaxTerms   = [];
            $__pcCustomTaxos = collect();
            $__pcCptTaxonomies = ['post' => ['category', 'tag'], 'page' => [], 'product' => []];
            try {
                $__pcTaxTerms['category'] = \FalconCms\Core\Models\Category::select('id','name','slug')->orderBy('name')->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values()->toArray();
                $__pcTaxTerms['tag']      = \FalconCms\Core\Models\Tag::select('id','name','slug')->orderBy('name')->get()->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->toArray();
                $__pcCustomTaxos = \FalconCms\Core\Models\CustomTaxonomy::where('is_active', true)->get();
                foreach ($__pcCustomTaxos as $__pct) {
                    $__pcTaxonomies[] = ['slug' => $__pct->slug, 'name' => $__pct->name, 'type' => 'custom'];
                    $__pcTaxTerms[$__pct->slug] = \FalconCms\Core\Models\TaxonomyTerm::where('taxonomy_slug', $__pct->slug)->select('id','name','slug')->orderBy('name')->get()->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])->values()->toArray();
                    foreach (($__pct->post_types ?? []) as $__ptSlug) {
                        if (!isset($__pcCptTaxonomies[$__ptSlug])) $__pcCptTaxonomies[$__ptSlug] = [];
                        if (!in_array($__pct->slug, $__pcCptTaxonomies[$__ptSlug])) $__pcCptTaxonomies[$__ptSlug][] = $__pct->slug;
                    }
                }
            } catch (\Exception $e) { /* built-ins already set */ }
        @endphp
        window.lazyTaxonomies    = {!! json_encode($__pcTaxonomies) !!};
        window.lazyTaxonomyTerms = {!! json_encode($__pcTaxTerms) !!};
        window.lazyCptTaxonomies = {!! json_encode($__pcCptTaxonomies) !!};
        window.lazyCptList       = [];
    </script>

    @include('falcon-cms::admin.falcon-builder.partials.styles')
</head>
<body class="bg-[#f1f1f1]">

    <div id="lazy-builder-app" class="builder-wrapper" :class="{ 'is-preview': isPreview, 'dragging-no-transition': isDragging }" v-cloak>

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

        <header class="builder-topbar">
            @include('falcon-cms::admin.falcon-builder.partials.topbar_content')
        </header>

        <template v-if="!isPreview">
            @include('falcon-cms::admin.falcon-builder.partials.sidebar')
        </template>

        @include('falcon-cms::admin.falcon-builder.partials.canvas')

        @include('falcon-cms::admin.falcon-builder.partials.modals.column-select')
        @include('falcon-cms::admin.falcon-builder.partials.modals.element-select')
        @include('falcon-cms::admin.falcon-builder.partials.modals.library')
        @include('falcon-cms::admin.falcon-builder.partials.modals.context-menu')
    </div>

    @include('falcon-cms::components.admin.media-modal')

    <script src="{{ asset('vendor/cms-dashboard/js/vue.global.js') }}"></script>

    @include('falcon-cms::admin.falcon-builder.partials.scripts')
</body>
</html>
