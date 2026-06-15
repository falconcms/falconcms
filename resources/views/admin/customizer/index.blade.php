<x-falcon-cms::layouts.admin>
<x-slot name="title">Customizer &lsaquo; FalconCMS</x-slot>
<x-falcon-cms::admin.delete-modal />

{{-- Toast container --}}
<div id="customizer-toast" class="fixed top-6 right-6 z-[99999] flex flex-col gap-2 pointer-events-none" style="min-width:280px;"></div>

<div x-cloak x-data="customizerApp('{{ $section }}')" x-init="init()" class="flex flex-col" style="height:calc(100vh - 32px - 40px);">

    {{-- ===== TOP BAR ===== --}}
    <div class="flex-shrink-0 flex items-center gap-3 mb-3">
        <h1 class="text-[20px] font-semibold text-[#1d2327] m-0 mr-auto">Customizer</h1>
        <div class="relative">
            <input type="text" x-model="search" @input="filterFields()"
                   placeholder="Search for option(s)"
                   class="wp-input h-8 w-[210px] pl-8 text-[12px]">
            <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-[#8c8f94]"
                  style="font-size:15px !important;">search</span>
        </div>
    </div>

    {{-- ===== MAIN CARD ===== --}}
    <div class="flex flex-1 overflow-hidden rounded border border-[#c3c4c7] shadow-sm bg-white">

        {{-- ===== LEFT SIDEBAR — dark like dashboard ===== --}}
        <div class="flex-shrink-0 bg-[#1d2327] overflow-y-auto overflow-x-hidden" style="width:210px; border-right:1px solid #2c3338;">
            <p class="px-3 pt-3 pb-1 text-[10px] font-semibold text-[#8c8f94] uppercase tracking-wider">Theme Options</p>
            @php $renderedGroups = []; @endphp
            @foreach($sections as $key => $sec)
                @if(empty($sec['parent']))
                    {{-- Standalone (flat) section --}}
                    <button type="button"
                            @click="switchSection('{{ $key }}')"
                            :class="activeSection === '{{ $key }}'
                                ? 'bg-[#2271b1] text-white'
                                : 'text-[#c3c4c7] hover:bg-[#2c3338] hover:text-[#72aee6]'"
                            class="relative w-full flex items-center gap-2 px-3 py-[9px] text-[13px] transition-colors duration-150 cursor-pointer text-left">
                        <span class="material-symbols-outlined flex-shrink-0 w-[18px] text-center"
                              style="font-size:16px !important; font-variation-settings:'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 20; max-width:18px; max-height:18px;">{{ $sec['icon'] }}</span>
                        <span class="leading-none" style="flex:1; min-width:0; white-space:nowrap;">{{ $sec['title'] }}</span>
                        <span x-cloak x-show="activeSection === '{{ $key }}'"
                              class="absolute -right-px top-1/2 -translate-y-1/2 w-0 h-0 z-10"
                              style="border-top:7px solid transparent; border-bottom:7px solid transparent; border-right:7px solid white;"></span>
                    </button>
                @elseif(!in_array($sec['parent'], $renderedGroups))
                    {{-- Grouped sections → collapsible parent (dashboard-style) rendered once --}}
                    @php
                        $grp = $sec['parent'];
                        $renderedGroups[] = $grp;
                        $childKeys = [];
                        foreach ($sections as $ck => $cs) { if (($cs['parent'] ?? null) === $grp) $childKeys[$ck] = $cs; }
                        $childJs   = "['" . implode("','", array_keys($childKeys)) . "']";
                        $grpIcon   = $sec['parent_icon'] ?? 'folder';
                    @endphp
                    <div x-data="{ open: {!! $childJs !!}.includes(activeSection) }">
                        <button type="button" @click="open = !open"
                                :class="{!! $childJs !!}.includes(activeSection)
                                    ? 'text-white bg-[#2c3338]'
                                    : 'text-[#c3c4c7] hover:bg-[#2c3338] hover:text-[#72aee6]'"
                                class="w-full flex items-center gap-2 px-3 py-[9px] text-[13px] transition-colors duration-150 cursor-pointer text-left">
                            <span class="material-symbols-outlined flex-shrink-0 w-[18px] text-center"
                                  style="font-size:16px !important; font-variation-settings:'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 20; max-width:18px; max-height:18px;">{{ $grpIcon }}</span>
                            <span class="leading-none" style="flex:1; min-width:0; white-space:nowrap;">{{ $grp }}</span>
                            <span class="material-symbols-outlined transition-transform duration-200 flex-shrink-0"
                                  :class="open ? 'rotate-180' : ''"
                                  style="font-size:16px !important;">expand_more</span>
                        </button>
                        <div x-show="open" x-cloak class="bg-[#2c3338]">
                            @foreach($childKeys as $ckey => $cs)
                                <button type="button" @click="switchSection('{{ $ckey }}')"
                                        :class="activeSection === '{{ $ckey }}'
                                            ? 'text-white font-semibold'
                                            : 'text-[#c3c4c7] hover:text-[#72aee6]'"
                                        class="relative w-full flex items-center gap-2 pl-9 pr-3 py-[7px] text-[12.5px] transition-colors duration-150 cursor-pointer text-left">
                                    <span class="leading-none" style="flex:1; min-width:0; white-space:nowrap;">{{ $cs['title'] }}</span>
                                    <span x-cloak x-show="activeSection === '{{ $ckey }}'"
                                          class="absolute -right-px top-1/2 -translate-y-1/2 w-0 h-0 z-10"
                                          style="border-top:7px solid transparent; border-bottom:7px solid transparent; border-right:7px solid white;"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- ===== RIGHT CONTENT ===== --}}
        <div class="flex flex-col flex-1 overflow-hidden">

            {{-- Section title bar --}}
            <div class="flex-shrink-0 border-b border-[#c3c4c7] bg-[#f6f7f7] px-5 py-2.5 flex items-center gap-2 min-h-[40px]">
                @foreach($sections as $key => $sec)
                    <span x-show="activeSection === '{{ $key }}'" class="text-[14px] font-semibold text-[#1d2327]">{{ !empty($sec['parent']) ? $sec['parent'] . ' — ' . $sec['title'] : $sec['title'] }}</span>
                @endforeach
                <span x-show="search.length > 0" class="ml-auto text-[11px] text-[#646970]">
                    <span x-text="visibleCount" class="font-semibold"></span> result(s) found
                </span>
            </div>

            {{-- Scrollable content --}}
            <div class="flex-1 overflow-y-auto">
                <form id="customizer-form">
                    @csrf

                    {{-- No results --}}
                    <div x-show="search.length > 0 && visibleCount === 0"
                         class="flex flex-col items-center justify-center py-16 text-[#646970]">
                        <span class="material-symbols-outlined text-[#c3c4c7] mb-2" style="font-size:40px !important;">search_off</span>
                        <p class="text-[13px]">No options found for &ldquo;<span class="font-semibold text-[#1d2327]" x-text="search"></span>&rdquo;</p>
                    </div>

                    @foreach($sections as $sectionKey => $sec)

                        @if($sectionKey === 'import_export')
                            <div x-show="activeSection === '{{ $sectionKey }}' && search.length === 0" class="p-5 space-y-4 max-w-2xl">

                                <div class="border border-[#c3c4c7] rounded">
                                    <div class="px-4 py-2.5 bg-[#f6f7f7] border-b border-[#c3c4c7]">
                                        <h3 class="text-[13px] font-bold text-[#1d2327] m-0">Export Settings</h3>
                                    </div>
                                    <div class="px-4 py-4">
                                        <p class="text-[12px] text-[#646970] mb-3">Download all current theme settings as a JSON backup file.</p>
                                        <a href="{{ route('admin.customizer.export') }}"
                                           class="wp-btn-primary h-8 px-4 text-[12px] inline-flex items-center gap-2">
                                            <span class="material-symbols-outlined" style="font-size:15px !important;">download</span>
                                            Download theme-options.json
                                        </a>
                                    </div>
                                </div>

                                <div class="border border-[#c3c4c7] rounded">
                                    <div class="px-4 py-2.5 bg-[#f6f7f7] border-b border-[#c3c4c7] flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[18px] text-[#646970]">upload</span>
                                        <div>
                                            <h3 class="text-[13px] font-bold text-[#1d2327] m-0">Import Settings</h3>
                                            <p class="text-[11px] text-[#646970] m-0">Upload a previously exported JSON file to restore settings.</p>
                                        </div>
                                    </div>
                                    <div class="px-4 py-4">
                                        <form id="import-settings-form" method="POST" action="{{ route('admin.customizer.import') }}" enctype="multipart/form-data">
                                            @csrf
                                            <div class="flex flex-col gap-3">
                                                <div id="cust-import-drop-zone"
                                                     class="relative border-2 border-dashed border-[#c3c4c7] rounded-sm p-5 text-center cursor-pointer hover:border-[#2271b1] transition-colors">
                                                    <span class="material-symbols-outlined text-[36px] text-[#c3c4c7] block mb-1" id="cust-import-icon">upload_file</span>
                                                    <p class="text-[13px] text-[#646970] m-0" id="cust-import-label">Click to choose file or drag &amp; drop here</p>
                                                    <p class="text-[11px] text-[#9ca3af] mt-1 mb-0">Accepted: <strong>.json</strong></p>
                                                    <input type="file" name="import_file" id="cust-import-file" accept=".json"
                                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                                           onchange="handleCustImportSelect(this)" required>
                                                </div>
                                                <div class="flex justify-center">
                                                    <button type="button" id="cust-import-btn"
                                                            onclick="custConfirmImport()"
                                                            disabled
                                                            class="wp-btn-primary flex items-center gap-1.5 opacity-50 pointer-events-none">
                                                        <span class="material-symbols-outlined" style="font-size:18px !important;">download_done</span>
                                                        Import Settings
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>

                        @else
                            <div x-show="activeSection === '{{ $sectionKey }}' && search.length === 0">
                                @php
                                    $isHeaderSection = in_array($sectionKey, ['header', 'menu']);
                                    $sectionLocked = ($isHeaderSection && ($builderHeaderActive ?? false)) || ($sectionKey === 'footer' && ($builderFooterActive ?? false));
                                    $lockedPart = $isHeaderSection ? 'Header' : 'Footer';
                                @endphp
                                @if($sectionLocked)
                                    <div class="m-5 p-5 rounded-lg border border-blue-200 bg-blue-50 flex items-start gap-3">
                                        <span class="material-symbols-outlined text-blue-600 mt-0.5" style="font-size:20px !important;">extension</span>
                                        <div>
                                            <p class="text-[13px] font-semibold text-blue-900 m-0 mb-1">{{ $lockedPart }} is being built with the Page Builder</p>
                                            <p class="text-[12px] text-blue-800 leading-relaxed m-0">
                                                You have an active {{ strtolower($lockedPart) }} created with the <strong>Lazy Builder</strong>, so these Customizer options don't apply. Edit your {{ strtolower($lockedPart) }} from <strong>Appearance → Lazy Builder</strong>.
                                                To use these Customizer settings instead, unpublish or delete the builder {{ strtolower($lockedPart) }}.
                                            </p>
                                        </div>
                                    </div>
                                @else
                                <table class="w-full border-collapse">
                                    <tbody>
                                        @foreach($sec['fields'] as $key => $field)
                                            @php
                                                $val   = $settings[$key] ?? ($field['default'] ?? '');
                                                $label = $field['label'] ?? $key;
                                                $desc  = $field['desc']  ?? '';
                                                $type  = $field['type']  ?? 'text';
                                            @endphp
                                                 @if($type === 'heading')
                                                    <tr class="section-heading-row bg-[#f6f7f7] border-b border-[#c3c4c7]">
                                                        <td colspan="2" class="px-5 py-2.5">
                                                            <h3 class="text-[13px] font-bold text-[#1d2327] m-0 uppercase tracking-wider">{{ $field['label'] }}</h3>
                                                        </td>
                                                    </tr>
                                                    @continue
                                                 @endif

                                                 @if($type === 'info')
                                                    <tr class="section-info-row bg-blue-50/50 border-b border-[#f0f0f1]">
                                                        <td colspan="2" class="px-5 py-3">
                                                            <div class="flex items-start gap-3">
                                                                <span class="material-symbols-outlined text-blue-600 mt-0.5" style="font-size:18px !important;">info</span>
                                                                <p class="text-[12px] text-blue-800 leading-relaxed m-0">{!! $field['desc'] !!}</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @continue
                                                 @endif

                                                 <tr class="field-row border-b border-[#f0f0f1] hover:bg-[#f6f7f7]/60 transition-colors"
                                                @if(!empty($field['depends'])) data-depends="{{ $field['depends'] }}" @endif
                                                @if(!empty($field['depends_on'])) data-depends-on="{{ $field['depends_on'] }}" data-depends-value="{{ $field['depends_value'] ?? '' }}" @endif
                                                data-label="{{ strtolower($label . ' ' . $desc . ' ' . $sectionKey . ' ' . $sections[$sectionKey]['title']) }}">
                                                <th scope="row" class="w-[260px] text-left align-top px-5 py-3.5">
                                                    <label for="field_{{ $key }}" class="text-[13px] font-semibold text-[#2271b1] block mb-0.5 cursor-pointer">{{ $label }}</label>
                                                    @if($desc)
                                                        <p class="text-[11px] text-[#646970] leading-relaxed m-0">{!! $desc !!}</p>
                                                    @endif
                                                </th>
                                                <td class="px-5 py-3.5 align-middle">
                                                    @if($type === 'text' || $type === 'url')
                                                        <input type="text" name="{{ $key }}" id="field_{{ $key }}"
                                                               value="{{ $val }}"
                                                               placeholder="{{ $field['placeholder'] ?? '' }}"
                                                               class="wp-input w-[300px] text-[13px]">

                                                    @elseif($type === 'typography')
                                                        @php
                                                            $tVal = is_array($val) ? $val : (json_decode($val, true) ?: ($field['default'] ?? []));
                                                        @endphp
                                                        <div x-data="typographyComponent('{{ $key }}', {{ json_encode($tVal) }})" x-init="init()" class="typography-container space-y-4">
                                                            <input type="hidden" name="{{ $key }}" :value="JSON.stringify(fontData)">
                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                                                <div class="space-y-1.5 relative">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Font Family</label>
                                                                    <div class="relative" @click.away="open = false">
                                                                        <button type="button" @click="open = !open" 
                                                                                class="wp-input w-full h-9 flex items-center justify-between px-3 text-[13px] bg-white border border-[#c3c4c7] rounded hover:border-[#2271b1] focus:ring-1 focus:ring-[#2271b1]">
                                                                            <span x-text="fontData.family || 'Select Font'"></span>
                                                                            <span class="material-symbols-outlined text-[#8c8f94]" style="font-size:18px !important;">expand_more</span>
                                                                        </button>
                                                                        <div x-show="open" x-cloak
                                                                             class="absolute z-[100] mt-1 w-full bg-white border border-[#c3c4c7] rounded shadow-xl overflow-hidden">
                                                                            <div class="p-2 border-b border-[#f0f0f1] bg-[#f6f7f7]">
                                                                                <input type="text" x-model="fontSearch" placeholder="Search fonts..." 
                                                                                       class="w-full h-8 px-2 text-[12px] border border-[#d1d5db] rounded focus:outline-none focus:border-[#2271b1]">
                                                                            </div>
                                                                            <div class="max-h-[250px] overflow-y-auto custom-scrollbar">
                                                                                <template x-for="(fonts, category) in filteredFonts" :key="category">
                                                                                    <div>
                                                                                        <div class="px-3 py-1.5 bg-[#f0f0f1] text-[10px] font-bold text-[#646970] uppercase" x-text="category"></div>
                                                                                        <template x-for="font in fonts" :key="font.family">
                                                                                            <div @click="selectFont(font)" 
                                                                                                 class="px-3 py-2 text-[13px] hover:bg-[#2271b1] hover:text-white cursor-pointer transition-colors"
                                                                                                 :class="fontData.family === font.family ? 'bg-[#f0f7ff] text-[#2271b1] font-semibold' : 'text-[#1d2327]'">
                                                                                                <span x-text="font.family"></span>
                                                                                            </div>
                                                                                        </template>
                                                                                    </div>
                                                                                </template>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Variant</label>
                                                                    <select x-model="fontData.variant" class="wp-input w-full h-9 text-[13px]">
                                                                        <template x-for="variant in currentVariants" :key="variant">
                                                                            <option :value="variant" x-text="formatVariant(variant)" :selected="fontData.variant == variant"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Font Size</label>
                                                                    <input type="text" x-model="fontData.size" placeholder="16px" class="wp-input w-full h-9 text-[13px]">
                                                                </div>
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Line Height</label>
                                                                    <input type="text" x-model="fontData.line_height" placeholder="1.6" class="wp-input w-full h-9 text-[13px]">
                                                                </div>
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Letter Spacing</label>
                                                                    <input type="text" x-model="fontData.letter_spacing" placeholder="0px" class="wp-input w-full h-9 text-[13px]">
                                                                </div>
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Transform</label>
                                                                    <div class="flex border border-[#c3c4c7] rounded overflow-hidden">
                                                                        <template x-for="option in ['none', 'capitalize', 'uppercase', 'lowercase']" :key="option">
                                                                            <button type="button" @click="fontData.text_transform = option"
                                                                                    class="flex-1 h-9 flex items-center justify-center transition-colors border-r border-[#c3c4c7] last:border-r-0"
                                                                                    :class="fontData.text_transform === option ? 'bg-[#2271b1] text-white' : 'bg-[#f6f7f7] text-[#50575e] hover:bg-white'">
                                                                                <span class="material-symbols-outlined" style="font-size:16px !important;" x-text="getTransformIcon(option)" :title="option"></span>
                                                                            </button>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                                <div class="space-y-1.5">
                                                                    <label class="text-[11px] font-semibold text-[#646970] uppercase">Style & Decor</label>
                                                                    <div class="flex border border-[#c3c4c7] rounded overflow-hidden">
                                                                        <button type="button" @click="toggleStyle('italic')"
                                                                                class="flex-1 h-9 flex items-center justify-center border-r border-[#c3c4c7]"
                                                                                :class="fontData.font_style === 'italic' ? 'bg-[#2271b1] text-white' : 'bg-[#f6f7f7] text-[#50575e] hover:bg-white'">
                                                                            <span class="material-symbols-outlined" style="font-size:18px !important;">format_italic</span>
                                                                        </button>
                                                                        <template x-for="option in ['none', 'underline', 'line-through']" :key="option">
                                                                            <button type="button" @click="toggleStyle(option)"
                                                                                    class="flex-1 h-9 flex items-center justify-center transition-colors border-r border-[#c3c4c7] last:border-r-0"
                                                                                    :class="(option === 'none' && fontData.font_style === 'normal' && fontData.text_decoration === 'none') || (option !== 'none' && fontData.text_decoration === option) ? 'bg-[#2271b1] text-white' : 'bg-[#f6f7f7] text-[#50575e] hover:bg-white'">
                                                                                <span class="material-symbols-outlined" style="font-size:18px !important;" x-text="getDecorIcon(option)" :title="option"></span>
                                                                            </button>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="mt-4 p-4 border border-[#e5e7eb] rounded bg-white overflow-hidden">
                                                                <div class="flex items-center justify-between mb-3 border-b border-[#f0f0f1] pb-2">
                                                                    <span class="text-[10px] font-bold text-[#8c8f94] uppercase tracking-wider">Live Preview</span>
                                                                    <span class="text-[10px] text-[#2271b1] font-medium" x-text="fontData.family + ' ' + formatVariant(fontData.variant)"></span>
                                                                </div>
                                                                <div class="preview-text" :style="getPreviewStyle()">
                                                                    1234567890ABCDEFGHIJKLMN OPQRSTUVWXY Zabcdefghijklm nopqrstuvwxyz
                                                                </div>
                                                            </div>
                                                        </div>

                                                    @elseif($type === 'image')
                                                        {{-- Simplified & Reliable Image Picker --}}
                                                        <div class="image-picker-component" x-data="{ 
                                                            imageUrl: '{{ $val }}',
                                                            selectImage() { openCmsMediaModal('field_{{ $key }}') },
                                                            removeImage() { this.imageUrl = ''; document.getElementById('field_{{ $key }}').value = ''; }
                                                        }">
                                                            <div class="space-y-3">
                                                                {{-- Clickable Preview Box --}}
                                                                <div x-on:click="selectImage()"
                                                                      class="w-48 h-48 rounded-lg border-2 border-dashed border-[#c3c4c7] bg-[#f6f7f7] cursor-pointer hover:border-[#2271b1] hover:bg-[#f0f7ff] transition-all overflow-hidden flex items-center justify-center relative group shadow-sm">
                                                                    
                                                                    <template x-if="imageUrl">
                                                                        <img :src="imageUrl" x-on:error="imageUrl = ''" class="w-full h-full object-cover">
                                                                    </template>
                                                                    
                                                                    <template x-if="!imageUrl">
                                                                        <div class="text-center p-4">
                                                                            <span class="material-symbols-outlined text-[#8c8f94] text-4xl block mb-2">add_photo_alternate</span>
                                                                            <span class="text-[11px] font-medium text-[#646970] uppercase">Click to Select Image</span>
                                                                        </div>
                                                                    </template>
                                                                </div>

                                                                {{-- Action Buttons --}}
                                                                <div class="flex items-center gap-4 px-1">
                                                                    <button type="button" x-on:click="selectImage()"
                                                                            class="flex items-center gap-1.5 text-[12px] font-bold text-[#2271b1] hover:text-[#135e96] transition-colors uppercase tracking-tight">
                                                                        <span class="material-symbols-outlined" style="font-size:16px !important;">edit</span>
                                                                        Edit
                                                                    </button>
                                                                    <div x-show="imageUrl" class="w-px h-3 bg-[#c3c4c7]"></div>
                                                                    <button type="button" x-show="imageUrl" x-on:click="removeImage()"
                                                                            class="flex items-center gap-1.5 text-[12px] font-bold text-red-600 hover:text-red-700 transition-colors uppercase tracking-tight">
                                                                        <span class="material-symbols-outlined" style="font-size:16px !important;">delete</span>
                                                                        Remove
                                                                    </button>
                                                                </div>

                                                                <input type="hidden" name="{{ $key }}" id="field_{{ $key }}" :value="imageUrl">
                                                            </div>
                                                        </div>

                                                    @elseif($type === 'color')
                                                        {{-- Pickr picker (same as the Lazy Builder) — swatch button opens the picker, hex syncs to the input --}}
                                                        <div class="flex items-center gap-2.5">
                                                            <button type="button" id="swatch_{{ $key }}"
                                                                    class="cstz-color-swatch"
                                                                    data-color-input="field_{{ $key }}"
                                                                    style="background: {{ $val ?: '#ffffff' }};"
                                                                    aria-label="Choose color"></button>
                                                            <input type="text" name="{{ $key }}" id="field_{{ $key }}"
                                                                   value="{{ $val ?: '' }}"
                                                                   placeholder="#000000"
                                                                   class="wp-input w-[130px] text-[12px] font-mono tracking-wide">
                                                        </div>

                                                    @elseif($type === 'select')
                                                        <select name="{{ $key }}" id="field_{{ $key }}"
                                                                class="wp-input w-[220px] text-[13px] cursor-pointer">
                                                            @foreach(($field['options'] ?? []) as $optVal => $optLabel)
                                                                <option value="{{ $optVal }}" {{ $val == $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                                            @endforeach
                                                        </select>

                                                     @elseif($type === 'multi_select')
                                                         @php
                                                             $mVal = is_array($val) ? $val : (json_decode($val, true) ?: ($field['default'] ?? []));
                                                             $options = $field['options'] ?? [];
                                                         @endphp
                                                         <div x-data='{ 
                                                                  open: false, 
                                                                  selected: {!! json_encode($mVal) !!}, 
                                                                  options: {!! json_encode($options) !!},
                                                                  toggle(opt) {
                                                                      if (this.selected.includes(opt)) {
                                                                          this.selected = this.selected.filter(i => i !== opt);
                                                                      } else {
                                                                          this.selected = [...this.selected, opt];
                                                                      }
                                                                  },
                                                                  remove(opt) {
                                                                      this.selected = this.selected.filter(i => i !== opt);
                                                                  }
                                                              }' 
                                                              class="multi-select-container relative w-[340px]">
                                                             <input type="hidden" name="{{ $key }}" :value="JSON.stringify(selected)">
                                                             
                                                             <div @click="open = !open" 
                                                                  class="wp-input min-h-[36px] h-auto flex flex-wrap items-center gap-1.5 p-1.5 cursor-pointer bg-white border border-[#c3c4c7] rounded shadow-sm hover:border-[#2271b1] transition-all">
                                                                 
                                                                 <template x-for="item in selected" :key="item">
                                                                     <div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-[#f0f0f1] text-[#2c3338] text-[11px] font-medium rounded-full border border-[#dcdcde] group/tag hover:bg-[#e0e0e2] transition-colors">
                                                                         <span x-text="options[item] || item"></span>
                                                                         <button type="button" @click.stop="remove(item)" class="flex items-center justify-center w-3.5 h-3.5 rounded-full hover:bg-[#dcdcde] transition-colors">
                                                                             <span class="material-symbols-outlined !text-[12px] text-[#50575e] group-hover/tag:text-red-600">close</span>
                                                                         </button>
                                                                     </div>
                                                                 </template>
                                                                 
                                                                 <span x-show="selected.length === 0" class="text-[#8c8f94] text-[12px] px-2 italic">Select allowed formats...</span>
                                                                 
                                                                 <div class="ml-auto flex items-center pr-1">
                                                                     <span class="material-symbols-outlined text-[#8c8f94] transition-transform duration-200" :class="open ? 'rotate-180' : ''" style="font-size:18px !important;">expand_more</span>
                                                                 </div>
                                                             </div>

                                                             <div x-show="open" @click.away="open = false" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                                                                  class="absolute z-[110] mt-1 w-full bg-white border border-[#c3c4c7] rounded shadow-xl max-h-[240px] overflow-y-auto custom-scrollbar ring-1 ring-black ring-opacity-5">
                                                                 <template x-for="(label, opt) in options" :key="opt">
                                                                     <div @click="toggle(opt)" 
                                                                          class="px-4 py-2.5 text-[12px] cursor-pointer hover:bg-[#f0f7ff] flex items-center justify-between border-b border-[#f0f0f1] last:border-0 transition-colors"
                                                                          :class="selected.includes(opt) ? 'bg-[#f0f7ff] text-[#2271b1] font-semibold' : 'text-[#1d2327]'">
                                                                         <span x-text="label"></span>
                                                                         <span x-show="selected.includes(opt)" class="material-symbols-outlined text-[16px] text-[#2271b1]">check_circle</span>
                                                                     </div>
                                                                 </template>
                                                             </div>
                                                         </div>

                                                    @elseif($type === 'button_group')
                                                        {{-- Segmented control — PHP sets initial state, JS updates on change --}}
                                                        <div class="cstz-btn-group inline-flex border border-[#c3c4c7] overflow-hidden">
                                                            @foreach(($field['options'] ?? []) as $optVal => $optLabel)
                                                                <label class="cursor-pointer">
                                                                    <input type="radio" name="{{ $key }}" value="{{ $optVal }}"
                                                                           {{ $val == $optVal ? 'checked' : '' }}
                                                                           class="sr-only"
                                                                           onchange="customizerBtnGroup(this)">
                                                                    <span class="cstz-btn-opt block px-6 py-[7px] text-[12px] font-medium select-none border-r border-[#c3c4c7] last:border-r-0 transition-colors"
                                                                          style="{{ $val == $optVal ? 'background:#2271b1;color:#fff;' : 'background:#f6f7f7;color:#50575e;' }}">
                                                                        {{ $optLabel }}
                                                                    </span>
                                                                </label>
                                                            @endforeach
                                                        </div>

                                                    @elseif($type === 'toggle')
                                                        <label for="toggle_{{ $key }}" class="flex items-center gap-3 cursor-pointer select-none">
                                                            <div class="relative w-11 h-6 flex-shrink-0">
                                                                <input type="hidden" name="{{ $key }}" id="hidden_{{ $key }}" value="{{ $val == '1' ? '1' : '0' }}">
                                                                <input type="checkbox" id="toggle_{{ $key }}" class="sr-only peer"
                                                                       {{ $val == '1' ? 'checked' : '' }}
                                                                       onchange="
                                                                           document.getElementById('hidden_{{ $key }}').value = this.checked ? '1' : '0';
                                                                           var lbl = document.getElementById('tlabel_{{ $key }}');
                                                                           lbl.textContent = this.checked ? 'Enabled' : 'Disabled';
                                                                           lbl.style.color = this.checked ? '#2271b1' : '#8c8f94';
                                                                       ">
                                                                <span class="absolute inset-0 rounded-full transition-colors duration-200 bg-[#d1d5db] peer-checked:bg-[#2271b1]"></span>
                                                                <span class="absolute top-[3px] left-[3px] w-[18px] h-[18px] rounded-full bg-white shadow-sm transition-transform duration-200 peer-checked:translate-x-5"></span>
                                                            </div>
                                                            <span id="tlabel_{{ $key }}"
                                                                  style="font-size:13px; font-weight:500; color:{{ $val == '1' ? '#2271b1' : '#8c8f94' }};">
                                                                {{ $val == '1' ? 'Enabled' : 'Disabled' }}
                                                            </span>
                                                        </label>

                                                    @elseif($type === 'textarea')
                                                        <textarea name="{{ $key }}" id="field_{{ $key }}"
                                                                  rows="4"
                                                                  placeholder="{{ $field['placeholder'] ?? '' }}"
                                                                  class="wp-input w-[340px] text-[13px] resize-y pt-2 leading-relaxed">{{ $val }}</textarea>

                                                    @elseif($type === 'action_button')
                                                        <div class="flex flex-col gap-2">
                                                            <button type="button" 
                                                                    @click="runAction('{{ $field['action'] }}', $event)"
                                                                    class="wp-btn-secondary px-6 self-start">
                                                                {{ $field['text'] ?? 'Run Action' }}
                                                            </button>
                                                            @if(!empty($field['desc']))
                                                                <p class="text-[11px] text-[#646970] leading-relaxed max-w-[340px] italic">
                                                                    {!! $field['desc'] !!}
                                                                </p>
                                                            @endif
                                                        </div>

                                                     @elseif($type === 'range')
                                                        {{-- Range/Slider field --}}
                                                        <div class="flex items-center gap-4 w-[340px]">
                                                            <input type="range" name="{{ $key }}" id="range_{{ $key }}"
                                                                   min="{{ $field['min'] ?? 0 }}"
                                                                   max="{{ $field['max'] ?? 2000 }}"
                                                                   step="{{ $field['step'] ?? 1 }}"
                                                                   value="{{ $val }}"
                                                                   class="flex-1 accent-[#2271b1] cursor-pointer h-1.5 bg-gray-200 rounded-lg appearance-none"
                                                                   oninput="document.getElementById('range_val_{{ $key }}').value = this.value; if('{{ $key }}' === 'theme_medium_screen_breakpoint') { var el = document.getElementById('large-screen-bp-val'); if(el) el.textContent = this.value; }">
                                                            <input type="number" id="range_val_{{ $key }}" value="{{ $val }}"
                                                                   class="wp-input w-16 h-8 text-center text-[12px]"
                                                                   oninput="document.getElementById('range_{{ $key }}').value = this.value; if('{{ $key }}' === 'theme_medium_screen_breakpoint') { var el = document.getElementById('large-screen-bp-val'); if(el) el.textContent = this.value; }">
                                                        </div>

                                                    @elseif($type === 'css' || $type === 'script')
                                                        {{-- Monaco Editor for CSS/JS --}}
                                                        <div class="monaco-wrapper border border-[#3c434a] rounded overflow-hidden shadow-inner" style="height: 500px;">
                                                            <div id="monaco-editor-{{ $key }}" class="w-full h-full"></div>
                                                            <textarea name="{{ $key }}" id="field_{{ $key }}" class="hidden">{{ $val }}</textarea>
                                                        </div>
                                                        <script>
                                                            window.addEventListener('load', function() {
                                                                initMonaco('{{ $key }}', '{{ $type === "css" ? "css" : "javascript" }}');
                                                            });
                                                        </script>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @endif
                            </div>
                        @endif

                    @endforeach

                    {{-- Search results --}}
                    <div x-show="search.length > 0 && visibleCount > 0">
                        <table class="w-full border-collapse">
                            <tbody>
                                @foreach($sections as $sectionKey => $sec)
                                    @if($sectionKey !== 'import_export')
                                        @foreach($sec['fields'] as $key => $field)
                                            @php
                                                $label = $field['label'] ?? $key;
                                                $desc  = $field['desc']  ?? '';
                                            @endphp
                                            <tr class="search-field-row border-b border-[#f0f0f1] hover:bg-[#f6f7f7]/60 transition-colors hidden"
                                                data-search="{{ strtolower($label . ' ' . $desc . ' ' . $sec['title']) }}">
                                                <th scope="row" class="w-[260px] text-left align-top px-5 py-3.5">
                                                    <span class="text-[10px] font-semibold text-[#8c8f94] uppercase tracking-wide block mb-0.5">{{ $sec['title'] }}</span>
                                                    <span class="text-[13px] font-semibold text-[#2271b1] block mb-1">{{ $label }}</span>
                                                    @if($desc)
                                                        <p class="text-[11px] text-[#646970] leading-relaxed m-0">{!! $desc !!}</p>
                                                    @endif
                                                </th>
                                                <td class="px-5 py-3.5 align-middle text-[12px] text-[#646970] italic">
                                                    Go to
                                                    <button type="button"
                                                            @click="switchSection('{{ $sectionKey }}'); search = ''; filterFields();"
                                                            class="text-[#2271b1] underline not-italic cursor-pointer font-medium">{{ $sec['title'] }}</button>
                                                    to edit this field.
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </form>
            </div>

            {{-- ===== STICKY FOOTER BAR ===== --}}
            <div class="flex-shrink-0 border-t border-[#c3c4c7] bg-[#f6f7f7] px-5 py-2.5 flex items-center gap-2">
                <span class="text-[11px] text-[#8c8f94] mr-auto">All sections saved together.</span>
                <button type="button" @click="ajaxReset('all')" class="wp-btn-secondary h-8 px-3 text-[12px]">Reset All</button>
                <button type="button" @click="ajaxReset('section')" class="wp-btn-secondary h-8 px-3 text-[12px]">Reset Section</button>
                <button type="button" @click="ajaxSave()"
                        class="wp-btn-primary h-8 px-5 text-[12px] font-semibold flex items-center gap-1.5">
                    <span class="material-symbols-outlined" id="save-icon" style="font-size:15px !important;">save</span>
                    <span id="save-label">Save Changes</span>
                </button>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="{{ asset('vendor/cms-dashboard/css/pickr.classic.min.css') }}">
<script src="{{ asset('vendor/cms-dashboard/js/pickr.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
<script>
// Field dependency: rows with data-depends="<masterKey>" are only active when the
// master toggle (#toggle_<masterKey>) is ON. When OFF they grey out and stop responding.
function initCustomizerDeps() {
    // Toggle-based dependency (existing behaviour)
    document.querySelectorAll('[data-depends]').forEach(function (row) {
        var masterKey = row.getAttribute('data-depends');
        var master = document.getElementById('toggle_' + masterKey);
        if (!master || row.dataset.depInit) return;
        row.dataset.depInit = '1';
        var apply = function () {
            var on = master.checked;
            row.style.opacity = on ? '' : '0.45';
            row.style.pointerEvents = on ? '' : 'none';
            row.setAttribute('aria-disabled', on ? 'false' : 'true');
        };
        master.addEventListener('change', apply);
        apply();
    });

    // Value-based dependency (button_group / select master)
    document.querySelectorAll('[data-depends-on]').forEach(function (row) {
        if (row.dataset.depInit) return;
        row.dataset.depInit = '1';
        var masterKey   = row.getAttribute('data-depends-on');
        var targetValue = row.getAttribute('data-depends-value');
        var radios      = document.querySelectorAll('[name="' + masterKey + '"]');
        var selectEl    = document.getElementById('field_' + masterKey);
        var getValue = function () {
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) return radios[i].value;
            }
            return selectEl ? selectEl.value : '';
        };
        var apply = function () {
            var on = getValue() === targetValue;
            row.style.opacity      = on ? '' : '0.4';
            row.style.pointerEvents = on ? '' : 'none';
            row.setAttribute('aria-disabled', on ? 'false' : 'true');
        };
        radios.forEach(function (r) { r.addEventListener('change', apply); });
        if (selectEl) selectEl.addEventListener('change', apply);
        apply();
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCustomizerDeps);
else initCustomizerDeps();

// Monaco Configuration
require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});
window.monacoEditors = {};

function initMonaco(fieldId, language) {
    const container = document.getElementById('monaco-editor-' + fieldId);
    const textarea = document.getElementById('field_' + fieldId);
    if (!container || !textarea) return;

    require(['vs/editor/editor.main'], function() {
        const editor = monaco.editor.create(container, {
            value: textarea.value,
            language: language,
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            fontSize: 13,
            lineHeight: 20,
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            formatOnPaste: true,
            formatOnType: true,
            suggestOnTriggerCharacters: true,
            quickSuggestions: true,
            colorDecorators: true
        });

        editor.onDidChangeModelContent(() => {
            textarea.value = editor.getValue();
            // Dispatch input event to notify Alpine.js or other listeners
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });

        window.monacoEditors[fieldId] = editor;
    });
}

// Media Modal Integration
function openCmsMediaModal(fieldId) {
    // Search for a global media picker instance
    const picker = window.cmsMediaPicker || window.mediaPicker || (window.CMSMediaModal ? CMSMediaModal : null);
    
    const updateImageField = (attachment) => {
        // Try multiple properties for the URL
        let url = attachment.full_url || attachment.url || attachment.path || attachment.guid || '';
        
        if (!url) {
            console.error('Media selection failed: No URL found in attachment object', attachment);
            return;
        }

        // Specific fix for Laravel storage: if it starts with 'media/', it needs '/storage/'
        if (url.startsWith('media/')) {
            url = '/storage/' + url;
        } else if (!url.startsWith('http') && !url.startsWith('/')) {
            // General fallback for other relative paths
            url = '/' + url;
        }

        const input = document.getElementById(fieldId);
        if (input) {
            input.value = url;
            
            // Try to find the Alpine.js component scope and update it
            const component = input.closest('.image-picker-component');
            if (component && window.Alpine) {
                const data = Alpine.$data(component);
                if (data) {
                    data.imageUrl = url;
                }
            }
            
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    if (picker && typeof picker.open === 'function') {
        picker.open(updateImageField);
    } else if (typeof window.openMediaModal === 'function') {
        window.openMediaModal(updateImageField);
    } else {
        window.showToast('Media picker not available. Please ensure the media modal component is loaded.', 'error');
    }
}

// Color picker — uses Pickr (the same picker as the Lazy Builder).
function cstzHexFromPickr(color) {
    var s = color.toHEXA().toString();           // #RRGGBB or #RRGGBBAA
    if (/^#([0-9a-fA-F]{6})ff$/i.test(s)) s = s.slice(0, 7); // drop alpha when fully opaque
    return s.toLowerCase();
}
function initCustomizerColorPickers() {
    if (typeof Pickr === 'undefined') return;
    document.querySelectorAll('.cstz-color-swatch').forEach(function (btn) {
        if (btn.dataset.pickrInit) return;
        btn.dataset.pickrInit = '1';
        var input = document.getElementById(btn.getAttribute('data-color-input'));
        if (!input) return;

        var pickr = Pickr.create({
            el: btn,
            useAsButton: true,
            theme: 'classic',
            default: input.value || '#ffffff',
            defaultRepresentation: 'HEXA',
            components: {
                preview: true, opacity: true, hue: true,
                interaction: { hex: true, rgba: false, input: true, clear: false, save: true }
            },
            swatches: [
                '#000000', '#ffffff', '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5',
                '#2196f3', '#03a6f4', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39'
            ]
        });

        var applyColor = function (color) {
            var hex = cstzHexFromPickr(color);
            input.value = hex;
            btn.style.background = hex;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        pickr.on('change', function (color) { applyColor(color); })
             .on('save', function (color, instance) { if (color) applyColor(color); instance.hide(); });

        // Typing a hex in the input updates the swatch + picker.
        input.addEventListener('input', function () {
            var v = input.value.trim();
            btn.style.background = v || '#ffffff';
            if (/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(v)) {
                try { pickr.setColor(v, true); } catch (e) {}
            }
        });
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCustomizerColorPickers);
else initCustomizerColorPickers();

// Segmented button group: update visual state on radio change
function customizerBtnGroup(radio) {
    const group = radio.closest('.cstz-btn-group');
    group.querySelectorAll('.cstz-btn-opt').forEach(span => {
        span.style.background = '#f6f7f7';
        span.style.color = '#50575e';
    });
    radio.nextElementSibling.style.background = '#2271b1';
    radio.nextElementSibling.style.color = '#fff';
}

function customizerApp(initialSection) {
    return {
        activeSection: initialSection || 'layout',
        search: '',
        visibleCount: 0,

        init() {
            const hash   = window.location.hash.replace('#', '');
            const stored = localStorage.getItem('customizer-section');
            if (hash) {
                this.activeSection = hash;
            } else if (stored) {
                this.activeSection = stored;
            }
        },

        switchSection(key) {
            this.activeSection = key;
            localStorage.setItem('customizer-section', key);
            history.replaceState(null, '', window.location.pathname + '#' + key);
            this.search = '';
            this.filterFields();
        },

        filterFields() {
            const q = this.search.toLowerCase().trim();
            if (!q) {
                document.querySelectorAll('.search-field-row').forEach(r => r.classList.add('hidden'));
                this.visibleCount = 0;
                return;
            }
            let count = 0;
            document.querySelectorAll('.search-field-row').forEach(row => {
                const show = (row.dataset.search || '').includes(q);
                row.classList.toggle('hidden', !show);
                if (show) count++;
            });
            this.visibleCount = count;
        },

        getCsrf() {
            return document.querySelector('#customizer-form input[name="_token"]')?.value || '';
        },

        async ajaxSave() {
            const form     = document.getElementById('customizer-form');
            const formData = new FormData(form);
            formData.set('_section', this.activeSection);

            const icon  = document.getElementById('save-icon');
            const label = document.getElementById('save-label');
            icon.textContent  = 'hourglass_top';
            label.textContent = 'Saving…';

            try {
                const res  = await fetch('{{ route("admin.customizer.save") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.showToast(data.message || 'Saved.', data.success ? 'success' : 'error');
            } catch {
                this.showToast('Error saving settings. Please try again.', 'error');
            } finally {
                icon.textContent  = 'save';
                label.textContent = 'Save Changes';
            }
        },

        async runAction(action, event) {
            let confirmed = false;
            if (action === 'optimizeImages') {
                confirmed = await window.lazyConfirm({
                    title: 'Optimize Images',
                    message: 'Caution: This will replace all existing original images with optimized versions. This process cannot be undone. Continue?',
                    confirmText: 'Yes, Optimize All',
                    isDanger: true
                });
            } else {
                confirmed = await window.lazyConfirm({
                    title: 'Run Action',
                    message: 'Are you sure you want to run this action?',
                    confirmText: 'Run Action',
                    isDanger: false
                });
            }

            if (!confirmed) return;

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin inline-block mr-2">↻</span> Processing...';

            try {
                const res = await fetch(`{{ url('admin/appearance/customizer/action') }}/${action}`, {
                    method: 'POST',
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                this.showToast(data.message || 'Action completed.', data.success ? 'success' : 'error');
            } catch (err) {
                this.showToast('Error executing action: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },

        async ajaxReset(type) {
            const title = type === 'all' ? 'Reset All Settings' : 'Reset Section';
            const msg = type === 'all'
                ? 'Reset ALL settings to defaults? This cannot be undone.'
                : 'Reset this section to default values?';
            
            const confirmed = await window.lazyConfirm({
                title: title,
                message: msg,
                confirmText: 'Yes, Reset',
                isDanger: true
            });

            if (!confirmed) return;

            const fd = new FormData();
            fd.append('_token', this.getCsrf());
            if (type === 'all') {
                fd.append('all', '1');
            } else {
                fd.append('section', this.activeSection);
            }

            try {
                const res  = await fetch('{{ route("admin.customizer.reset") }}', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.showToast(data.message || 'Reset.', data.success ? 'success' : 'error');
                if (data.success && data.reload) {
                    setTimeout(() => window.location.reload(), 900);
                }
            } catch {
                this.showToast('Error resetting settings. Please try again.', 'error');
            }
        },

        showToast(message, type) {
            const container = document.getElementById('customizer-toast');
            const el = document.createElement('div');
            const ok = type === 'success';
            el.style.cssText = 'pointer-events:auto; opacity:0; transform:translateY(-8px); transition:opacity .2s,transform .2s;';
            el.className = `flex items-center gap-2 px-4 py-3 rounded-lg shadow-xl text-[13px] font-medium text-white ${ok ? 'bg-[#00a32a]' : 'bg-[#b32d2e]'}`;
            el.innerHTML = `<span class="material-symbols-outlined" style="font-size:17px !important;">${ok ? 'check_circle' : 'error'}</span><span>${message}</span>`;
            container.appendChild(el);
            requestAnimationFrame(() => {
                el.style.opacity   = '1';
                el.style.transform = 'translateY(0)';
            });
            setTimeout(() => {
                el.style.opacity   = '0';
                el.style.transform = 'translateY(-8px)';
                setTimeout(() => el.remove(), 220);
            }, 3200);
        },

    };
}

// Customizer Import — drag & drop + file feedback
function handleCustImportSelect(input) {
    const zone  = document.getElementById('cust-import-drop-zone');
    const label = document.getElementById('cust-import-label');
    const icon  = document.getElementById('cust-import-icon');
    const btn   = document.getElementById('cust-import-btn');
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    label.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    label.style.color = '#2271b1';
    icon.textContent  = 'check_circle';
    icon.style.color  = '#46b450';
    zone.style.borderColor = '#46b450';
    btn.disabled = false;
    btn.classList.remove('opacity-50', 'pointer-events-none');
}

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const zone = document.getElementById('cust-import-drop-zone');
        if (!zone) return;
        zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.borderColor = '#2271b1'; });
        zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.style.borderColor = '';
            const input = document.getElementById('cust-import-file');
            if (e.dataTransfer && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                handleCustImportSelect(input);
            }
        });
    });
})();

async function custConfirmImport() {
    const confirmed = await window.lazyConfirm({
        title:       'Import Settings',
        message:     'This will overwrite your current theme settings with the ones from the uploaded file. Continue?',
        confirmText: 'Import Now',
        isDanger:    true
    });
    if (confirmed) document.getElementById('import-settings-form').submit();
}

// Typography Component Logic
const GOOGLE_FONTS = {
    'Sans-serif': [
        { family: 'Inter', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Roboto', variants: ['100', '300', '400', '500', '700', '900'] },
        { family: 'Open Sans', variants: ['300', '400', '500', '600', '700', '800'] },
        { family: 'Lato', variants: ['100', '300', '400', '700', '900'] },
        { family: 'Poppins', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Nunito', variants: ['200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Montserrat', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Source Sans 3', variants: ['200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Raleway', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Ubuntu', variants: ['300', '400', '500', '700'] },
        { family: 'Oswald', variants: ['200', '300', '400', '500', '600', '700'] },
        { family: 'Quicksand', variants: ['300', '400', '500', '600', '700'] },
        { family: 'PT Sans', variants: ['400', '700'] },
        { family: 'Mukta', variants: ['200', '300', '400', '500', '600', '700', '800'] },
        { family: 'Work Sans', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Nanum Gothic', variants: ['400', '700', '800'] },
        { family: 'Noto Sans', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Rubik', variants: ['300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Heebo', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Karla', variants: ['200', '300', '400', '500', '600', '700', '800'] },
        { family: 'Arimo', variants: ['400', '500', '600', '700'] },
        { family: 'Cabin', variants: ['400', '500', '600', '700'] },
        { family: 'Libre Franklin', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Barlow', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'DM Sans', variants: ['400', '500', '700'] },
        { family: 'Questrial', variants: ['400'] },
        { family: 'Cairo', variants: ['200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Titillium Web', variants: ['200', '300', '400', '600', '700', '900'] },
        { family: 'Hind', variants: ['300', '400', '500', '600', '700'] },
        { family: 'Josefin Sans', variants: ['100', '200', '300', '400', '500', '600', '700'] },
        { family: 'Public Sans', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Signika', variants: ['300', '400', '500', '600', '700'] },
        { family: 'Exo 2', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Maven Pro', variants: ['400', '500', '600', '700', '800', '900'] },
        { family: 'Assistant', variants: ['200', '300', '400', '500', '600', '700', '800'] },
        { family: 'Oxygen', variants: ['300', '400', '700'] },
        { family: 'Fira Sans', variants: ['100', '200', '300', '400', '500', '600', '700', '800', '900'] }
    ],
    'Serif': [
        { family: 'Playfair Display', variants: ['400', '500', '600', '700', '800', '900'] },
        { family: 'Merriweather', variants: ['300', '400', '700', '900'] },
        { family: 'Lora', variants: ['400', '500', '600', '700'] },
        { family: 'PT Serif', variants: ['400', '700'] },
        { family: 'Libre Baskerville', variants: ['400', '700'] },
        { family: 'Crimson Text', variants: ['400', '600', '700'] },
        { family: 'Arvo', variants: ['400', '700'] },
        { family: 'Bitter', variants: ['400', '700'] },
        { family: 'EB Garamond', variants: ['400', '500', '600', '700', '800'] },
        { family: 'Noticia Text', variants: ['400', '700'] },
        { family: 'Old Standard TT', variants: ['400', '700'] },
        { family: 'Cardo', variants: ['400'] }
    ],
    'Monospace': [
        { family: 'Fira Code', variants: ['300', '400', '500', '600', '700'] },
        { family: 'Source Code Pro', variants: ['200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Roboto Mono', variants: ['100', '200', '300', '400', '500', '600', '700'] },
        { family: 'Inconsolata', variants: ['200', '300', '400', '500', '600', '700', '800', '900'] },
        { family: 'Ubuntu Mono', variants: ['400', '700'] },
        { family: 'Space Mono', variants: ['400', '700'] },
        { family: 'VT323', variants: ['400'] }
    ],
    'Display': [
        { family: 'Lobster', variants: ['400'] },
        { family: 'Pacifico', variants: ['400'] },
        { family: 'Dancing Script', variants: ['400', '500', '600', '700'] },
        { family: 'Abril Fatface', variants: ['400'] },
        { family: 'Righteous', variants: ['400'] },
        { family: 'Comfortaa', variants: ['300', '400', '500', '600', '700'] },
        { family: 'Bebas Neue', variants: ['400'] },
        { family: 'Caveat', variants: ['400', '500', '600', '700'] },
        { family: 'Satisfy', variants: ['400'] },
        { family: 'Patua One', variants: ['400'] }
    ]
};

function typographyComponent(key, initialData) {
    return {
        key: key,
        open: false,
        fontSearch: '',
        fontData: initialData || {
            family: 'Inter',
            variant: '400',
            size: '15px',
            line_height: '1.6',
            letter_spacing: '0px',
            text_transform: 'none',
            text_decoration: 'none',
            font_style: 'normal'
        },
        
        init() {
            if (this.fontData.family) {
                this.loadFont(this.fontData.family);
            }
        },

        get currentVariants() {
            for (const cat in GOOGLE_FONTS) {
                const found = GOOGLE_FONTS[cat].find(f => f.family === this.fontData.family);
                if (found) return found.variants;
            }
            return ['400'];
        },

        get filteredFonts() {
            if (!this.fontSearch) return GOOGLE_FONTS;
            const q = this.fontSearch.toLowerCase();
            const filtered = {};
            for (const cat in GOOGLE_FONTS) {
                const matches = GOOGLE_FONTS[cat].filter(f => f.family.toLowerCase().includes(q));
                if (matches.length) filtered[cat] = matches;
            }
            return filtered;
        },

        selectFont(font) {
            this.fontData.family = font.family;
            if (!font.variants.includes(this.fontData.variant)) {
                this.fontData.variant = font.variants.includes('400') ? '400' : font.variants[0];
            }
            this.loadFont(font.family);
            this.open = false;
        },

        loadFont(family) {
            const linkId = 'font-' + family.replace(/\s+/g, '-').toLowerCase();
            if (document.getElementById(linkId)) return;
            const link = document.createElement('link');
            link.id = linkId;
            link.rel = 'stylesheet';
            link.href = `https://fonts.googleapis.com/css2?family=${family.replace(/\s+/g, '+')}:wght@100;200;300;400;500;600;700;800;900&display=swap`;
            document.head.appendChild(link);
        },

        formatVariant(v) {
            const labels = {
                '100': 'Thin (100)',
                '200': 'Extra Light (200)',
                '300': 'Light (300)',
                '400': 'Regular (400)',
                '500': 'Medium (500)',
                '600': 'Semi Bold (600)',
                '700': 'Bold (700)',
                '800': 'Extra Bold (800)',
                '900': 'Black (900)'
            };
            return labels[v] || v;
        },

        getTransformIcon(opt) {
            const icons = { 'none': 'do_not_disturb_on', 'capitalize': 'match_case', 'uppercase': 'uppercase', 'lowercase': 'lowercase' };
            return icons[opt] || 'text_fields';
        },

        getDecorIcon(opt) {
            const icons = { 'none': 'format_clear', 'underline': 'format_underlined', 'line-through': 'format_strikethrough' };
            return icons[opt] || 'text_fields';
        },

        toggleStyle(type) {
            if (type === 'italic') {
                this.fontData.font_style = this.fontData.font_style === 'italic' ? 'normal' : 'italic';
            } else if (type === 'none') {
                this.fontData.text_decoration = 'none';
                this.fontData.font_style = 'normal';
            } else {
                this.fontData.text_decoration = this.fontData.text_decoration === type ? 'none' : type;
            }
        },

        getPreviewStyle() {
            return {
                fontFamily: `'${this.fontData.family}', sans-serif`,
                fontWeight: this.fontData.variant,
                fontSize: this.fontData.size,
                lineHeight: this.fontData.line_height,
                letterSpacing: this.fontData.letter_spacing,
                textTransform: this.fontData.text_transform,
                textDecoration: this.fontData.text_decoration,
                fontStyle: this.fontData.font_style,
                transition: 'all 0.2s'
            };
        }
    };
}
</script>

<style>
/* Customizer Panel Width - Set to 70% */
#customizer-app {
    max-width: 70% !important;
    margin: 20px auto;
}
/* Customizer scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #c3c4c7; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #8c8f94; }

/* Preview Text */
.preview-text {
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #1d2327;
    background: #fdfdfd;
    border-radius: 4px;
    padding: 20px;
}

/* Pickr color picker */
.pcr-app { z-index: 99999 !important; }

/* Compact Pickr popup — scaled down proportionally so hex/save/clear buttons stay intact */
.pcr-app[data-theme="classic"] {
    font-size: 11px;            /* em-based theme → shrinks the whole widget uniformly */
    width: 20em !important;
    padding: 0.55em !important;
    border-radius: 6px;
}
.pcr-app[data-theme="classic"] .pcr-selection { height: 7.5em; }
.pcr-app[data-theme="classic"] .pcr-selection .pcr-color-preview { width: 1.4em; margin-right: 0.6em; }
.pcr-app[data-theme="classic"] .pcr-selection .pcr-color-chooser,
.pcr-app[data-theme="classic"] .pcr-selection .pcr-color-opacity { width: 1.4em; margin-left: 0.6em; }
.pcr-app[data-theme="classic"] .pcr-swatches { margin-top: 0.5em; }
.pcr-app[data-theme="classic"] .pcr-swatches button { width: 1.35em; height: 1.35em; }
.pcr-app[data-theme="classic"] .pcr-interaction { margin-top: 0.5em; }
.pcr-app[data-theme="classic"] .pcr-interaction .pcr-result { min-height: 1.8em; font-size: 0.95em; padding: 0.2em 0.45em; }
.pcr-app[data-theme="classic"] .pcr-interaction .pcr-type,
.pcr-app[data-theme="classic"] .pcr-interaction .pcr-save,
.pcr-app[data-theme="classic"] .pcr-interaction .pcr-clear,
.pcr-app[data-theme="classic"] .pcr-interaction .pcr-cancel { padding: 0.3em 0.5em; font-size: 0.9em; }

/* Color swatch button (opens the Pickr picker) */
.cstz-color-swatch {
    width: 28px; height: 28px; border-radius: 4px; border: 1px solid #c3c4c7;
    cursor: pointer; flex-shrink: 0; padding: 0;
    box-shadow: inset 0 0 0 2px #fff;
    transition: border-color .15s, transform .1s;
}
.cstz-color-swatch:hover { border-color: #2271b1; }
.cstz-color-swatch:active { transform: scale(0.95); }
/* Segmented btn hover (non-active) */
.cstz-btn-opt:hover { filter: brightness(0.97); }
</style>
@endpush
</x-falcon-cms::layouts.admin>
