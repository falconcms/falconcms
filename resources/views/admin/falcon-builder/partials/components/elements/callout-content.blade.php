{{-- Callout — Content tab.

     The variant list comes from CalloutStyles::variantOptions() via FC_CAL_VARIANT_OPTIONS,
     so a variant added in PHP appears here on its own, with its own colour and icon. --}}

{{-- ══ VARIANT ══ --}}
<div>
    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Type</label>
    <div class="grid grid-cols-3 gap-1.5">
        <button v-for="(name, slug) in fcCalVariantOptions" :key="slug"
                @click="editingElement.settings.variant = slug; if (!editingElement.settings.title) editingElement.settings.title = name"
                :class="editingElement.settings.variant === slug
                    ? 'border-[#0091ea] bg-[#0091ea]/5 text-[#0091ea]'
                    : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                class="flex items-center gap-1.5 border rounded px-2 py-2 text-[11px] font-semibold transition-colors">
            <i :class="fcCalVariantIcon(slug)" :style="{ color: fcCalVariantAccent(slug) }" class="text-[12px]"></i>
            <span class="truncate">@{{ name }}</span>
        </button>
    </div>
    <p class="text-[11px] text-slate-500 mt-2">
        The type is what the box <em>means</em> — it carries the colour and the icon. How it is
        drawn is the Style in the Design tab, and applies to every type alike.
    </p>
</div>

{{-- ══ TITLE ══ --}}
<div>
    <div class="flex justify-between items-center mb-2">
        <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Title</label>
        <button @click="editingElement.settings.title = ''" title="No title"
                class="text-[10px] text-slate-400 hover:text-red-500 transition-colors">clear</button>
    </div>
    <input type="text" v-model="editingElement.settings.title"
           :placeholder="fcCalVariantOptions[editingElement.settings.variant] || 'Note'"
           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
    <p class="text-[11px] text-slate-500 mt-1.5">Leave it empty for a box with no title.</p>
</div>

{{-- ══ BODY ══ --}}
<div>
    <label class="text-[11px] font-bold text-slate-600 uppercase tracking-wide block mb-2">Text</label>
    <textarea v-model="editingElement.settings.body" rows="7"
              class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] leading-relaxed font-mono focus:outline-none focus:border-[#0091ea]"
              placeholder="A blank line starts a new paragraph."></textarea>

    <div class="mt-2 rounded border border-slate-200 bg-slate-50 px-3 py-2.5">
        <p class="text-[11px] font-bold text-slate-500 mb-1.5">What you can write</p>
        <ul class="text-[11px] text-slate-500 space-y-1 leading-relaxed">
            <li><code>**bold**</code>, <code>*italic*</code>, <code>`code`</code></li>
            <li><code>[text](https://…)</code> a link, <code>[button Buy](https://…)</code> a button</li>
            <li>A line starting <code>-&nbsp;</code> is a bullet; <code>1.&nbsp;</code> is a numbered list</li>
            <li><code>[check]</code> and <code>[cross]</code> for a tick and a cross</li>
        </ul>
        <p class="text-[11px] text-slate-400 mt-2">
            Text, never HTML — which is why nothing typed here can carry a script.
        </p>
    </div>
</div>

{{-- ══ COLLAPSIBLE ══ --}}
<div class="space-y-3">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Behaviour</div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" v-model="editingElement.settings.collapsible" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Collapsible — the title opens and closes the box</span>
    </label>

    <label v-if="editingElement.settings.collapsible" class="flex items-center gap-2 cursor-pointer pl-6">
        <input type="checkbox" v-model="editingElement.settings.openByDefault" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Open when the page loads</span>
    </label>

    <p v-if="editingElement.settings.collapsible" class="text-[11px] text-slate-500 pl-6">
        Built on <code>&lt;details&gt;</code>, so it is keyboard-reachable and the browser's
        find-in-page can open it to reach the text inside.
    </p>
</div>
