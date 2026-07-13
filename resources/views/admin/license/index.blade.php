<x-falcon-cms::layouts.admin title="License" active-menu="falcon-builder">
    <x-slot name="title">License</x-slot>

    <div class="max-w-3xl">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-1">FalconCMS Pro License</h1>
        <p class="text-[13px] text-[#646970] mb-6">Enter your license key to unlock Pro features on this site.</p>

        @if(session('success'))
            <div class="bg-white border-l-4 border-[#46b450] shadow-sm p-3 mb-5 text-[13px]">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="bg-white border-l-4 border-[#d63638] shadow-sm p-3 mb-5 text-[13px] text-[#8a1f21]">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-white border-l-4 border-[#d63638] shadow-sm p-3 mb-5 text-[13px]">{{ $errors->first() }}</div>
        @endif

        {{-- ── Status card ── --}}
        @if($licensed)
            <div class="rounded-lg border border-[#a7e0a7] bg-[#f0faf0] p-5 mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-[#1a8a1a]" style="font-size:22px">verified</span>
                    <span class="text-[15px] font-bold text-[#0f5d0f]">Pro is active</span>
                    <span class="ml-1 px-2 py-0.5 rounded-full bg-[#1a8a1a] text-white text-[11px] font-bold uppercase tracking-wide">{{ $plan }} plan</span>
                </div>
                <p class="text-[12.5px] text-[#3a6a3a] mb-3">Key <code class="px-1 bg-white/60 rounded">{{ $maskedKey }}</code> — the following features are unlocked:</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($features as $f)
                        <span class="px-2 py-0.5 rounded bg-white border border-[#cfe8cf] text-[11.5px] text-[#2c5a2c]">{{ str_replace('_', ' ', $f) }}</span>
                    @endforeach
                </div>
            </div>
        @elseif($hasKey)
            <div class="rounded-lg border border-[#f0c47a] bg-[#fdf6e9] p-5 mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-[#c98a1a]" style="font-size:22px">error</span>
                    <span class="text-[15px] font-bold text-[#5b4a1f]">Key saved, but Pro is not active</span>
                </div>
                @if(! $proInstalled)
                    <p class="text-[12.5px] text-[#7a663a] mb-2">The <strong>falconcms/pro</strong> package is not installed on this site yet. In your project root, add the repository:</p>
                    <pre class="bg-[#1d2327] text-[#e6e6e6] text-[12.5px] rounded p-3 overflow-x-auto"><code>composer config repositories.falconcms-pro vcs https://github.com/falconcms/falconcms-pro.git</code></pre>
                    <p class="text-[12.5px] text-[#7a663a] my-2">Create an <code>auth.json</code> file (next to <code>composer.json</code>) with the <strong>access token</strong> from your purchase email, then add it to <code>.gitignore</code>:</p>
                    <pre class="bg-[#1d2327] text-[#e6e6e6] text-[12.5px] rounded p-3 overflow-x-auto"><code>{
    "github-oauth": {
        "github.com": "YOUR-ACCESS-TOKEN"
    }
}</code></pre>
                    <p class="text-[12.5px] text-[#7a663a] my-2">Then install:</p>
                    <pre class="bg-[#1d2327] text-[#e6e6e6] text-[12.5px] rounded p-3 overflow-x-auto"><code>composer require falconcms/pro</code></pre>
                    <p class="text-[12px] text-[#7a663a] mt-2">Full guide, deployment &amp; CI setup:
                        <a href="https://falconcms.github.io/falconcms/guide/pro" target="_blank" rel="noopener" class="text-[#2271b1] hover:text-[#135e96] font-semibold hover:underline">Installing FalconCMS Pro</a>.
                    </p>
                @else
                    <p class="text-[12.5px] text-[#7a663a]">The key <code class="px-1 bg-white/60 rounded">{{ $maskedKey }}</code> did not resolve to a valid plan. Double-check the key, or deactivate and try again.</p>
                @endif
            </div>
        @else
            <div class="rounded-lg border border-[#c3c4c7] bg-white p-5 mb-6">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#646970]" style="font-size:22px">lock</span>
                    <span class="text-[14px] font-semibold text-[#1d2327]">No license active — Pro features are locked</span>
                </div>
            </div>
        @endif

        {{-- ── Activate form ── --}}
        <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-lg p-5 mb-5">
            <form action="{{ route('admin.license.activate') }}" method="POST">
                @csrf
                <label for="license_key" class="block text-[13px] font-semibold text-[#1d2327] mb-1.5">License key</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" name="license_key" id="license_key" autocomplete="off" spellcheck="false"
                           placeholder="e.g. PRO-XXXXXXXX or AGENCY-XXXXXXXX"
                           class="flex-1 rounded border border-[#8c8f94] px-3 h-9 text-[13px] font-mono focus:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1] outline-none">
                    <button type="submit" class="shrink-0 inline-flex items-center justify-center gap-1.5 px-5 h-9 bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold rounded transition-colors">
                        <span class="material-symbols-outlined" style="font-size:16px">key</span> {{ $hasKey ? 'Update key' : 'Activate' }}
                    </button>
                </div>
                <p class="mt-2 text-[12px] text-[#646970]">
                    Don't have a key yet?
                    <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="text-[#2271b1] hover:text-[#135e96] font-semibold hover:underline">Buy a license</a>.
                </p>
            </form>

            @if($hasKey)
                <form action="{{ route('admin.license.deactivate') }}" method="POST" class="mt-3"
                      onsubmit="return confirm('Deactivate the license? Pro features will lock immediately.');">
                    @csrf
                    <button type="submit" class="text-[12.5px] text-[#b32d2e] hover:text-[#d63638] hover:underline">Deactivate this license</button>
                </form>
            @endif
        </div>

        {{-- ── How it works ── --}}
        <div class="text-[12px] text-[#646970] leading-relaxed">
            <p class="font-semibold text-[#50575e] mb-1">How licensing works</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Your key is stored privately on this site and validated against your plan.</li>
                <li>Pro features (e-commerce, multi-language, analytics, advanced builder, custom fields) unlock only while a valid license is active.</li>
                <li>Pro code is delivered via <code>composer require falconcms/pro</code> from the private repository — it is never bundled in the free download. See the <a href="https://falconcms.github.io/falconcms/guide/pro" target="_blank" rel="noopener" class="text-[#2271b1] hover:underline">install guide</a>.</li>
            </ul>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
