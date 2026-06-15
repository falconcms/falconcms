<div class="space-y-5">

    <!-- Alignment -->
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">Alignment</label>
        <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
            <button @click="editingElement.settings.textAlign = 'left'"
                    :class="(editingElement.settings.textAlign || 'center') === 'left' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Left</button>
            <button @click="editingElement.settings.textAlign = 'center'"
                    :class="(!editingElement.settings.textAlign || editingElement.settings.textAlign === 'center') ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold border-r border-slate-100 transition-all">Center</button>
            <button @click="editingElement.settings.textAlign = 'right'"
                    :class="editingElement.settings.textAlign === 'right' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                    class="flex-1 py-2 text-[11px] font-bold transition-all">Right</button>
        </div>
    </div>

    <!-- ── Number Typography ── -->
    <div class="border-t border-slate-50 pt-4 space-y-4">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Number Typography</label>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
            <select v-model="editingElement.settings.numberFontFamily"
                    @change="loadBuilderFont(editingElement.settings.numberFontFamily)"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <option value="inherit">Default@{{ themeBodyFont ? ' (' + themeBodyFont + ')' : '' }}</option>
                <template v-for="(fonts, category) in builderFontGroups" :key="category">
                    <optgroup :label="category">
                        <option v-for="font in fonts" :key="font.family"
                                :value="font.family + ', ' + (font.category === 'Monospace' ? 'monospace' : (font.category === 'Serif' ? 'serif' : 'sans-serif'))">
                            @{{ font.family }}
                        </option>
                    </optgroup>
                </template>
            </select>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Weight</label>
            <select v-model="editingElement.settings.numberFontWeight"
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
                <input type="text" v-model="editingElement.settings.numberFontSize" placeholder="48px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Height</label>
                <input type="text" v-model="editingElement.settings.numberLineHeight" placeholder="1.1"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
                <input type="text" v-model="editingElement.settings.numberLetterSpacing" placeholder="0px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Color</label>
            <div class="flex gap-2 items-center">
                <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                     @click="openColorPicker($event, editingElement.settings, 'numberColor')">
                    <div :style="{ backgroundColor: editingElement.settings.numberColor }" class="w-full h-full"></div>
                </div>
                <input type="text" v-model="editingElement.settings.numberColor" placeholder="#222222"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- ── Label Typography ── -->
    <div class="border-t border-slate-50 pt-4 space-y-4">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Label Typography</label>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Font Family</label>
            <select v-model="editingElement.settings.labelFontFamily"
                    @change="loadBuilderFont(editingElement.settings.labelFontFamily)"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                <option value="inherit">Default@{{ themeBodyFont ? ' (' + themeBodyFont + ')' : '' }}</option>
                <template v-for="(fonts, category) in builderFontGroups" :key="category">
                    <optgroup :label="category">
                        <option v-for="font in fonts" :key="font.family"
                                :value="font.family + ', ' + (font.category === 'Monospace' ? 'monospace' : (font.category === 'Serif' ? 'serif' : 'sans-serif'))">
                            @{{ font.family }}
                        </option>
                    </optgroup>
                </template>
            </select>
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
                <input type="text" v-model="editingElement.settings.labelFontSize" placeholder="14px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Line Height</label>
                <input type="text" v-model="editingElement.settings.labelLineHeight" placeholder="1.4"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[8px] font-bold text-slate-400 uppercase mb-1 block">Letter S...</label>
                <input type="text" v-model="editingElement.settings.labelLetterSpacing" placeholder="0px"
                       class="w-full border border-slate-200 rounded px-2 py-2 text-[12px] text-center focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Text Transform</label>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.labelTextTransform = 'none'"
                        :class="(editingElement.settings.labelTextTransform || 'none') === 'none' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">Normal</button>
                <button @click="editingElement.settings.labelTextTransform = 'initial'"
                        :class="editingElement.settings.labelTextTransform === 'initial' ? 'bg-[#2271b1] text-white' : 'text-slate-400'"
                        class="flex-1 py-2 text-[10px] font-bold border-r border-slate-100 transition-all">—</button>
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
                <input type="text" v-model="editingElement.settings.labelColor" placeholder="#666666"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
        </div>
    </div>

    <!-- ── Icon ── -->
    <div class="border-t border-slate-50 pt-4 space-y-3">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Icon (optional)</label>

        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Icon Class (FontAwesome)</label>
            <input type="text" v-model="editingElement.settings.icon" placeholder="e.g. fa fa-users"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
        </div>

        <template v-if="editingElement.settings.icon">
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Icon Size (px)</label>
                <input type="number" v-model.number="editingElement.settings.iconSize" placeholder="40"
                       class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
            </div>
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Icon Color</label>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-9 h-9 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                         @click="openColorPicker($event, editingElement.settings, 'iconColor')">
                        <div :style="{ backgroundColor: editingElement.settings.iconColor }" class="w-full h-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.iconColor" placeholder="#0091ea"
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[12px] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>
        </template>
    </div>

    <!-- ── Spacing ── -->
    <div class="border-t border-slate-50 pt-4 space-y-3">
        <label class="text-[12px] font-bold text-[#333] block uppercase">Spacing</label>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Margin Top</label>
                <div class="flex border border-slate-200 rounded overflow-hidden focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings.marginTop" placeholder="0"
                           class="w-full h-9 px-2 text-[12px] text-center border-none focus:ring-0">
                    <select v-model="editingElement.settings.marginTopUnit"
                            class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                        <option value="px">px</option>
                        <option value="rem">rem</option>
                        <option value="%">%</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-[9px] font-bold text-slate-400 uppercase mb-1.5 block">Margin Bottom</label>
                <div class="flex border border-slate-200 rounded overflow-hidden focus-within:border-[#0091ea]">
                    <input type="number" v-model.number="editingElement.settings.marginBottom" placeholder="0"
                           class="w-full h-9 px-2 text-[12px] text-center border-none focus:ring-0">
                    <select v-model="editingElement.settings.marginBottomUnit"
                            class="bg-slate-50 border-l border-slate-200 text-[9px] px-0.5 focus:ring-0 border-none outline-none cursor-pointer text-center">
                        <option value="px">px</option>
                        <option value="rem">rem</option>
                        <option value="%">%</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

</div>
