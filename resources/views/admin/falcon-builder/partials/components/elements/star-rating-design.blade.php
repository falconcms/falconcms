<div class="space-y-5">

    <!-- Star Size -->
    <div>
        <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Star Size (px)</label>
        <input type="number" v-model.number="editingElement.settings.starSize" min="10" max="120" step="2"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
    </div>

    <!-- Star Colors -->
    <div class="space-y-3">
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Filled Star Color</label>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'starColor')">
                    <div :style="{ backgroundColor: editingElement.settings.starColor }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.starColor" placeholder="#f59e0b"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Empty Star Color</label>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'emptyColor')">
                    <div :style="{ backgroundColor: editingElement.settings.emptyColor }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.emptyColor" placeholder="#d1d5db"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- ── Label Typography ── -->
    <div v-if="editingElement.settings.label" class="border-t border-slate-50 pt-4 space-y-4">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Label Typography</label>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
            <font-select v-model="editingElement.settings.labelFontFamily" @change="loadBuilderFont($event)" :font-groups="builderFontGroups" :theme-font="themeBodyFont"></font-select>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
            <select v-model="editingElement.settings.labelFontWeight"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <option v-for="v in titleFontVariants" :key="v" :value="v">@{{ ({
                    '100':'Thin 100','200':'Extra Light 200','300':'Light 300',
                    '400':'Regular 400','500':'Medium 500','600':'Semi Bold 600',
                    '700':'Bold 700','800':'Extra Bold 800','900':'Black 900'
                })[v] || ('Weight ' + v) }}</option>
            </select>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Font Size</label>
                <input type="text" :value="fsDisplay(editingElement.settings, 'labelFontSize')" @input="fsInput(editingElement.settings, 'labelFontSize', $event.target.value)"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Height</label>
                <input type="text" v-model="editingElement.settings.labelLineHeight" placeholder="1.4"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
                <input type="text" :value="fsDisplay(editingElement.settings, 'labelLetterSpacing')" @input="fsInput(editingElement.settings, 'labelLetterSpacing', $event.target.value)" placeholder="0px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.labelTextTransform = 'none'"
                        :class="(editingElement.settings.labelTextTransform || 'none') === 'none' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
                <button @click="editingElement.settings.labelTextTransform = 'uppercase'"
                        :class="editingElement.settings.labelTextTransform === 'uppercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">AB</button>
                <button @click="editingElement.settings.labelTextTransform = 'lowercase'"
                        :class="editingElement.settings.labelTextTransform === 'lowercase' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">ab</button>
                <button @click="editingElement.settings.labelTextTransform = 'capitalize'"
                        :class="editingElement.settings.labelTextTransform === 'capitalize' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold transition-all">Ab</button>
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Color</label>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'labelColor')">
                    <div :style="{ backgroundColor: editingElement.settings.labelColor }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.labelColor" placeholder="#6b7280"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- ── Spacing ── -->
    <div class="border-t border-slate-50 pt-4 space-y-3">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Spacing</label>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Margin Top</label>
                <input type="text" :value="spDisplay(editingElement.settings, 'marginTop', 'desktop', true)" @input="spInput(editingElement.settings, 'marginTop', 'desktop', $event.target.value, true)" :placeholder="spHint(editingElement.settings, 'marginTop', 'desktop', true, '0')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Margin Bottom</label>
                <input type="text" :value="spDisplay(editingElement.settings, 'marginBottom', 'desktop', true)" @input="spInput(editingElement.settings, 'marginBottom', 'desktop', $event.target.value, true)" :placeholder="spHint(editingElement.settings, 'marginBottom', 'desktop', true, '0')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

</div>
