{{-- Extra tab → Text Animation, shared by every element that offers it.

     A looping motion applied to the element itself, distinct from the Scroll Entrance
     Animation below it (which plays once, when the element scrolls into view). The mode
     list, its default speeds, the easing choices and which modes each element may use
     all come from FalconCms\Core\Support\TextAnimations via scripts.blade.php, so this
     panel can never offer a mode the renderers would refuse. Binds
     editingElement.settings.textAnim*. --}}
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[11px] font-bold text-[#444]">Text Animation</label>
        <button v-if="editingElement.settings.textAnim"
                @click="editingElement.settings.textAnim = ''"
                title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
            <i class="fa fa-undo text-[10px]"></i>
        </button>
    </div>

    <div class="space-y-3">
        <div>
            <label class="text-[10px] text-slate-500 block mb-1.5">Animation</label>
            <select :value="editingElement.settings.textAnim || ''"
                    @change="editingElement.settings.textAnim = $event.target.value"
                    class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                <option value="">None</option>
                <template v-for="(modes, group) in textAnimGroups(editingElement.type)" :key="group">
                    <optgroup :label="group">
                        <option v-for="mode in modes" :key="mode" :value="mode">@{{ fcTanimModes[mode].label }}</option>
                    </optgroup>
                </template>
            </select>
            <p class="text-[10px] text-slate-400 mt-1.5 leading-relaxed">
                Loops on the element itself. The Scroll Entrance Animation below plays once, on scroll — the two can be used together.
            </p>
        </div>

        <template v-if="textAnimActive(editingElement)">
            <div>
                <label class="text-[10px] text-slate-500 block mb-1.5">Play</label>
                <div class="grid grid-cols-2 gap-1">
                    <button @click="editingElement.settings.textAnimTrigger = 'always'"
                            :class="(editingElement.settings.textAnimTrigger || 'always') === 'always' ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                            class="py-2 rounded transition-all text-[10px] font-semibold">
                        Always
                    </button>
                    <button @click="editingElement.settings.textAnimTrigger = 'hover'"
                            :class="editingElement.settings.textAnimTrigger === 'hover' ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                            class="py-2 rounded transition-all text-[10px] font-semibold">
                        On Hover
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Duration (ms)</label>
                    <input type="number" v-model.number="editingElement.settings.textAnimDuration"
                           :placeholder="textAnimDefaultDuration(editingElement)"
                           min="100" max="20000" step="100"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Delay (ms)</label>
                    <input type="number" v-model.number="editingElement.settings.textAnimDelay"
                           placeholder="0" min="0" max="10000" step="100"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Repeat</label>
                    <select :value="editingElement.settings.textAnimIteration || 'infinite'"
                            @change="editingElement.settings.textAnimIteration = $event.target.value"
                            class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                        <option value="infinite">Infinite</option>
                        <option value="1">Once</option>
                        <option value="2">2 times</option>
                        <option value="3">3 times</option>
                        <option value="5">5 times</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Easing</label>
                    <select :value="editingElement.settings.textAnimEasing || ''"
                            @change="editingElement.settings.textAnimEasing = $event.target.value"
                            class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                        <option value="">Default</option>
                        <option v-for="(label, value) in fcTanimEasings" :key="value" :value="value">@{{ label }}</option>
                    </select>
                </div>
            </div>

            <div v-if="textAnimUsesAccent(editingElement)">
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-[10px] text-slate-500">Accent Color</label>
                    <button @click="clearColorField(editingElement.settings, 'textAnimColor')"
                            title="Reset" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                </div>
                <div class="flex gap-2 items-center">
                    <div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0"
                         @click="openColorPicker($event, editingElement.settings, 'textAnimColor')">
                        <div :style="{ backgroundColor: editingElement.settings.textAnimColor || '#0091ea' }" class="w-full h-full rounded-full"></div>
                    </div>
                    <input type="text" v-model="editingElement.settings.textAnimColor" placeholder="Auto"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5 leading-relaxed">
                    The highlight the mode sweeps or glows with. Left empty it follows the element's own colour.
                </p>
            </div>
        </template>
    </div>
</div>
