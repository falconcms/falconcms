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
        @if (session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">{{ session('error') }}</div>
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

            {{-- ── Cloudflare cache purge ── --}}
            @php
                $cfTokenSaved = !empty($settings['cloudflare_api_token']);
                $cfLast = \FalconCms\Core\Support\Cloudflare::last();
            @endphp
            <h2 id="cloudflare" class="text-[15px] font-semibold text-[#1d2327] mb-1 mt-4 pt-6 border-t border-gray-100">Cloudflare cache</h2>
            <p class="text-[13px] text-[#646970] mb-4">
                If your site runs through Cloudflare, it can empty Cloudflare's cache each time you change something (a post, a page, the Customizer, a menu),
                so visitors never get an old copy. This matters most if Cloudflare caches your pages (a Cache Rule); images, CSS and JavaScript it caches anyway.
            </p>
            <table class="w-full border-separate border-spacing-y-4">
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-1"><span class="text-[14px] font-semibold text-[#1d2327]">Purge on change</span></th>
                    <td>
                        <label class="inline-flex items-center gap-2 text-[13px]">
                            <input type="checkbox" name="cloudflare_purge_enabled" value="1" @checked(($settings['cloudflare_purge_enabled'] ?? '0') === '1') class="rounded-sm border-[#8c8f94]">
                            Empty Cloudflare's cache whenever the site's content changes
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="cloudflare_zone_id" class="text-[14px] font-semibold text-[#1d2327]">Zone ID</label></th>
                    <td>
                        <input type="text" id="cloudflare_zone_id" name="cloudflare_zone_id" value="{{ $settings['cloudflare_zone_id'] ?? '' }}"
                               class="wp-input w-[400px] h-8 shadow-sm font-mono" placeholder="32 characters">
                        <p class="text-[12px] text-[#646970] mt-1">In Cloudflare, open your site: the Overview page shows the Zone ID on the right.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="cloudflare_api_token" class="text-[14px] font-semibold text-[#1d2327]">API token</label></th>
                    <td>
                        <input type="password" id="cloudflare_api_token" name="cloudflare_api_token" autocomplete="off"
                               class="wp-input w-[400px] h-8 shadow-sm font-mono" placeholder="{{ $cfTokenSaved ? 'Saved. Leave empty to keep it.' : 'Paste the token' }}">
                        @if($cfTokenSaved)
                            <label class="inline-flex items-center gap-1 text-[12px] text-[#646970] ml-2">
                                <input type="checkbox" name="cloudflare_api_token_remove" value="1" class="rounded-sm border-[#8c8f94]"> Remove
                            </label>
                        @endif
                        <p class="text-[12px] text-[#646970] mt-1">
                            <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener" class="text-[#2271b1] hover:underline">My Profile → API Tokens</a>
                            → Create Token → Custom token, permission <strong>Zone → Cache Purge → Purge</strong>, for this site's zone only. Stored encrypted.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-1"><span class="text-[14px] font-semibold text-[#1d2327]">Last purge</span></th>
                    <td class="text-[13px]">
                        @if($cfLast['at'])
                            @if($cfLast['error'])
                                <span class="text-[#b32d2e]">{{ \Illuminate\Support\Carbon::parse($cfLast['at'])->diffForHumans() }}: {{ $cfLast['error'] }}</span>
                            @else
                                <span class="text-green-700">{{ \Illuminate\Support\Carbon::parse($cfLast['at'])->diffForHumans() }}, done.</span>
                            @endif
                        @else
                            <span class="text-[#646970]">Not yet.</span>
                        @endif
                        @if($cfTokenSaved && !empty($settings['cloudflare_zone_id']))
                            <button type="submit" form="cloudflare-purge-form" class="ml-3 wp-btn-secondary h-7 py-0 px-3 text-[12px]">Purge everything now</button>
                        @endif
                    </td>
                </tr>
            </table>

            {!! do_falcon_action('falcon_integrations_settings_form_bottom') !!}

            <div class="pt-6 border-t border-gray-100 mt-2">
                <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
            </div>
        </form>
        {{-- Its own form (the button above points at it with form=…): a purge must not save the settings. --}}
        <form id="cloudflare-purge-form" method="POST" action="{{ route('admin.settings.integrations.cloudflare-purge') }}">@csrf</form>
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
