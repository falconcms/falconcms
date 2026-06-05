<x-cms-dashboard::layouts.admin title="Add New {{ ucfirst($type) }}" active-menu="{{ $type === 'page' ? 'pages' : ($type ?: 'posts') }}">
    
    <div class="mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327] inline-block mr-3">Add New {{ ucfirst($type) }}</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif


    <form action="{{ route('admin.posts.store') }}" method="POST" id="post-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="status" id="status-hidden" value="published">
        
        <div class="flex flex-col lg:flex-row gap-5">
            <!-- Left Column: Content -->
            <div class="flex-grow min-w-0">
                @if(in_array('title', $supports))
                <div class="mb-4">
                    <input type="text" name="title" id="title-input" value="{{ old('title') }}" 
                           class="w-full text-[1.7em] leading-normal border @error('title') border-[#d63638] @else border-[#8c8f94] @enderror rounded-sm py-[3px] px-[8px] focus:ring-[#2271b1] focus:border-[#2271b1] shadow-none m-0 bg-white" 
                           placeholder="Add title">
                    @error('title')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                    
                    @if(!isset($postType) || $postType->is_public)
                    <div id="permalink-container" class="mt-2 text-[13px] {{ old('title') ? 'flex' : 'hidden' }} items-center font-medium">
                        <span class="text-[#646970] mr-1">Permalink:</span>
                        <span id="permalink-view">
                            @php 
                                $isMultiLang = get_cms_option('multi_language_enabled', 0);
                                $selectedLang = request('lang', get_cms_option('default_language', 'en'));
                                
                                $baseUrl = url('/');
                                if ($isMultiLang && $selectedLang !== 'en') {
                                    $baseUrl = url($selectedLang);
                                }
                                $baseUrl = rtrim($baseUrl, '/') . '/';
                                
                                if($type !== 'page') $baseUrl .= $type . '/';
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
                    @endif
                </div>
                @else
                <input type="hidden" name="title" value="Draft {{ time() }}">
                @endif


                @if(in_array('editor', $supports))
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
                @endif

                @if(in_array('excerpt', $supports) && $type !== 'product')
                <!-- Excerpt (products use the dedicated Product Short Description field instead) -->
                <div class="wp-metabox mt-6 mb-6">
                    <div class="wp-metabox-header"><span>Excerpt</span></div>
                    <div class="wp-metabox-content">
                        <textarea name="excerpt" id="excerpt" rows="3" class="w-full text-[14px] leading-normal border @error('excerpt') border-[#d63638] @else border-[#8c8f94] @enderror rounded-sm py-[3px] px-[8px] focus:ring-[#2271b1] focus:border-[#2271b1] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] m-0 bg-white">{{ old('excerpt') }}</textarea>
                        @error('excerpt')
                            <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-[12px] text-[#646970] mt-2">Excerpts are optional hand-crafted summaries of your content that can be used in your theme.</p>
                    </div>
                </div>
                @endif

                <!-- Custom Fields Groups -->
                @if(isset($fieldGroups) && $fieldGroups->isNotEmpty())
                    @foreach($fieldGroups as $group)
                    <div class="wp-metabox mt-6 mb-6">
                        <div class="wp-metabox-header"><span>{{ $group->title }}</span></div>
                        <div class="wp-metabox-content" style="padding: 16px;">
                            <div class="space-y-6">
                                @foreach($group->fields as $field)
                                <div class="field-row">
                                    <label class="block text-[13px] font-bold text-[#1d2327] mb-1">
                                        {{ $field->label }}
                                        @if($field->required)
                                            <span class="text-[#d63638]">*</span>
                                        @endif
                                    </label>
                                    @if($field->instructions)
                                        <p class="text-[12px] text-[#646970] mb-2 italic">{{ $field->instructions }}</p>
                                    @endif

                                    @if($field->type === 'text')
                                        <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ old('custom_fields.'.$field->id) }}" class="wp-input w-full" placeholder="" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->type === 'textarea')
                                        <textarea name="custom_fields[{{ $field->id }}]" rows="4" class="wp-input w-full" {{ $field->required ? 'required' : '' }}>{{ old('custom_fields.'.$field->id) }}</textarea>
                                    @elseif($field->type === 'select')
                                        @php $selectOpts = array_filter(array_map('trim', explode("\n", $field->params['options'] ?? ''))); @endphp
                                        <select name="custom_fields[{{ $field->id }}]" class="wp-input w-full h-8 py-0" {{ $field->required ? 'required' : '' }}>
                                            <option value="">— Select —</option>
                                            @foreach($selectOpts as $opt)
                                                <option value="{{ $opt }}" {{ old('custom_fields.'.$field->id) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field->type === 'checkbox')
                                        @php
                                            $cbOpts = array_filter(array_map('trim', explode("\n", $field->params['options'] ?? '')));
                                            $cbOld  = (array) old('custom_fields.'.$field->id, []);
                                        @endphp
                                        <div class="space-y-2">
                                            @foreach($cbOpts as $opt)
                                                <label class="flex items-center gap-2 text-[13px] text-[#2c3338] cursor-pointer">
                                                    <input type="checkbox" name="custom_fields[{{ $field->id }}][]" value="{{ $opt }}" {{ in_array($opt, $cbOld) ? 'checked' : '' }} class="rounded border-[#c3c4c7]">
                                                    {{ $opt }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($field->type === 'radio')
                                        @php $radioOpts = array_filter(array_map('trim', explode("\n", $field->params['options'] ?? ''))); @endphp
                                        <div class="space-y-2">
                                            @foreach($radioOpts as $opt)
                                                <label class="flex items-center gap-2 text-[13px] text-[#2c3338] cursor-pointer">
                                                    <input type="radio" name="custom_fields[{{ $field->id }}]" value="{{ $opt }}" {{ old('custom_fields.'.$field->id) === $opt ? 'checked' : '' }}>
                                                    {{ $opt }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($field->type === 'repeater')
                                        @php $subFields = $field->params['sub_fields'] ?? []; @endphp
                                        @if(count($subFields) > 0)
                                        <div class="repeater-field" data-field-id="{{ $field->id }}">
                                            <div class="rep-rows space-y-3">
                                                <div class="rep-row border border-[#c3c4c7] rounded-sm p-3 relative bg-[#f9f9f9]">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                        @foreach($subFields as $sf)
                                                        <div>
                                                            <label class="block text-[12px] font-bold text-[#1d2327] mb-1">{{ $sf['label'] }}</label>
                                                            @if(($sf['type'] ?? 'text') === 'textarea')
                                                                <textarea name="custom_fields[{{ $field->id }}][0][{{ $sf['name'] }}]" rows="2" class="wp-input w-full text-[13px]"></textarea>
                                                            @elseif($sf['type'] === 'image')
                                                                <div class="flex items-center gap-2">
                                                                    <div class="w-14 h-14 bg-[#f0f0f1] border border-[#c3c4c7] rounded-sm flex items-center justify-center overflow-hidden shrink-0 cf-img-preview">
                                                                        <svg class="w-6 h-6 text-[#c3c4c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                                    </div>
                                                                    <button type="button" class="wp-btn-secondary h-8 px-3 text-[12px] cf-img-btn">Choose Image</button>
                                                                    <input type="hidden" name="custom_fields[{{ $field->id }}][0][{{ $sf['name'] }}]" value="">
                                                                </div>
                                                            @else
                                                                <input type="text" name="custom_fields[{{ $field->id }}][0][{{ $sf['name'] }}]" class="wp-input w-full text-[13px]">
                                                            @endif
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                    <button type="button" onclick="removeRepRow(this)" class="absolute top-2 right-2 text-[11px] text-[#d63638] hover:underline">Remove</button>
                                                </div>
                                            </div>
                                            <button type="button" onclick="addRepRow(this, {{ $field->id }}, {{ json_encode($subFields) }})" class="mt-3 text-[12px] text-[#2271b1] hover:underline font-medium">+ Add Row</button>
                                        </div>
                                        @endif
                                    @elseif($field->type === 'wysiwyg')
                                        <textarea name="custom_fields[{{ $field->id }}]" class="wp-input w-full h-32" {{ $field->required ? 'required' : '' }}>{{ old('custom_fields.'.$field->id) }}</textarea>
                                    @elseif($field->type === 'image')
                                        <div class="flex items-center gap-4">
                                            <div class="w-20 h-20 bg-[#f0f0f1] border border-[#c3c4c7] rounded-sm flex items-center justify-center overflow-hidden cf-img-preview">
                                                <svg class="w-8 h-8 text-[#c3c4c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <button type="button" class="wp-btn-secondary h-8 px-4 text-[12px] cf-img-btn">Choose Image</button>
                                            <input type="hidden" name="custom_fields[{{ $field->id }}]" value="{{ old('custom_fields.'.$field->id) }}" {{ $field->required ? 'required' : '' }}>
                                        </div>
                                    @else
                                        <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ old('custom_fields.'.$field->id) }}" class="wp-input w-full" {{ $field->required ? 'required' : '' }}>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif

                @include('cms-dashboard::admin.posts.partials.product-data', ['post' => $post, 'type' => $type])
                @include('cms-dashboard::admin.posts.partials.seo', ['post' => new \Acme\CmsDashboard\Models\Post()])
            </div>

            <!-- Right Column: Metaboxes -->
            <div class="w-full lg:w-[280px] shrink-0 space-y-5">
                
                <!-- Multilingual Metabox -->
                @php 
                    $isMultiLang = get_cms_option('multi_language_enabled', 0);
                    $activeLanguages = \Acme\CmsDashboard\Models\Language::where('status', true)->get(); 
                @endphp

                @if($activeLanguages->count() > 1)
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header"><span>Language</span></div>
                    <div class="wp-metabox-content p-3">
                        <div class="mb-3">
                            <label class="block text-[12px] font-bold text-[#1d2327] mb-1">Post Language</label>
                            <select name="lang_code" class="wp-input w-full text-[13px] h-8 py-0">
                                @foreach($activeLanguages as $lang)
                                    <option value="{{ $lang->code }}" {{ $lang->is_default ? 'selected' : '' }}>
                                        {{ $lang->flag }} {{ $lang->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">This is the language of the content you are currently writing.</p>
                        </div>

                        <hr class="my-3 border-gray-100">
                        <label class="flex items-center text-[13px] font-bold text-[#1d2327] mb-3 cursor-pointer">
                            <input type="checkbox" name="make_multilingual_copy" value="1" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]" onchange="document.getElementById('multi-lang-list').classList.toggle('hidden', !this.checked)">
                            Make a copy for other languages?
                        </label>

                        <div id="multi-lang-list" class="hidden space-y-2 pl-6 border-l-2 border-gray-100">
                            <p class="text-[11px] text-gray-500 mb-2">Select languages to clone this post to:</p>
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
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
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
                <!-- Categories Metabox -->
                @if($type === 'post' && !in_array('categories', $overriddenTaxonomies))
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
                        <span>Categories</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    </div>
                    <div class="wp-metabox-content" style="padding: 10px;">
                        <div class="h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-white">
                            @php $allCategories = \Acme\CmsDashboard\Models\Category::orderBy('name')->get(); @endphp
                            @forelse($allCategories as $category)
                                <label class="flex items-center text-[13px] text-[#2c3338] mb-1">
                                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" {{ in_array($category->id, old('categories', [])) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                    {{ $category->name }}
                                </label>
                            @empty
                                <p class="text-[12px] text-[#646970] italic no-terms-msg">No categories found.</p>
                            @endforelse
                        </div>
                        <a href="#" class="text-[#2271b1] text-[13px] underline toggle-quick-add">+ Add New Category</a>
                        <div class="quick-add-term-box hidden mt-3 space-y-2 pt-3 border-t border-[#f0f0f1]">
                            <input type="text" class="wp-input w-full text-[13px] h-8 new-term-name" placeholder="Category Name">
                            <select class="wp-input w-full text-[13px] h-8 py-0 new-term-parent">
                                <option value="">— Parent Category —</option>
                                @foreach($allCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="wp-btn-secondary text-[12px] h-[30px] w-full mt-2 add-term-ajax-btn" data-taxonomy="categories" data-cpt="{{ $type }}" data-is-builtin="true">Add New Category</button>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Dynamic Taxonomies Metaboxes -->
                @if(!empty($assignedTaxonomies))
                    @foreach($assignedTaxonomies as $taxonomy)
                    @php 
                        $isHierarchical = (bool) $taxonomy->hierarchical;
                    @endphp
                    <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                        <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
                            <span>{{ $taxonomy->name }}</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                        </div>
                        <div class="wp-metabox-content" style="padding: 10px;">
                            @if(!$isHierarchical)
                                <!-- Tag Style UI for CPT -->
                                <div class="p-1">
                                    <div class="flex gap-2">
                                        <input type="text" class="wp-input flex-grow text-[13px] h-8 cpt-tag-input" 
                                               data-taxonomy="{{ $taxonomy->slug }}" 
                                               data-cpt="{{ $type }}" placeholder="">
                                        <button type="button" class="wp-btn-secondary h-8 px-4 text-[13px] add-cpt-tag-btn">Add</button>
                                    </div>
                                    <p class="text-[11px] text-[#646970] mt-1 italic">Separate {{ strtolower($taxonomy->name) }} with commas</p>
                                    
                                    <div class="cpt-tags-container mt-3 flex flex-wrap gap-2" data-taxonomy="{{ $taxonomy->slug }}">
                                        <!-- Bubbles will appear here -->
                                    </div>
                                    <!-- Hidden inputs for actual IDs -->
                                    <div class="cpt-tags-hidden-inputs hidden" data-taxonomy="{{ $taxonomy->slug }}">
                                        @foreach($taxonomy->terms as $term)
                                            {{-- These would be pre-checked if it was edit --}}
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <!-- Category style UI (Checklist) -->
                                <div class="h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-white">
                                    @forelse($taxonomy->terms as $term)
                                        <label class="flex items-center text-[13px] text-[#2c3338] mb-1">
                                            @if(isset($taxonomy->is_builtin) && $taxonomy->is_builtin)
                                                <input type="checkbox" name="categories[]" value="{{ $term->id }}" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                            @else
                                                <input type="checkbox" name="tax_terms[]" value="{{ $term->id }}" {{ in_array($term->id, old('tax_terms', [])) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                            @endif
                                            {{ $term->name }}
                                        </label>
                                    @empty
                                        <p class="text-[12px] text-[#646970] italic no-terms-msg">No {{ strtolower($taxonomy->name) }} found.</p>
                                    @endforelse
                                </div>

                                <a href="#" class="text-[#2271b1] text-[13px] underline toggle-quick-add">+ Add New {{ $taxonomy->singular_name ?? $taxonomy->name }}</a>
                                
                                <div class="quick-add-term-box hidden mt-3 space-y-2 pt-3 border-t border-[#f0f0f1]">
                                    <input type="text" class="wp-input w-full text-[13px] h-8 new-term-name" placeholder="{{ $taxonomy->singular_name ?? $taxonomy->name }} Name">
                                    <select class="wp-input w-full text-[13px] h-8 py-0 new-term-parent">
                                        <option value="">— Parent {{ $taxonomy->singular_name ?? $taxonomy->name }} —</option>
                                        @foreach($taxonomy->terms as $term)
                                            <option value="{{ $term->id }}">{{ $term->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" 
                                            class="wp-btn-secondary text-[12px] h-[30px] w-full mt-2 add-term-ajax-btn"
                                            data-taxonomy="{{ $taxonomy->slug }}"
                                            data-cpt="{{ $type }}">
                                        Add New {{ $taxonomy->singular_name ?? $taxonomy->name }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @endif

                <!-- Standard Tags Metabox -->
                @if($type === 'post' && !in_array('tags', $overriddenTaxonomies))
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
                        <span>Tags</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    </div>
                    <div class="wp-metabox-content p-3">
                        <div class="flex gap-2">
                            <input type="text" id="tag-input" class="wp-input flex-grow text-[13px] h-8" placeholder="">
                            <button type="button" id="add-tag-btn" class="wp-btn-secondary h-8 px-4 text-[13px]">Add</button>
                        </div>
                        <p class="text-[11px] text-[#646970] mt-1 italic">Separate tags with commas</p>
                        
                        <div id="tags-container" class="mt-3 flex flex-wrap gap-2"></div>
                        <input type="hidden" name="tags" id="tags-hidden-input" value="{{ old('tags') }}">

                        {{-- Removed: Choose from most used tags --}}
                    </div>
                </div>
                @endif

                @if($type === 'product')
                    @include('cms-dashboard::admin.posts.partials.product-taxonomies')
                @endif


                @if(in_array('featured_image', $supports) || in_array('thumbnail', $supports))
                <!-- Featured Image -->
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer"><span>Featured image</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div>
                    <div class="wp-metabox-content">
                        <div id="fi-preview-container" class="{{ old('featured_image') ? '' : 'hidden' }} mb-3">
                            <img id="fi-preview" src="{{ old('featured_image') ? asset('storage/'.old('featured_image')) : '' }}" class="max-w-full h-auto border border-gray-200 p-1 bg-white cursor-pointer">
                        </div>
                        <a href="#" id="set-fi-btn" class="text-[#2271b1] text-[13px] underline {{ old('featured_image') ? 'hidden' : '' }}">Set featured image</a>
                        <a href="#" id="remove-fi-btn" class="text-[#b32d2e] text-[13px] underline {{ old('featured_image') ? '' : 'hidden' }} mt-2">Remove featured image</a>
                        <input type="hidden" name="featured_image" id="fi-path-hidden" value="{{ old('featured_image') }}">
                    </div>
                </div>

                @if($type === 'product')
                <!-- Gallery -->
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer"><span>Gallery</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div>
                    <div class="wp-metabox-content">
                        <div id="gallery-container" class="grid grid-cols-3 gap-2 mb-3">
                            {{-- Pre-populated if any --}}
                        </div>
                        <a href="#" id="add-gallery-btn" class="text-[#2271b1] text-[13px] underline">+ Add images to gallery</a>
                    </div>
                </div>
                @endif
                @endif

            </div>
        </div>
    </form>

    <script src="{{ asset('vendor/cms-dashboard/js/tinymce.min.js') }}"></script>
    <script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>
    <script>
        tinymce.init({
            selector: '#wp-editor',
            menubar: false,
            height: 450,
            width: '100%',
            plugins: ['lists', 'link', 'image', 'preview', 'code', 'fullscreen', 'media', 'table', 'wordcount'],
            toolbar: 'formatselect | bold italic underline strikethrough | blockquote | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | code fullscreen',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size:14px; padding: 20px; }',
            branding: false,
            image_title: true,
            automatic_uploads: true,
            file_picker_types: 'image',
            file_picker_callback: function (cb, value, meta) {
                window.openMediaModal(function(media) {
                   cb(`/storage/${media.path}`, { title: media.title, alt: media.alt_text });
                });
            }
        });

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

        if (permalinkContainer && titleInput && slugInput) {
            titleInput.addEventListener('blur', function() {
                if (this.value) {
                    let newSlug = generateSlug(this.value);
                    if (!slugInput.value) {
                        slugInput.value = newSlug;
                        if (slugDisplay) slugDisplay.innerText = newSlug;
                        originalSlug = newSlug;
                    }
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
                if (slugDisplay) slugDisplay.innerText = newSlug;
                originalSlug = newSlug;
                viewSpan?.classList.remove('hidden');
                editSpan?.classList.add('hidden');
            });

            document.getElementById('cancel-slug-btn')?.addEventListener('click', (e) => { 
                e.preventDefault(); 
                slugInput.value = originalSlug; 
                viewSpan?.classList.remove('hidden'); 
                editSpan?.classList.add('hidden'); 
            });
        }

        // Save Draft Logic Override
        document.getElementById('save-draft-btn')?.addEventListener('click', function() {
            if (statusHidden) statusHidden.value = 'draft';
            document.getElementById('post-form')?.submit();
        });

        // Featured Image UI with Modal
        const setFiBtn = document.getElementById('set-fi-btn');
        const removeFiBtn = document.getElementById('remove-fi-btn');
        const fiPreview = document.getElementById('fi-preview');
        const fiPreviewContainer = document.getElementById('fi-preview-container');
        const fiPathHidden = document.getElementById('fi-path-hidden');

        setFiBtn?.addEventListener('click', (e) => { 
            e.preventDefault(); 
            window.openMediaModal(function(media) {
                if (fiPathHidden) fiPathHidden.value = media.path;
                if (fiPreview) fiPreview.src = `/storage/${media.path}`;
                fiPreviewContainer?.classList.remove('hidden');
                setFiBtn?.classList.add('hidden');
                removeFiBtn?.classList.remove('hidden');
            });
        });

        fiPreview?.addEventListener('click', (e) => { 
            e.preventDefault(); 
            setFiBtn?.click();
        });

        removeFiBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (fiPathHidden) fiPathHidden.value = '';
            fiPreviewContainer?.classList.add('hidden');
            setFiBtn?.classList.remove('hidden');
            removeFiBtn?.classList.add('hidden');
        });

        // Gallery Logic
        const addGalleryBtn = document.getElementById('add-gallery-btn');
        const galleryContainer = document.getElementById('gallery-container');

        addGalleryBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            window.openMediaModal(function(mediaItems) {
                const items = Array.isArray(mediaItems) ? mediaItems : [mediaItems];
                
                items.forEach(media => {
                    // Check if already in gallery
                    const existing = galleryContainer.querySelector(`.gallery-item[data-path="${media.path}"]`);
                    if (existing) return;

                    const item = document.createElement('div');
                    item.className = "gallery-item relative group aspect-square border border-gray-200 p-1 bg-white cursor-pointer";
                    item.setAttribute('data-path', media.path);
                    item.innerHTML = `
                        <img src="/storage/${media.path}" class="w-full h-full object-cover">
                        <button type="button" class="absolute top-0 right-0 bg-red-600 text-white w-5 h-5 flex items-center justify-center text-[10px] opacity-0 group-hover:opacity-100 transition-opacity remove-gallery-img">×</button>
                        <input type="hidden" name="gallery[]" value="${media.path}">
                    `;
                    galleryContainer.appendChild(item);
                });
            }, { multiple: true });
        });

        galleryContainer?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-gallery-img')) {
                e.target.closest('.gallery-item').remove();
            }
        });


        // Publish Metabox Logic
        document.querySelectorAll('.toggle-publish-edit').forEach(el => {
            el.addEventListener('click', function(e) { e.preventDefault(); document.getElementById(this.getAttribute('data-target')).classList.toggle('hidden'); });
        });

        document.getElementById('ok-status-btn')?.addEventListener('click', function() {
            let select = document.getElementById('status-select-ui');
            let val = select.value;
            document.getElementById('status-display-text').innerText = select.options[select.selectedIndex].text;
            statusHidden.value = val;
            document.getElementById('status-edit').classList.add('hidden');
        });

        document.getElementById('ok-visibility-btn')?.addEventListener('click', function() {
            let selected = document.querySelector('input[name="visibility"]:checked').value;
            document.getElementById('visibility-display-text').innerText = selected;
            document.getElementById('visibility-edit').classList.add('hidden');
        });

        document.getElementById('ok-publish-btn')?.addEventListener('click', function() {
            let mm = document.getElementById('pub-mm').value;
            let dd = document.getElementById('pub-dd').value;
            let yy = document.getElementById('pub-yy').value;
            let hr = document.getElementById('pub-hr').value;
            let min = document.getElementById('pub-min').value;
            
            let selDate = new Date(`${yy}-${mm}-${dd}T${hr}:${min}:00`);
            // Compare against the CMS timezone "now" (Settings → General), not the browser clock.
            let now = new Date("{{ now(cms_timezone())->format('Y-m-d\TH:i:s') }}");
            let isFuture = selDate > now;
            
            let monthName = document.getElementById('pub-mm').options[document.getElementById('pub-mm').selectedIndex].text;
            document.getElementById('publish-display-prefix').innerText = isFuture ? 'Scheduled for' : 'Publish';
            document.getElementById('publish-display-text').innerText = isFuture ? `${monthName} ${dd}, ${yy} @ ${hr}:${min}` : 'immediately';
            
            if (isFuture) {
                document.getElementById('main-publish-btn').innerText = 'Schedule';
                statusHidden.value = 'scheduled';
                document.getElementById('status-display-text').innerText = 'Scheduled';
                // Update select UI too
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
        // Combined Click Events Delegation
        document.addEventListener('click', function(e) {
            // 1. Toggle Quick Add Category Box
            const toggleBtn = e.target.closest('.toggle-quick-add');
            if (toggleBtn) {
                e.preventDefault();
                const metabox = toggleBtn.closest('.wp-metabox-content');
                if (metabox) {
                    const box = metabox.querySelector('.quick-add-term-box');
                    if (box) box.classList.toggle('hidden');
                }
                return;
            }

            // 2. Add Category/Term AJAX
            const addTermBtn = e.target.closest('.add-term-ajax-btn');
            if (addTermBtn) {
                e.preventDefault();
                addTermAjax(addTermBtn);
                return;
            }

            // 3. Add CPT Tag
            const addTagBtn = e.target.closest('.add-cpt-tag-btn');
            if (addTagBtn) {
                e.preventDefault();
                const metabox = addTagBtn.closest('.wp-metabox-content');
                const input = metabox.querySelector('.cpt-tag-input');
                addCPTTag(input);
                return;
            }

            // 4. Remove CPT Tag
            const removeTagBtn = e.target.closest('.remove-cpt-tag');
            if (removeTagBtn) {
                e.preventDefault();
                const bubble = removeTagBtn.closest('span');
                const id = removeTagBtn.getAttribute('data-id');
                const metabox = removeTagBtn.closest('.wp-metabox-content');
                if (id) {
                    const hiddenInput = metabox.querySelector(`.cpt-tags-hidden-inputs input[value="${id}"]`);
                    if (hiddenInput) hiddenInput.remove();
                }
                bubble.remove();
                return;
            }
        });

        async function addTermAjax(btn) {
            const metabox = btn.closest('.wp-metabox-content');
            const nameInput = metabox.querySelector('.new-term-name');
            const parentSelect = metabox.querySelector('.new-term-parent');
            const name = nameInput.value;
            const parentId = (parentSelect && parentSelect.value !== '') ? parentSelect.value : null;
            const taxonomy = btn.getAttribute('data-taxonomy');
            const cpt = btn.getAttribute('data-cpt');

            if (!name) return window.showToast('Please enter a name', 'warning');

            btn.disabled = true;
            btn.innerText = 'Adding...';

            try {
                const isBuiltin = btn.getAttribute('data-is-builtin') === 'true';
                let url = isBuiltin ? "{{ route('admin.categories.ajax') }}" : "{{ route('admin.acpt.terms.ajax') }}";

                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ name: name, parent_id: parentId, taxonomy_slug: taxonomy, cpt_slug: cpt })
                });

                const data = await response.json();
                if (response.ok && data.id) {
                    const checklist = metabox.querySelector('.h-36') || metabox.querySelector('.h-44');
                    const noMsg = checklist.querySelector('.no-terms-msg');
                    if (noMsg) noMsg.remove();

                    const label = document.createElement('label');
                    label.className = "flex items-center text-[13px] text-[#2c3338] mb-1";
                    const inputName = isBuiltin ? 'categories[]' : 'tax_terms[]';
                    let displayName = data.name;
                    if (parentId) displayName = '— ' + data.name;
                    
                    label.innerHTML = `<input type="checkbox" name="${inputName}" value="${data.id}" checked class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> ${displayName}`;
                    checklist.prepend(label);
                    
                    if (parentSelect) {
                        const option = document.createElement('option');
                        option.value = data.id;
                        option.text = data.name;
                        parentSelect.appendChild(option);
                    }
                    nameInput.value = '';
                    metabox.querySelector('.quick-add-term-box').classList.add('hidden');
                    window.showToast('Added successfully');
                } else {
                    window.showToast(data.message || 'Error adding item', 'error');
                }
            } catch (error) {
                window.showToast('Error adding item', 'error');
                console.error(error);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Add New';
            }
        }

        // CPT Tags Logic
        async function addCPTTag(inputEl) {
            const val = inputEl.value.trim();
            if (!val) return;

            const taxonomy = inputEl.getAttribute('data-taxonomy');
            const cpt = inputEl.getAttribute('data-cpt');
            const metabox = inputEl.closest('.wp-metabox-content');
            const container = metabox.querySelector('.cpt-tags-container');
            const hiddenInputs = metabox.querySelector('.cpt-tags-hidden-inputs');

            const tags = val.split(',').map(t => t.trim()).filter(t => t);
            
            for (const tagName of tags) {
                // Check if already added in current UI
                const existing = Array.from(container.querySelectorAll('span')).some(s => s.innerText.trim().replace('×', '').trim() === tagName);
                if (existing) continue;

                try {
                    const response = await fetch("{{ route('admin.acpt.terms.ajax') }}", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ name: tagName, taxonomy_slug: taxonomy, cpt_slug: cpt })
                    });
                    const data = await response.json();
                    if (data.id) {
                        // Append Bubble
                        const bubble = document.createElement('span');
                        bubble.className = "inline-flex items-center bg-[#f0f0f1] text-[#2c3338] text-[12px] px-2 py-0.5 rounded-sm border border-[#dfdfdf]";
                        bubble.innerHTML = `${data.name} <button type="button" class="ml-1 text-[#b32d2e] font-bold remove-cpt-tag" data-id="${data.id}">×</button>`;
                        container.appendChild(bubble);

                        // Append Hidden Input
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'tax_terms[]';
                        input.value = data.id;
                        hiddenInputs.appendChild(input);
                    } else {
                        if (data.errors) {
                            let errorMsgs = Object.values(data.errors).flat().join(' ');
                            window.showToast(errorMsgs, 'error');
                        } else {
                            window.showToast(data.message || 'Error adding tag', 'error');
                        }
                    }
                } catch (e) { 
                    window.showToast('Error adding tag', 'error');
                    console.error(e); 
                }
            }
            inputEl.value = '';
        }

        // CPT Tags: Keydown listener removed as per request (only Add button allowed)


        // Standard Tags Logic
        const tagInput = document.getElementById('tag-input');
        const tagsContainer = document.getElementById('tags-container');
        const tagsHidden = document.getElementById('tags-hidden-input');
        const addTagBtn = document.getElementById('add-tag-btn');
        let tags = [];

        function renderTags() {
            if (!tagsContainer || !tagsHidden) return;
            tagsContainer.innerHTML = '';
            tags.forEach((tag, index) => {
                const bubble = document.createElement('span');
                bubble.className = "inline-flex items-center bg-[#f0f0f1] text-[#2c3338] text-[12px] px-2 py-0.5 rounded-sm border border-[#dfdfdf]";
                bubble.innerHTML = `${tag} <button type="button" class="ml-1 text-[#b32d2e] font-bold hover:text-red-700" onclick="removeTag(${index})">×</button>`;
                tagsContainer.appendChild(bubble);
            });
            tagsHidden.value = tags.join(',');
        }

        window.removeTag = function(index) {
            tags.splice(index, 1);
            renderTags();
        };

        function addTagsFromInput() {
            if (!tagInput) return;
            const val = tagInput.value.trim();
            if (!val) return;
            const newTags = val.split(',').map(t => t.trim()).filter(t => t && !tags.includes(t));
            tags = [...tags, ...newTags];
            tagInput.value = '';
            renderTags();
        }

        addTagBtn?.addEventListener('click', addTagsFromInput);
        // Standard Tags: Keydown listener removed as per request (only Add button allowed)
        if (tagsHidden && tagsHidden.value) {
            tags = tagsHidden.value.split(',').filter(t => t.trim() !== '');
            renderTags();
        }

        // Editor Toggle Logic
        const richEditorBtn = document.getElementById('editor-mode-rich');
        const builderEditorBtn = document.getElementById('editor-mode-builder');
        const richEditorContainer = document.getElementById('rich-editor-container');
        const builderPlaceholder = document.getElementById('page-builder-placeholder');
        const editorTypeHidden = document.getElementById('editor_type');

        function switchEditorMode(mode) {
            if (!richEditorBtn || !builderEditorBtn || !richEditorContainer || !builderPlaceholder) return;

            if (mode === 'builder') {
                richEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-transparent border-b-0 text-[#2271b1] hover:text-[#0a4b78] rounded-t-sm";
                builderEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-[#dcdcde] border-b-0 bg-white text-[#2c3338] rounded-t-sm shadow-[0_1px_0_#fff]";
                richEditorContainer.classList.add('hidden');
                builderPlaceholder.classList.remove('hidden');
                if (editorTypeHidden) editorTypeHidden.value = 'builder';
            } else {
                builderEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-transparent border-b-0 text-[#2271b1] hover:text-[#0a4b78] rounded-t-sm";
                richEditorBtn.className = "px-4 py-2 text-[13px] font-semibold border border-[#dcdcde] border-b-0 bg-white text-[#2c3338] rounded-t-sm shadow-[0_1px_0_#fff]";
                builderPlaceholder.classList.add('hidden');
                richEditorContainer.classList.remove('hidden');
                if (editorTypeHidden) editorTypeHidden.value = 'rich';
            }
        }

        richEditorBtn?.addEventListener('click', () => switchEditorMode('rich'));
        builderEditorBtn?.addEventListener('click', () => switchEditorMode('builder'));

        // Initialize based on old value or default
        if (editorTypeHidden) {
            switchEditorMode(editorTypeHidden.value || 'rich');
        }

        // Start Builder Button
        document.getElementById('start-builder-btn')?.addEventListener('click', function() {
            if (!validatePostForm()) return;

            // Force status to draft if it's a new post so we don't accidentally publish an empty page
            if (document.getElementById('status-hidden').value !== 'published') {
                document.getElementById('status-hidden').value = 'draft';
            } else {
                // If it was already set to publish, let it be, but ideally a new page shouldn't be published empty via builder start.
                document.getElementById('status-hidden').value = 'draft';
            }
            
            // Set a flag to redirect to builder after saving (can be handled by controller if implemented)
            const form = document.getElementById('post-form');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'redirect_to_builder';
            input.value = '1';
            form.appendChild(input);

            const editorTypeInput = document.getElementById('editor_type');
            if (editorTypeInput) editorTypeInput.value = 'builder';

            // Give the button a loading state
            this.innerText = 'Saving & Starting Builder...';
            this.classList.add('opacity-70', 'cursor-not-allowed');

            form.submit();
        });

        // Language selector logic to hide current lang from clone list
        const langSelect = document.querySelector('select[name="lang_code"]');
        if (langSelect) {
            const updateCloneList = () => {
                const selectedLang = langSelect.value;
                
                // Update Permalink Base Display
                const siteUrl = "{{ url('/') }}";
                const postType = "{{ $type }}";
                let newBase = selectedLang === 'en' ? siteUrl + '/' : siteUrl + '/' + selectedLang + '/';
                if(postType !== 'page') newBase += postType + '/';

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
            updateCloneList(); // Initial run
        }

        // --- Client Side Validation ---
        function validatePostForm(e) {
            let isValid = true;
            const titleInput = document.getElementById('title-input');
            const postType = "{{ $type ?? 'post' }}";
            
            // Clear previous errors
            titleInput.classList.remove('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
            
            if (!titleInput.value.trim()) {
                isValid = false;
                titleInput.classList.add('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
                titleInput.focus();
                window.showToast('Please enter a title before saving.', 'error');
            }
            
            // Product Specific Validation
            if (isValid && postType === 'product') {
                const priceInput = document.getElementById('regular_price');
                const salePriceInput = document.getElementById('sale_price');
                
                if (priceInput) priceInput.classList.remove('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
                if (salePriceInput) salePriceInput.classList.remove('border-[#d63638]', 'ring-1', 'ring-[#d63638]');

                if (priceInput && !priceInput.value.trim()) {
                    isValid = false;
                    priceInput.classList.add('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
                    priceInput.focus();
                    window.showToast('Please enter a regular price for the product.', 'error');
                } else if (salePriceInput && salePriceInput.value.trim()) {
                    const price = parseFloat(priceInput.value);
                    const salePrice = parseFloat(salePriceInput.value);
                    
                    if (isNaN(salePrice)) {
                        isValid = false;
                        salePriceInput.classList.add('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
                        window.showToast('Sale price must be a number.', 'error');
                    } else if (salePrice >= price) {
                        isValid = false;
                        salePriceInput.classList.add('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
                        salePriceInput.focus();
                        window.showToast('Sale price must be less than the regular price.', 'error');
                    }
                }
            }

            if (!isValid && e) {
                e.preventDefault();
            }
            return isValid;
        }

        document.getElementById('post-form')?.addEventListener('submit', function(e) {
            if (!validatePostForm(e)) {
                return false;
            }
        });

        function addRepRow(btn, fieldId, subFields) {
            const container = btn.closest('.repeater-field');
            const rowsContainer = container.querySelector('.rep-rows');
            const rowIndex = rowsContainer.querySelectorAll('.rep-row').length;
            let html = '<div class="rep-row border border-[#c3c4c7] rounded-sm p-3 relative bg-[#f9f9f9]"><div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
            subFields.forEach(sf => {
                html += `<div><label class="block text-[12px] font-bold text-[#1d2327] mb-1">${sf.label}</label>`;
                if (sf.type === 'textarea') {
                    html += `<textarea name="custom_fields[${fieldId}][${rowIndex}][${sf.name}]" rows="2" class="wp-input w-full text-[13px]"></textarea>`;
                } else if (sf.type === 'image') {
                    html += `<div class="flex items-center gap-2">
                        <div class="w-14 h-14 bg-[#f0f0f1] border border-[#c3c4c7] rounded-sm flex items-center justify-center overflow-hidden shrink-0 cf-img-preview">
                            <svg class="w-6 h-6 text-[#c3c4c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <button type="button" class="wp-btn-secondary h-8 px-3 text-[12px] cf-img-btn">Choose Image</button>
                        <input type="hidden" name="custom_fields[${fieldId}][${rowIndex}][${sf.name}]" value="">
                    </div>`;
                } else {
                    html += `<input type="text" name="custom_fields[${fieldId}][${rowIndex}][${sf.name}]" class="wp-input w-full text-[13px]">`;
                }
                html += '</div>';
            });
            html += '</div><button type="button" onclick="removeRepRow(this)" class="absolute top-2 right-2 text-[11px] text-[#d63638] hover:underline">Remove</button></div>';
            rowsContainer.insertAdjacentHTML('beforeend', html);
        }

        function removeRepRow(btn) {
            const row = btn.closest('.rep-row');
            const container = row.closest('.rep-rows');
            if (container.querySelectorAll('.rep-row').length > 1) row.remove();
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.cf-img-btn');
            if (!btn) return;
            const wrap = btn.parentElement;
            const hidden = wrap.querySelector('input[type="hidden"]');
            const preview = wrap.querySelector('.cf-img-preview');
            if (!hidden) return;
            window.openMediaModal(function(media) {
                hidden.value = media.path;
                if (preview) preview.innerHTML = `<img src="/storage/${media.path}" class="w-full h-full object-cover">`;
                btn.textContent = 'Change Image';
            });
        });
    </script>
</x-cms-dashboard::layouts.admin>
