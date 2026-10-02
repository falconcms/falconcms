@php
    $builderTitle   = $offCanvas['name'];
    $builderContent = json_encode($offCanvas['config']['layout'] ?? []);
    $builderSaveUrl = route('admin.falcon-builder.off-canvas.save-layout', $offCanvas['id']);
    $builderBackUrl = route('admin.falcon-builder.library') . '?tab=off_canvas';
    $postCardMode   = false;
    $isOffCanvasBuilder = true;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $offCanvas['name'] }} | Off-Canvas Builder</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/font-awesome.all.min.css') }}">
    @foreach(falcon_icon_sets() as $__iconSet)
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/'.$__iconSet['asset']) }}">
    @endforeach
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/material-symbols.css') }}" />

    <script src="{{ asset('vendor/falcon-cms/js/tailwind.min.js') }}"></script>

    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/pickr.classic.min.css') }}"/>
    <script src="{{ asset('vendor/falcon-cms/js/pickr.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/tom-select.default.min.css') }}">
    <script src="{{ asset('vendor/falcon-cms/js/tom-select.complete.min.js') }}"></script>
    {{-- Same look as the page builder's TomSelect fields; the dropdown hangs off <body>, so it
         needs to sit above the builder chrome. --}}
    <style>
        .ts-wrapper { font-size: 13px; }
        .ts-control { border-color: #e2e8f0 !important; border-radius: 6px !important; padding: 4px 8px !important; min-height: 36px; box-shadow: none !important; }
        .ts-control:focus-within { border-color: #0091ea !important; }
        .ts-control .item { background: #e0f2fe !important; color: #0369a1 !important; border: 1px solid #bae6fd !important; border-radius: 4px !important; font-size: 11px !important; font-weight: 600 !important; padding: 1px 6px !important; }
        .ts-control .item .remove { color: #0369a1 !important; border-left: 1px solid #bae6fd !important; }
        .ts-dropdown { border-color: #e2e8f0 !important; border-radius: 6px !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; font-size: 13px !important; z-index: 99999 !important; }
        .ts-dropdown .option:hover, .ts-dropdown .option.active { background: #e0f2fe !important; color: #0369a1 !important; }
        .ts-dropdown .option.selected { background: #f0f9ff !important; color: #0369a1 !important; }
        .ts-dropdown .optgroup-header { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; padding-top: 8px; }
        .ts-dropdown .create { color: #0369a1; }
    </style>
    <script src="{{ asset('vendor/falcon-cms/js/tinymce.min.js') }}"></script>
    <script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>

    <script>
        window.builderBreakpoints = {
            small: {{ get_cms_option('theme_small_screen_breakpoint', '800') }},
            medium: {{ get_cms_option('theme_medium_screen_breakpoint', '1100') }}
        };
        window.builderBreakpointsUrl = '{{ route('admin.falcon-builder.breakpoints') }}';
        {{-- A panel has no page padding around it on the front end, so neither does the canvas. --}}
        window.builderPagePadding = { top: '0px', bottom: '0px' };
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
        window.falconMenuData  = {!! $menuDataJson !!};
        window.falconMenusList = {!! $menuNamesJson !!};
        @php $builderPostCards = json_decode(get_cms_option('falcon_post_cards','[]'),true) ?: []; @endphp
        window.falconPostCards = {!! json_encode($builderPostCards) !!};
        window.falconPostCardMode = false;
        window.falconOffCanvas = {
            id: @json($offCanvas['id']),
            anchor: @json(\FalconCms\Core\Support\OffCanvas::anchor($offCanvas)),
            settings: @json($offCanvas['config']['settings']),
            saveUrl: @json(route('admin.falcon-builder.off-canvas.save-settings', $offCanvas['id'])),
            targetOptions: @json($targetPicker['options']),
            targetGroups: @json($targetPicker['optgroups']),
            targetSearchUrl: @json(route('admin.falcon-builder.off-canvas.targets')),
        };
    </script>

    @include('falcon-cms::admin.falcon-builder.partials.builder-data')

    @include('falcon-cms::admin.falcon-builder.partials.styles')
    <style>
        /* The canvas stands in for the panel: give it the panel's edge against the grey. */
        .canvas-container:not(.mobile):not(.tablet) { box-shadow: 0 10px 45px rgba(0,0,0,0.12) !important; outline: 1px solid #e2e8f0; }
    </style>
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

    <script src="{{ asset('vendor/falcon-cms/js/vue.global.js') }}"></script>

    @include('falcon-cms::admin.falcon-builder.partials.scripts')

</body>
</html>
