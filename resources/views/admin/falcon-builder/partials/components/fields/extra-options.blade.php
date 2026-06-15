{{-- Reusable Extra-tab options: Conditional Visibility + Scroll Entrance Animation.
     Binds editingElement.settings.vis_* and anim_* --}}
<div class="space-y-5">
    {{-- Conditional Visibility --}}
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[11px] font-bold text-[#444]">Conditional Visibility</label>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[10px] text-slate-500 block mb-1.5">Show When</label>
                <select :value="editingElement.settings.vis_condition || ''"
                        @change="editingElement.settings.vis_condition = $event.target.value"
                        class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                    <option value="">Default (Always Show)</option>
                    <option value="logged_in">User is Logged In</option>
                    <option value="logged_out">User is Logged Out</option>
                    <option value="schedule">By Schedule</option>
                </select>
            </div>
            <template v-if="(editingElement.settings.vis_condition || '') === 'schedule'">
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Show From</label>
                    <input type="datetime-local" v-model="editingElement.settings.vis_date_from"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Show Until</label>
                    <input type="datetime-local" v-model="editingElement.settings.vis_date_to"
                           class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                </div>
            </template>
        </div>
    </div>

    {{-- Scroll Entrance Animation --}}
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[11px] font-bold text-[#444]">Scroll Entrance Animation</label>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[10px] text-slate-500 block mb-1.5">Animation Type</label>
                <select v-model="editingElement.settings.anim_type"
                        class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                    <option value="">None</option>
                    <option value="fade-in">Fade In</option>
                    <option value="slide-up">Slide Up</option>
                    <option value="slide-down">Slide Down</option>
                    <option value="slide-left">Slide Left</option>
                    <option value="slide-right">Slide Right</option>
                    <option value="zoom-in">Zoom In</option>
                    <option value="zoom-out">Zoom Out</option>
                    <option value="bounce-in">Bounce In</option>
                </select>
            </div>
            <template v-if="editingElement.settings.anim_type">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1.5">Duration (ms)</label>
                        <input type="number" v-model.number="editingElement.settings.anim_duration" placeholder="600" min="100" max="3000" step="100"
                               class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1.5">Delay (ms)</label>
                        <input type="number" v-model.number="editingElement.settings.anim_delay" placeholder="0" min="0" max="3000" step="100"
                               class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                    </div>
                </div>
                <div>
                    <label class="text-[10px] text-slate-500 block mb-1.5">Easing</label>
                    <select v-model="editingElement.settings.anim_easing"
                            class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                        <option value="ease">Ease</option>
                        <option value="ease-in">Ease In</option>
                        <option value="ease-out">Ease Out</option>
                        <option value="ease-in-out">Ease In Out</option>
                        <option value="linear">Linear</option>
                    </select>
                </div>
            </template>
        </div>
    </div>
</div>
