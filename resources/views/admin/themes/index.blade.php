<x-falcon-cms::layouts.admin>
    <x-slot name="title">Themes - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex items-center gap-4 mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Themes</h1>
            <button id="upload-theme-toggle" class="wp-btn-secondary px-3 h-7 text-[13px] font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">upload</span>
                Upload Theme
            </button>
        </div>

        {{-- Upload Form (Hidden by default) --}}
        <div id="upload-theme-container" class="hidden bg-white border border-[#dcdcde] p-6 mb-8 shadow-sm">
            <p class="text-[14px] text-[#1d2327] mb-4">If you have a theme in a .zip format, you may install or update it by uploading it here.</p>
            <form action="{{ route('admin.themes.upload') }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-center gap-4">
                @csrf
                <input type="file" name="theme_zip" accept=".zip" class="wp-input w-full sm:w-auto h-9 pt-1" required>
                <button type="submit" class="wp-btn-secondary h-9 px-4 font-semibold">Install Now</button>
            </form>
            @error('theme_zip')
                <p class="text-[#d63638] text-[12px] mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-4">
                <span class="text-[13px] text-[#1d2327] font-semibold border-b-2 border-[#1d2327] pb-1 cursor-pointer">All <span class="text-[#646970] font-normal">({{ count($themes) }})</span></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[#646970] text-[13px]">Search themes...</span>
                <input type="text" class="wp-input w-48 h-7">
            </div>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('error') }}
            </div>
        @endif

        <div class="themes-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($themes as $theme)
                <div class="theme-card group relative bg-white border {{ $theme['is_active'] ? 'border-[#2271b1] ring-1 ring-[#2271b1]' : 'border-[#dcdcde]' }} shadow-sm overflow-hidden flex flex-col h-full">
                    
                    {{-- Theme Screenshot --}}
                    <div class="theme-screenshot relative aspect-[4/3] bg-[#f0f0f1] border-b border-[#dcdcde] overflow-hidden">
                        @if($theme['screenshot'])
                            <img src="{{ $theme['screenshot'] }}" alt="{{ $theme['name'] }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center text-[#dcdcde]">
                                <span class="material-symbols-outlined text-[64px]">palette</span>
                                <span class="text-[12px] mt-2 font-medium">No Screenshot</span>
                            </div>
                        @endif

                        {{-- Active Badge --}}
                        @if($theme['is_active'])
                            <div class="absolute top-0 left-0 bg-[#2271b1] text-white px-3 py-1 text-[12px] font-semibold">
                                Active
                            </div>
                        @endif

                        {{-- Overlay Actions --}}
                        <div class="absolute inset-0 bg-white/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
                            @if($theme['is_active'])
                                <div class="bg-[#2271b1] text-white px-4 py-1 text-[13px] font-semibold rounded">
                                    Activated
                                </div>
                            @else
                                @if($theme['is_activatable'])
                                    <div class="flex items-center justify-center gap-3">
                                        <form action="{{ route('admin.themes.activate', $theme['slug']) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="wp-btn-primary px-4 py-1 h-auto text-[13px] font-semibold">Activate</button>
                                        </form>
                                        <button class="wp-btn-secondary bg-white px-4 py-1 h-auto text-[13px] font-semibold">Live Preview</button>
                                    </div>
                                @else
                                    <div class="bg-[#d63638] text-white px-3 py-2 text-[11px] font-bold rounded shadow-sm text-center mx-4">
                                        Broken Theme: Missing index.blade.php
                                    </div>
                                @endif
                                
                                {{-- Delete Button (Only for inactive and non-core themes) --}}
                                @if($theme['slug'] !== 'lazy-theme')
                                    <form id="delete-theme-{{ $theme['slug'] }}" action="{{ route('admin.themes.destroy', $theme['slug']) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" onclick="confirmThemeDelete('{{ $theme['slug'] }}')" class="text-[#d63638] text-[12px] hover:underline mt-2">Delete</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Theme Info --}}
                    <div class="theme-info p-3 bg-white flex items-center justify-between mt-auto">
                        <div class="truncate">
                            <h2 class="text-[14px] font-bold text-[#1d2327] truncate">{{ $theme['name'] }}</h2>
                        </div>
                        <div class="flex items-center">
                            @if($theme['is_active'])
                                <button class="text-[#2271b1] hover:text-[#135e96] transition">
                                    <span class="material-symbols-outlined text-[20px]">info</span>
                                </button>
                            @else
                                <button class="text-[#646970] hover:text-[#2271b1] transition">
                                    <span class="material-symbols-outlined text-[20px]">info</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Add New Theme Placeholder --}}
            <div class="border-2 border-dashed border-[#dcdcde] bg-[#f6f7f7] hover:bg-[#f0f0f1] hover:border-[#c3c4c7] transition flex flex-col items-center justify-center min-h-[250px] cursor-pointer group">
                <div class="w-12 h-12 rounded-full border-2 border-[#dcdcde] group-hover:border-[#c3c4c7] flex items-center justify-center text-[#dcdcde] group-hover:text-[#c3c4c7] mb-3">
                    <span class="material-symbols-outlined text-[32px]">add</span>
                </div>
                <span class="text-[14px] font-bold text-[#646970]">Add New Theme</span>
            </div>
        </div>
    </div>

    <style>
        /* Add some specific WordPress-like styling for themes page */
        .wp-btn-primary { 
            background: #2271b1; 
            border-color: #2271b1; 
            box-shadow: 0 1px 0 #135e96; 
            color: #fff;
            padding: 4px 12px;
            border-radius: 3px;
            cursor: pointer;
        }
        .wp-btn-primary:hover { 
            background: #135e96; 
            border-color: #135e96; 
        }
        .wp-btn-secondary {
            background: #f6f7f7;
            border: 1px solid #2271b1;
            color: #2271b1;
            padding: 4px 12px;
            border-radius: 3px;
            cursor: pointer;
        }

        /* Grid Fallback if Tailwind fails */
        .themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        .theme-card {
            background: #fff;
            border: 1px solid #dcdcde;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .theme-screenshot {
            aspect-ratio: 4/3;
            background: #f0f0f1;
            border-bottom: 1px solid #dcdcde;
            position: relative;
            overflow: hidden;
        }
        .theme-info {
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
    </style>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const uploadToggle = document.getElementById('upload-theme-toggle');
                const uploadContainer = document.getElementById('upload-theme-container');

                if (uploadToggle && uploadContainer) {
                    uploadToggle.addEventListener('click', function() {
                        uploadContainer.classList.toggle('hidden');
                        uploadToggle.classList.toggle('bg-[#dcdcde]');
                    });
                }
            });

            window.confirmThemeDelete = async function(slug) {
                const confirmed = await window.lazyConfirm({
                    title: 'Delete Theme',
                    message: 'Are you sure you want to delete this theme? This action cannot be undone and will remove all theme files from the server.',
                    confirmText: 'Delete Theme',
                    isDanger: true
                });

                if (confirmed) {
                    document.getElementById(`delete-theme-${slug}`).submit();
                }
            };
        </script>
    @endpush
</x-falcon-cms::layouts.admin>
