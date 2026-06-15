<x-falcon-cms::layouts.admin title="Updates">

    <div class="p-4 sm:p-6 bg-[#f0f0f1] min-h-screen">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Updates</h1>
            <nav class="text-[13px] text-[#646970]">Home / Dashboard / Updates</nav>
        </div>

        @if(session('success'))
        <div class="mb-4 p-3 bg-[#edfaee] border border-[#46b450] text-[#1a6b20] text-[13px] rounded">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="mb-4 p-3 bg-[#fef0f0] border border-[#d63638] text-[#d63638] text-[13px] rounded">
            {{ session('error') }}
        </div>
        @endif

        {{-- Step Output after running update --}}
        @if(session('update_steps'))
        @php $hadError = session('update_had_error', false); @endphp
        <div class="max-w-2xl mb-5">
            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)]">
                <div class="px-4 py-3 border-b border-[#f0f0f1] flex items-center gap-2">
                    @if($hadError)
                        <span class="material-symbols-outlined text-[#d63638] text-[18px]">error</span>
                        <span class="text-[14px] font-semibold text-[#d63638]">Update completed with errors</span>
                    @else
                        <span class="material-symbols-outlined text-[#46b450] text-[18px]">check_circle</span>
                        <span class="text-[14px] font-semibold text-[#1a6b20]">Update completed successfully</span>
                    @endif
                </div>
                <div class="p-4 space-y-3">
                    @foreach(session('update_steps') as $step)
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            @if($step['ok'])
                                <span class="material-symbols-outlined text-[#46b450] text-[15px]">check_circle</span>
                            @else
                                <span class="material-symbols-outlined text-[#d63638] text-[15px]">cancel</span>
                            @endif
                            <span class="text-[12px] font-semibold text-[#1d2327] font-mono">{{ $step['label'] }}</span>
                        </div>
                        @if(!empty($step['output']))
                        <pre class="bg-[#1d2327] text-[#e2e4e7] text-[11px] font-mono p-3 rounded overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ $step['output'] }}</pre>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="max-w-2xl">

            {{-- Version Status Card --}}
            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-5">
                <div class="px-4 py-3 border-b border-[#f0f0f1] flex items-center justify-between">
                    <span class="text-[14px] font-semibold text-[#1d2327]">FalconCMS</span>
                    @if($update['has_update'])
                        <span class="px-2 py-0.5 bg-[#46b450] text-white text-[11px] font-bold rounded uppercase tracking-wide">Update Available</span>
                    @elseif($update['latest'])
                        <span class="px-2 py-0.5 bg-[#c3c4c7] text-[#1d2327] text-[11px] font-bold rounded uppercase tracking-wide">Up to Date</span>
                    @else
                        <span class="px-2 py-0.5 bg-[#dba617] text-white text-[11px] font-bold rounded uppercase tracking-wide">Unable to Check</span>
                    @endif
                </div>

                <div class="p-5">
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="bg-[#f6f7f7] rounded p-3 text-center">
                            <div class="text-[11px] text-[#646970] font-semibold uppercase tracking-wide mb-1">Installed Version</div>
                            <div class="text-[20px] font-bold text-[#1d2327]">v{{ $update['current'] }}</div>
                        </div>
                        <div class="rounded p-3 text-center {{ $update['has_update'] ? 'bg-[#edfaee] border border-[#46b450]' : 'bg-[#f6f7f7]' }}">
                            <div class="text-[11px] text-[#646970] font-semibold uppercase tracking-wide mb-1">Latest Version</div>
                            <div class="text-[20px] font-bold {{ $update['has_update'] ? 'text-[#46b450]' : 'text-[#1d2327]' }}">
                                {{ $update['latest'] ? 'v' . $update['latest'] : '—' }}
                            </div>
                        </div>
                    </div>

                    @if($update['has_update'])
                    <div class="bg-[#eef4fb] border border-[#2271b1]/30 rounded p-3 mb-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-[#2271b1] text-[20px] mt-0.5 flex-shrink-0">info</span>
                        <div class="text-[13px] text-[#1d2327]">
                            A new version <strong>v{{ $update['latest'] }}</strong> is available.
                            Run the update to get the latest features and fixes.
                            @if($update['url'])
                                <a href="{{ $update['url'] }}" target="_blank" class="text-[#2271b1] underline ml-1">View details →</a>
                            @endif
                        </div>
                    </div>

                    {{-- Backup Warning --}}
                    <div class="bg-[#fcf0d5] border border-[#dba617] rounded p-4 mb-4">
                        <div class="flex items-start gap-3 mb-3">
                            <span class="material-symbols-outlined text-[#dba617] text-[22px] mt-0.5 flex-shrink-0">backup_table</span>
                            <div>
                                <p class="text-[13px] font-semibold text-[#1d2327] mb-1">Backup recommended before updating</p>
                                <p class="text-[13px] text-[#646970]">
                                    Please backup your database before running the update to avoid any data loss.
                                    Go to
                                    <a href="{{ route('admin.backup.index') }}" class="text-[#2271b1] underline font-medium">Tools → Backup &amp; Restore</a>
                                    to create a backup now.
                                </p>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" id="backup-confirm-checkbox" class="w-4 h-4 accent-[#2271b1] cursor-pointer">
                            <span class="text-[13px] text-[#1d2327] font-medium">I have created a backup and I'm ready to update</span>
                        </label>
                    </div>

                    <form method="POST" action="{{ route('admin.update.run') }}" id="update-form">
                        @csrf
                        <button type="submit" id="run-update-btn" disabled
                            class="inline-flex items-center gap-2 bg-[#2271b1] text-white font-semibold text-[13px] px-5 py-2 rounded transition-colors opacity-50 cursor-not-allowed"
                            onclick="return confirm('This will run composer update + falcon:update — migrations, asset publishing and cache clearing will happen. Continue?')">
                            <span class="material-symbols-outlined text-[18px]">system_update</span>
                            Run Update Now
                        </button>
                    </form>

                    @elseif($update['latest'])
                    {{-- Already up to date --}}
                    <div class="bg-[#edfaee] border border-[#46b450] rounded p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-[#46b450] text-[24px] flex-shrink-0">verified</span>
                        <div>
                            <p class="text-[14px] font-semibold text-[#1a6b20] mb-0.5">You're up to date!</p>
                            <p class="text-[13px] text-[#1a6b20]">
                                FalconCMS <strong>v{{ $update['current'] }}</strong> is the latest version. No action required.
                            </p>
                        </div>
                    </div>

                    @else
                    <p class="text-[13px] text-[#646970]">
                        Could not connect to the update server. Please check your internet connection and try again.
                    </p>
                    <a href="{{ route('admin.update') }}" class="mt-3 inline-flex items-center gap-1.5 text-[13px] text-[#2271b1] hover:underline">
                        <span class="material-symbols-outlined text-[16px]">refresh</span>
                        Check Again
                    </a>
                    @endif
                </div>

                <div class="px-4 py-2.5 bg-[#f6f7f7] border-t border-[#c3c4c7] text-[11px] text-[#646970]">
                    Last checked: {{ $update['checked_at'] ?? 'Just now' }}
                </div>
            </div>

            {{-- Package Info --}}
            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)]">
                <div class="px-4 py-3 border-b border-[#f0f0f1]">
                    <span class="text-[14px] font-semibold text-[#1d2327]">Package Info</span>
                </div>
                <div class="p-4 space-y-2.5 text-[13px]">
                    <div class="flex justify-between">
                        <span class="text-[#646970]">Package</span>
                        <span class="font-medium text-[#1d2327]">falconcms/falconcms</span>
                    </div>
                    <div class="flex justify-between border-t border-[#f0f0f1] pt-2.5">
                        <span class="text-[#646970]">Installed</span>
                        <span class="font-medium text-[#1d2327]">v{{ $update['current'] }}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#f0f0f1] pt-2.5">
                        <span class="text-[#646970]">PHP</span>
                        <span class="font-medium text-[#1d2327]">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#f0f0f1] pt-2.5">
                        <span class="text-[#646970]">Laravel</span>
                        <span class="font-medium text-[#1d2327]">{{ app()->version() }}</span>
                    </div>
                    @if($update['url'])
                    <div class="flex justify-between border-t border-[#f0f0f1] pt-2.5">
                        <span class="text-[#646970]">Source</span>
                        <a href="{{ $update['url'] }}" target="_blank" class="text-[#2271b1] hover:underline">Packagist →</a>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    @if($update['has_update'])
    @push('scripts')
    <script>
        (function () {
            var cb  = document.getElementById('backup-confirm-checkbox');
            var btn = document.getElementById('run-update-btn');
            if (!cb || !btn) return;
            cb.addEventListener('change', function () {
                if (this.checked) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btn.classList.add('hover:bg-[#135e96]', 'cursor-pointer');
                } else {
                    btn.disabled = true;
                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                    btn.classList.remove('hover:bg-[#135e96]', 'cursor-pointer');
                }
            });
        })();
    </script>
    @endpush
    @endif

</x-falcon-cms::layouts.admin>
