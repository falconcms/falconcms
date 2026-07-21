<x-falcon-cms::layouts.admin :title="$page['title'] ?? 'Settings'" :active-menu="$slug">
    <x-slot name="title">{{ $page['title'] ?? 'Settings' }}</x-slot>

    <div class="max-w-3xl">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-1">{{ $page['title'] ?? ($page['menu_title'] ?? 'Settings') }}</h1>
        @if(!empty($page['description']))
            <p class="text-[13px] text-[#646970] mb-5">{{ $page['description'] }}</p>
        @else
            <div class="mb-5"></div>
        @endif

        @if(session('success'))
            <div class="bg-white border-l-4 border-[#46b450] shadow-sm p-3 mb-5 text-[13px]">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.options.save', ['slug' => $slug]) }}">
            @csrf
            @if(!empty($page['tabs']))
                {{-- Tabbed layout — all tabs live in one form and save together. --}}
                <input type="hidden" name="_tab" id="fms-active-tab" value="{{ request('tab', '') }}">
                <div class="fms-tabs-nav">
                    @foreach($page['tabs'] as $ti => $tab)
                        @php $tid = $tab['id'] ?? ('tab-' . $ti); @endphp
                        <button type="button" class="fms-tab-btn" data-tab="{{ $tid }}">
                            @if(!empty($tab['icon']))<span class="material-symbols-outlined text-[17px] mr-1.5">{{ $tab['icon'] }}</span>@endif
                            {{ $tab['label'] ?? $tid }}
                        </button>
                    @endforeach
                </div>
                @foreach($page['tabs'] as $ti => $tab)
                    @php $tid = $tab['id'] ?? ('tab-' . $ti); @endphp
                    <div class="fms-tab-panel" data-tab="{{ $tid }}" hidden>
                        <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] rounded">
                            @include('falcon-cms::admin.partials.options-fields', ['fields' => $tab['fields'] ?? [], 'values' => $values])
                        </div>
                    </div>
                @endforeach
            @else
                {{-- Single-page layout. --}}
                <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] rounded">
                    @include('falcon-cms::admin.partials.options-fields', ['fields' => $page['fields'] ?? [], 'values' => $values])
                </div>
            @endif

            <div class="mt-4">
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-[#2271b1] hover:bg-[#135e96] text-white font-semibold text-[13px] px-5 h-9 rounded transition-colors">
                    <span class="material-symbols-outlined text-[18px]">save</span> Save Changes
                </button>
            </div>
        </form>
    </div>

    @include('falcon-cms::admin.partials.options-fields-assets')
</x-falcon-cms::layouts.admin>
