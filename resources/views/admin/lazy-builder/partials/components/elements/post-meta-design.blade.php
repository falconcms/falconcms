<div class="space-y-6">
    <!-- Alignment -->
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Alignment</label>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.metaAlign = 'left'"
                    :class="(editingElement.settings.metaAlign || 'left') === 'left' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">
                <i class="fa fa-align-left"></i>
            </button>
            <button @click="editingElement.settings.metaAlign = 'center'"
                    :class="editingElement.settings.metaAlign === 'center' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">
                <i class="fa fa-align-center"></i>
            </button>
            <button @click="editingElement.settings.metaAlign = 'right'"
                    :class="editingElement.settings.metaAlign === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-200 last:border-r-0 transition-all">
                <i class="fa fa-align-right"></i>
            </button>
        </div>
    </div>

    <!-- Typography -->
    <div class="pt-4 border-t border-slate-50 space-y-4">
        <div class="flex justify-between items-center mb-1">
            <label class="text-[12px] font-bold text-[#333]">Typography</label>
        </div>
        @include('falcon-cms::admin.lazy-builder.partials.components.fields.typography', ['prefix' => 'meta'])
    </div>

    <!-- Text Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Text Color</label>
            <button @click="editingElement.settings.color = '#6b7280'" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'color')">
                <div :style="{ backgroundColor: editingElement.settings.color || '#6b7280' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.color"
                   class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>

    <!-- Link Hover Color -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Link Hover Color</label>
            <button @click="editingElement.settings.linkColor = '#374151'" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
        </div>
        <p class="text-[11px] text-slate-400 mb-2">Applied when hovering over category, tag, and author links.</p>
        <div class="flex gap-2 items-center">
            <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 @click="openColorPicker($event, editingElement.settings, 'linkColor')">
                <div :style="{ backgroundColor: editingElement.settings.linkColor || '#374151' }" class="w-full h-full"></div>
            </div>
            <input type="text" v-model="editingElement.settings.linkColor"
                   class="flex-1 border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>

    <!-- Gap (inline mode only) -->
    <template v-if="(editingElement.settings.layout || 'inline') === 'inline'">
        <div class="pt-4 border-t border-slate-50">
            <label class="text-[12px] font-bold text-[#333] block mb-3">Item Gap</label>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <input type="number" v-model.number="editingElement.settings.gap" min="0" max="60"
                           class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <select v-model="editingElement.settings.gapUnit"
                            class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                        <option value="px">px</option>
                        <option value="rem">rem</option>
                        <option value="em">em</option>
                    </select>
                </div>
            </div>
        </div>
    </template>

    <!-- Margin Top / Bottom -->
    <div class="pt-4 border-t border-slate-50">
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">Margin</label>
            <div class="flex gap-1 items-center">
                <button @click="['Top','Bottom'].forEach(s => setResponsiveVal(editingElement.settings, 'margin' + s, device, ''))" title="Reset" class="text-slate-300 hover:text-red-500 transition-colors"><i class="fa fa-undo text-[10px]"></i></button>
                <div class="relative inline-block">
                    <button @click="activeResponsiveMenu = activeResponsiveMenu === 'postMetaMargin' ? null : 'postMetaMargin'" class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                        <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                        <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                    </button>
                    <div v-show="activeResponsiveMenu === 'postMetaMargin'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                        <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)"><i class="fa fa-desktop text-[11px]"></i></button>
                        <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)"><i class="fa fa-tablet-alt text-[11px]"></i></button>
                        <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)"><i class="fa fa-mobile-alt text-[11px]"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Top</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginTop' : 'marginTop_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginTop', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginTopUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginTopUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center">Bottom</label>
                <div class="flex border border-slate-200 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-[#0091ea]/20 focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings[device === 'desktop' ? 'marginBottom' : 'marginBottom_' + device]" :placeholder="getResponsiveVal(editingElement.settings, 'marginBottom', device) || '0'" class="w-full h-8 px-1 text-[11px] text-center border-none focus:ring-0">
                    <select :value="getResponsiveVal(editingElement.settings, 'marginBottomUnit', device) || 'px'" @change="setResponsiveVal(editingElement.settings, 'marginBottomUnit', device, $event.target.value)" class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center"><option value="px">px</option><option value="rem">rem</option><option value="%">%</option></select>
                </div>
            </div>
        </div>
    </div>
</div>
