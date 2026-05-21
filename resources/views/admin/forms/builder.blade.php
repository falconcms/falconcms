<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Form Builder - {{ $form->title }}</x-slot>

    <style>
        .field-item { transition: box-shadow 0.2s, border-color 0.15s; }
        .field-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .sortable-ghost { opacity: 0.4; background: #dbeafe; border: 2px dashed #3b82f6 !important; }
        .field-type-btn { transition: all 0.15s; }
        .field-type-btn:hover { transform: translateY(-1px); }
        #drop-zone.drag-over { border-color: #3b82f6; background: #eff6ff; }
        .tab-btn.active { border-bottom: 2px solid #2563eb; color: #2563eb; }
        .col-badge { font-size: .6rem; padding: 1px 5px; border-radius: 4px; font-weight: 700; background: #ede9fe; color: #7c3aed; }
    </style>

    @php $savedCols = (int)(($form->settings['appearance']['columns'] ?? 1)); @endphp

    <div class="flex h-[calc(100vh-4rem)] overflow-hidden">

        {{-- LEFT PANEL: Field Types --}}
        <div class="w-56 bg-white border-r border-gray-200 flex flex-col overflow-y-auto shrink-0">
            <div class="px-4 py-3 border-b border-gray-100">
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Field Types</h2>
            </div>
            <div class="p-3 space-y-1.5">
                @foreach([
                    ['type'=>'text',      'icon'=>'text_fields',         'label'=>'Text'],
                    ['type'=>'email',     'icon'=>'mail',                'label'=>'Email'],
                    ['type'=>'tel',       'icon'=>'phone',               'label'=>'Phone'],
                    ['type'=>'number',    'icon'=>'pin',                 'label'=>'Number'],
                    ['type'=>'textarea',  'icon'=>'notes',               'label'=>'Textarea'],
                    ['type'=>'select',    'icon'=>'arrow_drop_down_circle','label'=>'Dropdown'],
                    ['type'=>'checkbox',  'icon'=>'check_box',           'label'=>'Checkbox'],
                    ['type'=>'radio',     'icon'=>'radio_button_checked','label'=>'Radio'],
                    ['type'=>'date',      'icon'=>'calendar_month',      'label'=>'Date'],
                    ['type'=>'file',      'icon'=>'attach_file',         'label'=>'File Upload'],
                    ['type'=>'hidden',    'icon'=>'visibility_off',      'label'=>'Hidden'],
                    ['type'=>'heading',   'icon'=>'title',               'label'=>'Heading'],
                    ['type'=>'paragraph', 'icon'=>'segment',             'label'=>'Paragraph'],
                    ['type'=>'divider',   'icon'=>'horizontal_rule',     'label'=>'Divider'],
                ] as $ft)
                    <button onclick="addField('{{ $ft['type'] }}')" class="field-type-btn w-full flex items-center gap-2.5 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 text-gray-700 text-xs font-medium text-left">
                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings:'FILL' 1">{{ $ft['icon'] }}</span>
                        {{ $ft['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- CENTER: Drop Zone --}}
        <div class="flex-1 flex flex-col overflow-hidden bg-gray-50">
            <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-gray-200 shrink-0">
                <div>
                    <h1 class="text-base font-black text-gray-900">{{ $form->title }}</h1>
                    <p class="text-xs text-gray-400">Drag fields to reorder · Click to edit</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.forms.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← All Forms</a>
                    <button onclick="saveForm()" id="save-btn" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                        <span class="material-symbols-outlined text-[16px]">save</span> Save Form
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-5">
                <div id="drop-zone"
                     class="min-h-64 border-2 border-dashed border-gray-300 rounded-xl p-4 transition-colors"
                     style="display:grid; grid-template-columns:repeat({{ $savedCols }},1fr); gap:.75rem; align-items:start;">
                    <p id="empty-msg"
                       style="grid-column:1/-1"
                       class="text-center text-gray-400 text-sm py-10 {{ count($form->fields ?? []) > 0 ? 'hidden' : '' }}">
                        Click a field type on the left to add it here
                    </p>
                </div>
            </div>
        </div>

        {{-- RIGHT PANEL: Settings & Appearance --}}
        <div class="w-80 bg-white border-l border-gray-200 flex flex-col overflow-hidden shrink-0">
            <div class="flex border-b border-gray-200 shrink-0">
                <button onclick="switchTab('settings')"   id="tab-settings"   class="tab-btn active flex-1 py-3 text-xs font-bold uppercase tracking-wider text-gray-500">Settings</button>
                <button onclick="switchTab('appearance')" id="tab-appearance" class="tab-btn flex-1 py-3 text-xs font-bold uppercase tracking-wider text-gray-500">Appearance</button>
            </div>

            {{-- ── SETTINGS PANEL ── --}}
            <div id="panel-settings" class="flex-1 overflow-y-auto flex flex-col">

                {{-- Field Settings (dynamic) --}}
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 shrink-0">
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Field Settings</h2>
                </div>
                <div id="settings-content" class="p-4 shrink-0">
                    <p class="text-xs text-gray-400 text-center mt-6">Click a field to edit its settings</p>
                </div>

                {{-- Form-level Settings --}}
                <div class="border-t border-gray-200 p-4 mt-auto">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Form Settings</h3>

                    <label class="block text-xs font-semibold text-gray-700 mb-1">Columns Layout</label>
                    <select id="app-columns" oninput="updateDropZoneGrid()" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none mb-3">
                        <option value="1" {{ $savedCols == 1 ? 'selected' : '' }}>1 Column</option>
                        <option value="2" {{ $savedCols == 2 ? 'selected' : '' }}>2 Columns</option>
                        <option value="3" {{ $savedCols == 3 ? 'selected' : '' }}>3 Columns</option>
                    </select>

                    <label class="block text-xs font-semibold text-gray-700 mb-1">Success Message</label>
                    <textarea id="success-message" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none mb-3" rows="2">{{ ($form->settings ?? [])['success_message'] ?? 'Thank you! Your message has been sent.' }}</textarea>

                    <label class="block text-xs font-semibold text-gray-700 mb-1">Submit Button Label</label>
                    <input type="text" id="submit-label" value="{{ ($form->settings ?? [])['submit_label'] ?? 'Submit' }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none mb-3">

                    <label class="block text-xs font-semibold text-gray-700 mb-1">Notify Email</label>
                    <input type="email" id="notify-email" value="{{ ($form->settings ?? [])['notify_email'] ?? '' }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="admin@example.com">
                </div>
            </div>

            {{-- ── APPEARANCE PANEL ── --}}
            <div id="panel-appearance" class="flex-1 overflow-y-auto hidden p-4 space-y-4">
                @php
                    $app = ($form->settings['appearance'] ?? []);
                    $appearanceFields = [
                        ['id'=>'label_color',       'label'=>'Label Color',         'type'=>'color',  'default'=>'#374151'],
                        ['id'=>'text_color',        'label'=>'Input Text Color',    'type'=>'color',  'default'=>'#1f2937'],
                        ['id'=>'field_bg',          'label'=>'Field Background',    'type'=>'color',  'default'=>'#ffffff'],
                        ['id'=>'border_color',      'label'=>'Border Color',        'type'=>'color',  'default'=>'#d1d5db'],
                        ['id'=>'focus_color',       'label'=>'Focus / Ring Color',  'type'=>'color',  'default'=>'#3b82f6'],
                        ['id'=>'placeholder_color', 'label'=>'Placeholder Color',   'type'=>'color',  'default'=>'#9ca3af'],
                        ['id'=>'button_bg',         'label'=>'Button Background',   'type'=>'color',  'default'=>'#2563eb'],
                        ['id'=>'button_text',       'label'=>'Button Text Color',   'type'=>'color',  'default'=>'#ffffff'],
                        ['id'=>'border_radius',     'label'=>'Border Radius (px)',  'type'=>'number', 'default'=>'8'],
                    ];
                @endphp

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Field Size</label>
                    <select id="app-size" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none">
                        <option value="sm" {{ ($app['size'] ?? 'md') === 'sm' ? 'selected' : '' }}>Small</option>
                        <option value="md" {{ ($app['size'] ?? 'md') === 'md' ? 'selected' : '' }}>Medium</option>
                        <option value="lg" {{ ($app['size'] ?? 'md') === 'lg' ? 'selected' : '' }}>Large</option>
                    </select>
                </div>

                @foreach($appearanceFields as $af)
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $af['label'] }}</label>
                        <input type="{{ $af['type'] }}" id="app-{{ $af['id'] }}" value="{{ $app[$af['id']] ?? $af['default'] }}"
                            @if($af['type'] === 'number') min="0" @endif
                            class="{{ $af['type'] === 'color' ? 'h-8 w-full' : 'w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs' }} focus:outline-none">
                    </div>
                @endforeach

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Button Width</label>
                    <select id="app-btn_width" onchange="toggleBtnAlign()" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none">
                        <option value="full" {{ ($app['btn_width'] ?? 'full') === 'full' ? 'selected' : '' }}>Full Width</option>
                        <option value="auto" {{ ($app['btn_width'] ?? 'full') === 'auto' ? 'selected' : '' }}>Fit Content</option>
                    </select>
                </div>
                <div id="btn-align-row" class="{{ ($app['btn_width'] ?? 'full') === 'auto' ? '' : 'hidden' }}">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Button Alignment</label>
                    <select id="app-btn_align" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none">
                        <option value="left"   {{ ($app['btn_align'] ?? 'center') === 'left'   ? 'selected' : '' }}>Left</option>
                        <option value="center" {{ ($app['btn_align'] ?? 'center') === 'center' ? 'selected' : '' }}>Center</option>
                        <option value="right"  {{ ($app['btn_align'] ?? 'center') === 'right'  ? 'selected' : '' }}>Right</option>
                    </select>
                </div>

                {{-- Shortcode --}}
                <div class="pt-2 border-t border-gray-100">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Shortcode</p>
                    <code class="block bg-gray-100 text-blue-700 px-3 py-2 rounded-lg text-xs break-all">[lazy_form slug="{{ $form->slug }}"]</code>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/cms-dashboard/js/sortable.min.js') }}"></script>
    <script>
    let fields     = @json($form->fields ?? []);
    let selectedId = null;

    const dropZone = document.getElementById('drop-zone');

    new Sortable(dropZone, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        handle: '.drag-handle',
        onEnd: syncFieldOrder,
    });

    // ── grid helpers ─────────────────────────────────────────────────
    function totalCols() {
        return parseInt(document.getElementById('app-columns').value) || 1;
    }

    function updateFieldSpan(el, field) {
        const cols = totalCols();
        const alwaysFull = ['heading','paragraph','divider','hidden'].includes(field.type);
        if (alwaysFull || cols === 1) {
            el.style.gridColumn = '1 / -1';
        } else {
            const raw  = field.col_span;
            const span = (raw === undefined || raw === null || raw === 'full')
                ? cols
                : Math.max(1, Math.min(cols, parseInt(raw)));
            el.style.gridColumn = span >= cols ? '1 / -1' : `span ${span}`;
        }
    }

    function updateDropZoneGrid() {
        const cols = totalCols();
        dropZone.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
        dropZone.querySelectorAll('.field-item[data-id]').forEach(el => {
            const f = fields.find(f => f.id === el.dataset.id);
            if (f) {
                updateFieldSpan(el, f);
                updateColBadge(el, f);
            }
        });
        // Refresh settings panel to update col span options
        if (selectedId) {
            const f = fields.find(f => f.id === selectedId);
            if (f) renderSettings(f);
        }
    }

    // ── tab switch ───────────────────────────────────────────────────
    function switchTab(tab) {
        document.getElementById('panel-settings').classList.toggle('hidden',   tab !== 'settings');
        document.getElementById('panel-appearance').classList.toggle('hidden', tab !== 'appearance');
        document.getElementById('tab-settings').classList.toggle('active',     tab === 'settings');
        document.getElementById('tab-appearance').classList.toggle('active',   tab === 'appearance');
    }

    function toggleBtnAlign() {
        const auto = document.getElementById('app-btn_width').value === 'auto';
        document.getElementById('btn-align-row').classList.toggle('hidden', !auto);
    }

    // ── uid ──────────────────────────────────────────────────────────
    function uid() { return 'f_' + Date.now() + '_' + Math.random().toString(36).substr(2,5); }

    // ── add / render field ───────────────────────────────────────────
    function addField(type) {
        const defaults = {
            text:      { label:'Text Field',  placeholder:'Enter text...',    required:false, error_message:'This field is required' },
            email:     { label:'Email',        placeholder:'Enter email...',   required:false, error_message:'Please enter a valid email' },
            tel:       { label:'Phone',        placeholder:'Enter phone...',   required:false, error_message:'This field is required' },
            number:    { label:'Number',       placeholder:'0',               required:false, error_message:'This field is required' },
            textarea:  { label:'Message',      placeholder:'Your message...', required:false, error_message:'This field is required', rows:4 },
            select:    { label:'Select',       options:'Option 1\nOption 2\nOption 3', required:false, error_message:'Please select an option' },
            checkbox:  { label:'Checkbox',     options:'Option 1\nOption 2',   required:false, error_message:'Please check at least one' },
            radio:     { label:'Radio',        options:'Option A\nOption B',   required:false, error_message:'Please choose an option' },
            date:      { label:'Date',         required:false, error_message:'Please select a date' },
            file:      { label:'File Upload',  required:false, error_message:'Please upload a file' },
            hidden:    { label:'Hidden Field', value:'' },
            heading:   { content:'Section Heading', level:'h2' },
            paragraph: { content:'Enter some descriptive text here.' },
            divider:   {},
        };
        const field = { id:uid(), type, name:type+'_'+Date.now(), ...defaults[type] };
        fields.push(field);
        renderField(field, true);
    }

    function colBadgeText(field) {
        const cols = totalCols();
        if (cols <= 1 || ['heading','paragraph','divider','hidden'].includes(field.type)) return '';
        const raw  = field.col_span;
        const span = (raw === undefined || raw === null || raw === 'full')
            ? cols : Math.max(1, Math.min(cols, parseInt(raw)));
        if (span >= cols) return '';
        return span + '/' + cols;
    }

    function updateColBadge(el, field) {
        const old = el.querySelector('.col-badge');
        if (old) old.remove();
        const txt = colBadgeText(field);
        if (txt) {
            const b = document.createElement('span');
            b.className = 'col-badge';
            b.textContent = txt;
            el.querySelector('.flex.items-center.gap-2')?.appendChild(b);
        }
    }

    const ICONS = {text:'text_fields',email:'mail',tel:'phone',number:'pin',textarea:'notes',select:'arrow_drop_down_circle',checkbox:'check_box',radio:'radio_button_checked',date:'calendar_month',file:'attach_file',hidden:'visibility_off',heading:'title',paragraph:'segment',divider:'horizontal_rule'};

    function renderField(field, select) {
        document.getElementById('empty-msg').classList.add('hidden');

        const el = document.createElement('div');
        el.className = 'field-item bg-white border border-gray-200 rounded-xl p-3 flex items-center gap-2.5 cursor-pointer group';
        el.dataset.id = field.id;
        updateFieldSpan(el, field);

        const badgeTxt = colBadgeText(field);
        el.innerHTML = `
            <div class="drag-handle cursor-move text-gray-300 hover:text-gray-500 shrink-0">
                <span class="material-symbols-outlined text-[18px]">drag_indicator</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="material-symbols-outlined text-[14px] text-blue-500 shrink-0" style="font-variation-settings:'FILL' 1">${ICONS[field.type]||'input'}</span>
                    <span class="text-xs font-semibold text-gray-700 truncate">${field.label||field.content||field.type}</span>
                    <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded shrink-0">${field.type}</span>
                    ${field.required ? '<span class="text-xs text-red-500 font-bold">*</span>' : ''}
                    ${badgeTxt ? `<span class="col-badge">${badgeTxt}</span>` : ''}
                </div>
            </div>
            <button onclick="removeField('${field.id}',event)" class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-600 transition-all shrink-0">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
        `;
        el.addEventListener('click', () => selectField(field.id));
        dropZone.appendChild(el);
        if (select) selectField(field.id);
    }

    // ── select field ─────────────────────────────────────────────────
    function selectField(id) {
        selectedId = id;
        document.querySelectorAll('.field-item').forEach(el => {
            el.classList.toggle('border-blue-400', el.dataset.id === id);
            el.classList.toggle('ring-2',          el.dataset.id === id);
            el.classList.toggle('ring-blue-100',   el.dataset.id === id);
        });
        const field = fields.find(f => f.id === id);
        if (!field) return;
        renderSettings(field);
        switchTab('settings');
    }

    // ── render settings ──────────────────────────────────────────────
    function renderSettings(field) {
        const panel = document.getElementById('settings-content');
        let html = '';

        if (!['divider','paragraph','heading'].includes(field.type)) {
            html += row('Label', `<input type="text" value="${esc(field.label||'')}" oninput="updateField('label',this.value)" class="${inp}">`);
            html += row('Field Name', `<input type="text" value="${esc(field.name||'')}" oninput="updateField('name',this.value)" class="${inp}">`);
        }

        if (['text','email','tel','number','textarea'].includes(field.type)) {
            html += row('Placeholder', `<input type="text" value="${esc(field.placeholder||'')}" oninput="updateField('placeholder',this.value)" class="${inp}">`);
        }

        if (['select','checkbox','radio'].includes(field.type)) {
            html += row('Options (one per line)', `<textarea oninput="updateField('options',this.value)" class="${inp} resize-none" rows="4">${esc(field.options||'')}</textarea>`);
        }

        if (field.type === 'heading') {
            html += row('Content', `<input type="text" value="${esc(field.content||'')}" oninput="updateField('content',this.value)" class="${inp}">`);
            html += row('Level', `<select onchange="updateField('level',this.value)" class="${inp}">
                ${['h1','h2','h3','h4'].map(h=>`<option value="${h}" ${(field.level||'h2')===h?'selected':''}>${h.toUpperCase()}</option>`).join('')}
            </select>`);
        }

        if (field.type === 'paragraph') {
            html += row('Content', `<textarea oninput="updateField('content',this.value)" class="${inp} resize-none" rows="3">${esc(field.content||'')}</textarea>`);
        }

        if (field.type === 'textarea') {
            html += row('Rows', `<input type="number" value="${field.rows||4}" min="2" max="20" oninput="updateField('rows',this.value)" class="${inp}">`);
        }

        if (field.type === 'hidden') {
            html += row('Value', `<input type="text" value="${esc(field.value||'')}" oninput="updateField('value',this.value)" class="${inp}">`);
        }

        if (!['divider','heading','paragraph','hidden'].includes(field.type)) {
            html += `<div class="mb-3 bg-blue-50 p-3 rounded-lg border border-blue-100">
                <div class="flex items-center gap-2 mb-2">
                    <input type="checkbox" id="req-check" ${field.required?'checked':''} onchange="updateField('required',this.checked)" class="rounded">
                    <label for="req-check" class="text-xs font-bold text-blue-800 uppercase tracking-tighter">Required Field</label>
                </div>
                <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Custom Error Message</label>
                <input type="text" value="${esc(field.error_message||'')}" oninput="updateField('error_message',this.value)"
                    class="w-full border border-blue-200 rounded-lg px-2 py-1.5 text-[11px] focus:outline-none" placeholder="This field is required">
            </div>`;

            // Column Width — only when total columns > 1
            const cols = totalCols();
            if (cols > 1) {
                const raw = field.col_span;
                const cur = (raw===undefined||raw===null||raw==='full') ? cols : Math.max(1,Math.min(cols,parseInt(raw)));
                let opts = '';
                for (let i = 1; i <= cols; i++) {
                    const lbl = i===cols ? `Full Width (${i}/${cols})` : i===1 && cols===2 ? 'Half Width (1/2)' : i===1 ? `Narrow (1/${cols})` : `Medium (${i}/${cols})`;
                    opts += `<option value="${i}" ${cur===i?'selected':''}>${lbl}</option>`;
                }
                html += row('Column Width', `<select onchange="updateField('col_span',parseInt(this.value))" class="${inp}">${opts}</select>`);
            }
        }

        panel.innerHTML = html || '<p class="text-xs text-gray-400 text-center mt-4">No settings for this field</p>';
    }

    const inp = 'w-full border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none';
    function row(label, control) {
        return `<div class="mb-3"><label class="text-xs font-semibold text-gray-600 block mb-1">${label}</label>${control}</div>`;
    }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // ── update field ──────────────────────────────────────────────────
    function updateField(key, value) {
        const field = fields.find(f => f.id === selectedId);
        if (!field) return;
        field[key] = value;
        const el = dropZone.querySelector(`[data-id="${selectedId}"]`);
        if (!el) return;

        // Update label text
        const labelEl = el.querySelector('span.font-semibold');
        if (labelEl) labelEl.textContent = field.label || field.content || field.type;

        // Required star
        const reqEl = el.querySelector('span.text-red-500');
        if (field.required && !reqEl) {
            labelEl?.parentElement?.insertAdjacentHTML('beforeend','<span class="text-xs text-red-500 font-bold">*</span>');
        } else if (!field.required && reqEl) {
            reqEl.remove();
        }

        // Grid span + badge
        if (key === 'col_span') {
            updateFieldSpan(el, field);
            updateColBadge(el, field);
        }
    }

    // ── remove field ─────────────────────────────────────────────────
    function removeField(id, event) {
        event.stopPropagation();
        fields = fields.filter(f => f.id !== id);
        dropZone.querySelector(`[data-id="${id}"]`)?.remove();
        if (fields.length === 0) document.getElementById('empty-msg').classList.remove('hidden');
        if (selectedId === id) {
            selectedId = null;
            document.getElementById('settings-content').innerHTML = '<p class="text-xs text-gray-400 text-center mt-6">Click a field to edit its settings</p>';
        }
    }

    // ── sync order after drag ─────────────────────────────────────────
    function syncFieldOrder() {
        const order = [];
        dropZone.querySelectorAll('.field-item[data-id]').forEach(el => {
            const f = fields.find(f => f.id === el.dataset.id);
            if (f) order.push(f);
        });
        fields = order;
    }

    // ── save ──────────────────────────────────────────────────────────
    async function saveForm() {
        syncFieldOrder();
        const btn = document.getElementById('save-btn');
        const orig = btn.innerHTML;
        btn.textContent = 'Saving...';
        btn.disabled = true;

        const settings = {
            success_message: document.getElementById('success-message').value,
            notify_email:    document.getElementById('notify-email').value,
            submit_label:    document.getElementById('submit-label').value,
            appearance: {
                columns:           parseInt(document.getElementById('app-columns').value) || 1,
                size:              document.getElementById('app-size').value,
                label_color:       document.getElementById('app-label_color').value,
                text_color:        document.getElementById('app-text_color').value,
                field_bg:          document.getElementById('app-field_bg').value,
                border_color:      document.getElementById('app-border_color').value,
                focus_color:       document.getElementById('app-focus_color').value,
                placeholder_color: document.getElementById('app-placeholder_color').value,
                button_bg:         document.getElementById('app-button_bg').value,
                button_text:       document.getElementById('app-button_text').value,
                border_radius:     Math.max(0, parseInt(document.getElementById('app-border_radius').value)||0),
                btn_width:         document.getElementById('app-btn_width').value,
                btn_align:         document.getElementById('app-btn_align').value,
            }
        };

        await fetch('{{ route("admin.forms.save", $form->id) }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
            body: JSON.stringify({ fields, settings })
        });

        btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span> Saved!';
        btn.classList.replace('bg-blue-600','bg-green-600');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.replace('bg-green-600','bg-blue-600');
            btn.disabled = false;
        }, 2000);
    }

    // Init
    fields.forEach(f => renderField(f, false));

    dropZone.addEventListener('dragover',  () => dropZone.classList.add('drag-over'));
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop',      () => dropZone.classList.remove('drag-over'));
    </script>
</x-cms-dashboard::layouts.admin>
