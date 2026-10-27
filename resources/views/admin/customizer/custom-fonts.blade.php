{{--
    Customizer → Typography → Custom Font.

    Uploads go straight to Admin\CustomFontsController (not through the Customizer's Save):
    each answer brings back the whole list and its @font-face CSS, which this screen puts in
    the page at once, so every preview below is drawn in its own font.
--}}
@php
    $cfInitial = \FalconCms\Core\Support\CustomFonts\CustomFonts::forEditor();
    $cfCss = \FalconCms\Core\Support\CustomFonts\CustomFonts::css();
@endphp
<style id="falcon-custom-fonts-live">{!! $cfCss !!}</style>
<div x-data="falconCustomFonts(@js($cfInitial))" class="space-y-5 max-w-[900px]">

    {{-- Upload --}}
    <div class="rounded-lg border-2 border-dashed transition-colors p-6 text-center"
         :class="dragging ? 'border-[#2271b1] bg-[#f0f6fc]' : 'border-[#c3c4c7] bg-[#fbfbfc]'"
         @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; send($event.dataTransfer.files)">
        <span class="material-symbols-outlined text-[#2271b1]" style="font-size:34px !important;">font_download</span>
        <p class="text-[14px] font-semibold text-[#1d2327] mt-1">Drop font files here, or
            <label class="text-[#2271b1] underline cursor-pointer">choose files
                <input type="file" class="hidden" multiple accept=".woff2,.woff,.ttf,.otf" @change="send($event.target.files); $event.target.value = ''">
            </label>
        </p>
        <p class="text-[12px] text-[#646970] mt-1">WOFF2, WOFF, TTF or OTF — several at once is fine. The name, weight and italic are read from each file.</p>
        <p x-show="busy" x-cloak class="text-[12px] text-[#2271b1] mt-2">Uploading…</p>
    </div>

    <template x-if="added.length">
        <div class="rounded border border-[#00a32a]/40 bg-[#edfaef] px-3 py-2 text-[12px] text-[#1d2327]">
            <strong>Added:</strong> <span x-text="added.join(', ')"></span>
        </div>
    </template>
    <template x-if="errors.length">
        <div class="rounded border border-[#d63638]/40 bg-[#fcf0f1] px-3 py-2 text-[12px] text-[#1d2327] space-y-0.5">
            <template x-for="e in errors" :key="e"><div x-text="e"></div></template>
        </div>
    </template>

    {{-- The fonts --}}
    <template x-if="!fonts.length">
        <p class="text-[13px] text-[#646970]">No custom fonts yet.</p>
    </template>
    <template x-for="font in fonts" :key="font.id">
        <div class="rounded-lg border border-[#dcdcde] bg-white overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 bg-[#f6f7f7] border-b border-[#dcdcde]">
                <input type="text" :value="font.family" @change="save(font, { family: $event.target.value })" maxlength="60"
                       class="wp-input h-8 text-[13px] font-semibold w-[220px]" title="Font name — the name shown in every font list">
                <label class="text-[12px] text-[#646970] flex items-center gap-1.5">Fallback
                    <select :value="font.fallback" @change="save(font, { fallback: $event.target.value })" class="wp-input h-8 text-[12px]">
                        @foreach(\FalconCms\Core\Support\CustomFonts\CustomFonts::FALLBACKS as $fb)
                            <option value="{{ $fb }}">{{ $fb }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[12px] text-[#2271b1] cursor-pointer ml-auto flex items-center gap-1" title="Add more files to this font">
                    <span class="material-symbols-outlined" style="font-size:16px !important;">upload</span> Add files
                    <input type="file" class="hidden" multiple accept=".woff2,.woff,.ttf,.otf" @change="send($event.target.files, font.id); $event.target.value = ''">
                </label>
                <button type="button" @click="remove(font)" class="text-[12px] text-[#d63638] hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:16px !important;">delete</span> Delete font
                </button>
            </div>
            <div class="px-4 py-3 text-[26px] leading-snug text-[#1d2327] truncate" :style="'font-family:\'' + font.family + '\', ' + font.fallback"
                 x-text="sample"></div>
            <table class="w-full text-[12px] border-t border-[#f0f0f1]">
                <tbody>
                    <template x-for="v in font.variants" :key="v.id">
                        <tr class="border-b border-[#f0f0f1] last:border-0">
                            <td class="px-4 py-2 w-[150px]">
                                <select :value="v.weight" @change="reweigh(font, v, { weight: +$event.target.value })" class="wp-input h-7 text-[12px]">
                                    <template x-for="w in [100,200,300,400,500,600,700,800,900]" :key="w">
                                        <option :value="w" x-text="w + ' — ' + weightName(w)" :selected="v.weight === w"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-2 py-2 w-[110px]">
                                <select :value="v.style" @change="reweigh(font, v, { style: $event.target.value })" class="wp-input h-7 text-[12px]">
                                    <option value="normal" :selected="v.style === 'normal'">Normal</option>
                                    <option value="italic" :selected="v.style === 'italic'">Italic</option>
                                </select>
                            </td>
                            <td class="px-2 py-2 text-[15px] truncate max-w-[260px]"
                                :style="'font-family:\'' + font.family + '\', ' + font.fallback + ';font-weight:' + v.weight + ';font-style:' + v.style">The quick brown fox</td>
                            <td class="px-2 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="fmt in ['woff2','woff','ttf','otf']" :key="fmt">
                                        <span x-show="v.files[fmt]" class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                                              :class="(v.generated || []).includes(fmt) ? 'bg-[#f0f6fc] text-[#2271b1] border border-dashed border-[#2271b1]/50' : 'bg-[#f0f0f1] text-[#3c434a]'"
                                              :title="(v.generated || []).includes(fmt) ? 'Made automatically from the TTF / OTF, for every browser' : 'Uploaded'"
                                              x-text="fmt + ((v.generated || []).includes(fmt) ? ' · auto' : '')"></span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" @click="removeVariant(font, v)" class="text-[#a7aaad] hover:text-[#d63638]" title="Delete this weight">
                                    <span class="material-symbols-outlined" style="font-size:17px !important;">close</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    {{-- How it works --}}
    <div class="rounded border border-[#c3c4c7] bg-[#f6f7f7] px-4 py-3 text-[12px] text-[#3c434a] leading-relaxed space-y-1">
        <p><strong>Where to use it:</strong> pick it under <em>Custom Fonts</em> in Typography → General, in the page builder's Font Family, or for Motion Visual text.</p>
        <p><strong>Every browser:</strong> WOFF2 is the smallest and works in all current browsers; WOFF works in every browser, old ones too. Upload a TTF or OTF and a WOFF copy is made for you. Fonts load with <code>font-display: swap</code>, so text is never hidden while they arrive.</p>
        <p><strong>Weights:</strong> upload one file per weight and style (Regular, Bold, Italic…). Only the files a page actually uses are downloaded.</p>
        <p class="text-[#996800]"><strong>Licence:</strong> make sure the font's licence allows using it on a website.</p>
    </div>
</div>

<script>
function falconCustomFonts(initial) {
    // Built from the real routes (the admin path can be renamed), with placeholder ids swapped in.
    const fontUrl = @json(route('admin.customizer.custom-fonts.update', ['font' => 'fontid0']));
    const variantUrl = @json(route('admin.customizer.custom-fonts.variant', ['font' => 'fontid0', 'variant' => 'variantid0']));
    const routes = {
        upload: @json(route('admin.customizer.custom-fonts.upload')),
        font: (id) => fontUrl.replace('fontid0', id),
        variant: (id, v) => variantUrl.replace('fontid0', id).replace('variantid0', v),
    };
    const token = @json(csrf_token());
    return {
        fonts: initial || [], errors: [], added: [], busy: false, dragging: false,
        sample: 'The quick brown fox jumps over the lazy dog 0123456789',
        weightName(w) {
            return { 100: 'Thin', 200: 'Extra Light', 300: 'Light', 400: 'Regular', 500: 'Medium', 600: 'Semi Bold', 700: 'Bold', 800: 'Extra Bold', 900: 'Black' }[w] || '';
        },
        async call(url, method, body) {
            this.busy = true;
            try {
                const res = await fetch(url, { method, body, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json().catch(() => ({}));
                if (!res.ok && !data.fonts) {
                    this.errors = [data.message || ('The server answered ' + res.status + '.')];
                    return;
                }
                this.fonts = data.fonts || [];
                this.errors = data.errors || [];
                this.added = data.added || [];
                // The fonts in the page now, so every preview here uses them at once.
                document.getElementById('falcon-custom-fonts-live').textContent = data.css || '';
            } catch (e) {
                this.errors = ['The request did not get through: ' + e.message];
            } finally {
                this.busy = false;
            }
        },
        send(files, into) {
            if (!files || !files.length) return;
            const fd = new FormData();
            Array.from(files).forEach(f => fd.append('fonts[]', f));
            if (into) fd.append('into', into);
            this.call(routes.upload, 'POST', fd);
        },
        save(font, fields) {
            const fd = new FormData();
            Object.keys(fields).forEach(k => fd.append(k, fields[k]));
            this.call(routes.font(font.id), 'POST', fd);
        },
        reweigh(font, v, change) {
            const fd = new FormData();
            fd.append('weight', change.weight !== undefined ? change.weight : v.weight);
            fd.append('style', change.style !== undefined ? change.style : v.style);
            this.call(routes.variant(font.id, v.id), 'POST', fd);
        },
        remove(font) {
            if (!confirm('Delete “' + font.family + '” and all its files? Text set in it falls back to ' + font.fallback + '.')) return;
            this.call(routes.font(font.id), 'DELETE');
        },
        removeVariant(font, v) {
            if (!confirm('Delete ' + font.family + ' ' + v.weight + (v.style === 'italic' ? ' italic' : '') + '?')) return;
            this.call(routes.variant(font.id, v.id), 'DELETE');
        },
    };
}
</script>
