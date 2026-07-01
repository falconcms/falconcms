<x-falcon-cms::layouts.admin>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Upload New Media</h1>
        <a href="{{ route('admin.media.index') }}" class="wp-btn-secondary text-[13px]">← Back to Library</a>
    </div>

    <!-- Upload Zone -->
    <div id="media-upload-area" class="flex flex-col items-center justify-center border-2 border-dashed border-[#c3c4c7] rounded-sm bg-white py-20 px-10 transition-all cursor-pointer hover:border-[#2271b1] hover:bg-[#f8f8f8]"
         onclick="document.getElementById('media-page-upload-input').click()">
        @php
            $allowedRaw = get_cms_option('performance_allowed_formats', '[]');
            $allowedFormats = is_array($allowedRaw) ? $allowedRaw : json_decode($allowedRaw, true);
            $accept = !empty($allowedFormats) ? '.' . implode(',.', $allowedFormats) : '';
        @endphp
        <div class="text-center pointer-events-none">
            <svg class="w-16 h-16 mx-auto text-[#c3c4c7] mb-4" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"/>
            </svg>
            <h2 class="text-[20px] mb-2 font-normal text-[#1d2327]">Drop files to upload</h2>
            <p class="text-[14px] text-[#646970] mb-4">or click to browse</p>
            <input type="file" id="media-page-upload-input" class="hidden" multiple {!! $accept ? 'accept="'.$accept.'"' : '' !!}>
            <span class="wp-btn-secondary px-6 pointer-events-none">Select Files</span>
            <p class="mt-6 text-[12px] text-[#646970]">Maximum upload file size: {{ falcon_max_upload_human() }} <span class="text-[#9ca3af]">(server limit)</span>.</p>
        </div>
    </div>

    <div id="upload-status-container" class="mt-4 space-y-2 hidden">
        <h3 class="text-[14px] font-bold text-[#1d2327]">Uploading...</h3>
        <div id="upload-progress-list" class="space-y-2"></div>
    </div>

    <div class="mt-6 text-[13px] text-[#646970]">
        You are using the multi-file uploader. 
        @if(!empty($allowedFormats))
            Supported formats: {{ strtoupper(implode(', ', $allowedFormats)) }}.
        @else
            All file formats are allowed.
        @endif
    </div>

    <script>
    (function() {
        const uploadArea  = document.getElementById('media-upload-area');
        const fileInput   = document.getElementById('media-page-upload-input');
        const statusBox   = document.getElementById('upload-status-container');
        const progressList = document.getElementById('upload-progress-list');

        let activeUploads = 0;

        // Drag & Drop
        uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('border-[#2271b1]', 'bg-blue-50'); });
        uploadArea.addEventListener('dragleave', e => { uploadArea.classList.remove('border-[#2271b1]', 'bg-blue-50'); });
        uploadArea.addEventListener('drop', e => {
            e.preventDefault();
            uploadArea.classList.remove('border-[#2271b1]', 'bg-blue-50');
            processFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', () => { processFiles(fileInput.files); fileInput.value = ''; });

        function processFiles(files) {
            if (!files || !files.length) return;
            statusBox.classList.remove('hidden');
            activeUploads += files.length;
            Array.from(files).forEach((file, i) => setTimeout(() => uploadFile(file), i * 300));
        }

        async function uploadFile(file) {
            const row = document.createElement('div');
            row.className = 'bg-white border border-[#c3c4c7] rounded-sm overflow-hidden shadow-sm';
            row.innerHTML = `
                <div class="flex items-center p-2 gap-3">
                    <div class="w-10 h-10 bg-[#f0f0f1] flex-shrink-0 overflow-hidden rounded">
                        <img src="${URL.createObjectURL(file)}" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-grow min-w-0">
                        <div class="text-[13px] font-medium text-[#1d2327] truncate">${file.name}</div>
                        <div class="h-1.5 bg-[#f0f0f1] rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-[#2271b1] rounded-full transition-all duration-500 w-0 progress-fill"></div>
                        </div>
                    </div>
                    <span class="text-[12px] text-[#646970] status-text flex-shrink-0 w-24 text-right">Waiting...</span>
                </div>
                <div class="compress-info hidden px-2 pb-2 text-[11px] text-[#646970] pl-15"></div>
            `;
            progressList.prepend(row);

            const fill        = row.querySelector('.progress-fill');
            const statusText  = row.querySelector('.status-text');
            const compressDiv = row.querySelector('.compress-info');

            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('_token', '{{ csrf_token() }}');

                statusText.innerText = 'Uploading...';
                fill.style.width = '40%';

                const response = await fetch('{{ route('admin.media.store') }}', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });

                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Upload failed');

                fill.style.width = '100%';
                fill.classList.remove('bg-[#2271b1]');
                fill.classList.add('bg-green-500');
                statusText.innerText = 'Done ✓';
                statusText.classList.add('text-green-600');

                if (data.was_compressed) {
                    compressDiv.classList.remove('hidden');
                    compressDiv.innerHTML = `📦 Compressed: <span class="line-through">${data.original_size}</span> → <strong class="text-green-600">${data.compressed_size}</strong>`;
                }

            } catch (err) {
                fill.classList.add('bg-red-500');
                statusText.innerText = 'Error';
                statusText.classList.add('text-red-600');
            } finally {
                activeUploads--;
                if (activeUploads === 0) {
                    setTimeout(() => { window.location.href = "{{ route('admin.media.index') }}"; }, 1000);
                }
            }
        }
    })();
    </script>
</x-falcon-cms::layouts.admin>
