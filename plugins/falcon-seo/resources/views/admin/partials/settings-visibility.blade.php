{{-- SEO → Settings: search engine visibility (falcon_seo_settings_form_top). --}}
@php $settings = ['seo_hide_site' => get_cms_option('seo_hide_site', '0')]; @endphp
<div class="mb-8">
{{-- Search engine visibility: hide a demo, staging or unfinished site --}}
@php $hidden = ($settings['seo_hide_site'] ?? '0') === '1'; @endphp
<div class="wp-metabox {{ $hidden ? 'border-[#d63638]' : '' }}">
    <div class="wp-metabox-header"><span>Search engine visibility</span></div>
    <div class="wp-metabox-content p-4 space-y-2">
        <label class="flex items-start gap-2 text-[13px] text-[#1d2327]">
            <input type="checkbox" name="seo_hide_site" value="1" @checked($hidden) class="rounded-sm border-[#8c8f94] mt-1">
            <span><strong>Hide this site from search engines</strong><br>
                <span class="text-[#646970]">For a demo, a staging copy or a site still being built. Every page says <code>noindex</code>, the sitemap is withdrawn and IndexNow stops. Pages already in Google drop out as they are recrawled, usually within a few weeks.</span></span>
        </label>
        @if($hidden)
            <p class="text-[12px] text-[#d63638]">This site is hidden from search engines. Untick this before launch, or nobody will find it on Google.</p>
        @endif
        <p class="text-[12px] text-[#646970]">robots.txt keeps allowing crawling on purpose: a page Google may not fetch is a page whose noindex it never sees.</p>
    </div>
</div>
</div>
