{{--
    Customizer → Typography → Custom Font: one box per font face (name, weight, style, a file per
    format), files picked from the Media Library, saved with the Customizer's Save Changes.

    The list travels in the hidden input "custom_fonts"; CustomFonts::save() checks every file
    again on the server (a Media Library font whose bytes are the slot's format), so nothing
    here is trusted. EOT and SVG fonts are not offered: no current browser needs them, and an
    SVG font is a document that can carry script.
--}}
@php
    $cfInitial = \FalconCms\Core\Support\CustomFonts\CustomFonts::forEditor();
@endphp
<style id="falcon-custom-fonts-live"></style>
<div x-data="falconCustomFonts(@js($cfInitial), @js(route('admin.customizer.font-info')))" x-init="init()" class="space-y-3 max-w-[460px]">
    <input type="hidden" name="custom_fonts" :value="payload()">

    <template x-for="(font, i) in fonts" :key="font.key">
        <div class="border border-[#dcdcde] rounded bg-white overflow-hidden">
            <div class="flex items-center gap-2 px-3 h-10 bg-[#f0f0f1] border-b border-[#dcdcde] cursor-pointer select-none" @click="font.open = !font.open">
                <span class="text-[16px] font-bold text-[#1d2327] w-4 text-center" x-text="font.open ? '–' : '+'"></span>
                <span class="text-[13px] text-[#1d2327] truncate" x-show="!font.open"
                      x-text="(font.family || 'Untitled font') + ' · ' + label(font) + (font.style === 'italic' ? ' italic' : '')"></span>
            </div>
            <div x-show="font.open" class="px-4 py-4 space-y-4">
                <div>
                    <label class="block text-[13px] text-[#1d2327] mb-1.5">Font Name</label>
                    <input type="text" x-model="font.family" maxlength="60" class="wp-input w-full h-9 text-[13px]" placeholder="e.g. Brand Sans">
                </div>
                <div>
                    <label class="block text-[13px] text-[#1d2327] mb-1.5">Font Weight</label>
                    <select @change="setWeight(font, $event.target.value)" class="wp-input w-full h-9 text-[13px]">
                        <template x-for="w in weights" :key="w[0]">
                            <option :value="w[0]" x-text="w[1] + ' ' + w[0]" :selected="!font.weight_to && font.weight === w[0]"></option>
                        </template>
                        <option value="variable" :selected="!!font.weight_to">Variable (a range of weights in one file)</option>
                    </select>
                    <div x-show="font.weight_to" class="flex items-center gap-2 mt-2 text-[12px] text-[#50575e]">
                        <span>From</span>
                        <select x-model.number="font.weight" class="wp-input h-8 text-[12px] flex-1">
                            <template x-for="w in weights" :key="w[0]">
                                <option :value="w[0]" x-text="w[0]" :selected="font.weight === w[0]" :disabled="w[0] >= font.weight_to"></option>
                            </template>
                        </select>
                        <span>to</span>
                        <select x-model.number="font.weight_to" class="wp-input h-8 text-[12px] flex-1">
                            <template x-for="w in weights" :key="w[0]">
                                <option :value="w[0]" x-text="w[0]" :selected="font.weight_to === w[0]" :disabled="w[0] <= font.weight"></option>
                            </template>
                        </select>
                    </div>
                    <p x-show="font.info && font.info.from === 'font' && !mismatch(font)" class="mt-1.5 text-[11px] text-[#00a32a]"
                       x-text="font.info ? 'Matches the file: ' + describe(font.info) + '.' : ''"></p>
                    <div x-show="mismatch(font)" class="mt-2 rounded border border-[#dba617] bg-[#fcf9e8] px-3 py-2 text-[12px] text-[#50575e]">
                        <span x-text="font.info ? 'The file itself is ' + describe(font.info) + '. Set otherwise, browsers use it at the wrong weight and fake the others.' : ''"></span>
                        <button type="button" @click="apply(font, font.info)" class="ml-1 text-[#2271b1] underline">Use what the file says</button>
                    </div>
                    <p x-show="font.info && font.info.from === 'name'" class="mt-1.5 text-[11px] text-[#646970]">A WOFF2 cannot be read here, so the weight was guessed from its file name — check it.</p>
                </div>
                <div>
                    <label class="block text-[13px] text-[#1d2327] mb-1.5">Font Style</label>
                    <select x-model="font.style" class="wp-input w-full h-9 text-[13px]">
                        <option value="normal" :selected="font.style === 'normal'">Normal</option>
                        <option value="italic" :selected="font.style === 'italic'">Italic</option>
                    </select>
                </div>

                <div class="space-y-2.5">
                    <template x-for="fmt in formats" :key="fmt">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-12 text-[12px] text-[#1d2327] uppercase" x-text="fmt"></span>
                                <input type="text" readonly :value="font.names[fmt] || ''" :placeholder="fmt === 'woff' && autoWoff(font) ? 'Made from the ' + autoWoff(font) + ' on save' : ''"
                                       class="wp-input flex-1 min-w-0 h-8 text-[12px] bg-white" :title="font.files[fmt] || ''">
                                <button type="button" @click="pick(font, fmt)" :title="'Choose a ' + fmt.toUpperCase() + ' font from the Media Library'"
                                        class="w-9 h-8 rounded bg-[#5cb85c] hover:bg-[#4cae4c] text-white flex items-center justify-center">
                                    <span class="material-symbols-outlined" style="font-size:17px !important;">upload</span>
                                </button>
                                <button type="button" @click="clear(font, fmt)" :title="'Remove the ' + fmt.toUpperCase() + ' file'"
                                        class="w-9 h-8 rounded bg-[#d63638] hover:bg-[#b32d2e] text-white flex items-center justify-center">
                                    <span class="material-symbols-outlined" style="font-size:17px !important;">close</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="hasFile(font)" class="rounded bg-[#f6f7f7] px-3 py-2 text-[20px] leading-snug text-[#1d2327] truncate"
                     :style="'font-family:\'' + (font.family || '__cf' + font.key) + '\', sans-serif;font-weight:' + font.weight + ';font-style:' + font.style">
                    The quick brown fox jumps over the lazy dog
                </div>

                <div class="flex justify-end">
                    <button type="button" @click="remove(i)" class="px-4 h-8 rounded border border-[#dcdcde] text-[13px] text-[#d63638] hover:border-[#d63638] bg-white">Delete</button>
                </div>
            </div>
        </div>
    </template>

    <div class="flex justify-end">
        <button type="button" @click="add()" class="px-5 h-9 rounded bg-[#2271b1] hover:bg-[#135e96] text-white text-[13px] font-semibold">Add</button>
    </div>
    <p class="text-[11px] text-[#646970]">Changes are kept when you press <strong>Save Changes</strong>. Make sure each font's licence allows using it on a website.</p>
</div>

<script>
function falconCustomFonts(initial, infoUrl) {
    let seq = 0;
    const entry = (e = {}) => ({
        key: ++seq, id: e.id || '', family: e.family || '', weight: e.weight || 400, weight_to: e.weight_to || 0, style: e.style || 'normal',
        files: Object.assign({}, e.files || {}), names: Object.assign({}, e.names || {}), open: !e.id, info: null,
    });
    return {
        fonts: (initial || []).map(entry),
        formats: ['woff2', 'woff', 'ttf', 'otf'],
        weights: [[100, 'Thin'], [200, 'Extra Light'], [300, 'Light'], [400, 'Normal'], [500, 'Medium'], [600, 'Semi Bold'], [700, 'Bold'], [800, 'Extra Bold'], [900, 'Black']],
        init() {
            if (!this.fonts.length) this.add();
            // What each saved font's file says about itself, to flag a weight set wrong.
            this.fonts.forEach(f => { const p = this.readable(f); if (p) this.inspect(p).then(i => { f.info = i; }); });
            // Previews use the fonts as they are being set up, before anything is saved.
            this.$watch('fonts', () => this.paint(), { deep: true });
            this.paint();
        },
        payload() {
            return JSON.stringify(this.fonts.map(f => ({ id: f.id, family: f.family, weight: f.weight, weight_to: f.weight_to, style: f.style, files: f.files })));
        },
        label(f) { return f.weight_to ? f.weight + '–' + f.weight_to : String(f.weight); },
        describe(i) { return (i.weight_to ? 'variable, weights ' + i.weight + '–' + i.weight_to : 'weight ' + i.weight) + (i.style === 'italic' ? ', italic' : ''); },
        setWeight(f, v) {
            if (v === 'variable') {
                const i = f.info && f.info.weight_to ? f.info : null;
                f.weight = i ? i.weight : 100; f.weight_to = i ? i.weight_to : 900;
            } else { f.weight = +v; f.weight_to = 0; }
        },
        // Only what was read from the font's own tables is sure enough to argue with.
        mismatch(f) {
            const i = f.info;
            return !!i && i.from === 'font' && (i.weight !== f.weight || i.weight_to !== f.weight_to || i.style !== f.style);
        },
        apply(f, i) { f.weight = i.weight; f.weight_to = i.weight_to; f.style = i.style; },
        // TTF / OTF / WOFF can be read; a WOFF2 only by its name.
        readable(f) { return f.files.ttf || f.files.otf || f.files.woff || f.files.woff2 || ''; },
        inspect(path) {
            return fetch(infoUrl + '?path=' + encodeURIComponent(path), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : null).catch(() => null);
        },
        hasFile(f) { return this.formats.some(x => f.files[x]); },
        autoWoff(f) { return !f.files.woff && (f.files.ttf ? 'TTF' : (f.files.otf ? 'OTF' : '')); },
        add() { this.fonts.push(entry()); },
        remove(i) {
            const f = this.fonts[i];
            if (this.hasFile(f) && !confirm('Remove “' + (f.family || 'this font') + '”? The files stay in the Media Library.')) return;
            this.fonts.splice(i, 1);
        },
        clear(f, fmt) { delete f.files[fmt]; delete f.names[fmt]; },
        pick(f, fmt) {
            if (typeof window.openMediaModal !== 'function') { alert('The Media Library is not available on this page.'); return; }
            window.openMediaModal((m) => {
                const path = String(m.path || '');
                if (!path.toLowerCase().endsWith('.' + fmt)) {
                    alert('That is not a ' + fmt.toUpperCase() + ' file. Pick a .' + fmt + ' font for this slot.');
                    return;
                }
                f.files[fmt] = path;
                f.names[fmt] = m.filename || path.split('/').pop();
                const empty = !f.family;
                this.inspect(this.readable(f)).then(i => {
                    if (!i) return;
                    f.info = i;
                    if (empty && !f.family) f.family = i.family;
                    // The font's own tables are the truth; a name guess only fills a new box.
                    if (i.from === 'font' || empty) this.apply(f, i);
                });
            }, { fontFormat: fmt });
        },
        paint() {
            const order = { woff2: 'woff2', woff: 'woff', ttf: 'truetype', otf: 'opentype' };
            const css = this.fonts.filter(f => this.hasFile(f)).map(f => {
                const name = (f.family || '__cf' + f.key).replace(/['\\<>]/g, '');
                const src = Object.keys(order).filter(x => f.files[x]).map(x => "url('/storage/" + f.files[x].replace(/['\\\s]/g, '') + "') format('" + order[x] + "')");
                return "@font-face{font-family:'" + name + "';src:" + src.join(',') + ';font-weight:' + this.label(f).replace('–', ' ') + ';font-style:' + f.style + ';font-display:swap}';
            }).join('');
            const el = document.getElementById('falcon-custom-fonts-live');
            if (el) el.textContent = css;
        },
    };
}
</script>
