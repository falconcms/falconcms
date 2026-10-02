{{-- Off-canvas builder → Panel Options tab.
     Bound to `ocSettings` (scripts.blade.php), which saves itself a moment after each change
     and redraws the canvas at the panel's width and colour. Flags are '1'/'0' strings, as
     they are stored — never test them for truthiness, '0' is truthy. --}}
@php
    $ocLabel  = 'block text-[11px] font-black text-[#1d2327] uppercase tracking-widest mb-2';
    $ocInput  = 'w-full border border-slate-200 rounded px-3 py-2 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea] bg-white';
    $ocHead   = 'text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] pb-2 border-b border-slate-100';
    $ocToggle = function (string $key, string $label) {
        return '<div class="flex items-center justify-between gap-3">'
            .'<span class="text-[12px] font-bold text-[#333]">'.e($label).'</span>'
            .'<div class="flex bg-slate-50 border border-slate-100 rounded p-0.5 shrink-0">'
            .'<button type="button" @click="ocSettings.'.$key.' = \'1\'" :class="ocSettings.'.$key.' === \'1\' ? \'bg-[#2271b1] text-white shadow\' : \'text-slate-400\'" class="px-3 py-1 text-[10px] font-black uppercase rounded transition-all">On</button>'
            .'<button type="button" @click="ocSettings.'.$key.' = \'0\'" :class="ocSettings.'.$key.' !== \'1\' ? \'bg-[#2271b1] text-white shadow\' : \'text-slate-400\'" class="px-3 py-1 text-[10px] font-black uppercase rounded transition-all">Off</button>'
            .'</div></div>';
    };
    // Label row of a field that can differ per device: the builder's usual device switcher
    // (it switches the canvas too), a dot when this device overrides the size above, and a
    // reset back to inheriting it.
    $ocResp = function (string $label, string $menu, array $keys, string $hint = '') use ($ocLabel) {
        $keysJs = "['".implode("','", $keys)."']";
        $any = implode(' || ', array_map(fn ($k) => "ocOverridden('".$k."')", $keys));

        return '<div class="flex items-center justify-between mb-2">'
            .'<label class="'.$ocLabel.' !mb-0 flex items-center gap-1.5">'.e($label)
            .($hint ? ' <span class="normal-case tracking-normal font-normal text-slate-400">'.e($hint).'</span>' : '')
            .'<span v-if="'.$any.'" class="w-1.5 h-1.5 rounded-full bg-[#0091ea]" title="Set for this device"></span></label>'
            .'<div class="flex items-center gap-1.5">'
            .'<button type="button" v-if="'.$any.'" @click="ocResetDevice('.$keysJs.')" class="text-[10px] text-slate-400 hover:text-[#0091ea]" title="Use the larger screen value again"><i class="fa fa-undo"></i></button>'
            .view('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => $menu])->render()
            .'</div></div>';
    };
    $ocColor = function (string $key, string $label, string $fallback) {
        return '<div>'
            .'<div class="flex justify-between items-center mb-1.5"><label class="text-[12px] font-bold text-[#333]">'.e($label).'</label>'
            .'<button type="button" @click="clearColorField(ocSettings, \''.$key.'\')" class="text-slate-300 hover:text-[#0091ea]" title="Reset"><i class="fa fa-undo text-[10px]"></i></button></div>'
            .'<div class="flex gap-2 items-center">'
            .'<div class="checkerboard rounded-full overflow-hidden w-8 h-8 border border-slate-200 cursor-pointer flex-shrink-0" @click="openColorPicker($event, ocSettings, \''.$key.'\')">'
            .'<div :style="{ backgroundColor: ocSettings.'.$key.' || \''.$fallback.'\' }" class="w-full h-full rounded-full"></div></div>'
            .'<input type="text" v-model.lazy="ocSettings.'.$key.'" placeholder="'.$fallback.'" class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px]">'
            .'</div></div>';
    };
@endphp
<div v-show="activeTab==='oc_options'" class="h-full overflow-y-auto custom-scrollbar animate-fade-in">
    <div class="px-4 py-5 space-y-6">

        {{-- Trigger + save state --}}
        <div class="rounded-lg border border-[#0091ea]/20 bg-[#0091ea]/5 p-3">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-widest text-[#0091ea]">Trigger link</span>
                <span class="text-[10px] font-bold"
                      :class="ocSaveState === 'error' ? 'text-red-500' : (ocSaveState === 'saved' ? 'text-[#00a32a]' : 'text-slate-400')">
                    <template v-if="ocSaveState === 'pending' || ocSaveState === 'saving'"><i class="fa fa-circle-notch fa-spin mr-1"></i>Saving…</template>
                    <template v-else-if="ocSaveState === 'saved'"><i class="fa fa-check mr-1"></i>Saved</template>
                    <template v-else-if="ocSaveState === 'error'"><i class="fa fa-exclamation-triangle mr-1"></i>Not saved</template>
                </span>
            </div>
            <code class="block text-[12px] font-bold text-[#1d2327] bg-white border border-slate-200 rounded px-2 py-1.5 select-all">{{ \FalconCms\Core\Support\OffCanvas::anchor($offCanvas) }}</code>
            <p class="text-[11px] text-slate-500 leading-relaxed mt-2">Use it as the URL of any button, link or menu item to open this panel. These options save automatically.</p>
        </div>

        {{-- ── Panel ── --}}
        <div class="space-y-4">
            <div class="{{ $ocHead }}">Panel</div>

            <div>
                {!! $ocResp('Position', 'ocPos', ['position']) !!}
                <div class="grid grid-cols-5 gap-1">
                    @foreach(['left' => ['Left', 'left:3px;top:3px;bottom:3px;width:36%;'], 'right' => ['Right', 'right:3px;top:3px;bottom:3px;width:36%;'], 'top' => ['Top', 'left:3px;right:3px;top:3px;height:36%;'], 'bottom' => ['Bottom', 'left:3px;right:3px;bottom:3px;height:36%;'], 'center' => ['Popup', 'left:24%;right:24%;top:22%;bottom:22%;border-radius:2px;']] as $pos => [$lbl, $mini])
                    <button type="button" @click="ocSet('position', '{{ $pos }}')" title="{{ $lbl }}"
                            :class="ocVal('position') === '{{ $pos }}' ? 'border-[#0091ea] bg-[#0091ea]/5 text-[#0091ea]' : 'border-slate-200 text-slate-400 hover:border-slate-300'"
                            class="border-2 rounded p-1 transition-all">
                        <div class="relative h-7 bg-slate-100 rounded-sm">
                            <div class="absolute" :class="ocVal('position') === '{{ $pos }}' ? 'bg-[#0091ea]' : 'bg-slate-300'" style="{{ $mini }}"></div>
                        </div>
                        <span class="block text-[9px] font-black uppercase mt-1">{{ $lbl }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            <div v-if="ocVal('position') !== 'top' && ocVal('position') !== 'bottom'">
                {!! $ocResp('Width', 'ocWidth', ['width', 'width_unit']) !!}
                <div class="flex items-center gap-2">
                    <input type="range" :value="ocVal('width')" @input="ocSet('width', Number($event.target.value))" min="1" :max="ocVal('width_unit') === 'px' ? 1600 : 100" class="flex-1 accent-[#0091ea]">
                    <input type="number" :value="ocVal('width')" @input="ocSet('width', Number($event.target.value))" min="1" class="w-[68px] border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                    <select :value="ocVal('width_unit')" @change="ocSet('width_unit', $event.target.value)" class="border border-slate-200 rounded px-1.5 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                        <option value="px">px</option><option value="%">%</option><option value="vw">vw</option>
                    </select>
                </div>
            </div>

            <div v-if="ocVal('position') !== 'left' && ocVal('position') !== 'right'">
                {!! $ocResp('Height', 'ocHeight', ['height', 'height_unit'], '(0 = fit content)') !!}
                <div class="flex items-center gap-2">
                    <input type="range" :value="ocVal('height')" @input="ocSet('height', Number($event.target.value))" min="0" :max="ocVal('height_unit') === 'px' ? 1200 : 100" class="flex-1 accent-[#0091ea]">
                    <input type="number" :value="ocVal('height')" @input="ocSet('height', Number($event.target.value))" min="0" class="w-[68px] border border-slate-200 rounded px-2 py-1.5 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                    <select :value="ocVal('height_unit')" @change="ocSet('height_unit', $event.target.value)" class="border border-slate-200 rounded px-1.5 py-1.5 text-[12px] text-slate-600 bg-white focus:outline-none focus:border-[#0091ea]">
                        <option value="px">px</option><option value="vh">vh</option>
                    </select>
                </div>
            </div>

            {!! $ocColor('bg_color', 'Background', '#ffffff') !!}

            <div>
                {!! $ocResp('Corner Radius', 'ocRadius', ['radius']) !!}
                <div class="flex items-center gap-2">
                    <input type="range" :value="ocVal('radius')" @input="ocSet('radius', Number($event.target.value))" min="0" max="60" class="flex-1 accent-[#0091ea]">
                    <span class="w-[44px] text-right text-[12px] text-slate-500">@{{ ocVal('radius') }}px</span>
                </div>
            </div>

            {!! $ocToggle('shadow', 'Drop Shadow') !!}
        </div>

        {{-- ── Overlay ── --}}
        <div class="space-y-4">
            <div class="{{ $ocHead }}">Overlay</div>
            {!! $ocToggle('overlay', 'Show Overlay') !!}
            <template v-if="ocSettings.overlay === '1'">
                {!! $ocColor('overlay_color', 'Overlay Color', '#000000') !!}
                <div>
                    <label class="{{ $ocLabel }}">Overlay Opacity</label>
                    <div class="flex items-center gap-2">
                        <input type="range" v-model.number="ocSettings.overlay_opacity" min="0" max="100" class="flex-1 accent-[#0091ea]">
                        <span class="w-[44px] text-right text-[12px] text-slate-500">@{{ ocSettings.overlay_opacity }}%</span>
                    </div>
                </div>
                <div>
                    <label class="{{ $ocLabel }}">Background Blur</label>
                    <div class="flex items-center gap-2">
                        <input type="range" v-model.number="ocSettings.overlay_blur" min="0" max="20" class="flex-1 accent-[#0091ea]">
                        <span class="w-[44px] text-right text-[12px] text-slate-500">@{{ ocSettings.overlay_blur }}px</span>
                    </div>
                </div>
                {!! $ocToggle('close_overlay', 'Close on Overlay Click') !!}
            </template>
        </div>

        {{-- ── Closing ── --}}
        <div class="space-y-4">
            <div class="{{ $ocHead }}">Closing</div>
            {!! $ocToggle('close_button', 'Close Button') !!}
            <template v-if="ocSettings.close_button === '1'">
                {!! $ocColor('close_color', 'Close Icon Color', '#1d2327') !!}
                <div>
                    {!! $ocResp('Close Icon Size', 'ocCloseSize', ['close_size']) !!}
                    <div class="flex items-center gap-2">
                        <input type="range" :value="ocVal('close_size')" @input="ocSet('close_size', Number($event.target.value))" min="12" max="48" class="flex-1 accent-[#0091ea]">
                        <span class="w-[44px] text-right text-[12px] text-slate-500">@{{ ocVal('close_size') }}px</span>
                    </div>
                </div>
            </template>
            <div v-if="ocSettings.close_button !== '1' && !(ocSettings.overlay === '1' && ocSettings.close_overlay === '1')"
                 class="flex gap-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800 leading-relaxed">
                <i class="fa fa-exclamation-triangle mt-0.5"></i>
                <span>No close button and no overlay click: on a phone there is no Esc key, so visitors can't close this panel — unless it holds a button linking to <code>#offcanvas-close</code>.</span>
            </div>
            {!! $ocToggle('close_esc', 'Close with Esc Key') !!}
            {!! $ocToggle('lock_scroll', 'Lock Page Scroll While Open') !!}
            <p class="text-[11px] text-slate-400 leading-relaxed">Tip: a button inside the panel linking to <code class="text-[#0091ea]">#offcanvas-close</code> closes it too.</p>
        </div>

        {{-- ── Animation ── --}}
        <div class="space-y-4">
            <div class="{{ $ocHead }}">Animation</div>
            <div class="grid grid-cols-4 gap-1">
                @foreach(['slide' => 'Slide', 'fade' => 'Fade', 'zoom' => 'Zoom', 'none' => 'None'] as $anim => $lbl)
                <button type="button" @click="ocSettings.animation = '{{ $anim }}'"
                        :class="ocSettings.animation === '{{ $anim }}' ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                        class="py-2 rounded text-[10px] font-black uppercase transition-all">{{ $lbl }}</button>
                @endforeach
            </div>
            <div v-if="ocSettings.animation !== 'none'">
                <label class="{{ $ocLabel }}">Duration</label>
                <div class="flex items-center gap-2">
                    <input type="range" v-model.number="ocSettings.duration" min="100" max="1500" step="50" class="flex-1 accent-[#0091ea]">
                    <span class="w-[52px] text-right text-[12px] text-slate-500">@{{ ocSettings.duration }}ms</span>
                </div>
            </div>
        </div>

        {{-- ── Auto Open ── --}}
        <div class="space-y-4">
            <div class="{{ $ocHead }}">Auto Open</div>
            <div>
                <label class="{{ $ocLabel }}">Open By Itself</label>
                <select v-model="ocSettings.trigger" class="{{ $ocInput }}">
                    <option value="none">Never — only from a link</option>
                    <option value="load">After the page loads</option>
                    <option value="scroll">After scrolling down</option>
                    <option value="exit">When leaving the page (exit intent)</option>
                </select>
            </div>

            <template v-if="ocSettings.trigger !== 'none'">
                <div v-if="ocSettings.trigger === 'load'">
                    <label class="{{ $ocLabel }}">Delay</label>
                    <div class="flex items-center gap-2">
                        <input type="range" v-model.number="ocSettings.trigger_delay" min="0" max="60" class="flex-1 accent-[#0091ea]">
                        <span class="w-[44px] text-right text-[12px] text-slate-500">@{{ ocSettings.trigger_delay }}s</span>
                    </div>
                </div>
                <div v-if="ocSettings.trigger === 'scroll'">
                    <label class="{{ $ocLabel }}">Scroll Depth</label>
                    <div class="flex items-center gap-2">
                        <input type="range" v-model.number="ocSettings.trigger_scroll" min="1" max="100" class="flex-1 accent-[#0091ea]">
                        <span class="w-[44px] text-right text-[12px] text-slate-500">@{{ ocSettings.trigger_scroll }}%</span>
                    </div>
                </div>
                <p v-if="ocSettings.trigger === 'exit'" class="text-[11px] text-slate-400 leading-relaxed -mt-2">Opens when the pointer leaves through the top of the window. Desktop only — touch screens have no pointer to leave.</p>

                <div>
                    <label class="{{ $ocLabel }}">How Often</label>
                    <select v-model="ocSettings.frequency" class="{{ $ocInput }}">
                        <option value="always">Every page view</option>
                        <option value="session">Once per visit</option>
                        <option value="days">Once every few days</option>
                    </select>
                </div>
                <div v-if="ocSettings.frequency === 'days'">
                    <label class="{{ $ocLabel }}">Show Again After</label>
                    <div class="flex items-center gap-2">
                        <input type="number" v-model.number="ocSettings.frequency_days" min="1" max="365" class="w-[80px] {{ $ocInput }}">
                        <span class="text-[12px] text-slate-400">days</span>
                    </div>
                </div>

                <div>
                    <label class="{{ $ocLabel }}">On Pages</label>
                    <select v-model="ocSettings.auto_pages" class="{{ $ocInput }}">
                        <option value="all">Entire site</option>
                        <option value="include">Only on…</option>
                        <option value="exclude">Everywhere except…</option>
                    </select>
                </div>
                <div v-if="ocSettings.auto_pages === 'include' || ocSettings.auto_pages === 'exclude'" class="oc-target-picker">
                    <select multiple
                            v-tomselect="{ value: ocSettings.auto_targets, placeholder: 'Search pages, posts, categories…', settings: ocTargetPicker, onChange: (v) => { ocSettings.auto_targets = Array.isArray(v) ? v : (v ? [v] : []) } }"
                            class="w-full text-[13px]"></select>
                    <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Choose where this panel opens by itself: whole groups (All Pages, Post Archive…) or a specific page, post or product. Type to search, or enter a URL like <code>/shop/*</code> to cover a whole section.</p>
                </div>

                <div>
                    <label class="{{ $ocLabel }}">On Devices</label>
                    <div class="grid grid-cols-3 gap-1">
                        @foreach(['auto_mobile' => 'fa-mobile-alt', 'auto_tablet' => 'fa-tablet-alt', 'auto_desktop' => 'fa-desktop'] as $key => $icon)
                        <button type="button" @click="ocSettings.{{ $key }} = ocSettings.{{ $key }} === '1' ? '0' : '1'"
                                :class="ocSettings.{{ $key }} === '1' ? 'bg-[#0091ea] text-white' : 'bg-slate-100 text-slate-400'"
                                class="py-3 rounded transition-all flex items-center justify-center">
                            <i class="fa {{ $icon }} text-sm"></i>
                        </button>
                        @endforeach
                    </div>
                </div>
            </template>
        </div>

    </div>
</div>
