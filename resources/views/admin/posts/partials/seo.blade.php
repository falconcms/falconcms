@php
    $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
    $seoTitle = $seo['title'] ?? ($post->title ?? '');
    $focusKeyword = $seo['focus_keyword'] ?? '';
@endphp

<div class="wp-metabox mt-5 overflow-hidden">
    <div class="wp-metabox-header flex justify-between items-center">
        <span class="font-bold">SEO Settings</span>
        <div class="flex gap-1 pr-2">
            <button type="button" onclick="switchSeoTab('general')" id="seo-tab-btn-general" class="seo-tab-btn px-3 py-1 text-[11px] font-bold rounded-sm bg-[#2271b1] text-white">General</button>
            <button type="button" onclick="switchSeoTab('social')" id="seo-tab-btn-social" class="seo-tab-btn px-3 py-1 text-[11px] font-bold rounded-sm bg-[#f0f0f1] text-[#2c3338] hover:bg-[#dcdcde]">Social</button>
            <button type="button" onclick="switchSeoTab('internal')" id="seo-tab-btn-internal" class="seo-tab-btn px-3 py-1 text-[11px] font-bold rounded-sm bg-[#f0f0f1] text-[#2c3338] hover:bg-[#dcdcde]">Internal Linking</button>
            <button type="button" onclick="switchSeoTab('advanced')" id="seo-tab-btn-advanced" class="seo-tab-btn px-3 py-1 text-[11px] font-bold rounded-sm bg-[#f0f0f1] text-[#2c3338] hover:bg-[#dcdcde]">Advanced</button>
        </div>
    </div>
    
    <div class="wp-metabox-content p-0">
        {{-- General Tab --}}
        <div id="seo-tab-general" class="seo-tab-content p-5 space-y-5">
            {{-- Focus Keyword --}}
            <div>
                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Focus Keyword</label>
                <input type="text" name="seo[focus_keyword]" id="seo-focus-keyword" value="{{ old('seo.focus_keyword', $focusKeyword) }}" 
                       class="wp-input w-full @error('seo.focus_keyword') border-[#d63638] @enderror" placeholder="Enter main keyword...">
                @error('seo.focus_keyword')
                    <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                @enderror
                <p class="text-[11px] text-[#646970] mt-1">What keyword should this post rank for?</p>
            </div>

            {{-- Preview Tool --}}
            <div class="bg-[#f6f7f7] border border-[#dcdcde] rounded p-4 mb-4">
                <p class="text-[11px] font-bold text-[#646970] uppercase mb-2">Search Preview</p>
                <div class="space-y-1">
                    <div id="preview-title" class="text-[18px] text-[#1a0dab] hover:underline cursor-pointer truncate">{{ $seoTitle }}</div>
                    {{-- The slug on its own is not the address: a post sits under its type's
                         base and a translation under its language prefix, so a custom post type
                         previewed here as /the-slug was never where the page actually lives.
                         The permalink helper is what the rest of the screen uses. --}}
                    {{-- The slug on its own is not the address: a custom post type sits under
                         its own base and a translation under its language prefix, so a recipe
                         previewed here as /the-slug was never where the page lives. Built the
                         same way the Permalink line above it is, so the two screens agree. --}}
                    @php
                        $previewSlug = $post->slug ?? '';
                        $previewType = $post->type ?? 'post';
                        $previewLang = $post->lang_code ?? falcon_default_language();
                        $previewPrefix = $previewLang === falcon_default_language() ? '' : $previewLang.'/';
                        if ($previewType !== 'post' && $previewType !== 'page') {
                            $previewPrefix .= $previewType.'/';
                        }
                        $previewUrl = $previewSlug ? rtrim(url('/'), '/').'/'.$previewPrefix.$previewSlug : url('/');
                    @endphp
                    <div id="preview-url" class="text-[14px] text-[#006621] truncate">{{ $previewUrl }}</div>
                    <div id="preview-desc" class="text-[13px] text-[#4d5156] line-clamp-2">{{ $seo['description'] ?? 'Please provide a meta description...' }}</div>
                </div>
            </div>

            {{-- SEO Title --}}
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="block text-[13px] font-bold text-[#2c3338]">SEO Title</label>
                    <span id="seo-title-count" class="text-[11px] text-[#646970]">0 characters</span>
                </div>
                <input type="text" name="seo[title]" id="seo-title-input" value="{{ old('seo.title', $seoTitle) }}" 
                       class="wp-input w-full @error('seo.title') border-[#d63638] @enderror" placeholder="Enter SEO title...">
                @error('seo.title')
                    <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                @enderror
                <div class="mt-1.5 h-1 w-full bg-[#f0f0f1] rounded-full overflow-hidden">
                    <div id="seo-title-bar" class="h-full w-0 transition-all duration-300"></div>
                </div>
                <p class="text-[11px] text-[#646970] mt-1">Ideal: 50-60 characters.</p>
            </div>

            {{-- SEO Description --}}
            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="block text-[13px] font-bold text-[#2c3338]">Meta Description</label>
                    <span id="seo-desc-count" class="text-[11px] text-[#646970]">0 characters</span>
                </div>
                <textarea name="seo[description]" id="seo-desc-input" rows="3" class="wp-input w-full @error('seo.description') border-[#d63638] @enderror" 
                          placeholder="Enter meta description...">{{ old('seo.description', $seo['description'] ?? '') }}</textarea>
                @error('seo.description')
                    <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                @enderror
                <div class="mt-1.5 h-1 w-full bg-[#f0f0f1] rounded-full overflow-hidden">
                    <div id="seo-desc-bar" class="h-full w-0 transition-all duration-300"></div>
                </div>
                <p class="text-[11px] text-[#646970] mt-1">Ideal: 150-160 characters.</p>
            </div>

            {{-- Analysis Section --}}
            <div class="pt-4 border-t border-[#dcdcde]">
                <p class="text-[13px] font-bold text-[#2c3338] mb-3">SEO Analysis</p>
                <ul id="seo-analysis-results" class="space-y-2">
                    <!-- Analysis results injected via JS -->
                </ul>
            </div>
        </div>

        {{-- Social Tab --}}
        <div id="seo-tab-social" class="seo-tab-content p-5 space-y-6 hidden">
            <div class="space-y-4">
                <p class="text-[14px] font-bold text-[#2c3338] border-b pb-2">Facebook (OpenGraph)</p>
                <div>
                    <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Facebook Title</label>
                    <input type="text" name="seo[og_title]" value="{{ old('seo.og_title', $seo['og_title'] ?? '') }}" class="wp-input w-full @error('seo.og_title') border-[#d63638] @enderror" placeholder="Defaults to SEO title">
                    @error('seo.og_title')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Facebook Description</label>
                    <textarea name="seo[og_description]" rows="2" class="wp-input w-full @error('seo.og_description') border-[#d63638] @enderror" placeholder="Defaults to meta description">{{ old('seo.og_description', $seo['og_description'] ?? '') }}</textarea>
                    @error('seo.og_description')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-[13px] font-bold text-[#2c3338] mb-2">Facebook Image</label>
                    <div class="flex items-center gap-3">
                        <div id="seo-og-preview" class="w-20 h-20 bg-[#f0f0f1] border border-[#dfdfdf] rounded flex items-center justify-center overflow-hidden cursor-pointer" onclick="openSeoMedia('og')">
                            @if(!empty($seo['og_image']))
                                <img src="{{ asset('storage/' . $seo['og_image']) }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-8 h-8 text-[#dcdcde]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1">
                            <input type="text" name="seo[og_image]" id="seo-og-image" value="{{ old('seo.og_image', $seo['og_image'] ?? '') }}" class="wp-input w-full h-8 text-[12px] mb-1" placeholder="Image path...">
                            <button type="button" onclick="openSeoMedia('og')" class="wp-btn-secondary h-8 py-0 px-3 text-[12px]">Select Image</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 pt-4 border-t">
                <p class="text-[14px] font-bold text-[#2c3338] border-b pb-2">X (Twitter Cards)</p>
                <div>
                    <label class="block text-[13px] font-bold text-[#2c3338] mb-1">X Title</label>
                    <input type="text" name="seo[twitter_title]" value="{{ old('seo.twitter_title', $seo['twitter_title'] ?? '') }}" class="wp-input w-full @error('seo.twitter_title') border-[#d63638] @enderror" placeholder="Defaults to SEO title">
                    @error('seo.twitter_title')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-[13px] font-bold text-[#2c3338] mb-2">X Image</label>
                    <div class="flex items-center gap-3">
                        <div id="seo-twitter-preview" class="w-20 h-20 bg-[#f0f0f1] border border-[#dfdfdf] rounded flex items-center justify-center overflow-hidden cursor-pointer" onclick="openSeoMedia('twitter')">
                            @if(!empty($seo['twitter_image']))
                                <img src="{{ asset('storage/' . $seo['twitter_image']) }}" class="w-full h-full object-cover">
                            @else
                                <svg class="w-8 h-8 text-[#dcdcde]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1">
                            <input type="text" name="seo[twitter_image]" id="seo-twitter-image" value="{{ old('seo.twitter_image', $seo['twitter_image'] ?? '') }}" class="wp-input w-full h-8 text-[12px] mb-1" placeholder="Image path...">
                            <button type="button" onclick="openSeoMedia('twitter')" class="wp-btn-secondary h-8 py-0 px-3 text-[12px]">Select Image</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Internal Linking Tab --}}
        <div id="seo-tab-internal" class="seo-tab-content hidden p-4">
            <div class="mb-4">
                <h4 class="text-[13px] font-bold text-[#2c3338] mb-1">Internal Link Suggestions</h4>
                <p class="text-[12px] text-[#646970]">Linking to related content helps search engines understand your site structure and improves user engagement.</p>
            </div>
            <div id="internal-links-suggestions" class="space-y-3">
                <div class="text-[13px] text-gray-500 italic">Start typing a title to see suggestions...</div>
            </div>
        </div>

        {{-- Advanced Tab --}}
        <div id="seo-tab-advanced" class="seo-tab-content p-5 space-y-5 hidden">
            <div>
                <label class="block text-[13px] font-bold text-[#2c3338] mb-1">Canonical URL</label>
                <input type="text" name="seo[canonical_url]" value="{{ old('seo.canonical_url', $seo['canonical_url'] ?? '') }}" 
                       class="wp-input w-full @error('seo.canonical_url') border-[#d63638] @enderror" placeholder="https://example.com/canonical-page">
                @error('seo.canonical_url')
                    <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                @enderror
                <p class="text-[11px] text-[#646970] mt-1">If this is a duplicate page, specify the original URL.</p>
            </div>
            
            <div class="space-y-2">
                <label class="flex items-center text-[13px] text-[#2c3338] font-bold">
                    <input type="checkbox" name="seo[noindex]" value="1" {{ ($seo['noindex'] ?? false) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> 
                    Discourage search engines from indexing this page (noindex)
                </label>
                <label class="flex items-center text-[13px] text-[#2c3338] font-bold">
                    <input type="checkbox" name="seo[nofollow]" value="1" {{ ($seo['nofollow'] ?? false) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> 
                    Don't follow links on this page (nofollow)
                </label>
            </div>
        </div>
    </div>
</div>

<script>
    function switchSeoTab(tab) {
        document.querySelectorAll('.seo-tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.seo-tab-btn').forEach(btn => {
            btn.classList.remove('bg-[#2271b1]', 'text-white');
            btn.classList.add('bg-[#f0f0f1]', 'text-[#2c3338]');
        });

        document.getElementById('seo-tab-' + tab).classList.remove('hidden');
        document.getElementById('seo-tab-btn-' + tab).classList.add('bg-[#2271b1]', 'text-white');
        document.getElementById('seo-tab-btn-' + tab).classList.remove('bg-[#f0f0f1]', 'text-[#2c3338]');

        if (tab === 'internal') {
            fetchInternalSuggestions();
        }
    }

    function fetchInternalSuggestions() {
        const title = document.querySelector('input[name="title"]').value;
        const container = document.getElementById('internal-links-suggestions');
        const postId = "{{ $post->id ?? 0 }}";

        if (!title || title.length < 3) {
            container.innerHTML = '<div class="text-[13px] text-gray-500 italic">Enter at least 3 characters in the post title to see suggestions...</div>';
            return;
        }

        container.innerHTML = '<div class="text-[13px] text-gray-500 italic">Searching for related content...</div>';

        fetch("{{ route('admin.seo.related-posts') }}?s=" + encodeURIComponent(title) + "&exclude=" + postId)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    container.innerHTML = '<div class="text-[13px] text-red-500 italic">No related posts found.</div>';
                    return;
                }

                let html = '<div class="space-y-2">';
                data.forEach(item => {
                    html += `
                        <div class="p-2 border border-gray-100 rounded bg-gray-50 flex items-center justify-between group">
                            <div class="flex-1">
                                <div class="text-[13px] font-medium text-[#2271b1] truncate">${item.title}</div>
                                <div class="text-[11px] text-gray-400 truncate">${item.url}</div>
                            </div>
                            <button type="button" onclick="copyToClipboard('${item.url}')" class="ml-2 p-1.5 bg-white border border-gray-200 rounded shadow-sm hover:bg-gray-100 transition-colors" title="Copy URL">
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            });
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            window.showToast('URL copied to clipboard!', 'success');
        });
    }

    // Update suggestions when title changes
    document.querySelector('input[name="title"]').addEventListener('input', debounce(function() {
        if (!document.getElementById('seo-tab-internal').classList.contains('hidden')) {
            fetchInternalSuggestions();
        }
    }, 500));

    function debounce(func, wait) {
        let timeout;
        return function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, arguments), wait);
        };
    }

    function openSeoMedia(type) {
        if (typeof window.openMediaModal === 'function') {
            window.openMediaModal(function(media) {
                const inputId = type === 'og' ? 'seo-og-image' : 'seo-twitter-image';
                const previewId = type === 'og' ? 'seo-og-preview' : 'seo-twitter-preview';
                
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                if (input) input.value = media.path;
                if (preview) {
                    preview.innerHTML = `<img src="/storage/${media.path}" class="w-full h-full object-cover">`;
                }
            });
        }
    }

    function runSeoAnalysis() {
        const title = document.getElementById('seo-title-input')?.value || '';
        const desc = document.getElementById('seo-desc-input')?.value || '';
        const keyword = document.getElementById('seo-focus-keyword')?.value.toLowerCase() || '';
        const results = document.getElementById('seo-analysis-results');
        
        // Use TinyMCE content if available, otherwise fallback to textarea
        let content = '';
        if (window.tinymce && tinymce.get('wp-editor')) {
            content = tinymce.get('wp-editor').getContent({format: 'text'}).toLowerCase();
        } else {
            content = document.querySelector('textarea[name="content"]')?.value.toLowerCase() || '';
        }

        if (!results) return;
        results.innerHTML = '';

        const checks = [
            { 
                label: 'Focus keyword in SEO title', 
                status: keyword && title.toLowerCase().includes(keyword) ? 'good' : 'bad',
                msg: keyword ? '' : 'Provide a focus keyword to analyze.'
            },
            {
                label: 'Focus keyword in Meta description',
                status: keyword && desc.toLowerCase().includes(keyword) ? 'good' : 'bad'
            },
            {
                label: 'SEO Title length',
                status: (title.length >= 50 && title.length <= 60) ? 'good' : 'warning'
            },
            {
                label: 'Meta Description length',
                status: (desc.length >= 150 && desc.length <= 160) ? 'good' : 'warning'
            },
            {
                label: 'Focus keyword in content',
                status: keyword && content.includes(keyword) ? 'good' : 'bad'
            }
        ];

        checks.forEach(check => {
            const li = document.createElement('li');
            li.className = "flex items-center text-[12px]";
            let dotColor = '#d63638'; // Default red
            if (check.status === 'good') dotColor = '#00a32a';
            if (check.status === 'warning') dotColor = '#dba617';

            li.innerHTML = `<span class="w-2.5 h-2.5 rounded-full mr-2 shrink-0" style="background-color: ${dotColor}"></span> <span>${check.label}</span>`;
            results.appendChild(li);
        });

        // Update Previews
        document.getElementById('preview-title').innerText = title || 'Page Title';
        document.getElementById('preview-desc').innerText = desc || 'Please provide a meta description...';
    }

    function updateSeoBar(inputId, barId, countId, minOk, maxOk, maxLimit) {
        const input = document.getElementById(inputId);
        const bar = document.getElementById(barId);
        const count = document.getElementById(countId);
        if (!input || !bar || !count) return;

        const len = input.value.length;
        count.innerText = `${len} characters`;

        let percentage = (len / maxLimit) * 100;
        if (percentage > 100) percentage = 100;
        bar.style.width = `${percentage}%`;

        if (len === 0) {
            bar.style.backgroundColor = '#f0f0f1';
        } else if (len < minOk) {
            bar.style.backgroundColor = '#d63638';
        } else if (len >= minOk && len <= maxOk) {
            bar.style.backgroundColor = '#00a32a';
        } else {
            bar.style.backgroundColor = '#dba617';
        }
    }

    const seoTitleInput = document.getElementById('seo-title-input');
    const seoDescInput = document.getElementById('seo-desc-input');
    const seoFocusInput = document.getElementById('seo-focus-keyword');

    [seoTitleInput, seoDescInput, seoFocusInput].forEach(el => {
        el?.addEventListener('input', () => {
            updateSeoBar('seo-title-input', 'seo-title-bar', 'seo-title-count', 50, 60, 80);
            updateSeoBar('seo-desc-input', 'seo-desc-bar', 'seo-desc-count', 150, 160, 200);
            runSeoAnalysis();
        });
    });

    // Run once on load
    setTimeout(() => {
        updateSeoBar('seo-title-input', 'seo-title-bar', 'seo-title-count', 50, 60, 80);
        updateSeoBar('seo-desc-input', 'seo-desc-bar', 'seo-desc-count', 150, 160, 200);
        runSeoAnalysis();
    }, 500);

    // Sync with main title
    const mainTitleInput = document.getElementById('title-input');
    let isSeoTitleManuallyEdited = {{ !empty($seo['title']) ? 'true' : 'false' }};

    if (seoTitleInput && mainTitleInput) {
        mainTitleInput.addEventListener('input', function() {
            if (!isSeoTitleManuallyEdited) {
                seoTitleInput.value = this.value;
                updateSeoBar('seo-title-input', 'seo-title-bar', 'seo-title-count', 50, 60, 80);
                runSeoAnalysis();
            }
        });
        seoTitleInput.addEventListener('input', () => isSeoTitleManuallyEdited = true);
    }
</script>
