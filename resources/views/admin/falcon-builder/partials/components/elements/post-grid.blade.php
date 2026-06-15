<div v-if="el.type === 'post_grid'"
     class="w-full py-3"
     :style="getCanvasVisibilityStyle(el.settings)">
    <div class="border border-dashed border-[#0091ea]/30 rounded-lg bg-gradient-to-br from-[#f8fbff] to-[#f0f6fb] p-5">

        {{-- Header Row --}}
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 bg-[#2271b1] rounded flex items-center justify-center">
                    <i class="fa fa-th-large text-white text-[11px]"></i>
                </div>
                <span class="text-[12px] font-black text-[#1d2327] uppercase tracking-wider">Post Grid</span>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="el.settings.post_card_id && postCardsMap[el.settings.post_card_id]"
                      class="text-[10px] bg-[#2271b1] text-white px-2 py-0.5 rounded-full font-bold">
                    @{{ postCardsMap[el.settings.post_card_id] }}
                </span>
                <span v-else class="text-[10px] bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full font-bold">
                    No card selected
                </span>
            </div>
        </div>

        {{-- Grid Mockup --}}
        <div class="grid gap-3"
             :style="{ gridTemplateColumns: 'repeat(' + Math.min(el.settings.columns || 3, 4) + ', 1fr)' }">
            <div v-for="n in Math.min(el.settings.posts_count || 6, el.settings.columns || 3)"
                 :key="n"
                 class="bg-white border border-slate-200/80 rounded-lg overflow-hidden shadow-sm">
                <div class="h-16 bg-gradient-to-br from-slate-100 to-slate-200"></div>
                <div class="p-2.5 space-y-1.5">
                    <div class="h-2.5 bg-slate-200 rounded w-full"></div>
                    <div class="h-2 bg-slate-100 rounded w-4/5"></div>
                    <div class="h-2 bg-slate-100 rounded w-3/5"></div>
                    <div class="h-5 bg-[#2271b1]/10 rounded w-1/3 mt-2"></div>
                </div>
            </div>
        </div>

        {{-- Info Row --}}
        <div class="mt-3 pt-3 border-t border-[#0091ea]/10 flex items-center gap-4 text-[10px] text-slate-400 font-bold">
            <span class="flex items-center gap-1">
                <i class="fa fa-columns text-[#0091ea]/50"></i> @{{ el.settings.columns || 3 }} cols
            </span>
            <span class="flex items-center gap-1">
                <i class="fa fa-list text-[#0091ea]/50"></i> @{{ el.settings.posts_count || 6 }} posts
            </span>
            <span v-if="el.settings.category_slug" class="flex items-center gap-1">
                <i class="fa fa-tag text-[#0091ea]/50"></i> @{{ el.settings.category_slug }}
            </span>
            <span v-if="el.settings.post_type && el.settings.post_type !== 'post'" class="flex items-center gap-1">
                <i class="fa fa-file-alt text-[#0091ea]/50"></i> @{{ el.settings.post_type }}
            </span>
        </div>
    </div>
</div>
