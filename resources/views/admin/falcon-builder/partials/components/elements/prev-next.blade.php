{{-- Previous / Next — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/prev-next.blade.php.

     These are the page's real neighbours, not a sample. Which page comes before this one
     is a question about menus and database rows that the canvas cannot answer, so the
     server answers it for every source the author might switch to and hands the answers
     over as JSON; the fcPn* helpers pick one out. Changing the menu or the order in the
     panel shows what the published page will actually say. --}}
<div v-if="el.type === 'prev_next'"
     class="w-full"
     :style="getCanvasVisibilityStyle(el.settings)">

    <div v-if="fcPnPair(el).prev || fcPnPair(el).next" :style="fcPnGridStyle(el)">
        <template v-for="side in ['prev', 'next']" :key="side">
            <a v-if="fcPnPair(el)[side]" :style="fcPnLinkStyle(el, side)" @click.prevent>
                <i v-if="el.settings.showArrows !== false" :style="fcPnArrowStyle(el)"
                   v-text="side === 'prev' ? '←' : '→'"></i>
                <span style="min-width:0;">
                    <span v-if="fcPnLabel(el, side)" :style="fcPnLabelStyle(el)">@{{ fcPnLabel(el, side) }}</span>
                    <span v-if="el.settings.showTitles !== false" :style="fcPnTitleStyle(el)">@{{ fcPnPair(el)[side].title }}</span>
                </span>
            </a>
        </template>
    </div>

    {{-- Empty is a real answer here — this page is simply not in the sequence — and
         without saying which, the canvas would look the same as a broken element. --}}
    <div v-else
         style="padding:18px;border:1px dashed #c9d0d8;border-radius:8px;color:#79838F;font-size:13px;text-align:center;line-height:1.5;">
        Nothing to link to yet.<br>
        <span style="font-size:12px;opacity:.85;" v-text="fcPnEmptyReason(el)"></span>
    </div>

    <p v-if="fcPnPair(el).prev || fcPnPair(el).next"
       style="margin:6px 2px 0;font-size:11px;color:#94a3b8;line-height:1.4;">
        <i class="fas fa-circle-info" style="font-size:10px;"></i>
        This page's real neighbours — the published page works them out again as it renders.
    </p>
</div>
