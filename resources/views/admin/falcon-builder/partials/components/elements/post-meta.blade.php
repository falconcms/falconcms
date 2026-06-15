<div v-if="el.type === 'post_meta'"
     class="w-full post-meta-canvas-wrap"
     :style="[
         {
             width:          '100%',
             display:        'flex',
             flexWrap:       'wrap',
             alignItems:     (el.settings.layout || 'inline') === 'stacked' ? 'flex-start' : 'center',
             flexDirection:  (el.settings.layout || 'inline') === 'stacked' ? 'column' : 'row',
             gap:            (el.settings.layout || 'inline') === 'stacked' ? '4px' : getUnitVal(el.settings.gap ?? 12, el.settings.gapUnit || 'px'),
             justifyContent: { left: 'flex-start', center: 'center', right: 'flex-end' }[el.settings.metaAlign || 'left'] || 'flex-start',
             fontFamily:     el.settings.meta_family     || 'inherit',
             fontSize:       el.settings.meta_size       || getUnitVal(el.settings.fontSize || 13, el.settings.fontSizeUnit || 'px'),
             fontWeight:     el.settings.meta_weight     || el.settings.fontWeight || '400',
             lineHeight:     el.settings.meta_line_height    || 'inherit',
             letterSpacing:  el.settings.meta_letter_spacing || 'normal',
             textTransform:  el.settings.meta_transform  || 'none',
             color:          el.settings.color    || '#6b7280',
             '--pm-tc':      el.settings.color    || '#6b7280',
             '--pm-lc':      el.settings.linkColor || '#374151',
             marginTop:      getUnitVal(getResponsiveVal(el.settings, 'marginTop', device) ?? 0, getResponsiveVal(el.settings, 'marginTopUnit', device) || 'px'),
             marginBottom:   getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) ?? 8, getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
         },
         getCanvasVisibilityStyle(el.settings)
     ]">

    <template v-for="(key, idx) in (el.settings.metaOrder || ['categories','tags','author','date','reading_time'])" :key="key">

        <!-- separator before item (inline mode, not first visible item) -->
        <template v-if="(el.settings.layout || 'inline') !== 'stacked' && idx > 0 && (el.settings.separator ?? '·') !== ''">
            <span v-if="(key==='categories' && el.settings.showCategories!==false) || (key==='tags' && el.settings.showTags) || (key==='author' && el.settings.showAuthor!==false) || (key==='date' && el.settings.showDate!==false) || (key==='reading_time' && el.settings.showReadingTime)"
                  style="opacity:0.5;font-size:0.85em;line-height:1;">@{{ el.settings.separator ?? '·' }}</span>
        </template>

        <!-- Categories -->
        <span v-if="key==='categories' && el.settings.showCategories!==false"
              class="pm-g" style="display:inline-flex;align-items:center;gap:4px;line-height:1.4;flex-wrap:wrap;">
            <i v-if="el.settings.showIcons!==false" class="fa fa-folder-open" style="font-size:0.85em;opacity:0.7;"></i>
            <template v-for="n in Math.min(el.settings.limitCategories > 0 ? el.settings.limitCategories : 3, 5)" :key="'cat'+n">
                <span v-if="n > 1" style="opacity:0.4;">,&nbsp;</span>
                <span class="pm-canvas-link">Cat @{{ n }}</span>
            </template>
            <span v-if="el.settings.limitCategories > 0" style="opacity:0.35;font-size:0.8em;">&nbsp;(@{{ el.settings.limitCategories }} max)</span>
        </span>

        <!-- Tags -->
        <span v-if="key==='tags' && el.settings.showTags"
              class="pm-g" style="display:inline-flex;align-items:center;gap:4px;line-height:1.4;flex-wrap:wrap;">
            <i v-if="el.settings.showIcons!==false" class="fa fa-tags" style="font-size:0.85em;opacity:0.7;"></i>
            <template v-for="n in Math.min(el.settings.limitTags > 0 ? el.settings.limitTags : 3, 5)" :key="'tag'+n">
                <span v-if="n > 1" style="opacity:0.4;">,&nbsp;</span>
                <span class="pm-canvas-link">Tag @{{ n }}</span>
            </template>
            <span v-if="el.settings.limitTags > 0" style="opacity:0.35;font-size:0.8em;">&nbsp;(@{{ el.settings.limitTags }} max)</span>
        </span>

        <!-- Author -->
        <span v-if="key==='author' && el.settings.showAuthor!==false"
              class="pm-g" style="display:inline-flex;align-items:center;gap:4px;line-height:1.4;">
            <i v-if="el.settings.showIcons!==false" class="fa fa-user" style="font-size:0.85em;opacity:0.7;"></i>
            <span class="pm-canvas-link">John Doe</span>
        </span>

        <!-- Date -->
        <span v-if="key==='date' && el.settings.showDate!==false"
              style="display:inline-flex;align-items:center;gap:4px;line-height:1.4;">
            <i v-if="el.settings.showIcons!==false" class="fa fa-calendar" style="font-size:0.85em;opacity:0.7;"></i>
            <span>Jun 9, 2026</span>
        </span>

        <!-- Reading Time -->
        <span v-if="key==='reading_time' && el.settings.showReadingTime"
              style="display:inline-flex;align-items:center;gap:4px;line-height:1.4;">
            <i v-if="el.settings.showIcons!==false" class="fa fa-clock" style="font-size:0.85em;opacity:0.7;"></i>
            <span>5 min read</span>
        </span>

    </template>

    <!-- Type label -->
    <div class="w-full flex items-center gap-1 mt-0.5"
         style="font-size:10px;font-weight:700;color:#94a3b8;">
        <i class="fa fa-tags opacity-50"></i>
        <span>Post Meta</span>
    </div>

</div>
