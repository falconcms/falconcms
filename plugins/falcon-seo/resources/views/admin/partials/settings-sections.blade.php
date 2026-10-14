{{-- SEO → Settings: the plugin's sections (falcon_seo_settings_sections, given $settings). --}}
<div class="space-y-8">
{{-- Who runs the site: Organization / LocalBusiness structured data --}}
@php $orgType = $settings['seo_org_type'] ?? 'Organization'; @endphp
<div class="wp-metabox" x-data="{ type: @js($orgType) }">
    <div class="wp-metabox-header"><span>Your business or organization</span></div>
    <div class="wp-metabox-content p-4 space-y-4">
        <p class="text-[12px] text-[#646970]">Tells Google who stands behind the site: it can show your logo, profiles and contact details in its knowledge panel, and it counts towards trust. Every page carries it; fill in what applies.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Type</label>
                <select name="seo_org_type" x-model="type" class="wp-input w-full h-8 py-0">
                    @foreach(\FalconSeo\Support\StructuredData::BUSINESS_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($orgType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Name</label>
                <input type="text" name="seo_org_name" value="{{ $settings['seo_org_name'] ?? '' }}" class="wp-input w-full h-8" placeholder="{{ get_cms_option('site_title', '') }}">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Logo URL</label>
                <input type="text" name="seo_org_logo" value="{{ $settings['seo_org_logo'] ?? '' }}" class="wp-input w-full h-8" placeholder="Empty: the theme logo is used">
                <p class="text-[11px] text-[#646970] mt-1">A square or wide logo, at least 112×112px.</p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Social profiles</label>
                <textarea name="seo_org_same_as" rows="3" class="wp-input w-full text-[13px]" placeholder="https://facebook.com/yourpage&#10;https://www.linkedin.com/company/you&#10;https://x.com/you">{{ $settings['seo_org_same_as'] ?? '' }}</textarea>
                <p class="text-[11px] text-[#646970] mt-1">One address per line: Facebook, LinkedIn, X, YouTube, Instagram, Wikipedia…</p>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Phone</label>
                <input type="text" name="seo_org_phone" value="{{ $settings['seo_org_phone'] ?? '' }}" class="wp-input w-full h-8" placeholder="+880 1700 000000">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Email</label>
                <input type="email" name="seo_org_email" value="{{ $settings['seo_org_email'] ?? '' }}" class="wp-input w-full h-8">
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1" x-show="type !== 'Organization'" x-cloak>
            <div class="sm:col-span-2 text-[12px] font-semibold text-[#50575e] uppercase">Address (shown for a local business)</div>
            <div class="sm:col-span-2">
                <input type="text" name="seo_org_street" value="{{ $settings['seo_org_street'] ?? '' }}" class="wp-input w-full h-8" placeholder="Street address">
            </div>
            <input type="text" name="seo_org_city" value="{{ $settings['seo_org_city'] ?? '' }}" class="wp-input w-full h-8" placeholder="City">
            <input type="text" name="seo_org_region" value="{{ $settings['seo_org_region'] ?? '' }}" class="wp-input w-full h-8" placeholder="Region / division">
            <input type="text" name="seo_org_postal_code" value="{{ $settings['seo_org_postal_code'] ?? '' }}" class="wp-input w-full h-8" placeholder="Postal code">
            <input type="text" name="seo_org_country" value="{{ $settings['seo_org_country'] ?? '' }}" class="wp-input w-full h-8" placeholder="Country code, e.g. BD">
            <input type="text" name="seo_org_price_range" value="{{ $settings['seo_org_price_range'] ?? '' }}" class="wp-input w-full h-8" placeholder="Price range, e.g. $$">
        </div>
        <p class="text-[12px] text-[#646970]">Authors get the same treatment from their profile: name, photo, bio and social links (Users → edit) become a Person linked from every post they write, with a profile page at their author archive.</p>
    </div>
</div>

{{-- AI crawlers: two plain choices, written into robots.txt --}}
<div class="wp-metabox">
    <div class="wp-metabox-header"><span>AI crawlers</span></div>
    <div class="wp-metabox-content p-4 space-y-3">
        <p class="text-[12px] text-[#646970]">Neither choice affects Google or Bing search. The rules are added to robots.txt for you; well-behaved crawlers follow them, though nothing can force a crawler to.</p>
        <label class="flex items-start gap-2">
            <input type="checkbox" name="ai_block_training" value="1" @checked(($settings['ai_block_training'] ?? '0') === '1') class="rounded-sm border-[#8c8f94] mt-1">
            <span>
                <span class="text-[14px] font-semibold text-[#1d2327]">Don't let AI companies train on this site's content</span>
                <span class="block text-[12px] text-[#646970]">Blocks {{ implode(', ', array_keys(\FalconSeo\Support\AiCrawlers::TRAINING)) }}.</span>
            </span>
        </label>
        <label class="flex items-start gap-2">
            <input type="checkbox" name="ai_block_search" value="1" @checked(($settings['ai_block_search'] ?? '0') === '1') class="rounded-sm border-[#8c8f94] mt-1">
            <span>
                <span class="text-[14px] font-semibold text-[#1d2327]">Keep this site out of AI search answers</span>
                <span class="block text-[12px] text-[#646970]">Blocks {{ implode(', ', array_keys(\FalconSeo\Support\AiCrawlers::SEARCH)) }}. Usually best left off: these answers link to the sites they cite and send visitors.</span>
            </span>
        </label>
    </div>
</div>

{{-- IndexNow: instant notice to Bing, Yandex and the rest --}}
@php
    $indexNowOn = ($settings['indexnow_enabled'] ?? '0') === '1';
    $indexNowLast = \FalconSeo\Support\IndexNow::last();
@endphp
<div class="wp-metabox">
    <div class="wp-metabox-header"><span>Instant indexing (IndexNow)</span></div>
    <div class="wp-metabox-content p-4 space-y-3">
        <label class="flex items-center gap-2 text-[14px] font-semibold text-[#1d2327]">
            <input type="checkbox" name="indexnow_enabled" value="1" @checked($indexNowOn) class="rounded-sm border-[#8c8f94]">
            Tell search engines the moment a page is published, updated or removed
        </label>
        <p class="text-[12px] text-[#646970]">
            Pings Bing, Yandex, Naver, Seznam and Yep through IndexNow, so changes are picked up in minutes instead of waiting for a crawl.
            Bing's index also feeds Copilot and ChatGPT search. Google does not use IndexNow; it finds changes through your sitemap.
        </p>
        @if($indexNowOn)
            <p class="text-[12px] text-[#50575e]">
                Key file: <a href="{{ \FalconSeo\Support\IndexNow::keyUrl() }}" target="_blank" rel="noopener" class="text-[#2271b1]">{{ \FalconSeo\Support\IndexNow::keyUrl() }}</a>
                @if($indexNowLast)
                    · last ping {{ \Carbon\Carbon::parse($indexNowLast['at'])->diffForHumans() }}: {{ $indexNowLast['urls'] }} URL(s), {{ \FalconSeo\Support\IndexNow::describe($indexNowLast['status']) }}
                @else
                    · nothing sent yet; the next published change goes out.
                @endif
            </p>
        @endif
    </div>
</div>

{{-- 404 log --}}
<div class="wp-metabox">
    <div class="wp-metabox-header"><span>Broken links (404 log)</span></div>
    <div class="wp-metabox-content p-4 space-y-2">
        <label class="flex items-center gap-2 text-[14px] font-semibold text-[#1d2327]">
            <input type="checkbox" name="seo_404_log" value="1" @checked(($settings['seo_404_log'] ?? '1') !== '0') class="rounded-sm border-[#8c8f94]">
            Record pages visitors could not find
        </label>
        <p class="text-[12px] text-[#646970]">Missing pages (not images or files) are listed under <a href="{{ route('admin.redirects.index') }}" class="text-[#2271b1]">SEO → Redirects</a>, with how often and from where, ready to redirect in one click. A page whose address you change is redirected automatically.</p>
    </div>
</div>

{{-- Large image previews: Google Discover and image-rich results --}}
<div class="wp-metabox">
    <div class="wp-metabox-header"><span>Google Discover &amp; large previews</span></div>
    <div class="wp-metabox-content p-4 space-y-2">
        <label class="flex items-center gap-2 text-[14px] font-semibold text-[#1d2327]">
            <input type="checkbox" name="seo_large_image_preview" value="1" @checked(($settings['seo_large_image_preview'] ?? '1') !== '0') class="rounded-sm border-[#8c8f94]">
            Allow Google to show large image previews
        </label>
        <p class="text-[12px] text-[#646970]">
            Adds <code>max-image-preview:large</code> to every indexable page. Google Discover shows a page with a big image only when it has this
            and a featured image at least 1200px wide; the editor warns when a featured image is smaller.
        </p>
    </div>
</div>

{{-- Keyword research: free sources for search demand in the editor's Keywords tab --}}
@php
    $plannerCount = \FalconSeo\Support\KeywordDemand::plannerCount();
@endphp
<div class="wp-metabox" id="keyword-research">
    <div class="wp-metabox-header"><span>Keyword research</span></div>
    <div class="wp-metabox-content p-4 space-y-4">
        <p class="text-[12px] text-[#646970]">How many people search for a keyword, shown in the editor (SEO → Keywords → Search demand). All three sources are free; use any of them.</p>

        <div>
            @include('falcon-seo::admin.partials.api-key-field', ['service' => 'bing', 'label' => 'Bing Webmaster Tools API key', 'placeholder' => '32 letters and digits'])
            <p class="text-[12px] text-[#646970] mt-1">Searches on Bing for a keyword and related keywords. In <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener" class="text-[#2271b1]">Bing Webmaster Tools</a> (add and verify this site; it can import it from Google Search Console), open Settings → API access → API key. Stored encrypted.</p>
        </div>

        <div class="flex flex-wrap gap-4">
            <div>
                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Country</label>
                <input type="text" name="keyword_country" value="{{ $settings['keyword_country'] ?? 'us' }}" maxlength="5" class="wp-input w-[90px]" placeholder="us">
            </div>
            <div>
                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Language</label>
                <input type="text" name="keyword_language" value="{{ $settings['keyword_language'] ?? 'en-US' }}" maxlength="10" class="wp-input w-[110px]" placeholder="en-US">
            </div>
            <p class="text-[12px] text-[#646970] self-end pb-1">Where your visitors search: "us" + "en-US", "gb" + "en-GB", "bd" + "bn-BD"…</p>
        </div>

        <label class="flex items-start gap-2 text-[13px] text-[#1d2327]">
            <input type="checkbox" name="keyword_autocomplete" value="1" @checked(($settings['keyword_autocomplete'] ?? '0') === '1') class="rounded-sm border-[#8c8f94] mt-1">
            <span>Show Google's search suggestions<br>
                <span class="text-[#646970] text-[12px]">What people type into Google around a keyword (no volumes). Not an official Google API: asked only when you press "Check" in the editor, and remembered for a week.</span></span>
        </label>

        <div class="pt-3 border-t border-[#f0f0f1] text-[12px] text-[#646970]">
            <strong class="text-[#1d2327]">Google Keyword Planner:</strong>
            {{ $plannerCount ? number_format($plannerCount).' keywords imported.' : 'nothing imported yet.' }}
            Import a file on the <a href="{{ route('admin.settings.seo-audit') }}#keyword-planner-import" class="text-[#2271b1]">SEO Audit</a> tab.
        </div>
    </div>
</div>

@include('falcon-seo::admin.partials.settings-pro')
</div>
