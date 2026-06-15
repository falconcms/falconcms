<x-falcon-cms::layouts.admin title="Lazy Builder">
    <div class="p-6 bg-[#f0f0f1] min-h-screen">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-[23px] font-normal text-[#1d2327] mb-1">Lazy Builder</h1>
                <p class="text-[13px] text-[#646970]">Design custom header and footer for your website.</p>
            </div>
            <nav class="text-[13px] text-[#646970]">
                Appearance / Lazy Builder
            </nav>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Header Builder Card -->
            <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm overflow-hidden flex flex-col">
                <div class="p-5 border-b border-[#f0f0f1] bg-[#f9fafb] flex items-center gap-3">
                    <span class="material-symbols-outlined text-[#2271b1]">dock_to_bottom</span>
                    <h2 class="text-[16px] font-semibold text-[#1d2327]">Header Builder</h2>
                </div>
                <div class="p-8 flex-1 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-[#f0f6fb] rounded-full flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-[40px] text-[#2271b1]">web_asset</span>
                    </div>
                    <p class="text-[#646970] text-[14px] leading-relaxed mb-6 max-w-[300px]">
                        Create a stunning custom header. If enabled, this will override your theme's default header.
                    </p>
                    <a href="{{ route('admin.falcon-builder.header') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#2271b1] hover:bg-[#135e96] text-white text-[14px] font-semibold rounded transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                        {{ $header ? 'Edit Header' : 'Start Building Header' }}
                    </a>
                </div>
                @if($header)
                <div class="px-5 py-3 bg-[#f9fafb] border-t border-[#f0f0f1] flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-[12px] text-[#8c8f94]">Status:</span>
                        <form action="{{ route('admin.falcon-builder.toggle', $header->id) }}" method="POST" id="toggle-header-form">
                            @csrf
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" {{ $header->status === 'published' ? 'checked' : '' }} onchange="document.getElementById('toggle-header-form').submit()">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-2 text-xs font-bold {{ $header->status === 'published' ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $header->status === 'published' ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </label>
                        </form>
                    </div>
                    <span class="text-[11px] text-[#8c8f94]">{{ $header->updated_at->diffForHumans() }}</span>
                </div>
                @endif
            </div>

            <!-- Footer Builder Card -->
            <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm overflow-hidden flex flex-col">
                <div class="p-5 border-b border-[#f0f0f1] bg-[#f9fafb] flex items-center gap-3">
                    <span class="material-symbols-outlined text-[#d63638]">dock_to_left</span>
                    <h2 class="text-[16px] font-semibold text-[#1d2327]">Footer Builder</h2>
                </div>
                <div class="p-8 flex-1 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-[#fdf2f2] rounded-full flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-[40px] text-[#d63638]">bottom_panel_open</span>
                    </div>
                    <p class="text-[#646970] text-[14px] leading-relaxed mb-6 max-w-[300px]">
                        Design a professional footer with widgets and links. Overrides theme default footer.
                    </p>
                    <a href="{{ route('admin.falcon-builder.footer') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#d63638] hover:bg-[#b32d2e] text-white text-[14px] font-semibold rounded transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                        {{ $footer ? 'Edit Footer' : 'Start Building Footer' }}
                    </a>
                </div>
                @if($footer)
                <div class="px-5 py-3 bg-[#f9fafb] border-t border-[#f0f0f1] flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-[12px] text-[#8c8f94]">Status:</span>
                        <form action="{{ route('admin.falcon-builder.toggle', $footer->id) }}" method="POST" id="toggle-footer-form">
                            @csrf
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" {{ $footer->status === 'published' ? 'checked' : '' }} onchange="document.getElementById('toggle-footer-form').submit()">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-600"></div>
                                <span class="ml-2 text-xs font-bold {{ $footer->status === 'published' ? 'text-red-600' : 'text-gray-400' }}">
                                    {{ $footer->status === 'published' ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                            </label>
                        </form>
                    </div>
                    <span class="text-[11px] text-[#8c8f94]">{{ $footer->updated_at->diffForHumans() }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="mt-12 bg-blue-50 border-l-4 border-blue-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-400">info</span>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Pro Tip:</strong> Once you publish your header or footer, it will automatically replace the default theme sections. You can always come back here to edit or disable them.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
