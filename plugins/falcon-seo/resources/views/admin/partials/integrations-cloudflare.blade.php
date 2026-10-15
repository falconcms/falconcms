{{-- Settings → Integrations → Cloudflare cache purge (falcon_integrations_settings_form_bottom). --}}
@php
    $settings = [
        'cloudflare_api_token' => get_cms_option('cloudflare_api_token', ''),
        'cloudflare_purge_enabled' => get_cms_option('cloudflare_purge_enabled', '0'),
        'cloudflare_zone_id' => get_cms_option('cloudflare_zone_id', ''),
    ];
    $cfTokenSaved = !empty($settings['cloudflare_api_token']);
    $cfTokenMasked = $cfTokenSaved ? \FalconSeo\Support\Cloudflare::maskedToken() : null;
    $cfLast = \FalconSeo\Support\Cloudflare::last();
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
            @if($cfTokenMasked)
                {{-- The saved token, its second half masked. Saved unchanged it is kept; clicking
                     in empties the field for a new one (put back if left empty). --}}
                <input type="text" id="cloudflare_api_token" name="cloudflare_api_token" value="{{ $cfTokenMasked }}" data-masked="{{ $cfTokenMasked }}"
                       autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore spellcheck="false"
                       class="wp-input w-[400px] h-8 shadow-sm font-mono text-[#50575e]"
                       title="Saved. Click to paste a new token; leave it as it is to keep this one."
                       onfocus="if (this.value === this.dataset.masked) { this.value = ''; this.type = 'password'; this.autocomplete = 'new-password'; this.placeholder = 'Paste a new token, or leave empty to keep the saved one'; }"
                       onblur="if (this.value === '') { this.type = 'text'; this.value = this.dataset.masked; }">
            @else
                <input type="password" id="cloudflare_api_token" name="cloudflare_api_token" autocomplete="new-password"
                       data-lpignore="true" data-1p-ignore data-bwignore spellcheck="false"
                       class="wp-input w-[400px] h-8 shadow-sm font-mono"
                       placeholder="{{ $cfTokenSaved ? 'Saved, but it cannot be read (APP_KEY changed?) — paste it again' : 'Paste the token' }}">
            @endif
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
