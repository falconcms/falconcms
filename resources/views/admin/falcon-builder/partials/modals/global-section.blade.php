<template v-if="showGlobalModal">
    <div class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 backdrop-blur-sm"
         @click.self="showGlobalModal = false">
        <div class="bg-white w-[420px] rounded-xl shadow-2xl overflow-hidden" @click.stop>
            <!-- Header -->
            <div class="bg-[#7c3aed] px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa fa-globe text-white text-sm"></i>
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Save as Global Section</h3>
                </div>
                <button @click="showGlobalModal = false" class="text-white/60 hover:text-white transition-colors">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-5">
                <p class="text-xs text-slate-500 mb-4">
                    Give this section a name. It will be saved globally and can be inserted on any page. Editing it on one page updates it everywhere.
                </p>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Section Name</label>
                <input type="text"
                       v-model="globalModalName"
                       @keydown.enter="saveAsGlobal"
                       placeholder="e.g. Homepage Hero, Footer CTA..."
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#7c3aed]/40 focus:border-[#7c3aed]"
                       autofocus>
            </div>

            <!-- Footer -->
            <div class="px-5 pb-5 flex justify-end gap-2">
                <button @click="showGlobalModal = false"
                        class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                    Cancel
                </button>
                <button @click="saveAsGlobal"
                        :disabled="!globalModalName.trim() || isSavingGlobal"
                        class="px-5 py-2 bg-[#7c3aed] text-white text-sm font-semibold rounded-lg hover:bg-[#6d28d9] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <i v-if="isSavingGlobal" class="fa fa-spinner fa-spin text-xs"></i>
                    <i v-else class="fa fa-globe text-xs"></i>
                    @{{ isSavingGlobal ? 'Saving…' : 'Save as Global' }}
                </button>
            </div>
        </div>
    </div>
</template>
