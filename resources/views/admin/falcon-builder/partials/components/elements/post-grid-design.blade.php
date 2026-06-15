{{-- CSS Class --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">CSS Class</label>
    <input type="text" v-model="editingElement.settings.cssClass"
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
</div>

{{-- CSS ID --}}
<div>
    <label class="text-[12px] font-bold text-[#333] block mb-2">CSS ID</label>
    <input type="text" v-model="editingElement.settings.cssId"
           class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
</div>

<div class="p-4 bg-slate-50 rounded border border-dashed border-slate-200 text-center">
    <i class="fa fa-info-circle text-slate-300 text-2xl mb-2 block"></i>
    <p class="text-[11px] text-slate-400">All layout settings are in the <strong class="text-slate-500">Content</strong> tab.</p>
</div>
