<x-falcon-cms::layouts.admin>
    <x-slot name="title">Backup & Snapshots - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Backup & Snapshots</h1>
            <form action="{{ route('admin.backup.create') }}" method="POST">
                @csrf
                <button type="submit" class="wp-btn-primary">
                    <span class="material-symbols-outlined mr-1">backup</span>
                    Create New Snapshot
                </button>
            </form>
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

        {{-- Upload Backup --}}
        <div class="bg-white border border-[#c3c4c7] shadow-sm mb-6">
            <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7] flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-[#646970]">upload_file</span>
                <div>
                    <h2 class="text-[14px] font-semibold text-[#1d2327]">Upload Backup from Another Site</h2>
                    <p class="text-[12px] text-[#646970]">Upload a <code>.sql</code>, <code>.sql.gz</code>, or <code>.zip</code> backup file exported from another site, then restore it below.</p>
                </div>
            </div>
            <div class="p-5">
                <form action="{{ route('admin.backup.upload') }}" method="POST" enctype="multipart/form-data" id="upload-backup-form">
                    @csrf
                    @error('backup_file')
                        <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327]">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">Select backup file</label>
                            <div id="upload-drop-zone"
                                 class="relative border-2 border-dashed border-[#c3c4c7] rounded-sm p-5 text-center cursor-pointer hover:border-[#2271b1] transition-colors">
                                <span class="material-symbols-outlined text-[36px] text-[#c3c4c7] block mb-1" id="upload-icon">cloud_upload</span>
                                <p class="text-[13px] text-[#646970]" id="upload-label">
                                    Click to choose file or drag & drop here
                                </p>
                                <p class="text-[11px] text-[#9ca3af] mt-1">
                                    Accepted: .sql, .sql.gz, .zip &nbsp;|&nbsp;
                                    Max size: <strong class="text-[#1d2327]">{{ $maxUploadHuman }}</strong>
                                    <span class="text-[#9ca3af]">(server limit)</span>
                                </p>
                                <input type="file" name="backup_file" id="backup_file"
                                       accept=".sql,.gz,.zip"
                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                       onchange="handleFileSelect(this)">
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <button type="button" onclick="confirmUpload()" id="upload-btn"
                                    class="wp-btn-primary flex items-center gap-1.5 opacity-50 pointer-events-none" disabled>
                                <span class="material-symbols-outlined text-[18px]">upload</span>
                                Upload Backup
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Available Snapshots --}}
        <div class="bg-white border border-[#c3c4c7] shadow-sm">
            <div class="p-4 border-b border-[#c3c4c7] bg-[#f6f7f7]">
                <h2 class="text-[14px] font-semibold text-[#1d2327]">Available Snapshots</h2>
                <p class="text-[12px] text-[#646970]">Full database exports available for download. Keep your data safe.</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13px] border-collapse">
                    <thead>
                        <tr class="border-b border-[#c3c4c7]">
                            <th class="p-3 font-semibold text-[#2c3338]">Filename</th>
                            <th class="p-3 font-semibold text-[#2c3338]">Size</th>
                            <th class="p-3 font-semibold text-[#2c3338]">Created Date</th>
                            <th class="p-3 font-semibold text-[#2c3338] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backups as $backup)
                            <tr class="border-b border-[#f0f0f1] hover:bg-[#f9f9f9]">
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[#646970]">description</span>
                                        <span class="font-medium text-[#2271b1]">{{ $backup['name'] }}</span>
                                    </div>
                                </td>
                                <td class="p-3 text-[#646970]">{{ $backup['size'] }}</td>
                                <td class="p-3 text-[#646970]">{{ $backup['date'] }}</td>
                                <td class="p-3 text-right">
                                    <div class="flex justify-end gap-3">
                                        <form id="restore-backup-{{ $loop->index }}" action="{{ route('admin.backup.restore', $backup['name']) }}" method="POST">
                                            @csrf
                                            <button type="button" onclick="confirmRestore('{{ $backup['name'] }}', 'restore-backup-{{ $loop->index }}')" class="text-[#b16d22] hover:underline flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[18px]">history</span>
                                                Restore
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.backup.download', $backup['name']) }}" class="text-[#2271b1] hover:underline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[18px]">download</span>
                                            Download
                                        </a>
                                        <form id="delete-backup-{{ $loop->index }}" action="{{ route('admin.backup.destroy', $backup['name']) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="confirmDelete('{{ $backup['name'] }}', 'delete-backup-{{ $loop->index }}')" class="text-[#b32d2e] hover:underline flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-[#646970] italic">
                                    No snapshots found. Click "Create New Snapshot" to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 bg-[#f0f6fa] border border-[#d5ecf5] p-4 rounded-sm">
            <h3 class="text-[14px] font-semibold text-[#0c3d5d] mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">info</span>
                Pro Tip: Regular Backups
            </h3>
            <p class="text-[13px] text-[#1d2327] leading-relaxed">
                Database snapshots capture your posts, pages, users, and settings. It is recommended to download your snapshots and store them in a secure offline location. 
                <br>
                <strong>Note:</strong> This tool currently only backs up the database structure and data. Uploaded images and files should be backed up separately from your <code>storage/app/public/uploads</code> directory.
            </p>
        </div>
    </div>
    <script>
        // ── Upload: file select / drag-drop ──
        function handleFileSelect(input) {
            const zone  = document.getElementById('upload-drop-zone');
            const label = document.getElementById('upload-label');
            const icon  = document.getElementById('upload-icon');
            const btn   = document.getElementById('upload-btn');
            const maxBytes = {{ $maxUploadBytes }};

            if (!input.files || !input.files[0]) return;
            const file = input.files[0];

            if (file.size > maxBytes) {
                label.textContent = 'File too large! Max allowed: {{ $maxUploadHuman }}';
                label.style.color = '#d63638';
                icon.textContent  = 'error';
                icon.style.color  = '#d63638';
                btn.disabled      = true;
                btn.classList.add('opacity-50', 'pointer-events-none');
                input.value = '';
                return;
            }

            const sizeMb = (file.size / 1024 / 1024).toFixed(2);
            label.textContent  = file.name + ' (' + sizeMb + ' MB)';
            label.style.color  = '#2271b1';
            icon.textContent   = 'check_circle';
            icon.style.color   = '#46b450';
            zone.style.borderColor = '#46b450';
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'pointer-events-none');
        }

        // Drag-and-drop
        (function () {
            const zone = document.getElementById('upload-drop-zone');
            if (!zone) return;
            zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.borderColor = '#2271b1'; });
            zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.style.borderColor = '';
                const input = document.getElementById('backup_file');
                const dt    = e.dataTransfer;
                if (dt && dt.files.length) {
                    input.files = dt.files;
                    handleFileSelect(input);
                }
            });
        })();

        window.confirmUpload = async function () {
            const confirmed = await window.lazyConfirm({
                title:       'Upload Backup File',
                message:     'This will upload the backup file to this server. After uploading you can restore it. Are you sure?',
                confirmText: 'Yes, Upload',
                isDanger:    false
            });
            if (confirmed) document.getElementById('upload-backup-form').submit();
        };

        // ── Restore / Delete confirmations ──
        window.confirmRestore = async function(name, formId) {
            const confirmed = await window.lazyConfirm({
                title: 'Restore Snapshot',
                message: `WARNING: This will overwrite your current database with the contents of "${name}". Any changes made since this snapshot was created will be lost. This action cannot be undone.`,
                confirmText: 'Yes, Restore Database',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(formId).submit();
            }
        };

        window.confirmDelete = async function(name, formId) {
            const confirmed = await window.lazyConfirm({
                title: 'Delete Snapshot',
                message: `Are you sure you want to delete the snapshot "${name}"? This action cannot be undone.`,
                confirmText: 'Delete Snapshot',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(formId).submit();
            }
        };
    </script>
</x-falcon-cms::layouts.admin>
