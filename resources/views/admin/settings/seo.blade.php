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
