<x-falcon-cms::layouts.admin>
    <x-slot name="title">WordPress Import — FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">WordPress Import</h1>
        </div>

        {{-- Global alerts --}}
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

        {{-- Import result summary --}}
        @if(session('wp_import_summary'))
            @php $s = session('wp_import_summary'); @endphp
            <div class="bg-white border border-[#c3c4c7] shadow-sm mb-6 overflow-hidden">
                <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-[#46b450]">check_circle</span>
                    <h2 class="text-[14px] font-semibold text-[#1d2327]">Import Result</h2>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 text-center mb-4">
                        @foreach(['posts'=>'Posts','pages'=>'Pages','cpt'=>'Custom','categories'=>'Categories','tags'=>'Tags','skipped'=>'Skipped'] as $k => $label)
                            <div class="border border-[#f0f0f1] rounded py-3">
                                <div class="text-[22px] font-semibold text-[#1d2327]">{{ $s[$k] ?? 0 }}</div>
                                <div class="text-[11px] text-[#646970] uppercase">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($s['errors']))
                        <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 text-[13px] text-[#1d2327]">
                            <div class="font-semibold mb-1">{{ count($s['errors']) }} item(s) had problems:</div>
                            <ul class="list-disc pl-5 max-h-36 overflow-y-auto text-[12px] text-[#646970]">
                                @foreach(array_slice($s['errors'], 0, 50) as $err)<li>{{ $err }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Media import result --}}
        @if(session('media_success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">{{ session('media_success') }}</div>
        @endif
        @if(session('media_error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">{{ session('media_error') }}</div>
        @endif

        {{-- ── Section 1: Content Import (WXR) ─────────────────────────────── --}}
        <div class="bg-white border border-[#c3c4c7] shadow-sm mb-6">
            <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7] flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#646970]">article</span>
                <div>
                    <h2 class="text-[14px] font-semibold text-[#1d2327]">Import Posts, Pages & Taxonomies</h2>
                    <p class="text-[12px] text-[#646970]">Upload a WordPress export <code>.xml</code> (WXR) file to import posts, pages, categories and tags.</p>
                </div>
            </div>
            <div class="p-5">
                <form action="{{ route('admin.wp-import.import') }}" method="POST" enctype="multipart/form-data" id="wxr-form">
                    @csrf
                    @error('wxr_file')
                        <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">{{ $message }}</div>
                    @enderror

                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">Select WordPress export file</label>
                            <div id="wxr-drop-zone"
                                 class="relative border-2 border-dashed border-[#c3c4c7] rounded-sm p-5 text-center cursor-pointer hover:border-[#2271b1] transition-colors">
                                <span class="material-symbols-outlined text-[36px] text-[#c3c4c7] block mb-1" id="wxr-icon">upload_file</span>
                                <p class="text-[13px] text-[#646970]" id="wxr-label">Click to choose file or drag & drop here</p>
                                <p class="text-[11px] text-[#9ca3af] mt-1">
                                    Accepted: .xml, .wxr &nbsp;|&nbsp;
                                    Max size: <strong class="text-[#1d2327]">{{ $maxUploadHuman }}</strong>
                                    <span class="text-[#9ca3af]">(server limit)</span>
                                </p>
                                <input type="file" name="wxr_file" id="wxr_file"
                                       accept=".xml,.wxr,text/xml,application/xml"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       onchange="handleWxrSelect(this)">
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-[13px] text-[#1d2327] cursor-pointer select-none">
                            <input type="checkbox" name="import_pages" value="1" checked class="rounded border-[#8c8f94]">
                            Also import Pages (uncheck to import Posts only)
                        </label>

                        <div class="flex justify-center">
                            <button type="button" onclick="confirmWxrImport()" id="wxr-btn"
                                    class="wp-btn-primary flex items-center gap-1.5 opacity-50 pointer-events-none" disabled>
                                <span class="material-symbols-outlined text-[18px]">download_done</span>
                                Run Import
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Section 2: Media Import (ZIP) ───────────────────────────────── --}}
        <div class="bg-white border border-[#c3c4c7] shadow-sm mb-6">
            <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7] flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#646970]">photo_library</span>
                <div>
                    <h2 class="text-[14px] font-semibold text-[#1d2327]">Import Media Files</h2>
                    <p class="text-[12px] text-[#646970]">Upload a <code>.zip</code> of your WordPress <code>wp-content/uploads</code> folder to migrate images and files.</p>
                </div>
            </div>
            <div class="p-5">
                <form action="{{ route('admin.wp-import.media') }}" method="POST" enctype="multipart/form-data" id="media-form">
                    @csrf
                    @error('wp_media_file')
                        <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">{{ $message }}</div>
                    @enderror

                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">Select media zip file</label>
                            <div id="media-drop-zone"
                                 class="relative border-2 border-dashed border-[#c3c4c7] rounded-sm p-5 text-center cursor-pointer hover:border-[#2271b1] transition-colors">
                                <span class="material-symbols-outlined text-[36px] text-[#c3c4c7] block mb-1" id="media-icon">folder_zip</span>
                                <p class="text-[13px] text-[#646970]" id="media-label">Click to choose file or drag & drop here</p>
                                <p class="text-[11px] text-[#9ca3af] mt-1">
                                    Accepted: .zip &nbsp;|&nbsp;
                                    Max size: <strong class="text-[#1d2327]">{{ $maxUploadHuman }}</strong>
                                    <span class="text-[#9ca3af]">(server limit)</span>
                                </p>
                                <input type="file" name="wp_media_file" id="wp_media_file"
                                       accept=".zip"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       onchange="handleMediaSelect(this)">
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <button type="button" onclick="confirmMediaImport()" id="media-btn"
                                    class="wp-btn-primary flex items-center gap-1.5 opacity-50 pointer-events-none" disabled>
                                <span class="material-symbols-outlined text-[18px]">upload</span>
                                Import Media
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- How-to --}}
        <div class="bg-[#f0f6fa] border border-[#d5ecf5] p-4 rounded-sm">
            <h3 class="text-[14px] font-semibold text-[#0c3d5d] mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">info</span>
                How to export from WordPress
            </h3>
            <ol class="list-decimal pl-5 space-y-1 text-[13px] text-[#1d2327]">
                <li>In WordPress go to <strong>Tools → Export</strong>, choose <strong>All content</strong>, click <strong>Download Export File</strong> — upload that <code>.xml</code> here.</li>
                <li>For media, zip your <code>wp-content/uploads</code> folder and upload the <code>.zip</code> in the section above.</li>
            </ol>
            <p class="text-[12px] text-[#50575e] mt-2">
                Re-running content import is safe — items already imported (same type &amp; slug) are skipped.
                Featured images are linked to their original URLs until you import the media zip.
            </p>
        </div>
    </div>

    <script>
        const maxBytes = {{ $maxUploadBytes }};

        function handleWxrSelect(input) {
            const zone  = document.getElementById('wxr-drop-zone');
            const label = document.getElementById('wxr-label');
            const icon  = document.getElementById('wxr-icon');
            const btn   = document.getElementById('wxr-btn');
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > maxBytes) {
                label.textContent = 'File too large! Max: {{ $maxUploadHuman }}';
                label.style.color = '#d63638'; icon.textContent = 'error'; icon.style.color = '#d63638';
                btn.disabled = true; btn.classList.add('opacity-50', 'pointer-events-none');
                input.value = ''; return;
            }
            label.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            label.style.color = '#2271b1'; icon.textContent = 'check_circle'; icon.style.color = '#46b450';
            zone.style.borderColor = '#46b450';
            btn.disabled = false; btn.classList.remove('opacity-50', 'pointer-events-none');
        }

        function handleMediaSelect(input) {
            const zone  = document.getElementById('media-drop-zone');
            const label = document.getElementById('media-label');
            const icon  = document.getElementById('media-icon');
            const btn   = document.getElementById('media-btn');
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > maxBytes) {
                label.textContent = 'File too large! Max: {{ $maxUploadHuman }}';
                label.style.color = '#d63638'; icon.textContent = 'error'; icon.style.color = '#d63638';
                btn.disabled = true; btn.classList.add('opacity-50', 'pointer-events-none');
                input.value = ''; return;
            }
            label.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            label.style.color = '#2271b1'; icon.textContent = 'check_circle'; icon.style.color = '#46b450';
            zone.style.borderColor = '#46b450';
            btn.disabled = false; btn.classList.remove('opacity-50', 'pointer-events-none');
        }

        // Drag-and-drop for WXR zone
        (function () {
            const zone = document.getElementById('wxr-drop-zone');
            if (!zone) return;
            zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.borderColor = '#2271b1'; });
            zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
            zone.addEventListener('drop', e => {
                e.preventDefault(); zone.style.borderColor = '';
                const input = document.getElementById('wxr_file');
                if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; handleWxrSelect(input); }
            });
        })();

        // Drag-and-drop for media zone
        (function () {
            const zone = document.getElementById('media-drop-zone');
            if (!zone) return;
            zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.borderColor = '#2271b1'; });
            zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
            zone.addEventListener('drop', e => {
                e.preventDefault(); zone.style.borderColor = '';
                const input = document.getElementById('wp_media_file');
                if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; handleMediaSelect(input); }
            });
        })();

        window.confirmWxrImport = async function () {
            const confirmed = await window.falconConfirm({
                title:       'Import WordPress Content',
                message:     'This will import posts, pages, categories and tags from the selected file. Existing items with the same slug will be skipped. Continue?',
                confirmText: 'Yes, Run Import',
                isDanger:    false,
            });
            if (confirmed) document.getElementById('wxr-form').submit();
        };

        window.confirmMediaImport = async function () {
            const confirmed = await window.falconConfirm({
                title:       'Import Media Files',
                message:     'This will extract all media files from the zip into your uploads folder. Duplicate filenames will be renamed automatically. Continue?',
                confirmText: 'Yes, Import Media',
                isDanger:    false,
            });
            if (confirmed) document.getElementById('media-form').submit();
        };
    </script>
</x-falcon-cms::layouts.admin>
