<x-falcon-cms::layouts.admin>
    <x-slot name="title">Export - FalconCMS</x-slot>

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Export</h1>
        </div>

        @if(session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white border border-[#c3c4c7] shadow-sm">
            <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7] flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#646970]">download</span>
                <div>
                    <h2 class="text-[14px] font-semibold text-[#1d2327]">Choose what to export</h2>
                    <p class="text-[12px] text-[#646970]">
                        Download a portable <code>.xml</code> (WordPress-compatible WXR) file of your content.
                        It can be re-imported here via <strong>Tools → WordPress Import</strong>, or into any WordPress site.
                    </p>
                </div>
            </div>

            <form action="{{ route('admin.export.download') }}" method="POST" class="p-5">
                @csrf

                {{-- All content --}}
                <label class="flex items-start gap-3 cursor-pointer py-2">
                    <input type="radio" name="content" value="all" checked
                           class="mt-1 accent-[#2271b1]">
                    <span>
                        <span class="text-[14px] font-semibold text-[#1d2327]">All content</span>
                        <span class="block text-[12px] text-[#646970] mt-0.5">
                            This will contain all of your posts, pages, custom post types, taxonomy terms and media.
                        </span>
                    </span>
                </label>

                <div class="border-t border-[#f0f0f1] my-3"></div>

                {{-- Dynamic, feature-driven options --}}
                @foreach($grouped as $group => $items)
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-[#8c8f94] mt-4 mb-1">{{ $group }}</p>
                    @foreach($items as $src)
                        <label class="flex items-center gap-3 cursor-pointer py-1.5">
                            <input type="radio" name="content" value="{{ $src['key'] }}" class="accent-[#2271b1]">
                            <span class="text-[13px] text-[#1d2327]">
                                {{ $src['label'] }}
                                @if(!is_null($src['count']))
                                    <span class="text-[#8c8f94] text-[12px]">({{ number_format($src['count']) }})</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                @endforeach

                <div class="mt-6 pt-4 border-t border-[#f0f0f1]">
                    <button type="submit" class="wp-btn-primary flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">download</span>
                        Download Export File
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 bg-[#f0f6fa] border border-[#d5ecf5] p-4 rounded-sm">
            <h3 class="text-[14px] font-semibold text-[#0c3d5d] mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">info</span>
                About this list
            </h3>
            <p class="text-[13px] text-[#1d2327] leading-relaxed">
                The options above are built automatically from the features your site has registered — every post type,
                taxonomy and library shows up on its own. When a new exportable feature is added later, it appears here
                automatically. Media files themselves are referenced by URL in the export; to move the actual files, also
                use <strong>Tools → Backup → Backup Media Files</strong>.
            </p>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
