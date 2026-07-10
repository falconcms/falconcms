<x-falcon-cms::layouts.admin pro-lock-feature="{{ ($post->type ?? '') === 'product' ? 'ecommerce' : '' }}" title="Edit {{ ucfirst($post->type) }}" active-menu="{{ $post->type === 'page' ? 'pages' : ($post->type ?: 'posts') }}">
    
    <div class="mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327] inline-block mr-3">Edit {{ ucfirst($post->type) }}</h1>
        <a href="{{ route('admin.posts.create', ['type' => $post->type]) }}" class="wp-btn-secondary px-2 py-0.5 text-[12px] bg-white hover:bg-[#f6f7f7] border-[#2271b1] text-[#2271b1] leading-normal">Add New</a>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif


    @if(!empty($pendingAutosave))
    <div id="autosave-recovery" class="flex items-center gap-3 bg-[#fcf9e8] border border-[#f0e6a6] text-[#8a6d3b] rounded p-3 mb-4 text-[13px]">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>An autosaved draft from <strong>{{ $pendingAutosave['time'] }}</strong> is available.</span>
        <form action="{{ route('admin.posts.revisions.restore', ['id' => $post->id, 'revision' => $pendingAutosave['id']]) }}" method="POST" class="ml-auto">
            @csrf
            <button type="submit" class="wp-btn-primary text-[12px] px-3 py-1">Restore draft</button>
        </form>
        <button type="button" onclick="document.getElementById('autosave-recovery').remove()" class="text-[#8a6d3b] hover:text-[#5c4827]">&times;</button>
    </div>
    @endif

    <form action="{{ route('admin.posts.update', $post) }}" method="POST" id="post-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="type" value="{{ $post->type }}">
        <input type="hidden" name="status" id="status-hidden" value="{{ $post->status }}">
        
        <div class="flex flex-col lg:flex-row gap-5">
            <!-- Left Column: Content -->
            <div class="flex-grow min-w-0">
                @if(in_array('title', $supports))
                <div class="mb-4">
                    <input type="text" name="title" id="title-input" value="{{ old('title', $post->title) }}" 
                           class="w-full text-[1.7em] leading-normal border @error('title') border-[#d63638] @else border-[#8c8f94] @enderror rounded-sm py-[3px] px-[8px] focus:ring-[#2271b1] focus:border-[#2271b1] shadow-none m-0 bg-white" 
                           placeholder="Add title">
                    @error('title')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                    
                    @if(!isset($postType) || $postType->is_public)
                    <div id="permalink-container" data-is-home="{{ is_lazy_homepage($post) ? '1' : '0' }}" class="mt-2 text-[13px] flex items-center font-medium">
                        <span class="text-[#646970] mr-1">Permalink:</span>
                        <span id="permalink-view">
                            @php 
                                $isHome = is_lazy_homepage($post);
                                $fullUrl = get_falcon_permalink($post);
                                
                                $defaultLang = \Illuminate\Support\Facades\DB::table('cms_languages')->where('is_default', true)->value('code') ?: 'en';
                                $postLang = $post->lang_code ?? $defaultLang;
                                $isDefault = ($postLang === $defaultLang);
                                
                                if ($isHome) {
                                    $displayBase = rtrim(url('/'), '/') . ($isDefault ? '/' : '/' . $postLang);
                                    $displaySlug = '';
                                } else {
                                    $prefix = ($isDefault ? '' : $postLang . '/');
                                    if ($post->type !== 'post' && $post->type !== 'page') {
                                        $prefix .= $post->type . '/';
                                    }
                                    $displayBase = rtrim(url('/'), '/') . '/' . $prefix;
                                    $displaySlug = $post->slug . '/';
                                }
                            @endphp
                            <a id="permalink-full-link" href="{{ $fullUrl }}" target="_blank" class="text-[#2271b1] underline font-medium">{{ $displayBase }}<span id="permalink-slug-display" class="font-medium text-[#2271b1]">{{ $displaySlug }}</span></a>
                            @if(!$isHome)
                                <button type="button" id="edit-slug-btn" class="wp-btn-secondary bg-[#f6f7f7] text-[12px] h-[24px] ml-1 font-medium text-[#2271b1] border-[#c3c4c7]">Edit</button>
                            @endif
                        </span>
                        <span id="permalink-edit" class="hidden items-center">
                            <span class="text-[#646970] font-medium">{{ $displayBase }}</span>
                            <input type="text" name="slug" id="slug-input" value="{{ $post->slug }}" class="wp-input text-[13px] h-[24px] px-1 mx-1 font-medium" style="width: 150px;">/
                            <button type="button" id="ok-slug-btn" class="wp-btn-secondary bg-[#f6f7f7] text-[12px] h-[24px] mx-1 font-medium">OK</button>
                            <a href="#" id="cancel-slug-btn" class="text-[#2271b1] underline ml-1 font-medium">Cancel</a>
                        </span>
                    </div>
                    @endif
                </div>
                @else
                <input type="hidden" name="title" value="{{ $post->title }}">
                @endif


                @if(in_array('editor', $supports))
                <!-- Editor Toggle -->
                <div class="flex items-center mb-[1px] relative z-10 pl-2">
                    <button type="button" id="editor-mode-rich" class="px-4 py-2 text-[13px] font-semibold border border-[#dcdcde] border-b-0 bg-white text-[#2c3338] rounded-t-sm">Rich Editor</button>
                    <button type="button" id="editor-mode-builder" class="px-4 py-2 text-[13px] font-semibold border border-transparent border-b-0 text-[#2271b1] hover:text-[#0a4b78] rounded-t-sm">Page Builder</button>
                </div>
                <!-- Check if start_builder is passed in request or if the post's editor_type is builder -->
                @php
                    $isBuilderActive = request('start_builder') || old('editor_type', $post->editor_type) === 'builder';
                @endphp
                <input type="hidden" name="editor_type" id="editor_type" value="{{ $isBuilderActive ? 'builder' : 'rich' }}">

                <!-- Rich Text Editor -->
                <div id="rich-editor-container" class="bg-white border border-[#dcdcde] rounded-sm p-0 {{ $isBuilderActive ? 'hidden' : '' }}">
                    <textarea id="wp-editor" name="content" rows="20">{{ old('content', $post->content) }}</textarea>
                </div>

                <!-- Page Builder Placeholder -->
                <div id="page-builder-placeholder" class="bg-white border border-[#dcdcde] rounded-sm p-8 {{ $isBuilderActive ? '' : 'hidden' }}">
                    <div class="border-2 border-dashed border-[#dcdcde] rounded-xl p-16 text-center bg-[#fcfcfc] flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-14 h-14 rounded-full bg-[#f0f6fc] text-[#0A66C2] flex items-center justify-center mb-6 shadow-sm border border-[#e1e9f1] cursor-pointer hover:bg-[#e1edf9] transition-colors">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        </div>
                        <h2 class="text-[#2c3338] text-[22px] font-bold mb-3">Welcome to the Page Builder</h2>
                        <p class="text-[#646970] text-[14px] mb-8">This post is now using the amazing page builder.</p>
                        
                        <button type="button" @if(isset($post->id)) onclick="window.location.href='{{ route('admin.falcon-builder', $post->id) }}'" @else onclick="window.showToast('Please save the post first to enable the Page Builder.', 'warning')" @endif class="wp-btn-primary px-6 py-2 h-auto text-[15px] rounded-md shadow-sm">
                            Edit with Page Builder
                        </button>
                    </div>
                </div>
                @endif
                
                @if(in_array('excerpt', $supports) && $type !== 'product')
                <!-- Excerpt (products use the dedicated Product Short Description field instead) -->
                <div class="wp-metabox mt-6 mb-6">
                    <div class="wp-metabox-header"><span>Excerpt</span></div>
                    <div class="wp-metabox-content">
                        <textarea name="excerpt" id="excerpt" rows="3" class="w-full text-[14px] leading-normal border @error('excerpt') border-[#d63638] @else border-[#8c8f94] @enderror rounded-sm py-[3px] px-[8px] focus:ring-[#2271b1] focus:border-[#2271b1] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] m-0 bg-white">{{ old('excerpt', $post->excerpt) }}</textarea>
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
                                @php $val = $fieldValues[$field->id] ?? ''; @endphp
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
                                        <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ $val }}" class="wp-input w-full" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->type === 'textarea')
                                        <textarea name="custom_fields[{{ $field->id }}]" rows="4" class="wp-input w-full" {{ $field->required ? 'required' : '' }}>{{ old('custom_fields.'.$field->id, $val) }}</textarea>
                                    @elseif($field->type === 'select')
                                        @php $selectOpts = array_filter(array_map('trim', explode("\n", $field->params['options'] ?? ''))); @endphp
                                        <select name="custom_fields[{{ $field->id }}]" class="wp-input w-full h-8 py-0" {{ $field->required ? 'required' : '' }}>
                                            <option value="">— Select —</option>
                                            @foreach($selectOpts as $opt)
                                                <option value="{{ $opt }}" {{ old('custom_fields.'.$field->id, $val) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field->type === 'checkbox')
                                        @php
                                            $cbOpts = array_filter(array_map('trim', explode("\n", $field->params['options'] ?? '')));
                                            $cbVal  = is_string($val) && str_starts_with($val, '[') ? (json_decode($val, true) ?? []) : (array)($val ?: []);
                                        @endphp
                                        <div class="space-y-2">
                                            @foreach($cbOpts as $opt)
                                                <label class="flex items-center gap-2 text-[13px] text-[#2c3338] cursor-pointer">
                                                    <input type="checkbox" name="custom_fields[{{ $field->id }}][]" value="{{ $opt }}" {{ in_array($opt, $cbVal) ? 'checked' : '' }} class="rounded border-[#c3c4c7]">
                                                    {{ $opt }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($field->type === 'radio')
                                        @php $radioOpts = array_filter(array_map('trim', explode("\n", $field->params['options'] ?? ''))); @endphp
                                        <div class="space-y-2">
                                            @foreach($radioOpts as $opt)
                                                <label class="flex items-center gap-2 text-[13px] text-[#2c3338] cursor-pointer">
                                                    <input type="radio" name="custom_fields[{{ $field->id }}]" value="{{ $opt }}" {{ old('custom_fields.'.$field->id, $val) === $opt ? 'checked' : '' }}>
                                                    {{ $opt }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif($field->type === 'repeater')
                                        @php
                                            $subFields = $field->params['sub_fields'] ?? [];
                                            $repRows   = is_string($val) && str_starts_with($val, '[') ? (json_decode($val, true) ?? []) : [];
                                            if (empty($repRows)) $repRows = [[]];
                                        @endphp
                                        @if(count($subFields) > 0)
                                        <div class="repeater-field" data-field-id="{{ $field->id }}">
                                            <div class="rep-rows space-y-3">
                                                @foreach($repRows as $rowIdx => $rowData)
                                                <div class="rep-row border border-[#c3c4c7] rounded-sm p-3 relative bg-[#f9f9f9]">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                        @foreach($subFields as $sf)
                                                        <div>
                                                            <label class="block text-[12px] font-bold text-[#1d2327] mb-1">{{ $sf['label'] }}</label>
                                                            @if(($sf['type'] ?? 'text') === 'textarea')
                                                                <textarea name="custom_fields[{{ $field->id }}][{{ $rowIdx }}][{{ $sf['name'] }}]" rows="2" class="wp-input w-full text-[13px]">{{ $rowData[$sf['name']] ?? '' }}</textarea>
                                                            @elseif($sf['type'] === 'image')
                                                                @php $imgVal = $rowData[$sf['name']] ?? ''; @endphp
                                                                <div class="flex items-center gap-2">
                                                                    <div class="w-14 h-14 bg-[#f0f0f1] border border-[#c3c4c7] rounded-sm flex items-center justify-center overflow-hidden shrink-0 cf-img-preview">
                                                                        @if($imgVal)
                                                                            <img src="/storage/{{ $imgVal }}" class="w-full h-full object-cover">
                                                                        @else
                                                                            <svg class="w-6 h-6 text-[#c3c4c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                                        @endif
                                                                    </div>
                                                                    <button type="button" class="wp-btn-secondary h-8 px-3 text-[12px] cf-img-btn">{{ $imgVal ? 'Change Image' : 'Choose Image' }}</button>
                                                                    <input type="hidden" name="custom_fields[{{ $field->id }}][{{ $rowIdx }}][{{ $sf['name'] }}]" value="{{ $imgVal }}">
                                                                </div>
                                                            @else
                                                                <input type="text" name="custom_fields[{{ $field->id }}][{{ $rowIdx }}][{{ $sf['name'] }}]" value="{{ $rowData[$sf['name']] ?? '' }}" class="wp-input w-full text-[13px]">
                                                            @endif
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                    <button type="button" onclick="removeRepRow(this)" class="absolute top-2 right-2 text-[11px] text-[#d63638] hover:underline">Remove</button>
                                                </div>
                                                @endforeach
                                            </div>
                                            <button type="button" onclick="addRepRow(this, {{ $field->id }}, {{ json_encode($subFields) }})" class="mt-3 text-[12px] text-[#2271b1] hover:underline font-medium">+ Add Row</button>
                                        </div>
                                        @endif
                                    @elseif($field->type === 'wysiwyg')
                                        <textarea name="custom_fields[{{ $field->id }}]" class="wp-input w-full h-32" {{ $field->required ? 'required' : '' }}>{{ old('custom_fields.'.$field->id, $val) }}</textarea>
                                    @elseif($field->type === 'image')
                                        <div class="flex items-center gap-4">
                                            <div class="w-20 h-20 bg-[#f0f0f1] border border-[#c3c4c7] rounded-sm flex items-center justify-center overflow-hidden cf-img-preview">
                                                @if($val)
                                                    <img src="/storage/{{ $val }}" class="w-full h-full object-cover">
                                                @else
                                                    <svg class="w-8 h-8 text-[#c3c4c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                @endif
                                            </div>
                                            <button type="button" class="wp-btn-secondary h-8 px-4 text-[12px] cf-img-btn">{{ $val ? 'Change Image' : 'Choose Image' }}</button>
                                            <input type="hidden" name="custom_fields[{{ $field->id }}]" value="{{ old('custom_fields.'.$field->id, $val) }}" {{ $field->required ? 'required' : '' }}>
                                        </div>
                                    @else
                                        <input type="text" name="custom_fields[{{ $field->id }}]" value="{{ $val }}" class="wp-input w-full" {{ $field->required ? 'required' : '' }}>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif

                @include('falcon-cms::admin.posts.partials.product-data', ['post' => $post, 'type' => $type])
                @include('falcon-cms::admin.posts.partials.seo', ['post' => $post])
            </div>

            <!-- Right Column: Metaboxes -->
            <div class="w-full lg:w-[280px] shrink-0 space-y-5">
                
                <!-- Language & Multilingual Metabox -->
                @php 
                    $isMultiLang = get_cms_option('multi_language_enabled', 0);
                    $activeLanguages = \FalconCms\Core\Models\Language::where('status', true)->get(); 
                @endphp

                @if($activeLanguages->count() > 1)
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header"><span>Language</span></div>
                    <div class="wp-metabox-content p-3">
                        <div class="mb-3">
                            <label class="block text-[12px] font-bold text-[#1d2327] mb-1">Post Language</label>
                            <select name="lang_code" class="wp-input w-full text-[13px] h-8 py-0">
                                @foreach($activeLanguages as $lang)
                                    <option value="{{ $lang->code }}" {{ $post->lang_code == $lang->code ? 'selected' : '' }}>
                                        {{ $lang->flag }} {{ $lang->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if(!$post->origin_id)
                            <hr class="my-3 border-gray-100">
                            <label class="flex items-center text-[13px] font-bold text-[#1d2327] mb-3 cursor-pointer">
                                <input type="checkbox" name="make_multilingual_copy" value="1" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]" onchange="document.getElementById('multi-lang-list').classList.toggle('hidden', !this.checked)">
                                Make more copies?
                            </label>

                            <div id="multi-lang-list" class="hidden space-y-2 pl-6 border-l-2 border-gray-100">
                                <p class="text-[11px] text-gray-500 mb-2">Clone to:</p>
                                @php
                                    $existingClones = \FalconCms\Core\Models\Post::where('origin_id', $post->id)->pluck('lang_code')->toArray();
                                @endphp
                                @foreach($activeLanguages as $lang)
                                    @if($lang->code !== $post->lang_code && !in_array($lang->code, $existingClones))
                                    <label class="flex items-center text-[12px] text-[#2c3338] lang-option-{{ $lang->code }}">
                                        <input type="checkbox" name="copy_to_languages[]" value="{{ $lang->code }}" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                        <span class="mr-1">{{ $lang->flag }}</span> {{ $lang->name }}
                                    </label>
                                    @endif
                                @endforeach
                            </div>
                        @elseif($post->origin_id)
                            @php $original = \FalconCms\Core\Models\Post::find($post->origin_id); @endphp
                            <div class="bg-blue-50 p-2 border border-blue-100 rounded-sm">
                                <p class="text-[11px] text-blue-700">
                                    This is the <strong>{{ $activeLanguages->where('code', $post->lang_code)->first()->name ?? $post->lang_code }}</strong> version.
                                </p>
                                @if($original)
                                <p class="text-[10px] mt-1">
                                    <a href="{{ route('admin.posts.edit', $original) }}" class="text-blue-600 underline font-bold">View Original ({{ strtoupper($original->lang_code) }})</a>
                                </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                @else
                    <input type="hidden" name="lang_code" value="{{ $post->lang_code }}">
                    @if($post->origin_id)
                         <div class="wp-metabox mb-6 p-3 bg-blue-50 border border-blue-100 rounded-sm">
                            <p class="text-[11px] text-blue-700">This is a translated version ({{ strtoupper($post->lang_code) }}). Language switching is currently disabled.</p>
                         </div>
                    @endif
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
                                    Status: <strong class="text-black" id="status-display-text">{{ ucfirst($post->status) }}</strong> <a href="#" class="text-[#2271b1] underline ml-1 toggle-publish-edit" data-target="status-edit">Edit</a>
                                    <div id="status-edit" class="hidden mt-2 p-2 bg-[#f6f7f7] border border-[#dfdfdf]">
                                        <div class="flex space-x-1">
                                            <select id="status-select-ui" class="wp-input text-[13px] py-0 h-[26px] flex-grow">
                                                <option value="draft" {{ $post->status == 'draft' ? 'selected' : '' }}>Draft</option>
                                                <option value="published" {{ $post->status == 'published' ? 'selected' : '' }}>Published</option>
                                                <option value="scheduled" {{ $post->status == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
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
                                    <span id="publish-display-prefix">{{ $post->status == 'scheduled' ? 'Scheduled for' : 'Published on' }}</span> <strong class="text-black" id="publish-display-text">{{ $post->published_at ? $post->published_at->timezone(cms_timezone())->format('M j, Y @ H:i') : $post->created_at?->timezone(cms_timezone())->format('M j, Y @ H:i') }}</strong> <a href="#" class="text-[#2271b1] underline ml-1 toggle-publish-edit" data-target="publish-edit">Edit</a>
                                    <div id="publish-edit" class="hidden mt-2 p-3 bg-[#f6f7f7] border border-[#dfdfdf] rounded">
                                        @php
                                            $months = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
                                            $pDate = ($post->published_at ?: $post->created_at ?: now())->copy()->timezone(cms_timezone());
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
                                        <input type="hidden" name="published_at" id="published-at-hidden" value="{{ $pDate->format('Y-m-d H:i:s') }}">
                                        <div class="text-right"><button type="button" id="ok-publish-btn" class="wp-btn-secondary text-[12px] h-[28px]">OK</button></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Revisions row --}}
                    <div class="border-t border-[#dfdfdf] px-3 py-2 flex items-center justify-between">
                        <span class="text-[13px] text-[#1d2327] flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Revisions: <strong>{{ $revisionCount ?? 0 }}</strong>
                        </span>
                        <a href="{{ route('admin.posts.revisions', $post->id) }}" class="text-[#2271b1] hover:underline text-[13px] font-semibold">Browse</a>
                    </div>
                    <div class="bg-[#f6f7f7] border-t border-[#dfdfdf] p-2 flex justify-between items-center">
                        <button type="submit" form="trash-form-main" class="text-[#b32d2e] hover:text-[#8a2424] text-[13px] underline bg-transparent border-0 cursor-pointer">Move to Trash</button>
                        <span id="autosave-status" class="text-[11px] text-[#646970] italic mr-auto ml-2"></span>
                        <button type="submit" id="main-publish-btn" class="wp-btn-primary">Update</button>
                    </div>
                </div>

                <!-- Categories Metabox -->
                @if($post->type === 'post' && !in_array('categories', $overriddenTaxonomies))
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
                        <span>Categories</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    </div>
                    <div class="wp-metabox-content" style="padding: 10px;">
                        <div class="h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-white">
                            @php 
                                $allCategories = \FalconCms\Core\Models\Category::orderBy('name')->get();
                                $selectedCatIds = $post->categories->pluck('id')->toArray();
                            @endphp
                            @forelse($allCategories as $category)
                                <label class="flex items-center text-[13px] text-[#2c3338] mb-1">
                                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" {{ in_array($category->id, old('categories', $selectedCatIds)) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
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
                            <button type="button" class="wp-btn-secondary text-[12px] h-[30px] w-full mt-2 add-term-ajax-btn" data-taxonomy="categories" data-cpt="{{ $post->type }}" data-is-builtin="true">Add New Category</button>
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
                                <!-- Tag Style UI for CPT Edit -->
                                <div class="p-1">
                                    <div class="flex gap-2">
                                        <input type="text" class="wp-input flex-grow text-[13px] h-8 cpt-tag-input" 
                                               data-taxonomy="{{ $taxonomy->slug }}" 
                                               data-cpt="{{ $post->type }}" placeholder="">
                                        <button type="button" class="wp-btn-secondary h-8 px-4 text-[13px] add-cpt-tag-btn">Add</button>
                                    </div>
                                    <p class="text-[11px] text-[#646970] mt-1 italic">Separate {{ strtolower($taxonomy->name) }} with commas</p>
                                    
                                    <div class="cpt-tags-container mt-3 flex flex-wrap gap-2" data-taxonomy="{{ $taxonomy->slug }}">
                                        @foreach($taxonomy->terms as $term)
                                            @if(in_array($term->id, $taxonomy->selected_ids ?? []))
                                                <span class="inline-flex items-center bg-[#f0f0f1] text-[#2c3338] text-[12px] px-2 py-0.5 rounded-sm border border-[#dfdfdf]">
                                                    {{ $term->name }} 
                                                    <button type="button" class="ml-1 text-[#b32d2e] font-bold remove-cpt-tag" data-id="{{ $term->id }}">×</button>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                    <!-- Hidden inputs for actual IDs -->
                                    <div class="cpt-tags-hidden-inputs hidden" data-taxonomy="{{ $taxonomy->slug }}">
                                        @foreach($taxonomy->terms as $term)
                                            @if(in_array($term->id, $taxonomy->selected_ids ?? []))
                                                <input type="hidden" name="tax_terms[]" value="{{ $term->id }}">
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <!-- Category style UI (Checklist) -->
                                <div class="h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-white">
                                    @forelse($taxonomy->terms as $term)
                                        <label class="flex items-center text-[13px] text-[#2c3338] mb-1">
                                            @php $isChecked = in_array($term->id, $taxonomy->selected_ids ?? []); @endphp
                                            @if(isset($taxonomy->is_builtin) && $taxonomy->is_builtin)
                                                <input type="checkbox" name="categories[]" value="{{ $term->id }}" {{ in_array($term->id, old('categories', $taxonomy->selected_ids ?? [])) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                            @else
                                                <input type="checkbox" name="tax_terms[]" value="{{ $term->id }}" {{ in_array($term->id, old('tax_terms', $taxonomy->selected_ids ?? [])) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
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
                                            data-cpt="{{ $post->type }}">
                                        Add New {{ $taxonomy->singular_name ?? $taxonomy->name }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @endif

                <!-- Standard Tags Metabox -->
                @if($post->type === 'post' && !in_array('tags', $overriddenTaxonomies))
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
                        <input type="hidden" name="tags" id="tags-hidden-input" value="{{ old('tags', implode(',', $post->tags->pluck('name')->toArray())) }}">

                        {{-- Removed: Choose from most used tags --}}
                    </div>
                </div>
                @endif

                @if($post->type === 'product')
                    @include('falcon-cms::admin.posts.partials.product-taxonomies')
                @endif

                @if(in_array('featured_image', $supports) || in_array('thumbnail', $supports))
                <!-- Featured Image -->
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer"><span>Featured image</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div>
                    <div class="wp-metabox-content">
                        @php $fi = old('featured_image', $post->featured_image); @endphp
                        <div id="fi-preview-container" class="{{ $fi ? '' : 'hidden' }} mb-3">
                            <img id="fi-preview" src="{{ $fi ? asset('storage/'.$fi) : '' }}" class="max-w-full h-auto border border-gray-200 p-1 bg-white cursor-pointer">
                        </div>
                        <a href="#" id="set-fi-btn" class="text-[#2271b1] text-[13px] underline {{ $fi ? 'hidden' : '' }}">Set featured image</a>
                        <a href="#" id="remove-fi-btn" class="text-[#b32d2e] text-[13px] underline {{ $fi ? '' : 'hidden' }} mt-2">Remove featured image</a>
                        <input type="hidden" name="featured_image" id="fi-path-hidden" value="{{ $fi }}">
                        <input type="hidden" name="remove_featured_image" id="remove-fi-hidden" value="{{ old('remove_featured_image', '0') }}">
                    </div>
                </div>

                @if($type === 'product')
                <!-- Gallery -->
                <div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
                    <div class="wp-metabox-header flex justify-between items-center cursor-pointer"><span>Gallery</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div>
                    <div class="wp-metabox-content">
                        <div id="gallery-container" class="grid grid-cols-3 gap-2 mb-3">
                            @if($post->gallery && is_array($post->gallery))
                                @foreach($post->gallery as $image)
                                    <div class="gallery-item relative group aspect-square border border-gray-200 p-1 bg-white cursor-pointer" data-path="{{ $image }}">
                                        <img src="{{ asset('storage/'.$image) }}" class="w-full h-full object-cover">
                                        <button type="button" class="absolute top-0 right-0 bg-red-600 text-white w-5 h-5 flex items-center justify-center text-[10px] opacity-0 group-hover:opacity-100 transition-opacity remove-gallery-img">×</button>
                                        <input type="hidden" name="gallery[]" value="{{ $image }}">
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <a href="#" id="add-gallery-btn" class="text-[#2271b1] text-[13px] underline">+ Add images to gallery</a>
                    </div>
                </div>
                @endif
                @endif

            </div>
        </div>
    </form>

    <form action="{{ route('admin.posts.destroy', $post) }}" method="POST" id="trash-form-main" class="hidden">
        @csrf @method('DELETE')
    </form>

    <script src="{{ asset('vendor/falcon-cms/js/tinymce.min.js') }}"></script>
    <script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>
    <script>
        tinymce.init({
            selector: '#wp-editor',
            menubar: false,
            height: 450,
            width: '100%',
            plugins: ['lists', 'link', 'image', 'preview', 'code', 'fullscreen', 'media', 'table', 'wordcount'],
            toolbar: 'blocks | bold italic underline strikethrough | blockquote | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | code fullscreen',
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
        const slugDisplay = document.getElementById('permalink-slug-display');
        const slugInput = document.getElementById('slug-input');
        const viewSpan = document.getElementById('permalink-view');
        const editSpan = document.getElementById('permalink-edit');
        const statusHidden = document.getElementById('status-hidden');
        let originalSlug = slugInput ? slugInput.value : '';

        const permalinkContainer = document.getElementById('permalink-container');
        
        function generateSlug(text) {
            return text.toString().toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '-').replace(/^-+/, '').replace(/-+$/, '');
        }

        const isHome = permalinkContainer?.getAttribute('data-is-home') === '1';

        if (permalinkContainer && titleInput && slugInput && !isHome) {
            titleInput.addEventListener('blur', function() {
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
                let newSlug = slugInput.value.toLowerCase().replace(/\s+/g, '-').replace(/[^\w\-]+/g, '');
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
        const removeHidden = document.getElementById('remove-fi-hidden');

        setFiBtn?.addEventListener('click', (e) => { 
            e.preventDefault(); 
            window.openMediaModal(function(media) {
                if (fiPathHidden) fiPathHidden.value = media.path;
                if (fiPreview) fiPreview.src = `/storage/${media.path}`;
                fiPreviewContainer?.classList.remove('hidden');
                setFiBtn?.classList.add('hidden');
                removeFiBtn?.classList.remove('hidden');
                if (removeHidden) removeHidden.value = "0";
            });
        });

        fiPreview?.addEventListener('click', (e) => { 
            e.preventDefault(); 
            setFiBtn?.click();
        });

        removeFiBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (fiPathHidden) fiPathHidden.value = '';
            if (removeHidden) removeHidden.value = "1";
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

        // Gallery Container click
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
            
            // If selecting scheduled but no future date set, default behavior
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
            // Compare against the CMS timezone "now" (Settings → General), not the browser clock,
            // so a small future bump is detected correctly regardless of the editor's local timezone.
            let now = new Date("{{ now(cms_timezone())->format('Y-m-d\TH:i:s') }}");
            let isFuture = selDate > now;
            
            let monthName = document.getElementById('pub-mm').options[document.getElementById('pub-mm').selectedIndex].text;
            document.getElementById('publish-display-prefix').innerText = isFuture ? 'Scheduled for' : 'Published on';
            document.getElementById('publish-display-text').innerText = `${monthName} ${dd}, ${yy} @ ${hr}:${min}`;
            
            if (isFuture) {
                document.getElementById('main-publish-btn').innerText = 'Schedule';
                statusHidden.value = 'scheduled';
                document.getElementById('status-display-text').innerText = 'Scheduled';
                // Update select UI too
                const statusSelectUI = document.getElementById('status-select-ui');
                if (statusSelectUI) statusSelectUI.value = 'scheduled';
            } else {
                document.getElementById('main-publish-btn').innerText = 'Update';
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
                    if (typeof window.showToast === 'function') window.showToast('Added successfully');
                } else {
                    alert("Error Response: " + JSON.stringify(data));
                    if (typeof window.showToast === 'function') window.showToast(data?.message || 'Error adding item', 'error');
                }
            } catch (error) {
                alert("JS Catch Error: " + error.message);
                if (typeof window.showToast === 'function') window.showToast('Error adding item', 'error');
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
                            if (typeof window.showToast === 'function') window.showToast(errorMsgs, 'error'); else alert(errorMsgs);
                        } else {
                            if (typeof window.showToast === 'function') window.showToast(data.message || 'Error adding tag', 'error'); else alert(data.message || 'Error adding tag');
                        }
                    }
                } catch (e) { 
                    alert("Tag Add JS Error: " + e.message);
                    if (typeof window.showToast === 'function') window.showToast('Error adding tag', 'error');
                    console.error(e); 
                }
            }
            inputEl.value = '';
        }

        // CPT Tags: Keydown listener removed as per request (only Add button allowed)


        // Standard Tags Logic
        let tags = [];
        const tagInput = document.getElementById('tag-input');
        const tagsContainer = document.getElementById('tags-container');
        const tagsHidden = document.getElementById('tags-hidden-input');
        const addTagBtn = document.getElementById('add-tag-btn');
        if (tagsHidden && tagsHidden.value && tags.length === 0) {
            tags = tagsHidden.value.split(',').map(t => t.trim()).filter(t => t);
            renderTags();
        }

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

        // Initialize based on hidden input value
        if (editorTypeHidden) {
            switchEditorMode(editorTypeHidden.value || 'rich');
        }

        // Initialize Tags
        renderTags();

        // Language selector logic to hide current lang from clone list
        const langSelect = document.querySelector('select[name="lang_code"]');
        if (langSelect) {
            const updateCloneList = () => {
                const selectedLang = langSelect.value;
                document.querySelectorAll('#multi-lang-list label').forEach(label => {
                    if (label.classList.contains(`lang-option-${selectedLang}`)) {
                        label.classList.add('hidden');
                        label.querySelector('input').checked = false;
                    } else {
                        label.classList.remove('hidden');
                        label.querySelector('input').checked = false; // In edit, don't auto check to avoid accidental clones
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
            const postType = "{{ $post->type ?? 'post' }}";
            
            // Clear previous errors
            titleInput.classList.remove('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
            
            if (!titleInput.value.trim()) {
                isValid = false;
                titleInput.classList.add('border-[#d63638]', 'ring-1', 'ring-[#d63638]');
                titleInput.focus();
                window.showToast('Please enter a title before updating.', 'error');
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

    {{-- ── Classic editor: Revisions + Autosave ── --}}
    <script>
    (function () {
        const postId       = {{ $post->id }};
        const autosaveUrl  = "{{ route('admin.posts.autosave', $post->id) }}";
        const revisionsUrl = "{{ route('admin.posts.revisions', $post->id) }}";
        const restoreBase  = "{{ url('admin/posts/'.$post->id.'/revisions') }}";
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        const titleEl  = document.getElementById('title-input');
        const statusEl = document.getElementById('autosave-status');

        const getEditor  = () => (window.tinymce && tinymce.get('wp-editor')) || null;
        const getContent = () => {
            const ed = getEditor();
            if (ed) return ed.getContent();
            const ta = document.getElementById('wp-editor');
            return ta ? ta.value : '';
        };
        const getTitle = () => titleEl ? titleEl.value : '';

        // Dirty tracking — autosave ONLY runs after a real edit, not on a timer alone.
        let baselineTitle = getTitle();
        let titleDirty = false;
        if (titleEl) titleEl.addEventListener('input', () => { titleDirty = (getTitle() !== baselineTitle); });

        const isChanged = () => {
            const ed = getEditor();
            const contentDirty = ed ? ed.isDirty() : false;  // TinyMCE's own change tracking
            return titleDirty || contentDirty;
        };

        async function autosave() {
            if (!isChanged()) return;                          // <-- nothing actually changed → skip
            try {
                const res = await fetch(autosaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ title: getTitle(), content: getContent() })
                });
                const d = await res.json();
                if (d.success) {
                    // Reset dirty baselines so we don't re-send unchanged content next tick.
                    baselineTitle = getTitle();
                    titleDirty = false;
                    const ed = getEditor();
                    if (ed) ed.setDirty(false);
                    if (statusEl) statusEl.textContent = 'Draft saved ' + d.time;
                }
            } catch (e) { /* best-effort */ }
        }

        setInterval(autosave, 30000);
        // Flush once when leaving/hiding the tab (only fires if there are real changes).
        document.addEventListener('visibilitychange', () => { if (document.hidden) autosave(); });
        // When the form is submitted (manual save), no autosave is needed.
        document.getElementById('post-form')?.addEventListener('submit', () => { titleDirty = false; const ed = getEditor(); if (ed) ed.setDirty(false); });
    })();
    </script>
</x-falcon-cms::layouts.admin>
