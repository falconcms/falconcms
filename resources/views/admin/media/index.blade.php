<x-falcon-cms::layouts.admin title="Media Library">
    <x-falcon-cms::admin.delete-modal />
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Media Library</h1>
            <a href="{{ route('admin.media.create') }}" class="wp-btn-secondary px-2 py-0.5 text-[13px]">Add Media File</a>
        </div>
    </div>

    <!-- Media Toolbar -->
    <form action="{{ route('admin.media.index') }}" method="GET" class="flex flex-wrap items-center justify-between gap-4 mb-4 bg-white p-2 border border-[#c3c4c7] rounded-sm">
        <div class="flex items-center gap-2">
            <!-- View Icons -->
            <div class="flex border-r border-[#c3c4c7] pr-2 mr-2">
                <button type="button" id="view-grid-btn" class="p-1 text-[#646970] hover:bg-[#f0f0f1] rounded" title="Grid View">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                </button>
                <button type="button" id="view-list-btn" class="p-1 text-[#2271b1] hover:bg-[#f0f0f1] rounded" title="List View">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            
            <select name="type" class="wp-input h-7 text-[13px] py-0 w-36">
                <option value="all" {{ request('type') == 'all' ? 'selected' : '' }}>All media items</option>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" {{ request('type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="m" class="wp-input h-7 text-[13px] py-0 w-32">
                <option value="">All dates</option>
                @foreach($months as $month)
                    <option value="{{ $month->month_val }}" {{ request('m') == $month->month_val ? 'selected' : '' }}>
                        {{ $month->month_label }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="wp-btn-secondary h-7 px-3 text-[13px]">Filter</button>
        </div>
        
        <div class="flex items-center gap-2">
            <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-7 text-[13px] w-48" placeholder="Search media...">
            <button type="submit" class="wp-btn-secondary h-7 px-3 text-[13px]">Search Media</button>
        </div>
    </form>

    <!-- Top Bulk Actions & Pagination -->
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
            <select id="bulk-action-selector-top" class="wp-input h-7 text-[13px] py-0 w-32">
                <option value="">Bulk actions</option>
                <option value="delete">Delete permanently</option>
            </select>
            <button id="apply-bulk-action-top" class="wp-btn-secondary h-7 px-3 text-[13px]">Apply</button>
            <button id="bulk-select-btn" class="wp-btn-secondary h-7 px-3 text-[13px] hidden">Bulk Select</button>
        </div>
        <div id="pagination-top" class="flex items-center gap-4 text-[13px] text-[#646970]">
            <span>{{ $media->total() }} items</span>
            <div class="flex items-center gap-1">
                {{ $media->links('falcon-cms::components.admin.pagination') }}
            </div>
        </div>
    </div>

    <!-- Media Grid Container -->
    <div id="media-grid-view" class="hidden">
        <div id="media-grid-container" class="grid grid-cols-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-3 bg-white p-4 border border-[#c3c4c7]">
            @forelse($media as $index => $item)
                <div class="relative cursor-pointer group media-item-container" data-id="{{ $item->id }}" data-index="{{ $index }}">
                    <div class="media-item" data-item='@json($item)' data-index="{{ $index }}">
                        <div class="aspect-square border-2 border-transparent bg-[#f0f0f1] overflow-hidden group-hover:border-[#2271b1] transition-all flex items-center justify-center">
                            @if(strpos($item->mime_type, 'image/') === 0)
                                <img src="{{ asset('storage/'.$item->path) }}" class="w-full h-full object-cover">
                            @elseif(strpos($item->mime_type, 'video/') === 0)
                                <div class="w-full h-full bg-[#0d1117] flex flex-col items-center justify-center gap-1 vid-thumb-wrapper" data-video-src="{{ asset('storage/'.$item->path) }}">
                                    <span class="material-symbols-outlined text-white/40 text-4xl">movie</span>
                                </div>
                            @elseif($item->mime_type === 'application/pdf')
                                <span class="material-symbols-outlined text-[#646970] text-4xl">description</span>
                            @else
                                <span class="material-symbols-outlined text-[#646970] text-4xl">draft</span>
                            @endif
                        </div>
                    </div>
                    <div class="absolute top-1 right-1 z-10 media-checkbox-wrapper hidden">
                        <input type="checkbox" value="{{ $item->id }}" class="media-checkbox w-5 h-5 cursor-pointer">
                    </div>
                    <div class="mt-1 px-1 overflow-hidden">
                        <div class="text-[11px] text-[#1d2327] truncate font-bold media-display-title" title="{{ $item->title ?: $item->filename }}">
                            {{ $item->title ?: $item->filename }}
                        </div>
                        <div class="text-[9px] text-[#646970] truncate media-display-filename">{{ $item->filename }}</div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-20 text-center text-[#646970] italic">No media files found.</div>
            @endforelse
        </div>
        <!-- Load More Button -->
        <div id="load-more-container" class="flex justify-center mt-6 mb-10 {{ $media->hasMorePages() ? '' : 'hidden' }}">
            <button id="load-more-btn" class="wp-btn-secondary h-10 px-8 text-[14px] font-semibold" data-next-page="2">Load more items</button>
        </div>
    </div>

    <!-- Media List Table -->
    <div id="media-list-container" class="bg-white border border-[#c3c4c7]">
        <table class="wp-list-table w-full text-left text-[13px] border-collapse">
            <thead>
                <tr class="border-b border-[#c3c4c7] bg-[#f9f9f9]">
                    <th class="p-2 w-8"><input type="checkbox" id="select-all-media" class="w-4 h-4"></th>
                    <th class="p-2 w-20">File</th>
                    <th class="p-2 font-semibold">Title</th>
                    <th class="p-2 font-semibold">Author</th>
                    <th class="p-2 font-semibold">Uploaded to</th>
                    <th class="p-2 font-semibold">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($media as $index => $item)
                    <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7] group" data-id="{{ $item->id }}" data-index="{{ $index }}">
                        <td class="p-2 text-center align-top pt-3">
                            <input type="checkbox" value="{{ $item->id }}" class="media-checkbox w-4 h-4">
                        </td>
                        <td class="p-2 align-top">
                            <div class="w-16 h-16 bg-[#f0f0f1] border border-[#c3c4c7] overflow-hidden cursor-pointer media-item flex items-center justify-center" data-item='@json($item)' data-index="{{ $index }}">
                                @if(strpos($item->mime_type, 'image/') === 0)
                                    <img src="{{ asset('storage/'.$item->path) }}" class="w-full h-full object-cover">
                                @elseif(strpos($item->mime_type, 'video/') === 0)
                                    <div class="w-full h-full bg-[#0d1117] flex items-center justify-center vid-thumb-wrapper" data-video-src="{{ asset('storage/'.$item->path) }}">
                                        <span class="material-symbols-outlined text-white/40 text-2xl">movie</span>
                                    </div>
                                @elseif($item->mime_type === 'application/pdf')
                                    <span class="material-symbols-outlined text-[#646970] text-3xl">description</span>
                                @else
                                    <span class="material-symbols-outlined text-[#646970] text-3xl">draft</span>
                                @endif
                            </div>
                        </td>
                        <td class="p-2 align-top pt-3">
                            <div class="font-bold text-[#2271b1] hover:text-[#135e96] cursor-pointer media-item media-display-title" data-item='@json($item)' data-index="{{ $index }}">
                                {{ $item->title ?: $item->filename }}
                            </div>
                            <div class="text-[11px] text-[#646970] mt-1 media-display-filename">{{ $item->filename }}</div>
                            <!-- Row Actions -->
                            <div class="flex gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity text-[12px]">
                                <a href="#" class="text-[#2271b1] hover:text-[#135e96] media-item" data-item='@json($item)' data-index="{{ $index }}">Edit</a>
                                <span class="text-[#c3c4c7]">|</span>
                                <button class="text-[#b32d2e] hover:text-[#8a2424]" onclick="confirmDelete('{{ $item->id }}')">Delete Permanently</button>
                                <span class="text-[#c3c4c7]">|</span>
                                <a href="{{ asset('storage/'.$item->path) }}" target="_blank" class="text-[#2271b1] hover:underline">View</a>
                            </div>
                        </td>
                        <td class="p-2 align-top pt-3 text-[#2271b1]">{{ $item->user->name ?? 'Admin' }}</td>
                        <td class="p-2 align-top pt-3">
                            @if($item->model_type && $item->model_id)
                                <div class="text-[#2271b1] hover:text-[#135e96] cursor-pointer">Linked Content</div>
                                <div class="text-[11px] text-[#646970] mt-1 italic">Detach</div>
                            @else
                                <span class="text-[#646970] italic">(Unattached)</span>
                                <div class="text-[11px] text-[#2271b1] hover:text-[#135e96] mt-1 cursor-pointer">Attach</div>
                            @endif
                        </td>
                        <td class="p-2 align-top pt-3 text-[#646970]">{{ cms_date($item->created_at, 'Y/m/d') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-[#646970] italic bg-white">No media files found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Bottom Bulk Actions & Pagination -->
    <div id="pagination-bottom-container" class="flex items-center justify-between mt-4">
        <div class="flex items-center gap-2">
            <select id="bulk-action-selector-bottom" class="wp-input h-7 text-[13px] py-0 w-32">
                <option value="">Bulk actions</option>
                <option value="delete">Delete permanently</option>
            </select>
            <button id="apply-bulk-action-bottom" class="wp-btn-secondary h-7 px-3 text-[13px]">Apply</button>
        </div>
        <div class="flex items-center gap-4 text-[13px] text-[#646970]">
            <span>{{ $media->total() }} items</span>
            <div class="flex items-center gap-1">
                {{ $media->links('falcon-cms::components.admin.pagination') }}
            </div>
        </div>
    </div>

    <!-- Attachment Details Modal (WP Full Page Style) -->
    <div id="attachment-details-modal" class="hidden fixed inset-0 z-[100000] overflow-hidden flex flex-col">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" id="details-modal-backdrop"></div>
        
        <div class="relative m-4 sm:m-10 bg-white shadow-2xl flex-grow flex flex-col overflow-hidden border border-[#c3c4c7]">
            <!-- Header -->
            <div class="h-[50px] border-b border-[#c3c4c7] flex justify-between items-center px-4 bg-white shrink-0">
                <h1 class="text-[20px] font-normal text-[#1d2327]">Attachment details</h1>
                <div class="flex items-center">
                    <button id="prev-attachment" class="p-2 text-[#646970] hover:text-[#2271b1] disabled:opacity-30"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
                    <button id="next-attachment" class="p-2 text-[#646970] hover:text-[#2271b1] disabled:opacity-30"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
                    <button id="close-details-modal" class="ml-4 p-2 text-[#646970] hover:text-black">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="flex flex-grow overflow-hidden flex-col md:flex-row">
                <!-- Large Image / Video View -->
                <div class="flex-grow bg-[#f0f0f1] flex items-center justify-center p-8 overflow-auto relative">
                    <img id="modal-detail-img" src="" class="max-w-full max-h-full shadow-lg">
                    <video id="modal-detail-video" controls class="max-w-full max-h-full shadow-lg" style="display:none;"></video>
                    <div id="modal-detail-file-placeholder" class="flex flex-col items-center gap-3" style="display:none;">
                        <span class="material-symbols-outlined text-[#9ca3af] text-[80px]" id="modal-placeholder-icon">draft</span>
                        <span id="modal-placeholder-label" class="text-[#646970] text-[13px]"></span>
                    </div>
                </div>

                <!-- Sidebar Details -->
                <div class="w-full md:w-[350px] border-l border-[#c3c4c7] bg-white p-4 overflow-y-auto overflow-x-hidden">
                    <div class="text-[12px] text-[#2c3338] mb-4 pb-4 border-b border-[#c3c4c7] leading-relaxed space-y-1">
                        <div><strong>Uploaded on:</strong> <span id="modal-detail-date" class="text-[#646970]"></span></div>
                        <div class="truncate"><strong>File name:</strong> <span id="modal-detail-filename" class="text-[#646970]"></span></div>
                        <div><strong>File type:</strong> <span id="modal-detail-mime" class="text-[#646970]"></span></div>
                        <div><strong>Dimensions:</strong> <span id="modal-detail-dimensions" class="text-[#646970]"></span></div>
                        <div class="pt-2 border-t border-[#f0f0f1] mt-2">
                            <div><strong>Main File size:</strong> <span id="modal-detail-orig-size" class="text-[#646970]"></span></div>
                            <div><strong>Compression Size:</strong> <span id="modal-detail-comp-size" class="text-[#646970]"></span></div>
                            <div><strong>Total Compression:</strong> <span id="modal-detail-pct" class="text-green-600 font-bold"></span></div>
                            <div><strong>Status:</strong> <span id="modal-detail-status" class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"></span></div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div><label class="block text-[12px] font-bold text-[#646970] mb-1">Alternative Text</label><textarea id="modal-meta-alt" class="wp-input w-full text-[13px] h-16"></textarea></div>
                        <div><label class="block text-[12px] font-bold text-[#646970] mb-1">Title</label><input type="text" id="modal-meta-title" class="wp-input w-full text-[13px] h-8"></div>
                        <div><label class="block text-[12px] font-bold text-[#646970] mb-1">Caption</label><textarea id="modal-meta-caption" class="wp-input w-full text-[13px] h-16"></textarea></div>
                        <div><label class="block text-[12px] font-bold text-[#646970] mb-1">Description</label><textarea id="modal-meta-desc" class="wp-input w-full text-[13px] h-16"></textarea></div>
                        <div><label class="block text-[12px] font-bold text-[#646970] mb-1">File URL:</label><div class="flex gap-1"><input type="text" id="modal-meta-url" readonly class="wp-input grow text-[11px] h-8 bg-[#f6f7f7]"><button onclick="document.getElementById('modal-meta-url').select();document.execCommand('copy');window.showToast('Copied to clipboard!', 'success')" class="wp-btn-secondary h-8 px-2 text-[11px]">Copy</button></div></div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-[#c3c4c7] space-y-4">
                        <div class="flex justify-between items-center">
                            <button id="modal-update-btn" class="wp-btn-primary h-8 px-6 text-[13px] font-semibold">Update</button>
                            <span id="update-status-msg" class="text-[12px] text-green-600 font-medium hidden">Saved!</span>
                        </div>
                        <div class="flex flex-wrap gap-4 text-[13px]">
                            <a href="#" id="modal-view-file" target="_blank" class="text-[#2271b1] hover:underline">View media file</a>
                            <button id="modal-delete-btn" class="text-[#b32d2e] hover:underline">Delete permanently</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatBytes(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' B';
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

        function applyVideoThumbs(container) {
            (container || document).querySelectorAll('.vid-thumb-wrapper[data-video-src]').forEach(wrapper => {
                if (wrapper.dataset.thumbDone) return;
                wrapper.dataset.thumbDone = '1';
                captureVideoThumb(wrapper.dataset.videoSrc, dataUrl => {
                    if (!dataUrl) return;
                    const img = document.createElement('img');
                    img.src = dataUrl;
                    img.className = 'w-full h-full object-cover';
                    wrapper.replaceWith(img);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // State
            let items = Array.from(document.querySelectorAll('.media-item')).map(el => {
                try { return JSON.parse(el.dataset.item); } catch(e) { return null; }
            }).filter(i => i !== null);
            
            const modal = document.getElementById('attachment-details-modal');
            const bulkSelectBtn = document.getElementById('bulk-select-btn');
            const gridView = document.getElementById('media-grid-view');
            const listContainer = document.getElementById('media-list-container');
            const gridContainer = document.getElementById('media-grid-container');
            const paginationTop = document.getElementById('pagination-top');
            const paginationBottom = document.getElementById('pagination-bottom-container');
            const loadMoreBtn = document.getElementById('load-more-btn');
            
            let currentIndex = -1;
            let isBulkSelectMode = false;

            // Modal Functions
            function openDetails(index) {
                currentIndex = index;
                const item = items[index];
                if (!item) return;

                modal.classList.remove('hidden');

                const imgEl   = document.getElementById('modal-detail-img');
                const videoEl = document.getElementById('modal-detail-video');
                const filePh  = document.getElementById('modal-detail-file-placeholder');
                imgEl.style.display = 'none';
                videoEl.style.display = 'none';
                filePh.style.display = 'none';

                if (item.mime_type && item.mime_type.startsWith('video/')) {
                    videoEl.src = `/storage/${item.path}`;
                    videoEl.style.display = 'block';
                } else if (item.mime_type && item.mime_type.startsWith('image/')) {
                    imgEl.src = `/storage/${item.path}`;
                    imgEl.style.display = 'block';
                } else {
                    document.getElementById('modal-placeholder-icon').innerText = item.mime_type === 'application/pdf' ? 'description' : 'draft';
                    document.getElementById('modal-placeholder-label').innerText = item.mime_type || item.filename;
                    filePh.style.display = 'flex';
                }
                document.getElementById('modal-detail-filename').innerText = item.filename;
                document.getElementById('modal-detail-mime').innerText = item.mime_type;
                document.getElementById('modal-detail-dimensions').innerText = (item.width && item.height) ? `Width: ${item.width}px by Height: ${item.height}px` : 'N/A';
                document.getElementById('modal-detail-date').innerText = new Date(item.created_at).toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'});
                
                const orig = item.original_size || 0;
                const comp = item.compressed_size || orig;
                
                document.getElementById('modal-detail-orig-size').innerText = formatBytes(orig);
                document.getElementById('modal-detail-comp-size').innerText = formatBytes(comp);

                const statusEl = document.getElementById('modal-detail-status');
                const pctEl = document.getElementById('modal-detail-pct');

                if (comp < orig) {
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
                
                document.getElementById('modal-meta-alt').value = item.alt_text || '';
                document.getElementById('modal-meta-title').value = item.title || '';
                document.getElementById('modal-meta-caption').value = item.caption || '';
                document.getElementById('modal-meta-desc').value = item.description || '';
                document.getElementById('modal-meta-url').value = window.location.origin + '/storage/' + item.path;
                document.getElementById('modal-view-file').href = `/storage/${item.path}`;

                document.getElementById('prev-attachment').disabled = (index === 0);
                document.getElementById('next-attachment').disabled = (index === items.length - 1);
            }

            // View Control
            function updateViewUI(view) {
                if (view === 'grid') {
                    gridView.classList.remove('hidden');
                    listContainer.classList.add('hidden');
                    paginationTop.classList.add('invisible');
                    paginationBottom.classList.add('hidden');
                    bulkSelectBtn.classList.remove('hidden');
                    document.getElementById('view-grid-btn').classList.add('text-[#2271b1]');
                    document.getElementById('view-grid-btn').classList.remove('text-[#646970]');
                    document.getElementById('view-list-btn').classList.add('text-[#646970]');
                    document.getElementById('view-list-btn').classList.remove('text-[#2271b1]');
                } else {
                    gridView.classList.add('hidden');
                    listContainer.classList.remove('hidden');
                    paginationTop.classList.remove('invisible');
                    paginationBottom.classList.remove('hidden');
                    bulkSelectBtn.classList.add('hidden');
                    document.getElementById('view-list-btn').classList.add('text-[#2271b1]');
                    document.getElementById('view-list-btn').classList.remove('text-[#646970]');
                    document.getElementById('view-grid-btn').classList.add('text-[#646970]');
                    document.getElementById('view-grid-btn').classList.remove('text-[#2271b1]');
                    disableBulkSelectMode();
                }
            }

            function handleMediaItemClick(index, el) {
                if (isBulkSelectMode) {
                    const container = el.closest('.media-item-container') || el.closest('tr');
                    const cb = container.querySelector('.media-checkbox');
                    if (cb) cb.checked = !cb.checked;
                } else {
                    openDetails(index);
                }
            }

            function disableBulkSelectMode() {
                isBulkSelectMode = false;
                bulkSelectBtn.innerText = 'Bulk Select';
                bulkSelectBtn.classList.remove('bg-[#f0f0f1]');
                document.querySelectorAll('.media-checkbox-wrapper').forEach(el => el.classList.add('hidden'));
                document.querySelectorAll('.media-checkbox').forEach(cb => cb.checked = false);
            }

            // Listeners
            document.getElementById('view-grid-btn').addEventListener('click', () => { updateViewUI('grid'); localStorage.setItem('media_view', 'grid'); });
            document.getElementById('view-list-btn').addEventListener('click', () => { updateViewUI('list'); localStorage.setItem('media_view', 'list'); });
            
            bulkSelectBtn.addEventListener('click', () => {
                isBulkSelectMode = !isBulkSelectMode;
                if (isBulkSelectMode) {
                    bulkSelectBtn.innerText = 'Cancel Selection';
                    bulkSelectBtn.classList.add('bg-[#f0f0f1]');
                    document.querySelectorAll('.media-checkbox-wrapper').forEach(el => el.classList.remove('hidden'));
                    // Auto-select all visible items in the grid
                    document.querySelectorAll('#media-grid-container .media-checkbox').forEach(cb => cb.checked = true);
                } else {
                    disableBulkSelectMode();
                }
            });

            document.querySelectorAll('.media-item').forEach(el => {
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    handleMediaItemClick(parseInt(el.dataset.index), el);
                });
            });

            document.getElementById('prev-attachment').addEventListener('click', () => { if(currentIndex > 0) openDetails(currentIndex - 1); });
            document.getElementById('next-attachment').addEventListener('click', () => { if(currentIndex < items.length - 1) openDetails(currentIndex + 1); });
            document.getElementById('close-details-modal').addEventListener('click', () => modal.classList.add('hidden'));
            document.getElementById('details-modal-backdrop').addEventListener('click', () => modal.classList.add('hidden'));

            // Load More
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    const nextPage = this.dataset.nextPage;
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', nextPage);
                    url.searchParams.set('per_page', '10');

                    this.disabled = true;
                    this.innerText = 'Loading...';
                    const btn = this;

                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.json())
                    .then(data => {
                        if (data.data && data.data.length > 0) {
                            data.data.forEach(item => {
                                const newIndex = items.length;
                                items.push(item);
                                const html = `
                                    <div class="relative cursor-pointer group media-item-container" data-id="${item.id}" data-index="${newIndex}">
                                        <div class="media-item" data-index="${newIndex}" data-item='${JSON.stringify(item)}'>
                                            <div class="aspect-square border-2 border-transparent bg-[#f0f0f1] overflow-hidden group-hover:border-[#2271b1] transition-all flex items-center justify-center">
                                                ${item.mime_type.startsWith('image/')
                                                    ? `<img src="/storage/${item.path}" class="w-full h-full object-cover">`
                                                    : item.mime_type.startsWith('video/')
                                                        ? `<div class="w-full h-full bg-[#0d1117] flex flex-col items-center justify-center gap-1 vid-thumb-wrapper" data-video-src="/storage/${item.path}"><span class="material-symbols-outlined text-white/40 text-4xl">movie</span></div>`
                                                        : `<span class="material-symbols-outlined text-[#646970] text-4xl">${item.mime_type === 'application/pdf' ? 'description' : 'draft'}</span>`
                                                }
                                            </div>
                                        </div>
                                        <div class="absolute top-1 right-1 z-10 media-checkbox-wrapper ${isBulkSelectMode ? '' : 'hidden'}">
                                            <input type="checkbox" value="${item.id}" class="media-checkbox w-5 h-5 cursor-pointer">
                                        </div>
                                        <div class="mt-1 px-1 overflow-hidden">
                                            <div class="text-[11px] text-[#1d2327] truncate font-bold media-display-title" title="${item.title || item.filename}">${item.title || item.filename}</div>
                                            <div class="text-[9px] text-[#646970] truncate media-display-filename">${item.filename}</div>
                                        </div>
                                    </div>`;
                                const div = document.createElement('div');
                                div.innerHTML = html.trim();
                                const newEl = div.firstChild;
                                newEl.querySelector('.media-item').addEventListener('click', (e) => {
                                    e.stopPropagation();
                                    handleMediaItemClick(newIndex, newEl.querySelector('.media-item'));
                                });
                                gridContainer.appendChild(newEl);
                            });
                            applyVideoThumbs(gridContainer);

                            if (data.next_page_url) {
                                btn.dataset.nextPage = parseInt(nextPage) + 1;
                                btn.disabled = false;
                                btn.innerText = 'Load more items';
                            } else {
                                document.getElementById('load-more-container').classList.add('hidden');
                            }
                        } else {
                            document.getElementById('load-more-container').classList.add('hidden');
                        }
                    })
                    .catch(() => {
                        btn.disabled = false;
                        btn.innerText = 'Load more items';
                    });
                });
            }

            // Bulk Action Handlers
            const applyTop = document.getElementById('apply-bulk-action-top');
            const applyBottom = document.getElementById('apply-bulk-action-bottom');
            
            async function executeBulkDelete(ids) {
                const confirmed = await window.lazyConfirm({
                    title: 'Delete Media',
                    message: `Are you sure you want to delete ${ids.length > 1 ? ids.length + ' selected items' : 'this item'} permanently? This action cannot be undone.`,
                    confirmText: 'Delete Permanently',
                    isDanger: true
                });

                if (!confirmed) return;

                fetch('{{ route('admin.media.bulk-delete') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ ids: ids })
                }).then(() => location.reload());
            }

            applyTop.addEventListener('click', () => {
                const action = document.getElementById('bulk-action-selector-top').value;
                const ids = Array.from(document.querySelectorAll('.media-checkbox:checked')).map(cb => cb.value);
                if (action === 'delete' && ids.length > 0) executeBulkDelete(ids);
            });

            applyBottom.addEventListener('click', () => {
                const action = document.getElementById('bulk-action-selector-bottom').value;
                const ids = Array.from(document.querySelectorAll('.media-checkbox:checked')).map(cb => cb.value);
                if (action === 'delete' && ids.length > 0) executeBulkDelete(ids);
            });

            // Select-all checkbox in list view header
            const selectAllEl = document.getElementById('select-all-media');
            if (selectAllEl) {
                selectAllEl.addEventListener('change', function () {
                    document.querySelectorAll('#media-list-container .media-checkbox').forEach(cb => {
                        cb.checked = this.checked;
                    });
                });
                // Keep select-all in sync when individual checkboxes change
                document.getElementById('media-list-container').addEventListener('change', function (e) {
                    if (e.target.classList.contains('media-checkbox')) {
                        const all  = document.querySelectorAll('#media-list-container .media-checkbox');
                        const checked = document.querySelectorAll('#media-list-container .media-checkbox:checked');
                        selectAllEl.indeterminate = checked.length > 0 && checked.length < all.length;
                        selectAllEl.checked = checked.length === all.length;
                    }
                });
            }

            window.confirmDelete = (id) => executeBulkDelete([id]);

            // Update Manual Save
            const updateBtn = document.getElementById('modal-update-btn');
            const statusMsg = document.getElementById('update-status-msg');
            updateBtn.addEventListener('click', function() {
                if (currentIndex === -1) return;
                const item = items[currentIndex];
                
                this.disabled = true;
                this.innerText = 'Saving...';

                const data = {
                    alt_text: document.getElementById('modal-meta-alt').value,
                    title: document.getElementById('modal-meta-title').value,
                    caption: document.getElementById('modal-meta-caption').value,
                    description: document.getElementById('modal-meta-desc').value,
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT'
                };

                fetch(`/admin/media/${item.id}`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify(data)
                })
                .then(res => res.json())
                .then(response => {
                    const data = response.data;
                    this.innerText = 'Update';
                    this.disabled = false;
                    statusMsg.classList.remove('hidden');
                    
                    // Update local state completely with server data
                    items[currentIndex] = data;

                    // Update UI (Grid & List) and data-item attributes using UNIQUE ID
                    const newTitle = data.title || data.filename;
                    const containers = document.querySelectorAll(`[data-id="${data.id}"]`);
                    
                    containers.forEach(container => {
                        // 1. Update text displays
                        const titleEl = container.classList.contains('media-display-title') ? container : container.querySelector('.media-display-title');
                        const filenameEl = container.classList.contains('media-display-filename') ? container : container.querySelector('.media-display-filename');
                        
                        if (titleEl) {
                            titleEl.innerText = newTitle;
                            titleEl.title = newTitle;
                        }
                        if (filenameEl) {
                            filenameEl.innerText = data.filename;
                        }

                        // 2. Update data-item attribute for all children that have it
                        const dataItemEls = container.hasAttribute('data-item') ? [container] : [];
                        container.querySelectorAll('[data-item]').forEach(el => dataItemEls.push(el));
                        
                        dataItemEls.forEach(el => {
                            el.dataset.item = JSON.stringify(data);
                        });

                        // 3. Update thumbnail if renamed (the image source)
                        const img = container.querySelector('img');
                        if (img) img.src = `/storage/${data.path}?v=${new Date().getTime()}`;
                    });

                    // Update Modal View for renamed file
                    if (data.mime_type && data.mime_type.startsWith('video/')) {
                        document.getElementById('modal-detail-video').src = `/storage/${data.path}?v=${new Date().getTime()}`;
                    } else {
                        document.getElementById('modal-detail-img').src = `/storage/${data.path}?v=${new Date().getTime()}`;
                    }
                    document.getElementById('modal-detail-filename').innerText = data.filename;
                    document.getElementById('modal-meta-url').value = window.location.origin + '/storage/' + data.path;
                    document.getElementById('modal-view-file').href = `/storage/${data.path}`;

                    setTimeout(() => {
                        statusMsg.classList.add('hidden');
                    }, 3000);
                });
            });

            // Auto-save on blur
            ['alt', 'title', 'caption', 'desc'].forEach(meta => {
                const el = document.getElementById(`modal-meta-${meta}`);
                if (el) {
                    el.addEventListener('blur', function() {
                        if (currentIndex === -1) return;
                        const item = items[currentIndex];
                        const data = {
                            alt_text: document.getElementById('modal-meta-alt').value,
                            title: document.getElementById('modal-meta-title').value,
                            caption: document.getElementById('modal-meta-caption').value,
                            description: document.getElementById('modal-meta-desc').value,
                            _token: '{{ csrf_token() }}',
                            _method: 'PUT'
                        };
                        fetch(`/admin/media/${item.id}`, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                            body: JSON.stringify(data)
                        });
                    });
                }
            });

            document.getElementById('modal-delete-btn').addEventListener('click', async function() {
                if (currentIndex === -1) return;
                
                const confirmed = await window.lazyConfirm({
                    title: 'Delete Media',
                    message: 'Are you sure you want to delete this media item permanently? This action cannot be undone.',
                    confirmText: 'Delete Permanently',
                    isDanger: true
                });

                if (!confirmed) return;

                const item = items[currentIndex];
                fetch(`/admin/media/${item.id}`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({_token: '{{ csrf_token() }}', _method: 'DELETE'})
                }).then(() => location.reload());
            });

            // Restore preference
            const savedView = localStorage.getItem('media_view') || 'grid';
            updateViewUI(savedView);

            // Generate video thumbnails for initial items
            applyVideoThumbs();
        });
    </script>

</x-falcon-cms::layouts.admin>
