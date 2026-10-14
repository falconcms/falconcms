{{-- SEO → Settings: the Pro tools' settings (AI writing, keyword data, weekly email). --}}
@php
    $falconSeoPro = \FalconSeo\Support\Pro::active();
    $model = \FalconSeo\Support\Pro::claudeModel();
    $sdk = class_exists(\Anthropic\Client::class);
@endphp
<div class="wp-metabox" id="seo-pro">
    <div class="wp-metabox-header flex items-center gap-2">
        <span>AI writing, keyword data &amp; weekly email</span>
        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-[#2271b1] text-white">Pro</span>
    </div>
    <div class="wp-metabox-content p-4 space-y-6">
        @unless($falconSeoPro)
            <div class="p-3 rounded bg-[#f0f6fc] border border-[#72aee6] text-[13px] text-[#1d2327]">
                Part of FalconCMS Pro: Claude writes SEO titles, descriptions and alt text for you, DataForSEO brings Google's own search numbers
                and difficulty for any country, Site Analysis fixes problems in one click, and a weekly email tells you what changed.
                <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="text-[#2271b1] font-semibold">Upgrade to Pro</a>
            </div>
        @endunless

        <fieldset @disabled(!$falconSeoPro) class="space-y-6 {{ $falconSeoPro ? '' : 'opacity-60' }}">
            {{-- AI writing --}}
            <div>
                <h3 class="text-[14px] font-semibold text-[#1d2327] mb-1">AI writing (Claude)</h3>
                <p class="text-[12px] text-[#646970] mb-3">"Suggest with AI" beside the SEO title and description in the editor, and AI fixes in Site Analysis.
                    Uses your own Anthropic API key: get one at <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" class="text-[#2271b1]">console.anthropic.com</a>. Stored encrypted; only the page being worked on is sent.</p>
                @unless($sdk)
                    <p class="text-[12px] text-[#d63638] mb-2">The Anthropic PHP library is missing on this server: run <code>composer update</code> once.</p>
                @endunless
                @include('falcon-seo::admin.partials.api-key-field', ['service' => 'claude', 'label' => 'Claude API key', 'placeholder' => 'sk-ant-…'])
                <div class="mt-3">
                    <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Model</label>
                    <select name="{{ \FalconSeo\Support\Pro::CLAUDE_MODEL }}" class="wp-input h-8 py-0 text-[13px]">
                        @foreach(\FalconSeo\Support\Pro::MODELS as $id => $label)
                            <option value="{{ $id }}" @selected($id === $model)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Keyword data --}}
            <div class="pt-4 border-t border-[#f0f0f1]">
                <h3 class="text-[14px] font-semibold text-[#1d2327] mb-1">Keyword data (DataForSEO)</h3>
                <p class="text-[12px] text-[#646970] mb-3">Google's monthly searches, cost per click, ad competition and ranking difficulty for the focus keyword, in each country you choose
                    (editor → SEO → Analysis). Uses your own <a href="https://dataforseo.com" target="_blank" rel="noopener" class="text-[#2271b1]">DataForSEO</a> account, which bills per request;
                    each keyword and country is asked once a month at most.</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-[13px] font-bold text-[#2c3338] mb-1">API login (your DataForSEO email)</label>
                        <input type="email" name="{{ \FalconSeo\Support\Pro::DATAFORSEO_LOGIN }}" value="{{ $settings[\FalconSeo\Support\Pro::DATAFORSEO_LOGIN] ?? '' }}"
                               autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore class="wp-input w-[360px] max-w-full" placeholder="you@example.com">
                    </div>
                    @include('falcon-seo::admin.partials.api-key-field', ['service' => 'dataforseo', 'label' => 'API password (app.dataforseo.com → API Access)', 'placeholder' => 'The API password, not your account password'])
                </div>
            </div>

            {{-- Weekly email --}}
            <div class="pt-4 border-t border-[#f0f0f1]">
                <h3 class="text-[14px] font-semibold text-[#1d2327] mb-1">Weekly SEO email</h3>
                <label class="flex items-center gap-2 text-[13px] text-[#1d2327]">
                    <input type="checkbox" name="{{ \FalconSeo\Support\Pro::REPORT_ENABLED }}" value="1" @checked(($settings[\FalconSeo\Support\Pro::REPORT_ENABLED] ?? '0') === '1') class="rounded-sm border-[#8c8f94]">
                    Every Monday morning: the average score, pages whose score dropped, every broken link, what to fix first
                </label>
                <div class="mt-2">
                    <input type="text" name="{{ \FalconSeo\Support\Pro::REPORT_TO }}" value="{{ $settings[\FalconSeo\Support\Pro::REPORT_TO] ?? '' }}" class="wp-input w-[420px] max-w-full"
                           placeholder="Who gets it (empty: {{ get_cms_option('admin_email') ?: 'the admin email' }}), separated by commas">
                    @if($falconSeoPro)
                        <button type="submit" formaction="{{ route('admin.seo.report-test') }}" formmethod="post" class="ml-2 wp-btn-secondary h-8 py-0 px-3 text-[12px]" title="Sends to the saved addresses: save first if you changed them">Send a test now</button>
                    @endif
                </div>
            </div>
        </fieldset>
    </div>
</div>
