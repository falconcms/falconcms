<x-falcon-cms::layouts.admin>
    <x-slot name="title">Import - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Import</h1>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">{{ session('error') }}</div>
        @endif

        {{-- Result summary --}}
        @if(session('import_summary'))
            @php $s = session('import_summary'); @endphp
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
                    @php $__new = ($s['posts']??0)+($s['pages']??0)+($s['cpt']??0)+($s['categories']??0)+($s['tags']??0); @endphp
                    @if($__new === 0)
                        <div class="bg-[#fcf9e8] border-l-4 border-[#dba617] p-3 text-[13px] text-[#1d2327] mb-2">
                            <strong>Nothing new was imported.</strong> Every item in this file already exists on this site, so it was skipped — existing content is never overwritten. This is expected when re-importing an export back into the same site. To populate a different or empty site, run the import there instead.
                        </div>
                    @endif
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

        <div class="bg-white border border-[#c3c4c7] shadow-sm">
            <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7] flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#646970]">upload</span>
                <div>
                    <h2 class="text-[14px] font-semibold text-[#1d2327]">Import from an export file</h2>
                    <p class="text-[12px] text-[#646970]">
                        Upload a <code>.xml</code> file created by <strong>Tools → Export</strong> (or any WordPress WXR export)
                        to restore posts, pages, custom post types and taxonomy terms.
                    </p>
                </div>
            </div>
            <div class="p-5">
                <form action="{{ route('admin.import.run') }}" method="POST" enctype="multipart/form-data" id="import-form">
                    @csrf
                    @error('import_file')
                        <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">{{ $message }}</div>
                    @enderror

                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">Select export file</label>
                            <div id="import-drop-zone"
                                 class="relative border-2 border-dashed border-[#c3c4c7] rounded-sm p-5 text-center cursor-pointer hover:border-[#2271b1] transition-colors">
                                <span class="material-symbols-outlined text-[36px] text-[#c3c4c7] block mb-1" id="import-icon">upload_file</span>
                                <p class="text-[13px] text-[#646970]" id="import-label">Click to choose file or drag &amp; drop here</p>
                                <p class="text-[11px] text-[#9ca3af] mt-1">
                                    Accepted: .xml, .wxr &nbsp;|&nbsp;
                                    Max size: <strong class="text-[#1d2327]">{{ $maxUploadHuman }}</strong>
                                    <span class="text-[#9ca3af]">(server limit)</span>
                                </p>
                                <input type="file" name="import_file" id="import_file"
                                       accept=".xml,.wxr,text/xml,application/xml"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       onchange="handleImportSelect(this)">
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-[13px] text-[#1d2327] cursor-pointer select-none">
                            <input type="checkbox" name="import_pages" value="1" checked class="rounded border-[#8c8f94]">
                            Also import Pages (uncheck to import Posts &amp; other types only)
                        </label>

                        <div class="flex justify-center">
                            <button type="button" onclick="confirmImport()" id="import-btn"
                                    class="wp-btn-primary flex items-center gap-1.5 opacity-50 pointer-events-none" disabled>
                                <span class="material-symbols-outlined text-[18px]">download_done</span>
                                Run Import
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-6 bg-[#f0f6fa] border border-[#d5ecf5] p-4 rounded-sm">
            <h3 class="text-[14px] font-semibold text-[#0c3d5d] mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">info</span>
                Good to know
            </h3>
            <p class="text-[13px] text-[#1d2327] leading-relaxed">
                Re-running an import is safe — items already present (same type &amp; slug) are skipped, not duplicated.
                Media files are referenced by their original URL; to move the actual files use
                <strong>Tools → WordPress Import → Import Media Files</strong> or restore a media backup.
            </p>
        </div>
    </div>

    <script>
        const importMaxBytes = {{ $maxUploadBytes }};

        function handleImportSelect(input) {
            const zone  = document.getElementById('import-drop-zone');
            const label = document.getElementById('import-label');
            const icon  = document.getElementById('import-icon');
            const btn   = document.getElementById('import-btn');
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (file.size > importMaxBytes) {
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

        (function () {
            const zone = document.getElementById('import-drop-zone');
            if (!zone) return;
            zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.borderColor = '#2271b1'; });
            zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
            zone.addEventListener('drop', e => {
                e.preventDefault(); zone.style.borderColor = '';
                const input = document.getElementById('import_file');
                if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; handleImportSelect(input); }
            });
        })();

        window.confirmImport = async function () {
            const confirmed = await window.falconConfirm({
                title:       'Run Import',
                message:     'This will import posts, pages, custom post types and taxonomy terms from the selected file. Existing items with the same slug are skipped. Continue?',
                confirmText: 'Yes, Run Import',
                isDanger:    false,
            });
            if (confirmed) document.getElementById('import-form').submit();
        };
    </script>
</x-falcon-cms::layouts.admin>
