{{-- CodeMirror — HTML Block IDE editor (local assets) --}}
<link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/codemirror/codemirror.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/codemirror/dracula.min.css') }}">
<script src="{{ asset('vendor/falcon-cms/js/codemirror/codemirror.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/xml.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/javascript.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/css.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/htmlmixed.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/closetag.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/closebrackets.min.js') }}"></script>
<script src="{{ asset('vendor/falcon-cms/js/codemirror/matchbrackets.min.js') }}"></script>
<style>
    .CodeMirror { height: 280px !important; font-size: 12px; font-family: 'Fira Code', 'Courier New', monospace; }
    .CodeMirror-scroll { min-height: 260px; }
    .lazy-dyn-btn { flex-shrink:0; width:22px; height:22px; display:flex; align-items:center; justify-content:center; color:#cbd5e1; border-radius:4px; transition:all .15s; cursor:pointer; background:transparent; border:0; padding:0; }
    .lazy-dyn-btn:hover { color:#0091ea; background:#f0f9ff; }
    .dyn-token-btn { padding:3px 7px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; font-weight:500; color:#475569; background:#f8fafc; cursor:pointer; transition:all .15s; text-align:left; white-space:nowrap; }
    .dyn-token-btn:hover { background:#eff6ff; border-color:#0091ea; color:#0091ea; }
</style>

<script>
    const { createApp, ref, reactive, computed, onMounted, nextTick, watch, watchEffect } = Vue;

    createApp({
        setup() {
            const layout = ref([]);
            const postCardMode = ref(window.falconPostCardMode || false);
            // Layout Builder sections (header/footer/page-title-bar/content) can also use
            // the dynamic Post elements (Content, Post Meta, Product Meta).
            const layoutMode = ref(window.falconLayoutMode || false);
            const isPreview = ref(false);
            const isSaving = ref(false);
            const isDirty = ref(false);
            let lastSavedLayout = '';

            // ── Undo / Redo ────────────────────────────────────────────────
            const _historyStack = [];
            const historyIndex = ref(-1);
            const canUndo = computed(() => historyIndex.value > 0);
            const canRedo = computed(() => historyIndex.value < _historyStack.length - 1);
            let _isUndoRedo = false;
            const MAX_HISTORY = 50;
            const serializedLayout = computed(() => {
                // Return layout without transient UI state like isHovered or properties starting with _
                return JSON.stringify(layout.value, (key, value) => {
                    if (key === 'isHovered' || key.startsWith('_')) return undefined;
                    return value;
                });
            });
            let _trackLayoutDirty = false;
            const activeTab = ref('navigator'); // Default to Navigator like in screenshot
            const device = ref('desktop');
            const activeResponsiveMenu = ref(null);
            const activePickr = ref(null);
            // ── Dynamic Value Picker (inline token {lazy:X} system) ────────────
            const dynMenu = reactive({ open: false, x: 0, y: 0, settings: null, key: '' });
            const dynAcptSlug = ref('');
            const openDynMenu = (settings, key, event) => {
                dynSrcMenu.open = false;
                const rect = event.currentTarget.getBoundingClientRect();
                dynMenu.settings = settings;
                dynMenu.key = key;
                dynAcptSlug.value = '';
                dynMenu.x = Math.max(8, rect.right - 280);
                dynMenu.y = rect.bottom + 4;
                dynMenu.open = true;
            };
            const insertDynToken = (token) => {
                if (!dynMenu.settings || !dynMenu.key) return;
                const cur = String(dynMenu.settings[dynMenu.key] ?? '');
                dynMenu.settings[dynMenu.key] = cur + '{lazy:' + token + '}';
                dynMenu.open = false;
            };
            const insertDynAcpt = () => {
                const slug = dynAcptSlug.value.trim();
                if (slug) insertDynToken('acpt_' + slug);
            };
            // ── Dynamic Source Picker (database-icon / blue-pill system) ────────
            const dynSrcMenu = reactive({ open: false, x: 0, y: 0, settings: null, sourceKey: '', ctx: 'text', showConfig: false, configKey: '' });
            const dynSrcDefs = {
                text: [
                    { key: 'post_title',        label: 'Post Title',      icon: 'fa-heading',       group: 'Post',   subFields: [] },
                    { key: 'post_excerpt',       label: 'Post Excerpt',    icon: 'fa-align-left',    group: 'Post',   subFields: [
                        { key: 'dynamic_excerpt_length', label: 'Length (chars)', type: 'number', placeholder: '150' },
                        { key: 'dynamic_fallback',       label: 'Fallback',       type: 'text',   placeholder: 'No excerpt available...' },
                    ]},
                    { key: 'post_date',          label: 'Post Date',       icon: 'fa-calendar',      group: 'Post',   subFields: [
                        { key: 'dynamic_date_type',   label: 'Date Type', type: 'select', options: [
                            { value: 'published', label: 'Post Published' },
                            { value: 'modified',  label: 'Post Modified' },
                        ]},
                        { key: 'dynamic_date_format', label: 'Format',    type: 'text', placeholder: 'F j, Y' },
                        { key: 'dynamic_before',      label: 'Before',    type: 'text', placeholder: 'Text before value' },
                        { key: 'dynamic_after',       label: 'After',     type: 'text', placeholder: 'Text after value' },
                        { key: 'dynamic_fallback',    label: 'Fallback',  type: 'text', placeholder: 'Fallback value' },
                    ]},
                    { key: 'post_reading_time',  label: 'Reading Time',    icon: 'fa-clock',         group: 'Post',   subFields: [] },
                    { key: 'post_id',            label: 'Post ID',         icon: 'fa-hashtag',       group: 'Post',   subFields: [] },
                    { key: 'post_type',          label: 'Post Type',       icon: 'fa-tag',           group: 'Post',   subFields: [] },
                    { key: 'product_price',          label: 'Product Price',   icon: 'fa-dollar-sign',   group: 'Product', subFields: [
                        { key: 'dynamic_before',   label: 'Before',   type: 'text', placeholder: 'Text before value' },
                        { key: 'dynamic_after',    label: 'After',    type: 'text', placeholder: 'Text after value' },
                        { key: 'dynamic_fallback', label: 'Fallback', type: 'text', placeholder: 'Fallback value' },
                    ]},
                    { key: 'product_regular_price',  label: 'Regular Price',   icon: 'fa-tag',           group: 'Product', subFields: [
                        { key: 'dynamic_before',   label: 'Before',   type: 'text', placeholder: 'Text before value' },
                        { key: 'dynamic_after',    label: 'After',    type: 'text', placeholder: 'Text after value' },
                        { key: 'dynamic_fallback', label: 'Fallback', type: 'text', placeholder: 'Fallback value' },
                    ]},
                    { key: 'product_sale_price',     label: 'Sale Price',      icon: 'fa-percent',       group: 'Product', subFields: [
                        { key: 'dynamic_before',   label: 'Before',   type: 'text', placeholder: 'Text before value' },
                        { key: 'dynamic_after',    label: 'After',    type: 'text', placeholder: 'Text after value' },
                        { key: 'dynamic_fallback', label: 'Fallback', type: 'text', placeholder: 'Not on sale' },
                    ]},
                    { key: 'product_sku',            label: 'Product SKU',     icon: 'fa-barcode',       group: 'Product', subFields: [
                        { key: 'dynamic_before',   label: 'Before',   type: 'text', placeholder: 'e.g. SKU: ' },
                        { key: 'dynamic_fallback', label: 'Fallback', type: 'text', placeholder: 'Fallback value' },
                    ]},
                    { key: 'product_stock_status',   label: 'Stock Status',    icon: 'fa-circle-check',  group: 'Product', subFields: [
                        { key: 'dynamic_fallback', label: 'Fallback', type: 'text', placeholder: 'Fallback value' },
                    ]},
                    { key: 'product_stock_quantity', label: 'Stock Quantity',  icon: 'fa-cubes',         group: 'Product', subFields: [
                        { key: 'dynamic_before',   label: 'Before',   type: 'text', placeholder: 'Text before value' },
                        { key: 'dynamic_after',    label: 'After',    type: 'text', placeholder: 'e.g. in stock' },
                        { key: 'dynamic_fallback', label: 'Fallback', type: 'text', placeholder: 'Fallback value' },
                    ]},
                    { key: 'post_author',        label: 'Author Name',     icon: 'fa-user',          group: 'Author', subFields: [] },
                    { key: 'author_bio',         label: 'Author Bio',      icon: 'fa-user-edit',     group: 'Author', subFields: [] },
                    { key: 'site_name',          label: 'Site Title',      icon: 'fa-globe',         group: 'Site',   subFields: [] },
                    { key: 'site_tagline',       label: 'Site Tagline',    icon: 'fa-quote-left',    group: 'Site',   subFields: [] },
                    { key: 'current_date',       label: 'Current Date',    icon: 'fa-calendar-day',  group: 'Other',  subFields: [
                        { key: 'dynamic_date_format', label: 'Format', type: 'text', placeholder: 'F j, Y' },
                        { key: 'dynamic_before',      label: 'Before', type: 'text', placeholder: '' },
                        { key: 'dynamic_after',       label: 'After',  type: 'text', placeholder: '' },
                    ]},
                    { key: 'current_year',       label: 'Current Year',    icon: 'fa-calendar-alt',  group: 'Other',  subFields: [] },
                    { key: 'user_name',          label: 'Logged-in User',  icon: 'fa-user-circle',   group: 'Other',  subFields: [] },
                    { key: 'acpt_custom',        label: 'Custom Field',    icon: 'fa-database',      group: 'Custom', subFields: [
                        { key: 'dynamic_acpt_slug', label: 'Field Slug', type: 'text', placeholder: 'e.g. my_field_slug', required: true },
                        { key: 'dynamic_fallback',  label: 'Fallback',   type: 'text', placeholder: '' },
                    ]},
                ],
                link: [
                    { key: 'post_url',   label: 'Post URL',   icon: 'fa-link',  group: 'Post',   subFields: [] },
                    { key: 'author_url', label: 'Author URL', icon: 'fa-user',  group: 'Author', subFields: [] },
                    { key: 'site_url',   label: 'Site URL',   icon: 'fa-globe', group: 'Site',   subFields: [] },
                ],
                image: [
                    { key: 'feature_image', label: 'Feature Image', icon: 'fa-image',       group: 'Post',   subFields: [] },
                    { key: 'author_avatar', label: 'Author Avatar', icon: 'fa-user-circle', group: 'Author', subFields: [] },
                    { key: 'logo',          label: 'Site Logo',     icon: 'fa-star',         group: 'Site',   subFields: [] },
                ],
            };
            // Live preview value per dynamic source. Prefer the REAL resolved value for the
            // post being edited (window.builderDynPreview, computed server-side); fall back to a
            // representative sample when the post has no value for that source (e.g. a product
            // field on a non-product page). Real values resolve on the frontend from $post.
            const dynSrcGenericSample = (key) => ({
                post_title: 'Sample Post Title',
                post_excerpt: 'A short sample excerpt of the post content shown here…',
                post_date: 'Jan 1, 2026', post_reading_time: '3 min read', post_id: '123', post_type: 'post',
                post_author: 'Author Name', author_bio: 'Short author biography text…',
                site_name: 'Site Title', site_tagline: 'Just another tagline', site_url: 'example.com',
                current_date: 'Jan 1, 2026', current_year: String(new Date().getFullYear()), user_name: 'John Doe',
                acpt_custom: 'Custom Field', post_url: 'example.com/post', author_url: 'example.com/author',
                product_price: '$49.00', product_regular_price: '$59.00', product_sale_price: '$49.00',
                product_sku: 'SAMPLE-SKU-001', product_stock_status: 'In stock', product_stock_quantity: '25',
            }[key] || String(key).replace(/_/g, ' '));
            const dynSrcSample = (key) => {
                const real = (window.builderDynPreview && window.builderDynPreview[key]) || '';
                return real !== '' ? real : dynSrcGenericSample(key);
            };
            // Returns the canvas preview string for a field's dynamic source, or null if not dynamic.
            const dynSrcPreview = (s) => {
                if (!s || !s.dynamic_source) return null;
                return (s.dynamic_before || '') + dynSrcSample(s.dynamic_source) + (s.dynamic_after || '');
            };
            const getDynSrcDef = (key) => {
                const all = [...dynSrcDefs.text, ...dynSrcDefs.link, ...dynSrcDefs.image];
                return all.find(d => d.key === key) || { key, label: key, icon: 'fa-bolt', subFields: [] };
            };
            // Resolve a dynamic background-image source (Feature Image / Author Avatar / Site Logo)
            // to a real preview URL for the canvas. Empty when the current post has no value.
            const dynBgUrl = (s) => {
                const src = s && s.bgImageDynamicSource;
                if (!src) return '';
                return (window.builderDynPreview && window.builderDynPreview[src]) || '';
            };
            // Shown in-canvas when a dynamic bg source is set but resolves to no URL (e.g. a page
            // with no featured image) — signals the dynamic bg is active without a broken image.
            const DYN_BG_PLACEHOLDER = 'linear-gradient(135deg, rgba(34,113,177,0.14), rgba(0,145,234,0.06))';
            const getDynSrcGroups = (ctx) => {
                const opts = dynSrcDefs[ctx] || dynSrcDefs.text;
                const map = {};
                opts.forEach(opt => { const g = opt.group || 'Other'; if (!map[g]) map[g] = []; map[g].push(opt); });
                return Object.entries(map).map(([name, items]) => ({ name, items }));
            };
            const _applySubFieldDefaults = (key) => {
                const def = getDynSrcDef(key);
                (def.subFields || []).forEach(field => {
                    if (field.type === 'select' && field.options && field.options.length && !dynSrcMenu.settings[field.key]) {
                        dynSrcMenu.settings[field.key] = field.options[0].value;
                    }
                });
            };
            const openDynSrcMenu = (settings, sourceKey, ctx, event) => {
                // Dynamic content is a Pro (builder_pro) feature. Existing dynamic bindings keep
                // rendering; only adding/changing one is gated so unlicensed sites aren't broken.
                if (!window.falconBuilderPro) {
                    showToast('Dynamic content is a Pro feature — upgrade to unlock dynamic sources.', 'error');
                    return;
                }
                dynMenu.open = false;
                const rect = event.currentTarget.getBoundingClientRect();
                dynSrcMenu.settings = settings;
                dynSrcMenu.sourceKey = sourceKey;
                dynSrcMenu.ctx = ctx || 'text';
                dynSrcMenu.x = Math.max(8, rect.right - 278);
                dynSrcMenu.y = rect.bottom + 4;
                const currentKey = settings[sourceKey];
                const def = currentKey ? getDynSrcDef(currentKey) : null;
                if (def && def.subFields && def.subFields.length) {
                    dynSrcMenu.configKey = currentKey;
                    dynSrcMenu.showConfig = true;
                    _applySubFieldDefaults(currentKey);
                } else {
                    dynSrcMenu.showConfig = false;
                    dynSrcMenu.configKey = '';
                }
                dynSrcMenu.open = true;
            };
            const selectDynSource = (key) => {
                if (!dynSrcMenu.settings) return;
                const def = getDynSrcDef(key);
                dynSrcMenu.settings[dynSrcMenu.sourceKey] = key;
                if (def.subFields && def.subFields.length) {
                    _applySubFieldDefaults(key);
                    dynSrcMenu.configKey = key;
                    dynSrcMenu.showConfig = true;
                } else {
                    dynSrcMenu.open = false;
                }
            };
            const clearDynSource = () => {
                if (!dynSrcMenu.settings) return;
                dynSrcMenu.settings[dynSrcMenu.sourceKey] = '';
                dynSrcMenu.open = false;
            };
            // ── End Dynamic Pickers ─────────────────────────────────────────────
            let _pickerCtx = null; // { obj, colorKey, opacityKey, origColor, origOpacity }

            const _closeActivePickr = (revert) => {
                if (!activePickr.value) return;
                if (revert && _pickerCtx) {
                    const ctx = _pickerCtx;
                    ctx.obj[ctx.colorKey] = ctx.origColor !== null ? ctx.origColor : '';
                    if (ctx.opacityKey) ctx.obj[ctx.opacityKey] = ctx.origOpacity !== null ? ctx.origOpacity : 1;
                }
                try { activePickr.value.hide(); } catch(e) {}
                try { activePickr.value.destroy(); } catch(e) {}
                activePickr.value = null;
                _pickerCtx = null;
            };

            watch(device, () => {
                // Commit (keep) the picked colour when switching device — reverting here wiped
                // per-device responsive colours (e.g. a tablet bg colour) the user just set.
                _closeActivePickr(false);
                // Re-fetch card previews so the carousel shows the correct per-device "items per slide".
                const walkCards = (items) => (items || []).forEach(it => {
                    if (it.type === 'card' && it.settings && it.settings.post_card_id) fetchCardPreview(it);
                    if (it.columns) it.columns.forEach(c => walkCards(c.elements));
                    if (it.elements) walkCards(it.elements);
                });
                walkCards(layout.value);
            });

            const getResponsiveVal = (settings, prop, deviceMode) => {
                if (!settings) return undefined;
                if (deviceMode === 'mobile') {
                    if (settings[prop + '_mobile'] !== undefined && settings[prop + '_mobile'] !== '') return settings[prop + '_mobile'];
                    if (settings[prop + '_tablet'] !== undefined && settings[prop + '_tablet'] !== '') return settings[prop + '_tablet'];
                    return settings[prop];
                }
                if (deviceMode === 'tablet') {
                    if (settings[prop + '_tablet'] !== undefined && settings[prop + '_tablet'] !== '') return settings[prop + '_tablet'];
                    return settings[prop];
                }
                return settings[prop];
            };

            // Card carousel "items per slide" for the CURRENT device (live canvas preview).
            // Mirrors the front-end: tablet auto = min(desktop, 2), mobile auto = 1.
            const cardPerView = (s) => {
                const d = parseInt(s.items_per_slide) || 1;
                if (device.value === 'tablet') { const t = parseInt(s.items_per_slide_tablet) || 0; return t > 0 ? t : Math.min(d, 2); }
                if (device.value === 'mobile') { const m = parseInt(s.items_per_slide_mobile) || 0; return m > 0 ? m : 1; }
                return d;
            };
            // How many grid columns the canvas card preview should show for the current device.
            const cardPreviewCols = (s) => {
                if (!s) return 3;
                if (s.layout === 'list') return 1;
                if (s.layout === 'carousel') return cardPerView(s);
                if (device.value === 'tablet') return parseInt(s.columns_tablet) || parseInt(s.columns) || 3;
                if (device.value === 'mobile') return parseInt(s.columns_mobile) || 1;
                return parseInt(s.columns) || 3;
            };
            // How many card placeholders to render in the canvas preview.
            const cardPreviewCount = (s) => {
                if (!s) return 6;
                if (s.layout === 'carousel') return cardPerView(s);
                return parseInt(s.posts_count) || 6;
            };

            const setResponsiveVal = (settings, prop, deviceMode, val) => {
                if (!settings) return;
                if (deviceMode === 'desktop') {
                    settings[prop] = val;
                } else {
                    settings[prop + '_' + deviceMode] = val;
                }
            };

            const resetResponsiveVal = (settings, prop, deviceMode, defaultVal = '') => {
                if (!settings) return;
                if (deviceMode === 'desktop') {
                    settings[prop] = defaultVal;
                } else {
                    settings[prop + '_' + deviceMode] = '';
                }
            };

            const activeCi = ref(null);
            const editingCi = ref(null);
            const activeColi = ref(null);
            const activeColCi = ref(null);
            const editingContext = ref({ type: null, ci: null, coli: null, eli: null, ncoli: null, neli: null, tab: 'content' });
            const activePanelTab = ref('general');
            const activeColPanelTab = ref('general');
            const siteWidth = ref("{{ get_cms_option('theme_site_width', '1200px') }}");
            if (!isNaN(siteWidth.value)) siteWidth.value += 'px';

            const isDragging = ref(false);
            const dragType = ref(null);
            const dragCi = ref(null);
            const startY = ref(0);
            const startX = ref(0);
            const startVal = ref(0);
            
            const toasts = ref([]);
            const showToast = (message, type = 'success') => {
                const id = Date.now();
                toasts.value.push({ id, message, type });
                setTimeout(() => {
                    toasts.value = toasts.value.filter(t => t.id !== id);
                }, 3000);
            };

            const hoveredType = ref(null); // 'container', 'column', 'element', 'nested-row', 'nested-column'
            const hoveredCi = ref(null);
            const hoveredColi = ref(null);
            const hoveredEli = ref(null);
            const hoveredNcoli = ref(null);

            const setHover = (type, ci = null, coli = null, eli = null, ncoli = null) => {
                hoveredType.value = type;
                hoveredCi.value = ci;
                hoveredColi.value = coli;
                hoveredEli.value = eli;
                hoveredNcoli.value = ncoli;
            };

            const showColumnModal = ref(false);
            const columnModalTarget = ref(null);

            const searchColumnQuery = ref('');
            const searchElementQuery = ref('');

            const columnLayouts = [
                // Row 1
                { id: '1', label: '1/1', config: '1/1' },
                { id: '2', label: '1/2 - 1/2', config: '1/2-1/2' },
                { id: '3', label: '1/3 - 1/3 - 1/3', config: '1/3-1/3-1/3' },
                { id: '4', label: '1/4 - 1/4 - 1/4 - 1/4', config: '1/4-1/4-1/4-1/4' },
                { id: '5', label: '2/3 - 1/3', config: '2/3-1/3' },
                { id: '6', label: '1/3 - 2/3', config: '1/3-2/3' },
                { id: '7', label: '1/4 - 3/4', config: '1/4-3/4' },
                // Row 2
                { id: '8', label: '3/4 - 1/4', config: '3/4-1/4' },
                { id: '9', label: '1/2 - 1/4 - 1/4', config: '1/2-1/4-1/4' },
                { id: '10', label: '1/4 - 1/4 - 1/2', config: '1/4-1/4-1/2' },
                { id: '11', label: '1/4 - 1/2 - 1/4', config: '1/4-1/2-1/4' },
                { id: '12', label: '1/5 - 4/5', config: '1/5-4/5' },
                { id: '13', label: '4/5 - 1/5', config: '4/5-1/5' },
                { id: '14', label: '3/5 - 2/5', config: '3/5-2/5' },
                // Row 3
                { id: '15', label: '2/5 - 3/5', config: '2/5-3/5' },
                { id: '16', label: '1/5 - 1/5 - 3/5', config: '1/5-1/5-3/5' },
                { id: '17', label: '1/5 - 3/5 - 1/5', config: '1/5-3/5-1/5' },
                { id: '18', label: '1/2 - 1/6 - 1/6 - 1/6', config: '1/2-1/6-1/6-1/6' },
                { id: '19', label: '1/6 - 1/6 - 1/6 - 1/2', config: '1/6-1/6-1/6-1/2' },
                { id: '20', label: '1/6 - 2/3 - 1/6', config: '1/6-2/3-1/6' },
                { id: '21', label: '1/5 - 1/5 - 1/5 - 1/5 - 1/5', config: '1/5-1/5-1/5-1/5-1/5' },
                // Row 4
                { id: '22', label: '1/6 - 1/6 - 1/6 - 1/6 - 1/6 - 1/6', config: '1/6-1/6-1/6-1/6-1/6-1/6' },
                { id: '23', label: '5/6', config: '5/6' },
                { id: '24', label: '4/5', config: '4/5' },
                { id: '25', label: '3/4', config: '3/4' },
                { id: '26', label: '2/3', config: '2/3' },
                { id: '27', label: '3/5', config: '3/5' },
                { id: '28', label: '1/2', config: '1/2' },
                // Row 5
                { id: '29', label: '2/5', config: '2/5' },
                { id: '30', label: '1/3', config: '1/3' },
                { id: '31', label: '1/4', config: '1/4' },
                { id: '32', label: '1/5', config: '1/5' },
                { id: '33', label: '1/6', config: '1/6' },
            ];

            // Customizer theme fonts (passed from controller)
            const themeBodyFont    = @json($themeBodyFont ?? null);
            const themeHeadingFont = @json($themeHeadingFont ?? null);

            // Google Fonts database (mirrors customizer list)
            const BUILDER_FONTS = [
                { family: 'Inter', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Roboto', category: 'Sans-serif', variants: ['100','300','400','500','700','900'] },
                { family: 'Open Sans', category: 'Sans-serif', variants: ['300','400','500','600','700','800'] },
                { family: 'Lato', category: 'Sans-serif', variants: ['100','300','400','700','900'] },
                { family: 'Poppins', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Nunito', category: 'Sans-serif', variants: ['200','300','400','500','600','700','800','900'] },
                { family: 'Montserrat', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Raleway', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Ubuntu', category: 'Sans-serif', variants: ['300','400','500','700'] },
                { family: 'Oswald', category: 'Sans-serif', variants: ['200','300','400','500','600','700'] },
                { family: 'Quicksand', category: 'Sans-serif', variants: ['300','400','500','600','700'] },
                { family: 'Work Sans', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Noto Sans', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Rubik', category: 'Sans-serif', variants: ['300','400','500','600','700','800','900'] },
                { family: 'DM Sans', category: 'Sans-serif', variants: ['400','500','700'] },
                { family: 'Cairo', category: 'Sans-serif', variants: ['200','300','400','500','600','700','800','900'] },
                { family: 'Josefin Sans', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700'] },
                { family: 'Public Sans', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Fira Sans', category: 'Sans-serif', variants: ['100','200','300','400','500','600','700','800','900'] },
                { family: 'Playfair Display', category: 'Serif', variants: ['400','500','600','700','800','900'] },
                { family: 'Merriweather', category: 'Serif', variants: ['300','400','700','900'] },
                { family: 'Lora', category: 'Serif', variants: ['400','500','600','700'] },
                { family: 'PT Serif', category: 'Serif', variants: ['400','700'] },
                { family: 'Libre Baskerville', category: 'Serif', variants: ['400','700'] },
                { family: 'Crimson Text', category: 'Serif', variants: ['400','600','700'] },
                { family: 'EB Garamond', category: 'Serif', variants: ['400','500','600','700','800'] },
                { family: 'Fira Code', category: 'Monospace', variants: ['300','400','500','600','700'] },
                { family: 'Source Code Pro', category: 'Monospace', variants: ['200','300','400','500','600','700','800','900'] },
                { family: 'Roboto Mono', category: 'Monospace', variants: ['100','200','300','400','500','600','700'] },
                { family: 'Lobster', category: 'Display', variants: ['400'] },
                { family: 'Pacifico', category: 'Display', variants: ['400'] },
                { family: 'Dancing Script', category: 'Display', variants: ['400','500','600','700'] },
                { family: 'Bebas Neue', category: 'Display', variants: ['400'] },
                { family: 'Comfortaa', category: 'Display', variants: ['300','400','500','600','700'] },
            ];

            const builderFontGroups = BUILDER_FONTS.reduce((acc, font) => {
                if (!acc[font.category]) acc[font.category] = [];
                acc[font.category].push(font);
                return acc;
            }, {});

            const activeIconTab = ref('Solid');
            const searchIconQuery = ref('');
            const activeAccordionItem = ref(null);
            const activeTabsItem = ref(null);
            const activeIconListItem = ref(null);
            const FA_GROUPS = {
                'Solid': ['fas fa-0', 'fas fa-1', 'fas fa-2', 'fas fa-3', 'fas fa-4', 'fas fa-5', 'fas fa-6', 'fas fa-7', 'fas fa-8', 'fas fa-9', 'fas fa-a', 'fas fa-address-book', 'fas fa-address-card', 'fas fa-align-center', 'fas fa-align-justify', 'fas fa-align-left', 'fas fa-align-right', 'fas fa-anchor', 'fas fa-anchor-circle-check', 'fas fa-anchor-circle-exclamation', 'fas fa-anchor-circle-xmark', 'fas fa-anchor-lock', 'fas fa-angle-down', 'fas fa-angle-left', 'fas fa-angle-right', 'fas fa-angle-up', 'fas fa-angles-down', 'fas fa-angles-left', 'fas fa-angles-right', 'fas fa-angles-up', 'fas fa-ankh', 'fas fa-apple-whole', 'fas fa-archway', 'fas fa-arrow-down', 'fas fa-arrow-down-1-9', 'fas fa-arrow-down-9-1', 'fas fa-arrow-down-a-z', 'fas fa-arrow-down-long', 'fas fa-arrow-down-short-wide', 'fas fa-arrow-down-up-across-line', 'fas fa-arrow-down-up-lock', 'fas fa-arrow-down-wide-short', 'fas fa-arrow-down-z-a', 'fas fa-arrow-left', 'fas fa-arrow-left-long', 'fas fa-arrow-pointer', 'fas fa-arrow-right', 'fas fa-arrow-right-arrow-left', 'fas fa-arrow-right-from-bracket', 'fas fa-arrow-right-long', 'fas fa-arrow-right-to-bracket', 'fas fa-arrow-right-to-city', 'fas fa-arrow-rotate-left', 'fas fa-arrow-rotate-right', 'fas fa-arrow-trend-down', 'fas fa-arrow-trend-up', 'fas fa-arrow-turn-down', 'fas fa-arrow-turn-up', 'fas fa-arrow-up', 'fas fa-arrow-up-1-9', 'fas fa-arrow-up-9-1', 'fas fa-arrow-up-a-z', 'fas fa-arrow-up-from-bracket', 'fas fa-arrow-up-from-ground-water', 'fas fa-arrow-up-from-water-pump', 'fas fa-arrow-up-long', 'fas fa-arrow-up-right-dots', 'fas fa-arrow-up-right-from-square', 'fas fa-arrow-up-short-wide', 'fas fa-arrow-up-wide-short', 'fas fa-arrow-up-z-a', 'fas fa-arrows-down-to-line', 'fas fa-arrows-down-to-people', 'fas fa-arrows-left-right', 'fas fa-arrows-left-right-to-line', 'fas fa-arrows-rotate', 'fas fa-arrows-spin', 'fas fa-arrows-split-up-and-left', 'fas fa-arrows-to-circle', 'fas fa-arrows-to-dot', 'fas fa-arrows-to-eye', 'fas fa-arrows-turn-right', 'fas fa-arrows-turn-to-dots', 'fas fa-arrows-up-down', 'fas fa-arrows-up-down-left-right', 'fas fa-arrows-up-to-line', 'fas fa-asterisk', 'fas fa-at', 'fas fa-atom', 'fas fa-audio-description', 'fas fa-austral-sign', 'fas fa-award', 'fas fa-b', 'fas fa-baby', 'fas fa-baby-carriage', 'fas fa-backward', 'fas fa-backward-fast', 'fas fa-backward-step', 'fas fa-bacon', 'fas fa-bacteria', 'fas fa-bacterium', 'fas fa-bag-shopping', 'fas fa-bahai', 'fas fa-baht-sign', 'fas fa-ban', 'fas fa-ban-smoking', 'fas fa-bandage', 'fas fa-bangladeshi-taka-sign', 'fas fa-barcode', 'fas fa-bars', 'fas fa-bars-progress', 'fas fa-bars-staggered', 'fas fa-baseball', 'fas fa-baseball-bat-ball', 'fas fa-basket-shopping', 'fas fa-basketball', 'fas fa-bath', 'fas fa-battery-empty', 'fas fa-battery-full', 'fas fa-battery-half', 'fas fa-battery-quarter', 'fas fa-battery-three-quarters', 'fas fa-bed', 'fas fa-bed-pulse', 'fas fa-beer-mug-empty', 'fas fa-bell', 'fas fa-bell-concierge', 'fas fa-bell-slash', 'fas fa-bezier-curve', 'fas fa-bicycle', 'fas fa-binoculars', 'fas fa-biohazard', 'fas fa-bitcoin-sign', 'fas fa-blender', 'fas fa-blender-phone', 'fas fa-blog', 'fas fa-bold', 'fas fa-bolt', 'fas fa-bolt-lightning', 'fas fa-bomb', 'fas fa-bone', 'fas fa-bong', 'fas fa-book', 'fas fa-book-atlas', 'fas fa-book-bible', 'fas fa-book-bookmark', 'fas fa-book-journal-whills', 'fas fa-book-medical', 'fas fa-book-open', 'fas fa-book-open-reader', 'fas fa-book-quran', 'fas fa-book-skull', 'fas fa-book-tanakh', 'fas fa-bookmark', 'fas fa-border-all', 'fas fa-border-none', 'fas fa-border-top-left', 'fas fa-bore-hole', 'fas fa-bottle-droplet', 'fas fa-bottle-water', 'fas fa-bowl-food', 'fas fa-bowl-rice', 'fas fa-bowling-ball', 'fas fa-box', 'fas fa-box-archive', 'fas fa-box-open', 'fas fa-box-tissue', 'fas fa-boxes-packing', 'fas fa-boxes-stacked', 'fas fa-braille', 'fas fa-brain', 'fas fa-brazilian-real-sign', 'fas fa-bread-slice', 'fas fa-bridge', 'fas fa-bridge-circle-check', 'fas fa-bridge-circle-exclamation', 'fas fa-bridge-circle-xmark', 'fas fa-bridge-lock', 'fas fa-bridge-water', 'fas fa-briefcase', 'fas fa-briefcase-medical', 'fas fa-broom', 'fas fa-broom-ball', 'fas fa-brush', 'fas fa-bucket', 'fas fa-bug', 'fas fa-bug-slash', 'fas fa-bugs', 'fas fa-building', 'fas fa-building-circle-arrow-right', 'fas fa-building-circle-check', 'fas fa-building-circle-exclamation', 'fas fa-building-circle-xmark', 'fas fa-building-columns', 'fas fa-building-flag', 'fas fa-building-lock', 'fas fa-building-ngo', 'fas fa-building-shield', 'fas fa-building-un', 'fas fa-building-user', 'fas fa-building-wheat', 'fas fa-bullhorn', 'fas fa-bullseye', 'fas fa-burger', 'fas fa-burst', 'fas fa-bus', 'fas fa-bus-simple', 'fas fa-business-time', 'fas fa-c', 'fas fa-cable-car', 'fas fa-cake-candles', 'fas fa-calculator', 'fas fa-calendar', 'fas fa-calendar-check', 'fas fa-calendar-day', 'fas fa-calendar-days', 'fas fa-calendar-minus', 'fas fa-calendar-plus', 'fas fa-calendar-week', 'fas fa-calendar-xmark', 'fas fa-camera', 'fas fa-camera-retro', 'fas fa-camera-rotate', 'fas fa-campground', 'fas fa-candy-cane', 'fas fa-cannabis', 'fas fa-capsules', 'fas fa-car', 'fas fa-car-battery', 'fas fa-car-burst', 'fas fa-car-on', 'fas fa-car-rear', 'fas fa-car-side', 'fas fa-car-tunnel', 'fas fa-caravan', 'fas fa-caret-down', 'fas fa-caret-left', 'fas fa-caret-right', 'fas fa-caret-up', 'fas fa-carrot', 'fas fa-cart-arrow-down', 'fas fa-cart-flatbed', 'fas fa-cart-flatbed-suitcase', 'fas fa-cart-plus', 'fas fa-cart-shopping', 'fas fa-cash-register', 'fas fa-cat', 'fas fa-cedi-sign', 'fas fa-cent-sign', 'fas fa-certificate', 'fas fa-chair', 'fas fa-chalkboard', 'fas fa-chalkboard-user', 'fas fa-champagne-glasses', 'fas fa-charging-station', 'fas fa-chart-area', 'fas fa-chart-bar', 'fas fa-chart-column', 'fas fa-chart-diagram', 'fas fa-chart-gantt', 'fas fa-chart-line', 'fas fa-chart-pie', 'fas fa-chart-simple', 'fas fa-check', 'fas fa-check-double', 'fas fa-check-to-slot', 'fas fa-cheese', 'fas fa-chess', 'fas fa-chess-bishop', 'fas fa-chess-board', 'fas fa-chess-king', 'fas fa-chess-knight', 'fas fa-chess-pawn', 'fas fa-chess-queen', 'fas fa-chess-rook', 'fas fa-chevron-down', 'fas fa-chevron-left', 'fas fa-chevron-right', 'fas fa-chevron-up', 'fas fa-child', 'fas fa-child-combatant', 'fas fa-child-dress', 'fas fa-child-reaching', 'fas fa-children', 'fas fa-church', 'fas fa-circle', 'fas fa-circle-arrow-down', 'fas fa-circle-arrow-left', 'fas fa-circle-arrow-right', 'fas fa-circle-arrow-up', 'fas fa-circle-check', 'fas fa-circle-chevron-down', 'fas fa-circle-chevron-left', 'fas fa-circle-chevron-right', 'fas fa-circle-chevron-up', 'fas fa-circle-dollar-to-slot', 'fas fa-circle-dot', 'fas fa-circle-down', 'fas fa-circle-exclamation', 'fas fa-circle-h', 'fas fa-circle-half-stroke', 'fas fa-circle-info', 'fas fa-circle-left', 'fas fa-circle-minus', 'fas fa-circle-nodes', 'fas fa-circle-notch', 'fas fa-circle-pause', 'fas fa-circle-play', 'fas fa-circle-plus', 'fas fa-circle-question', 'fas fa-circle-radiation', 'fas fa-circle-right', 'fas fa-circle-stop', 'fas fa-circle-up', 'fas fa-circle-user', 'fas fa-circle-xmark', 'fas fa-city', 'fas fa-clapperboard', 'fas fa-clipboard', 'fas fa-clipboard-check', 'fas fa-clipboard-list', 'fas fa-clipboard-question', 'fas fa-clipboard-user', 'fas fa-clock', 'fas fa-clock-rotate-left', 'fas fa-clone', 'fas fa-closed-captioning', 'fas fa-cloud', 'fas fa-cloud-arrow-down', 'fas fa-cloud-arrow-up', 'fas fa-cloud-bolt', 'fas fa-cloud-meatball', 'fas fa-cloud-moon', 'fas fa-cloud-moon-rain', 'fas fa-cloud-rain', 'fas fa-cloud-showers-heavy', 'fas fa-cloud-showers-water', 'fas fa-cloud-sun', 'fas fa-cloud-sun-rain', 'fas fa-clover', 'fas fa-code', 'fas fa-code-branch', 'fas fa-code-commit', 'fas fa-code-compare', 'fas fa-code-fork', 'fas fa-code-merge', 'fas fa-code-pull-request', 'fas fa-coins', 'fas fa-colon-sign', 'fas fa-comment', 'fas fa-comment-dollar', 'fas fa-comment-dots', 'fas fa-comment-medical', 'fas fa-comment-nodes', 'fas fa-comment-slash', 'fas fa-comment-sms', 'fas fa-comments', 'fas fa-comments-dollar', 'fas fa-compact-disc', 'fas fa-compass', 'fas fa-compass-drafting', 'fas fa-compress', 'fas fa-computer', 'fas fa-computer-mouse', 'fas fa-cookie', 'fas fa-cookie-bite', 'fas fa-copy', 'fas fa-copyright', 'fas fa-couch', 'fas fa-cow', 'fas fa-credit-card', 'fas fa-crop', 'fas fa-crop-simple', 'fas fa-cross', 'fas fa-crosshairs', 'fas fa-crow', 'fas fa-crown', 'fas fa-crutch', 'fas fa-cruzeiro-sign', 'fas fa-cube', 'fas fa-cubes', 'fas fa-cubes-stacked', 'fas fa-d', 'fas fa-database', 'fas fa-delete-left', 'fas fa-democrat', 'fas fa-desktop', 'fas fa-dharmachakra', 'fas fa-diagram-next', 'fas fa-diagram-predecessor', 'fas fa-diagram-project', 'fas fa-diagram-successor', 'fas fa-diamond', 'fas fa-diamond-turn-right', 'fas fa-dice', 'fas fa-dice-d20', 'fas fa-dice-d6', 'fas fa-dice-five', 'fas fa-dice-four', 'fas fa-dice-one', 'fas fa-dice-six', 'fas fa-dice-three', 'fas fa-dice-two', 'fas fa-disease', 'fas fa-display', 'fas fa-divide', 'fas fa-dna', 'fas fa-dog', 'fas fa-dollar-sign', 'fas fa-dolly', 'fas fa-dong-sign', 'fas fa-door-closed', 'fas fa-door-open', 'fas fa-dove', 'fas fa-down-left-and-up-right-to-center', 'fas fa-down-long', 'fas fa-download', 'fas fa-dragon', 'fas fa-draw-polygon', 'fas fa-droplet', 'fas fa-droplet-slash', 'fas fa-drum', 'fas fa-drum-steelpan', 'fas fa-drumstick-bite', 'fas fa-dumbbell', 'fas fa-dumpster', 'fas fa-dumpster-fire', 'fas fa-dungeon', 'fas fa-e', 'fas fa-ear-deaf', 'fas fa-ear-listen', 'fas fa-earth-africa', 'fas fa-earth-americas', 'fas fa-earth-asia', 'fas fa-earth-europe', 'fas fa-earth-oceania', 'fas fa-egg', 'fas fa-eject', 'fas fa-elevator', 'fas fa-ellipsis', 'fas fa-ellipsis-vertical', 'fas fa-envelope', 'fas fa-envelope-circle-check', 'fas fa-envelope-open', 'fas fa-envelope-open-text', 'fas fa-envelopes-bulk', 'fas fa-equals', 'fas fa-eraser', 'fas fa-ethernet', 'fas fa-euro-sign', 'fas fa-exclamation', 'fas fa-expand', 'fas fa-explosion', 'fas fa-eye', 'fas fa-eye-dropper', 'fas fa-eye-low-vision', 'fas fa-eye-slash', 'fas fa-f', 'fas fa-face-angry', 'fas fa-face-dizzy', 'fas fa-face-flushed', 'fas fa-face-frown', 'fas fa-face-frown-open', 'fas fa-face-grimace', 'fas fa-face-grin', 'fas fa-face-grin-beam', 'fas fa-face-grin-beam-sweat', 'fas fa-face-grin-hearts', 'fas fa-face-grin-squint', 'fas fa-face-grin-squint-tears', 'fas fa-face-grin-stars', 'fas fa-face-grin-tears', 'fas fa-face-grin-tongue', 'fas fa-face-grin-tongue-squint', 'fas fa-face-grin-tongue-wink', 'fas fa-face-grin-wide', 'fas fa-face-grin-wink', 'fas fa-face-kiss', 'fas fa-face-kiss-beam', 'fas fa-face-kiss-wink-heart', 'fas fa-face-laugh', 'fas fa-face-laugh-beam', 'fas fa-face-laugh-squint', 'fas fa-face-laugh-wink', 'fas fa-face-meh', 'fas fa-face-meh-blank', 'fas fa-face-rolling-eyes', 'fas fa-face-sad-cry', 'fas fa-face-sad-tear', 'fas fa-face-smile', 'fas fa-face-smile-beam', 'fas fa-face-smile-wink', 'fas fa-face-surprise', 'fas fa-face-tired', 'fas fa-fan', 'fas fa-faucet', 'fas fa-faucet-drip', 'fas fa-fax', 'fas fa-feather', 'fas fa-feather-pointed', 'fas fa-ferry', 'fas fa-file', 'fas fa-file-arrow-down', 'fas fa-file-arrow-up', 'fas fa-file-audio', 'fas fa-file-circle-check', 'fas fa-file-circle-exclamation', 'fas fa-file-circle-minus', 'fas fa-file-circle-plus', 'fas fa-file-circle-question', 'fas fa-file-circle-xmark', 'fas fa-file-code', 'fas fa-file-contract', 'fas fa-file-csv', 'fas fa-file-excel', 'fas fa-file-export', 'fas fa-file-fragment', 'fas fa-file-half-dashed', 'fas fa-file-image', 'fas fa-file-import', 'fas fa-file-invoice', 'fas fa-file-invoice-dollar', 'fas fa-file-lines', 'fas fa-file-medical', 'fas fa-file-pdf', 'fas fa-file-pen', 'fas fa-file-powerpoint', 'fas fa-file-prescription', 'fas fa-file-shield', 'fas fa-file-signature', 'fas fa-file-video', 'fas fa-file-waveform', 'fas fa-file-word', 'fas fa-file-zipper', 'fas fa-fill', 'fas fa-fill-drip', 'fas fa-film', 'fas fa-filter', 'fas fa-filter-circle-dollar', 'fas fa-filter-circle-xmark', 'fas fa-fingerprint', 'fas fa-fire', 'fas fa-fire-burner', 'fas fa-fire-extinguisher', 'fas fa-fire-flame-curved', 'fas fa-fire-flame-simple', 'fas fa-fish', 'fas fa-fish-fins', 'fas fa-flag', 'fas fa-flag-checkered', 'fas fa-flag-usa', 'fas fa-flask', 'fas fa-flask-vial', 'fas fa-floppy-disk', 'fas fa-florin-sign', 'fas fa-folder', 'fas fa-folder-closed', 'fas fa-folder-minus', 'fas fa-folder-open', 'fas fa-folder-plus', 'fas fa-folder-tree', 'fas fa-font', 'fas fa-font-awesome', 'fas fa-football', 'fas fa-forward', 'fas fa-forward-fast', 'fas fa-forward-step', 'fas fa-franc-sign', 'fas fa-frog', 'fas fa-futbol', 'fas fa-g', 'fas fa-gamepad', 'fas fa-gas-pump', 'fas fa-gauge', 'fas fa-gauge-high', 'fas fa-gauge-simple', 'fas fa-gauge-simple-high', 'fas fa-gavel', 'fas fa-gear', 'fas fa-gears', 'fas fa-gem', 'fas fa-genderless', 'fas fa-ghost', 'fas fa-gift', 'fas fa-gifts', 'fas fa-glass-water', 'fas fa-glass-water-droplet', 'fas fa-glasses', 'fas fa-globe', 'fas fa-golf-ball-tee', 'fas fa-gopuram', 'fas fa-graduation-cap', 'fas fa-greater-than', 'fas fa-greater-than-equal', 'fas fa-grip', 'fas fa-grip-lines', 'fas fa-grip-lines-vertical', 'fas fa-grip-vertical', 'fas fa-group-arrows-rotate', 'fas fa-guarani-sign', 'fas fa-guitar', 'fas fa-gun', 'fas fa-h', 'fas fa-hammer', 'fas fa-hamsa', 'fas fa-hand', 'fas fa-hand-back-fist', 'fas fa-hand-dots', 'fas fa-hand-fist', 'fas fa-hand-holding', 'fas fa-hand-holding-dollar', 'fas fa-hand-holding-droplet', 'fas fa-hand-holding-hand', 'fas fa-hand-holding-heart', 'fas fa-hand-holding-medical', 'fas fa-hand-lizard', 'fas fa-hand-middle-finger', 'fas fa-hand-peace', 'fas fa-hand-point-down', 'fas fa-hand-point-left', 'fas fa-hand-point-right', 'fas fa-hand-point-up', 'fas fa-hand-pointer', 'fas fa-hand-scissors', 'fas fa-hand-sparkles', 'fas fa-hand-spock', 'fas fa-handcuffs', 'fas fa-hands', 'fas fa-hands-asl-interpreting', 'fas fa-hands-bound', 'fas fa-hands-bubbles', 'fas fa-hands-clapping', 'fas fa-hands-holding', 'fas fa-hands-holding-child', 'fas fa-hands-holding-circle', 'fas fa-hands-praying', 'fas fa-handshake', 'fas fa-handshake-angle', 'fas fa-handshake-simple', 'fas fa-handshake-simple-slash', 'fas fa-handshake-slash', 'fas fa-hanukiah', 'fas fa-hard-drive', 'fas fa-hashtag', 'fas fa-hat-cowboy', 'fas fa-hat-cowboy-side', 'fas fa-hat-wizard', 'fas fa-head-side-cough', 'fas fa-head-side-cough-slash', 'fas fa-head-side-mask', 'fas fa-head-side-virus', 'fas fa-heading', 'fas fa-headphones', 'fas fa-headphones-simple', 'fas fa-headset', 'fas fa-heart', 'fas fa-heart-circle-bolt', 'fas fa-heart-circle-check', 'fas fa-heart-circle-exclamation', 'fas fa-heart-circle-minus', 'fas fa-heart-circle-plus', 'fas fa-heart-circle-xmark', 'fas fa-heart-crack', 'fas fa-heart-pulse', 'fas fa-helicopter', 'fas fa-helicopter-symbol', 'fas fa-helmet-safety', 'fas fa-helmet-un', 'fas fa-hexagon-nodes', 'fas fa-hexagon-nodes-bolt', 'fas fa-highlighter', 'fas fa-hill-avalanche', 'fas fa-hill-rockslide', 'fas fa-hippo', 'fas fa-hockey-puck', 'fas fa-holly-berry', 'fas fa-horse', 'fas fa-horse-head', 'fas fa-hospital', 'fas fa-hospital-user', 'fas fa-hot-tub-person', 'fas fa-hotdog', 'fas fa-hotel', 'fas fa-hourglass', 'fas fa-hourglass-end', 'fas fa-hourglass-half', 'fas fa-hourglass-start', 'fas fa-house', 'fas fa-house-chimney', 'fas fa-house-chimney-crack', 'fas fa-house-chimney-medical', 'fas fa-house-chimney-user', 'fas fa-house-chimney-window', 'fas fa-house-circle-check', 'fas fa-house-circle-exclamation', 'fas fa-house-circle-xmark', 'fas fa-house-crack', 'fas fa-house-fire', 'fas fa-house-flag', 'fas fa-house-flood-water', 'fas fa-house-flood-water-circle-arrow-right', 'fas fa-house-laptop', 'fas fa-house-lock', 'fas fa-house-medical', 'fas fa-house-medical-circle-check', 'fas fa-house-medical-circle-exclamation', 'fas fa-house-medical-circle-xmark', 'fas fa-house-medical-flag', 'fas fa-house-signal', 'fas fa-house-tsunami', 'fas fa-house-user', 'fas fa-hryvnia-sign', 'fas fa-hurricane', 'fas fa-i', 'fas fa-i-cursor', 'fas fa-ice-cream', 'fas fa-icicles', 'fas fa-icons', 'fas fa-id-badge', 'fas fa-id-card', 'fas fa-id-card-clip', 'fas fa-igloo', 'fas fa-image', 'fas fa-image-portrait', 'fas fa-images', 'fas fa-inbox', 'fas fa-indent', 'fas fa-indian-rupee-sign', 'fas fa-industry', 'fas fa-infinity', 'fas fa-info', 'fas fa-italic', 'fas fa-j', 'fas fa-jar', 'fas fa-jar-wheat', 'fas fa-jedi', 'fas fa-jet-fighter', 'fas fa-jet-fighter-up', 'fas fa-joint', 'fas fa-jug-detergent', 'fas fa-k', 'fas fa-kaaba', 'fas fa-key', 'fas fa-keyboard', 'fas fa-khanda', 'fas fa-kip-sign', 'fas fa-kit-medical', 'fas fa-kitchen-set', 'fas fa-kiwi-bird', 'fas fa-l', 'fas fa-land-mine-on', 'fas fa-landmark', 'fas fa-landmark-dome', 'fas fa-landmark-flag', 'fas fa-language', 'fas fa-laptop', 'fas fa-laptop-code', 'fas fa-laptop-file', 'fas fa-laptop-medical', 'fas fa-lari-sign', 'fas fa-layer-group', 'fas fa-leaf', 'fas fa-left-long', 'fas fa-left-right', 'fas fa-lemon', 'fas fa-less-than', 'fas fa-less-than-equal', 'fas fa-life-ring', 'fas fa-lightbulb', 'fas fa-lines-leaning', 'fas fa-link', 'fas fa-link-slash', 'fas fa-lira-sign', 'fas fa-list', 'fas fa-list-check', 'fas fa-list-ol', 'fas fa-list-ul', 'fas fa-litecoin-sign', 'fas fa-location-arrow', 'fas fa-location-crosshairs', 'fas fa-location-dot', 'fas fa-location-pin', 'fas fa-location-pin-lock', 'fas fa-lock', 'fas fa-lock-open', 'fas fa-locust', 'fas fa-lungs', 'fas fa-lungs-virus', 'fas fa-m', 'fas fa-magnet', 'fas fa-magnifying-glass', 'fas fa-magnifying-glass-arrow-right', 'fas fa-magnifying-glass-chart', 'fas fa-magnifying-glass-dollar', 'fas fa-magnifying-glass-location', 'fas fa-magnifying-glass-minus', 'fas fa-magnifying-glass-plus', 'fas fa-manat-sign', 'fas fa-map', 'fas fa-map-location', 'fas fa-map-location-dot', 'fas fa-map-pin', 'fas fa-marker', 'fas fa-mars', 'fas fa-mars-and-venus', 'fas fa-mars-and-venus-burst', 'fas fa-mars-double', 'fas fa-mars-stroke', 'fas fa-mars-stroke-right', 'fas fa-mars-stroke-up', 'fas fa-martini-glass', 'fas fa-martini-glass-citrus', 'fas fa-martini-glass-empty', 'fas fa-mask', 'fas fa-mask-face', 'fas fa-mask-ventilator', 'fas fa-masks-theater', 'fas fa-mattress-pillow', 'fas fa-maximize', 'fas fa-medal', 'fas fa-memory', 'fas fa-menorah', 'fas fa-mercury', 'fas fa-message', 'fas fa-meteor', 'fas fa-microchip', 'fas fa-microphone', 'fas fa-microphone-lines', 'fas fa-microphone-lines-slash', 'fas fa-microphone-slash', 'fas fa-microscope', 'fas fa-mill-sign', 'fas fa-minimize', 'fas fa-minus', 'fas fa-mitten', 'fas fa-mobile', 'fas fa-mobile-button', 'fas fa-mobile-retro', 'fas fa-mobile-screen', 'fas fa-mobile-screen-button', 'fas fa-money-bill', 'fas fa-money-bill-1', 'fas fa-money-bill-1-wave', 'fas fa-money-bill-transfer', 'fas fa-money-bill-trend-up', 'fas fa-money-bill-wave', 'fas fa-money-bill-wheat', 'fas fa-money-bills', 'fas fa-money-check', 'fas fa-money-check-dollar', 'fas fa-monument', 'fas fa-moon', 'fas fa-mortar-pestle', 'fas fa-mosque', 'fas fa-mosquito', 'fas fa-mosquito-net', 'fas fa-motorcycle', 'fas fa-mound', 'fas fa-mountain', 'fas fa-mountain-city', 'fas fa-mountain-sun', 'fas fa-mug-hot', 'fas fa-mug-saucer', 'fas fa-music', 'fas fa-n', 'fas fa-naira-sign', 'fas fa-network-wired', 'fas fa-neuter', 'fas fa-newspaper', 'fas fa-not-equal', 'fas fa-notdef', 'fas fa-note-sticky', 'fas fa-notes-medical', 'fas fa-o', 'fas fa-object-group', 'fas fa-object-ungroup', 'fas fa-oil-can', 'fas fa-oil-well', 'fas fa-om', 'fas fa-otter', 'fas fa-outdent', 'fas fa-p', 'fas fa-pager', 'fas fa-paint-roller', 'fas fa-paintbrush', 'fas fa-palette', 'fas fa-pallet', 'fas fa-panorama', 'fas fa-paper-plane', 'fas fa-paperclip', 'fas fa-parachute-box', 'fas fa-paragraph', 'fas fa-passport', 'fas fa-paste', 'fas fa-pause', 'fas fa-paw', 'fas fa-peace', 'fas fa-pen', 'fas fa-pen-clip', 'fas fa-pen-fancy', 'fas fa-pen-nib', 'fas fa-pen-ruler', 'fas fa-pen-to-square', 'fas fa-pencil', 'fas fa-people-arrows', 'fas fa-people-carry-box', 'fas fa-people-group', 'fas fa-people-line', 'fas fa-people-pulling', 'fas fa-people-robbery', 'fas fa-people-roof', 'fas fa-pepper-hot', 'fas fa-percent', 'fas fa-person', 'fas fa-person-arrow-down-to-line', 'fas fa-person-arrow-up-from-line', 'fas fa-person-biking', 'fas fa-person-booth', 'fas fa-person-breastfeeding', 'fas fa-person-burst', 'fas fa-person-cane', 'fas fa-person-chalkboard', 'fas fa-person-circle-check', 'fas fa-person-circle-exclamation', 'fas fa-person-circle-minus', 'fas fa-person-circle-plus', 'fas fa-person-circle-question', 'fas fa-person-circle-xmark', 'fas fa-person-digging', 'fas fa-person-dots-from-line', 'fas fa-person-dress', 'fas fa-person-dress-burst', 'fas fa-person-drowning', 'fas fa-person-falling', 'fas fa-person-falling-burst', 'fas fa-person-half-dress', 'fas fa-person-harassing', 'fas fa-person-hiking', 'fas fa-person-military-pointing', 'fas fa-person-military-rifle', 'fas fa-person-military-to-person', 'fas fa-person-praying', 'fas fa-person-pregnant', 'fas fa-person-rays', 'fas fa-person-rifle', 'fas fa-person-running', 'fas fa-person-shelter', 'fas fa-person-skating', 'fas fa-person-skiing', 'fas fa-person-skiing-nordic', 'fas fa-person-snowboarding', 'fas fa-person-swimming', 'fas fa-person-through-window', 'fas fa-person-walking', 'fas fa-person-walking-arrow-loop-left', 'fas fa-person-walking-arrow-right', 'fas fa-person-walking-dashed-line-arrow-right', 'fas fa-person-walking-luggage', 'fas fa-person-walking-with-cane', 'fas fa-peseta-sign', 'fas fa-peso-sign', 'fas fa-phone', 'fas fa-phone-flip', 'fas fa-phone-slash', 'fas fa-phone-volume', 'fas fa-photo-film', 'fas fa-piggy-bank', 'fas fa-pills', 'fas fa-pizza-slice', 'fas fa-place-of-worship', 'fas fa-plane', 'fas fa-plane-arrival', 'fas fa-plane-circle-check', 'fas fa-plane-circle-exclamation', 'fas fa-plane-circle-xmark', 'fas fa-plane-departure', 'fas fa-plane-lock', 'fas fa-plane-slash', 'fas fa-plane-up', 'fas fa-plant-wilt', 'fas fa-plate-wheat', 'fas fa-play', 'fas fa-plug', 'fas fa-plug-circle-bolt', 'fas fa-plug-circle-check', 'fas fa-plug-circle-exclamation', 'fas fa-plug-circle-minus', 'fas fa-plug-circle-plus', 'fas fa-plug-circle-xmark', 'fas fa-plus', 'fas fa-plus-minus', 'fas fa-podcast', 'fas fa-poo', 'fas fa-poo-storm', 'fas fa-poop', 'fas fa-power-off', 'fas fa-prescription', 'fas fa-prescription-bottle', 'fas fa-prescription-bottle-medical', 'fas fa-print', 'fas fa-pump-medical', 'fas fa-pump-soap', 'fas fa-puzzle-piece', 'fas fa-q', 'fas fa-qrcode', 'fas fa-question', 'fas fa-quote-left', 'fas fa-quote-right', 'fas fa-r', 'fas fa-radiation', 'fas fa-radio', 'fas fa-rainbow', 'fas fa-ranking-star', 'fas fa-receipt', 'fas fa-record-vinyl', 'fas fa-rectangle-ad', 'fas fa-rectangle-list', 'fas fa-rectangle-xmark', 'fas fa-recycle', 'fas fa-registered', 'fas fa-repeat', 'fas fa-reply', 'fas fa-reply-all', 'fas fa-republican', 'fas fa-restroom', 'fas fa-retweet', 'fas fa-ribbon', 'fas fa-right-from-bracket', 'fas fa-right-left', 'fas fa-right-long', 'fas fa-right-to-bracket', 'fas fa-ring', 'fas fa-road', 'fas fa-road-barrier', 'fas fa-road-bridge', 'fas fa-road-circle-check', 'fas fa-road-circle-exclamation', 'fas fa-road-circle-xmark', 'fas fa-road-lock', 'fas fa-road-spikes', 'fas fa-robot', 'fas fa-rocket', 'fas fa-rotate', 'fas fa-rotate-left', 'fas fa-rotate-right', 'fas fa-route', 'fas fa-rss', 'fas fa-ruble-sign', 'fas fa-rug', 'fas fa-ruler', 'fas fa-ruler-combined', 'fas fa-ruler-horizontal', 'fas fa-ruler-vertical', 'fas fa-rupee-sign', 'fas fa-rupiah-sign', 'fas fa-s', 'fas fa-sack-dollar', 'fas fa-sack-xmark', 'fas fa-sailboat', 'fas fa-satellite', 'fas fa-satellite-dish', 'fas fa-scale-balanced', 'fas fa-scale-unbalanced', 'fas fa-scale-unbalanced-flip', 'fas fa-school', 'fas fa-school-circle-check', 'fas fa-school-circle-exclamation', 'fas fa-school-circle-xmark', 'fas fa-school-flag', 'fas fa-school-lock', 'fas fa-scissors', 'fas fa-screwdriver', 'fas fa-screwdriver-wrench', 'fas fa-scroll', 'fas fa-scroll-torah', 'fas fa-sd-card', 'fas fa-section', 'fas fa-seedling', 'fas fa-server', 'fas fa-shapes', 'fas fa-share', 'fas fa-share-from-square', 'fas fa-share-nodes', 'fas fa-sheet-plastic', 'fas fa-shekel-sign', 'fas fa-shield', 'fas fa-shield-cat', 'fas fa-shield-dog', 'fas fa-shield-halved', 'fas fa-shield-heart', 'fas fa-shield-virus', 'fas fa-ship', 'fas fa-shirt', 'fas fa-shoe-prints', 'fas fa-shop', 'fas fa-shop-lock', 'fas fa-shop-slash', 'fas fa-shower', 'fas fa-shrimp', 'fas fa-shuffle', 'fas fa-shuttle-space', 'fas fa-sign-hanging', 'fas fa-signal', 'fas fa-signature', 'fas fa-signs-post', 'fas fa-sim-card', 'fas fa-sink', 'fas fa-sitemap', 'fas fa-skull', 'fas fa-skull-crossbones', 'fas fa-slash', 'fas fa-sleigh', 'fas fa-sliders', 'fas fa-smog', 'fas fa-smoking', 'fas fa-snowflake', 'fas fa-snowman', 'fas fa-snowplow', 'fas fa-soap', 'fas fa-socks', 'fas fa-solar-panel', 'fas fa-sort', 'fas fa-sort-down', 'fas fa-sort-up', 'fas fa-spa', 'fas fa-spaghetti-monster-flying', 'fas fa-spell-check', 'fas fa-spider', 'fas fa-spinner', 'fas fa-splotch', 'fas fa-spoon', 'fas fa-spray-can', 'fas fa-spray-can-sparkles', 'fas fa-square', 'fas fa-square-arrow-up-right', 'fas fa-square-binary', 'fas fa-square-caret-down', 'fas fa-square-caret-left', 'fas fa-square-caret-right', 'fas fa-square-caret-up', 'fas fa-square-check', 'fas fa-square-envelope', 'fas fa-square-full', 'fas fa-square-h', 'fas fa-square-minus', 'fas fa-square-nfi', 'fas fa-square-parking', 'fas fa-square-pen', 'fas fa-square-person-confined', 'fas fa-square-phone', 'fas fa-square-phone-flip', 'fas fa-square-plus', 'fas fa-square-poll-horizontal', 'fas fa-square-poll-vertical', 'fas fa-square-root-variable', 'fas fa-square-rss', 'fas fa-square-share-nodes', 'fas fa-square-up-right', 'fas fa-square-virus', 'fas fa-square-xmark', 'fas fa-staff-snake', 'fas fa-stairs', 'fas fa-stamp', 'fas fa-stapler', 'fas fa-star', 'fas fa-star-and-crescent', 'fas fa-star-half', 'fas fa-star-half-stroke', 'fas fa-star-of-david', 'fas fa-star-of-life', 'fas fa-sterling-sign', 'fas fa-stethoscope', 'fas fa-stop', 'fas fa-stopwatch', 'fas fa-stopwatch-20', 'fas fa-store', 'fas fa-store-slash', 'fas fa-street-view', 'fas fa-strikethrough', 'fas fa-stroopwafel', 'fas fa-subscript', 'fas fa-suitcase', 'fas fa-suitcase-medical', 'fas fa-suitcase-rolling', 'fas fa-sun', 'fas fa-sun-plant-wilt', 'fas fa-superscript', 'fas fa-swatchbook', 'fas fa-synagogue', 'fas fa-syringe', 'fas fa-t', 'fas fa-table', 'fas fa-table-cells', 'fas fa-table-cells-column-lock', 'fas fa-table-cells-large', 'fas fa-table-cells-row-lock', 'fas fa-table-cells-row-unlock', 'fas fa-table-columns', 'fas fa-table-list', 'fas fa-table-tennis-paddle-ball', 'fas fa-tablet', 'fas fa-tablet-button', 'fas fa-tablet-screen-button', 'fas fa-tablets', 'fas fa-tachograph-digital', 'fas fa-tag', 'fas fa-tags', 'fas fa-tape', 'fas fa-tarp', 'fas fa-tarp-droplet', 'fas fa-taxi', 'fas fa-teeth', 'fas fa-teeth-open', 'fas fa-temperature-arrow-down', 'fas fa-temperature-arrow-up', 'fas fa-temperature-empty', 'fas fa-temperature-full', 'fas fa-temperature-half', 'fas fa-temperature-high', 'fas fa-temperature-low', 'fas fa-temperature-quarter', 'fas fa-temperature-three-quarters', 'fas fa-tenge-sign', 'fas fa-tent', 'fas fa-tent-arrow-down-to-line', 'fas fa-tent-arrow-left-right', 'fas fa-tent-arrow-turn-left', 'fas fa-tent-arrows-down', 'fas fa-tents', 'fas fa-terminal', 'fas fa-text-height', 'fas fa-text-slash', 'fas fa-text-width', 'fas fa-thermometer', 'fas fa-thumbs-down', 'fas fa-thumbs-up', 'fas fa-thumbtack', 'fas fa-thumbtack-slash', 'fas fa-ticket', 'fas fa-ticket-simple', 'fas fa-timeline', 'fas fa-toggle-off', 'fas fa-toggle-on', 'fas fa-toilet', 'fas fa-toilet-paper', 'fas fa-toilet-paper-slash', 'fas fa-toilet-portable', 'fas fa-toilets-portable', 'fas fa-toolbox', 'fas fa-tooth', 'fas fa-torii-gate', 'fas fa-tornado', 'fas fa-tower-broadcast', 'fas fa-tower-cell', 'fas fa-tower-observation', 'fas fa-tractor', 'fas fa-trademark', 'fas fa-traffic-light', 'fas fa-trailer', 'fas fa-train', 'fas fa-train-subway', 'fas fa-train-tram', 'fas fa-transgender', 'fas fa-trash', 'fas fa-trash-arrow-up', 'fas fa-trash-can', 'fas fa-trash-can-arrow-up', 'fas fa-tree', 'fas fa-tree-city', 'fas fa-triangle-exclamation', 'fas fa-trophy', 'fas fa-trowel', 'fas fa-trowel-bricks', 'fas fa-truck', 'fas fa-truck-arrow-right', 'fas fa-truck-droplet', 'fas fa-truck-fast', 'fas fa-truck-field', 'fas fa-truck-field-un', 'fas fa-truck-front', 'fas fa-truck-medical', 'fas fa-truck-monster', 'fas fa-truck-moving', 'fas fa-truck-pickup', 'fas fa-truck-plane', 'fas fa-truck-ramp-box', 'fas fa-tty', 'fas fa-turkish-lira-sign', 'fas fa-turn-down', 'fas fa-turn-up', 'fas fa-tv', 'fas fa-u', 'fas fa-umbrella', 'fas fa-umbrella-beach', 'fas fa-underline', 'fas fa-universal-access', 'fas fa-unlock', 'fas fa-unlock-keyhole', 'fas fa-up-down', 'fas fa-up-down-left-right', 'fas fa-up-long', 'fas fa-up-right-and-down-left-from-center', 'fas fa-up-right-from-square', 'fas fa-upload', 'fas fa-user', 'fas fa-user-astronaut', 'fas fa-user-check', 'fas fa-user-clock', 'fas fa-user-doctor', 'fas fa-user-gear', 'fas fa-user-graduate', 'fas fa-user-group', 'fas fa-user-injured', 'fas fa-user-large', 'fas fa-user-large-slash', 'fas fa-user-lock', 'fas fa-user-minus', 'fas fa-user-ninja', 'fas fa-user-nurse', 'fas fa-user-pen', 'fas fa-user-plus', 'fas fa-user-secret', 'fas fa-user-shield', 'fas fa-user-slash', 'fas fa-user-tag', 'fas fa-user-tie', 'fas fa-user-xmark', 'fas fa-users', 'fas fa-users-between-lines', 'fas fa-users-gear', 'fas fa-users-line', 'fas fa-users-rays', 'fas fa-users-rectangle', 'fas fa-users-slash', 'fas fa-users-viewfinder', 'fas fa-utensils', 'fas fa-v', 'fas fa-van-shuttle', 'fas fa-vault', 'fas fa-vector-square', 'fas fa-venus', 'fas fa-venus-double', 'fas fa-venus-mars', 'fas fa-vest', 'fas fa-vest-patches', 'fas fa-vial', 'fas fa-vial-circle-check', 'fas fa-vial-virus', 'fas fa-vials', 'fas fa-video', 'fas fa-video-slash', 'fas fa-vihara', 'fas fa-virus', 'fas fa-virus-covid', 'fas fa-virus-covid-slash', 'fas fa-virus-slash', 'fas fa-viruses', 'fas fa-voicemail', 'fas fa-volcano', 'fas fa-volleyball', 'fas fa-volume-high', 'fas fa-volume-low', 'fas fa-volume-off', 'fas fa-volume-xmark', 'fas fa-vr-cardboard', 'fas fa-w', 'fas fa-walkie-talkie', 'fas fa-wallet', 'fas fa-wand-magic', 'fas fa-wand-magic-sparkles', 'fas fa-wand-sparkles', 'fas fa-warehouse', 'fas fa-water', 'fas fa-water-ladder', 'fas fa-wave-square', 'fas fa-web-awesome', 'fas fa-weight-hanging', 'fas fa-weight-scale', 'fas fa-wheat-awn', 'fas fa-wheat-awn-circle-exclamation', 'fas fa-wheelchair', 'fas fa-wheelchair-move', 'fas fa-whiskey-glass', 'fas fa-wifi', 'fas fa-wind', 'fas fa-window-maximize', 'fas fa-window-minimize', 'fas fa-window-restore', 'fas fa-wine-bottle', 'fas fa-wine-glass', 'fas fa-wine-glass-empty', 'fas fa-won-sign', 'fas fa-worm', 'fas fa-wrench', 'fas fa-x', 'fas fa-x-ray', 'fas fa-xmark', 'fas fa-xmarks-lines', 'fas fa-y', 'fas fa-yen-sign', 'fas fa-yin-yang', 'fas fa-z'],
                'Regular': ['far fa-address-book', 'far fa-address-card', 'far fa-bell', 'far fa-bell-slash', 'far fa-bookmark', 'far fa-building', 'far fa-calendar', 'far fa-calendar-check', 'far fa-calendar-days', 'far fa-calendar-minus', 'far fa-calendar-plus', 'far fa-calendar-xmark', 'far fa-chart-bar', 'far fa-chess-bishop', 'far fa-chess-king', 'far fa-chess-knight', 'far fa-chess-pawn', 'far fa-chess-queen', 'far fa-chess-rook', 'far fa-circle', 'far fa-circle-check', 'far fa-circle-dot', 'far fa-circle-down', 'far fa-circle-left', 'far fa-circle-pause', 'far fa-circle-play', 'far fa-circle-question', 'far fa-circle-right', 'far fa-circle-stop', 'far fa-circle-up', 'far fa-circle-user', 'far fa-circle-xmark', 'far fa-clipboard', 'far fa-clock', 'far fa-clone', 'far fa-closed-captioning', 'far fa-comment', 'far fa-comment-dots', 'far fa-comments', 'far fa-compass', 'far fa-copy', 'far fa-copyright', 'far fa-credit-card', 'far fa-envelope', 'far fa-envelope-open', 'far fa-eye', 'far fa-eye-slash', 'far fa-face-angry', 'far fa-face-dizzy', 'far fa-face-flushed', 'far fa-face-frown', 'far fa-face-frown-open', 'far fa-face-grimace', 'far fa-face-grin', 'far fa-face-grin-beam', 'far fa-face-grin-beam-sweat', 'far fa-face-grin-hearts', 'far fa-face-grin-squint', 'far fa-face-grin-squint-tears', 'far fa-face-grin-stars', 'far fa-face-grin-tears', 'far fa-face-grin-tongue', 'far fa-face-grin-tongue-squint', 'far fa-face-grin-tongue-wink', 'far fa-face-grin-wide', 'far fa-face-grin-wink', 'far fa-face-kiss', 'far fa-face-kiss-beam', 'far fa-face-kiss-wink-heart', 'far fa-face-laugh', 'far fa-face-laugh-beam', 'far fa-face-laugh-squint', 'far fa-face-laugh-wink', 'far fa-face-meh', 'far fa-face-meh-blank', 'far fa-face-rolling-eyes', 'far fa-face-sad-cry', 'far fa-face-sad-tear', 'far fa-face-smile', 'far fa-face-smile-beam', 'far fa-face-smile-wink', 'far fa-face-surprise', 'far fa-face-tired', 'far fa-file', 'far fa-file-audio', 'far fa-file-code', 'far fa-file-excel', 'far fa-file-image', 'far fa-file-lines', 'far fa-file-pdf', 'far fa-file-powerpoint', 'far fa-file-video', 'far fa-file-word', 'far fa-file-zipper', 'far fa-flag', 'far fa-floppy-disk', 'far fa-folder', 'far fa-folder-closed', 'far fa-folder-open', 'far fa-font-awesome', 'far fa-futbol', 'far fa-gem', 'far fa-hand', 'far fa-hand-back-fist', 'far fa-hand-lizard', 'far fa-hand-peace', 'far fa-hand-point-down', 'far fa-hand-point-left', 'far fa-hand-point-right', 'far fa-hand-point-up', 'far fa-hand-pointer', 'far fa-hand-scissors', 'far fa-hand-spock', 'far fa-handshake', 'far fa-hard-drive', 'far fa-heart', 'far fa-hospital', 'far fa-hourglass', 'far fa-hourglass-half', 'far fa-id-badge', 'far fa-id-card', 'far fa-image', 'far fa-images', 'far fa-keyboard', 'far fa-lemon', 'far fa-life-ring', 'far fa-lightbulb', 'far fa-map', 'far fa-message', 'far fa-money-bill-1', 'far fa-moon', 'far fa-newspaper', 'far fa-note-sticky', 'far fa-object-group', 'far fa-object-ungroup', 'far fa-paper-plane', 'far fa-paste', 'far fa-pen-to-square', 'far fa-rectangle-list', 'far fa-rectangle-xmark', 'far fa-registered', 'far fa-share-from-square', 'far fa-snowflake', 'far fa-square', 'far fa-square-caret-down', 'far fa-square-caret-left', 'far fa-square-caret-right', 'far fa-square-caret-up', 'far fa-square-check', 'far fa-square-full', 'far fa-square-minus', 'far fa-square-plus', 'far fa-star', 'far fa-star-half', 'far fa-star-half-stroke', 'far fa-sun', 'far fa-thumbs-down', 'far fa-thumbs-up', 'far fa-trash-can', 'far fa-user', 'far fa-window-maximize', 'far fa-window-minimize', 'far fa-window-restore'],
                'Brands': ['fab fa-42-group', 'fab fa-500px', 'fab fa-accessible-icon', 'fab fa-accusoft', 'fab fa-adn', 'fab fa-adversal', 'fab fa-affiliatetheme', 'fab fa-airbnb', 'fab fa-algolia', 'fab fa-alipay', 'fab fa-amazon', 'fab fa-amazon-pay', 'fab fa-amilia', 'fab fa-android', 'fab fa-angellist', 'fab fa-angrycreative', 'fab fa-angular', 'fab fa-app-store', 'fab fa-app-store-ios', 'fab fa-apper', 'fab fa-apple', 'fab fa-apple-pay', 'fab fa-artstation', 'fab fa-asymmetrik', 'fab fa-atlassian', 'fab fa-audible', 'fab fa-autoprefixer', 'fab fa-avianex', 'fab fa-aviato', 'fab fa-aws', 'fab fa-bandcamp', 'fab fa-battle-net', 'fab fa-behance', 'fab fa-bilibili', 'fab fa-bimobject', 'fab fa-bitbucket', 'fab fa-bitcoin', 'fab fa-bity', 'fab fa-black-tie', 'fab fa-blackberry', 'fab fa-blogger', 'fab fa-blogger-b', 'fab fa-bluesky', 'fab fa-bluetooth', 'fab fa-bluetooth-b', 'fab fa-bootstrap', 'fab fa-bots', 'fab fa-brave', 'fab fa-brave-reverse', 'fab fa-btc', 'fab fa-buffer', 'fab fa-buromobelexperte', 'fab fa-buy-n-large', 'fab fa-buysellads', 'fab fa-canadian-maple-leaf', 'fab fa-cc-amazon-pay', 'fab fa-cc-amex', 'fab fa-cc-apple-pay', 'fab fa-cc-diners-club', 'fab fa-cc-discover', 'fab fa-cc-jcb', 'fab fa-cc-mastercard', 'fab fa-cc-paypal', 'fab fa-cc-stripe', 'fab fa-cc-visa', 'fab fa-centercode', 'fab fa-centos', 'fab fa-chrome', 'fab fa-chromecast', 'fab fa-cloudflare', 'fab fa-cloudscale', 'fab fa-cloudsmith', 'fab fa-cloudversify', 'fab fa-cmplid', 'fab fa-codepen', 'fab fa-codiepie', 'fab fa-confluence', 'fab fa-connectdevelop', 'fab fa-contao', 'fab fa-cotton-bureau', 'fab fa-cpanel', 'fab fa-creative-commons', 'fab fa-creative-commons-by', 'fab fa-creative-commons-nc', 'fab fa-creative-commons-nc-eu', 'fab fa-creative-commons-nc-jp', 'fab fa-creative-commons-nd', 'fab fa-creative-commons-pd', 'fab fa-creative-commons-pd-alt', 'fab fa-creative-commons-remix', 'fab fa-creative-commons-sa', 'fab fa-creative-commons-sampling', 'fab fa-creative-commons-sampling-plus', 'fab fa-creative-commons-share', 'fab fa-creative-commons-zero', 'fab fa-critical-role', 'fab fa-css', 'fab fa-css3', 'fab fa-css3-alt', 'fab fa-cuttlefish', 'fab fa-d-and-d', 'fab fa-d-and-d-beyond', 'fab fa-dailymotion', 'fab fa-dart-lang', 'fab fa-dashcube', 'fab fa-debian', 'fab fa-deezer', 'fab fa-delicious', 'fab fa-deploydog', 'fab fa-deskpro', 'fab fa-dev', 'fab fa-deviantart', 'fab fa-dhl', 'fab fa-diaspora', 'fab fa-digg', 'fab fa-digital-ocean', 'fab fa-discord', 'fab fa-discourse', 'fab fa-dochub', 'fab fa-docker', 'fab fa-draft2digital', 'fab fa-dribbble', 'fab fa-dropbox', 'fab fa-drupal', 'fab fa-dyalog', 'fab fa-earlybirds', 'fab fa-ebay', 'fab fa-edge', 'fab fa-edge-legacy', 'fab fa-elementor', 'fab fa-ello', 'fab fa-ember', 'fab fa-empire', 'fab fa-envira', 'fab fa-erlang', 'fab fa-ethereum', 'fab fa-etsy', 'fab fa-evernote', 'fab fa-expeditedssl', 'fab fa-facebook', 'fab fa-facebook-f', 'fab fa-facebook-messenger', 'fab fa-fantasy-flight-games', 'fab fa-fedex', 'fab fa-fedora', 'fab fa-figma', 'fab fa-files-pinwheel', 'fab fa-firefox', 'fab fa-firefox-browser', 'fab fa-first-order', 'fab fa-first-order-alt', 'fab fa-firstdraft', 'fab fa-flickr', 'fab fa-flipboard', 'fab fa-flutter', 'fab fa-fly', 'fab fa-font-awesome', 'fab fa-fonticons', 'fab fa-fonticons-fi', 'fab fa-fort-awesome', 'fab fa-fort-awesome-alt', 'fab fa-forumbee', 'fab fa-foursquare', 'fab fa-free-code-camp', 'fab fa-freebsd', 'fab fa-fulcrum', 'fab fa-galactic-republic', 'fab fa-galactic-senate', 'fab fa-get-pocket', 'fab fa-gg', 'fab fa-gg-circle', 'fab fa-git', 'fab fa-git-alt', 'fab fa-github', 'fab fa-github-alt', 'fab fa-gitkraken', 'fab fa-gitlab', 'fab fa-gitter', 'fab fa-glide', 'fab fa-glide-g', 'fab fa-gofore', 'fab fa-golang', 'fab fa-goodreads', 'fab fa-goodreads-g', 'fab fa-google', 'fab fa-google-drive', 'fab fa-google-pay', 'fab fa-google-play', 'fab fa-google-plus', 'fab fa-google-plus-g', 'fab fa-google-scholar', 'fab fa-google-wallet', 'fab fa-gratipay', 'fab fa-grav', 'fab fa-gripfire', 'fab fa-grunt', 'fab fa-guilded', 'fab fa-gulp', 'fab fa-hacker-news', 'fab fa-hackerrank', 'fab fa-hashnode', 'fab fa-hips', 'fab fa-hire-a-helper', 'fab fa-hive', 'fab fa-hooli', 'fab fa-hornbill', 'fab fa-hotjar', 'fab fa-houzz', 'fab fa-html5', 'fab fa-hubspot', 'fab fa-ideal', 'fab fa-imdb', 'fab fa-instagram', 'fab fa-instalod', 'fab fa-intercom', 'fab fa-internet-explorer', 'fab fa-invision', 'fab fa-ioxhost', 'fab fa-itch-io', 'fab fa-itunes', 'fab fa-itunes-note', 'fab fa-java', 'fab fa-jedi-order', 'fab fa-jenkins', 'fab fa-jira', 'fab fa-joget', 'fab fa-joomla', 'fab fa-js', 'fab fa-jsfiddle', 'fab fa-jxl', 'fab fa-kaggle', 'fab fa-keybase', 'fab fa-keycdn', 'fab fa-kickstarter', 'fab fa-kickstarter-k', 'fab fa-korvue', 'fab fa-laravel', 'fab fa-lastfm', 'fab fa-leanpub', 'fab fa-less', 'fab fa-letterboxd', 'fab fa-line', 'fab fa-linkedin', 'fab fa-linkedin-in', 'fab fa-linode', 'fab fa-linux', 'fab fa-lyft', 'fab fa-magento', 'fab fa-mailchimp', 'fab fa-mandalorian', 'fab fa-markdown', 'fab fa-mastodon', 'fab fa-maxcdn', 'fab fa-mdb', 'fab fa-medapps', 'fab fa-medium', 'fab fa-medrt', 'fab fa-meetup', 'fab fa-megaport', 'fab fa-mendeley', 'fab fa-meta', 'fab fa-microblog', 'fab fa-microsoft', 'fab fa-mintbit', 'fab fa-mix', 'fab fa-mixcloud', 'fab fa-mixer', 'fab fa-mizuni', 'fab fa-modx', 'fab fa-monero', 'fab fa-napster', 'fab fa-neos', 'fab fa-nfc-directional', 'fab fa-nfc-symbol', 'fab fa-nimblr', 'fab fa-node', 'fab fa-node-js', 'fab fa-npm', 'fab fa-ns8', 'fab fa-nutritionix', 'fab fa-octopus-deploy', 'fab fa-odnoklassniki', 'fab fa-odysee', 'fab fa-old-republic', 'fab fa-opencart', 'fab fa-openid', 'fab fa-opensuse', 'fab fa-opera', 'fab fa-optin-monster', 'fab fa-orcid', 'fab fa-osi', 'fab fa-padlet', 'fab fa-page4', 'fab fa-pagelines', 'fab fa-palfed', 'fab fa-patreon', 'fab fa-paypal', 'fab fa-perbyte', 'fab fa-periscope', 'fab fa-phabricator', 'fab fa-phoenix-framework', 'fab fa-phoenix-squadron', 'fab fa-php', 'fab fa-pied-piper', 'fab fa-pied-piper-alt', 'fab fa-pied-piper-hat', 'fab fa-pied-piper-pp', 'fab fa-pinterest', 'fab fa-pinterest-p', 'fab fa-pix', 'fab fa-pixiv', 'fab fa-playstation', 'fab fa-product-hunt', 'fab fa-pushed', 'fab fa-python', 'fab fa-qq', 'fab fa-quinscape', 'fab fa-quora', 'fab fa-r-project', 'fab fa-raspberry-pi', 'fab fa-ravelry', 'fab fa-react', 'fab fa-reacteurope', 'fab fa-readme', 'fab fa-rebel', 'fab fa-red-river', 'fab fa-reddit', 'fab fa-reddit-alien', 'fab fa-redhat', 'fab fa-renren', 'fab fa-replyd', 'fab fa-researchgate', 'fab fa-resolving', 'fab fa-rev', 'fab fa-rocketchat', 'fab fa-rockrms', 'fab fa-rust', 'fab fa-safari', 'fab fa-salesforce', 'fab fa-sass', 'fab fa-schlix', 'fab fa-screenpal', 'fab fa-scribd', 'fab fa-searchengin', 'fab fa-sellcast', 'fab fa-sellsy', 'fab fa-servicestack', 'fab fa-shirtsinbulk', 'fab fa-shoelace', 'fab fa-shopify', 'fab fa-shopware', 'fab fa-signal-messenger', 'fab fa-simplybuilt', 'fab fa-sistrix', 'fab fa-sith', 'fab fa-sitrox', 'fab fa-sketch', 'fab fa-skyatlas', 'fab fa-skype', 'fab fa-slack', 'fab fa-slideshare', 'fab fa-snapchat', 'fab fa-soundcloud', 'fab fa-sourcetree', 'fab fa-space-awesome', 'fab fa-speakap', 'fab fa-speaker-deck', 'fab fa-spotify', 'fab fa-square-behance', 'fab fa-square-bluesky', 'fab fa-square-dribbble', 'fab fa-square-facebook', 'fab fa-square-font-awesome', 'fab fa-square-font-awesome-stroke', 'fab fa-square-git', 'fab fa-square-github', 'fab fa-square-gitlab', 'fab fa-square-google-plus', 'fab fa-square-hacker-news', 'fab fa-square-instagram', 'fab fa-square-js', 'fab fa-square-lastfm', 'fab fa-square-letterboxd', 'fab fa-square-odnoklassniki', 'fab fa-square-pied-piper', 'fab fa-square-pinterest', 'fab fa-square-reddit', 'fab fa-square-snapchat', 'fab fa-square-steam', 'fab fa-square-threads', 'fab fa-square-tumblr', 'fab fa-square-twitter', 'fab fa-square-upwork', 'fab fa-square-viadeo', 'fab fa-square-vimeo', 'fab fa-square-web-awesome', 'fab fa-square-web-awesome-stroke', 'fab fa-square-whatsapp', 'fab fa-square-x-twitter', 'fab fa-square-xing', 'fab fa-square-youtube', 'fab fa-squarespace', 'fab fa-stack-exchange', 'fab fa-stack-overflow', 'fab fa-stackpath', 'fab fa-staylinked', 'fab fa-steam', 'fab fa-steam-symbol', 'fab fa-sticker-mule', 'fab fa-strava', 'fab fa-stripe', 'fab fa-stripe-s', 'fab fa-stubber', 'fab fa-studiovinari', 'fab fa-stumbleupon', 'fab fa-stumbleupon-circle', 'fab fa-superpowers', 'fab fa-supple', 'fab fa-suse', 'fab fa-swift', 'fab fa-symfony', 'fab fa-teamspeak', 'fab fa-telegram', 'fab fa-tencent-weibo', 'fab fa-the-red-yeti', 'fab fa-themeco', 'fab fa-themeisle', 'fab fa-think-peaks', 'fab fa-threads', 'fab fa-tiktok', 'fab fa-trade-federation', 'fab fa-trello', 'fab fa-tumblr', 'fab fa-twitch', 'fab fa-twitter', 'fab fa-typo3', 'fab fa-uber', 'fab fa-ubuntu', 'fab fa-uikit', 'fab fa-umbraco', 'fab fa-uncharted', 'fab fa-uniregistry', 'fab fa-unity', 'fab fa-unsplash', 'fab fa-untappd', 'fab fa-ups', 'fab fa-upwork', 'fab fa-usb', 'fab fa-usps', 'fab fa-ussunnah', 'fab fa-vaadin', 'fab fa-viacoin', 'fab fa-viadeo', 'fab fa-viber', 'fab fa-vimeo', 'fab fa-vimeo-v', 'fab fa-vine', 'fab fa-vk', 'fab fa-vnv', 'fab fa-vuejs', 'fab fa-watchman-monitoring', 'fab fa-waze', 'fab fa-web-awesome', 'fab fa-webflow', 'fab fa-weebly', 'fab fa-weibo', 'fab fa-weixin', 'fab fa-whatsapp', 'fab fa-whmcs', 'fab fa-wikipedia-w', 'fab fa-windows', 'fab fa-wirsindhandwerk', 'fab fa-wix', 'fab fa-wizards-of-the-coast', 'fab fa-wodu', 'fab fa-wolf-pack-battalion', 'fab fa-wordpress', 'fab fa-wordpress-simple', 'fab fa-wpbeginner', 'fab fa-wpexplorer', 'fab fa-wpforms', 'fab fa-wpressr', 'fab fa-x-twitter', 'fab fa-xbox', 'fab fa-xing', 'fab fa-y-combinator', 'fab fa-yahoo', 'fab fa-yammer', 'fab fa-yandex', 'fab fa-yandex-international', 'fab fa-yarn', 'fab fa-yelp', 'fab fa-yoast', 'fab fa-youtube', 'fab fa-zhihu']
            };

            const ICON_DISPLAY_CAP = 300;
            const filteredIcons = computed(() => {
                const icons = FA_GROUPS[activeIconTab.value] || [];
                const q = searchIconQuery.value.trim().toLowerCase();
                const list = q ? icons.filter(icon => icon.toLowerCase().includes(q)) : icons;
                // Cap the rendered list so the picker stays snappy with ~1400 solid icons;
                // searching narrows the full set, so every icon is still reachable.
                return list.slice(0, ICON_DISPLAY_CAP);
            });
            const iconTabCount = computed(() => (FA_GROUPS[activeIconTab.value] || []).length);

            const selectIcon = (target, icon, key = 'icon') => {
                target[key] = icon;
            };

            const clearColorField = (target, colorKey, opacityKey = null) => {
                if (target) {
                    target[colorKey] = '';
                    if (opacityKey) target[opacityKey] = 1;
                }
            };

            const loadBuilderFont = (fontFamily) => {
                if (!fontFamily || fontFamily === 'inherit') return;
                const match = fontFamily.match(/['"]?([^'",$]+)/);
                const name = match ? match[1].trim() : fontFamily.trim();
                if (!name || name === 'inherit') return;
                const linkId = 'bfont-' + name.replace(/\s+/g, '-').toLowerCase();
                if (document.getElementById(linkId)) return;
                const link = document.createElement('link');
                link.id   = linkId;
                link.rel  = 'stylesheet';
                link.href = `https://fonts.googleapis.com/css2?family=${name.replace(/\s+/g, '+')}:wght@100;200;300;400;500;600;700;800;900&display=swap`;
                document.head.appendChild(link);
            };

            const availableElements = [
                { type: 'accordion', name: 'Accordion', icon: 'fa fa-list-ul' },
                { type: 'card', name: 'Card', icon: 'fa fa-th-large' },
                { type: 'counter', name: 'Counter', icon: 'fa fa-hashtag' },
                { type: 'html', name: 'HTML Block', icon: 'fa fa-code' },
                { type: 'icon_list', name: 'Icon List', icon: 'fa fa-list-check' },
                { type: 'video', name: 'Video', icon: 'fa fa-play-circle' },
                { type: 'icon_box', name: 'Icon Box', icon: 'fa fa-star-half-alt' },
                { type: 'spacer', name: 'Spacer', icon: 'fa fa-arrows-alt-v' },
                { type: 'tabs', name: 'Tabs', icon: 'fa fa-folder' },
                { type: 'title', name: 'Title', icon: 'fa fa-heading' },
                { type: 'breadcrumb', name: 'Bread Crumb', icon: 'fa fa-angle-right' },
                { type: 'star_rating', name: 'Star Rating', icon: 'fa fa-star' },
                { type: 'gallery', name: 'Gallery', icon: 'fa fa-images' },
                { type: 'ticker', name: 'Ticker', icon: 'fa fa-rss' },
            ];
            // Advanced elements — available only with a builder_pro license. The palette shows
            // them with a lock badge; adding one without a license shows an upgrade prompt.
            const proElementTypes = ['accordion', 'counter', 'tabs', 'gallery', 'ticker', 'breadcrumb', 'star_rating', 'html'];
            const isElementPro   = (type) => proElementTypes.includes(type);
            const elementLocked  = (type) => isElementPro(type) && !window.falconBuilderPro;
            if (postCardMode.value || layoutMode.value) {
                availableElements.push({ type: 'post_content', name: 'Content', icon: 'fa fa-paragraph' });
                availableElements.push({ type: 'post_meta', name: 'Post Meta', icon: 'fa fa-tags' });
                // Product Meta — renders product price/SKU/stock when the current post is a
                // product, nothing otherwise. Available in post cards and layout sections.
                availableElements.push({ type: 'product_meta', name: 'Product Meta', icon: 'fa fa-tag' });
            }

            const filteredColumnLayouts = computed(() => {
                if (!searchColumnQuery.value) return columnLayouts;
                const query = searchColumnQuery.value.toLowerCase();
                return columnLayouts.filter(layout => layout.label.toLowerCase().includes(query) || layout.config.toLowerCase().includes(query));
            });

            const filteredNestedColumnLayouts = computed(() => {
                if (!searchElementQuery.value) return columnLayouts;
                const query = searchElementQuery.value.toLowerCase();
                return columnLayouts.filter(layout => layout.label.toLowerCase().includes(query) || layout.config.toLowerCase().includes(query));
            });

            const filteredAvailableElements = computed(() => {
                const query = searchElementQuery.value.toLowerCase();
                const list = !query
                    ? [...availableElements]
                    : availableElements.filter(el =>
                        (el.name && el.name.toLowerCase().includes(query)) ||
                        el.type.toLowerCase().includes(query)
                    );
                return list.sort((a, b) => (a.name || a.type).localeCompare(b.name || b.type));
            });

            // Dynamic Custom Elements Registration
            const customElements = @json($customElements ?? []);
            if (Object.keys(customElements).length > 0) {
                Object.values(customElements).forEach(el => {
                    availableElements.push(el);
                });
            }
            // Expose to converter so it can handle custom shortcode tags
            window.falconCustomElements = customElements;

            // Canvas preview helpers for custom elements
            const _CE_TEXT_TYPES  = ['text', 'textarea', 'textfield'];
            const _CE_COLOR_TYPES = ['color', 'colorpicker', 'colorpickeralpha'];
            const _CE_IMAGE_TYPES = ['image', 'media'];

            // Derive the storage key for a param (handles array param_name sugar via _ceResolveName).
            const customParamKey = (param) => {
                if (Array.isArray(param.param_name)) return _ceResolveName(param).key;
                return param.param_name || (param.heading ? param.heading.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'') : null);
            };

            // Array param_name sugar → derive {key, applyTo, applyAs} (suffix decides property, prefix decides target)
            const _CE_SUFFIX_AS = [['_hover_color','hover_color'],['_hover_bg','hover_bg'],['_color','color'],['_bg','bg'],['_typo',''],['_pad','padding'],['_margin','margin']];
            const _ceStripBase = (k) => {
                for (const [suf, as] of _CE_SUFFIX_AS) { if (k.endsWith(suf)) return [k.slice(0, -suf.length), as]; }
                return [k, ''];
            };
            const _ceSlug = (t) => t.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            const _ceResolveName = (p, contentKeys = []) => {
                const pn = p.param_name;
                if (Array.isArray(pn) && pn.length) {
                    const [b0, as0] = _ceStripBase(pn[0]);
                    if (b0 !== pn[0]) {
                        // suffixed entries → first is storage key, strip suffix for targets
                        return { key: pn[0], applyTo: p.apply_to || pn.map(k => _ceStripBase(k)[0]), applyAs: p.apply_as || as0 };
                    }
                    // bare target names → synthesise a non-colliding storage key
                    let key = p.heading ? _ceSlug(p.heading) : ('cf_' + pn.join('_'));
                    while (contentKeys.includes(key)) key += '_x';
                    const nt = (p.type === 'colorpickeralpha' || p.type === 'colorpicker') ? 'color' : p.type;
                    return { key, applyTo: p.apply_to || pn, applyAs: p.apply_as || (nt === 'dimensions' ? 'padding' : (nt === 'color' ? 'color' : '')) };
                }
                return { key: customParamKey(p), applyTo: p.apply_to, applyAs: p.apply_as };
            };

            // Type-aware default value for a param
            const customParamDefault = (param) => {
                if (param.value !== undefined) return param.value;
                if (param.type === 'checkbox' || param.type === 'repeater') return [];
                if (param.type === 'toggle') return false;
                return '';
            };

            const getCustomElementPreviewColor = (el) => {
                const def = customElements[el.type];
                if (!def) return '';
                for (const p of (def.params || [])) {
                    if (_CE_COLOR_TYPES.includes(p.type)) return el.settings[p.param_name] || '';
                }
                for (const [k, f] of Object.entries(def.fields || {})) {
                    if (_CE_COLOR_TYPES.includes(f.type || '')) return el.settings[k] || '';
                }
                return '';
            };

            // Returns array of { type: 'text'|'image', value, color } for all non-empty general fields
            const getCustomElementPreviewFields = (el) => {
                const def = customElements[el.type];
                if (!def) return [];
                const color  = getCustomElementPreviewColor(el);
                const result = [];
                const _autoKey = (p) => p.param_name || (p.heading ? p.heading.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'') : null);
                const allParams = def.params
                    ? def.params.map(p => ({ key: _autoKey(p), type: p.type, tab: p.tab || 'general' }))
                    : Object.entries(def.fields || {}).map(([k, f]) => ({ key: k, type: f.type || 'text', tab: f.tab || 'general' }));

                for (const p of allParams) {
                    const val = el.settings[p.key];
                    if (val === undefined || val === null || val === '') continue;
                    if (_CE_IMAGE_TYPES.includes(p.type)) {
                        result.push({ type: 'image', value: String(val) });
                    } else if (_CE_TEXT_TYPES.includes(p.type) && p.tab !== 'design') {
                        result.push({ type: 'text', value: String(val), color, primary: result.filter(r => r.type === 'text').length === 0 });
                    }
                    if (result.length >= 4) break;
                }
                return result;
            };

            const getCustomElementPreviewText = (el) => {
                const fields = getCustomElementPreviewFields(el);
                const tf = fields.find(f => f.type === 'text');
                return tf ? tf.value : '';
            };

            // ── Convention-based live canvas renderer for custom elements ──────────────
            // Content fields are rendered; design fields relate by prefix:
            //   {base}_color, {base}_bg, {base}_typo_*, {base}_pad_*, {base}_margin_*, {base}_align
            const _CE_CONTENT_TYPES = ['text','textfield','textarea','wysiwyg','image','media','icon','button','repeater','date','number','slider','select','radio','checkbox','url','link'];
            // A field renders as content unless it's a design modifier (an align select/radio, or an apply_to/array relation).
            const _ceIsContent = (f) => _CE_CONTENT_TYPES.includes(f.type)
                && !((f.type === 'select' || f.type === 'radio') && (f.key || '').endsWith('_align'))
                && !f.applyTo;

            const _ceFields = (def) => {
                if (def.params && def.params.length) {
                    // pre-scan content-field keys so bare-target arrays avoid colliding with them
                    const contentKeys = def.params
                        .filter(p => _CE_CONTENT_TYPES.includes(p.type))
                        .map(p => (Array.isArray(p.param_name) ? p.param_name[0] : p.param_name) || (p.heading ? _ceSlug(p.heading) : ''))
                        .filter(Boolean);
                    return def.params.map(p => {
                        const r = _ceResolveName(p, contentKeys);
                        return { key: r.key, type: p.type, raw: p, applyTo: r.applyTo, applyAs: r.applyAs };
                    });
                }
                if (def.fields) return Object.entries(def.fields).map(([k, f]) => ({ key: k, type: f.type, raw: f, applyTo: f.apply_to, applyAs: f.apply_as }));
                return [];
            };

            const _ceUnit = (v) => (v !== undefined && v !== null && v !== '' && /^-?\d+(\.\d+)?$/.test(String(v))) ? v + 'px' : v;

            // typography CSS from a prefix (reads {prefix}_family/_size/_weight/_line_height/_letter_spacing/_transform)
            const _ceTypo = (s, tp) => {
                const o = {};
                if (s[tp + '_family'] && s[tp + '_family'] !== 'inherit') o.fontFamily = s[tp + '_family'];
                if (s[tp + '_size'])           o.fontSize = _ceUnit(s[tp + '_size']);
                if (s[tp + '_weight'])         o.fontWeight = s[tp + '_weight'];
                if (s[tp + '_line_height'])    o.lineHeight = s[tp + '_line_height'];
                if (s[tp + '_letter_spacing'] !== undefined && s[tp + '_letter_spacing'] !== '') o.letterSpacing = _ceUnit(s[tp + '_letter_spacing']);
                if (s[tp + '_transform'] && s[tp + '_transform'] !== 'none') o.textTransform = s[tp + '_transform'];
                return o;
            };

            // T/R/B/L edges from a prefix (reads {prefix}_top etc, responsive-aware) → CSS shorthand or null
            const _ceEdges = (s, prefix) => {
                const e = ['top','right','bottom','left'].map(side => {
                    const v = getResponsiveVal(s, prefix + '_' + side, device.value);
                    if (v === undefined || v === null || v === '') return null;
                    const u = getResponsiveVal(s, prefix + '_' + side + '_unit', device.value) || 'px';
                    return v + u;
                });
                return e.some(x => x !== null) ? e.map(x => x || '0').join(' ') : null;
            };

            // Build a CSS style object for a content field `base` from its prefix-related modifiers.
            const _ceStyle = (s, base) => {
                const st = {};
                if (s[base + '_color']) st.color = s[base + '_color'];
                if (s[base + '_bg'])    st.backgroundColor = s[base + '_bg'];
                if (s[base + '_align']) st.textAlign = s[base + '_align'];
                Object.assign(st, _ceTypo(s, base + '_typo'));
                const p = _ceEdges(s, base + '_pad');    if (p) st.padding = p;
                const m = _ceEdges(s, base + '_margin'); if (m) st.margin = m;
                return st;
            };

            // Hover styles for a base → {color?, backgroundColor?} from {base}_hover_color / {base}_hover_bg
            const _ceHover = (s, base) => {
                const h = {};
                if (s[base + '_hover_color']) h.color = s[base + '_hover_color'];
                if (s[base + '_hover_bg'])    h.backgroundColor = s[base + '_hover_bg'];
                return h;
            };

            // Contribution of a design field (with apply_to) to a target → { style:{}, hover:{} }
            const _ceContrib = (s, f) => {
                const out = { style: {}, hover: {} };
                const as = f.applyAs || (f.type === 'dimensions' ? 'padding' : (['color','colorpicker','colorpickeralpha'].includes(f.type) ? 'color' : ''));
                if (['color','colorpicker','colorpickeralpha'].includes(f.type)) {
                    const v = s[f.key]; if (!v) return out;
                    if (as === 'bg')              out.style.backgroundColor = v;
                    else if (as === 'hover_color') out.hover.color = v;
                    else if (as === 'hover_bg')    out.hover.backgroundColor = v;
                    else                           out.style.color = v;
                } else if (f.type === 'typography') {
                    Object.assign(out.style, _ceTypo(s, f.key));
                } else if (f.type === 'dimensions') {
                    const e = _ceEdges(s, f.key);
                    if (e) out.style[as === 'margin' ? 'margin' : 'padding'] = e;
                }
                return out;
            };

            // Detect which content base a design/modifier field targets (by type + suffix).
            const _ceModifierBase = (f) => {
                const k = f.key; if (!k) return null;
                if (['color','colorpicker','colorpickeralpha'].includes(f.type)) {
                    if (k.endsWith('_hover_color')) return k.slice(0, -12);
                    if (k.endsWith('_hover_bg'))    return k.slice(0, -9);
                    if (k.endsWith('_color'))       return k.slice(0, -6);
                    if (k.endsWith('_bg'))          return k.slice(0, -3);
                }
                if (f.type === 'typography' && k.endsWith('_typo')) return k.slice(0, -5);
                if (f.type === 'dimensions') {
                    if (k.endsWith('_pad'))    return k.slice(0, -4);
                    if (k.endsWith('_margin')) return k.slice(0, -7);
                }
                if ((f.type === 'select' || f.type === 'radio') && k.endsWith('_align')) return k.slice(0, -6);
                return null;
            };

            // Returns { wrapperStyle, items, hoverCss } for live canvas rendering.
            const getCustomElementRender = (el) => {
                const def = customElements[el.type];
                if (!def) return { wrapperStyle: {}, items: [], hoverCss: '' };
                const s = el.settings || {};
                const elId = el.id || 'x';

                // Social Icons: fixed per-platform fields → render real icon chips for any filled URL.
                if (el.type === 'social_icons' && def.fields) {
                    const box   = Math.max(0, parseInt(s.boxSize)  || 38);
                    const ic    = Math.max(0, parseInt(s.iconSize) || 18);
                    const gap   = Math.max(0, parseInt(s.gap)      || 10);
                    const shape = s.shape || 'circle';
                    const radius = shape === 'circle' ? '50%' : (shape === 'rounded' ? '8px' : '0');
                    let align = getResponsiveVal(s, 'align', device.value) || 'center';
                    if (!['flex-start','center','flex-end'].includes(align)) align = 'center';

                    const boxed = (s.boxedStyle || 'default') !== 'no';
                    const colorType = s.colorType || 'default';
                    // Readable fg for a light/dark brand bg.
                    const contrast = (hex) => {
                        let h = String(hex || '').replace('#', '');
                        if (h.length === 3) h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2];
                        if (h.length < 6) return '#ffffff';
                        const r = parseInt(h.slice(0,2),16), g = parseInt(h.slice(2,4),16), b = parseInt(h.slice(4,6),16);
                        return ((0.299*r + 0.587*g + 0.114*b) / 255) > 0.65 ? '#111111' : '#ffffff';
                    };
                    const resolve = (brand) => {
                        if (colorType === 'brand') return boxed ? { fg: contrast(brand), bg: brand } : { fg: brand, bg: 'transparent' };
                        if (colorType === 'custom') return boxed ? { fg: s.iconColor || '#ffffff', bg: s.bgColor || '#2271b1' } : { fg: s.iconColor || '#ffffff', bg: 'transparent' };
                        return boxed ? { fg: '#ffffff', bg: '#2271b1' } : { fg: '#2271b1', bg: 'transparent' };
                    };

                    // Tooltip (platform name on hover). default = top; none = off.
                    let tipPos = s.tooltipPosition || 'default';
                    if (tipPos === 'default') tipPos = 'top';
                    const tipOn = tipPos !== 'none';

                    const baseChip = {
                        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', lineHeight: '1',
                    };
                    if (boxed) { baseChip.width = box + 'px'; baseChip.height = box + 'px'; baseChip.borderRadius = radius; }
                    if (tipOn) baseChip.position = 'relative';

                    // Hover rule (shared across this element's chips). Brand keeps its own colour.
                    const hoverCls = 'lzsoc-' + elId;
                    let socHoverCss = '';
                    if (colorType === 'custom') {
                        const d = [];
                        if (boxed && s.bgHoverColor) d.push('background:' + s.bgHoverColor + ' !important');
                        if (s.iconHoverColor)        d.push('color:' + s.iconHoverColor + ' !important');
                        if (d.length) socHoverCss = '.' + hoverCls + ':hover{' + d.join(';') + '}';
                    } else if (colorType !== 'brand') {
                        socHoverCss = boxed ? '.' + hoverCls + ':hover{background:#135e96 !important;color:#fff !important}'
                                            : '.' + hoverCls + ':hover{color:#135e96 !important}';
                    }
                    // Tooltip CSS
                    if (tipOn) {
                        const place = {
                            top:    'bottom:100%;left:50%;transform:translateX(-50%) translateY(-8px)',
                            bottom: 'top:100%;left:50%;transform:translateX(-50%) translateY(8px)',
                            left:   'right:100%;top:50%;transform:translateY(-50%) translateX(-8px)',
                            right:  'left:100%;top:50%;transform:translateY(-50%) translateX(8px)',
                        }[tipPos] || 'bottom:100%;left:50%;transform:translateX(-50%) translateY(-8px)';
                        socHoverCss += '.' + hoverCls + '::after{content:attr(data-tip);position:absolute;' + place + ';background:#111;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;line-height:1.5;white-space:nowrap;opacity:0;visibility:hidden;transition:opacity .18s;pointer-events:none;z-index:30}';
                        socHoverCss += '.' + hoverCls + ':hover::after{opacity:1;visibility:visible}';
                    }

                    const socItems = [];
                    Object.entries(def.fields).forEach(([k, f]) => {
                        if (!f.social_icon) return;
                        if (!s[k] || !String(s[k]).trim()) return;
                        const c = resolve(f.social_color || '#2271b1');
                        const style = Object.assign({ color: c.fg }, baseChip);
                        if (boxed) style.background = c.bg;
                        socItems.push({ kind: 'social', icon: f.social_icon, style, hoverClass: hoverCls,
                                        iconStyle: { fontSize: ic + 'px' }, tip: tipOn ? (f.social_label || '') : '' });
                    });
                    const wrapperStyle = {
                        display: 'flex', flexWrap: 'wrap', gap: gap + 'px', justifyContent: align,
                    };
                    // Responsive margin (dimensions field) for the current device.
                    ['top','right','bottom','left'].forEach(side => {
                        const v = getResponsiveVal(s, 'margin_' + side, device.value);
                        if (v === undefined || v === null || v === '') return;
                        const u = getResponsiveVal(s, 'margin_' + side + '_unit', device.value) || 'px';
                        wrapperStyle['margin' + side.charAt(0).toUpperCase() + side.slice(1)] = v + u;
                    });
                    return { wrapperStyle, wrapperHoverClass: '', items: socItems, hoverCss: socHoverCss };
                }

                // Advanced Search: render a live search-bar mockup in the canvas.
                if (el.type === 'advanced_search') {
                    const esc = (v) => String(v == null ? '' : v).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    const accent = s.accentColor || '#2271b1';
                    const bg     = s.bgColor || '#ffffff';
                    const txt    = s.textColor || '#1d2327';
                    const phc    = s.placeholderColor || '#9ca3af';
                    const ddTxt  = s.dropdownTextColor || '#1d2327';
                    const ddBg   = s.dropdownBgColor || '#ffffff';
                    const bc     = s.borderColor || '#e5e7eb';
                    const h      = Math.max(28, parseInt(s.height) || 46);
                    const rad    = Math.max(0, parseInt(s.borderRadius) || 6);
                    const mw     = Math.max(0, parseInt(s.maxWidth) || 0);
                    const align  = s.align || 'flex-start';
                    const ph     = s.placeholder || 'Search...';
                    const showBtn = (s.showButton !== false);
                    const btnTxt = s.buttonText || 'Search';
                    let bar = '<div style="display:flex;align-items:stretch;width:100%;' + (mw > 0 ? 'max-width:' + mw + 'px;' : '') + 'background:' + bg + ';border:1px solid ' + bc + ';border-radius:' + rad + 'px;overflow:hidden;">';
                    if (s.enableCategoryDropdown) {
                        bar += '<div style="display:flex;align-items:center;gap:6px;padding:0 12px;height:' + h + 'px;border-right:1px solid ' + bc + ';background:' + ddBg + ';color:' + ddTxt + ';font-size:13px;white-space:nowrap;">All Categories <i class="fa fa-chevron-down" style="font-size:9px;"></i></div>';
                    }
                    bar += '<div style="flex:1;display:flex;align-items:center;padding:0 14px;height:' + h + 'px;font-size:14px;"><span style="color:' + txt + ';font-weight:600;margin-right:1px;">|</span><span style="color:' + phc + ';">' + esc(ph) + '</span></div>';
                    if (showBtn) {
                        bar += '<div style="display:flex;align-items:center;background:' + accent + ';color:#fff;padding:0 18px;height:' + h + 'px;font-size:14px;font-weight:600;white-space:nowrap;">' + esc(btnTxt) + '</div>';
                    } else {
                        bar += '<div style="display:flex;align-items:center;color:' + accent + ';padding:0 14px;height:' + h + 'px;"><i class="fa fa-magnifying-glass"></i></div>';
                    }
                    bar += '</div>';
                    const html = '<div style="display:flex;justify-content:' + align + ';width:100%;">' + bar + '</div>';
                    return { wrapperStyle: { width: '100%' }, wrapperHoverClass: '', items: [{ kind: 'html', value: html, style: { width: '100%' }, hoverClass: '' }], hoverCss: '' };
                }

                const fields = _ceFields(def);
                const contentKeys = fields.filter(_ceIsContent).map(f => f.key);

                let hoverCss = '';
                let _hcSeq = 0;
                const _hoverClass = (hv) => {
                    const decl = [];
                    if (hv.color)           decl.push('color:' + hv.color + ' !important');
                    if (hv.backgroundColor) decl.push('background-color:' + hv.backgroundColor + ' !important');
                    if (!decl.length) return '';
                    const cls = 'lzceh-' + elId + '-' + (_hcSeq++);
                    hoverCss += '.' + cls + ':hover{' + decl.join(';') + '}';
                    return cls;
                };

                // Explicit multi-target relations: a design field with `apply_to` styles one or more content fields.
                const explicit = {};
                fields.forEach(f => {
                    if (!f.applyTo) return;
                    const targets = Array.isArray(f.applyTo) ? f.applyTo : [f.applyTo];
                    const c = _ceContrib(s, f);
                    targets.forEach(t => {
                        if (!explicit[t]) explicit[t] = { style: {}, hover: {} };
                        Object.assign(explicit[t].style, c.style);
                        Object.assign(explicit[t].hover, c.hover);
                    });
                });

                const items = [];
                fields.forEach(f => {
                    if (!_ceIsContent(f)) return;
                    const style = Object.assign(_ceStyle(s, f.key), explicit[f.key] ? explicit[f.key].style : {});
                    const hover = Object.assign(_ceHover(s, f.key), explicit[f.key] ? explicit[f.key].hover : {});
                    const hoverClass = _hoverClass(hover);
                    if (f.type === 'repeater') {
                        let subDefs = f.raw.fields || f.raw.params || [];
                        // Sub-fields may be an array (params style) or an object keyed by name (fields style).
                        if (!Array.isArray(subDefs)) subDefs = Object.keys(subDefs).map(k => Object.assign({ param_name: k }, subDefs[k]));
                        const subFields = subDefs.map(sp => ({ key: customParamKey(sp), type: sp.type }));
                        items.push({ kind: 'repeater', key: f.key, style, hoverClass, rows: Array.isArray(s[f.key]) ? s[f.key] : [], subFields });
                    } else {
                        // checkbox → comma list; everything else → its scalar value
                        const v = (f.type === 'checkbox') ? (Array.isArray(s[f.key]) ? s[f.key].join(', ') : '') : s[f.key];
                        items.push({ kind: f.type, key: f.key, value: v, style, hoverClass });
                    }
                });

                // Orphan prefix modifiers (no matching content field, no apply_to) → apply to wrapper
                const wrapperStyle = {};
                let wrapperHoverClass = '';
                fields.forEach(f => {
                    if (f.applyTo) return;
                    const base = _ceModifierBase(f);
                    if (base && !contentKeys.includes(base)) {
                        Object.assign(wrapperStyle, _ceStyle(s, base));
                        const hc = _hoverClass(_ceHover(s, base));
                        if (hc) wrapperHoverClass = hc;
                    }
                });

                return { wrapperStyle, wrapperHoverClass, items, hoverCss };
            };

            // Conditional field visibility for custom elements.
            // cond can be a single object {field, value, operator?} or an array of them (AND logic).
            const customFieldVisible = (settings, cond) => {
                if (!cond) return true;
                const checks = Array.isArray(cond) ? cond : [cond];
                return checks.every(c => {
                    if (!c || !c.field) return true;
                    const actual   = settings ? settings[c.field] : undefined;
                    const expected = c.value;
                    switch (c.operator || '==') {
                        case '==':       return actual == expected;
                        case '!=':       return actual != expected;
                        case '>':        return Number(actual) >  Number(expected);
                        case '<':        return Number(actual) <  Number(expected);
                        case '>=':       return Number(actual) >= Number(expected);
                        case '<=':       return Number(actual) <= Number(expected);
                        case 'in':       return Array.isArray(expected) && expected.includes(actual);
                        case 'not_in':   return Array.isArray(expected) && !expected.includes(actual);
                        case 'contains': return Array.isArray(actual) && actual.includes(expected);
                        case 'truthy':   return !!actual;
                        case 'falsy':    return !actual;
                        default:         return actual == expected;
                    }
                });
            };

            // Initialize layout
            onMounted(() => {
                const rawContent = @json($builderContent ?? $post->content);
                try {
                    if (rawContent) {
                        let parsed;
                        if (typeof rawContent === 'string') {
                            const trimmed = rawContent.trim();
                            if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
                                try {
                                    parsed = JSON.parse(trimmed);
                                } catch (parseError) {
                                    console.error('Layout JSON parse error:', parseError);
                                    parsed = [];
                                }
                            } else {
                                // Content is a plain string (raw HTML/text from the rich editor),
                                // NOT builder JSON. Preserve it by loading it into a Text Block element
                                // so switching an existing page/post/product/CPT into the builder does
                                // not wipe the existing rich-editor content. (Builder JSON/shortcode is
                                // handled by the branch above and is unaffected.)
                                if (trimmed !== '') {
                                    parsed = [{
                                        id: uid(),
                                        type: 'container',
                                        settings: { visibility: { mobile: true, tablet: true, desktop: true } },
                                        columns: [{
                                            id: uid(), basis: '100%', basis_tablet: null, basis_mobile: null,
                                            settings: makeColumnSettings(),
                                            elements: [{
                                                id: Date.now(),
                                                type: 'text_block',
                                                settings: { content: rawContent, visibility: { mobile: true, tablet: true, desktop: true } }
                                            }]
                                        }]
                                    }];
                                } else {
                                    parsed = [];
                                }
                            }
                        } else {
                            parsed = rawContent;
                        }

                        const migrateLayout = (items) => {
                            if (!items || !Array.isArray(items)) return;
                            items.forEach(item => {
                                // 1. Migrate Rows / Containers
                                if (item.type === 'row' || item.type === 'container') {
                                    if (!item.settings) item.settings = {};
                                    if (!item.settings.visibility) item.settings.visibility = { mobile: true, tablet: true, desktop: true };
                                    if (item.settings.contentWidth === undefined) item.settings.contentWidth = 'site';
                                    if (item.settings.height === undefined) item.settings.height = 'auto';
                                    if (item.settings.customHeight === undefined) item.settings.customHeight = '';
                                    if (item.settings.alignItems === undefined) item.settings.alignItems = 'stretch';
                                    if (item.settings.alignContent === undefined) item.settings.alignContent = 'flex-start';
                                    if (item.settings.justifyContent === undefined) item.settings.justifyContent = 'flex-start';
                                    if (item.settings.flexWrap === undefined) item.settings.flexWrap = 'wrap';
                                    if (item.settings.columnGap === undefined) item.settings.columnGap = '';
                                    if (item.settings.htmlTag === undefined) item.settings.htmlTag = 'div';
                                }

                                // 2. Migrate Columns
                                if (item.columns && Array.isArray(item.columns)) {
                                    const total = item.columns.length;
                                    item.columns.forEach(col => {
                                        if (!col.basis) col.basis = (100 / total) + '%';
                                        if (col.basis_tablet === undefined) col.basis_tablet = null;
                                        if (col.basis_mobile === undefined) col.basis_mobile = null;
                                        if (!col.settings) col.settings = makeColumnSettings();
                                        
                                        // Merge defaults for missing column settings
                                        Object.entries(makeColumnSettings()).forEach(([k, v]) => {
                                            if (col.settings[k] === undefined) col.settings[k] = v;
                                        });

                                        if (col.elements) migrateLayout(col.elements);
                                    });
                                }

                                // 3. Migrate Elements
                                if (item.type && item.type !== 'row' && item.type !== 'container') {
                                    if (!item.settings) item.settings = {};
                                    if (!item.settings.visibility) item.settings.visibility = { mobile: true, tablet: true, desktop: true };

                                    // Initialize transient UI state (not saved)
                                    if (item.type === 'menu') {
                                        if (item._mobileMenuOpen === undefined) item._mobileMenuOpen = false;
                                        if (item._hoveredIdx === undefined) item._hoveredIdx = null;
                                        if (item._hoveredSubIdx === undefined) item._hoveredSubIdx = null;
                                        if (item._hoveredGSubIdx === undefined) item._hoveredGSubIdx = null;
                                    }

                                    // Apply defaults for missing settings from custom element definitions
                                    const type = item.type;
                                    const customDef = customElements[type] || Object.values(customElements).find(e => e.type === type);
                                    if (customDef) {
                                        // Old fields format: { key: { default, type, ... } }
                                        if (customDef.fields) {
                                            Object.entries(customDef.fields).forEach(([key, field]) => {
                                                if (field.default !== undefined && item.settings[key] === undefined) {
                                                    item.settings[key] = field.default;
                                                }
                                            });
                                        }
                                        // New params format (Avada-style): [{ param_name, value, ... }]
                                        if (customDef.params) {
                                            customDef.params.forEach(param => {
                                                const key = customParamKey(param);
                                                if (key && item.settings[key] === undefined) {
                                                    item.settings[key] = customParamDefault(param);
                                                }
                                            });
                                        }
                                    }
                                }
                            });
                        };

                        if (Array.isArray(parsed)) {
                            migrateLayout(parsed);
                            layout.value = parsed;
                        } else {
                            layout.value = [];
                        }
                    }
                } catch (e) {
                    console.error('Failed to parse layout', e);
                    layout.value = [];
                }
                // Enable dirty tracking after initial load settles (setTimeout = macro-task, runs after Vue's watcher micro-tasks)
                setTimeout(() => { 
                    _trackLayoutDirty = true; 
                    console.log('Builder initialized, scanning for fonts...');
                    
                    // Recursively load all fonts used in the layout
                    const scanForFonts = (items) => {
                        if (!items || !Array.isArray(items)) return;
                        items.forEach(item => {
                            // Check if it's an element with fontFamily
                            if (item.settings && item.settings.fontFamily && item.settings.fontFamily !== 'inherit') {
                                console.log('Loading font for element:', item.settings.fontFamily);
                                loadBuilderFont(item.settings.fontFamily);
                            }
                            
                            // Check columns (for containers or rows)
                            if (item.columns && Array.isArray(item.columns)) {
                                item.columns.forEach(col => {
                                    // Columns themselves might have fonts in the future, but for now we check their elements
                                    if (col.elements) scanForFonts(col.elements);
                                });
                            }

                            // Check nested elements (if any other type has them)
                            if (item.elements && Array.isArray(item.elements)) {
                                scanForFonts(item.elements);
                            }
                        });
                    };

                    scanForFonts(layout.value);

                    // Auto-fetch previews for all card elements so canvas shows correct post type after reload
                    const scanForCards = (items) => {
                        if (!Array.isArray(items)) return;
                        items.forEach(item => {
                            if (item.type === 'card') fetchCardPreview(item);
                            if (item.columns) item.columns.forEach(col => scanForCards(col.elements || []));
                            if (item.elements) scanForCards(item.elements);
                        });
                    };
                    scanForCards(layout.value);

                    // Fetch global sections and sync columns from store (so edits on other pages are reflected)
                    fetchGlobalSections().then(syncGlobalColumnsFromStore);

                    // Initialize last saved layout for dirty tracking
                    lastSavedLayout = serializedLayout.value;

                    // Push initial snapshot so Ctrl+Z can undo the very first action
                    _historyStack.splice(0);
                    _historyStack.push(JSON.parse(JSON.stringify(layout.value)));
                    historyIndex.value = 0;
                }, 100);
            });

            watch(serializedLayout, (newVal) => {
                if (_trackLayoutDirty) {
                    isDirty.value = (newVal !== lastSavedLayout);
                }
            });

            watch(layout, () => {
                if (_isUndoRedo) return;
                if (isDragging.value) return; // skip per-pixel drag events; stopDrag pushes one snapshot
                _historyStack.splice(historyIndex.value + 1);
                _historyStack.push(JSON.parse(JSON.stringify(layout.value)));
                if (_historyStack.length > MAX_HISTORY) _historyStack.shift();
                historyIndex.value = _historyStack.length - 1;
            }, { deep: true, flush: 'sync' });

            // Watch isPreview to directly control layout via DOM
            watch(isPreview, (val) => {
                const wrapper = document.getElementById('lazy-builder-app');
                const sidebar = document.querySelector('.builder-sidebar');
                if (val) {
                    // Preview ON: collapse sidebar column to 0
                    wrapper.style.gridTemplateColumns = '0px 1fr';
                    wrapper.classList.add('is-preview');
                    if (sidebar) {
                        sidebar.style.display = 'none';
                        sidebar.style.width = '0';
                        sidebar.style.overflow = 'hidden';
                    }
                } else {
                    // Preview OFF: restore sidebar column
                    wrapper.style.gridTemplateColumns = '';
                    wrapper.classList.remove('is-preview');
                    if (sidebar) {
                        sidebar.style.display = '';
                        sidebar.style.width = '';
                        sidebar.style.overflow = '';
                    }
                }
            });


            const hexToRgba = (hex, opacity) => {
                if (!hex || hex === 'transparent') return 'transparent';
                if (hex.startsWith('rgba')) return hex;
                let r = 0, g = 0, b = 0;
                if (hex.length == 4) {
                    r = "0x" + hex[1] + hex[1];
                    g = "0x" + hex[2] + hex[2];
                    b = "0x" + hex[3] + hex[3];
                } else if (hex.length == 7) {
                    r = "0x" + hex[1] + hex[2];
                    g = "0x" + hex[3] + hex[4];
                    b = "0x" + hex[5] + hex[6];
                }
                const a = (opacity !== undefined && opacity !== null && opacity !== '') ? opacity : 1;
                return `rgba(${+r}, ${+g}, ${+b}, ${a})`;
            };

            const uid = () => Math.random().toString(36).substr(2, 9);

            const makeColumnSettings = (overrides = {}) => ({
                paddingTop: 0, paddingBottom: 0, paddingLeft: 0, paddingRight: 0,
                marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0,
                alignment: 'default', contentLayout: '', contentAlignH: 'flex-start', contentAlignV: 'flex-start',
                gapWidth: '', gapHeight: '', htmlTag: 'div', linkUrl: '', linkTarget: '_self',
                visibility: { mobile: true, tablet: true, desktop: true },
                cssClass: '', cssId: '', textColor: '', bgColor: 'transparent',
                bgColorOpacity: 1,
                bgType: 'color',
                hoverType: 'none',
                bgGradientStartColor: '', bgGradientEndColor: '',
                bgGradientStartOpacity: 1, bgGradientEndOpacity: 1,
                bgGradientStartPosition: 0, bgGradientEndPosition: 100,
                bgGradientType: 'linear', bgGradientAngle: 180,
                bgImage: '', bgImageDynamicSource: '', bgImageSkipLazy: false, bgImagePosition: 'center center',
                bgImageRepeat: 'no-repeat', bgImageSize: 'auto',
                bgImageFading: false, bgImageParallax: 'none', bgImageBlendMode: 'normal',
                fontSize: '', fontWeight: '', lineHeight: '', letterSpacing: '', textAlign: '',
                borderSizeTop: '', borderSizeRight: '', borderSizeBottom: '', borderSizeLeft: '',
                borderColor: '#000000', borderRadiusTopLeft: '', borderRadiusTopRight: '',
                borderRadiusBottomRight: '', borderRadiusBottomLeft: '',
                boxShadow: false, boxShadowPositionVertical: 0, boxShadowPositionHorizontal: 0,
                boxShadowBlurRadius: 0, boxShadowSpreadRadius: 0, boxShadowColor: '#000000', boxShadowStyle: 'outer',
                sticky: false, stickyDesktop: true, stickyTablet: true, stickyMobile: true,
                stickyOffset: 0, stickyZIndex: 99,
                zIndex: null, overflow: 'default',
                ...overrides
            });

            const columnModalType = ref('new'); // 'new' or 'edit'

            const clearEditingContext = () => {
                editingContext.value = { type: null, ci: null, coli: null, eli: null, ncoli: null, neli: null, tab: 'content' };
                editingCi.value = null;
                activeColi.value = null;
                activeColCi.value = null;
                activeTab.value = 'navigator';
            };

            const initRichEditors = () => {
                setTimeout(() => {
                    if (!editingElement.value) return;
                    const elId = editingElement.value.id;
                    const type = editingElement.value.type;

                    const customDef = customElements[type] || Object.values(customElements).find(e => e.type === type);
                    if (!customDef) return;

                    // Collect wysiwyg field keys from both params (new) and fields (old) formats
                    const wysiwygKeys = [];
                    const _autoKey = (p) => p.param_name || (p.heading ? p.heading.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'') : null);
                    (customDef.params || []).forEach(p => {
                        if (p.type === 'wysiwyg' || p.type === 'textarea_html') {
                            const k = _autoKey(p);
                            if (k) wysiwygKeys.push(k);
                        }
                    });
                    Object.entries(customDef.fields || {}).forEach(([key, field]) => {
                        if (field.type === 'wysiwyg' || field.type === 'textarea_html') wysiwygKeys.push(key);
                    });
                    if (!wysiwygKeys.length) return;

                    wysiwygKeys.forEach(key => {
                        const selector = `#rich-editor-${elId}-${key}`;
                        tinymce.remove(selector);
                        tinymce.init({
                            selector: selector,
                            menubar: false,
                            height: 350,
                            plugins: 'lists link code table lists wordcount preview fullscreen',
                            toolbar1: 'blocks | bold italic underline strikethrough blockquote',
                            toolbar2: 'alignleft aligncenter alignright alignjustify',
                            toolbar3: 'bullist numlist outdent indent link table | code fullscreen',
                            valid_elements: '*[*]',
                            extended_valid_elements: '*[*]',
                            entity_encoding: 'raw',
                            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size:14px; padding: 10px; }',
                            branding: false,
                            setup: (editor) => {
                                editor.on('change keyup', () => {
                                    editingElement.value.settings[key] = editor.getContent();
                                });
                                editor.on('init', () => {
                                    editor.setContent(editingElement.value.settings[key] || '');
                                });
                            }
                        });
                    });
                }, 100);
            };

            // A Pro element already on the canvas is read-only once builder Pro is locked
            // (grandfathered, after grace): it still renders on the frontend, but it can't be
            // edited, moved or dragged without upgrading. Resolves the element + checks elementLocked().
            const lockedElementAt = (type, ci, coli, eli, ncoli, neli) => {
                let el = null;
                try {
                    if (type === 'element') el = layout.value?.[ci]?.columns?.[coli]?.elements?.[eli];
                    else if (type === 'nested-element') el = layout.value?.[ci]?.columns?.[coli]?.elements?.[eli]?.columns?.[ncoli]?.elements?.[neli];
                } catch (e) {}
                return !!(el && elementLocked(el.type));
            };

            const setEditingContext = (type, ci = null, coli = null, eli = null, ncoli = null, neli = null) => {
                if ((type === 'element' || type === 'nested-element') && lockedElementAt(type, ci, coli, eli, ncoli, neli)) {
                    showToast('This is a Pro element — upgrade to Pro to edit it.', 'error');
                    return;
                }
                editingContext.value = {
                    type, ci, coli, eli, ncoli, neli,
                    tab: editingContext.value.tab || 'content'
                };
                activeTab.value = 'settings';
                
                // Reset highlights
                editingCi.value = null;
                activeColi.value = null;
                activeColCi.value = null;

                // For canvas highlighting
                if (type === 'container') {
                    editingCi.value = ci;
                } else if (type === 'column') {
                    activeColi.value = coli;
                    activeColCi.value = ci;
                } else if (type === 'nested-column') {
                    activeColi.value = ncoli;
                    activeColCi.value = eli;
                }

                if (type === 'element' || type === 'nested-element') {
                    initRichEditors();
                }
            };

            // Reset tabs to general only when switching to a different entity
            watch(editingContext, (newCtx, oldCtx) => {
                const sameEntity = newCtx.type === oldCtx?.type && newCtx.ci === oldCtx?.ci && newCtx.coli === oldCtx?.coli && newCtx.ncoli === oldCtx?.ncoli;
                if (!sameEntity) {
                    if (newCtx.type === 'container') activePanelTab.value = 'general';
                    if (newCtx.type === 'column' || newCtx.type === 'nested-column') activeColPanelTab.value = 'general';
                }
                
                // Re-init rich editors if tab changes to content
                if (newCtx.tab === 'content' && (newCtx.type === 'element' || newCtx.type === 'nested-element')) {
                    initRichEditors();
                }
            }, { deep: true });

            const selectLayout = (layoutData) => {
                const columns = layoutData.config.split('-').map(part => {
                    const [num, den] = part.split('/');
                    return {
                        id: uid(),
                        basis: ((num / den) * 100).toFixed(2) + '%',
                        basis_tablet: null,
                        basis_mobile: null,
                        settings: makeColumnSettings(),
                        elements: []
                    };
                });

                if (columnModalType.value === 'edit') {
                    // Append mode
                    const ci = columnModalTarget.value;
                    const container = layout.value[ci];

                    // Add the new columns with their correctly calculated basis
                    container.columns.push(...columns);
                    activeCi.value = ci;
                } else {

                    // Add new container
                    const newContainer = {
                        id: uid(),
                        settings: {
                            marginTop: '', marginBottom: '',
                            paddingTop: 0, paddingBottom: 0,
                            paddingLeft: 0, paddingRight: 0,
                            bgColor: 'transparent', bgColorOpacity: 1, bgType: 'color',
                            bgGradientStartColor: '', bgGradientEndColor: '',
                            bgGradientStartPosition: 0, bgGradientEndPosition: 100,
                            bgGradientType: 'linear', bgGradientAngle: 180,
                            bgImage: '', bgImageDynamicSource: '', bgImageSkipLazy: false, bgImagePosition: 'center center',
                            bgImageRepeat: 'no-repeat', bgImageSize: 'auto', bgImageFading: false,
                            bgImageParallax: 'none', bgImageBlendMode: 'normal',
                            contentWidth: 'site', height: 'auto', customHeight: '',
                            alignItems: 'stretch', alignContent: '', justifyContent: 'flex-start',
                            flexWrap: 'wrap', columnGap: '', htmlTag: 'div',
                            menuAnchor: '', visibility: { mobile: true, tablet: true, desktop: true },
                            status: 'published', cssClass: '',
                            linkColor: '',
                            borderSizeTop: '', borderSizeRight: '', borderSizeBottom: '', borderSizeLeft: '', borderColor: '#000000',
                            borderRadiusTopLeft: '', borderRadiusTopRight: '', borderRadiusBottomRight: '', borderRadiusBottomLeft: '',
                            boxShadow: false, boxShadowPositionVertical: 0, boxShadowPositionHorizontal: 0,
                            boxShadowBlurRadius: 0, boxShadowSpreadRadius: 0, boxShadowColor: '#000000', boxShadowStyle: 'outer',
                            zIndex: '', overflow: 'default'
                        },
                        columns: columns
                    };

                    if (columnModalTarget.value !== null) {
                        layout.value.splice(columnModalTarget.value, 0, newContainer);
                        activeCi.value = columnModalTarget.value;
                    } else {
                        layout.value.push(newContainer);
                        activeCi.value = layout.value.length - 1;
                    }
                }

                showColumnModal.value = false;
            };

            const addContainer = (index = null) => {
                openColumnModal(index);
            };

            const addColumn = (ci) => {
                layout.value[ci].columns.push({ id: uid(), basis: '100%', basis_tablet: null, basis_mobile: null, settings: makeColumnSettings(), elements: [] });
            };

            const addNestedColumn = (ci, coli, eli) => {
                layout.value[ci].columns[coli].elements[eli].columns.push({ id: uid(), basis: '100%', basis_tablet: null, basis_mobile: null, settings: makeColumnSettings(), elements: [] });
            };

            const shouldShowGuide = (type, ci, coli = null, eli = null, ncoli = null) => {
                if (isPreview.value) return false;
                
                // If dragging, only show what's being dragged
                if (isDragging.value) {
                    if (type === 'container' && dragCi.value === ci && !isColumnDrag.value) return true;
                    if (type === 'column' && dragCi.value === ci && dragColi.value === coli && dragEli.value === null) return true;
                    if (type === 'nested-column' && dragCi.value === ci && dragColi.value === coli && dragEli.value === eli && dragNcoli.value === ncoli) return true;
                    return false;
                }

                // If something is in edit mode (sidebar open), strictly show ONLY that type
                if (editingContext.value.type) {
                    if (type === 'container') return editingContext.value.type === 'container' && editingContext.value.ci === ci;
                    if (type === 'column') return editingContext.value.type === 'column' && editingContext.value.ci === ci && editingContext.value.coli === coli;
                    if (type === 'nested-column') return editingContext.value.type === 'nested-column' && editingContext.value.ci === ci && editingContext.value.coli === coli && editingContext.value.eli === eli && editingContext.value.ncoli === ncoli;
                    return false;
                }

                // Default: Show on hover (handled by CSS classes if this returns true)
                return true;
            };
            const cloneObject = (obj) => {
                if (!obj) return null;
                const clone = JSON.parse(JSON.stringify(obj));
                const resetIds = (item) => {
                    if (!item) return;
                    item.id = uid();
                    if (item.columns && Array.isArray(item.columns)) item.columns.forEach(col => resetIds(col));
                    if (item.elements && Array.isArray(item.elements)) item.elements.forEach(el => resetIds(el));
                };
                resetIds(clone);
                return clone;
            };


            const duplicateContainer = (ci) => {
                layout.value.splice(ci + 1, 0, cloneObject(layout.value[ci]));
            };

            const duplicateColumn = (ci, coli) => {
                layout.value[ci].columns.splice(coli + 1, 0, cloneObject(layout.value[ci].columns[coli]));
            };

            const duplicateNestedColumn = (ci, coli, eli, ncoli) => {
                const row = layout.value[ci]?.columns[coli]?.elements[eli];
                if (!row || !row.columns) return;
                row.columns.splice(ncoli + 1, 0, cloneObject(row.columns[ncoli]));
            };

            const duplicateNestedRow = (ci, coli, eli) => {
                const column = layout.value[ci]?.columns[coli];
                if (!column || !column.elements) return;
                column.elements.splice(eli + 1, 0, cloneObject(column.elements[eli]));
            };

            const duplicateElement = (ci, coli, eli) => {
                const elements = layout.value[ci]?.columns[coli]?.elements;
                if (!elements) return;
                elements.splice(eli + 1, 0, cloneObject(elements[eli]));
            };

            const duplicateNestedElement = (ci, coli, eli, ncoli, neli) => {
                const elements = layout.value[ci]?.columns[coli]?.elements[eli]?.columns[ncoli]?.elements;
                if (!elements) return;
                elements.splice(neli + 1, 0, cloneObject(elements[neli]));
            };

            // Navigator drag-to-reorder (separate from canvas drag)
            const navDragSrc  = ref(null);
            const navDragOver = ref(null);

            const navDragStart = (e, type, ci = null, coli = null, eli = null, ncoli = null, neli = null) => {
                if ((type === 'element' || type === 'nested-element') && lockedElementAt(type, ci, coli, eli, ncoli, neli)) {
                    e.preventDefault();
                    showToast('This is a Pro element — upgrade to Pro to move it.', 'error');
                    return;
                }
                navDragSrc.value = { type, ci, coli, eli, ncoli, neli };
                e.dataTransfer.effectAllowed = 'move';
            };
            const navDragEnd = () => { navDragSrc.value = null; navDragOver.value = null; };
            const navDragOverHandler = (e, type, ci = null, coli = null, eli = null, ncoli = null, neli = null) => {
                e.preventDefault();
                navDragOver.value = { type, ci, coli, eli, ncoli, neli };
            };
            const navDrop = (e, type, ci = null, coli = null, eli = null, ncoli = null, neli = null) => {
                e.preventDefault();
                const src = navDragSrc.value;
                if (!src || src.type !== type) { navDragEnd(); return; }
                if (type === 'container' && src.ci !== ci) {
                    const [item] = layout.value.splice(src.ci, 1);
                    layout.value.splice(ci, 0, item);
                } else if (type === 'column' && src.ci === ci && src.coli !== coli) {
                    const cols = layout.value[ci].columns;
                    const [item] = cols.splice(src.coli, 1);
                    cols.splice(coli, 0, item);
                } else if (type === 'element' && src.ci === ci && src.coli === coli && src.eli !== eli) {
                    const els = layout.value[ci].columns[coli].elements;
                    const [item] = els.splice(src.eli, 1);
                    els.splice(eli, 0, item);
                } else if (type === 'nested-column' && src.ci === ci && src.coli === coli && src.eli === eli && src.ncoli !== ncoli) {
                    const cols = layout.value[ci].columns[coli].elements[eli].columns;
                    const [item] = cols.splice(src.ncoli, 1);
                    cols.splice(ncoli, 0, item);
                } else if (type === 'nested-element' && src.ci === ci && src.coli === coli && src.eli === eli && src.ncoli === ncoli && src.neli !== neli) {
                    const els = layout.value[ci].columns[coli].elements[eli].columns[ncoli].elements;
                    const [item] = els.splice(src.neli, 1);
                    els.splice(neli, 0, item);
                }
                navDragEnd();
            };

            // Canvas Right-Click Context Menu
            const ctxMenu = ref({ show: false, x: 0, y: 0, type: null, ci: null, coli: null, eli: null, ncoli: null, neli: null });
            // localStorage-backed clipboard — initialized synchronously from storage so cross-page paste works
            const _CLIP_KEY = 'lazy_builder_clipboard';
            const _clipInit = (() => { try { const s = localStorage.getItem(_CLIP_KEY); return s ? JSON.parse(s) : null; } catch(e) { return null; } })();
            const ctxClipboard = ref(_clipInit); // { type, data }

            const ctxMenuTitle = computed(() => {
                const m = ctxMenu.value;
                if (!m.type) return '';
                if (m.type === 'container') return 'Container';
                if (m.type === 'column') return 'Column';
                if (m.type === 'nested-row') return 'Nested Row';
                if (m.type === 'nested-column') return 'Nested Column';
                if (m.type === 'element') {
                    const el = layout.value[m.ci]?.columns[m.coli]?.elements[m.eli];
                    if (!el) return 'Element';
                    return (el.type === 'text_block' || el.type === 'special_text') ? 'Text Block' : el.type.replace(/_/g, ' ');
                }
                if (m.type === 'nested-element') {
                    const el = layout.value[m.ci]?.columns[m.coli]?.elements[m.eli]?.columns[m.ncoli]?.elements[m.neli];
                    if (!el) return 'Element';
                    return (el.type === 'text_block' || el.type === 'special_text') ? 'Text Block' : el.type.replace(/_/g, ' ');
                }
                return m.type;
            });

            const openCtxMenu = (e, type, ci = null, coli = null, eli = null, ncoli = null, neli = null) => {
                e.preventDefault();
                e.stopPropagation();
                const menuW = 220, menuH = 360;
                const x = (e.clientX + menuW > window.innerWidth)  ? e.clientX - menuW : e.clientX;
                const y = (e.clientY + menuH > window.innerHeight) ? e.clientY - menuH : e.clientY;
                ctxMenu.value = { show: true, x, y, type, ci, coli, eli, ncoli, neli };
            };
            const closeCtxMenu = () => { ctxMenu.value = { ...ctxMenu.value, show: false }; };

            const ctxEdit = () => {
                const m = ctxMenu.value;
                setEditingContext(m.type, m.ci, m.coli, m.eli, m.ncoli, m.neli);
                closeCtxMenu();
            };
            const ctxSave = () => {
                const m = ctxMenu.value;
                const typeMap = { container: 'containers', column: 'columns', element: 'elements', 'nested-column': 'nested_columns', 'nested-element': 'elements' };
                openLibraryModal(typeMap[m.type], m.ci, m.coli, m.eli, m.ncoli, m.neli);
                closeCtxMenu();
            };
            const ctxClone = () => {
                const m = ctxMenu.value;
                if (m.type === 'container') duplicateContainer(m.ci);
                else if (m.type === 'column') duplicateColumn(m.ci, m.coli);
                else if (m.type === 'element') duplicateElement(m.ci, m.coli, m.eli);
                else if (m.type === 'nested-row') duplicateElement(m.ci, m.coli, m.eli);
                else if (m.type === 'nested-column') duplicateNestedColumn(m.ci, m.coli, m.eli, m.ncoli);
                else if (m.type === 'nested-element') duplicateNestedElement(m.ci, m.coli, m.eli, m.ncoli, m.neli);
                closeCtxMenu();
            };
            const ctxRemove = () => {
                const m = ctxMenu.value;
                if (m.type === 'container') layout.value.splice(m.ci, 1);
                else if (m.type === 'column') layout.value[m.ci].columns.splice(m.coli, 1);
                else if (m.type === 'element' || m.type === 'nested-row') layout.value[m.ci].columns[m.coli].elements.splice(m.eli, 1);
                else if (m.type === 'nested-column') layout.value[m.ci].columns[m.coli].elements[m.eli].columns.splice(m.ncoli, 1);
                else if (m.type === 'nested-element') layout.value[m.ci].columns[m.coli].elements[m.eli].columns[m.ncoli].elements.splice(m.neli, 1);
                closeCtxMenu();
            };
            const ctxCopy = () => {
                const m = ctxMenu.value;
                let data;
                if (m.type === 'container') data = cloneObject(layout.value[m.ci]);
                else if (m.type === 'column') data = cloneObject(layout.value[m.ci].columns[m.coli]);
                else if (m.type === 'element' || m.type === 'nested-row') data = cloneObject(layout.value[m.ci].columns[m.coli].elements[m.eli]);
                else if (m.type === 'nested-column') data = cloneObject(layout.value[m.ci].columns[m.coli].elements[m.eli].columns[m.ncoli]);
                else if (m.type === 'nested-element') data = cloneObject(layout.value[m.ci].columns[m.coli].elements[m.eli].columns[m.ncoli].elements[m.neli]);
                ctxClipboard.value = { type: m.type, data };
                try {
                    localStorage.setItem(_CLIP_KEY, JSON.stringify({ type: m.type, data }));
                    showToast('Copied! Paste available on other pages.', 'success');
                } catch (e) {
                    showToast('Copied (cross-page paste unavailable: ' + e.message + ')', 'error');
                }
                closeCtxMenu();
            };
            const ctxPaste = (position) => {
                const m = ctxMenu.value;
                if (!ctxClipboard.value || ctxClipboard.value.type !== m.type) { closeCtxMenu(); return; }
                const copy = cloneObject(ctxClipboard.value.data);
                assignNewIds(copy);
                if (m.type === 'container') {
                    position === 'start' ? layout.value.unshift(copy) : layout.value.push(copy);
                } else if (m.type === 'column') {
                    const cols = layout.value[m.ci].columns;
                    position === 'start' ? cols.unshift(copy) : cols.push(copy);
                } else if (m.type === 'element' || m.type === 'nested-row') {
                    const els = layout.value[m.ci].columns[m.coli].elements;
                    position === 'start' ? els.unshift(copy) : els.push(copy);
                } else if (m.type === 'nested-column') {
                    const cols = layout.value[m.ci].columns[m.coli].elements[m.eli].columns;
                    position === 'start' ? cols.unshift(copy) : cols.push(copy);
                } else if (m.type === 'nested-element') {
                    const els = layout.value[m.ci].columns[m.coli].elements[m.eli].columns[m.ncoli].elements;
                    position === 'start' ? els.unshift(copy) : els.push(copy);
                }
                closeCtxMenu();
            };

            // Full HTML5 Drag and Drop Logic for Reordering
            const dragSource = ref(null);
            const dragTarget = ref(null); // Used for visual highlighting
            const dragPosition = ref('top'); // top, bottom, left, right

            const onDragStart = (e, type, ci, coli = null, eli = null, ncoli = null, neli = null) => {
                dragSource.value = { type, ci, coli, eli, ncoli, neli };
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', type);
                    // Add slight opacity to the dragged element
                    setTimeout(() => {
                        const target = e.target.closest('.group\\/cont, .group\\/col, .group\\/ncol, .group\\/nrow, .group\\/el');
                        if (target) target.style.opacity = '0.4';
                    }, 0);
                }
            };

            const onDragEnd = (e) => {
                const dragged = e.target.closest('.group\\/cont, .group\\/col, .group\\/ncol, .group\\/nrow, .group\\/el');
                if (dragged) dragged.style.opacity = '';
                dragSource.value = null;
                dragTarget.value = null;
            };

            const onDragOver = (e, type, ci, coli = null, eli = null, ncoli = null, neli = null) => {
                if (!dragSource.value) return;
                const src = dragSource.value;

                // Rule 1: Containers only over Containers
                if (src.type === 'container' && type !== 'container') return;
                // Rule 2: Columns can drag over Columns OR Container-inner (to append)
                if (src.type === 'column' && type !== 'column' && type !== 'container') return;
                // Rule 3: Elements can drag over Elements OR Column-inner OR Nested-Column-inner
                if (src.type === 'element' && !['element', 'column', 'nested-column'].includes(type)) return;

                // Restriction: Top-level elements cannot be dropped into nested columns
                if (src.type === 'element' && src.ncoli === null && (type === 'nested-column' || ncoli !== null)) {
                    dragTarget.value = null;
                    return; // Do not preventDefault or stopPropagation, let it bubble to the parent row container
                }

                e.preventDefault();
                e.stopPropagation();

                // Rule 4: Nested Columns (rows) drag over Nested Columns OR Column-inner
                if (src.type === 'nested-column' && !['nested-column', 'column'].includes(type)) return;

                const rect = e.currentTarget.getBoundingClientRect();
                let pos = 'top';
                
                // Axis detection based on SOURCE type (columns are horizontal, others vertical)
                if (src.type === 'column' || src.type === 'nested-column') {
                    pos = e.clientX > rect.left + rect.width / 2 ? 'right' : 'left';
                } else {
                    pos = e.clientY > rect.top + rect.height / 2 ? 'bottom' : 'top';
                }

                dragPosition.value = pos;
                const targetId = `${type}-${ci}-${coli}-${eli}-${ncoli}-${neli}`;
                if (dragTarget.value !== targetId) {
                    dragTarget.value = targetId;
                }
            };

            const moveItem = (srcArr, targetArr, srcIdx, targetIdx, position) => {
                if (!srcArr || !targetArr) return;
                
                // If same list and same index, do nothing
                if (srcArr === targetArr && srcIdx === targetIdx) return;

                const item = srcArr.splice(srcIdx, 1)[0];
                if (!item) return;

                let finalIdx = targetIdx;
                
                // If moving within the same array and source was before target,
                // the target index has shifted back by 1.
                if (srcArr === targetArr && srcIdx < targetIdx) {
                    finalIdx -= 1;
                }

                if (position === 'bottom' || position === 'right') finalIdx += 1;
                
                // Ensure bounds
                if (finalIdx < 0) finalIdx = 0;
                if (!targetArr) return; // Safety check
                if (finalIdx > targetArr.length) finalIdx = targetArr.length;

                targetArr.splice(finalIdx, 0, item);
            };

            const getListAndIndex = (ci, coli, eli, ncoli, neli) => {
                if (neli !== null) return { list: layout.value[ci].columns[coli].elements[eli].columns[ncoli].elements, index: neli };
                if (ncoli !== null) return { list: layout.value[ci].columns[coli].elements[eli].columns, index: ncoli };
                if (eli !== null) return { list: layout.value[ci].columns[coli].elements, index: eli };
                if (coli !== null) return { list: layout.value[ci].columns, index: coli };
                return { list: layout.value, index: ci };
            };

            const onDrop = (e, type, ci, coli = null, eli = null, ncoli = null, neli = null) => {
                e.preventDefault();
                e.stopPropagation();

                if (!dragSource.value) return;
                const src = dragSource.value;
                const pos = dragPosition.value;
                dragTarget.value = null;

                // 1. Get Source List and Index
                const srcData = getListAndIndex(src.ci, src.coli, src.eli, src.ncoli, src.neli);
                
                // 2. Determine Target List and Index
                let targetList;
                let targetIdx;

                if (src.type === 'container') {
                    targetList = layout.value;
                    targetIdx = ci;
                } 
                else if (src.type === 'column') {
                    // Columns can move between containers
                    targetList = layout.value[ci].columns;
                    targetIdx = (coli !== null) ? coli : targetList.length;
                }
                else if (src.type === 'nested-column') {
                    // Nested columns must go into a 'row' element
                    if (ci !== null && coli !== null && eli !== null) {
                        const targetEl = layout.value[ci].columns[coli].elements[eli];
                        if (targetEl && targetEl.type === 'row') {
                            targetList = targetEl.columns;
                            targetIdx = (ncoli !== null) ? ncoli : targetList.length;
                        }
                    }
                }
                else if (src.type === 'element') {
                    if (ncoli !== null) {
                        // Restriction: Top-level elements cannot be dropped into nested columns
                        if (src.ncoli === null) return;

                        // Target is a nested column
                        const targetEl = layout.value[ci].columns[coli].elements[eli];
                        if (targetEl && targetEl.type === 'row' && targetEl.columns[ncoli]) {
                            targetList = targetEl.columns[ncoli].elements;
                            targetIdx = (neli !== null) ? neli : targetList.length;
                        }
                    } else if (coli !== null) {
                        // Target is a main column
                        targetList = layout.value[ci].columns[coli].elements;
                        targetIdx = (eli !== null) ? eli : targetList.length;
                    }
                }

                if (!targetList) return;

                // Move the item
                moveItem(srcData.list, targetList, srcData.index, targetIdx, pos);
            };

            // Existing visual resizing handles drag logic...
            const dragColi = ref(null);
            const dragEli = ref(null);
            const dragNcoli = ref(null);
            const isColumnDrag = ref(false);
            const isNestedDrag = ref(false);
            const dragRefWidth = ref(300);

            const startDrag = (e, type, ci, coli = null, eli = null, ncoli = null) => {
                isDragging.value = true;
                dragType.value = type;
                dragCi.value = ci;
                dragColi.value = coli;
                dragEli.value = eli;
                dragNcoli.value = ncoli;

                isColumnDrag.value = coli !== null && eli === null;
                isNestedDrag.value = ncoli !== null;

                startY.value = e.clientY;
                startX.value = e.clientX;

                let target;
                if (isNestedDrag.value) {
                    target = layout.value[ci].columns[coli].elements[eli].columns[ncoli];
                } else if (isColumnDrag.value) {
                    target = layout.value[ci].columns[coli];
                } else {
                    target = layout.value[ci];
                }

                if (type === 'columnSpacingLeft' || type === 'columnSpacingRight') {
                    const colEl = e.target.closest('.column-outer');
                    const containerEl = colEl ? colEl.parentElement : null;
                    dragRefWidth.value = containerEl ? containerEl.clientWidth : (colEl ? colEl.clientWidth : 300);
                    if (target.settings[type] === undefined || target.settings[type] === null || target.settings[type] === '') {
                        const rawGap = layout.value[ci]?.settings?.columnGap;
                        target.settings[type] = (rawGap !== undefined && rawGap !== '' && rawGap !== null) ? Number(rawGap) : 0;
                    }
                } else if (type === 'paddingLeft' || type === 'paddingRight') {
                    const colEl = e.target.closest('.column-outer');
                    const rowEl = colEl ? null : e.target.closest('.container-row');
                    dragRefWidth.value = colEl ? colEl.clientWidth : (rowEl ? rowEl.clientWidth : 300);
                    if (!target.settings[type]) target.settings[type] = 0;
                } else {
                    if (!target.settings[type]) target.settings[type] = 0;
                }

                startVal.value = target.settings[type] || 0;

                // Open settings panel and switch to design tab for the dragged entity
                if (isNestedDrag.value) {
                    setEditingContext('nested-column', ci, coli, eli, ncoli);
                    activeColPanelTab.value = 'design';
                } else if (isColumnDrag.value) {
                    setEditingContext('column', ci, coli);
                    activeColPanelTab.value = 'design';
                } else {
                    setEditingContext('container', ci);
                    activePanelTab.value = 'design';
                }

                window.addEventListener('mousemove', handleDrag);
                window.addEventListener('mouseup', stopDrag);

                if (type.toLowerCase().includes('left') || type.toLowerCase().includes('right')) {
                    document.body.style.cursor = 'ew-resize';
                } else {
                    document.body.style.cursor = 'ns-resize';
                }

                document.body.classList.add('select-none');
            };

            const handleDrag = (e) => {
                if (!isDragging.value) return;
                const diffY = e.clientY - startY.value;
                const diffX = e.clientX - startX.value;
                let newVal = 0;

                // Column spacing: drag in % relative to column width
                if (dragType.value === 'columnSpacingLeft' || dragType.value === 'columnSpacingRight') {
                    const pctDiff = (diffX / (dragRefWidth.value || 300)) * 100;
                    newVal = dragType.value === 'columnSpacingLeft'
                        ? startVal.value + pctDiff
                        : startVal.value - pctDiff;
                // User logic: Dragging DOWN increases padding for both TOP and BOTTOM handles.
                } else if (dragType.value.toLowerCase().includes('top') || dragType.value.toLowerCase().includes('bottom')) {
                    newVal = startVal.value + diffY;
                } else {
                    newVal = startVal.value + diffX;
                }

                // Invert right-side handles: paddingRight and marginRight both invert
                // marginRight: drag LEFT = increases (right border moves left, left border stays)
                if (dragType.value === 'paddingRight' || dragType.value === 'marginRight') {
                    newVal = startVal.value - diffX;
                }

                let target;
                if (isNestedDrag.value) {
                    target = layout.value[dragCi.value].columns[dragColi.value].elements[dragEli.value].columns[dragNcoli.value];
                } else if (isColumnDrag.value) {
                    target = layout.value[dragCi.value].columns[dragColi.value];
                } else {
                    target = layout.value[dragCi.value];
                }

                if (dragType.value === 'columnSpacingLeft' || dragType.value === 'columnSpacingRight') {
                    const colBasis = parseFloat(target.basis) || 100;
                    const otherKey = dragType.value === 'columnSpacingLeft' ? 'columnSpacingRight' : 'columnSpacingLeft';
                    const otherSpacing = Number(target.settings[otherKey] || 0);
                    target.settings[dragType.value] = Math.max(0, Math.min(newVal, colBasis - otherSpacing));
                } else if (dragType.value === 'paddingLeft' || dragType.value === 'paddingRight') {
                    const otherKey = dragType.value === 'paddingLeft' ? 'paddingRight' : 'paddingLeft';
                    const otherPadding = Number(target.settings[otherKey] || 0);
                    target.settings[dragType.value] = Math.max(0, Math.min(newVal, dragRefWidth.value - otherPadding));
                } else {
                    target.settings[dragType.value] = Math.max(0, newVal);
                }
            };

            const stopDrag = () => {
                isDragging.value = false;
                dragType.value = null;
                dragCi.value = null;
                dragEli.value = null;
                dragNcoli.value = null;
                window.removeEventListener('mousemove', handleDrag);
                window.removeEventListener('mouseup', stopDrag);
                document.body.style.cursor = '';
                document.body.classList.remove('select-none');
                // push one clean snapshot for the completed drag
                _historyStack.splice(historyIndex.value + 1);
                _historyStack.push(JSON.parse(JSON.stringify(layout.value)));
                if (_historyStack.length > MAX_HISTORY) _historyStack.shift();
                historyIndex.value = _historyStack.length - 1;
            };

            const openMediaModal = (settingKey) => {
                const ctx = editingContext.value;
                let targetSettings = null;

                if (ctx.type === 'container') {
                    if (layout.value[ctx.ci]) targetSettings = layout.value[ctx.ci].settings;
                } else if (ctx.type === 'column' || ctx.type === 'nested-column') {
                    const col = editingColumn.value;
                    if (col) targetSettings = col.settings;
                } else if (ctx.type === 'element' || ctx.type === 'nested-element') {
                    const el = editingElement.value;
                    if (el) targetSettings = el.settings;
                }

                if (!targetSettings) return;

                if (window.openMediaModal) {
                    window.openMediaModal((selectedMedia) => {
                        const url = '/storage/' + selectedMedia.path;
                        targetSettings[settingKey] = url;
                    });
                } else {
                    const currentVal = targetSettings[settingKey] || '';
                    const url = prompt("Enter image URL:", currentVal);
                    if (url !== null) {
                        targetSettings[settingKey] = url;
                    }
                }
            };

            // Generic media picker that writes into any target object (used by custom element repeater rows & sub-fields)
            const openMediaModalForTarget = (targetObj, key) => {
                if (!targetObj) return;
                if (window.openMediaModal) {
                    window.openMediaModal((selectedMedia) => {
                        targetObj[key] = '/storage/' + selectedMedia.path;
                    });
                } else {
                    const url = prompt('Enter image URL:', targetObj[key] || '');
                    if (url !== null) targetObj[key] = url;
                }
            };

            const openGalleryImageMedia = (idx) => {
                const el = editingElement.value;
                if (!el || !el.settings || !Array.isArray(el.settings.images)) return;
                if (window.openMediaModal) {
                    window.openMediaModal((selectedMedia) => {
                        const url = '/storage/' + selectedMedia.path;
                        if (el.settings.images[idx] !== undefined) el.settings.images[idx].url = url;
                    });
                } else {
                    const currentVal = (el.settings.images[idx] || {}).url || '';
                    const url = prompt('Enter image URL:', currentVal);
                    if (url !== null && el.settings.images[idx] !== undefined) el.settings.images[idx].url = url;
                }
            };

            const openGalleryBulkMedia = () => {
                const el = editingElement.value;
                if (!el || !el.settings || !Array.isArray(el.settings.images)) return;
                if (window.openMediaModal) {
                    window.openMediaModal((selectedItems) => {
                        selectedItems.forEach(media => {
                            el.settings.images.push({ url: '/storage/' + media.path, alt: media.alt_text || '', caption: media.caption || '' });
                        });
                    }, { multiple: true });
                } else {
                    const urls = prompt('Enter image URLs (comma-separated):');
                    if (urls) {
                        urls.split(',').forEach(u => {
                            const url = u.trim();
                            if (url) el.settings.images.push({ url, alt: '', caption: '' });
                        });
                    }
                }
            };

            let _galleryDragSrc = -1;
            const galleryDragStart = (idx) => { _galleryDragSrc = idx; };
            const galleryDrop = (targetIdx) => {
                if (_galleryDragSrc < 0 || _galleryDragSrc === targetIdx) { _galleryDragSrc = -1; return; }
                const el = editingElement.value;
                if (!el?.settings?.images) { _galleryDragSrc = -1; return; }
                const imgs = el.settings.images;
                const [moved] = imgs.splice(_galleryDragSrc, 1);
                imgs.splice(targetIdx, 0, moved);
                _galleryDragSrc = -1;
            };

            const _alphaToHex = (a) => {
                let n = Math.round(Math.max(0, Math.min(1, Number(a))) * 255);
                let h = n.toString(16);
                return h.length === 1 ? '0' + h : h;
            };
            // Field display: show 8-digit #RRGGBBAA when opacity < 1, else 6-digit #RRGGBB.
            const falconColorDisplay = (obj, colorKey, opacityKey) => {
                let c = obj[colorKey];
                if (c === undefined || c === null || c === '') return '';
                let hex6 = String(c).replace('#', '').slice(0, 6);
                if (opacityKey && /^[0-9a-fA-F]{6}$/.test(hex6)) {
                    let o = obj[opacityKey];
                    if (o !== undefined && o !== null && Number(o) < 1) return '#' + hex6 + _alphaToHex(o);
                }
                return String(c);
            };
            // Parse typed value back: 8-digit splits into colour + opacity, otherwise plain colour.
            const falconColorInput = (obj, colorKey, opacityKey, value) => {
                let raw = String(value || '').replace('#', '').trim();
                if (opacityKey && /^[0-9a-fA-F]{8}$/.test(raw)) {
                    obj[colorKey] = '#' + raw.slice(0, 6);
                    obj[opacityKey] = parseInt(raw.slice(6, 8), 16) / 255;
                } else {
                    obj[colorKey] = (value && String(value).charAt(0) === '#') ? value : ('#' + raw);
                }
            };
            // Opening default: bake opacity into an 8-digit hex so the alpha slider knob sits correctly.
            const _pickerDefault = (obj, colorKey, opacityKey, cascadeColor) => {
                let c = obj[colorKey] || cascadeColor || '#ffffff';
                if (opacityKey) {
                    let hex6 = String(c).replace('#', '').slice(0, 6);
                    if (/^[0-9a-fA-F]{6}$/.test(hex6)) {
                        let o = obj[opacityKey];
                        if (o !== undefined && o !== null) return '#' + hex6 + _alphaToHex(o);
                    }
                }
                return c;
            };

            const openColorPicker = (event, obj, colorKey, opacityKey = null, cascadeColor = null, appClass = 'pcr-fnew') => {
                // Commit (don't revert) any previously open picker — its value was already applied live.
                // Reverting here wiped colors set on other targets (e.g. column bg vs element color).
                _closeActivePickr(false);

                const target = event.currentTarget;
                const origColor = (obj[colorKey] !== undefined && obj[colorKey] !== null && obj[colorKey] !== '')
                    ? obj[colorKey] : null;
                const origOpacity = opacityKey
                    ? (obj[opacityKey] !== undefined && obj[opacityKey] !== null ? obj[opacityKey] : null)
                    : null;

                _pickerCtx = { obj, colorKey, opacityKey, origColor, origOpacity };

                const pickr = Pickr.create({
                    el: target,
                    useAsButton: true,
                    theme: 'classic',
                    appClass: appClass || undefined,
                    position: appClass === 'pcr-fnew' ? 'bottom-start' : undefined,
                    default: _pickerDefault(obj, colorKey, opacityKey, cascadeColor),
                    defaultRepresentation: 'HEXA',
                    components: {
                        preview: true,
                        opacity: true,
                        hue: true,
                        interaction: {
                            hex: true,
                            rgba: false,
                            input: true,
                            clear: true,
                            save: true
                        }
                    },
                    swatches: [
                        '#000000', '#ffffff', '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5',
                        '#2196f3', '#03a6f4', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39'
                    ]
                });

                activePickr.value = pickr;

                // Tint the opacity slider with the current colour (instead of the default black).
                const _applyOpColor = () => {
                    try {
                        const root = pickr.getRoot();
                        const appEl = root && root.app ? root.app : null;
                        if (!appEl) return;
                        const rgba = pickr.getColor().toRGBA();
                        appEl.style.setProperty('--fnew-opcolor', 'rgb(' + Math.round(rgba[0]) + ',' + Math.round(rgba[1]) + ',' + Math.round(rgba[2]) + ')');
                    } catch (e) {}
                };

                pickr.on('save', (color, instance) => {
                    const rgba = color.toRGBA();
                    if (opacityKey) {
                        // separate opacity key: keep 6-digit hex + numeric opacity (legacy behaviour)
                        obj[colorKey]   = '#' + color.toHEXA()[0] + color.toHEXA()[1] + color.toHEXA()[2];
                        obj[opacityKey] = parseFloat((rgba[3]).toFixed(2));
                    } else {
                        // no opacity key: bake alpha into the value as 8-digit hex (#RRGGBBAA)
                        obj[colorKey] = color.toHEXA().toString();
                    }
                    instance.hide();
                    instance.destroy();
                    activePickr.value = null;
                    _pickerCtx = null;
                }).on('cancel', instance => {
                    instance.hide();
                    instance.destroy();
                    activePickr.value = null;
                    if (_pickerCtx) {
                        obj[colorKey] = origColor !== null ? origColor : '';
                        if (opacityKey) obj[opacityKey] = origOpacity !== null ? origOpacity : 1;
                        _pickerCtx = null;
                    }
                }).on('change', (color, source) => {
                    if (source === 'input') return;
                    const rgba = color.toRGBA();
                    if (opacityKey) {
                        obj[colorKey]   = '#' + color.toHEXA()[0] + color.toHEXA()[1] + color.toHEXA()[2];
                        obj[opacityKey] = parseFloat((rgba[3]).toFixed(2));
                    } else {
                        obj[colorKey] = color.toHEXA().toString();
                    }
                    _applyOpColor();
                });

                pickr.show();
                _applyOpColor();
            };

            // Browser EyeDropper API — pick a colour from anywhere on screen (Chromium browsers).
            const falconEyeDropper = async (obj, colorKey) => {
                if (!window.EyeDropper) return;
                try {
                    const res = await new window.EyeDropper().open();
                    if (res && res.sRGBHex) obj[colorKey] = res.sRGBHex;
                } catch (e) { /* user cancelled */ }
            };

            const saveLayout = async () => {
                isSaving.value = true;
                try {
                    // Sync all linked global sections to the global store so other pages get the updates
                    const globalContainers = layout.value.filter(c => c.settings?.global_id);
                    if (globalContainers.length) {
                        await Promise.all(globalContainers.map(container =>
                            fetch(`{{ url('admin/falcon-builder/global-sections') }}/${container.settings.global_id}`, {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                                body: JSON.stringify({ data: JSON.parse(JSON.stringify(container)) })
                            }).then(r => r.json()).catch(() => null)
                        ));
                    }

                    const response = await fetch("{{ $builderSaveUrl ?? route('admin.falcon-builder.save', $post->id) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ layout: layout.value })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showToast('Layout saved successfully!', 'success');
                        lastSavedLayout = serializedLayout.value;
                        isDirty.value = false;
                    } else {
                        showToast('Failed to save layout!', 'error');
                    }
                } catch (e) {
                    console.error('Save failed', e);
                    showToast('Save failed! Please check console.', 'error');
                } finally {
                    isSaving.value = false;
                }
            };

            // ── Revisions + Autosave ───────────────────────────────────────────────
            @isset($post)
            const revisionsEnabled = !postCardMode.value;
            const autosaveUrl   = "{{ route('admin.falcon-builder.autosave', $post->id) }}";
            const revisionsUrl  = "{{ route('admin.falcon-builder.revisions', $post->id) }}";
            const restoreUrlFor = (rid) => "{{ url('admin/falcon-builder/'.$post->id.'/revisions') }}/" + rid + "/restore";
            const deleteUrlFor  = (rid) => "{{ url('admin/falcon-builder/'.$post->id.'/revisions') }}/" + rid;
            @else
            const revisionsEnabled = false;
            const autosaveUrl = null, revisionsUrl = null;
            const restoreUrlFor = () => null;
            const deleteUrlFor  = () => null;
            @endisset

            const autosaveStatus = ref('');
            const showRevisions  = ref(false);
            const revisionList   = ref([]);
            const isRestoring    = ref(false);
            const autosaveBanner = ref(@json($pendingAutosave ?? null));
            const _csrfTok = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const runAutosave = async () => {
                if (!revisionsEnabled || !autosaveUrl || !isDirty.value) return;
                try {
                    const res = await fetch(autosaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfTok() },
                        body: JSON.stringify({ layout: layout.value })
                    });
                    const d = await res.json();
                    if (d.success) autosaveStatus.value = 'Draft saved ' + d.time;
                } catch (e) { /* silent — autosave is best-effort */ }
            };

            const openRevisions = async () => {
                if (!revisionsUrl) return;
                showRevisions.value = true;
                try {
                    const res = await fetch(revisionsUrl, { headers: { 'X-CSRF-TOKEN': _csrfTok() } });
                    const d = await res.json();
                    revisionList.value = d.revisions || [];
                } catch (e) { revisionList.value = []; }
            };

            const restoreRevision = async (rid) => {
                if (isRestoring.value || !restoreUrlFor(rid)) return;
                if (!confirm('Restore this version? Your current layout will be saved as a revision first.')) return;
                isRestoring.value = true;
                try {
                    const res = await fetch(restoreUrlFor(rid), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfTok() },
                        body: JSON.stringify({})
                    });
                    const d = await res.json();
                    if (d.success && Array.isArray(d.layout)) {
                        layout.value = d.layout;
                        lastSavedLayout = serializedLayout.value;
                        isDirty.value = false;
                        showRevisions.value = false;
                        autosaveBanner.value = null;
                        showToast('Version restored!', 'success');
                    } else {
                        showToast('Restore failed', 'error');
                    }
                } catch (e) { showToast('Restore failed', 'error'); }
                finally { isRestoring.value = false; }
            };

            const deleteRevisionItem = async (rid) => {
                if (!deleteUrlFor(rid) || !confirm('Delete this revision permanently?')) return;
                try {
                    const res = await fetch(deleteUrlFor(rid), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': _csrfTok() } });
                    const d = await res.json();
                    if (d.success) revisionList.value = revisionList.value.filter(r => r.id !== rid);
                } catch (e) { showToast('Delete failed', 'error'); }
            };

            const dismissAutosaveBanner = () => { autosaveBanner.value = null; };

            // Autosave loop — every 30s when there are unsaved changes
            if (revisionsEnabled) {
                setInterval(runAutosave, 30000);
            }

            const editingColumn = computed(() => {
                const ctx = editingContext.value;
                if (ctx.ci === null || ctx.ci === undefined || !layout.value[ctx.ci]) return null;
                const container = layout.value[ctx.ci];

                if (ctx.type === 'column') {
                    if (ctx.coli === null || ctx.coli === undefined) return null;
                    return container.columns[ctx.coli] || null;
                } else if (ctx.type === 'nested-column') {
                    if (ctx.coli === null || ctx.coli === undefined || ctx.eli === null || ctx.eli === undefined || ctx.ncoli === null || ctx.ncoli === undefined) return null;
                    const row = container.columns[ctx.coli].elements[ctx.eli];
                    return (row && row.columns) ? row.columns[ctx.ncoli] : null;
                }
                return null;
            });

            const editingElement = computed(() => {
                const ctx = editingContext.value;
                if (ctx.ci === null || ctx.coli === null || ctx.eli === null) return null;
                const container = layout.value[ctx.ci];
                if (!container) return null;
                const column = container.columns[ctx.coli];
                if (!column) return null;
                const el = column.elements[ctx.eli];
                if (!el) return null;

                if (ctx.ncoli !== null && ctx.neli !== null) {
                    const ncol = el.columns ? el.columns[ctx.ncoli] : null;
                    return ncol ? (ncol.elements[ctx.neli] ?? null) : null;
                }
                return el;
            });

            // Dynamic font variants for the selected title element's font
            const titleFontVariants = computed(() => {
                const rawFamily = editingElement.value?.settings?.fontFamily || '';
                if (!rawFamily || rawFamily === 'inherit') {
                    return ['100','200','300','400','500','600','700','800','900'];
                }
                const match = rawFamily.match(/['"]?([^'",$]+)/);
                const familyName = match ? match[1].trim() : rawFamily.trim();
                const found = BUILDER_FONTS.find(f => f.family === familyName);
                return found ? found.variants : ['100','200','300','400','500','600','700','800','900'];
            });

            // Auto-load Google Font when fontFamily setting changes
            watch(() => editingElement.value?.settings?.fontFamily, (newFamily) => {
                loadBuilderFont(newFamily);
            });

            // Reset taxonomy fields when post_type changes on a card element (skip on initial load)
            watch(() => editingElement.value?.settings?.post_type, (newType, oldType) => {
                const el = editingElement.value;
                if (!el || el.type !== 'card' || newType === oldType || oldType === undefined) return;
                el.settings.taxonomy_slug    = '';
                el.settings.taxonomy_include = [];
                el.settings.taxonomy_exclude = [];
            });

            // Debounced canvas preview fetch for card element settings
            let _cardPreviewTimer = null;
            watch(() => editingElement.value?.settings, (s) => {
                const el = editingElement.value;
                if (!el || el.type !== 'card') return;
                clearTimeout(_cardPreviewTimer);
                _cardPreviewTimer = setTimeout(() => fetchCardPreview(el), 600);
            }, { deep: true });

            // When a card element is selected, fill in any missing numeric defaults so
            // sliders (range inputs) start at the correct position instead of browser default.
            watch(() => editingElement.value, (el) => {
                if (!el || el.type !== 'card') return;
                const s = el.settings;
                if (s.column_spacing === undefined || s.column_spacing === null) s.column_spacing = 24;
                if (s.row_spacing    === undefined || s.row_spacing    === null) s.row_spacing    = 24;
                if (s.columns        === undefined || s.columns        === null) s.columns        = 3;
                if (s.columns_tablet === undefined || s.columns_tablet === null) s.columns_tablet = 2;
                if (s.columns_mobile === undefined || s.columns_mobile === null) s.columns_mobile = 1;
            }, { immediate: true });

            // HTML Block — CodeMirror IDE editor
            let falconHtmlCm = null;

            const initHtmlEditor = () => {
                if (!window.CodeMirror) return;
                const container = document.getElementById('lazy-html-editor');
                if (!container) return;

                // Editor was removed from DOM (v-if toggled off then on) — destroy and re-create
                if (falconHtmlCm && !document.body.contains(falconHtmlCm.getWrapperElement())) {
                    falconHtmlCm = null;
                }

                const currentVal = editingElement.value?.settings?.htmlContent || '';

                if (falconHtmlCm) {
                    if (falconHtmlCm.getValue() !== currentVal) {
                        falconHtmlCm.setValue(currentVal);
                    }
                    falconHtmlCm.refresh();
                    return;
                }

                falconHtmlCm = CodeMirror(container, {
                    value: currentVal,
                    mode: 'htmlmixed',
                    theme: 'dracula',
                    lineNumbers: true,
                    lineWrapping: true,
                    tabSize: 2,
                    indentWithTabs: false,
                    autoCloseTags: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                });
                falconHtmlCm.setSize('100%', 280);
                falconHtmlCm.on('change', (cm) => {
                    if (editingElement.value?.type === 'html') {
                        editingElement.value.settings.htmlContent = cm.getValue();
                    }
                });
            };

            // Watch when editing switches to/from an html element
            const isEditingHtml = computed(() => editingElement.value?.type === 'html');
            watch(isEditingHtml, (nowHtml) => {
                if (nowHtml) {
                    setTimeout(initHtmlEditor, 80);
                } else {
                    falconHtmlCm = null;
                }
            }, { flush: 'post' });
            // Re-init when switching between two html elements
            watch(() => editingElement.value?.id, (newId) => {
                if (isEditingHtml.value) {
                    setTimeout(initHtmlEditor, 80);
                }
            }, { flush: 'post' });
            // Re-init when switching back to General tab while editing an html element
            watch(() => editingContext.value.tab, (tab) => {
                if ((tab === 'content' || !tab) && isEditingHtml.value) {
                    setTimeout(initHtmlEditor, 80);
                }
            }, { flush: 'post' });

            // Dynamic Styles
            const canvasStyle = computed(() => {
                const pt = window.builderPagePadding?.top || '60px';
                const pb = window.builderPagePadding?.bottom || '60px';
                const baseStyle = { paddingTop: pt, paddingBottom: pb };
                if (isPreview.value) return { ...baseStyle, width: '100%' };
                if (device.value === 'mobile') {
                    return { ...baseStyle, width: (window.builderBreakpoints?.small || 800) + 'px' };
                }
                if (device.value === 'tablet') {
                    return { ...baseStyle, width: (window.builderBreakpoints?.medium || 1100) + 'px' };
                }
                return { ...baseStyle, width: '100%' };
            });

            const formatBasisToFraction = (basis) => {
                if (!basis || basis === 'auto') return 'Auto';
                if (basis === '100%') return '1/1';
                if (basis === '50%') return '1/2';
                if (basis === '33.33%') return '1/3';
                if (basis === '66.66%') return '2/3';
                if (basis === '25%') return '1/4';
                if (basis === '75%') return '3/4';
                if (basis === '20%') return '1/5';
                if (basis === '40%') return '2/5';
                if (basis === '60%') return '3/5';
                if (basis === '80%') return '4/5';
                if (basis === '16.66%') return '1/6';
                if (basis === '83.33%') return '5/6';
                return basis;
            };

            const updateBasis = (val) => {
                if (editingColumn.value) {
                    if (device.value === 'desktop') {
                        editingColumn.value.basis = val;
                    } else {
                        editingColumn.value['basis_' + device.value] = val;
                    }
                }
            };

            const applyButtonSize = (size) => {
                if (!editingElement.value) return;
                const config = {
                    'small': { pt: 8, pl: 20, fs: 14 },
                    'medium': { pt: 12, pl: 30, fs: 16 },
                    'large': { pt: 16, pl: 40, fs: 18 },
                    'extra-large': { pt: 20, pl: 50, fs: 20 }
                };
                const c = config[size] || config['medium'];
                editingElement.value.settings.paddingTop = c.pt;
                editingElement.value.settings.paddingBottom = c.pt;
                editingElement.value.settings.paddingLeft = c.pl;
                editingElement.value.settings.paddingRight = c.pl;
                editingElement.value.settings.fontSize = c.fs;
            };

            const getUnitVal = (val, unit = 'px') => {
                if (val === undefined || val === null || val === '') return undefined;
                const str = String(val).trim();
                if (str === '') return undefined;
                // Free-form values already carrying their own unit/function
                // (rem, em, %, vw, vh, calc(), var(), clamp(), auto, …) are used as-is.
                // Only bare numbers get the unit appended. Mirrors PHP getUnitVal().
                if (isNaN(str)) return str;
                return str + unit;
            };

            const containerStyle = (container, ci) => {
                const s = container.settings;
                const dev = device.value;
                let mTopRaw = getResponsiveVal(s, 'marginTop', dev);
                let mTop = mTopRaw !== undefined && mTopRaw !== '' ? Number(mTopRaw) : 0;
                let mBottomRaw = getResponsiveVal(s, 'marginBottom', dev);

                let boxShadowStr = 'none';
                if (s.boxShadow) {
                    const inset = s.boxShadowStyle === 'inner' ? 'inset ' : '';
                    boxShadowStr = `${inset}${s.boxShadowPositionHorizontal || 0}px ${s.boxShadowPositionVertical || 0}px ${s.boxShadowBlurRadius || 0}px ${s.boxShadowSpreadRadius || 0}px ${s.boxShadowColor || '#000000'}`;
                }

                const bgType = s.bgType || 'color';
                let responsiveBgColor = getResponsiveVal(s, 'bgColor', dev) || s.bgColor;
                let responsiveBgOpacity = getResponsiveVal(s, 'bgColorOpacity', dev);
                let bgStyle = bgType === 'color' ? hexToRgba(responsiveBgColor, responsiveBgOpacity !== undefined ? responsiveBgOpacity : 1) : undefined;
                let bgImages = [];

                if (bgType === 'gradient' && s.bgGradientStartColor && s.bgGradientEndColor) {
                    const start = hexToRgba(s.bgGradientStartColor, s.bgGradientStartOpacity !== undefined ? s.bgGradientStartOpacity : 1);
                    const end = hexToRgba(s.bgGradientEndColor, s.bgGradientEndOpacity !== undefined ? s.bgGradientEndOpacity : 1);

                    if (s.bgGradientType === 'radial') {
                        bgImages.push(`radial-gradient(circle at center, ${start} ${s.bgGradientStartPosition || 0}%, ${end} ${s.bgGradientEndPosition || 100}%)`);
                    } else {
                        bgImages.push(`linear-gradient(${s.bgGradientAngle || 180}deg, ${start} ${s.bgGradientStartPosition || 0}%, ${end} ${s.bgGradientEndPosition || 100}%)`);
                    }
                }

                const dynBg = dynBgUrl(s);
                const rBgImage = bgType !== 'color' ? (dynBg || getResponsiveVal(s, 'bgImage', dev) || s.bgImage) : null;
                if (rBgImage) {
                    bgImages.push(`url('${rBgImage}')`);
                } else if (bgType !== 'color' && s.bgImageDynamicSource) {
                    bgImages.push(DYN_BG_PLACEHOLDER);
                }

                let bgImageStr = bgImages.length > 0 ? bgImages.join(', ') : undefined;

                let pt = getResponsiveVal(s, 'paddingTop', dev);
                let pb = getResponsiveVal(s, 'paddingBottom', dev);
                let pl = getResponsiveVal(s, 'paddingLeft', dev);
                let pr = getResponsiveVal(s, 'paddingRight', dev);

                let ptu = getResponsiveVal(s, 'paddingTopUnit', dev) || s.paddingTopUnit || 'px';
                let pbu = getResponsiveVal(s, 'paddingBottomUnit', dev) || s.paddingBottomUnit || 'px';
                let plu = getResponsiveVal(s, 'paddingLeftUnit', dev) || s.paddingLeftUnit || 'px';
                let pru = getResponsiveVal(s, 'paddingRightUnit', dev) || s.paddingRightUnit || 'px';

                let mtu = getResponsiveVal(s, 'marginTopUnit', dev) || s.marginTopUnit || 'px';
                let mbu = getResponsiveVal(s, 'marginBottomUnit', dev) || s.marginBottomUnit || 'px';

                let bgPos = getResponsiveVal(s, 'bgImagePosition', dev) || s.bgImagePosition || 'center center';
                let bgRep = getResponsiveVal(s, 'bgImageRepeat', dev) || s.bgImageRepeat || 'no-repeat';
                let bgSz = getResponsiveVal(s, 'bgImageSize', dev) || s.bgImageSize || 'auto';
                if (s.bgImageDynamicSource && (!bgSz || bgSz === 'auto')) bgSz = 'cover';
                let bgBlend = getResponsiveVal(s, 'bgImageBlendMode', dev) || s.bgImageBlendMode || 'normal';

                return {
                    paddingTop: getUnitVal(pt, ptu) || '0px',
                    paddingBottom: getUnitVal(pb, pbu) || '0px',
                    paddingLeft: getUnitVal(pl, plu) || '0px',
                    paddingRight: getUnitVal(pr, pru) || '0px',
                    marginTop: getUnitVal(mTop, mtu) || '0px',
                    marginBottom: getUnitVal(mBottomRaw, mbu) || '0px',
                    borderTopWidth: (s.borderSizeTop || 0) + 'px',
                    borderRightWidth: (s.borderSizeRight || 0) + 'px',
                    borderBottomWidth: (s.borderSizeBottom || 0) + 'px',
                    borderLeftWidth: (s.borderSizeLeft || 0) + 'px',
                    borderStyle: 'solid',
                    borderColor: s.borderColor || '#000000',
                    borderTopLeftRadius: getUnitVal(s.borderRadiusTopLeft, s.borderRadiusTopLeftUnit) || '0px',
                    borderTopRightRadius: getUnitVal(s.borderRadiusTopRight, s.borderRadiusTopRightUnit) || '0px',
                    borderBottomRightRadius: getUnitVal(s.borderRadiusBottomRight, s.borderRadiusBottomRightUnit) || '0px',
                    borderBottomLeftRadius: getUnitVal(s.borderRadiusBottomLeft, s.borderRadiusBottomLeftUnit) || '0px',
                    zIndex: getResponsiveVal(s, 'zIndex', dev) || s.zIndex || 'auto',
                    overflow: (() => { const rv = getResponsiveVal(s, 'overflow', dev) || s.overflow; return rv && rv !== 'default' ? rv : 'visible'; })(),
                    backgroundColor: (container._bgHover && bgType === 'color' && getResponsiveVal(s, 'bgHoverColor', dev)) ? hexToRgba(getResponsiveVal(s, 'bgHoverColor', dev), getResponsiveVal(s, 'bgHoverColorOpacity', dev) !== undefined ? getResponsiveVal(s, 'bgHoverColorOpacity', dev) : 1) : bgStyle,
                    backgroundImage: bgImageStr || undefined,
                    backgroundPosition: rBgImage ? bgPos : undefined,
                    backgroundRepeat: rBgImage ? bgRep : undefined,
                    backgroundSize: rBgImage ? bgSz : undefined,
                    backgroundAttachment: rBgImage && s.bgImageParallax === 'fixed' ? 'fixed' : undefined,
                    backgroundBlendMode: rBgImage && bgBlend !== 'normal' ? bgBlend : undefined,
                    minHeight: (() => {
                        const rh = getResponsiveVal(s, 'height', dev) || 'auto';
                        if (rh === 'full') return '100vh';
                        if (rh === 'custom') return getResponsiveVal(s, 'customHeight', dev) || 'auto';
                        const rMin = getResponsiveVal(s, 'minHeight', dev);
                        if (rMin !== undefined && rMin !== '') return rMin;
                        return (container.columns || []).some(col => (col.elements || []).length > 0) ? '0px' : '100px';
                    })(),
                    height: 'auto',
                    display: 'flex',
                    flexDirection: 'column'
                };
            };

            const containerInnerStyle = (container) => {
                const s = container.settings;
                const isSpaceDistribution = ['space-between', 'space-around', 'space-evenly'].includes(s.justifyContent);
                const alignItems = getResponsiveVal(s, 'alignItems', device.value) || 'stretch';
                const hasContent = (container.columns || []).some(col => (col.elements || []).length > 0);
                const responsiveMinHeight = getResponsiveVal(s, 'minHeight', device.value);
                const innerMinHeight = (responsiveMinHeight !== undefined && responsiveMinHeight !== '') ? responsiveMinHeight : (hasContent ? '0px' : '100px');
                const rHeightMode = getResponsiveVal(s, 'height', device.value) || 'auto';
                // Use height:100% so flex-grow fills the outer container for custom/full height
                const innerHeight = (rHeightMode === 'custom' || rHeightMode === 'full') ? '100%' : 'auto';
                
                // Align-content logic:
                // - Auto height: flex-start
                // - Fixed height: Default to 'stretch' so rows fill the container,
                //   allowing internal align-items (Top/Center/Bottom/Stretch) to work.
                let alignContent = 'stretch';
                if (container.type === 'row') {
                    alignContent = getResponsiveVal(s, 'rowAlignContent', device.value) || 'flex-start';
                } else if (!rHeightMode || rHeightMode === 'auto') {
                    alignContent = 'flex-start';
                } else {
                    alignContent = getResponsiveVal(s, 'rowAlignContent', device.value) || 'stretch';
                }

                const flexWrapRaw = getResponsiveVal(s, 'flexWrap', device.value);

                return {
                    display: 'flex',
                    ...(container.type !== 'row' ? { maxWidth: postCardMode.value ? '560px' : (s.contentWidth === '100%' ? '100%' : siteWidth.value) } : {}),
                    width: '100%',
                    flexGrow: 1,
                    flexShrink: 0,
                    overflow: (() => { const rv = getResponsiveVal(s, 'overflow', device.value) || s.overflow; return rv && rv !== 'default' ? rv : undefined; })(),
                    minHeight: innerHeight === '100%' ? '100%' : innerMinHeight,
                    maxHeight: s.maxHeight || undefined,
                    height: 'auto',
                    flexDirection: 'row',
                    flexWrap: (!flexWrapRaw || flexWrapRaw === 'default') ? 'wrap' : flexWrapRaw,
                    alignItems: alignItems,
                    alignContent: alignContent,
                    justifyContent: getResponsiveVal(s, 'justifyContent', device.value) || 'flex-start',
                    // columnGap removed in favor of percentage-based column padding
                };
            };

            const columnOuterStyle = (container, column, count) => {
                const s = column.settings;
                const containerAlign = getResponsiveVal(container?.settings || {}, 'alignItems', device.value) || 'stretch';
                const basis = device.value === 'mobile'
                    ? (column.basis_mobile || '100%')
                    : device.value === 'tablet'
                    ? (column.basis_tablet || column.basis || (100 / count) + '%')
                    : (column.basis || (100 / count) + '%');
                
                let flexBasis;
                const gap = parseInt(container.settings.columnGap || '20px');
                if (basis === 'auto') {
                    flexBasis = 'auto';
                } else {
                    flexBasis = basis;
                }

                const pTop = Number(s.paddingTop) || 0;
                const pBottom = Number(s.paddingBottom) || 0;
                
                const elements = column.elements || [];
                const isEmpty = elements.length === 0;
                const colAlignRaw = getResponsiveVal(s, 'alignment', device.value) || 'default';
                const colAlign = (colAlignRaw && colAlignRaw !== 'default') ? colAlignRaw : containerAlign;
                const isStretch = colAlign === 'stretch';
                const containerHeight = getResponsiveVal(container?.settings || {}, 'height', device.value) || 'auto';

                const hasDefinedHeight = containerHeight === 'full' || containerHeight === 'custom';

                const rawGap = getResponsiveVal(container.settings, 'columnGap', device.value);
                const globalGap = (rawGap !== undefined && rawGap !== '' && rawGap !== null) ? rawGap : 0;

                const pLeft = (s.columnSpacingLeft !== undefined && s.columnSpacingLeft !== '' && s.columnSpacingLeft !== null) 
                              ? s.columnSpacingLeft + '%' 
                              : globalGap + '%';
                
                const pRight = (s.columnSpacingRight !== undefined && s.columnSpacingRight !== '' && s.columnSpacingRight !== null) 
                               ? s.columnSpacingRight + '%' 
                               : globalGap + '%';

                const style = {
                    flexBasis: flexBasis,
                    maxWidth: flexBasis === 'auto' ? 'none' : flexBasis,
                    flexGrow: s.flexGrow !== undefined && s.flexGrow !== '' ? s.flexGrow : (isStretch ? 1 : 0),
                    flexShrink: s.flexShrink !== undefined && s.flexShrink !== '' ? s.flexShrink : 0,
                    maxHeight: getUnitVal(s.maxHeight, s.maxHeightUnit) || undefined,
                    paddingLeft: pLeft,
                    paddingRight: pRight,
                    marginTop: getUnitVal(getResponsiveVal(s, 'marginTop', device.value), getResponsiveVal(s, 'marginTopUnit', device.value) || s.marginTopUnit || 'px'),
                    marginBottom: getUnitVal(getResponsiveVal(s, 'marginBottom', device.value), getResponsiveVal(s, 'marginBottomUnit', device.value) || s.marginBottomUnit || 'px'),
                    zIndex: getResponsiveVal(s, 'zIndex', device.value) || s.zIndex || 'auto',
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'stretch',
                    alignSelf: isStretch ? 'stretch' : colAlign
                };
                
                // Final refined height/align logic:
                // Final refined height/align logic:
                if (isStretch) {
                    style.flexGrow = 1;
                    style.alignSelf = 'stretch';
                    style.minHeight = isEmpty ? '100px' : 'auto';
                    style.height = 'auto';
                } else {
                    style.height = 'auto';
                    style.flexGrow = 0;
                    style.alignSelf = colAlign;
                    style.minHeight = getUnitVal(s.minHeight, s.minHeightUnit) || (isEmpty ? '100px' : 'auto');
                }

                return style;
            };

            const columnInnerStyle = (column, container) => {
                const s = column.settings;
                const containerAlign = getResponsiveVal(container?.settings || {}, 'alignItems', device.value) || 'stretch';
                const pTop = Number(s.paddingTop) || 0;
                const pBottom = Number(s.paddingBottom) || 0;
                
                const rBoxShadow = getResponsiveVal(s, 'boxShadow', device.value);
                let shadowStr = 'none';
                if (rBoxShadow) {
                    const inst = (getResponsiveVal(s, 'boxShadowStyle', device.value) || s.boxShadowStyle) === 'inner' ? 'inset ' : '';
                    const x  = getResponsiveVal(s, 'boxShadowPositionHorizontal', device.value) ?? s.boxShadowPositionHorizontal ?? 0;
                    const y  = getResponsiveVal(s, 'boxShadowPositionVertical',   device.value) ?? s.boxShadowPositionVertical   ?? 0;
                    const b  = getResponsiveVal(s, 'boxShadowBlurRadius',         device.value) ?? s.boxShadowBlurRadius         ?? 10;
                    const sp = getResponsiveVal(s, 'boxShadowSpreadRadius',       device.value) ?? s.boxShadowSpreadRadius       ?? 0;
                    const sc = getResponsiveVal(s, 'boxShadowColor',              device.value) || s.boxShadowColor || '#000000';
                    shadowStr = `${inst}${x}px ${y}px ${b}px ${sp}px ${sc}`;
                }

                const elements = column.elements || [];
                const isEmpty = elements.length === 0;
                const colAlignRaw = getResponsiveVal(s, 'alignment', device.value) || 'default';
                const colAlign = (colAlignRaw && colAlignRaw !== 'default') ? colAlignRaw : containerAlign;
                const isStretch = colAlign === 'stretch';
                // Match front-end: when column alignment is "default", the inner does NOT grow
                // vertically (no flex:1 1 auto) — it stays content-height even if the column is
                // stretched by a taller sibling. Front-end gates this on !$isDefaultAlign.
                const isDefaultAlign = !colAlignRaw || colAlignRaw === 'default';
                const containerHeight = getResponsiveVal(container?.settings || {}, 'height', device.value) || 'auto';
                const hasDefinedHeight = containerHeight === 'full' || containerHeight === 'custom';

                const rPT  = getResponsiveVal(s, 'paddingTop',       device.value); const rPTU  = getResponsiveVal(s, 'paddingTopUnit',    device.value) || s.paddingTopUnit    || 'px';
                const rPB  = getResponsiveVal(s, 'paddingBottom',    device.value); const rPBU  = getResponsiveVal(s, 'paddingBottomUnit', device.value) || s.paddingBottomUnit || 'px';
                const rPL  = getResponsiveVal(s, 'paddingLeft',      device.value); const rPLU  = getResponsiveVal(s, 'paddingLeftUnit',   device.value) || s.paddingLeftUnit   || 'px';
                const rPR  = getResponsiveVal(s, 'paddingRight',     device.value); const rPRU  = getResponsiveVal(s, 'paddingRightUnit',  device.value) || s.paddingRightUnit  || 'px';
                const rML  = getResponsiveVal(s, 'marginLeft',       device.value); const rMLU  = getResponsiveVal(s, 'marginLeftUnit',    device.value) || s.marginLeftUnit    || 'px';
                const rMR  = getResponsiveVal(s, 'marginRight',      device.value); const rMRU  = getResponsiveVal(s, 'marginRightUnit',   device.value) || s.marginRightUnit   || 'px';
                const style = {
                    backgroundColor: (!s.bgType || s.bgType === 'color') ? hexToRgba(getResponsiveVal(s, 'bgColor', device.value), getResponsiveVal(s, 'bgColorOpacity', device.value) !== undefined ? getResponsiveVal(s, 'bgColorOpacity', device.value) : 1) : 'transparent',
                    color: s.textColor || 'inherit',
                    paddingTop:    getUnitVal(rPT,  rPTU),
                    paddingLeft:   getUnitVal(rPL,  rPLU),
                    paddingRight:  getUnitVal(rPR,  rPRU),
                    marginLeft:    getUnitVal(rML,  rMLU),
                    marginRight:   getUnitVal(rMR,  rMRU),
                    minHeight: isEmpty ? `calc(100px + ${getUnitVal(rPT, rPTU) || '0px'} + ${getUnitVal(rPB, rPBU) || '0px'})` : 'auto',
                    maxHeight: s.maxHeight || undefined,
                    height: 'auto',
                    paddingBottom: getUnitVal(rPB,  rPBU),
                    flex: (isStretch && !isDefaultAlign) ? '1 1 auto' : '0 1 auto',
                    display: 'flex',
                    flexDirection: 'column',
                    borderTopWidth: getUnitVal(getResponsiveVal(s, 'borderSizeTop', device.value) ?? s.borderSizeTop) || '0px',
                    borderBottomWidth: getUnitVal(getResponsiveVal(s, 'borderSizeBottom', device.value) ?? s.borderSizeBottom) || '0px',
                    borderLeftWidth: getUnitVal(getResponsiveVal(s, 'borderSizeLeft', device.value) ?? s.borderSizeLeft) || '0px',
                    borderRightWidth: getUnitVal(getResponsiveVal(s, 'borderSizeRight', device.value) ?? s.borderSizeRight) || '0px',
                    borderStyle: 'solid',
                    borderColor: hexToRgba(getResponsiveVal(s, 'borderColor', device.value) || s.borderColor || '#eee', getResponsiveVal(s, 'borderColorOpacity', device.value) ?? s.borderColorOpacity ?? 1),
                    boxSizing: 'border-box',
                    borderTopLeftRadius: getUnitVal(getResponsiveVal(s, 'borderRadiusTopLeft', device.value) ?? s.borderRadiusTopLeft, getResponsiveVal(s, 'borderRadiusTopLeftUnit', device.value) || s.borderRadiusTopLeftUnit),
                    borderTopRightRadius: getUnitVal(getResponsiveVal(s, 'borderRadiusTopRight', device.value) ?? s.borderRadiusTopRight, getResponsiveVal(s, 'borderRadiusTopRightUnit', device.value) || s.borderRadiusTopRightUnit),
                    borderBottomRightRadius: getUnitVal(getResponsiveVal(s, 'borderRadiusBottomRight', device.value) ?? s.borderRadiusBottomRight, getResponsiveVal(s, 'borderRadiusBottomRightUnit', device.value) || s.borderRadiusBottomRightUnit),
                    borderBottomLeftRadius: getUnitVal(getResponsiveVal(s, 'borderRadiusBottomLeft', device.value) ?? s.borderRadiusBottomLeft, getResponsiveVal(s, 'borderRadiusBottomLeftUnit', device.value) || s.borderRadiusBottomLeftUnit),
                    boxShadow: shadowStr,
                    fontSize: getUnitVal(s.fontSize, s.fontSizeUnit),
                    fontWeight: s.fontWeight || undefined,
                    lineHeight: s.lineHeight || undefined,
                    letterSpacing: getUnitVal(s.letterSpacing, s.letterSpacingUnit),
                    textAlign: s.textAlign || undefined,
                    cursor: s.linkUrl ? 'pointer' : 'default'
                };
                // content layout — default is 'column' if not explicitly set
                const contentLayout = s.contentLayout || 'column';
                if (contentLayout === 'block') {
                    style.display = 'block';
                } else {
                    style.display = 'flex';
                    style.flexDirection = contentLayout === 'row' ? 'row' : 'column';
                    style.flexWrap = 'wrap';
                    const gW = s.gapWidth ? s.gapWidth + 'px' : '0px';
                    const gH = s.gapHeight ? s.gapHeight + 'px' : '0px';
                    if (s.gapWidth || s.gapHeight) style.gap = gH + ' ' + gW;
                    const respAlignH = getResponsiveVal(s, 'contentAlignH', device.value);
                    const respAlignV = getResponsiveVal(s, 'contentAlignV', device.value);
                    if (contentLayout === 'row') {
                        if (respAlignH) style.justifyContent = respAlignH;
                        if (respAlignV) style.alignItems = respAlignV;
                    } else {
                        if (respAlignV) style.justifyContent = respAlignV;
                        if (respAlignH) style.alignItems = respAlignH;
                    }
                }
                
                const rOverflow = getResponsiveVal(s, 'overflow', device.value) || s.overflow;
                if (rOverflow && rOverflow !== 'default') style.overflow = rOverflow;

                // Layered Background Logic (bgType controls which layer is active)
                const bgType     = s.bgType || 'color';
                const rBgColor   = getResponsiveVal(s, 'bgColor',       device.value);
                const rBgOpacity = getResponsiveVal(s, 'bgColorOpacity', device.value);
                const dynBg = dynBgUrl(s);
                const activeBgImage = dynBg || getResponsiveVal(s, 'bgImage', device.value);
                let bgImages = [];

                // Gradient — only when bgType is 'gradient'
                if (bgType === 'gradient' && s.bgGradientStartColor && s.bgGradientEndColor) {
                    const gType = s.bgGradientType || 'linear';
                    const angle = s.bgGradientAngle !== undefined ? s.bgGradientAngle + 'deg' : '180deg';
                    const start = hexToRgba(s.bgGradientStartColor, s.bgGradientStartOpacity !== undefined ? s.bgGradientStartOpacity : 1);
                    const end = hexToRgba(s.bgGradientEndColor, s.bgGradientEndOpacity !== undefined ? s.bgGradientEndOpacity : 1);
                    const startPos = s.bgGradientStartPosition !== undefined ? s.bgGradientStartPosition + '%' : '0%';
                    const endPos = s.bgGradientEndPosition !== undefined ? s.bgGradientEndPosition + '%' : '100%';
                    if (gType === 'linear') {
                        bgImages.push(`linear-gradient(${angle}, ${start} ${startPos}, ${end} ${endPos})`);
                    } else {
                        bgImages.push(`radial-gradient(circle, ${start} ${startPos}, ${end} ${endPos})`);
                    }
                }

                // BG Image — works when bgType is 'image' or 'gradient' (not 'color')
                if ((activeBgImage || s.bgImageDynamicSource) && bgType !== 'color') {
                    bgImages.push(activeBgImage ? `url('${activeBgImage}')` : DYN_BG_PLACEHOLDER);
                    style.backgroundPosition   = getResponsiveVal(s, 'bgImagePosition',  device.value) || 'center center';
                    style.backgroundRepeat     = getResponsiveVal(s, 'bgImageRepeat',    device.value) || 'no-repeat';
                    let _colBgSz = getResponsiveVal(s, 'bgImageSize', device.value) || 'cover';
                    if (s.bgImageDynamicSource && (!_colBgSz || _colBgSz === 'auto')) _colBgSz = 'cover';
                    style.backgroundSize       = _colBgSz;
                    style.backgroundAttachment = s.bgImageParallax === 'fixed' ? 'fixed' : 'scroll';
                    style.backgroundBlendMode  = getResponsiveVal(s, 'bgImageBlendMode', device.value) || 'normal';
                }

                if (bgImages.length > 0) {
                    style.backgroundImage = bgImages.join(', ');
                }

                // BG Color — only when bgType is 'color'
                if (bgType === 'color' && rBgColor) {
                    const rOpacityVal = (rBgOpacity !== undefined && rBgOpacity !== null && rBgOpacity !== '') ? rBgOpacity : 1;
                    style.backgroundColor = hexToRgba(rBgColor, rOpacityVal);
                }

                return style;
            };

            const showElementModal = ref(false);
            const elementModalTab = ref('design'); // design, library, nested, studio
            const elementModalRestricted = ref(false);
            const elementModalAllowedTabs = ref(['design', 'nested']);
            const currentTargetCi = ref(null);
            const currentTargetColi = ref(null);
            const currentTargetEli = ref(null);
            const currentTargetNcoli = ref(null);
            const currentTargetNeli = ref(null);

            const columnModalActiveTab = ref('columns');

            const openColumnModal = (index = null, type = 'new') => {
                columnModalTarget.value = index;
                columnModalType.value   = type;
                columnModalActiveTab.value = 'columns';
                showColumnModal.value   = true;
                fetchLibrary();
                fetchGlobalSections();
            };

            const addContainerFromColumnModal = (item) => {
                const copy = JSON.parse(JSON.stringify(item.data));
                assignNewIds(copy);
                const at = columnModalTarget.value !== null ? columnModalTarget.value : layout.value.length;
                layout.value.splice(at, 0, copy);
                showColumnModal.value = false;
                showToast('Container added from library!', 'success');
            };

            const addColumnFromColumnModal = (item) => {
                if (columnModalTarget.value === null) return;
                const copy = JSON.parse(JSON.stringify(item.data));
                assignNewIds(copy);
                layout.value[columnModalTarget.value].columns.push(copy);
                showColumnModal.value = false;
                showToast('Column added from library!', 'success');
            };

            const addElementFromElementModal = (item) => {
                const copy  = JSON.parse(JSON.stringify(item.data));
                assignNewIds(copy);
                const ci    = currentTargetCi.value;
                const coli  = currentTargetColi.value;
                const eli   = currentTargetEli.value;
                const ncoli = currentTargetNcoli.value;
                const neli  = currentTargetNeli.value;
                if (neli !== null) {
                    layout.value[ci].columns[coli].elements[eli].columns[ncoli].elements.splice(neli, 0, copy);
                } else if (eli !== null && ncoli !== null) {
                    layout.value[ci].columns[coli].elements[eli].columns[ncoli].elements.push(copy);
                } else if (eli !== null) {
                    layout.value[ci].columns[coli].elements.splice(eli, 0, copy);
                } else {
                    layout.value[ci].columns[coli].elements.push(copy);
                }
                showElementModal.value = false;
                showToast('Element added from library!', 'success');
            };

            const addNestedColumnFromElementModal = (item) => {
                const copy  = JSON.parse(JSON.stringify(item.data));
                assignNewIds(copy);
                const ci    = currentTargetCi.value;
                const coli  = currentTargetColi.value;
                const eli   = currentTargetEli.value;
                const ncoli = currentTargetNcoli.value;
                if (ci !== null && coli !== null && eli !== null) {
                    const nestedRow = layout.value[ci].columns[coli].elements[eli];
                    const at = ncoli !== null ? ncoli + 1 : nestedRow.columns.length;
                    nestedRow.columns.splice(at, 0, copy);
                }
                showElementModal.value = false;
                showToast('Nested column added from library!', 'success');
            };

            const openElementModal = (ci, coli = null, defaultTab = 'elements', restricted = false, eli = null, ncoli = null, neli = null, allowedTabs = ['elements', 'nested']) => {
                // Map 'design' tab from old templates to 'elements'
                if (defaultTab === 'design') defaultTab = 'elements';

                currentTargetCi.value = ci;
                currentTargetColi.value = coli;
                currentTargetEli.value = eli;
                currentTargetNcoli.value = ncoli;
                currentTargetNeli.value = neli;
                elementModalTab.value = defaultTab;
                elementModalRestricted.value = restricted;
                elementModalAllowedTabs.value = allowedTabs;
                showElementModal.value = true;
                fetchLibrary();
            };

            const selectNestedLayout = (layoutConfig) => {
                if (currentTargetCi.value === null) return;

                const layoutData = {
                    id: Date.now(),
                    type: 'row',
                    settings: {
                        paddingTop: 0, paddingBottom: 0, paddingLeft: 0, paddingRight: 0,
                        marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0
                    },
                    columns: layoutConfig.split('-').map((part, idx) => {
                        const [num, den] = part.split('/');
                        return { id: uid(), basis: ((num / den) * 100).toFixed(2) + '%', basis_tablet: null, basis_mobile: null, settings: makeColumnSettings(), elements: [] };
                    })
                };

                if (currentTargetNeli.value !== null) {
                    const nestedColumn = layout.value[currentTargetCi.value].columns[currentTargetColi.value].elements[currentTargetEli.value].columns[currentTargetNcoli.value];
                    nestedColumn.elements.splice(currentTargetNeli.value, 0, layoutData);
                } else if (currentTargetNcoli.value !== null) {
                    // Horizontal Expand: Add columns to the parent row
                    const nestedRow = layout.value[currentTargetCi.value].columns[currentTargetColi.value].elements[currentTargetEli.value];
                    nestedRow.columns.splice(currentTargetNcoli.value + 1, 0, ...layoutData.columns);
                } else if (currentTargetColi.value === null) {
                    // Top level container add
                    layout.value[currentTargetCi.value].columns.push(...layoutData.columns);
                } else if (currentTargetEli.value === null) {
                    // Main column end
                    const column = layout.value[currentTargetCi.value].columns[currentTargetColi.value];
                    column.elements.push(layoutData);
                } else if (elementModalRestricted.value) {
                    // Horizontal Expand: Add columns to existing row
                    const nestedRow = layout.value[currentTargetCi.value].columns[currentTargetColi.value].elements[currentTargetEli.value];
                    nestedRow.columns.push(...layoutData.columns);
                } else {
                    // Main column insert
                    const column = layout.value[currentTargetCi.value].columns[currentTargetColi.value];
                    column.elements.splice(currentTargetEli.value, 0, layoutData);
                }

                showElementModal.value = false;
                currentTargetCi.value = null;
                currentTargetColi.value = null;
                currentTargetEli.value = null;
                currentTargetNcoli.value = null;
                currentTargetNeli.value = null;
            };

            const getVisibilityClasses = (settings) => {
                if (!settings || !settings.visibility) return '';
                let classes = '';
                if (settings.visibility.mobile === false) classes += ' falcon-hide-mobile';
                if (settings.visibility.tablet === false) classes += ' falcon-hide-tablet';
                if (settings.visibility.desktop === false) classes += ' falcon-hide-desktop';
                if (settings.visibility.mobile === false && settings.visibility.tablet === false && settings.visibility.desktop === false) {
                    classes = ' falcon-hide-all';
                }
                return classes;
            };

            const getCanvasVisibilityStyle = (settings) => {
                if (!settings || !settings.visibility) return {};
                const hidden = settings.visibility[device.value] === false;
                return hidden ? { opacity: '0.4', outline: '2px dashed #fbbf24', outlineOffset: '-2px' } : {};
            };

            // Sample rows for the Product Meta canvas preview (real data shows on the frontend).
            const pmCanvasRows = (s) => {
                s = s || {};
                const vc = s.valueColor || '#111827';
                const rows = [];
                if (s.showPrice !== false)   rows.push({ label: s.priceLabel || 'Price:',        value: '$49.00',         color: vc,                       weight: 700 });
                if (s.showSku !== false)     rows.push({ label: s.skuLabel   || 'SKU:',          value: 'SAMPLE-SKU-001', color: vc,                       weight: 400 });
                if (s.showStock !== false)   rows.push({ label: s.stockLabel || 'Availability:', value: 'In stock',       color: s.instockColor || '#15803d', weight: 600 });
                if (s.showStockQty === true) rows.push({ label: s.qtyLabel   || 'In stock:',     value: '25',             color: vc,                       weight: 400 });
                if (s.showType === true)     rows.push({ label: s.typeLabel  || 'Type:',         value: 'simple',         color: vc,                       weight: 400 });
                return rows;
            };

            const addElement = (type) => {
                if (elementLocked(type)) {
                    showToast('This is a Pro element — upgrade to use it.', 'error');
                    return;
                }
                if (currentTargetCi.value === null || currentTargetColi.value === null) return;

                const newEl = {
                    id: Date.now(),
                    type: type,
                    settings: {
                        visibility: { mobile: true, tablet: true, desktop: true },
                        ...(type === 'counter' ? {
                            endValue: 100, startValue: 0, prefix: '', suffix: '', label: 'Happy Clients',
                            duration: 2000, decimals: 0, separator: '',
                            textAlign: 'center',
                            numberFontSize: '48px', numberFontWeight: '700',
                            numberColor: '#222222', numberFontFamily: 'inherit',
                            numberLineHeight: '1.1', numberLetterSpacing: '0px',
                            labelFontSize: '14px', labelFontWeight: '400', labelColor: '#666666',
                            labelFontFamily: 'inherit', labelLineHeight: '1.4', labelLetterSpacing: '0px', labelTextTransform: 'none',
                            icon: '', iconSize: 40, iconColor: '#2271b1',
                            marginTop: 0, marginTopUnit: 'px', marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'star_rating' ? {
                            rating: 4.5, maxStars: 5, label: '',
                            starSize: 24, starColor: '#f59e0b', emptyColor: '#d1d5db',
                            textAlign: 'center', gap: 4,
                            labelFontFamily: 'inherit', labelFontSize: '13px', labelFontWeight: '400',
                            labelLineHeight: '1.4', labelLetterSpacing: '0px', labelTextTransform: 'none',
                            labelColor: '#6b7280',
                            marginTop: 0, marginTopUnit: 'px', marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'gallery' ? {
                            images: [],
                            columns: 3, columnsTablet: 2, columnsMobile: 1,
                            gap: 8, aspectRatio: 'square', borderRadius: 0,
                            imgBorderWidth: 0, imgBorderStyle: 'solid', imgBorderColor: '#e2e8f0',
                            lightbox: true, hoverEffect: 'zoom',
                            captionAlign: 'center',
                            captionFontFamily: 'inherit', captionFontSize: '13px', captionFontWeight: '400',
                            captionLineHeight: '1.4', captionLetterSpacing: '0px', captionTextTransform: 'none',
                            captionColor: '#6b7280',
                            marginTop: 0, marginTopUnit: 'px', marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'heading' ? { title: 'New Heading', textAlign: 'left' } : {}),
                        ...(type === 'title' ? {
                            title: 'Title', titleColor: '#222', fontSize: 36, fontSizeUnit: 'px', fontWeight: '800', textAlign: 'center',
                            useLink: false, linkUrl: '', linkColor: '#2271b1', linkHoverColor: '#1a5a96',
                            separator: 'none', separatorColor: '#2271b1', dividerWidth: 60, dividerHeight: 3,
                            paddingTop: 20, paddingBottom: 20, marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0,
                            visibility: { mobile: true, tablet: true, desktop: true },
                            dynamic_source: '', link_dynamic_source: ''
                        } : {}),
                        ...(type === 'breadcrumb' ? {
                            showHome: true, homeLabel: 'Home', separator: '/', showCurrent: true,
                            fontFamily: 'inherit', fontSize: 14, fontSizeUnit: 'px', fontWeight: '400',
                            lineHeight: '', letterSpacing: '', textTransform: 'none',
                            color: '#6b7280', linkColor: '#6b7280', linkHoverColor: '#2271b1',
                            separatorColor: '#9ca3af', currentColor: '#111827',
                            textAlign: 'left',
                            marginTop: 0, marginRight: 0, marginBottom: 0, marginLeft: 0,
                            marginTopUnit: 'px', marginRightUnit: 'px', marginBottomUnit: 'px', marginLeftUnit: 'px',
                            paddingTop: 0, paddingRight: 0, paddingBottom: 0, paddingLeft: 0,
                            paddingTopUnit: 'px', paddingRightUnit: 'px', paddingBottomUnit: 'px', paddingLeftUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true }
                        } : {}),
                        ...(type === 'text' ? { content: '<p>New text here...</p>' } : {}),
                        ...(type === 'button' ? {
                            text: 'Click Me', url: '#', style: 'primary',
                            buttonStyle: 'default',
                            bgColor: '#2271b1', color: '#ffffff',
                            hoverColor: '#ffffff', hoverBgColor: '#1a5a96',
                            bgGradientStartColor: '#2271b1', bgGradientEndColor: '#1a5a96',
                            bgGradientHoverStartColor: '#1a5a96', bgGradientHoverEndColor: '#135e96',
                            bgGradientType: 'linear', bgGradientAngle: 180,
                            bgGradientStartPosition: 0, bgGradientEndPosition: 100,
                            dynamic_source: '', link_dynamic_source: '',
                        } : {}),
                        ...(type === 'image' ? { url: '', alt: '', linkUrl: '', linkTarget: '_self', dynamic_source: '', link_dynamic_source: '', aspectRatio: 'none', focusX: 50, focusY: 50 } : {}),
                        ...(type === 'icon_list' ? {
                            items: [
                                { id: Date.now() + '_1', icon: 'fa fa-check', iconColor: '', text: 'List item one',   link: '', linkTarget: '_self' },
                                { id: Date.now() + '_2', icon: 'fa fa-check', iconColor: '', text: 'List item two',   link: '', linkTarget: '_self' },
                                { id: Date.now() + '_3', icon: 'fa fa-check', iconColor: '', text: 'List item three', link: '', linkTarget: '_self' },
                            ],
                            defaultIcon: 'fa fa-check',
                            iconSize: 14, iconColor: '#2271b1', iconPosition: 'left',
                            gap: 10, itemSpacing: 10, textAlign: 'left',
                            textColor: '#333333', fontSize: 15, fontSizeUnit: 'px',
                            fontWeight: '400', fontFamily: 'inherit', lineHeight: '1.5',
                            marginTop: 0, marginTopUnit: 'px', marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'post_content' ? {
                            content_display: 'excerpt', excerptLength: 120, stripHtml: true,
                            textAlign: 'left', fontFamily: 'inherit', fontSize: 13, fontSizeUnit: 'px',
                            fontWeight: '400', lineHeight: '1.6', letterSpacing: 0, textTransform: 'none',
                            color: '#6b7280',
                            marginTop: 0, marginRight: 0, marginBottom: 8, marginLeft: 0,
                            marginTopUnit: 'px', marginRightUnit: 'px', marginBottomUnit: 'px', marginLeftUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true }
                        } : {}),
                        ...(type === 'post_meta' ? {
                            metaOrder: ['categories', 'tags', 'author', 'date', 'reading_time'],
                            showCategories: true, categoryTaxonomy: 'category',
                            showTags: false, tagTaxonomy: 'tag',
                            showAuthor: true, showDate: true, showReadingTime: false,
                            hideEmptyTerms: true,
                            dateFormat: 'M j, Y', separator: '·', layout: 'inline', showIcons: true,
                            metaAlign: 'left',
                            fontSize: 13, fontSizeUnit: 'px', fontWeight: '400', color: '#6b7280', linkColor: '#374151',
                            gap: 12, gapUnit: 'px',
                            marginTop: 0, marginBottom: 8, marginTopUnit: 'px', marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true }
                        } : {}),
                        ...(type === 'product_meta' ? {
                            showPrice: true, showSku: true, showStock: true,
                            showStockQty: false, showType: false,
                            showLabels: true,
                            priceLabel: 'Price:', skuLabel: 'SKU:', stockLabel: 'Availability:',
                            qtyLabel: 'In stock:', typeLabel: 'Type:',
                            layout: 'stacked', separator: '·', metaAlign: 'left',
                            labelColor: '#6b7280', valueColor: '#111827', saleColor: '#e02b2b',
                            instockColor: '#15803d', outofstockColor: '#b91c1c',
                            fontSize: 14, fontSizeUnit: 'px', fontWeight: '400',
                            gap: 8, gapUnit: 'px',
                            marginTop: 0, marginBottom: 0, marginTopUnit: 'px', marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true }
                        } : {}),
                        ...(type === 'ticker' ? {
                            items: [
                                { id: Date.now() + '_1', text: 'Breaking news item one', url: '' },
                                { id: Date.now() + '_2', text: 'Breaking news item two', url: '' },
                                { id: Date.now() + '_3', text: 'Breaking news item three', url: '' },
                            ],
                            label: 'LIVE',
                            separator: '•',
                            itemSpacing: 12,
                            direction: 'left',
                            speed: 50,
                            pauseOnHover: true,
                            bgColor: '#1e3a8a',
                            textColor: '#ffffff',
                            labelBgColor: '#ef4444',
                            labelTextColor: '#ffffff',
                            fontSize: '14px',
                            fontWeight: '500',
                            height: 44,
                            borderRadius: 0,
                            marginTop: 0, marginTopUnit: 'px',
                            marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'spacer' ? {
                            style: 'default',
                            flexGrow: 0,
                            marginTop: 0, marginTopUnit: 'px',
                            marginBottom: 0, marginBottomUnit: 'px',
                            separatorWidth: 100, separatorWidthUnit: '%',
                            alignment: 'center',
                            borderSize: 1,
                            separatorColor: '#cccccc',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'html' ? {
                            htmlContent: '',
                            marginTop: 0, marginTopUnit: 'px',
                            marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'icon_box' ? {
                            icon: 'fas fa-star',
                            title: 'Icon Box',
                            description: 'Add a short description for this icon box.',
                            linkUrl: '', linkTarget: '_self',
                            layout: 'top', alignment: 'center',
                            iconSize: 40, iconSizeUnit: 'px',
                            iconColor: '#2271b1',
                            iconBgColor: '', iconBgColorOpacity: 1,
                            iconBorderRadius: 50, iconSpacing: 16, iconPadding: 0,
                            titleTag: 'h3',
                            titleFontFamily: 'inherit',
                            titleFontSize: 20, titleFontSizeUnit: 'px',
                            titleFontWeight: '600', titleColor: '#222222', titleSpacing: 8,
                            titleLineHeight: 1.3, titleLetterSpacing: '0px', titleTextTransform: 'none',
                            descFontFamily: 'inherit',
                            descFontSize: 14, descFontSizeUnit: 'px',
                            descFontWeight: '400', descColor: '#666666', descLineHeight: 1.6,
                            descLetterSpacing: '0px', descTextTransform: 'none',
                            marginTop: 0, marginTopUnit: 'px',
                            marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'accordion' ? {
                            items: [
                                { id: Date.now() + '_1', title: 'Accordion Item 1', content: '<p>Add your content here.</p>' },
                                { id: Date.now() + '_2', title: 'Accordion Item 2', content: '<p>Add your content here.</p>' },
                            ],
                            defaultOpen: 0, allowMultiple: false,
                            iconType: 'plus', iconPosition: 'right',
                            titleFontSize: 15, titleFontWeight: '600',
                            titleFontFamily: 'inherit', titleLetterSpacing: '0px', titleLineHeight: 1.4, titleTextTransform: 'none',
                            titleColor: '#222222', titleBgColor: '#f8fafc',
                            titleActiveBgColor: '#2271b1', titleActiveColor: '#ffffff', titlePadding: 16,
                            contentFontSize: 14, contentFontFamily: 'inherit', contentLetterSpacing: '0px', contentLineHeight: 1.6,
                            contentColor: '#555555', contentBgColor: '#ffffff', contentPadding: 16,
                            borderColor: '#e2e8f0', borderRadius: 8, itemGap: 8,
                            marginTop: 0, marginTopUnit: 'px', marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'tabs' ? {
                            items: [
                                { id: Date.now() + '_1', label: 'Tab One', content: '<p>Tab one content goes here.</p>' },
                                { id: Date.now() + '_2', label: 'Tab Two', content: '<p>Tab two content goes here.</p>' },
                            ],
                            defaultActive: 0, style: 'underline', alignment: 'left',
                            tabFontSize: 14, tabFontWeight: '500',
                            tabFontFamily: 'inherit', tabLetterSpacing: '0px',
                            tabColor: '#666666', activeColor: '#2271b1',
                            contentFontSize: 14, contentFontFamily: 'inherit', contentLetterSpacing: '0px', contentLineHeight: 1.6,
                            contentColor: '#555555', contentBgColor: '#ffffff', contentPadding: 20,
                            borderColor: '#e2e8f0', borderRadius: 4,
                            marginTop: 0, marginTopUnit: 'px', marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'video' ? {
                            url: '',
                            videoSource: 'youtube',
                            aspectRatio: '16-9',
                            controls: true,
                            autoplay: false,
                            muted: false,
                            loop: false,
                            marginTop: 0, marginTopUnit: 'px',
                            marginBottom: 0, marginBottomUnit: 'px',
                            cssClass: '', cssId: '',
                            visibility: { mobile: true, tablet: true, desktop: true },
                        } : {}),
                        ...(type === 'card' ? {
                            post_card_id: '',
                            content_source: 'posts',
                            post_type: 'post',
                            posts_by: 'all',
                            posts_by_value: '',
                            posts_by_cf_key: '',
                            posts_by_cf_value: '',
                            post_status: ['publish'],
                            hide_out_of_stock: false,
                            posts_count: 6,
                            posts_offset: 0,
                            order_by: 'created_at',
                            order: 'desc',
                            pagination_type: 'none',
                            nothing_found_message: 'No posts found.',
                            layout: 'grid',
                            card_alignment: 'stretch',
                            columns: 3, columns_tablet: 2, columns_mobile: 1,
                            column_spacing: 24,
                            row_spacing: 24,
                            marginTop: 0, marginRight: 0, marginBottom: 0, marginLeft: 0,
                            marginTopUnit: 'px', marginRightUnit: 'px', marginBottomUnit: 'px', marginLeftUnit: 'px',
                            visibility: { mobile: true, tablet: true, desktop: true },
                            cssClass: '', cssId: '',
                            taxonomy_slug: '',
                            taxonomy_include: [],
                            taxonomy_exclude: [],
                            carousel_autoplay: false,
                            carousel_autoplay_speed: 3000,
                            carousel_arrows: true,
                            carousel_dots: true,
                            carousel_loop: false,
                            items_per_slide: 1,
                            items_per_slide_tablet: 0,
                            items_per_slide_mobile: 0,
                        } : {}),
                    }
                };

                // Apply custom element defaults (both old fields + new params format)
                const customDef = Object.values(customElements).find(e => e.type === type);
                if (customDef) {
                    if (customDef.fields) {
                        Object.entries(customDef.fields).forEach(([key, field]) => {
                            if (field.default !== undefined) newEl.settings[key] = field.default;
                        });
                    }
                    if (customDef.params) {
                        customDef.params.forEach(param => {
                            const k = customParamKey(param);
                            if (k) newEl.settings[k] = customParamDefault(param);
                        });
                    }
                }

                // Initialize transient UI state for specific elements
                if (type === 'menu') {
                    newEl._mobileMenuOpen = false;
                    newEl._hoveredIdx = null;
                    newEl._hoveredSubIdx = null;
                    newEl._hoveredGSubIdx = null;
                }

                console.log('Adding element:', type, {
                    ci: currentTargetCi.value,
                    coli: currentTargetColi.value,
                    eli: currentTargetEli.value,
                    ncoli: currentTargetNcoli.value,
                    neli: currentTargetNeli.value
                });

                if (currentTargetNeli.value !== null) {
                    // Nested insertion inside a nested column at specific index
                    const row = layout.value[currentTargetCi.value].columns[currentTargetColi.value].elements[currentTargetEli.value];
                    if (row && row.columns && row.columns[currentTargetNcoli.value]) {
                        row.columns[currentTargetNcoli.value].elements.splice(currentTargetNeli.value, 0, newEl);
                    } else {
                        console.error('Failed to find nested column for insertion', { row });
                    }
                } else if (currentTargetEli.value !== null && currentTargetNcoli.value !== null) {
                    // Nested insertion inside a nested column (append)
                    const row = layout.value[currentTargetCi.value].columns[currentTargetColi.value].elements[currentTargetEli.value];
                    if (row && row.columns && row.columns[currentTargetNcoli.value]) {
                        row.columns[currentTargetNcoli.value].elements.push(newEl);
                    } else {
                        console.error('Failed to find nested column for append', { row });
                    }
                } else if (currentTargetEli.value !== null) {
                    // Insertion at specific index in main column
                    const column = layout.value[currentTargetCi.value].columns[currentTargetColi.value];
                    if (column && column.elements) {
                        column.elements.splice(currentTargetEli.value, 0, newEl);
                    }
                } else {
                    // Regular insertion at end of column
                    const column = layout.value[currentTargetCi.value]?.columns[currentTargetColi.value];
                    if (column && column.elements) {
                        column.elements.push(newEl);
                    }
                }
                showElementModal.value = false;
            };



            // Inject per-column mobile width CSS using .canvas-container.mobile .column-outer.col-{id}
            // (specificity 0,3,0) to override the global .canvas-container.mobile .column-outer (0,2,0) rule
            (() => {
                const styleEl = document.createElement('style');
                styleEl.id = 'lazy-canvas-col-widths';
                document.head.appendChild(styleEl);

                const traverseColumns = (items, result = []) => {
                    items?.forEach(item => {
                        if (item.columns) {
                            item.columns.forEach(col => {
                                result.push(col);
                                if (col.elements) traverseColumns(col.elements, result);
                            });
                        }
                    });
                    return result;
                };

                watchEffect(() => {
                    let css = '';
                    traverseColumns(layout.value).forEach(col => {
                        const mobileBasis = col.basis_mobile || col.basis_tablet;
                        if (!mobileBasis) return;
                        const effective = col.basis_mobile || col.basis_tablet || col.basis || '100%';
                        const mw = effective === 'auto' ? 'none' : effective;
                        css += `.canvas-container.mobile .column-outer.col-${col.id}{flex-basis:${effective}!important;max-width:${mw}!important;width:${effective}!important;}`;
                    });
                    styleEl.textContent = css;
                });
            })();

            // ── Builder Library ──────────────────────────────────────────────────
            const showLibraryModal   = ref(false);
            const libraryActiveTab   = ref('containers');
            const libraryNewName     = ref('');
            const isSavingToLibrary  = ref(false);
            const saveAsGlobalChecked = ref(false);
            const libraryContext     = ref(null);
            const libraryItems       = ref({ containers: [], columns: [], nested_columns: [], elements: [] });
            const globalSections     = ref([]);
            const showGlobalModal    = ref(false);
            const globalModalName    = ref('');
            const globalModalCi      = ref(null);
            const isSavingGlobal     = ref(false);
            const postCardsList      = ref(window.falconPostCards || []);
            const recentPosts        = ref(window.falconRecentPosts || []);
            const falconTaxonomies     = ref(window.falconTaxonomies    || []);
            const falconTaxonomyTerms  = ref(window.falconTaxonomyTerms || {});
            const falconCptList        = ref(window.falconCptList        || []);
            const falconCptTaxonomies  = ref(window.falconCptTaxonomies  || { post: ['category','tag'] });
            const cardPreviewCache   = reactive({});
            const postCardsMap       = computed(() => {
                const m = {};
                postCardsList.value.forEach(c => { m[c.id] = c.name; });
                return m;
            });

            const getCardElementsFlat = (cardId) => {
                if (!cardId) return [];
                const card = postCardsList.value.find(c => c.id === cardId);
                if (!card?.config?.layout) return [];
                const elements = [];
                const traverse = (items) => {
                    if (!Array.isArray(items)) return;
                    for (const item of items) {
                        if (Array.isArray(item.columns)) {
                            for (const col of item.columns) traverse(col.elements || []);
                        } else if (item.type) {
                            elements.push(item);
                        }
                    }
                };
                traverse(card.config.layout);
                return elements;
            };

            const execCardPreviewScripts = (elId) => {
                const container = document.querySelector('[data-card-preview-id="' + elId + '"]');
                if (!container) return;
                container.querySelectorAll('script').forEach(old => {
                    const s = document.createElement('script');
                    s.textContent = old.textContent;
                    old.parentNode?.replaceChild(s, old);
                });
                // Make carousel arrows & dots clickable in the canvas without triggering element selection.
                // pointer-events:none on the container prevents general clicks; children with auto override it.
                nextTick(() => {
                    container.querySelectorAll('[onclick*="lzSlider"]').forEach(btn => {
                        btn.style.pointerEvents = 'auto';
                        btn.addEventListener('click', e => e.stopPropagation(), true);
                    });
                });
            };

            const fetchCardPreview = async (el) => {
                if (!el || el.type !== 'card') return;
                const elId = el.id;
                cardPreviewCache[elId] = { loading: true, html: cardPreviewCache[elId]?.html || '' };
                try {
                    const res = await fetch('{{ route("admin.falcon-builder.card-preview") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ settings: el.settings, device: device.value })
                    });
                    const data = await res.json();
                    cardPreviewCache[elId] = { loading: false, html: data.success ? data.html : '' };
                    if (data.success) nextTick(() => execCardPreviewScripts(elId));
                } catch(e) {
                    cardPreviewCache[elId] = { loading: false, html: '' };
                }
            };

            const toggleTaxTerm = (settingKey, slug) => {
                if (!editingElement.value) return;
                const arr = editingElement.value.settings[settingKey];
                if (!Array.isArray(arr)) { editingElement.value.settings[settingKey] = [slug]; return; }
                const idx = arr.indexOf(slug);
                if (idx === -1) arr.push(slug);
                else arr.splice(idx, 1);
            };

            const cardCategoryTerms = computed(() => {
                if (!editingElement.value || editingElement.value.type !== 'card') return [];
                const postType = editingElement.value.settings.post_type || 'post';
                const taxSlugs = falconCptTaxonomies.value[postType] || ['category'];
                const result = [];
                for (const slug of taxSlugs) {
                    if (slug === 'tag') continue;
                    const terms = falconTaxonomyTerms.value[slug] || [];
                    result.push(...terms);
                }
                return result;
            });

            const cardTagTerms = computed(() => falconTaxonomyTerms.value['tag'] || []);

            const cardTaxonomiesByPostType = computed(() => {
                if (!editingElement.value || editingElement.value.type !== 'card') return falconTaxonomies.value;
                const postType = editingElement.value.settings.post_type || 'post';
                const allowed = falconCptTaxonomies.value[postType];
                if (allowed === undefined) return falconTaxonomies.value;
                return falconTaxonomies.value.filter(t => allowed.includes(t.slug));
            });

            const postsbyValueArr = computed({
                get() {
                    const v = editingElement.value?.settings?.posts_by_value || '';
                    if (Array.isArray(v)) return v;
                    return v ? v.split(',').map(s => s.trim()).filter(Boolean) : [];
                },
                set(arr) {
                    if (editingElement.value?.settings) {
                        editingElement.value.settings.posts_by_value = Array.isArray(arr) ? arr.join(',') : (arr || '');
                    }
                }
            });

            const libraryTabs = [
                { key: 'containers',      label: 'Containers',     icon: 'fa fa-table-columns' },
                { key: 'columns',         label: 'Columns',        icon: 'fa fa-columns' },
                { key: 'nested_columns',  label: 'Nested Columns', icon: 'fa fa-layer-group' },
                { key: 'elements',        label: 'Elements',       icon: 'fa fa-cube' },
                { key: 'global_sections', label: 'Global',         icon: 'fa fa-globe' },
            ];

            const libraryCurrentItems  = computed(() => {
                if (libraryActiveTab.value === 'global_sections') return globalSections.value;
                return libraryItems.value[libraryActiveTab.value] || [];
            });
            const libraryActiveTabLabel = computed(() => libraryTabs.find(t => t.key === libraryActiveTab.value)?.label || '');
            const libraryTabIcon       = computed(() => libraryTabs.find(t => t.key === libraryActiveTab.value)?.icon || 'fa fa-cube');
            const libraryCanSave       = computed(() => libraryContext.value?.type === libraryActiveTab.value);

            const fetchLibrary = async () => {
                try {
                    const res  = await fetch('{{ route("admin.falcon-builder.library.index") }}');
                    const data = await res.json();
                    libraryItems.value = { containers: [], columns: [], nested_columns: [], elements: [], ...data };
                } catch (e) { console.error('Library fetch failed', e); }
            };

            // ── Global Sections ──────────────────────────────────────────────────

            const fetchGlobalSections = async () => {
                try {
                    const res  = await fetch('{{ route("admin.falcon-builder.global-sections.list") }}');
                    const data = await res.json();
                    globalSections.value = Array.isArray(data) ? data : [];
                } catch (e) { console.error('Global sections fetch failed', e); }
            };

            const syncGlobalColumnsFromStore = () => {
                layout.value.forEach(container => {
                    if (container.settings?.global_id) {
                        const gs = globalSections.value.find(s => s.id === container.settings.global_id);
                        if (gs?.data?.columns) {
                            container.columns = JSON.parse(JSON.stringify(gs.data.columns));
                        }
                    }
                });
            };

            const openGlobalModal = (ci) => {
                globalModalCi.value = ci;
                globalModalName.value = '';
                showGlobalModal.value = true;
            };

            const saveAsGlobal = async () => {
                if (!window.falconBuilderPro) {
                    showToast('Global sections are a Pro feature — upgrade to save one.', 'error');
                    return;
                }
                if (!globalModalName.value.trim() || isSavingGlobal.value) return;
                const ci = globalModalCi.value;
                if (ci === null || !layout.value[ci]) return;

                isSavingGlobal.value = true;
                try {
                    const containerData = JSON.parse(JSON.stringify(layout.value[ci]));
                    const res = await fetch('{{ route("admin.falcon-builder.global-sections.save") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ name: globalModalName.value.trim(), data: containerData })
                    });
                    const result = await res.json();
                    if (result.success) {
                        layout.value[ci].settings.global_id = result.section.id;
                        globalSections.value.unshift(result.section);
                        showGlobalModal.value = false;
                        showToast('Section saved as global!', 'success');
                    } else {
                        showToast('Failed to save global section.', 'error');
                    }
                } catch (e) {
                    showToast('Save failed!', 'error');
                } finally {
                    isSavingGlobal.value = false;
                }
            };

            const unlinkGlobal = (ci) => {
                if (ci === null || !layout.value[ci]) return;
                delete layout.value[ci].settings.global_id;
                showToast('Section unlinked from global.', 'success');
            };

            const ctxSaveAsGlobal = () => {
                const m = ctxMenu.value;
                if (m.type !== 'container') return;
                openLibraryModal('containers', m.ci);
                closeCtxMenu();
            };

            const insertGlobalSection = (section) => {
                const copy = JSON.parse(JSON.stringify(section.data));
                assignNewIds(copy);
                copy.settings = copy.settings || {};
                copy.settings.global_id = section.id;
                const at = columnModalTarget.value !== null ? columnModalTarget.value : layout.value.length;
                layout.value.splice(at, 0, copy);
                showToast('Global section added!', 'success');
                showColumnModal.value = false;
            };

            const deleteGlobalSection = async (id) => {
                try {
                    const res = await fetch(`{{ url('admin/falcon-builder/global-sections') }}/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const result = await res.json();
                    if (result.success) {
                        globalSections.value = globalSections.value.filter(s => s.id !== id);
                        showToast('Global section deleted.', 'success');
                    }
                } catch (e) { showToast('Delete failed!', 'error'); }
            };

            // ── End Global Sections ──────────────────────────────────────────────

            const openLibraryModal = (type, ci = null, coli = null, eli = null, ncoli = null, nestedEli = null) => {
                libraryContext.value  = { type, ci, coli, eli, ncoli, nestedEli };
                libraryActiveTab.value = type;
                libraryNewName.value  = '';
                saveAsGlobalChecked.value = false;
                showLibraryModal.value = true;
                fetchLibrary();
                fetchGlobalSections();
            };

            const saveToLibrary = async () => {
                if (!libraryNewName.value.trim() || isSavingToLibrary.value) return;
                const ctx = libraryContext.value;
                if (!ctx) return;

                let data = null;
                try {
                    if (ctx.type === 'containers') {
                        data = layout.value[ctx.ci];
                    } else if (ctx.type === 'columns') {
                        data = layout.value[ctx.ci].columns[ctx.coli];
                    } else if (ctx.type === 'nested_columns') {
                        data = layout.value[ctx.ci].columns[ctx.coli].elements[ctx.eli].columns[ctx.ncoli];
                    } else if (ctx.type === 'elements') {
                        data = ctx.ncoli !== null
                            ? layout.value[ctx.ci].columns[ctx.coli].elements[ctx.eli].columns[ctx.ncoli].elements[ctx.nestedEli]
                            : layout.value[ctx.ci].columns[ctx.coli].elements[ctx.eli];
                    }
                } catch (e) { data = null; }

                if (!data) { showToast('Could not read item data.', 'error'); return; }

                isSavingToLibrary.value = true;
                try {
                    const name = libraryNewName.value.trim();
                    const res    = await fetch('{{ route("admin.falcon-builder.library.save") }}', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body:    JSON.stringify({ type: ctx.type, name, data: JSON.parse(JSON.stringify(data)) })
                    });
                    const result = await res.json();
                    if (result.success) {
                        libraryItems.value[ctx.type].unshift(result.item);
                        libraryNewName.value = '';

                        // Also save as global section when checkbox is checked (containers only)
                        if (saveAsGlobalChecked.value && ctx.type === 'containers' && ctx.ci !== null) {
                            try {
                                const gsRes = await fetch('{{ route("admin.falcon-builder.global-sections.save") }}', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                                    body: JSON.stringify({ name, data: JSON.parse(JSON.stringify(data)) })
                                });
                                const gsResult = await gsRes.json();
                                if (gsResult.success) {
                                    layout.value[ctx.ci].settings.global_id = gsResult.section.id;
                                    globalSections.value.unshift(gsResult.section);
                                    showToast('Saved to library and as global section!', 'success');
                                } else {
                                    showToast('Saved to library (global save failed).', 'success');
                                }
                            } catch (e) {
                                showToast('Saved to library (global save failed).', 'success');
                            }
                            saveAsGlobalChecked.value = false;
                        } else {
                            showToast('Saved to library!', 'success');
                        }
                    }
                } catch (e) { showToast('Save to library failed!', 'error'); }
                finally { isSavingToLibrary.value = false; }
            };

            const assignNewIds = (obj) => {
                if (!obj || typeof obj !== 'object') return;
                if (Array.isArray(obj)) { obj.forEach(assignNewIds); return; }
                if ('id' in obj) obj.id = Date.now() + Math.floor(Math.random() * 1e9);
                Object.values(obj).forEach(assignNewIds);
            };

            const insertFromLibrary = (item) => {
                const ctx  = libraryContext.value;
                const copy = JSON.parse(JSON.stringify(item.data));
                assignNewIds(copy);

                const tab = libraryActiveTab.value;
                if (tab === 'containers') {
                    const at = (ctx?.ci !== null && ctx?.ci !== undefined) ? ctx.ci + 1 : layout.value.length;
                    layout.value.splice(at, 0, copy);
                } else if (tab === 'columns') {
                    if (ctx?.ci !== null && ctx?.coli !== null) {
                        layout.value[ctx.ci].columns.splice(ctx.coli + 1, 0, copy);
                    } else if (ctx?.ci !== null) {
                        layout.value[ctx.ci].columns.push(copy);
                    }
                } else if (tab === 'nested_columns') {
                    if (ctx?.ci !== null && ctx?.coli !== null && ctx?.eli !== null && ctx?.ncoli !== null) {
                        layout.value[ctx.ci].columns[ctx.coli].elements[ctx.eli].columns.splice(ctx.ncoli + 1, 0, copy);
                    }
                } else if (tab === 'elements') {
                    if (ctx?.ncoli !== null && ctx?.ncoli !== undefined) {
                        const target = layout.value[ctx.ci].columns[ctx.coli].elements[ctx.eli].columns[ctx.ncoli].elements;
                        const at     = (ctx.nestedEli !== null && ctx.nestedEli !== undefined) ? ctx.nestedEli + 1 : target.length;
                        target.splice(at, 0, copy);
                    } else if (ctx?.ci !== null && ctx?.coli !== null) {
                        const target = layout.value[ctx.ci].columns[ctx.coli].elements;
                        const at     = (ctx.eli !== null && ctx.eli !== undefined) ? ctx.eli + 1 : target.length;
                        target.splice(at, 0, copy);
                    }
                }

                showToast('Added from library!', 'success');
                showLibraryModal.value = false;
            };

            const insertGlobalFromLibrary = (section) => {
                const ctx  = libraryContext.value;
                const copy = JSON.parse(JSON.stringify(section.data));
                assignNewIds(copy);
                copy.settings = copy.settings || {};
                copy.settings.global_id = section.id;
                const at = (ctx?.ci !== null && ctx?.ci !== undefined) ? ctx.ci + 1 : layout.value.length;
                layout.value.splice(at, 0, copy);
                showToast('Global section added!', 'success');
                showLibraryModal.value = false;
            };

            const deleteFromLibrary = async (id) => {
                const type = libraryActiveTab.value;
                try {
                    const res    = await fetch(`{{ url('admin/falcon-builder/library') }}/${type}/${id}`, {
                        method:  'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const result = await res.json();
                    if (result.success) {
                        libraryItems.value[type] = libraryItems.value[type].filter(i => i.id !== id);
                        showToast('Deleted from library!', 'success');
                    }
                } catch (e) { showToast('Delete failed!', 'error'); }
            };
            // ── End Builder Library ──────────────────────────────────────────────

            // ── Undo / Redo Functions ────────────────────────────────────────────
            function undo() {
                if (!canUndo.value) return;
                _isUndoRedo = true;
                historyIndex.value--;
                layout.value = JSON.parse(JSON.stringify(_historyStack[historyIndex.value]));
                _isUndoRedo = false;
            }

            function redo() {
                if (!canRedo.value) return;
                _isUndoRedo = true;
                historyIndex.value++;
                layout.value = JSON.parse(JSON.stringify(_historyStack[historyIndex.value]));
                _isUndoRedo = false;
            }

            onMounted(() => {
                document.addEventListener('keydown', (e) => {
                    const tag = document.activeElement?.tagName;
                    if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable) return;
                    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
                        e.preventDefault();
                        undo();
                    }
                    if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) {
                        e.preventDefault();
                        redo();
                    }
                });
            });
            // ── End Undo / Redo ──────────────────────────────────────────────────

            return {
                layout, isPreview, isSaving, isDirty, activeTab, activePanelTab, activeColPanelTab, device, activeResponsiveMenu, availableElements,
                activeCi, editingCi, activeColi, activeColCi, editingContext,
                clearEditingContext, setEditingContext,
                showColumnModal, columnModalType, columnModalActiveTab, columnLayouts, openColumnModal, selectLayout, addContainerFromColumnModal, addColumnFromColumnModal,
                addElementFromElementModal, addNestedColumnFromElementModal,
                showElementModal, elementModalTab, elementModalRestricted, elementModalAllowedTabs, openElementModal, selectNestedLayout,
                editingColumn, editingElement,
                addContainer, addColumn, addNestedColumn, addElement, duplicateContainer, duplicateColumn, duplicateElement, duplicateNestedColumn, duplicateNestedRow, duplicateNestedElement, saveLayout, openMediaModal, openMediaModalForTarget, openGalleryImageMedia, openGalleryBulkMedia, galleryDragStart, galleryDrop, openColorPicker, falconEyeDropper, falconColorDisplay, falconColorInput,
                isDragging, isColumnDrag, dragType, dragSource, dragCi, dragColi, dragEli, dragNcoli, startDrag,
                onDragStart, onDragEnd, onDragOver, onDrop, dragTarget, dragPosition,
                canvasStyle, containerStyle, containerInnerStyle, columnOuterStyle, columnInnerStyle, formatBasisToFraction, updateBasis, hexToRgba, getUnitVal,
                getVisibilityClasses, getCanvasVisibilityStyle, pmCanvasRows, getResponsiveVal, setResponsiveVal, resetResponsiveVal,
                cardPerView, cardPreviewCols, cardPreviewCount,
                searchColumnQuery, searchElementQuery, filteredColumnLayouts, filteredNestedColumnLayouts, filteredAvailableElements, elementLocked, isElementPro,
                shouldShowGuide,
                toasts, showToast,
                showLibraryModal, libraryActiveTab, libraryNewName, isSavingToLibrary, saveAsGlobalChecked, libraryItems, libraryContext,
                postCardsList, postCardsMap, recentPosts, getCardElementsFlat,
                falconTaxonomies, falconTaxonomyTerms, falconCptList, falconCptTaxonomies, cardPreviewCache, fetchCardPreview,
                cardCategoryTerms, cardTagTerms, postsbyValueArr, cardTaxonomiesByPostType,
                libraryTabs, libraryCurrentItems, libraryActiveTabLabel, libraryTabIcon, libraryCanSave,
                openLibraryModal, saveToLibrary, insertFromLibrary, insertGlobalFromLibrary, deleteFromLibrary,
                hoveredType, hoveredCi, hoveredColi, hoveredEli, hoveredNcoli, setHover,
                navDragSrc, navDragOver, navDragStart, navDragEnd, navDragOverHandler, navDrop,
                ctxMenu, ctxClipboard, ctxMenuTitle, openCtxMenu, closeCtxMenu, ctxEdit, ctxSave, ctxClone, ctxRemove, ctxCopy, ctxPaste, ctxSaveAsGlobal,
                globalSections, showGlobalModal, globalModalName, isSavingGlobal, openGlobalModal, saveAsGlobal, unlinkGlobal, insertGlobalSection, deleteGlobalSection,
                themeBodyFont, themeHeadingFont, builderFontGroups, builderFonts: BUILDER_FONTS,
                titleFontVariants, loadBuilderFont,
                undo, redo, canUndo, canRedo,
                applyButtonSize, searchIconQuery, filteredIcons, iconTabCount, selectIcon, activeIconTab, clearColorField, activeAccordionItem, activeTabsItem, activeIconListItem,
                falconMenuData: reactive(window.falconMenuData || {}),
                falconMenusList: window.falconMenusList || {},
                postCardMode,
                customElements,
                getCustomElementPreviewText,
                getCustomElementPreviewColor,
                getCustomElementPreviewFields,
                getCustomElementRender,
                customFieldVisible,
                autosaveStatus, showRevisions, revisionList, isRestoring, autosaveBanner,
                openRevisions, restoreRevision, deleteRevisionItem, dismissAutosaveBanner,
                dynMenu, dynAcptSlug, openDynMenu, insertDynToken, insertDynAcpt,
                dynSrcMenu, dynSrcDefs, getDynSrcDef, getDynSrcGroups, dynSrcPreview, openDynSrcMenu, selectDynSource, clearDynSource
            };
        }
    }).directive('tomselect', {
        mounted(el, binding) {
            el._tsCfg = binding.value || {};
            const ts = new TomSelect(el, {
                plugins: el.multiple ? ['remove_button'] : [],
                maxItems: el.multiple ? null : 1,
                create: false,
                placeholder: el._tsCfg.placeholder || '',
                dropdownParent: 'body',
                onChange(val) {
                    if (typeof el._tsCfg.onChange === 'function') {
                        // For multi-select, always use ts.getValue() to get the FULL selection array
                        // (onChange `val` is only the last changed item, not all selected items)
                        el._tsCfg.onChange(el.multiple ? ts.getValue() : val);
                    }
                }
            });
            el._tomselect = ts;
            const initVal = el._tsCfg.value;
            if (initVal !== undefined && initVal !== null) ts.setValue(initVal, true);
        },
        updated(el, binding) {
            el._tsCfg = binding.value || {};
            const ts = el._tomselect;
            if (!ts) return;
            const newVal = el._tsCfg.value;
            if (newVal === undefined) return;
            const cur = ts.getValue();
            const a = [...(Array.isArray(newVal) ? newVal : [newVal]).filter(Boolean)].sort();
            const b = [...(Array.isArray(cur)    ? cur    : [cur]   ).filter(Boolean)].sort();
            if (JSON.stringify(a) !== JSON.stringify(b)) ts.setValue(newVal, true);
        },
        unmounted(el) {
            if (el._tomselect) { el._tomselect.destroy(); el._tomselect = null; }
            el._tsCfg = null;
        }
    }).directive('safe-html', {
        mounted(el, binding) {
            el.innerHTML = typeof DOMPurify !== 'undefined'
                ? DOMPurify.sanitize(binding.value ?? '', { FORCE_BODY: false })
                : (binding.value ?? '');
        },
        updated(el, binding) {
            if (el._lastSafeHtml === binding.value) return;
            el._lastSafeHtml = binding.value;
            el.innerHTML = typeof DOMPurify !== 'undefined'
                ? DOMPurify.sanitize(binding.value ?? '', { FORCE_BODY: false })
                : (binding.value ?? '');
        }
    }).mount('#lazy-builder-app');
</script>
