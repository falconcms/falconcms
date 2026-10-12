<x-falcon-cms::layouts.admin>
    <x-slot name="title">SEO Settings - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">SEO Settings</h1>

        @include('falcon-cms::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.seo.update') }}" method="POST" class="max-w-[800px]">
            @csrf
            {!! do_falcon_action('falcon_seo_settings_form_top') !!}

            <div class="space-y-8">
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

                {{-- Permalinks: the first segment of each taxonomy archive --}}
                <div class="wp-metabox">
                    <div class="wp-metabox-header"><span>Permalinks</span></div>
                    <div class="wp-metabox-content p-4 space-y-4">
                        <p class="text-[13px] text-[#646970]">
                            The word in front of a term's address. Leave one blank to keep the default.
                            Old addresses are not thrown away &mdash; whatever a base was before you
                            changed it keeps working and redirects here permanently.
                        </p>

                        @php
                            $permalinkBases = [
                                ['key' => 'category_base', 'label' => 'Category base', 'default' => 'category', 'example' => 'nutrition'],
                                ['key' => 'tag_base', 'label' => 'Tag base', 'default' => 'tag', 'example' => 'quick-meals'],
                                ['key' => 'product_category_base', 'label' => 'Product category base', 'default' => 'product-category', 'example' => 'jackets'],
                                ['key' => 'product_tag_base', 'label' => 'Product tag base', 'default' => 'product-tag', 'example' => 'waterproof'],
                            ];
                        @endphp

                        @foreach($permalinkBases as $base)
                            @php $value = $settings[$base['key']] ?? ''; @endphp
                            <div>
                                <label class="block text-[14px] font-semibold text-[#1d2327] mb-1">{{ $base['label'] }}</label>
                                <div class="flex items-center gap-1 text-[13px] text-[#646970]">
                                    <span>{{ rtrim(url('/'), '/') }}/</span>
                                    <input type="text" name="{{ $base['key'] }}" value="{{ $value }}"
                                           placeholder="{{ $base['default'] }}"
                                           class="wp-input h-8 w-56 text-[13px]">
                                    <span>/{{ $base['example'] }}</span>
                                </div>
                                <p class="text-[12px] text-[#646970] mt-1">
                                    Letters, numbers and dashes, one segment. Anything else, or a word the
                                    CMS already answers on, falls back to <code>{{ $base['default'] }}</code>.
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Robots.txt --}}
                <div class="wp-metabox">
                    <div class="wp-metabox-header"><span>Robots.txt Content</span></div>
                    <div class="wp-metabox-content p-4">
                        <textarea name="robots_txt" rows="10" class="wp-input w-full font-mono text-[13px]" placeholder="User-agent: *
Disallow: /admin/
Allow: /">{{ $settings['robots_txt'] ?? "User-agent: *\nDisallow: /admin/\nAllow: /\n\nSitemap: " . url('/sitemap.xml') }}</textarea>
                        <p class="text-[12px] text-[#646970] mt-2">Edit your robots.txt file to guide search engine crawlers. This will be served at {{ url('/robots.txt') }}.</p>
                    </div>
                </div>

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
                                    @foreach(\FalconCms\Core\Support\StructuredData::BUSINESS_TYPES as $value => $label)
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
                                <span class="block text-[12px] text-[#646970]">Blocks {{ implode(', ', array_keys(\FalconCms\Core\Support\AiCrawlers::TRAINING)) }}.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="ai_block_search" value="1" @checked(($settings['ai_block_search'] ?? '0') === '1') class="rounded-sm border-[#8c8f94] mt-1">
                            <span>
                                <span class="text-[14px] font-semibold text-[#1d2327]">Keep this site out of AI search answers</span>
                                <span class="block text-[12px] text-[#646970]">Blocks {{ implode(', ', array_keys(\FalconCms\Core\Support\AiCrawlers::SEARCH)) }}. Usually best left off: these answers link to the sites they cite and send visitors.</span>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- IndexNow: instant notice to Bing, Yandex and the rest --}}
                @php
                    $indexNowOn = ($settings['indexnow_enabled'] ?? '0') === '1';
                    $indexNowLast = \FalconCms\Core\Support\IndexNow::last();
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
                                Key file: <a href="{{ \FalconCms\Core\Support\IndexNow::keyUrl() }}" target="_blank" rel="noopener" class="text-[#2271b1]">{{ \FalconCms\Core\Support\IndexNow::keyUrl() }}</a>
                                @if($indexNowLast)
                                    · last ping {{ \Carbon\Carbon::parse($indexNowLast['at'])->diffForHumans() }}: {{ $indexNowLast['urls'] }} URL(s), {{ \FalconCms\Core\Support\IndexNow::describe($indexNowLast['status']) }}
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
                    $bingSaved = ($settings['bing_webmaster_api_key'] ?? '') !== '';
                    $plannerCount = \FalconCms\Core\Support\KeywordDemand::plannerCount();
                @endphp
                <div class="wp-metabox" id="keyword-research">
                    <div class="wp-metabox-header"><span>Keyword research</span></div>
                    <div class="wp-metabox-content p-4 space-y-4">
                        <p class="text-[12px] text-[#646970]">How many people search for a keyword, shown in the editor (SEO → Keywords → Search demand). All three sources are free; use any of them.</p>

                        <div>
                            <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Bing Webmaster Tools API key</label>
                            <input type="password" name="bing_webmaster_api_key" autocomplete="off" class="wp-input w-[360px] max-w-full"
                                   placeholder="{{ $bingSaved ? 'Saved. Leave empty to keep it.' : 'Paste your API key' }}">
                            @if($bingSaved)
                                <label class="inline-flex items-center gap-1 text-[12px] text-[#646970] ml-2">
                                    <input type="checkbox" name="bing_webmaster_api_key_remove" value="1" class="rounded-sm border-[#8c8f94]"> Remove
                                </label>
                            @endif
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

                {{-- Global Meta --}}
                <div class="wp-metabox">
                    <div class="wp-metabox-header"><span>Global SEO Defaults</span></div>
                    <div class="wp-metabox-content p-4 space-y-4">
                        <div>
                            <label class="block text-[14px] font-semibold text-[#1d2327] mb-1">Separator</label>
                            <input type="text" name="seo_separator" value="{{ $settings['seo_separator'] ?? '-' }}" class="wp-input w-20 h-8 text-center">
                            <p class="text-[12px] text-[#646970] mt-1">Character used to separate title and site name (e.g. Page Title - Site Name).</p>
                        </div>

                        <div>
                            <label class="block text-[14px] font-semibold text-[#1d2327] mb-1">Default Social Share Image</label>
                            <div class="flex items-center gap-3">
                                <div id="seo-default-preview" class="w-24 h-24 bg-[#f0f0f1] border border-[#dfdfdf] rounded flex items-center justify-center overflow-hidden cursor-pointer" onclick="openGlobalSeoMedia()">
                                    @if(!empty($settings['seo_default_image']))
                                        <img src="{{ asset('storage/' . $settings['seo_default_image']) }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-8 h-8 text-[#dcdcde]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <input type="text" name="seo_default_image" id="seo-default-image" value="{{ $settings['seo_default_image'] ?? '' }}" class="wp-input w-full h-8 text-[12px] mb-2" placeholder="Image path...">
                                    <button type="button" onclick="openGlobalSeoMedia()" class="wp-btn-secondary h-8 py-0 px-3 text-[12px]">Select Default Image</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Twitter / X Card --}}
                <div class="wp-metabox">
                    <div class="wp-metabox-header"><span>Twitter / X Card</span></div>
                    <div class="wp-metabox-content p-4 space-y-4">
                        <div>
                            <label class="block text-[14px] font-semibold text-[#1d2327] mb-1">Twitter Handle</label>
                            <div class="flex items-center">
                                <span class="h-8 px-3 flex items-center bg-[#f0f0f1] border border-r-0 border-[#8c8f94] rounded-l text-[13px] text-[#646970]">@</span>
                                <input type="text" name="twitter_handle" value="{{ $settings['twitter_handle'] ?? '' }}" class="wp-input w-[300px] h-8 rounded-l-none" placeholder="yourbrand">
                            </div>
                            <p class="text-[12px] text-[#646970] mt-1">Your site's Twitter/X username (without @). Added as <code class="bg-[#f0f0f1] px-1 rounded text-[11px]">twitter:site</code> meta on every page.</p>
                        </div>
                        <div>
                            <label class="block text-[14px] font-semibold text-[#1d2327] mb-1">Default Card Type</label>
                            <select name="twitter_card_type" class="wp-input w-[300px] h-8 py-0">
                                <option value="summary" {{ ($settings['twitter_card_type'] ?? 'summary') === 'summary' ? 'selected' : '' }}>Summary (small square thumbnail)</option>
                                <option value="summary_large_image" {{ ($settings['twitter_card_type'] ?? '') === 'summary_large_image' ? 'selected' : '' }}>Summary with Large Image (banner-style preview)</option>
                            </select>
                            <p class="text-[12px] text-[#646970] mt-1">Controls how your pages look when shared on Twitter/X. "Large Image" is recommended for blogs and product pages.</p>
                        </div>
                    </div>
                </div>

                {{-- Sitemap Info --}}
                <div class="wp-metabox">
                    <div class="wp-metabox-header"><span>XML Sitemap Settings</span></div>
                    <div class="wp-metabox-content p-4 space-y-4">
                        <div class="flex items-center justify-between border-b pb-4">
                            <div>
                                <p class="text-[14px] text-[#1d2327] font-medium">Your sitemap is automatically generated.</p>
                                <p class="text-[13px] text-[#646970] mt-1">Search engines use this file to index your site better.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ url('/sitemap.xml') }}" target="_blank" class="wp-btn-secondary bg-[#f6f7f7] h-8 flex items-center px-4">View Sitemap</a>
                                <a href="{{ url('/sitemap.xml') }}" download="sitemap.xml" class="wp-btn-secondary bg-[#f6f7f7] h-8 flex items-center px-4">
                                    <svg class="w-4 h-4 mr-2 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Download
                                </a>
                            </div>
                        </div>

                        <div class="space-y-3 pt-2">
                            <p class="text-[13px] font-bold text-[#2c3338]">Include in Sitemap:</p>
                            <div class="grid grid-cols-2 gap-4">
                                @php
                                    $allPostTypes = \FalconCms\Core\Models\PostType::where('is_active', true)
                                        ->orderByDesc('is_builtin')
                                        ->orderBy('name')
                                        ->get();
                                @endphp
                                @foreach($allPostTypes as $pt)
                                <label class="flex items-center text-[13px] text-[#2c3338]">
                                    <input type="checkbox" name="sitemap_include_{{ $pt->slug }}" value="1"
                                           {{ ($settings['sitemap_include_' . $pt->slug] ?? '1') == '1' ? 'checked' : '' }}
                                           class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> {{ $pt->name }}
                                </label>
                                @endforeach
                                <label class="flex items-center text-[13px] text-[#2c3338]">
                                    <input type="checkbox" name="sitemap_include_categories" value="1" {{ ($settings['sitemap_include_categories'] ?? '1') == '1' ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> Post Categories
                                </label>
                                <label class="flex items-center text-[13px] text-[#2c3338]">
                                    <input type="checkbox" name="sitemap_include_tags" value="1" {{ ($settings['sitemap_include_tags'] ?? '0') == '1' ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> Post Tags
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {!! do_falcon_action('falcon_seo_settings_form_bottom') !!}

            <div class="mt-8 pt-6 border-t border-[#c3c4c7]">
                <button type="submit" class="wp-btn-primary h-[32px] px-4 font-semibold">Save SEO Settings</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function openGlobalSeoMedia() {
            if (typeof window.openMediaModal === 'function') {
                window.openMediaModal(function(media) {
                    const input = document.getElementById('seo-default-image');
                    const preview = document.getElementById('seo-default-preview');
                    if (input) input.value = media.path;
                    if (preview) {
                        preview.innerHTML = `<img src="/storage/${media.path}" class="w-full h-full object-cover">`;
                    }
                });
            }
        }
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
