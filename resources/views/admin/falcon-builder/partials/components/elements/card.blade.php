<div v-if="el.type === 'card'"
     class="w-full py-2"
     :style="[getCanvasVisibilityStyle(el.settings), {
         marginTop:    (el.settings.marginTop    || 0) + (el.settings.marginTopUnit    || 'px'),
         marginRight:  (el.settings.marginRight  || 0) + (el.settings.marginRightUnit  || 'px'),
         marginBottom: (el.settings.marginBottom || 0) + (el.settings.marginBottomUnit || 'px'),
         marginLeft:   (el.settings.marginLeft   || 0) + (el.settings.marginLeftUnit   || 'px')
     }]">

    {{-- Header bar --}}
    <div class="flex items-center gap-1.5 mb-2 px-1">
        <div class="w-5 h-5 bg-[#2271b1] rounded flex items-center justify-center">
            <i class="fa fa-th-large text-white text-[9px]"></i>
        </div>
        <span class="text-[11px] font-black text-[#1d2327] uppercase tracking-wider">Card</span>
        <span v-if="el.settings.post_card_id && postCardsMap[el.settings.post_card_id]"
              class="text-[9px] bg-[#2271b1] text-white px-2 py-0.5 rounded-full font-bold">
            @{{ postCardsMap[el.settings.post_card_id] }}
        </span>
        <span v-else class="text-[9px] bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full font-bold">No card selected</span>
    </div>

    {{-- Body: only when a card is selected --}}
    <template v-if="el.settings.post_card_id">

        {{-- Loading indicator when fetching live preview --}}
        <div v-if="cardPreviewCache[el.id]?.loading"
             class="text-[10px] text-slate-400 flex items-center gap-1 mb-1 px-1">
            <i class="fa fa-spinner fa-spin text-[9px]"></i> Fetching posts…
        </div>

        {{-- Rendered frontend HTML preview --}}
        <div v-if="cardPreviewCache[el.id]?.html"
             v-safe-html="cardPreviewCache[el.id].html"
             :data-card-preview-id="el.id"
             style="pointer-events:none;padding:4px 6px">
        </div>

        {{-- No preview yet or fetch failed: skeleton placeholders --}}
        <div v-else
             class="grid"
             :style="{
                 gridTemplateColumns: 'repeat(' + cardPreviewCols(el.settings) + ', 1fr)',
                 columnGap: (el.settings.column_spacing ?? 24) + 'px',
                 rowGap:    (el.settings.row_spacing    ?? 24) + 'px'
             }">
            <div v-for="n in Math.min(cardPreviewCount(el.settings), 24)"
                 :key="n"
                 class="bg-white border border-slate-200/80 rounded-lg overflow-hidden shadow-sm">
                <div class="bg-gradient-to-br from-slate-100 to-slate-200" style="aspect-ratio:16/9;"></div>
                <div class="p-2 space-y-1.5">
                    <div class="h-2.5 bg-slate-200 rounded w-full"></div>
                    <div class="h-2 bg-slate-100 rounded w-3/4"></div>
                </div>
            </div>
        </div>

    </template>

    {{-- No card selected: centered empty state --}}
    <div v-else class="flex flex-col items-center justify-center gap-1.5 py-6 bg-amber-50 border border-dashed border-amber-200 rounded-lg">
        <i class="fa fa-th-large text-amber-300 text-xl"></i>
        <span class="text-[11px] font-bold text-amber-500 uppercase tracking-wide">No card selected</span>
        <span class="text-[10px] text-amber-400">Open settings to choose a card</span>
    </div>

</div>
