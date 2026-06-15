{{-- Reusable CSS Class + CSS ID controls. Binds editingElement.settings.cssClass / cssId --}}
<div class="grid grid-cols-1 gap-6">
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">CSS Class</label>
        </div>
        <input type="text" v-model="editingElement.settings.cssClass"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    </div>
    <div>
        <div class="flex justify-between items-center mb-3">
            <label class="text-[12px] font-bold text-[#333]">CSS ID</label>
        </div>
        <input type="text" v-model="editingElement.settings.cssId"
               class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
    </div>
</div>
