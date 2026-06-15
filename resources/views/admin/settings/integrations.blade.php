<x-falcon-cms::layouts.admin>
    <x-slot name="title">Integrations - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Settings</h1>

        @include('falcon-cms::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.integrations.update') }}" method="POST" class="max-w-[800px]">
            @csrf

            {{-- ── Section heading ── --}}
            <h2 class="text-[15px] font-semibold text-[#1d2327] mb-1">Cloudflare Turnstile</h2>
            <p class="text-[13px] text-[#646970] mb-6">
                A free CAPTCHA alternative that protects your forms from bots — without annoying puzzles.
                Get your keys from
                <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank"
                   class="text-[#2271b1] hover:text-[#135e96] hover:underline">Cloudflare Dashboard → Turnstile</a>
                (Add site → Managed widget → copy the keys).
            </p>

            <table class="w-full border-separate border-spacing-y-6">

                {{-- Site Key --}}
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="turnstile_site_key" class="text-[14px] font-semibold text-[#1d2327]">Site Key</label>
                    </th>
                    <td>
                        <input type="text" id="turnstile_site_key" name="turnstile_site_key"
                               value="{{ $settings['turnstile_site_key'] ?? '' }}"
                               placeholder="0x4AAAAAAA..."
                               class="wp-input w-[400px] h-8 shadow-sm font-mono">
                        <p class="text-[12px] text-[#646970] mt-1">Public key — safe to include in page HTML.</p>
                    </td>
                </tr>

                {{-- Secret Key --}}
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="turnstile_secret_key" class="text-[14px] font-semibold text-[#1d2327]">Secret Key</label>
                    </th>
                    <td>
                        <div class="flex items-center gap-2">
                            <input type="password" id="turnstile_secret_key" name="turnstile_secret_key"
                                   value="{{ $settings['turnstile_secret_key'] ?? '' }}"
                                   placeholder="0x4AAAAAAA..."
                                   class="wp-input w-[400px] h-8 shadow-sm font-mono">
                            <button type="button" onclick="toggleSecret()"
                                    class="text-[#646970] hover:text-[#1d2327] text-[13px] flex items-center gap-1">
                                <span id="secret-eye" class="material-symbols-outlined text-[16px]">visibility</span>
                            </button>
                        </div>
                        <p class="text-[12px] text-[#646970] mt-1">Private key — never expose this publicly.</p>
                    </td>
                </tr>

                {{-- Status --}}
                @php $hasKeys = !empty($settings['turnstile_site_key']) && !empty($settings['turnstile_secret_key']); @endphp
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <span class="text-[14px] font-semibold text-[#1d2327]">Status</span>
                    </th>
                    <td class="pt-2">
                        @if($hasKeys)
                            <span class="inline-flex items-center gap-1.5 text-[13px] font-medium text-green-700">
                                <span class="material-symbols-outlined text-[15px]">check_circle</span>
                                Keys configured — enable Turnstile on any form from the Form Builder.
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[13px] text-[#646970]">
                                <span class="material-symbols-outlined text-[15px]">radio_button_unchecked</span>
                                Keys not yet saved.
                            </span>
                        @endif
                    </td>
                </tr>

            </table>

            <div class="pt-6 border-t border-gray-100 mt-2">
                <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    function toggleSecret() {
        const inp = document.getElementById('turnstile_secret_key');
        const eye = document.getElementById('secret-eye');
        inp.type   = inp.type === 'password' ? 'text'       : 'password';
        eye.textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
    }
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
