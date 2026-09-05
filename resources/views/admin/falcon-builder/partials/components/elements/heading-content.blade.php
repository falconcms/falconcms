{{-- Heading — Content tab.

     This element has been in the builder since the beginning with no settings panel at
     all: both tabs opened empty, and the placeholder they held said "we can add these
     later". Everything the renderer already reads — the text, the tag, the alignment —
     could only be reached by editing the shortcode by hand.

     The tag matters more than it looks. It is what makes a heading a heading rather than
     large text, so it decides the page's outline for a screen reader and for search, and
     it is what the Table of Contents element lists. With no way to choose it, every
     heading on a page was an H2 and a contents list could only ever be flat.

     The keys below are the ones the renderer and the shortcode already use — `tag`, not
     `htmlTag`, which is the Title element's — so existing pages keep working untouched. --}}

{{-- ══ TEXT ══ --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">Text</label>
    <input type="text" v-model="editingElement.settings.title" placeholder="New Heading"
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
</div>

{{-- ══ TAG ══ --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">HTML tag</label>
    <div class="grid grid-cols-6 gap-1">
        <button v-for="tag in ['h1','h2','h3','h4','h5','h6']" :key="tag"
                @click="editingElement.settings.tag = tag"
                :class="(editingElement.settings.tag || 'h2') === tag ? 'bg-[#2271b1] text-white' : 'bg-slate-50 text-slate-500 hover:bg-slate-100'"
                class="py-2 rounded text-[11px] font-black uppercase transition-all"
                v-text="tag"></button>
    </div>
    <div class="flex gap-1 mt-1.5">
        <button v-for="tag in ['p','div','span']" :key="tag"
                @click="editingElement.settings.tag = tag"
                :class="editingElement.settings.tag === tag ? 'bg-[#2271b1] text-white' : 'bg-slate-50 text-slate-400 hover:bg-slate-100'"
                class="flex-1 py-1.5 rounded text-[10px] font-bold uppercase transition-all"
                v-text="tag"></button>
    </div>
    <p class="text-[11px] text-slate-500 mt-2">
        H1 is the page's own title, so a section is usually H2 and a subsection H3. The tag is
        what a screen reader, a search engine and the Table of Contents element all read as the
        page's outline — the three lower options are for text that only <em>looks</em> like a
        heading and should be left out of it.
    </p>
</div>

{{-- ══ ALIGNMENT ══ --}}
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">Alignment</label>
        <div class="flex gap-1 items-center">
            <button @click="setResponsiveVal(editingElement.settings, 'textAlign', device, '')" title="Reset Value"
                    class="text-slate-300 hover:text-red-500 transition-colors">
                <i class="fa fa-undo text-[10px]"></i>
            </button>
            <div class="relative inline-block">
                <button @click="activeResponsiveMenu = activeResponsiveMenu === 'hdAlign' ? null : 'hdAlign'"
                        class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] transition-all flex items-center gap-1" title="Responsive Mode">
                    <i class="fa" :class="device === 'desktop' ? 'fa-desktop' : (device === 'tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
                    <i class="fa fa-caret-down text-[8px] text-slate-400"></i>
                </button>
                <div v-show="activeResponsiveMenu === 'hdAlign'" class="absolute right-0 mt-1 bg-white border border-slate-200 rounded shadow-lg z-50 flex gap-0.5 p-1 min-w-max">
                    <button @click="device = 'desktop'; activeResponsiveMenu = null" :class="device === 'desktop' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Large (Desktop)">
                        <i class="fa fa-desktop text-[11px]"></i>
                    </button>
                    <button @click="device = 'tablet'; activeResponsiveMenu = null" :class="device === 'tablet' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Medium (Tablet)">
                        <i class="fa fa-tablet-alt text-[11px]"></i>
                    </button>
                    <button @click="device = 'mobile'; activeResponsiveMenu = null" :class="device === 'mobile' ? 'bg-[#2271b1] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-[10px] flex items-center justify-center transition-all" title="Small (Mobile)">
                        <i class="fa fa-mobile-alt text-[11px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
        <button v-for="opt in [{ v: 'left', i: 'fa-align-left' }, { v: 'center', i: 'fa-align-center' }, { v: 'right', i: 'fa-align-right' }]"
                :key="opt.v"
                @click="setResponsiveVal(editingElement.settings, 'textAlign', device, opt.v)"
                :class="(getResponsiveVal(editingElement.settings, 'textAlign', device) || 'left') === opt.v ? 'bg-[#2271b1] text-white' : 'text-slate-400 hover:text-slate-600'"
                class="flex-1 py-2 text-[12px] border-r border-slate-200 last:border-r-0 transition-all">
            <i class="fa" :class="opt.i"></i>
        </button>
    </div>
</div>

{{-- ══ CSS ══ --}}
<div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-50">
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
        <input type="text" v-model="editingElement.settings.cssClass"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    </div>
    <div>
        <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
        <input type="text" v-model="editingElement.settings.cssId"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">
            An id here is also the anchor a Table of Contents links to. Left empty, one is
            worked out from the text.
        </p>
    </div>
</div>
