{{-- Reusable Button control (label + url + open-in target).
     Vars: $key (setting key for label; url = {key}_url, target = {key}_target), $label (optional), $placeholder (optional) --}}
@php
    $label = $label ?? 'Button';
    $placeholder = $placeholder ?? 'Click here';
@endphp
<div>
    <div class="flex justify-between items-center mb-3">
        <label class="text-[12px] font-bold text-[#333]">{{ $label }}</label>
    </div>
    <div class="space-y-2 p-3 border border-slate-100 rounded-lg bg-slate-50/30">
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Button Label</label>
            <input type="text" v-model="editingElement.settings.{{ $key }}"
                   placeholder="{{ $placeholder }}"
                   class="w-full border border-slate-200 bg-white rounded px-3 py-2 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[9px] font-bold text-slate-400 uppercase block mb-1">Link URL</label>
            <div class="flex gap-1.5 items-center">
                <input type="text" v-model="editingElement.settings.{{ $key }}_url"
                       placeholder="https://..."
                       class="flex-1 border border-slate-200 bg-white rounded px-3 py-2 text-[12px] text-slate-600 focus:outline-none focus:border-[#0091ea]">
                <button @click="openMediaModal('{{ $key }}_url')" title="Browse"
                        class="shrink-0 w-8 h-8 flex items-center justify-center bg-slate-100 border border-slate-200 rounded hover:bg-[#1a5a96] hover:text-white text-slate-400 transition-all">
                    <i class="fa fa-upload text-xs"></i>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[11px] text-slate-500 shrink-0">Open in:</span>
            <div class="flex bg-white border border-slate-200 rounded p-0.5">
                <button @click="editingElement.settings.{{ $key }}_target = '_self'"
                        :class="editingElement.settings.{{ $key }}_target !== '_blank' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-3 py-1 text-[11px] font-semibold rounded transition-all">Same tab</button>
                <button @click="editingElement.settings.{{ $key }}_target = '_blank'"
                        :class="editingElement.settings.{{ $key }}_target === '_blank' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="px-3 py-1 text-[11px] font-semibold rounded transition-all">New tab</button>
            </div>
        </div>
    </div>
</div>
