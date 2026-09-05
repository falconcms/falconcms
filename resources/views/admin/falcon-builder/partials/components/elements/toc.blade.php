{{-- Table of Contents — live canvas preview.
     Mirrors resources/views/frontend/builder/elements/toc.blade.php. Presets come from
     the fcToc* helpers in partials/scripts.blade.php, which read the same arrays
     TocStyles gives the front end.

     On the page this element lists the real headings around it, which the browser finds
     by scanning the finished document. The canvas cannot do that — the headings there
     are Vue components in a scrolling editor, not a rendered page, and half of them are
     placeholder text an author has not written yet. So the canvas shows the element's
     own headings where it can find them, and a labelled sample where it cannot, which is
     what makes the styling controls usable. The count and the note under it say plainly
     that the real list is built on the page. --}}
<div v-if="el.type === 'toc'"
     class="w-full"
     :style="getCanvasVisibilityStyle(el.settings)">

    <div :style="fcTocOuterStyle(el)">
        <div v-if="fcTocTitle(el)" :style="fcTocHeadStyle(el)">
            <span :style="fcTocTitleStyle(el)">@{{ fcTocTitle(el) }}</span>
            <span :style="fcTocCountStyle(el)">@{{ fcTocItems(el).length }}</span>
            <i v-if="el.settings.collapsible" class="fas fa-chevron-down" :style="fcTocChevStyle(el)"></i>
        </div>

        <div v-if="el.settings.progress" :style="fcTocProgressStyle(el)">
            <span :style="fcTocProgressBarStyle(el)"></span>
        </div>

        {{-- Indentation is inline per item rather than real nested lists. An in-DOM Vue
             template can nest a v-for inside a v-for, but not to arbitrary depth without
             a recursive component — and a table of contents is a flat list of headings
             with a level on each, so the level is simply drawn as an indent. --}}
        <div v-for="(item, i) in fcTocItems(el)" :key="i" :style="fcTocItemStyle(el, item, i)">
            <span v-if="fcTocMarker(el, item, i)" :style="fcTocMarkerStyle(el)">@{{ fcTocMarker(el, item, i) }}</span>
            <span>@{{ item.text }}</span>
        </div>

        <div v-if="el.settings.backToTop" :style="fcTocTopStyle(el)">
            <i class="fas fa-arrow-up" style="font-size:.85em;"></i> Back to top
        </div>
    </div>

    <p style="margin:6px 2px 0;font-size:11px;color:#94a3b8;line-height:1.4;">
        <i class="fas fa-circle-info" style="font-size:10px;"></i>
        <span v-if="fcTocScanned(el)">Preview of this page's headings — the published page rebuilds the list as it renders.</span>
        <span v-else>No headings found on this page yet, so this is a sample. Add Heading elements and it will follow them.</span>
    </p>
</div>
