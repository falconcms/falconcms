{{-- Comments — builder canvas preview.

     The real comments belong to whichever post the layout is shown for, and that is only
     known when the page renders, so the canvas shows two sample comments and a reply drawn
     with the element's own settings. Frontend: frontend/builder/elements/post-comments. --}}
<div v-if="el.type === 'post_comments'"
     class="w-full"
     :style="[
         {
             width: '100%',
             marginTop: getUnitVal(getResponsiveVal(el.settings, 'marginTop', device) !== undefined && getResponsiveVal(el.settings, 'marginTop', device) !== '' ? getResponsiveVal(el.settings, 'marginTop', device) : 0, getResponsiveVal(el.settings, 'marginTopUnit', device) || 'px'),
             marginBottom: getUnitVal(getResponsiveVal(el.settings, 'marginBottom', device) !== undefined && getResponsiveVal(el.settings, 'marginBottom', device) !== '' ? getResponsiveVal(el.settings, 'marginBottom', device) : 0, getResponsiveVal(el.settings, 'marginBottomUnit', device) || 'px'),
             color: el.settings.textColor || '#4b5563',
             '--fcm-accent': el.settings.accentColor || 'var(--primary, #0091ea)',
             '--fcm-radius': (el.settings.radius !== undefined && el.settings.radius !== '' ? el.settings.radius : 8) + 'px',
             '--fcm-border': el.settings.borderColor || '#e5e7eb',
         },
         getCanvasVisibilityStyle(el.settings)
     ]">

    <h2 v-if="el.settings.title !== ''"
        :style="{ margin: '0 0 24px', color: el.settings.titleColor || '#111827', fontSize: (el.settings.cm_title_size ? (isNaN(el.settings.cm_title_size) ? el.settings.cm_title_size : el.settings.cm_title_size + 'px') : '26px'), fontWeight: el.settings.cm_title_weight || 800, fontFamily: el.settings.cm_title_family || 'inherit', lineHeight: 1.25, textTransform: el.settings.cm_title_transform || 'none' }">
        @{{ el.settings.title !== undefined && el.settings.title !== null ? el.settings.title : 'Comments' }}<span v-if="el.settings.showCount !== false"> (3)</span>
    </h2>

    <div :style="{ display: 'flex', flexDirection: 'column', gap: (el.settings.itemGap !== undefined && el.settings.itemGap !== '' ? el.settings.itemGap : 28) + 'px', marginBottom: '40px' }">
        <div v-for="(c, ci) in (el.settings.order === 'newest' ? [{ n: 'Sam Rivera', t: '2 hours ago', b: 'Thanks for writing this up — the section on caching saved me an afternoon.', r: false }, { n: 'Alex Morgan', t: '1 day ago', b: 'Clear and to the point. Would love a follow-up on deployment.', r: true }] : [{ n: 'Alex Morgan', t: '1 day ago', b: 'Clear and to the point. Would love a follow-up on deployment.', r: true }, { n: 'Sam Rivera', t: '2 hours ago', b: 'Thanks for writing this up — the section on caching saved me an afternoon.', r: false }])"
             :key="ci"
             :style="{ display: 'flex', gap: '16px', background: el.settings.cardBg || 'transparent', borderRadius: 'var(--fcm-radius)', padding: el.settings.cardBg ? '18px' : '0', border: el.settings.cardBg ? '1px solid var(--fcm-border)' : 'none' }">
            <div v-if="el.settings.showAvatars !== false"
                 :style="{ flexShrink: 0, width: '44px', height: '44px', borderRadius: '9999px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: '18px', color: 'var(--fcm-accent)', background: 'color-mix(in srgb, var(--fcm-accent) 12%, transparent)' }">@{{ c.n.charAt(0) }}</div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 10px;margin-bottom:6px;">
                    <span :style="{ fontWeight: 700, color: el.settings.nameColor || '#111827' }">@{{ c.n }}</span>
                    <span v-if="el.settings.showDates !== false" :style="{ fontSize: '12px', color: el.settings.metaColor || '#9ca3af' }">@{{ c.t }}</span>
                </div>
                <p :style="{ margin: 0, lineHeight: 1.7, fontSize: (el.settings.cm_body_size ? (isNaN(el.settings.cm_body_size) ? el.settings.cm_body_size : el.settings.cm_body_size + 'px') : 'inherit'), fontFamily: el.settings.cm_body_family || 'inherit' }">@{{ c.b }}</p>
                <div v-if="el.settings.allowReplies !== false && el.settings.showForm !== false" style="margin-top:8px;font-size:12px;font-weight:700;color:var(--fcm-accent);">Reply</div>
                <div v-if="c.r" style="margin-top:18px;padding-left:18px;border-left:2px solid var(--fcm-border);display:flex;gap:12px;">
                    <div v-if="el.settings.showAvatars !== false"
                         :style="{ flexShrink: 0, width: '36px', height: '36px', borderRadius: '9999px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800, fontSize: '15px', color: 'var(--fcm-accent)', background: 'color-mix(in srgb, var(--fcm-accent) 12%, transparent)' }">J</div>
                    <div>
                        <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 10px;margin-bottom:6px;">
                            <span :style="{ fontWeight: 700, color: el.settings.nameColor || '#111827' }">Jordan Lee</span>
                            <span v-if="el.settings.showDates !== false" :style="{ fontSize: '12px', color: el.settings.metaColor || '#9ca3af' }">20 hours ago</span>
                        </div>
                        <p :style="{ margin: 0, lineHeight: 1.7, fontSize: (el.settings.cm_body_size ? (isNaN(el.settings.cm_body_size) ? el.settings.cm_body_size : el.settings.cm_body_size + 'px') : 'inherit') }">Seconded — a deployment guide would be great.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="el.settings.showForm !== false">
        <h3 v-if="el.settings.formTitle !== ''"
            :style="{ margin: '0 0 16px', color: el.settings.titleColor || '#111827', fontSize: '20px', fontWeight: 700, fontFamily: el.settings.cm_title_family || 'inherit' }">
            @{{ el.settings.formTitle !== undefined && el.settings.formTitle !== null ? el.settings.formTitle : 'Leave a Reply' }}
        </h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div v-for="f in ['Name *', 'Email *']" :key="f">
                <div :style="{ fontSize: '12px', fontWeight: 700, marginBottom: '6px', color: el.settings.nameColor || '#111827' }">@{{ f }}</div>
                <div :style="{ height: '40px', border: '1px solid var(--fcm-border)', borderRadius: 'var(--fcm-radius)', background: el.settings.inputBg || '#ffffff' }"></div>
            </div>
        </div>
        <div :style="{ fontSize: '12px', fontWeight: 700, marginBottom: '6px', color: el.settings.nameColor || '#111827' }">Comment *</div>
        <div :style="{ height: '120px', border: '1px solid var(--fcm-border)', borderRadius: 'var(--fcm-radius)', background: el.settings.inputBg || '#ffffff', marginBottom: '14px' }"></div>
        <span :style="{ display: 'inline-block', padding: '11px 24px', borderRadius: 'var(--fcm-radius)', fontSize: '14px', fontWeight: 700, color: el.settings.buttonTextColor || '#ffffff', background: 'var(--fcm-accent)' }">
            @{{ el.settings.buttonText || 'Post Comment' }}
        </span>
    </div>
</div>
