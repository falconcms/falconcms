@php
    // The window opens on many screens (pages, the builder, settings); moving files and making
    // folders follow Media → Add New, not the screen it was opened from.
    $mediaCanWrite = falcon_admin_can_write('admin/media', '');
@endphp
<div id="wp-media-modal" class="hidden fixed inset-0 z-[99999] overflow-hidden" data-can-write="{{ $mediaCanWrite ? '1' : '' }}">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60"></div>
    
    <!-- Modal Container -->
    <div class="absolute inset-4 sm:inset-10 bg-white shadow-2xl flex flex-col overflow-hidden border border-[#c3c4c7]">
        
        <!-- Header -->
        <div class="h-[50px] border-b border-[#c3c4c7] flex justify-between items-center px-4 shrink-0 bg-white">
            <h1 class="text-[22px] font-normal text-[#1d2327]">Add media</h1>
            <button type="button" id="close-media-modal" class="text-[#646970] hover:text-black p-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Main Body -->
        <div class="flex flex-grow overflow-hidden">
            
            <!-- Sidebar Left (Tabs) -->
            <div class="w-[160px] border-r border-[#c3c4c7] bg-[#f0f0f1] py-4 shrink-0 hidden md:block">
                <ul class="text-[14px]">
                    <li><a href="#" class="media-modal-tab-btn active block px-4 py-2 border-l-4 border-l-[#2271b1] bg-white text-black font-semibold" data-target="media-upload-view">Upload files</a></li>
                    <li><a href="#" class="media-modal-tab-btn block px-4 py-2 border-l-4 border-l-transparent text-[#2271b1] hover:text-[#135e96]" data-target="media-library-view">Media Library</a></li>
                </ul>
                <div class="mt-4 px-4 text-[11px] font-bold uppercase tracking-wide text-[#646970]">Folders</div>
                <ul id="mm-folder-list" class="mt-1 text-[13px]"></ul>
            </div>

            <!-- Content Area -->
            <div class="flex-grow flex flex-col overflow-hidden">
                
                {{-- On a phone the tab sidebar is hidden: the same two tabs, across the top. --}}
                <div class="md:hidden flex border-b border-[#c3c4c7] text-[14px] shrink-0">
                    <a href="#" class="media-modal-tab-btn active flex-1 text-center px-2 py-2 border-l-4 border-l-[#2271b1] bg-white text-black font-semibold" data-target="media-upload-view">Upload files</a>
                    <a href="#" class="media-modal-tab-btn flex-1 text-center px-2 py-2 border-l-4 border-l-transparent text-[#2271b1]" data-target="media-library-view">Media Library</a>
                </div>

                <!-- Tab Views -->
                <div class="flex-grow overflow-y-auto p-4 flex flex-col">
                    
                    <!-- Upload Files View -->
                    <div id="media-upload-view" class="media-tab-content flex-grow flex flex-col items-center justify-center">
                        <div class="w-full max-w-[95%] mb-3">
                            @include('falcon-cms::admin.media.partials.upload-to', ['selectId' => 'mm-upload-folder', 'folders' => [], 'selected' => 0])
                        </div>
                        <div class="border-2 border-dashed border-[#c3c4c7] w-full max-w-[95%] h-full flex flex-col items-center justify-center bg-white"
                             onclick="document.getElementById('media-upload-input').click()">
                            @php
                                $allowedRaw = get_cms_option('performance_allowed_formats', '[]');
                                $allowedFormats = is_array($allowedRaw) ? $allowedRaw : json_decode($allowedRaw, true);
                                $accept = !empty($allowedFormats) ? '.' . implode(',.', $allowedFormats) : '';
                            @endphp
                            <input type="file" id="media-upload-input" class="hidden" multiple {!! $accept ? 'accept="'.$accept.'"' : '' !!}>
                            
                            <h3 class="text-[20px] font-normal text-[#1d2327] mb-2">Drop files to upload</h3>
                            <p class="text-[#646970] mb-4">or</p>
                            <button type="button" class="border border-[#2271b1] text-[#2271b1] bg-white hover:bg-[#f6f7f7] px-5 py-2 rounded-md text-[14px] font-medium transition-colors mb-4">Select Files</button>
                            
                            <p class="text-[12px] text-[#646970] font-medium uppercase tracking-wide">
                                @if(!empty($allowedFormats))
                                    Allowed: {{ strtoupper(implode(', ', $allowedFormats)) }}
                                @else
                                    All formats allowed
                                @endif
                            </p>

                            <p class="mt-8 text-[12px] text-[#646970]">Maximum upload file size: 10 MB.</p>
                        </div>
                    </div>

                    <!-- Media Library View -->
                    <div id="media-library-view" class="media-tab-content hidden flex-grow relative">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <select id="mm-folder-select" class="wp-input h-7 text-[12px] py-0 w-48 mm-folder-options" data-with-all="1" aria-label="Folder"></select>
                                <button type="button" id="mm-new-folder-btn" class="wp-btn-secondary h-7 px-2 text-[12px] items-center gap-1 hidden" title="New folder"><span class="material-symbols-outlined text-[16px]">create_new_folder</span>New folder</button>
                                <form id="mm-new-folder-form" class="hidden flex-wrap items-center gap-1">
                                    <input type="text" id="mm-new-folder-name" maxlength="100" placeholder="Folder name" class="wp-input h-7 text-[12px] w-40">
                                    <select id="mm-new-folder-parent" class="wp-input h-7 text-[12px] py-0 w-48" aria-label="Where"></select>
                                    <button type="submit" class="wp-btn-primary h-7 px-3 text-[12px]">Create</button>
                                    <button type="button" id="mm-new-folder-cancel" class="wp-btn-secondary h-7 px-2 text-[12px]">Cancel</button>
                                </form>
                            </div>
                            <input type="text" id="mm-search" placeholder="Search media items..." class="wp-input h-7 text-[12px] w-full sm:w-48">
                        </div>
                        <div id="media-library-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2">
                            <!-- JS populated -->
                        </div>
                        <div id="media-loading-spinner" class="hidden absolute inset-0 bg-white/50 flex items-center justify-center">
                            <svg class="animate-spin h-8 w-8 text-[#2271b1]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </div>
                    </div>

                </div>

                <!-- Footer (Selected Info) -->
                <div class="h-[60px] border-t border-[#c3c4c7] flex justify-between items-center px-4 shrink-0 bg-white">
                    <div id="selected-summary" class="text-[13px] text-[#646970]">
                        <span id="selected-count">0</span> item selected
                    </div>
                    <button type="button" id="insert-media-btn" class="wp-btn-primary px-6 opacity-50 cursor-not-allowed" disabled>Add Media</button>
                </div>
            </div>

            <!-- Sidebar Right (Attachment Details) -->
            <div class="w-[280px] border-l border-[#c3c4c7] bg-[#f0f0f1] p-4 shrink-0 overflow-y-auto hidden lg:block" id="media-details-sidebar">
                <h3 class="uppercase text-[12px] font-bold text-[#646970] mb-4">Attachment Details</h3>
                <div id="details-empty" class="text-[13px] text-[#646970] italic">Select an item to see details.</div>
                <div id="details-view" class="hidden space-y-4">
                    <div class="flex flex-col gap-3">
                        <img id="detail-thumb" src="" class="w-full h-auto max-h-[150px] object-contain bg-white border border-[#c3c4c7]">
                        <div id="detail-video-thumb" class="w-full h-[120px] bg-[#0d1117] flex items-center justify-center border border-[#c3c4c7] rounded" style="display:none;">
                            <div class="flex flex-col items-center gap-2">
                                <i class="fa fa-film text-white/40 text-3xl"></i>
                                <span id="detail-video-ext" class="text-white/30 text-[10px] font-bold uppercase tracking-widest">Video</span>
                            </div>
                        </div>
                        <div class="text-[12px] break-all">
                            <div id="detail-filename" class="font-bold text-black mt-1">image.jpg</div>
                            <div id="detail-date" class="text-[#646970] mb-1">April 17, 2026</div>
                            <div id="detail-dimensions" class="text-[#646970] mb-2 text-[11px]"></div>
                            <button type="button" id="delete-media-permanently" class="text-[#b32d2e] hover:underline mt-2 text-[12px]">Delete permanently</button>
                        </div>
                    </div>
                    <hr class="border-[#c3c4c7]">
                    <div class="space-y-3">
                        <div><label class="block text-[12px] text-[#646970] mb-1">Alt Text</label><input type="text" id="meta-alt" class="wp-input w-full text-[13px] h-7"></div>
                        <div><label class="block text-[12px] text-[#646970] mb-1">Title</label><input type="text" id="meta-title" class="wp-input w-full text-[13px] h-7"></div>
                        <div><label class="block text-[12px] text-[#646970] mb-1">Caption</label><textarea id="meta-caption" class="wp-input w-full text-[13px] h-16 py-1"></textarea></div>
                        <div><label class="block text-[12px] text-[#646970] mb-1">Description</label><textarea id="meta-desc" class="wp-input w-full text-[13px] h-16 py-1"></textarea></div>
                        <div><label class="block text-[12px] text-[#646970] mb-1">Folder</label><select id="meta-folder" class="wp-input w-full text-[12px] h-7 py-0 mm-folder-options"></select></div>
                        <div><label class="block text-[12px] text-[#646970] mb-1">File URL:</label><input type="text" id="meta-url" readonly class="wp-input w-full text-[12px] h-7 bg-white/50"></div>
                    </div>
                    
                    <div class="pt-2 border-t border-[#c3c4c7] mt-2 space-y-1 text-[11px]">
                        <div><strong>Main File size:</strong> <span id="detail-orig-size" class="text-[#646970]"></span></div>
                        <div><strong>Compression Size:</strong> <span id="detail-comp-size" class="text-[#646970]"></span></div>
                        <div><strong>Total Compression:</strong> <span id="detail-pct" class="text-green-600 font-bold"></span></div>
                        <div><strong>Status:</strong> <span id="detail-status" class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"></span></div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-[#c3c4c7]">
                        <button type="button" id="save-media-meta-btn" class="wp-btn-primary text-[12px] h-7 px-4">Update</button>
                        <span id="save-status-msg" class="text-[12px] text-green-600 font-medium opacity-0 transition-opacity duration-300">Saved!</span>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('wp-media-modal');
        const grid = document.getElementById('media-library-grid');
        const detailsView = document.getElementById('details-view');
        const detailsEmpty = document.getElementById('details-empty');
        const insertBtn = document.getElementById('insert-media-btn');
        const selectedCount = document.getElementById('selected-count');

        let selectedMediaItems = [];
        let isMultiple = false;
        let currentCallback = null;

        // Folders: '' = all files, 'none' = Uncategorized, or a folder's id. Uploads go into the
        // folder that is open (or the one picked under "Upload to").
        const canWrite = modal.dataset.canWrite === '1';
        const csrf = '{{ csrf_token() }}';
        let folders = [], folderTotals = { total: 0, uncategorized: 0 }, currentFolder = '', searchTerm = '';

        const esc = (t) => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const indent = (f) => '— '.repeat(f.depth);

        function renderFolders() {
            const list = document.getElementById('mm-folder-list');
            const row = (value, label, count, icon, depth) => `<li><a href="#" data-folder="${value}" class="mm-folder-link flex items-center gap-1.5 pr-3 py-1.5 border-l-4 ${String(currentFolder) === value ? 'border-l-[#2271b1] bg-white text-black font-semibold' : 'border-l-transparent text-[#2271b1] hover:text-[#135e96]'}" style="padding-left:${12 + depth * 12}px"><span class="material-symbols-outlined text-[16px] ${icon === 'folder' ? 'text-[#dba617]' : ''}">${icon}</span><span class="grow truncate">${esc(label)}</span><span class="text-[11px] text-[#646970]">${count}</span></a></li>`;
            list.innerHTML = row('', 'All files', folderTotals.total, 'perm_media', 0) + row('none', 'Uncategorized', folderTotals.uncategorized, 'folder_off', 0)
                + folders.map(f => row(String(f.id), f.name, f.count, 'folder', f.depth)).join('');

            document.querySelectorAll('.mm-folder-options').forEach(sel => {
                const keep = sel.value;
                const all = sel.dataset.withAll ? '<option value="">All files</option>' : '';
                sel.innerHTML = all + `<option value="${sel.dataset.withAll ? 'none' : ''}">Uncategorized</option>` + folders.map(f => `<option value="${f.id}">${esc(indent(f) + f.name)}</option>`).join('');
                sel.value = keep;
            });
            document.getElementById('mm-folder-select').value = currentFolder;
            document.getElementById('mm-new-folder-parent').innerHTML = '<option value="">At the top level (a main folder)</option>'
                + folders.map(f => `<option value="${f.id}">${esc(indent(f) + f.name)}</option>`).join('');
            document.dispatchEvent(new CustomEvent('falcon-media-folders', { detail: { folders, from: modal } }));
            if (/^\d+$/.test(currentFolder)) document.getElementById('mm-upload-folder').value = currentFolder;
        }

        // A folder made from an "Upload to" (here or on the page behind): show it in the lists.
        document.addEventListener('falcon-media-folders', e => { if (e.detail.made && e.detail.from !== modal) loadFolders(); });

        function loadFolders() {
            return fetch("{{ route('admin.media.folders.index') }}", { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.ok ? res.json() : null)
                .then(data => { if (data) { folders = data.folders || []; folderTotals = { total: data.total, uncategorized: data.uncategorized }; } renderFolders(); })
                .catch(() => renderFolders());
        }

        function openFolder(value) {
            currentFolder = value;
            document.querySelector('[data-target="media-library-view"]').click();
            renderFolders();
        }

        async function postJson(url, body) {
            const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success === false) {
                const msg = data.message || 'That did not work. Please try again.';
                if (typeof window.showToast === 'function') window.showToast(msg, 'error'); else alert(msg);
                return null;
            }
            return data;
        }

        // Global opener
        // Font mode (Customizer → Typography → Custom Font): openMediaModal(cb, { fontFormat: 'woff2' })
        // shows only fonts of that format, uploads only that format, and tells the server the
        // upload is a font for the site's settings (it is checked by its contents there).
        let fontFormat = '';
        let defaultAccept = null;

        window.openMediaModal = function(callback, options = {}) {
            modal.classList.remove('hidden');
            currentCallback = callback;
            isMultiple = options.multiple || false;
            const input = document.getElementById('media-upload-input');
            if (defaultAccept === null) defaultAccept = input.getAttribute('accept') || '';
            fontFormat = /^(woff2|woff|ttf|otf)$/.test(options.fontFormat || '') ? options.fontFormat : '';
            if (fontFormat) input.setAttribute('accept', '.' + fontFormat);
            else if (defaultAccept) input.setAttribute('accept', defaultAccept);
            else input.removeAttribute('accept');
            selectedMediaItems = [];
            resetUI();
            loadFolders();
            loadLibrary();
        };

        const resetUI = () => {
            selectedCount.innerText = '0';
            insertBtn.disabled = true;
            insertBtn.classList.add('opacity-50', 'cursor-not-allowed');
            detailsEmpty.classList.remove('hidden');
            detailsView.classList.add('hidden');
            grid.querySelectorAll('.check-overlay').forEach(el => el.classList.add('hidden'));
            grid.querySelectorAll('.check-icon').forEach(el => el.classList.add('hidden'));
            grid.querySelectorAll('.border-\\[\\#2271b1\\]').forEach(el => el.classList.remove('border-[#2271b1]'));
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            selectedMediaItems = [];
        };
        document.getElementById('close-media-modal').addEventListener('click', closeModal);

        // Tabs
        document.querySelectorAll('.media-modal-tab-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.media-modal-tab-btn').forEach(b => {
                    b.classList.remove('active', 'bg-white', 'text-black', 'font-semibold', 'border-l-[#2271b1]');
                    b.classList.add('border-l-transparent', 'text-[#2271b1]');
                });
                document.querySelectorAll(`.media-modal-tab-btn[data-target="${this.dataset.target}"]`).forEach(b => {
                    b.classList.add('active', 'bg-white', 'text-black', 'font-semibold', 'border-l-[#2271b1]');
                    b.classList.remove('border-l-transparent', 'text-[#2271b1]');
                });
                
                document.querySelectorAll('.media-tab-content').forEach(c => c.classList.add('hidden'));
                document.getElementById(this.dataset.target).classList.remove('hidden');
                
                if (this.dataset.target === 'media-library-view') loadLibrary();
            });
        });

        // Upload Logic
        const fileInput = document.getElementById('media-upload-input');
        const uploadView = document.getElementById('media-upload-view');
        const dropZone = uploadView.querySelector('.border-2.border-dashed');
        let isUploading = false;

        async function handleUpload(filesList) {
            if (!filesList || !filesList.length || isUploading) return;
            
            isUploading = true;
            const files = Array.from(filesList);
            
            // UI state updates to switch to library
            document.querySelectorAll('.media-modal-tab-btn').forEach(b => {
                b.classList.remove('active', 'bg-white', 'text-black', 'font-semibold', 'border-l-[#2271b1]');
                b.classList.add('border-l-transparent', 'text-[#2271b1]');
            });
            document.querySelectorAll('.media-modal-tab-btn[data-target="media-library-view"]').forEach(libraryTab => {
                libraryTab.classList.add('active', 'bg-white', 'text-black', 'font-semibold', 'border-l-[#2271b1]');
                libraryTab.classList.remove('border-l-transparent', 'text-[#2271b1]');
            });
            
            document.querySelectorAll('.media-tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('media-library-view').classList.remove('hidden');
            
            document.getElementById('media-loading-spinner').classList.remove('hidden');

            for (const file of files) {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('folder_id', document.getElementById('mm-upload-folder').value);
                if (fontFormat) formData.append('purpose', 'font');

                try {
                    const res = await fetch("{{ route('admin.media.store') }}", {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const isJson = res.headers.get('content-type')?.includes('application/json');
                    const data = isJson ? await res.json() : null;
                    
                    if (!res.ok || !data || !data.success) {
                        console.error('Upload Error:', data || await res.text());
                        // Say why, rather than leaving the file silently missing from the grid.
                        const msg = file.name + ': ' + ((data && data.message) || 'the upload was refused.');
                        if (typeof window.showToast === 'function') window.showToast(msg, 'error');
                        else alert(msg);
                    }
                } catch (err) {
                    console.error('Fetch Error:', err);
                }
            }

            // Show the folder the files went into.
            currentFolder = document.getElementById('mm-upload-folder').value || 'none';
            loadFolders();
            loadLibrary();
            document.getElementById('media-loading-spinner').classList.add('hidden');
            isUploading = false;
            fileInput.value = '';
        }

        fileInput.addEventListener('change', function() {
            handleUpload(this.files);
        });

        // Drag & Drop
        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            dropZone.classList.add('border-[#2271b1]', 'bg-blue-50');
        });

        dropZone.addEventListener('dragleave', e => {
            dropZone.classList.remove('border-[#2271b1]', 'bg-blue-50');
        });

        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('border-[#2271b1]', 'bg-blue-50');
            handleUpload(e.dataTransfer.files);
        });

        // Video helpers
        function isFontFile(path) {
            return /\.(woff2|woff|ttf|otf)$/i.test(path || '');
        }

        function isVideoFile(path) {
            return /\.(mp4|webm|ogg|mov|avi|mkv|m4v|flv|wmv)$/i.test(path);
        }

        function captureVideoThumb(src, callback) {
            const vid = document.createElement('video');
            vid.preload = 'metadata';
            vid.muted = true;
            vid.playsInline = true;
            vid.src = src + '#t=1'; // media fragment: browser loads from 1s, no explicit seek needed
            let done = false;
            const finish = (result) => { if (!done) { done = true; vid.src = ''; callback(result); } };
            vid.addEventListener('loadeddata', () => {
                try {
                    const c = document.createElement('canvas');
                    c.width = vid.videoWidth || 320;
                    c.height = vid.videoHeight || 180;
                    c.getContext('2d').drawImage(vid, 0, 0, c.width, c.height);
                    finish(c.toDataURL('image/jpeg', 0.8));
                } catch(e) { finish(null); }
            });
            vid.addEventListener('error', () => finish(null));
            setTimeout(() => finish(null), 10000);
            vid.load();
        }

        // Library Logic
        function loadLibrary() {
            document.getElementById('media-loading-spinner').classList.remove('hidden');
            const params = new URLSearchParams();
            if (fontFormat) { params.set('type', 'font'); params.set('font_format', fontFormat); }
            if (currentFolder) params.set('folder', currentFolder);
            if (searchTerm) params.set('s', searchTerm);
            fetch("{{ route('admin.media.index') }}" + (params.toString() ? '?' + params.toString() : ''), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                grid.innerHTML = '';
                if (!data.data.length) {
                    grid.innerHTML = `<div class="col-span-full py-16 text-center text-[13px] text-[#646970] italic">${currentFolder && !searchTerm ? 'This folder is empty.' : 'No media files found.'}</div>`;
                }
                data.data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = `relative aspect-square border-2 border-transparent bg-gray-100 cursor-pointer overflow-hidden group item-media-${item.id}`;
                    const isVid = isVideoFile(item.path);
                    const mediaTpl = isFontFile(item.path)
                        ? `<div class="w-full h-full bg-white flex flex-col items-center justify-center gap-1">
                               <span class="text-[26px] leading-none text-[#1d2327] pointer-events-none" style="font-family:serif">Aa</span>
                               <span class="text-[#646970] text-[9px] font-bold uppercase tracking-wider pointer-events-none">${item.path.split('.').pop()}</span>
                           </div>`
                        : isVid
                        ? `<div class="w-full h-full bg-[#0d1117] flex flex-col items-center justify-center gap-1 vid-placeholder-${item.id}">
                               <i class="fa fa-film text-white/35 text-xl pointer-events-none"></i>
                               <span class="text-white/25 text-[9px] font-bold uppercase tracking-wider pointer-events-none">${item.path.split('.').pop()}</span>
                           </div>`
                        : `<img src="/storage/${item.path}" class="w-full h-full object-cover">`;
                    div.innerHTML = mediaTpl +
                        `<div class="absolute inset-0 border-4 border-[#2271b1] hidden check-overlay" id="overlay-${item.id}"></div>
                         <div class="absolute top-1 right-1 bg-[#2271b1] text-white rounded-full p-0.5 hidden check-icon" id="icon-${item.id}"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg></div>`;
                    div.addEventListener('click', () => selectItem(item, div));
                    grid.appendChild(div);

                    if (isVid) {
                        captureVideoThumb('/storage/' + item.path, dataUrl => {
                            if (!dataUrl) return;
                            const placeholder = div.querySelector(`.vid-placeholder-${item.id}`);
                            if (!placeholder) return;
                            const img = document.createElement('img');
                            img.src = dataUrl;
                            img.className = 'w-full h-full object-cover';
                            placeholder.replaceWith(img);
                        });
                    }
                });
            })
            .finally(() => {
                document.getElementById('media-loading-spinner').classList.add('hidden');
            });
        }

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }


        function selectItem(item, el) {
            const index = selectedMediaItems.findIndex(m => m.id === item.id);
            
            if (!isMultiple) {
                // Clear previous selections
                document.querySelectorAll('#media-library-grid > div').forEach(d => {
                    d.classList.remove('border-[#2271b1]');
                    d.querySelector('.check-overlay').classList.add('hidden');
                    d.querySelector('.check-icon').classList.add('hidden');
                });
                selectedMediaItems = [item];
                el.classList.add('border-[#2271b1]');
                el.querySelector('.check-overlay').classList.remove('hidden');
                el.querySelector('.check-icon').classList.remove('hidden');
            } else {
                // Toggle selection
                if (index > -1) {
                    selectedMediaItems.splice(index, 1);
                    el.classList.remove('border-[#2271b1]');
                    el.querySelector('.check-overlay').classList.add('hidden');
                    el.querySelector('.check-icon').classList.add('hidden');
                } else {
                    selectedMediaItems.push(item);
                    el.classList.add('border-[#2271b1]');
                    el.querySelector('.check-overlay').classList.remove('hidden');
                    el.querySelector('.check-icon').classList.remove('hidden');
                }
            }

            if (selectedMediaItems.length > 0) {
                const latest = selectedMediaItems[selectedMediaItems.length - 1];
                showDetails(latest);
                insertBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                insertBtn.disabled = false;
            } else {
                detailsEmpty.classList.remove('hidden');
                detailsView.classList.add('hidden');
                insertBtn.classList.add('opacity-50', 'cursor-not-allowed');
                insertBtn.disabled = true;
            }
            
            selectedCount.innerText = selectedMediaItems.length;
        }

        function showDetails(item) {
            if (!item) {
                detailsEmpty.classList.remove('hidden');
                detailsView.classList.add('hidden');
                return;
            }
            detailsEmpty.classList.add('hidden');
            detailsView.classList.remove('hidden');

            // Fill details
            const thumbEl = document.getElementById('detail-thumb');
            const videoThumbEl = document.getElementById('detail-video-thumb');
            if (isFontFile(item.path)) {
                thumbEl.style.display = 'none';
                videoThumbEl.style.display = 'flex';
                document.getElementById('detail-video-ext').innerText = item.path.split('.').pop().toUpperCase() + ' font';
            } else if (isVideoFile(item.path)) {
                thumbEl.style.display = 'none';
                videoThumbEl.style.display = 'flex';
                document.getElementById('detail-video-ext').innerText = item.path.split('.').pop().toUpperCase();
                captureVideoThumb('/storage/' + item.path, dataUrl => {
                    if (dataUrl) {
                        thumbEl.src = dataUrl;
                        thumbEl.style.display = '';
                        videoThumbEl.style.display = 'none';
                    }
                });
            } else {
                thumbEl.style.display = '';
                videoThumbEl.style.display = 'none';
                thumbEl.src = `/storage/${item.path}`;
            }
            document.getElementById('detail-filename').innerText = item.filename;
            document.getElementById('detail-date').innerText = new Date(item.created_at).toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'});
            document.getElementById('detail-dimensions').innerText = (item.width && item.height) ? `Width: ${item.width}px by Height: ${item.height}px` : 'N/A';
            
            const orig = item.original_size || 0;
            const comp = item.compressed_size || orig;
            
            document.getElementById('detail-orig-size').innerText = formatBytes(orig);
            document.getElementById('detail-comp-size').innerText = formatBytes(comp);
            
            const pctEl = document.getElementById('detail-pct');
            const statusEl = document.getElementById('detail-status');

            if (comp < orig && orig > 0) {
                const saved = orig - comp;
                const pct = Math.round((saved / orig) * 100);
                pctEl.innerText = pct + '%';
                statusEl.innerText = 'Compressed';
                statusEl.className = 'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-green-100 text-green-700';
            } else {
                pctEl.innerText = '0%';
                statusEl.innerText = 'Uncompressed';
                statusEl.className = 'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-700';
            }

            document.getElementById('meta-alt').value = item.alt_text || '';
            document.getElementById('meta-title').value = item.title || '';
            document.getElementById('meta-caption').value = item.caption || '';
            document.getElementById('meta-desc').value = item.description || '';
            document.getElementById('meta-url').value = window.location.origin + '/storage/' + item.path;
            const folderSel = document.getElementById('meta-folder');
            folderSel.value = item.folder_id ? String(item.folder_id) : '';
            folderSel.disabled = !canWrite;
        }

        // Folder list, folder filter, search, new folder, and moving the selected file.
        document.getElementById('mm-folder-list').addEventListener('click', e => {
            const link = e.target.closest('.mm-folder-link');
            if (!link) return;
            e.preventDefault();
            openFolder(link.dataset.folder);
        });
        document.getElementById('mm-folder-select').addEventListener('change', function () { currentFolder = this.value; renderFolders(); loadLibrary(); });
        let searchTimer = null;
        document.getElementById('mm-search').addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { searchTerm = this.value.trim(); loadLibrary(); }, 300);
        });

        const newBtn = document.getElementById('mm-new-folder-btn'), newForm = document.getElementById('mm-new-folder-form');
        if (canWrite) { newBtn.classList.remove('hidden'); newBtn.classList.add('inline-flex'); }
        const toggleNew = (show) => {
            newForm.classList.toggle('hidden', !show); newForm.classList.toggle('flex', show);
            newBtn.classList.toggle('hidden', show); newBtn.classList.toggle('inline-flex', !show);
            if (show) {
                document.getElementById('mm-new-folder-name').value = '';
                document.getElementById('mm-new-folder-parent').value = /^\d+$/.test(currentFolder) ? currentFolder : '';
                document.getElementById('mm-new-folder-name').focus();
            }
        };
        newBtn.addEventListener('click', () => toggleNew(true));
        document.getElementById('mm-new-folder-cancel').addEventListener('click', () => toggleNew(false));
        newForm.addEventListener('submit', async e => {
            e.preventDefault();
            const name = document.getElementById('mm-new-folder-name').value.trim();
            if (!name) return;
            const where = document.getElementById('mm-new-folder-parent').value;
            const data = await postJson("{{ route('admin.media.folders.store') }}", { name, parent_id: where ? Number(where) : null });
            if (!data) return;
            toggleNew(false);
            currentFolder = String(data.folder.id);
            await loadFolders();
            loadLibrary();
        });

        document.getElementById('meta-folder').addEventListener('change', async function () {
            if (!selectedMediaItems.length) return;
            const item = selectedMediaItems[selectedMediaItems.length - 1];
            const folderId = this.value ? Number(this.value) : null;
            if (await postJson("{{ route('admin.media.move') }}", { ids: [item.id], folder_id: folderId })) {
                item.folder_id = folderId;
                if (typeof window.showToast === 'function') window.showToast('Moved to ' + this.options[this.selectedIndex].text.replace(/^(— )+/, ''));
                loadFolders();
            } else {
                this.value = item.folder_id ? String(item.folder_id) : '';
            }
        });

        document.getElementById('save-media-meta-btn').addEventListener('click', function() {
            if (selectedMediaItems.length === 0) return;
            const currentItem = selectedMediaItems[selectedMediaItems.length - 1];
            const btn = this;
            const originalText = btn.innerText;
            
            btn.disabled = true;
            btn.innerText = 'Saving...';

            const data = {
                alt_text: document.getElementById('meta-alt').value,
                title: document.getElementById('meta-title').value,
                caption: document.getElementById('meta-caption').value,
                description: document.getElementById('meta-desc').value,
                _token: '{{ csrf_token() }}',
                _method: 'PUT'
            };

            fetch(`/admin/media/${currentItem.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    const updatedItem = response.data;
                    const index = selectedMediaItems.findIndex(m => m.id === updatedItem.id);
                    if (index > -1) selectedMediaItems[index] = updatedItem;
                    
                    const gridItem = document.querySelector(`.item-media-${updatedItem.id}`);
                    if (gridItem) {
                        const img = gridItem.querySelector('img');
                        if (img) img.src = `/storage/${updatedItem.path}?v=${new Date().getTime()}`;
                    }

                    showDetails(updatedItem);
                    
                    const statusMsg = document.getElementById('save-status-msg');
                    statusMsg.classList.remove('opacity-0');
                    btn.innerText = originalText;
                    btn.disabled = false;
                    
                    setTimeout(() => {
                        statusMsg.classList.add('opacity-0');
                    }, 3000);
                }
            })
            .catch(err => {
                console.error(err);
                btn.innerText = 'Error!';
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }, 2000);
            });
        });

        document.getElementById('delete-media-permanently').addEventListener('click', function() {
           if (selectedMediaItems.length === 0 || !confirm('Are you sure you want to delete this file permanently?')) return;
           const currentItem = selectedMediaItems[selectedMediaItems.length - 1];
            fetch(`/admin/media/${currentItem.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({_token: '{{ csrf_token() }}', _method: 'DELETE'})
            }).then(() => {
               loadLibrary();
               showDetails(null); // or clear UI
               detailsView.classList.add('hidden');
               detailsEmpty.classList.remove('hidden');
               selectedMediaItems = [];
               insertBtn.disabled = true;
               insertBtn.classList.add('opacity-50', 'cursor-not-allowed');
           });
        });

        insertBtn.addEventListener('click', function() {
            if (selectedMediaItems.length > 0 && currentCallback) {
                if (isMultiple) {
                    currentCallback(selectedMediaItems);
                } else {
                    currentCallback(selectedMediaItems[0]);
                }
                closeModal();
            }
        });

    })();
</script>
