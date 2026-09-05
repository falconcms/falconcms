{{-- Table of Contents — Content tab.

     There is no list to edit here: the list is the page's own headings, found when the
     page renders. What this tab decides is which headings count and where to look for
     them. --}}

<div class="rounded border border-[#0091ea]/25 bg-[#2271b1]/6 px-3 py-2.5">
    <p class="text-[11px] text-slate-600 leading-relaxed">
        <i class="fas fa-circle-info text-[#0091ea] mr-1"></i>
        This list is built from the headings on the page, every time it loads — add a
        Heading element and it appears here on its own. Headings that have no anchor are
        given one, so every entry links somewhere.
    </p>
</div>

{{-- ══ TITLE ══ --}}
<div>
    <div class="flex justify-between items-center mb-2">
        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Title</label>
        <button @click="editingElement.settings.title = ''" title="No title"
                class="text-[10px] text-slate-400 hover:text-red-500 transition-colors">clear</button>
    </div>
    <input type="text" v-model="editingElement.settings.title" placeholder="On this page"
           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
</div>

{{-- ══ WHICH HEADINGS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Which headings</div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">From</label>
            <select v-model.number="editingElement.settings.minLevel" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                <option v-for="lv in fcTocLevels" :key="lv" :value="lv">H@{{ lv }}</option>
            </select>
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">To</label>
            <select v-model.number="editingElement.settings.maxLevel" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                <option v-for="lv in fcTocLevels" :key="lv" :value="lv">H@{{ lv }}</option>
            </select>
        </div>
    </div>
    <p class="text-[11px] text-slate-500 -mt-2">
        H1 is the page title, not a section, so it is never listed. H2 to H3 suits most pages.
    </p>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Hide when there are fewer than</label>
        <input type="number" v-model.number="editingElement.settings.minHeadings" min="1" max="20"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">
            A contents list of one entry is noise. Below this the element does not render at all.
        </p>
    </div>
</div>

{{-- ══ WHERE TO LOOK ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Where to look</div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Scope</label>
        <input type="text" v-model="editingElement.settings.scope" placeholder="the content this element sits in"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] font-mono focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">
            A CSS selector. Leave it empty and the element finds the article or main content
            around itself, which is right on almost every theme. Set it when the page has more
            than one region of headings — <code>.entry-content</code>, <code>#docs-body</code>.
        </p>
    </div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Skip</label>
        <input type="text" v-model="editingElement.settings.exclude" placeholder=".no-toc, aside"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] font-mono focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">
            Headings matching this, or inside something matching it, are left out — a sidebar's
            own headings, a related-posts block.
        </p>
    </div>
</div>

{{-- ══ BEHAVIOUR ══ --}}
<div class="space-y-3">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Behaviour</div>

    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.scrollSpy" class="mt-0.5 rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Highlight the section being read</span>
    </label>

    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.smoothScroll" class="mt-0.5 rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Scroll smoothly to the heading</span>
    </label>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Stop this far below the top</label>
        <input type="number" v-model.number="editingElement.settings.scrollOffset" min="0" max="400"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">
            Room for a sticky site header, which would otherwise cover the heading the reader
            just clicked.
        </p>
    </div>

    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.progress" class="mt-0.5 rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show a reading-progress bar</span>
    </label>

    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.backToTop" class="mt-0.5 rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show a "Back to top" link</span>
    </label>

    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.collapsible" class="mt-0.5 rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Collapsible — the title opens and closes the list</span>
    </label>

    <label v-if="editingElement.settings.collapsible" class="flex items-center gap-2 cursor-pointer pl-6">
        <input type="checkbox" v-model="editingElement.settings.openByDefault" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Open when the page loads</span>
    </label>
</div>

{{-- ══ STICKY ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Position</div>

    <label class="flex items-start gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.sticky" class="mt-0.5 rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Stick to the screen while the reader scrolls</span>
    </label>

    <div v-if="editingElement.settings.sticky" class="pl-6">
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Distance from the top</label>
        <input type="number" v-model.number="editingElement.settings.stickyTop" min="0" max="400"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">
            Sticking works inside a column that is taller than the list — put it in a narrow
            column beside the content.
        </p>
    </div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Maximum height</label>
        <input type="number" v-model.number="editingElement.settings.maxHeight" min="0" max="2000"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1.5">0 for no limit. Above it the list scrolls on its own.</p>
    </div>
</div>
