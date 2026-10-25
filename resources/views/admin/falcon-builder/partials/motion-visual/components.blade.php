{{--
    Motion Visual in the builder: the panel (Column / Container → Extra) and the canvas layer.

    Kept out of scripts.blade.php on purpose: included there once, just before the app mounts,
    it registers two components —
      <falcon-mv-panel :settings="…" where="column|container">
      <falcon-mv-preview :settings="…" where="…" :device="device" :editing="…">
    The canvas layer is the real front-end template rendered on the server with the settings
    being edited (route falcon-builder.motion-visual-preview), so it looks exactly as the page
    will. While its column or container is being edited, every item gets handles on top:
    drag to move, the corner to resize, the round knob to turn (Shift: 15° steps). A drag
    moves the drawn item directly and writes the setting when it ends, so it never waits on
    the server.

    Settings are flat mv_* scalars; the item list is mv_items, the list as JSON passed through
    encodeURIComponent (see Support\MotionVisual\MotionVisuals). The panel and the canvas
    share which item is selected through mvUi.
--}}
    window.falconMotionVisuals = @json(\FalconCms\Core\Support\MotionVisual\MotionVisuals::editorConfig());

    const mvUi = reactive({ sel: null });

    const mvItems = {
        read(settings) {
            const raw = settings && settings.mv_items;
            if (!raw) return [];
            try {
                const list = JSON.parse(decodeURIComponent(raw));
                return Array.isArray(list) ? list.filter(i => i && typeof i === 'object') : [];
            } catch (e) { return []; }
        },
        write(settings, list) {
            settings.mv_items = list.length ? encodeURIComponent(JSON.stringify(list)) : '';
        },
        id() { return 'i' + Math.random().toString(36).slice(2, 9); },
    };

    // The engine, loaded the first time a canvas layer needs it.
    const falconMvEngine = () => {
        if (window.FalconMotionVisual) return Promise.resolve(window.FalconMotionVisual);
        if (!window.__falconMvEngine) {
            window.__falconMvEngine = new Promise((resolve) => {
                const s = document.createElement('script');
                s.src = @json(asset('vendor/falcon-cms/js/motion-visual.js'));
                s.onload = () => resolve(window.FalconMotionVisual || null);
                s.onerror = () => resolve(null);
                document.head.appendChild(s);
            });
        }
        return window.__falconMvEngine;
    };

    __builderApp.component('falcon-mv-panel', {
        props: { settings: { type: Object, required: true }, where: { type: String, default: 'column' } },
        data() {
            return { cfg: window.falconMotionVisuals || { presets: {}, shapes: {}, shared: {}, animations: [] }, ui: mvUi, adding: false };
        },
        computed: {
            exists() { return !!this.settings.mv_preset; },
            // The switch: off keeps every setting and draws nothing, on the page or here.
            on() { return this.exists && this.flag('mv_enabled'); },
            // Pickr writes obj[key]; for an item that lands in the encoded list.
            itemColor() {
                const self = this;
                return {
                    get color() { return self.current ? (self.current.color || '') : ''; },
                    set color(v) { self.setItem('color', v); },
                };
            },
            // One number, read by each animation in its own unit; hidden where it does nothing.
            distField() {
                const f = {
                    float: ['Distance (px)', 200], drift: ['Distance (px)', 200], bounce: ['Height (px)', 200],
                    orbit: ['Orbit radius (px)', 200], pulse: ['Grow (%)', 100], swing: ['Swing (°)', 180],
                }[this.itemVal('anim', 'float')];
                return f ? [['dist', f[0], 0, f[1], 1, 24]] : [];
            },
            primaryHex() { return this.settings.mv_primary || this.cfg.themePrimary; },
            accentHex() { return this.settings.mv_accent || '#f59e0b'; },
            items() { return mvItems.read(this.settings); },
            current() { return this.items.find(i => i.id === this.ui.sel) || null; },
            full() { return this.items.length >= (this.cfg.maxItems || 40); },
        },
        methods: {
            val(key) {
                const v = this.settings[key];
                return v !== undefined && v !== null && v !== '' ? v : this.cfg.shared[key];
            },
            flag(key) {
                const v = this.val(key);
                return !(v === false || v === 'false' || v === 0 || v === '0');
            },
            set(key, value) { this.settings[key] = value; },
            toggle() {
                if (this.on) {
                    this.settings.mv_enabled = false;
                    this.ui.sel = null;
                    return;
                }
                this.settings.mv_enabled = true;
                if (!this.settings.mv_preset) this.settings.mv_preset = 'custom';
            },
            applyPreset(key) {
                const p = this.cfg.presets[key];
                if (!p) return;
                if (this.items.length && !confirm('Replace the current dots and shapes with “' + p.name + '”?')) return;
                Object.keys(p.settings).forEach((k) => { this.settings[k] = p.settings[k]; });
                // Presets carry no ids; every item needs one for its handles.
                const list = mvItems.read(this.settings).map(i => Object.assign({ id: mvItems.id() }, i));
                mvItems.write(this.settings, list);
                this.settings.mv_preset = key;
                this.ui.sel = null;
            },
            remove() {
                if (!confirm('Remove the Motion Visual from this ' + this.where + '?')) return;
                Object.keys(this.settings).forEach((k) => { if (k.indexOf('mv_') === 0) delete this.settings[k]; });
                this.ui.sel = null;
            },
            change(fn) {
                const list = mvItems.read(this.settings);
                fn(list);
                mvItems.write(this.settings, list);
            },
            setItem(key, value) {
                const id = this.ui.sel;
                this.change(list => { const it = list.find(i => i.id === id); if (it) it[key] = value; });
            },
            itemVal(key, d) {
                const it = this.current;
                return it && it[key] !== undefined && it[key] !== '' ? it[key] : d;
            },
            addShape(shape) {
                const id = mvItems.id();
                this.change(list => list.push({ id, kind: 'shape', shape, x: 50, y: 50, size: 8, anim: 'float', speed: 8, dist: 24, opacity: 80, depth: 1 }));
                this.ui.sel = id;
                this.adding = false;
            },
            addImage() {
                const add = (src) => {
                    if (!src) return;
                    const id = mvItems.id();
                    this.change(list => list.push({ id, kind: 'image', src, x: 50, y: 50, size: 12, anim: 'float', speed: 8, dist: 20, opacity: 100, depth: 1 }));
                    this.ui.sel = id;
                };
                if (window.openMediaModal) window.openMediaModal((m) => add('/storage/' + m.path));
                else add(prompt('Image URL (SVG, PNG, WebP…):', '') || '');
            },
            addText() {
                const id = mvItems.id();
                const text = this.cfg.siteTitle || 'Your text';
                // Starts at a size that keeps the whole line inside the layer.
                const size = Math.round(Math.min(6, 80 / (Math.max(3, text.length) * 0.6)) * 10) / 10;
                this.change(list => list.push({ id, kind: 'text', text, style: 'fill', font: 'inherit', weight: 800,
                    x: 50, y: 50, size, anim: 'float', speed: 8, dist: 16, opacity: 100, depth: 1 }));
                this.ui.sel = id;
            },
            pickImage() {
                if (window.openMediaModal) window.openMediaModal((m) => this.setItem('src', '/storage/' + m.path));
            },
            duplicate(id) {
                const nid = mvItems.id();
                this.change(list => {
                    const at = list.findIndex(i => i.id === id);
                    if (at < 0) return;
                    const copy = Object.assign({}, list[at], { id: nid, x: Math.min(100, (Number(list[at].x) || 50) + 4), y: Math.min(100, (Number(list[at].y) || 50) + 4) });
                    list.splice(at + 1, 0, copy);
                });
                this.ui.sel = nid;
            },
            del(id) {
                this.change(list => { const at = list.findIndex(i => i.id === id); if (at >= 0) list.splice(at, 1); });
                if (this.ui.sel === id) this.ui.sel = null;
            },
            move(id, by) {
                this.change(list => {
                    const at = list.findIndex(i => i.id === id), to = at + by;
                    if (at < 0 || to < 0 || to >= list.length) return;
                    list.splice(to, 0, list.splice(at, 1)[0]);
                });
            },
            label(i) {
                if (i.kind === 'text') return String(i.text || 'Text').replace(/\s+/g, ' ').slice(0, 28);
                if (i.kind === 'image') return (String(i.src || '').split('/').pop() || 'Image');
                return (this.cfg.shapes[i.shape] || {}).name || 'Shape';
            },
            svg(key) { return (this.cfg.shapes[key] || {}).svg || ''; },
        },
        template: `@verbatim
        <div class="border-b border-slate-100 pb-5">
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-[#444]">Motion Visual</label>
                <button type="button" @click="toggle" role="switch" :aria-checked="on ? 'true' : 'false'" :title="on ? 'Disable' : 'Enable'"
                        :class="on ? 'bg-[#2271b1]' : 'bg-slate-300'" class="relative w-9 h-5 rounded-full transition-colors flex-shrink-0">
                    <span :class="on ? 'translate-x-4' : 'translate-x-0'" class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform"></span>
                </button>
            </div>
            <p class="text-[10px] text-slate-400 mb-2">Moving dots, shapes, text and your own pictures across the whole {{ where }}.</p>
            <p v-if="exists && !on" class="text-[10px] text-slate-500 bg-slate-50 border border-slate-200 rounded px-2 py-1.5">Off: the settings are kept, but nothing is drawn and no script loads on the page.</p>

            <template v-if="on">
                <div class="flex justify-between items-center mt-3 mb-1.5">
                    <label class="text-[10px] text-slate-500">Start from a preset</label>
                    <button @click="remove" class="text-[10px] text-slate-400 hover:text-red-500" title="Remove every Motion Visual setting"><i class="fa fa-trash-alt"></i> Reset</button>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <button v-for="(p, key) in cfg.presets" :key="key" @click="applyPreset(key)" :title="p.description"
                            :class="settings.mv_preset === key ? 'border-[#0091ea] bg-[#0091ea]/5 text-[#0091ea]' : 'border-slate-200 text-[#444] hover:border-slate-300'"
                            class="border rounded px-2 py-1.5 text-[11px] text-left leading-tight">{{ p.name }}</button>
                </div>
                <!-- Layer -->
                <div class="mt-4">
                    <label class="text-[10px] text-slate-500 block mb-1.5">Layer</label>
                    <div class="flex bg-slate-100 rounded overflow-hidden">
                        <button v-for="m in [['behind','Behind content'],['front','In front']]" :key="m[0]" @click="set('mv_layer', m[0])"
                                :class="val('mv_layer') === m[0] ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-500 hover:bg-slate-200'"
                                class="flex-1 py-1.5 text-[11px] font-medium transition-colors">{{ m[1] }}</button>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1.5">Speed ({{ Number(val('mv_speed')).toFixed(2) }}×)</label>
                        <input type="range" min="0.25" max="3" step="0.25" :value="val('mv_speed')" @input="set('mv_speed', Number($event.target.value))" class="w-full">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1.5">Opacity ({{ val('mv_opacity') }}%)</label>
                        <input type="range" min="5" max="100" step="5" :value="val('mv_opacity')" @input="set('mv_opacity', Number($event.target.value))" class="w-full">
                    </div>
                </div>
                <label class="mt-2 flex items-center justify-between text-[11px] text-[#444]">
                    <span>Follow the mouse</span>
                    <input type="checkbox" :checked="flag('mv_mouse')" @change="set('mv_mouse', $event.target.checked)" class="rounded-sm border-slate-300">
                </label>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1.5">Main color</label>
                        <falcon-mv-color :obj="settings" k="mv_primary" :fallback="cfg.themePrimary" placeholder="Theme"></falcon-mv-color>
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1.5">Accent</label>
                        <falcon-mv-color :obj="settings" k="mv_accent" fallback="#f59e0b"></falcon-mv-color>
                    </div>
                </div>

                <!-- Dots -->
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <label class="flex items-center justify-between text-[11px] font-bold text-[#444]">
                        <span>Dots</span>
                        <input type="checkbox" :checked="flag('mv_dots')" @change="set('mv_dots', $event.target.checked)" class="rounded-sm border-slate-300">
                    </label>
                    <template v-if="flag('mv_dots')">
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] text-slate-500 block mb-1">How many ({{ val('mv_dots_count') }})</label>
                                <input type="range" min="5" max="250" step="5" :value="val('mv_dots_count')" @input="set('mv_dots_count', Number($event.target.value))" class="w-full">
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 block mb-1">Size ({{ val('mv_dots_size') }}px)</label>
                                <input type="range" min="0.5" max="14" step="0.5" :value="val('mv_dots_size')" @input="set('mv_dots_size', Number($event.target.value))" class="w-full">
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 block mb-1">Speed ({{ val('mv_dots_speed') }})</label>
                                <input type="range" min="0.05" max="4" step="0.05" :value="val('mv_dots_speed')" @input="set('mv_dots_speed', Number($event.target.value))" class="w-full">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="text-[10px] text-slate-500 block mb-1">Color</label>
                            <falcon-mv-color :obj="settings" k="mv_dots_color" tokens :primary="primaryHex" :accent="accentHex"></falcon-mv-color>
                        </div>
                        <label class="mt-2 flex items-center justify-between text-[11px] text-[#444]">
                            <span>Join near dots with lines</span>
                            <input type="checkbox" :checked="flag('mv_dots_links')" @change="set('mv_dots_links', $event.target.checked)" class="rounded-sm border-slate-300">
                        </label>
                        <div v-if="flag('mv_dots_links')" class="mt-1">
                            <label class="text-[10px] text-slate-500 block mb-1">Line reach ({{ val('mv_dots_link_dist') }}px)</label>
                            <input type="range" min="40" max="260" step="10" :value="val('mv_dots_link_dist')" @input="set('mv_dots_link_dist', Number($event.target.value))" class="w-full">
                        </div>
                        <div class="mt-2">
                            <label class="text-[10px] text-slate-500 block mb-1">When the mouse comes near</label>
                            <div class="flex bg-slate-100 rounded overflow-hidden">
                                <button v-for="m in [['none','Nothing'],['repel','Push away'],['attract','Pull in']]" :key="m[0]" @click="set('mv_dots_mouse', m[0])"
                                        :class="val('mv_dots_mouse') === m[0] ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-200'"
                                        class="flex-1 py-1 text-[10px] font-medium">{{ m[1] }}</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Shapes & pictures -->
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[11px] font-bold text-[#444]">Shapes &amp; pictures</label>
                        <span class="text-[10px] text-slate-400">{{ items.length }}/{{ cfg.maxItems }}</span>
                    </div>
                    <div class="space-y-1">
                        <div v-for="(i, n) in items" :key="i.id" @click="ui.sel = i.id"
                             :class="ui.sel === i.id ? 'border-[#0091ea] bg-[#0091ea]/5' : 'border-slate-200 hover:border-slate-300'"
                             class="flex items-center gap-2 border rounded px-1.5 py-1 cursor-pointer">
                            <span class="w-5 h-5 flex-shrink-0 grid place-items-center text-[#2271b1]">
                                <img v-if="i.kind === 'image'" :src="i.src" class="max-w-full max-h-full object-contain" alt="">
                                <i v-else-if="i.kind === 'text'" class="fa fa-font text-[11px]"></i>
                                <svg v-else viewBox="0 0 100 100" fill="currentColor" class="w-4 h-4" v-html="svg(i.shape)"></svg>
                            </span>
                            <span class="flex-1 min-w-0 truncate text-[11px] text-[#444]">{{ label(i) }}</span>
                            <span class="text-[9px] text-slate-400">{{ i.anim || 'float' }}</span>
                            <button @click.stop="move(i.id, -1)" :disabled="n === 0" class="text-slate-300 hover:text-slate-600 disabled:opacity-30" title="Up"><i class="fa fa-arrow-up text-[9px]"></i></button>
                            <button @click.stop="duplicate(i.id)" :disabled="full" class="text-slate-300 hover:text-slate-600" title="Duplicate"><i class="fa fa-copy text-[10px]"></i></button>
                            <button @click.stop="del(i.id)" class="text-slate-300 hover:text-red-500" title="Delete"><i class="fa fa-times text-[10px]"></i></button>
                        </div>
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-1.5">
                        <button @click="adding = !adding" :disabled="full" class="border border-dashed border-slate-300 hover:border-[#0091ea] hover:text-[#0091ea] rounded py-1.5 text-[11px] text-slate-500 disabled:opacity-40"><i class="fa fa-shapes mr-1"></i>Shape</button>
                        <button @click="addText" :disabled="full" class="border border-dashed border-slate-300 hover:border-[#0091ea] hover:text-[#0091ea] rounded py-1.5 text-[11px] text-slate-500 disabled:opacity-40"><i class="fa fa-font mr-1"></i>Text</button>
                        <button @click="addImage" :disabled="full" class="border border-dashed border-slate-300 hover:border-[#0091ea] hover:text-[#0091ea] rounded py-1.5 text-[11px] text-slate-500 disabled:opacity-40"><i class="fa fa-image mr-1"></i>Picture</button>
                    </div>
                    <div v-if="adding" class="mt-2 grid grid-cols-5 gap-1 p-1.5 bg-slate-50 rounded border border-slate-200">
                        <button v-for="(s, key) in cfg.shapes" :key="key" @click="addShape(key)" :title="s.name"
                                class="aspect-square rounded bg-white border border-slate-200 hover:border-[#0091ea] grid place-items-center text-[#2271b1]">
                            <svg viewBox="0 0 100 100" fill="currentColor" class="w-5 h-5" v-html="s.svg"></svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2">On the canvas: drag an item to move it, its corner to resize, the round knob to turn it.</p>
                </div>

                <!-- The selected item -->
                <div v-if="current" class="mt-3 p-2.5 rounded border border-[#0091ea]/40 bg-[#0091ea]/[0.03] space-y-2.5">
                    <div class="flex justify-between items-center">
                        <span class="text-[11px] font-bold text-[#2271b1]">{{ label(current) }}</span>
                        <button @click="ui.sel = null" class="text-slate-400 hover:text-slate-700"><i class="fa fa-times text-[10px]"></i></button>
                    </div>
                    <div v-if="current.kind === 'image'" class="flex gap-1">
                        <input type="text" :value="current.src || ''" @change="setItem('src', $event.target.value.trim())" class="w-full min-w-0 border border-slate-200 rounded px-2 py-1 text-[11px]">
                        <button @click="pickImage" class="px-2 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px]" title="Media Library"><i class="fa fa-image"></i></button>
                    </div>
                    <div v-else-if="current.kind === 'text'" class="space-y-2">
                        <textarea rows="2" :value="current.text || ''" @input="setItem('text', $event.target.value)" maxlength="200"
                                  class="w-full border border-slate-200 rounded px-2 py-1 text-[12px] leading-snug focus:outline-none focus:border-[#0091ea]"></textarea>
                        <div class="flex bg-slate-100 rounded overflow-hidden">
                            <button v-for="t in [['fill','Fill'],['outline','Outline'],['gradient','Gradient']]" :key="t[0]" @click="setItem('style', t[0])"
                                    :class="itemVal('style', 'fill') === t[0] ? 'bg-slate-800 text-white' : 'text-slate-500 hover:bg-slate-200'"
                                    class="flex-1 py-1 text-[10px] font-medium">{{ t[1] }}</button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <select :value="itemVal('font', 'inherit')" @change="setItem('font', $event.target.value)" class="w-full border border-slate-200 rounded px-1.5 py-1 text-[11px]">
                                <option value="inherit">Page font</option><option value="sans">Sans</option><option value="serif">Serif</option><option value="mono">Mono</option>
                            </select>
                            <select :value="itemVal('weight', 800)" @change="setItem('weight', Number($event.target.value))" class="w-full border border-slate-200 rounded px-1.5 py-1 text-[11px]">
                                <option v-for="w in [300,400,500,600,700,800,900]" :key="w" :value="w">Weight {{ w }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2 items-end">
                            <div>
                                <label class="text-[10px] text-slate-500 flex justify-between"><span>Letter spacing</span><span>{{ itemVal('spacing', 0) }}em</span></label>
                                <input type="range" min="-0.1" max="0.6" step="0.01" :value="itemVal('spacing', 0)" @change="setItem('spacing', Number($event.target.value))" class="w-full">
                            </div>
                            <label class="flex items-center gap-1.5 text-[11px] text-[#444] pb-1">
                                <input type="checkbox" :checked="!!current.upper" @change="setItem('upper', $event.target.checked)" class="rounded-sm border-slate-300"> UPPERCASE
                            </label>
                        </div>
                    </div>
                    <div v-else class="grid grid-cols-6 gap-1">
                        <button v-for="(s, key) in cfg.shapes" :key="key" @click="setItem('shape', key)" :title="s.name"
                                :class="current.shape === key ? 'border-[#0091ea] text-[#0091ea]' : 'border-slate-200 text-slate-500'"
                                class="aspect-square rounded bg-white border grid place-items-center">
                            <svg viewBox="0 0 100 100" fill="currentColor" class="w-4 h-4" v-html="s.svg"></svg>
                        </button>
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-500 block mb-1">Animation</label>
                        <div class="grid grid-cols-4 gap-1">
                            <button v-for="a in cfg.animations" :key="a" @click="setItem('anim', a)"
                                    :class="itemVal('anim', 'float') === a ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                    class="py-1 rounded text-[10px] capitalize">{{ a }}</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1.5">
                        <div v-for="f in [['speed','Seconds per cycle',0.5,60,0.5,8],...distField,['delay','Delay (s)',0,20,0.5,0],['depth','Mouse depth',-4,4,0.1,1],['size', current.kind === 'text' ? 'Font size (% of width)' : 'Size (%)',0.5,current.kind === 'text' ? 60 : 100,0.1,8],['rot','Turn (°)',-180,180,1,0],['opacity','Opacity (%)',0,100,1,100],['blur','Blur (px)',0,80,1,0],['x','Left (%)',-20,120,0.5,50],['y','Top (%)',-20,120,0.5,50]]" :key="f[0]">
                            <label class="text-[10px] text-slate-500 flex justify-between"><span>{{ f[1] }}</span><span>{{ itemVal(f[0], f[5]) }}</span></label>
                            <input type="range" :min="f[2]" :max="f[3]" :step="f[4]" :value="itemVal(f[0], f[5])" @change="setItem(f[0], Number($event.target.value))" class="w-full">
                        </div>
                    </div>
                    <div v-if="current.kind !== 'image'">
                        <label class="text-[10px] text-slate-500 block mb-1">Color</label>
                        <falcon-mv-color :obj="itemColor" k="color" tokens :primary="primaryHex" :accent="accentHex"></falcon-mv-color>
                    </div>
                </div>

                <!-- Devices -->
                <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-2">
                    <div v-for="d in [['mv_tablet','On tablets'],['mv_mobile','On phones']]" :key="d[0]">
                        <label class="text-[10px] text-slate-500 block mb-1.5">{{ d[1] }}</label>
                        <select :value="val(d[0])" @change="set(d[0], $event.target.value)"
                                class="w-full border border-slate-200 rounded px-2 py-1.5 text-[11px] text-[#444] focus:outline-none focus:border-[#0091ea]">
                            <option value="full">Full motion</option>
                            <option value="simple">Calmer</option>
                            <option value="static">Still</option>
                            <option value="hide">Hidden</option>
                        </select>
                    </div>
                </div>
                <p class="text-[10px] text-slate-400 mt-2">Visitors who ask their device for reduced motion always get it still.</p>
            </template>
        </div>
        @endverbatim`,
    });

    // A color field on the builder's own picker (Pickr, the pcr-fnew skin every other color in
    // the builder uses: palette, hue, opacity, swatches, hex). With `tokens` it also offers the
    // visual's Main and Accent colors by name, so they follow when those two change, and
    // Random: a color that keeps changing by itself. Picking a color in the palette ends it.
    __builderApp.component('falcon-mv-color', {
        props: {
            obj: { type: Object, required: true },
            k: { type: String, required: true },
            fallback: { type: String, default: '#94a3b8' },
            placeholder: { type: String, default: '' },
            tokens: { type: Boolean, default: false },
            primary: { type: String, default: '#005ab4' },
            accent: { type: String, default: '#f59e0b' },
        },
        computed: {
            raw() { return String(this.obj[this.k] || ''); },
            token() {
                if (this.raw === 'primary' || this.raw === 'accent' || this.raw === 'random') return this.raw;
                return this.tokens && this.raw === '' ? 'primary' : '';
            },
            swatch() {
                if (this.token === 'primary') return this.primary;
                if (this.token === 'accent') return this.accent;
                if (this.token === 'random') return this.primary; // where the picker opens
                return this.raw || this.fallback;
            },
        },
        methods: {
            pick(e) {
                const app = this.$root;
                if (app && typeof app.openColorPicker === 'function') app.openColorPicker(e, this.obj, this.k, null, this.swatch, 'pcr-fnew');
            },
            typed(v) {
                v = String(v || '').trim();
                this.obj[this.k] = v === '' ? '' : (v.charAt(0) === '#' ? v : '#' + v);
            },
        },
        template: `@verbatim
        <div class="flex items-center gap-1.5">
            <template v-if="tokens">
                <button v-for="t in [['primary','Main'],['accent','Accent'],['random','Random']]" :key="t[0]" type="button" @click="obj[k] = t[0]"
                        :title="t[0] === 'random' ? 'Keeps changing color by itself' : ''"
                        :class="token === t[0] ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                        class="px-2 h-7 rounded text-[10px] font-medium flex-shrink-0">{{ t[1] }}</button>
            </template>
            <div class="checkerboard rounded-full overflow-hidden w-7 h-7 flex-shrink-0 border border-slate-200 shadow-sm cursor-pointer"
                 :class="tokens && !token ? 'ring-2 ring-[#2271b1] ring-offset-1' : ''" @click="pick($event)" title="Pick a color">
                <div class="w-full h-full" :style="token === 'random'
                    ? { background: 'conic-gradient(hsl(0 80% 58%),hsl(60 80% 58%),hsl(120 80% 58%),hsl(180 80% 58%),hsl(240 80% 58%),hsl(300 80% 58%),hsl(360 80% 58%))' }
                    : { backgroundColor: swatch }"></div>
            </div>
            <input type="text" :value="token ? '' : raw" @change="typed($event.target.value)"
                   :placeholder="token ? ({ accent: 'Accent', random: 'Random', primary: 'Main color' })[token] : (placeholder || fallback)"
                   class="wp-input h-7 flex-1 min-w-0 text-[11px] font-mono px-2 focus:outline-none focus:border-[#2271b1]">
        </div>
        @endverbatim`,
    });

    __builderApp.component('falcon-mv-preview', {
        props: {
            settings: { type: Object, required: true },
            where: { type: String, default: 'column' },
            device: { type: String, default: 'desktop' },
            editing: { type: Boolean, default: false },
        },
        data() {
            return { ui: mvUi, boxes: [], timer: 0, seq: 0, drag: null };
        },
        computed: {
            // Only the mv_* settings redraw the layer; a padding change does not.
            mvKey() {
                const out = {};
                Object.keys(this.settings).sort().forEach((k) => {
                    if (k.indexOf('mv_') === 0) out[k] = this.settings[k];
                });
                return JSON.stringify(out);
            },
        },
        watch: {
            mvKey() { this.queue(); },
            editing() { this.$nextTick(() => this.measure()); },
        },
        mounted() {
            this.load();
            if ('ResizeObserver' in window) {
                this.ro = new ResizeObserver(() => this.measure());
                this.ro.observe(this.$el);
            }
        },
        beforeUnmount() {
            clearTimeout(this.timer);
            if (this.ro) this.ro.disconnect();
            this.release();
        },
        methods: {
            queue() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.load(), 200);
            },
            layer() { return this.$refs.box && this.$refs.box.querySelector('[data-falcon-mv]'); },
            release() {
                const old = this.layer();
                if (old && window.FalconMotionVisual) window.FalconMotionVisual.destroy(old);
            },
            async load() {
                const seq = ++this.seq;
                let html = '';
                try {
                    const res = await fetch(@json(route('admin.falcon-builder.motion-visual-preview')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ settings: JSON.parse(this.mvKey), where: this.where }),
                    });
                    html = (await res.json()).html || '';
                } catch (e) { html = ''; }
                if (seq !== this.seq || !this.$refs.box || this.drag) return; // a newer request, or a drag, has the last word
                this.release();
                this.$refs.box.innerHTML = html;
                this.measure();
                const root = this.layer();
                if (!root) return;
                const engine = await falconMvEngine();
                if (engine && seq === this.seq) engine.init(root);
            },
            // Where each item is drawn, as boxes for the handles (its resting box: the
            // animation moves an inner element, so the handles hold still while it plays).
            measure() {
                const root = this.layer();
                if (!root || !this.editing) { this.boxes = []; return; }
                const r = root.getBoundingClientRect();
                const list = mvItems.read(this.settings);
                this.boxes = Array.prototype.map.call(root.querySelectorAll('[data-mv-item]'), (el) => {
                    const b = el.getBoundingClientRect();
                    const it = list.find(i => i.id === el.getAttribute('data-mv-item')) || {};
                    return { id: el.getAttribute('data-mv-item'), left: b.left - r.left, top: b.top - r.top, width: b.width, height: b.height, rot: Number(it.rot) || 0 };
                });
            },
            select(id) { this.ui.sel = id; },
            start(e, box, mode) {
                e.preventDefault();
                e.stopPropagation();
                this.select(box.id);
                const root = this.layer();
                const el = root && root.querySelector('[data-mv-item="' + box.id + '"]');
                const it = mvItems.read(this.settings).find(i => i.id === box.id);
                if (!root || !el || !it) return;
                const r = root.getBoundingClientRect();
                this.drag = {
                    mode, id: box.id, el, shape: el.querySelector('.falcon-mv__shape'), box, text: it.kind === 'text',
                    w: r.width, h: r.height, sx: e.clientX, sy: e.clientY,
                    x: Number(it.x) || 50, y: Number(it.y) || 50, size: Number(it.size) || 8, rot: Number(it.rot) || 0,
                    cx: r.left + (Number(it.x) || 50) / 100 * r.width, cy: r.top + (Number(it.y) || 50) / 100 * r.height,
                    left: box.left, top: box.top, bw: box.width, bh: box.height,
                };
                window.addEventListener('pointermove', this.moveDrag);
                window.addEventListener('pointerup', this.endDrag, { once: true });
            },
            moveDrag(e) {
                const d = this.drag;
                if (!d) return;
                const dx = e.clientX - d.sx, dy = e.clientY - d.sy;
                if (d.mode === 'move') {
                    d.nx = Math.round((d.x + dx / d.w * 100) * 10) / 10;
                    d.ny = Math.round((d.y + dy / d.h * 100) * 10) / 10;
                    d.el.style.left = d.nx + '%';
                    d.el.style.top = d.ny + '%';
                    d.box.left = d.left + dx;
                    d.box.top = d.top + dy;
                } else if (d.mode === 'size') {
                    // From the centre: the corner moves twice as far as the size grows on one side.
                    const grow = Math.max(dx, dy) * 2;
                    d.nsize = Math.max(0.5, Math.round((d.size + grow / d.w * 100) * 10) / 10);
                    // A text item's size is its font size, as a share of the layer's width.
                    if (d.text) d.el.style.fontSize = d.nsize + 'cqw';
                    else d.el.style.width = d.nsize + '%';
                    const k = d.nsize / d.size;
                    d.box.width = d.bw * k;
                    d.box.height = d.bh * k;
                    d.box.left = d.left - (d.box.width - d.bw) / 2;
                    d.box.top = d.top - (d.box.height - d.bh) / 2;
                } else if (d.mode === 'turn') {
                    let a = Math.atan2(e.clientY - d.cy, e.clientX - d.cx) * 180 / Math.PI + 90;
                    if (a > 180) a -= 360;
                    if (e.shiftKey) a = Math.round(a / 15) * 15;
                    d.nrot = Math.round(a);
                    if (d.shape) d.shape.style.rotate = d.nrot + 'deg';
                    d.box.rot = d.nrot;
                }
            },
            endDrag() {
                window.removeEventListener('pointermove', this.moveDrag);
                const d = this.drag;
                this.drag = null;
                if (!d) return;
                const list = mvItems.read(this.settings);
                const it = list.find(i => i.id === d.id);
                if (!it) return;
                if (d.nx !== undefined) { it.x = d.nx; it.y = d.ny; }
                if (d.nsize !== undefined) it.size = d.nsize;
                if (d.nrot !== undefined) it.rot = d.nrot;
                mvItems.write(this.settings, list);
            },
        },
        template: `@verbatim
        <div class="falcon-mv-canvas-preview absolute inset-0 pointer-events-none" :style="{ zIndex: editing ? 40 : 0 }">
            <div ref="box" class="absolute inset-0" style="z-index:0"></div>
            <template v-if="editing">
                <div v-for="b in boxes" :key="b.id"
                     @pointerdown="start($event, b, 'move')" @click.stop
                     :class="ui.sel === b.id ? 'outline-[#0091ea]' : 'outline-[#0091ea]/40 hover:outline-[#0091ea]'"
                     class="absolute outline-1 outline-dashed cursor-move pointer-events-auto"
                     :style="{ left: b.left + 'px', top: b.top + 'px', width: b.width + 'px', height: b.height + 'px', rotate: b.rot + 'deg' }">
                    <template v-if="ui.sel === b.id">
                        <span @pointerdown="start($event, b, 'size')" class="absolute -right-1.5 -bottom-1.5 w-3 h-3 bg-white border-2 border-[#0091ea] rounded-sm cursor-nwse-resize" title="Resize"></span>
                        <span class="absolute left-1/2 -top-5 w-px h-4 bg-[#0091ea]"></span>
                        <span @pointerdown="start($event, b, 'turn')" class="absolute left-1/2 -top-7 -ml-1.5 w-3 h-3 bg-[#0091ea] border-2 border-white rounded-full cursor-grab shadow" title="Turn (Shift: 15° steps)"></span>
                    </template>
                </div>
            </template>
        </div>
        @endverbatim`,
    });
