<x-falcon-cms::layouts.admin active-menu="plugins">
    <x-slot name="title">Add Plugins - FalconCMS</x-slot>

    <div class="mb-4 flex items-center">
        <h1 class="text-[23px] font-normal text-[#1d2327] inline-block mr-3">Add Plugins</h1>
        <a href="{{ route('admin.plugins.index') }}" class="wp-btn-outline">Browse Installed</a>
    </div>

    <div class="max-w-[720px] mx-auto">
        @if(session('success'))
            <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ session('error') }}</div>
        @endif
        @if(isset($errors) && $errors->any())
            <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ $errors->first() }}</div>
        @endif

        {{-- Drag & drop upload --}}
        <form id="plugin-upload-form" action="{{ route('admin.plugins.upload') }}" method="POST" enctype="multipart/form-data"
              class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] rounded p-6 mb-6">
            @csrf
            <h2 class="text-[15px] font-semibold text-[#1d2327] mb-1">Upload a plugin</h2>
            <p class="text-[12.5px] text-[#646970] mb-4">If you have a plugin in a <code>.zip</code> file, drop it here or browse to install it.</p>

            <label id="dropzone" for="plugin-file"
                   class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-[#c3c4c7] rounded-lg px-6 py-12 text-center cursor-pointer transition-colors bg-[#fbfbfc] hover:border-[#2271b1] hover:bg-[#f6fafe]">
                <span id="dz-icon" class="material-symbols-outlined text-[46px] text-[#8c8f94]">upload_file</span>
                <div>
                    <div id="dz-title" class="text-[14px] font-semibold text-[#1d2327]">Drag &amp; drop your plugin .zip here</div>
                    <div id="dz-sub" class="text-[12.5px] text-[#646970] mt-0.5">or <span class="text-[#2271b1] underline">click to browse</span> · max 50&nbsp;MB</div>
                </div>
            </label>
            <input type="file" name="plugin_zip" id="plugin-file" accept=".zip" class="hidden">

            <div class="mt-4 flex items-center justify-between">
                <span id="dz-filename" class="text-[13px] text-[#50575e]"></span>
                <button type="submit" id="dz-submit" class="wp-btn-primary px-4 h-9 font-semibold opacity-50 pointer-events-none">Install Now</button>
            </div>
        </form>

        {{-- Install from URL --}}
        <form action="{{ route('admin.plugins.install-url') }}" method="POST"
              class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] rounded p-6">
            @csrf
            <h2 class="text-[15px] font-semibold text-[#1d2327] mb-1">Install from URL</h2>
            <p class="text-[12.5px] text-[#646970] mb-4">Paste a direct link to a plugin <code>.zip</code> (e.g. a GitHub release asset).</p>
            <div class="flex items-center gap-2">
                <input type="url" name="plugin_url" placeholder="https://example.com/plugin.zip" required
                       class="wp-input flex-1 h-9 shadow-sm">
                <button type="submit" class="wp-btn-primary px-4 h-9 font-semibold whitespace-nowrap">Install</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        (function () {
            var dropzone = document.getElementById('dropzone');
            var input    = document.getElementById('plugin-file');
            var filename = document.getElementById('dz-filename');
            var submit   = document.getElementById('dz-submit');
            var dzTitle  = document.getElementById('dz-title');
            var dzIcon   = document.getElementById('dz-icon');
            if (!dropzone || !input) return;

            function enableSubmit(on) {
                submit.classList.toggle('opacity-50', !on);
                submit.classList.toggle('pointer-events-none', !on);
            }

            function isZip(file) {
                return file && (/\.zip$/i.test(file.name) || file.type === 'application/zip' || file.type === 'application/x-zip-compressed');
            }

            function showFile(file) {
                if (!isZip(file)) {
                    filename.textContent = '“' + file.name + '” is not a .zip file.';
                    filename.classList.add('text-[#d63638]');
                    enableSubmit(false);
                    return;
                }
                filename.classList.remove('text-[#d63638]');
                var mb = (file.size / (1024 * 1024)).toFixed(2);
                filename.textContent = 'Selected: ' + file.name + ' (' + mb + ' MB)';
                dzTitle.textContent = 'Ready to install';
                dzIcon.textContent = 'inventory_2';
                enableSubmit(true);
            }

            // Click-to-browse changes
            input.addEventListener('change', function () {
                if (input.files && input.files.length) showFile(input.files[0]);
            });

            // Drag styling
            ['dragenter', 'dragover'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault(); e.stopPropagation();
                    dropzone.classList.add('border-[#2271b1]', 'bg-[#f6fafe]');
                });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault(); e.stopPropagation();
                    dropzone.classList.remove('border-[#2271b1]', 'bg-[#f6fafe]');
                });
            });

            // Drop → assign to the file input so it submits normally
            dropzone.addEventListener('drop', function (e) {
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) {
                    input.files = files;               // modern browsers allow this
                    showFile(files[0]);
                }
            });
        })();
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
