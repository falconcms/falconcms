<x-falcon-cms::layouts.admin title="Add New Page">
    
    <div class="flex items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Add New Page</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p class="font-bold mb-2">Error: Please check the following fields:</p>
            <ul class="list-disc list-inside text-[#d63638] space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.pages.store') }}" method="POST" id="page-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="status" id="status-hidden" value="published">
        
        <div class="flex flex-col lg:flex-row gap-5">
            <!-- Left Column: Content -->
            <div class="flex-grow min-w-0">
                <div class="mb-4">
                    <input type="text" name="title" id="title-input" value="{{ old('title') }}" 
                           class="w-full text-[1.7em] leading-normal border @error('title') border-[#d63638] @else border-[#8c8f94] @enderror rounded-sm py-[3px] px-[8px] focus:ring-[#2271b1] focus:border-[#2271b1] shadow-none m-0 bg-white" 
                           placeholder="Add title" required>
                    @error('title')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                    
                    <div id="permalink-container" class="mt-2 text-[13px] {{ old('title') ? 'flex' : 'hidden' }} items-center font-medium">
                        <span class="text-[#646970] mr-1">Permalink:</span>
                        <span id="permalink-view">
                            @php 
                                $selectedLang = request('lang', get_cms_option('default_language', 'en'));
                                $baseUrl = url($selectedLang === 'en' ? '/' : $selectedLang) . '/';
                            @endphp
                            <a id="permalink-full-link" href="#" target="_blank" class="text-[#2271b1] underline font-medium"><span id="permalink-base-display">{{ $baseUrl }}</span><span id="permalink-slug-display" class="text-[#2271b1]">{{ old('slug') }}</span>/</a>
                            <button type="button" id="edit-slug-btn" class="wp-btn-secondary bg-[#f6f7f7] text-[12px] h-[24px] ml-1 font-medium text-[#2271b1] border-[#c3c4c7]">Edit</button>
                        </span>
                        <span id="permalink-edit" class="hidden items-center">
                            <span class="text-[#646970] font-medium" id="permalink-base-edit">{{ $baseUrl }}</span>
                            <input type="text" name="slug" id="slug-input" value="{{ old('slug') }}" class="wp-input text-[13px] h-[24px] px-1 mx-1 font-medium" style="width: 150px;">/
                            <button type="button" id="ok-slug-btn" class="wp-btn-secondary bg-[#f6f7f7] text-[12px] h-[24px] mx-1 font-medium">OK</button>
                            <a href="#" id="cancel-slug-btn" class="text-[#2271b1] underline ml-1 font-medium">Cancel</a>
                        </span>
                    </div>
                </div>


                <!-- Editor Toggle -->
                <div class="flex items-center mb-[1px] relative z-10 pl-2">
                    <button type="button" id="editor-mode-rich" class="px-4 py-2 text-[13px] font-semibold border border-[#dcdcde] border-b-0 bg-white text-[#2c3338] rounded-t-sm">Rich Editor</button>
                    <button type="button" id="editor-mode-builder" class="px-4 py-2 text-[13px] font-semibold border border-transparent border-b-0 text-[#2271b1] hover:text-[#0a4b78] rounded-t-sm">Page Builder</button>
                </div>
                <input type="hidden" name="editor_type" id="editor_type" value="{{ old('editor_type', 'rich') }}">

                <!-- Rich Text Editor -->
                <div id="rich-editor-container" class="bg-white border border-[#dcdcde] rounded-sm p-0 {{ old('editor_type') === 'builder' ? 'hidden' : '' }}">
                    <textarea id="wp-editor" name="content" rows="20">{{ old('content') }}</textarea>
                </div>

                <!-- Page Builder Placeholder -->
                <div id="page-builder-placeholder" class="bg-white border border-[#dcdcde] rounded-sm p-8 {{ old('editor_type') === 'builder' ? '' : 'hidden' }}">
                    <div class="border-2 border-dashed border-[#dcdcde] rounded-xl p-16 text-center bg-[#fcfcfc] flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-14 h-14 rounded-full bg-[#f0f6fc] text-[#0A66C2] flex items-center justify-center mb-6 shadow-sm border border-[#e1e9f1] cursor-pointer hover:bg-[#e1edf9] transition-colors">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        </div>
                        <h2 class="text-[#2c3338] text-[22px] font-bold mb-3">Build your amazing page here</h2>
                        <p class="text-[#646970] text-[14px] mb-8">Click the "+" icon above to add your first container</p>
                        
                        <button type="button" id="start-builder-btn" class="wp-btn-primary px-6 py-2 h-auto text-[15px] rounded-md shadow-sm">
                            Build your site with amazing builder
                        </button>
                    </div>
                </div>

                <!-- Custom Fields Groups -->
                @if(isset($fieldGroups) && $fieldGroups->isNotEmpty())
                    @foreach($fieldGroups as $group)
                    <div class="wp-metabox mt-5 mb-0">
                        <div class="wp-metabox-header"><span>{{ $group->title }}</span></div>
                        <div class="wp-metabox-content" style="padding: 16px;">
                            <div class="space-y-6">
                                @foreach($group->fields as $field)
                                <div class="field-row">
                                    <label class="block text-[13px] font-bold text-[#1d2327] mb-1">{{ $field->label }}</label>
                                    @if($field->instructions)
                                        <p class="text-[12px] text-[#646970] mb-2 italic">{{ $field->instructions }}</p>
                                    @endif

                                    @if($field->type === 'text')
                                        <input type="text" name="custom_fields[{{ $field->id }}]" class="wp-input w-full" placeholder="">
                                    @elseif($field->type === 'textarea')
                                        <textarea name="custom_fields[{{ $field->id }}]" rows="4" class="wp-input w-full"></textarea>
                                    @elseif($field->type === 'select')
                                        <select name="custom_fields[{{ $field->id }}]" class="wp-input w-full h-8 py-0">
                                            <option value="">Select an option</option>
                                        </select>
                                    @elseif($field->type === 'wysiwyg')
                                        <textarea name="custom_fields[{{ $field->id }}]" class="wp-input w-full h-32"></textarea>
                                    @elseif($field->type === 'image')
                                        <div class="flex items-center gap-4">
                                            <div class="w-20 h-20 bg-[#f0f0f1] border border-[#c3c4c7] rounded-sm flex items-center justify-center overflow-hidden">
                                                <img src="" class="hidden w-full h-full object-cover">
                                                <svg class="w-8 h-8 text-[#c3c4c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <button type="button" class="wp-btn-secondary h-8 px-4 text-[12px]">Choose Image</button>
                                            <input type="hidden" name="custom_fields[{{ $field->id }}]">
                                        </div>
                                    @else
                                        <input type="text" name="custom_fields[{{ $field->id }}]" class="wp-input w-full">
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
                
                @include('falcon-cms::admin.posts.partials.seo', ['post' => new \FalconCms\Core\Models\Post()])
            </div>

            <!-- Right Column: Metaboxes -->
            <div class="w-full lg:w-[280px] shrink-0 space-y-5">
                
                <!-- Multilingual Metabox -->
                @php 
                    $isMultiLang = get_cms_option('multi_language_enabled', 0);
                    $activeLanguages = \FalconCms\Core\Models\Language::where('status', true)->get(); 
                @endphp
                @if($activeLanguages->count() > 1)
                <div class="wp-metabox mb-0">
                    <div class="wp-metabox-header"><span>Language</span></div>
                    <div class="wp-metabox-content p-3">
                        <div class="mb-3">
                            <label class="block text-[12px] font-bold text-[#1d2327] mb-1">Page Language</label>
                            <select name="lang_code" class="wp-input w-full text-[13px] h-8 py-0">
                                @foreach($activeLanguages as $lang)
                                    <option value="{{ $lang->code }}" {{ $lang->is_default ? 'selected' : '' }}>
                                        {{ $lang->flag }} {{ $lang->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">Language of current content.</p>
                        </div>

                        <hr class="my-3 border-gray-100">
                        <label class="flex items-center text-[13px] font-bold text-[#1d2327] mb-3 cursor-pointer">
                            <input type="checkbox" name="make_multilingual_copy" value="1" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]" onchange="document.getElementById('multi-lang-list').classList.toggle('hidden', !this.checked)">
                            Make a copy for other languages?
                        </label>
                        
                        <div id="multi-lang-list" class="hidden space-y-2 pl-6 border-l-2 border-gray-100">
                            @foreach($activeLanguages as $lang)
                                <label class="flex items-center text-[12px] text-[#2c3338] lang-option-{{ $lang->code }}">
                                    <input type="checkbox" name="copy_to_languages[]" value="{{ $lang->code }}" checked class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                    <span class="mr-1">{{ $lang->flag }}</span> {{ $lang->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                @else
                    <input type="hidden" name="lang_code" value="{{ get_cms_option('default_language', 'en') }}">
                @endif

                <!-- Publish Metabox -->
                <div class="wp-metabox mb-0">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
                        <span>Publish</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    </div>
                    <div class="wp-metabox-content" style="padding: 10px;">
                        <div class="flex justify-between items-center mb-3">
                            <button type="button" id="save-draft-btn" formnovalidate class="wp-btn-secondary text-[13px] bg-[#f6f7f7]">Save Draft</button>
                        </div>
                        <div class="text-[13px] text-[#646970] space-y-3 mb-4">
                            <!-- Status -->
                            <div class="flex items-start">
                                <svg class="w-4 h-4 mr-1 mt-[2px]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg> 
                                <div class="flex-grow">
                                    Status: <strong class="text-black" id="status-display-text">Published</strong> <a href="#" class="text-[#2271b1] underline ml-1 toggle-publish-edit" data-target="status-edit">Edit</a>
                                    <div id="status-edit" class="hidden mt-2 p-2 bg-[#f6f7f7] border border-[#dfdfdf]">
                                        <div class="flex space-x-1">
                                            <select id="status-select-ui" class="wp-input text-[13px] py-0 h-[26px] flex-grow">
                                                <option value="draft">Draft</option>
                                                <option value="published" selected>Published</option>
                                                <option value="scheduled">Scheduled</option>
                                            </select>
                                            <button type="button" id="ok-status-btn" class="wp-btn-secondary text-[12px] h-[26px]">OK</button> 
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Visibility -->
                            <div class="flex items-start">
                                <svg class="w-4 h-4 mr-1 mt-[2px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z" /><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" /></svg>
                                <div class="flex-grow">
                                    Visibility: <strong class="text-black" id="visibility-display-text">Public</strong> <a href="#" class="text-[#2271b1] underline ml-1 toggle-publish-edit" data-target="visibility-edit">Edit</a>
                                    <div id="visibility-edit" class="hidden mt-2 p-2 bg-[#f6f7f7] border border-[#dfdfdf] space-y-1">
                                        <label class="flex items-center text-black"><input type="radio" value="Public" name="visibility" checked class="mr-1"> Public</label>
                                        <div class="mt-2 text-right"><button type="button" id="ok-visibility-btn" class="wp-btn-secondary text-[12px] h-[24px]">OK</button></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Publish Time -->
                            <div class="flex items-start">
                                <svg class="w-4 h-4 mr-1 mt-[2px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> 
                                <div class="flex-grow">
                                    <span id="publish-display-prefix">Publish</span> <strong class="text-black" id="publish-display-text">immediately</strong> <a href="#" class="text-[#2271b1] underline ml-1 toggle-publish-edit" data-target="publish-edit">Edit</a>
                                    <div id="publish-edit" class="hidden mt-2 p-3 bg-[#f6f7f7] border border-[#dfdfdf] rounded">
                                        @php
                                            $months = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
                                            $pDate = now(cms_timezone());
                                            $curY  = (int) date('Y');
                                            $years = range($curY - 1, $curY + 6);
                                        @endphp
                                        <div class="grid grid-cols-3 gap-2 mb-2">
                                            <div>
                                                <label class="block text-[10px] font-medium text-[#646970] uppercase mb-1">Month</label>
                                                <select id="pub-mm" class="wp-input text-[12px] h-[30px] w-full">
                                                    @foreach($months as $mNum => $mName)
                                                    <option value="{{ $mNum }}" {{ $pDate->format('m') === $mNum ? 'selected' : '' }}>{{ $mName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-medium text-[#646970] uppercase mb-1">Day</label>
                                                <select id="pub-dd" class="wp-input text-[12px] h-[30px] w-full">
                                                    @for($d = 1; $d <= 31; $d++)
                                                    @php $dd = sprintf('%02d', $d); @endphp
                                                    <option value="{{ $dd }}" {{ $pDate->format('d') === $dd ? 'selected' : '' }}>{{ $dd }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-medium text-[#646970] uppercase mb-1">Year</label>
                                                <select id="pub-yy" class="wp-input text-[12px] h-[30px] w-full">
                                                    @foreach($years as $y)
                                                    <option value="{{ $y }}" {{ (int)$pDate->format('Y') === $y ? 'selected' : '' }}>{{ $y }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mb-2">
                                            <div>
                                                <label class="block text-[10px] font-medium text-[#646970] uppercase mb-1">Hour</label>
                                                <select id="pub-hr" class="wp-input text-[12px] h-[30px] w-full">
                                                    @for($h = 0; $h <= 23; $h++)
                                                    @php $hh = sprintf('%02d', $h); @endphp
                                                    <option value="{{ $hh }}" {{ $pDate->format('H') === $hh ? 'selected' : '' }}>{{ $hh }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-medium text-[#646970] uppercase mb-1">Minute</label>
                                                <select id="pub-min" class="wp-input text-[12px] h-[30px] w-full">
                                                    @for($mi = 0; $mi <= 59; $mi++)
                                                    @php $mm = sprintf('%02d', $mi); @endphp
                                                    <option value="{{ $mm }}" {{ $pDate->format('i') === $mm ? 'selected' : '' }}>{{ $mm }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        </div>
                                        <input type="hidden" name="published_at" id="published-at-hidden">
                                        <div class="text-right"><button type="button" id="ok-publish-btn" class="wp-btn-secondary text-[12px] h-[28px]">OK</button></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-[#f6f7f7] border-t border-[#dfdfdf] p-2 flex justify-between items-center">
                        <a href="#" class="text-[#b32d2e] hover:text-[#8a2424] text-[13px] underline">Move to Trash</a>
                        <button type="submit" id="main-publish-btn" class="wp-btn-primary">Publish</button>
                    </div>
                </div>


                <!-- Featured Image -->
                <div class="wp-metabox mb-0">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer"><span>Featured image</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div>
                    <div class="wp-metabox-content">
                        <div id="fi-preview-container" class="hidden mb-3">
                            <img id="fi-preview" src="" class="max-w-full h-auto border border-gray-200 p-1 bg-white cursor-pointer">
                        </div>
                        <a href="#" id="set-fi-btn" class="text-[#2271b1] text-[13px] underline">Set featured image</a>
                        <a href="#" id="remove-fi-btn" class="text-[#b32d2e] text-[13px] underline hidden mt-2">Remove featured image</a>
                        <input type="hidden" name="featured_image" id="fi-path-hidden">
                    </div>
                </div>

            </div>
        </div>
    </form>

    <script src="{{ asset('vendor/falcon-cms/js/tinymce.min.js') }}"></script>
    <script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize TinyMCE
            tinymce.init({
                selector: '#wp-editor',
                menubar: false, height: 450,
                plugins: ['lists', 'link', 'image', 'preview', 'code', 'fullscreen', 'media', 'table', 'wordcount'],
                toolbar: 'blocks | bold italic underline strikethrough | blockquote | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | code fullscreen',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size:14px }',
                branding: false,
                image_title: true, automatic_uploads: true, file_picker_types: 'image',
                file_picker_callback: function (cb, value, meta) {
                    window.openMediaModal(function(media) {
                       cb(`/storage/${media.path}`, { title: media.title, alt: media.alt_text });
                    });
                }
            });

            // Title & Permalink Logic
            const titleInput = document.getElementById('title-input');
            const permalinkContainer = document.getElementById('permalink-container');
            const slugDisplay = document.getElementById('permalink-slug-display');
            const slugInput = document.getElementById('slug-input');
            const viewSpan = document.getElementById('permalink-view');
            const editSpan = document.getElementById('permalink-edit');
            const statusHidden = document.getElementById('status-hidden');
            let originalSlug = '';

            function generateSlug(text) {
                return text.toString().toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '-').replace(/^-+/, '').replace(/-+$/, '');
            }

            titleInput?.addEventListener('blur', function() {
                if (this.value && !slugInput.value) {
                    let newSlug = generateSlug(this.value);
                    slugInput.value = newSlug;
                    if (slugDisplay) slugDisplay.innerText = newSlug;
                    originalSlug = newSlug;
                }
                
                if (this.value) {
                    permalinkContainer?.classList.remove('hidden');
                    permalinkContainer?.classList.add('flex');
                }
            });

            document.getElementById('edit-slug-btn')?.addEventListener('click', function() {
                viewSpan?.classList.add('hidden');
                editSpan?.classList.remove('hidden');
                slugInput?.focus();
            });

            document.getElementById('ok-slug-btn')?.addEventListener('click', function() {
                let newSlug = generateSlug(slugInput.value);
                slugInput.value = newSlug;
                slugDisplay.innerText = newSlug;
                originalSlug = newSlug;
                viewSpan?.classList.remove('hidden');
                editSpan?.classList.add('hidden');
            });

            document.getElementById('cancel-slug-btn')?.addEventListener('click', (e) => { 
                e.preventDefault(); slugInput.value = originalSlug; viewSpan?.classList.remove('hidden'); editSpan?.classList.add('hidden'); 
            });

            // Featured Image UI
            const setFiBtn = document.getElementById('set-fi-btn');
            const removeFiBtn = document.getElementById('remove-fi-btn');
            const fiPreview = document.getElementById('fi-preview');
            const fiPreviewContainer = document.getElementById('fi-preview-container');
            const fiPathHidden = document.getElementById('fi-path-hidden');

            setFiBtn?.addEventListener('click', (e) => { 
                e.preventDefault(); 
                window.openMediaModal(function(media) {
                    fiPathHidden.value = media.path;
                    fiPreview.src = `/storage/${media.path}`;
                    fiPreviewContainer?.classList.remove('hidden');
                    setFiBtn.classList.add('hidden');
                    removeFiBtn?.classList.remove('hidden');
                });
            });

            fiPreview?.addEventListener('click', (e) => { e.preventDefault(); setFiBtn?.click(); });

            removeFiBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                fiPathHidden.value = '';
                fiPreviewContainer?.classList.add('hidden');
                setFiBtn?.classList.remove('hidden');
                removeFiBtn.classList.add('hidden');
            });

            // Publish Metabox Logic
            document.querySelectorAll('.toggle-publish-edit').forEach(el => {
                el.addEventListener('click', function(e) { e.preventDefault(); document.getElementById(this.getAttribute('data-target')).classList.toggle('hidden'); });
            });

            document.getElementById('ok-status-btn')?.addEventListener('click', function() {
                let select = document.getElementById('status-select-ui');
                document.getElementById('status-display-text').innerText = select.options[select.selectedIndex].text;
                statusHidden.value = select.value;
                document.getElementById('status-edit').classList.add('hidden');
            });

            document.getElementById('ok-visibility-btn')?.addEventListener('click', function() {
                let selected = document.querySelector('input[name="visibility"]:checked').value;
                document.getElementById('visibility-display-text').innerText = selected;
                document.getElementById('visibility-edit').classList.add('hidden');
            });

            document.getElementById('ok-publish-btn')?.addEventListener('click', function() {
                let mm = document.getElementById('pub-mm').value, 
                    dd = document.getElementById('pub-dd').value, 
                    yy = document.getElementById('pub-yy').value, 
                    hr = document.getElementById('pub-hr').value, 
                    min = document.getElementById('pub-min').value;
                
                let selDate = new Date(`${yy}-${mm}-${dd}T${hr}:${min}:00`);
                // Compare against the CMS timezone "now" (Settings → General), not the browser clock.
                let isFuture = selDate > new Date("{{ now(cms_timezone())->format('Y-m-d\TH:i:s') }}");
                let monthName = document.getElementById('pub-mm').options[document.getElementById('pub-mm').selectedIndex].text;
                
                document.getElementById('publish-display-prefix').innerText = isFuture ? 'Scheduled for' : 'Publish';
                document.getElementById('publish-display-text').innerText = isFuture ? `${monthName} ${dd}, ${yy} @ ${hr}:${min}` : 'immediately';
                
                if (isFuture) {
                    document.getElementById('main-publish-btn').innerText = 'Schedule';
                    statusHidden.value = 'scheduled';
                    document.getElementById('status-display-text').innerText = 'Scheduled';
                    const statusSelectUI = document.getElementById('status-select-ui');
                    if (statusSelectUI) statusSelectUI.value = 'scheduled';
                } else {
                    document.getElementById('main-publish-btn').innerText = 'Publish';
                    if (statusHidden.value === 'scheduled') {
                        statusHidden.value = 'published';
                        document.getElementById('status-display-text').innerText = 'Published';
                        const statusSelectUI = document.getElementById('status-select-ui');
                        if (statusSelectUI) statusSelectUI.value = 'published';
                    }
                }
                document.getElementById('published-at-hidden').value = `${yy}-${mm}-${dd} ${hr}:${min}:00`;
                document.getElementById('publish-edit').classList.add('hidden');
            });

            // Editor Toggle Logic
            const richEditorBtn = document.getElementById('editor-mode-rich');
            const builderEditorBtn = document.getElementById('editor-mode-builder');
            const richEditorContainer = document.getElementById('rich-editor-container');
            const builderPlaceholder = document.getElementById('page-builder-placeholder');
            const editorTypeHidden = document.getElementById('editor_type');

            function switchEditorMode(mode) {
                if (!richEditorBtn || !builderEditorBtn) return;
                
                if (mode === 'builder') {
                    richEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-transparent border-b-0 text-[#2271b1] hover:text-[#0a4b78] rounded-t-sm";
                    builderEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-[#dcdcde] border-b-0 bg-white text-[#2c3338] rounded-t-sm shadow-[0_1px_0_#fff]";
                    richEditorContainer?.classList.add('hidden');
                    builderPlaceholder?.classList.remove('hidden');
                    editorTypeHidden.value = 'builder';
                } else {
                    builderEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-transparent border-b-0 text-[#2271b1] hover:text-[#0a4b78] rounded-t-sm";
                    richEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-[#dcdcde] border-b-0 bg-white text-[#2c3338] rounded-t-sm shadow-[0_1px_0_#fff]";
                    builderPlaceholder?.classList.add('hidden');
                    richEditorContainer?.classList.remove('hidden');
                    editorTypeHidden.value = 'rich';
                }
            }

            richEditorBtn?.addEventListener('click', () => switchEditorMode('rich'));
            builderEditorBtn?.addEventListener('click', () => switchEditorMode('builder'));
            if (editorTypeHidden) switchEditorMode(editorTypeHidden.value || 'rich');

            // Save Draft & Start Builder
            document.getElementById('save-draft-btn')?.addEventListener('click', () => {
                statusHidden.value = 'draft';
                document.getElementById('page-form')?.submit();
            });

            document.getElementById('start-builder-btn')?.addEventListener('click', function() {
                const statusH = document.getElementById('status-hidden');
                if (statusH) statusH.value = 'draft';
                const form = document.getElementById('page-form');
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'redirect_to_builder'; input.value = '1';
                form?.appendChild(input);
                this.innerText = 'Saving & Starting Builder...';
                form?.submit();
            });

            // Language selector logic
            const langSelect = document.querySelector('select[name="lang_code"]');
            if (langSelect) {
                const updateCloneList = () => {
                    const selectedLang = langSelect.value;
                    
                    // Update Permalink Base Display
                    const siteUrl = "{{ url('/') }}";
                    const newBase = selectedLang === 'en' ? siteUrl + '/' : siteUrl + '/' + selectedLang + '/';
                    const baseDisplay = document.getElementById('permalink-base-display');
                    const baseEdit = document.getElementById('permalink-base-edit');
                    if (baseDisplay) baseDisplay.innerText = newBase;
                    if (baseEdit) baseEdit.innerText = newBase;

                    document.querySelectorAll('#multi-lang-list label').forEach(label => {
                        if (label.classList.contains(`lang-option-${selectedLang}`)) {
                            label.classList.add('hidden');
                            label.querySelector('input').checked = false;
                        } else {
                            label.classList.remove('hidden');
                            label.querySelector('input').checked = true;
                        }
                    });
                };
                langSelect.addEventListener('change', updateCloneList);
                updateCloneList();
            }
        });
    </script>
</x-falcon-cms::layouts.admin>
