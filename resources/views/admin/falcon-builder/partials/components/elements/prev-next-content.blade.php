{{-- Previous / Next — Content tab.

     The only decision that matters here is where the order comes from. Typing both links
     by hand works for one page and falls apart at fifty: the links are duplicated
     everywhere and the first reorder makes every one of them wrong with nothing to say
     so. A site with a documentation sidebar has already written the order down in the
     menu that draws it, so that is the default. --}}

{{-- ══ SOURCE ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Order comes from</div>

    <div class="space-y-2">
        <label v-for="opt in [
                   { v: 'menu', t: 'A menu', d: 'The order the menu is in — usually the docs sidebar' },
                   { v: 'type', t: 'Pages of this type', d: 'When there is no menu to follow' },
                   { v: 'manual', t: 'Links I set myself', d: 'Both sides typed below' },
               ]" :key="opt.v"
               class="flex items-start gap-2 cursor-pointer border rounded px-3 py-2.5 transition-colors"
               :class="(editingElement.settings.source || 'menu') === opt.v ? 'border-[#0091ea] bg-[#0091ea]/5' : 'border-slate-200 hover:border-slate-300'">
            <input type="radio" :value="opt.v" v-model="editingElement.settings.source"
                   class="mt-0.5 border-slate-300 text-[#0091ea] focus:ring-0">
            <span>
                <span class="text-[12px] font-bold text-slate-700 block" v-text="opt.t"></span>
                <span class="text-[11px] text-slate-500" v-text="opt.d"></span>
            </span>
        </label>
    </div>

    <div v-if="(editingElement.settings.source || 'menu') === 'menu'">
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Menu</label>
        <select v-model="editingElement.settings.menuId"
                class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
            <option value="">Select a menu…</option>
            <option v-for="(name, id) in falconMenusList" :key="id" :value="id">@{{ name }}</option>
        </select>
        <p class="text-[11px] text-slate-500 mt-1.5">
            The menu is read the way a reader goes through it — each item, then everything
            under it, then the next. Reorder the menu and every Previous and Next on the site
            follows, because there is only one list.
        </p>
    </div>

    <div v-if="editingElement.settings.source === 'type'" class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Order by</label>
            <select v-model="editingElement.settings.orderBy"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                <option value="menu_order">Order field</option>
                <option value="date">Date published</option>
                <option value="title">Title</option>
            </select>
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Direction</label>
            <select v-model="editingElement.settings.orderDir"
                    class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                <option value="asc">First to last</option>
                <option value="desc">Last to first</option>
            </select>
        </div>
    </div>
</div>

{{-- ══ LABELS ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Labels</div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Previous</label>
            <input type="text" v-model="editingElement.settings.prevLabel" placeholder="Previous"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Next</label>
            <input type="text" v-model="editingElement.settings.nextLabel" placeholder="Next"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
    <p class="text-[11px] text-slate-500 -mt-2">Clear one to show only the page title.</p>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.showTitles" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show the page each link goes to</span>
    </label>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.showArrows" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show the arrows</span>
    </label>
</div>

{{-- ══ OVERRIDES ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">
        <span v-if="editingElement.settings.source === 'manual'">The two links</span>
        <span v-else>Override one side</span>
    </div>
    <p v-if="editingElement.settings.source !== 'manual'" class="text-[11px] text-slate-500 -mt-2">
        For the one page in a set that goes somewhere else — the last page of a tutorial
        pointing at the reference. Fill in a URL to replace that side; fill in only a title
        to rename what was found. Leave both empty and the order decides.
    </p>

    @foreach([['prev', 'Previous'], ['next', 'Next']] as [$side, $label])
    <div class="space-y-2">
        <div class="text-[11px] font-bold text-slate-500">{{ $label }}</div>
        <input type="text" v-model="editingElement.settings.{{ $side }}Url" placeholder="/docs/installation"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] font-mono focus:outline-none focus:border-[#0091ea]">
        <input type="text" v-model="editingElement.settings.{{ $side }}Title" placeholder="Page title"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
    </div>
    @endforeach
</div>
