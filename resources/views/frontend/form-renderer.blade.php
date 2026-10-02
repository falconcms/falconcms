@php
    $ap           = $form->settings['appearance'] ?? [];
    $columns      = max(1, intval($ap['columns']          ?? 1));
    $size         = $ap['size']                           ?? 'md';
    $borderClr    = $ap['border_color']                   ?? '#d1d5db';
    $bgClr        = $ap['field_bg']                       ?? '#ffffff';
    $textClr      = $ap['text_color']                     ?? '#374151';
    $phClr        = $ap['placeholder_color']              ?? '#9ca3af';
    $labelClr     = $ap['label_color']                    ?? '#374151';
    $focusClr     = $ap['focus_color']                    ?? '#3b82f6';
    $btnBg        = $ap['button_bg']                      ?? '#2563eb';
    $btnText      = $ap['button_text']                    ?? '#ffffff';
    $borderRadius = max(0, intval($ap['border_radius']    ?? 8));
    $btnWidth     = $ap['btn_width']                      ?? 'full';
    $btnAlign     = $ap['btn_align']                      ?? 'center';
    $labelAlign   = $ap['label_align']                    ?? 'left';
    $submitLabel      = $form->settings['submit_label']       ?? 'Submit';
    $turnstileEnabled = !empty($form->settings['turnstile_enabled'])
                        && get_cms_option('turnstile_site_key')
                        && get_cms_option('turnstile_secret_key');
    $turnstileSiteKey = get_cms_option('turnstile_site_key', '');
    $successMsg   = $form->settings['success_message']    ?? 'Thank you! Your message has been sent.';
    $formId       = 'lazy-form-' . $form->id;

    $inputSize = match($size) {
        'sm'    => 'px-3 py-1.5 text-xs',
        'lg'    => 'px-5 py-3 text-base',
        default => 'px-4 py-2.5 text-sm',
    };
    $btnPad = match($size) {
        'sm'    => 'py-2 px-5 text-sm',
        'lg'    => 'py-4 px-8 text-base',
        default => 'py-3 px-6 text-sm',
    };

    // Button wrapper: always spans full grid width, then controls inner alignment
    $alignJustify = match($btnAlign) { 'left' => 'flex-start', 'right' => 'flex-end', default => 'center' };
    $btnWrapStyle = 'grid-column:1/-1;' . ($btnWidth === 'auto' ? " display:flex; justify-content:{$alignJustify};" : '');
    $btnInline    = $btnWidth === 'auto' ? 'width:auto; min-width:120px;' : '';
@endphp

<style>
#{{ $formId }}-wrap .lf-input {
    border: 1px solid {{ $borderClr }};
    background-color: {{ $bgClr }};
    color: {{ $textClr }} !important;
    border-radius: {{ $borderRadius }}px;
    width: 100%;
    display: block;
    transition: border-color .15s, box-shadow .15s;
}
#{{ $formId }}-wrap .lf-input::placeholder { color: {{ $phClr }}; }
#{{ $formId }}-wrap .lf-input:hover:not(:focus) {
    border-color: {{ $borderClr }};
    background-color: {{ $bgClr }};
    box-shadow: none;
}
#{{ $formId }}-wrap .lf-input:focus {
    outline: none;
    border-color: {{ $focusClr }};
    box-shadow: 0 0 0 3px {{ $focusClr }}33;
}
#{{ $formId }}-wrap .lf-input.lf-err-input { border-color: #ef4444 !important; box-shadow: none !important; }
#{{ $formId }}-wrap .lf-label { color: {{ $labelClr }} !important; text-align: {{ $labelAlign }}; }
/* Floating label */
#{{ $formId }}-wrap .lf-field-wrap { position:relative; }
#{{ $formId }}-wrap .lf-float-input { padding-top:1.3rem !important; padding-bottom:.4rem !important; }
#{{ $formId }}-wrap .lf-float-label {
    position:absolute; left:14px; top:50%; transform:translateY(-50%);
    transition:top .15s ease,transform .15s ease,color .15s ease,padding .15s ease,background-color .15s ease;
    pointer-events:none; color:{{ $phClr }} !important; font-size:.875rem; font-weight:500; line-height:1;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:calc(100% - 28px); z-index:1;
}
#{{ $formId }}-wrap .lf-field-wrap.lf-focused .lf-float-label,
#{{ $formId }}-wrap .lf-field-wrap.lf-filled .lf-float-label {
    top:0; transform:translateY(-50%) scale(.78); transform-origin:left center;
    padding:0 3px; background-color:{{ $bgClr }}; color:{{ $labelClr }} !important; max-width:none; overflow:visible;
}
#{{ $formId }}-wrap .lf-field-wrap.lf-focused .lf-float-label { color:{{ $focusClr }} !important; }
#{{ $formId }}-wrap .lf-float-textarea .lf-float-label { top:.9rem; transform:none; }
#{{ $formId }}-wrap .lf-float-textarea.lf-focused .lf-float-label,
#{{ $formId }}-wrap .lf-float-textarea.lf-filled .lf-float-label { top:0; transform:translateY(-50%) scale(.78); transform-origin:left center; }
#{{ $formId }}-wrap .lf-btn {
    background-color: {{ $btnBg }} !important;
    color: {{ $btnText }} !important;
    border: none;
    cursor: pointer;
    border-radius: {{ $borderRadius }}px;
    font-weight: 600;
    transition: opacity .15s;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
}
#{{ $formId }}-wrap .lf-btn:hover:not(:disabled) { opacity: .88; }
#{{ $formId }}-wrap .lf-btn:disabled { opacity: .6; cursor: not-allowed; }
#{{ $formId }}-wrap .lf-field-err {
    color: #ef4444 !important;
    font-size: .72rem;
    margin-bottom: .3rem;
    font-weight: 500;
    display: block;
    text-align: left !important;
}
#{{ $formId }}-wrap .lf-help-text {
    color: {{ $labelClr }} !important;
    font-size: .72rem;
    margin-top: .3rem;
    opacity: .7 !important;
    display: block;
    text-align: left !important;
}
#{{ $formId }}-wrap *:hover .lf-help-text,
#{{ $formId }}-wrap .lf-help-text:hover {
    color: {{ $labelClr }} !important;
    opacity: .7 !important;
}
</style>

<div class="lazy-form-wrap my-6" id="{{ $formId }}-wrap">
    <div id="{{ $formId }}-success" class="hidden bg-green-50 border border-green-200 text-green-800 px-5 py-4 rounded-xl text-sm font-medium mb-4">
        {{ $successMsg }}
    </div>

    <form id="{{ $formId }}"
          style="display:grid; grid-template-columns: repeat({{ $columns }}, 1fr); gap: 1rem;"
          novalidate>
        @csrf
        <input type="hidden" name="form_id" value="{{ $form->id }}">
        {{-- Honeypot: hidden from real users, bots fill it in --}}
        <div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;opacity:0;" aria-hidden="true">
            <label for="_lf_hp_{{ $form->id }}">Website</label>
            <input type="text" name="_lf_hp_{{ $form->id }}" id="_lf_hp_{{ $form->id }}" value="" autocomplete="off" tabindex="-1">
        </div>

        @foreach($form->fields as $field)
            @php
                $name       = $field['name'] ?? $field['type'];
                $required   = !empty($field['required']);
                $alwaysFull = in_array($field['type'], ['heading', 'paragraph', 'divider', 'hidden']);

                if ($alwaysFull) {
                    $gridAttr = 'style="grid-column:1/-1"';
                } else {
                    $span = $field['col_span'] ?? null;
                    if ($span === null || $span === 'full') {
                        $gridAttr = 'style="grid-column:1/-1"';
                    } else {
                        $spanNum  = max(1, min($columns, intval($span)));
                        $gridAttr = $spanNum >= $columns
                            ? 'style="grid-column:1/-1"'
                            : 'style="grid-column:span ' . $spanNum . '"';
                    }
                }

                // Every real input wrapper carries its name and type, so the conditional-logic
                // engine can find it, clear it when hidden, and skip its required check.
                $idData = ' data-lf-name="' . e($name) . '" data-lf-type="' . e($field['type']) . '"';
                $reqData = $required
                    ? ' data-lf-req="1" data-lf-err="' . e($field['error_message'] ?? '') . '"'
                    : '';
                // Conditional logic: show/hide this field based on other answers. Normalised to
                // the shape the front-end engine (and the builder) use; invalid rules dropped.
                $condData = '';
                $cond = $field['conditional'] ?? null;
                if (is_array($cond) && !empty($cond['enabled']) && !empty($cond['rules']) && is_array($cond['rules'])) {
                    $rules = array_values(array_filter(array_map(function ($r) {
                        if (!is_array($r) || empty($r['field']) || empty($r['operator'])) {
                            return null;
                        }

                        return ['field' => (string) $r['field'], 'operator' => (string) $r['operator'], 'value' => (string) ($r['value'] ?? '')];
                    }, $cond['rules'])));
                    if ($rules) {
                        $condData = ' data-lf-cond="' . e(json_encode([
                            'action' => ($cond['action'] ?? 'show') === 'hide' ? 'hide' : 'show',
                            'logic' => ($cond['logic'] ?? 'all') === 'any' ? 'any' : 'all',
                            'rules' => $rules,
                        ], JSON_HEX_APOS | JSON_HEX_QUOT)) . '"';
                    }
                }
                $wrapAttrs = trim($gridAttr . $idData . $reqData . $condData);
            @endphp

            @if($field['type'] === 'heading')
                <div style="grid-column:1/-1">
                    <{{ $field['level'] ?? 'h3' }} class="lf-label font-bold text-lg mt-2">{{ $field['content'] ?? '' }}</{{ $field['level'] ?? 'h3' }}>
                </div>

            @elseif($field['type'] === 'paragraph')
                <div style="grid-column:1/-1">
                    <p class="lf-label text-sm">{{ $field['content'] ?? '' }}</p>
                </div>

            @elseif($field['type'] === 'divider')
                <div style="grid-column:1/-1"><hr style="border-color:{{ $borderClr }}" class="my-1"></div>

            @elseif($field['type'] === 'hidden')
                <input type="hidden" name="{{ $name }}" value="{{ $field['value'] ?? '' }}">

            @elseif($field['type'] === 'textarea')
                <div {!! $wrapAttrs !!}>
                    <div class="lf-field-wrap lf-float-textarea">
                        <textarea name="{{ $name }}" rows="{{ $field['rows'] ?? 4 }}" placeholder=" "
                                  class="lf-input lf-float-input {{ $inputSize }} resize-none"></textarea>
                        <label class="lf-float-label">{{ $field['label'] ?? '' }}@if($required)<span style="color:#ef4444;margin-left:2px">*</span>@endif</label>
                    </div>
                    @if(!empty($field['help_text']))<p class="lf-help-text">{{ $field['help_text'] }}</p>@endif
                </div>

            @elseif($field['type'] === 'select')
                <div {!! $wrapAttrs !!}>
                    <div class="lf-field-wrap">
                        <select name="{{ $name }}" class="lf-input lf-float-input {{ $inputSize }}">
                            <option value=""></option>
                            @foreach(explode("\n", $field['options'] ?? '') as $opt)
                                @php $opt = trim($opt); @endphp
                                @if($opt)<option value="{{ $opt }}">{{ $opt }}</option>@endif
                            @endforeach
                        </select>
                        <label class="lf-float-label">{{ $field['label'] ?? '' }}@if($required)<span style="color:#ef4444;margin-left:2px">*</span>@endif</label>
                    </div>
                    @if(!empty($field['help_text']))<p class="lf-help-text">{{ $field['help_text'] }}</p>@endif
                </div>

            @elseif($field['type'] === 'checkbox')
                <div {!! $wrapAttrs !!}>
                    <label class="lf-label block text-sm font-semibold mb-2">
                        {{ $field['label'] ?? '' }}@if($required)<span class="text-red-500 ml-0.5">*</span>@endif
                    </label>
                    @foreach(explode("\n", $field['options'] ?? '') as $opt)
                        @php $opt = trim($opt); @endphp
                        @if($opt)
                            <label class="flex items-center gap-2 text-sm mb-1.5 cursor-pointer lf-label">
                                <input type="checkbox" name="{{ $name }}[]" value="{{ $opt }}"
                                       style="accent-color:{{ $focusClr }}" class="rounded">
                                {{ $opt }}
                            </label>
                        @endif
                    @endforeach
                    @if(!empty($field['help_text']))<p class="lf-help-text">{{ $field['help_text'] }}</p>@endif
                </div>

            @elseif($field['type'] === 'radio')
                <div {!! $wrapAttrs !!}>
                    <label class="lf-label block text-sm font-semibold mb-2">
                        {{ $field['label'] ?? '' }}@if($required)<span class="text-red-500 ml-0.5">*</span>@endif
                    </label>
                    @foreach(explode("\n", $field['options'] ?? '') as $opt)
                        @php $opt = trim($opt); @endphp
                        @if($opt)
                            <label class="flex items-center gap-2 text-sm mb-1.5 cursor-pointer lf-label">
                                <input type="radio" name="{{ $name }}" value="{{ $opt }}"
                                       style="accent-color:{{ $focusClr }}">
                                {{ $opt }}
                            </label>
                        @endif
                    @endforeach
                    @if(!empty($field['help_text']))<p class="lf-help-text">{{ $field['help_text'] }}</p>@endif
                </div>

            @elseif($field['type'] === 'file')
                <div {!! $wrapAttrs !!}>
                    <label class="lf-label block text-sm font-semibold mb-1">
                        {{ $field['label'] ?? '' }}@if($required)<span class="text-red-500 ml-0.5">*</span>@endif
                    </label>
                    <input type="file" name="{{ $name }}"
                           @if(!empty($field['accepted_types'])) accept="{{ $field['accepted_types'] }}" @endif
                           @if(!empty($field['max_size_mb'])) data-lf-maxsize="{{ $field['max_size_mb'] }}" @endif
                           class="lf-input {{ $inputSize }} file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    @if(!empty($field['help_text']))<p class="lf-help-text">{{ $field['help_text'] }}</p>@endif
                </div>

            @else
                <div {!! $wrapAttrs !!}>
                    <div class="lf-field-wrap{{ $field['type'] === 'date' ? ' lf-filled' : '' }}">
                        <input type="{{ $field['type'] }}" name="{{ $name }}" placeholder=" "
                               class="lf-input lf-float-input {{ $inputSize }}"
                               @if($field['type'] === 'number')
                                   @if(isset($field['min']) && $field['min'] !== '') min="{{ $field['min'] }}" @endif
                                   @if(isset($field['max']) && $field['max'] !== '') max="{{ $field['max'] }}" @endif
                                   @if(isset($field['step']) && $field['step'] !== '') step="{{ $field['step'] }}" @endif
                               @elseif($field['type'] === 'date')
                                   @if(!empty($field['min_date'])) min="{{ $field['min_date'] }}" @endif
                                   @if(!empty($field['max_date'])) max="{{ $field['max_date'] }}" @endif
                               @endif
                               >
                        <label class="lf-float-label">{{ $field['label'] ?? '' }}@if($required)<span style="color:#ef4444;margin-left:2px">*</span>@endif</label>
                    </div>
                    @if(!empty($field['help_text']))<p class="lf-help-text">{{ $field['help_text'] }}</p>@endif
                </div>
            @endif
        @endforeach

        @if($turnstileEnabled)
        <div style="grid-column:1/-1">
            <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="light"></div>
        </div>
        @endif

        <div style="{{ $btnWrapStyle }}">
            <button type="submit" class="lf-btn {{ $btnPad }}" style="{{ $btnInline }}">
                <span class="btn-text">{{ $submitLabel }}</span>
                <svg class="btn-spinner hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </button>
        </div>

        {{-- AJAX error shown below the button --}}
        <div id="{{ $formId }}-ajax-error" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium" style="grid-column:1/-1"></div>
    </form>
</div>

@if($turnstileEnabled)
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif

<script>
(function () {
    const form      = document.getElementById('{{ $formId }}');
    const success   = document.getElementById('{{ $formId }}-success');
    const ajaxErr   = document.getElementById('{{ $formId }}-ajax-error');
    const origLabel = '{{ addslashes($submitLabel) }}';

    // ── error helpers ────────────────────────────────────────────────
    function clearErr(wrap) {
        wrap.querySelectorAll('.lf-field-err').forEach(e => e.remove());
        const inp = wrap.querySelector('.lf-err-input');
        if (inp) { inp.classList.remove('lf-err-input'); inp.style.borderColor = ''; }
    }

    function showErr(wrap, msg) {
        clearErr(wrap);
        const span = document.createElement('span');
        span.className = 'lf-field-err';
        span.textContent = msg || 'This field is required.';
        const fieldWrap   = wrap.querySelector('.lf-field-wrap');
        const firstOptLbl = wrap.querySelector('label.flex');
        const fileInp     = wrap.querySelector('input[type="file"]');
        if (fieldWrap)       wrap.insertBefore(span, fieldWrap);
        else if (firstOptLbl) wrap.insertBefore(span, firstOptLbl);
        else if (fileInp)    wrap.insertBefore(span, fileInp);
        else                 wrap.appendChild(span);
        const inp = wrap.querySelector('.lf-input');
        if (inp) inp.classList.add('lf-err-input');
    }

    // clear error on interaction
    form.addEventListener('input',  e => { const w = e.target.closest('[data-lf-req]'); if (w) clearErr(w); });
    form.addEventListener('change', e => { const w = e.target.closest('[data-lf-req]'); if (w) clearErr(w); });

    // ── validation ───────────────────────────────────────────────────
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // ── conditional logic (show/hide fields by other answers) ─────────
    // A field may carry data-lf-cond = { action:'show'|'hide', logic:'all'|'any', rules:[...] }.
    // Rules read the current value of another field by its name. Re-run on every change, and
    // on load, until the set of visible fields stops changing (so a field that reveals another,
    // which reveals a third, settles in one pass).
    const condWraps = Array.from(form.querySelectorAll('[data-lf-cond]')).map(wrap => {
        let cfg = null;
        try { cfg = JSON.parse(wrap.getAttribute('data-lf-cond')); } catch (e) {}
        return { wrap, cfg };
    }).filter(c => c.cfg && Array.isArray(c.cfg.rules) && c.cfg.rules.length);

    // The value(s) of a field by name, as a flat array of strings (checkboxes → every checked).
    function fieldValues(name) {
        const checks = form.querySelectorAll(`input[name="${name}[]"]:checked, input[name="${name}"][type=checkbox]:checked`);
        if (checks.length) return Array.from(checks).map(i => i.value);
        const radio = form.querySelector(`input[name="${name}"][type=radio]:checked`);
        if (radio) return [radio.value];
        const el = form.querySelector(`[name="${name}"]:not([type=checkbox]):not([type=radio]), [name="${name}[]"]`);
        if (el && 'value' in el) return [String(el.value)];
        return [];
    }

    function ruleMatches(rule) {
        const vals = fieldValues(rule.field);
        const joined = vals.join(' ').trim();
        const target = String(rule.value ?? '');
        const num = parseFloat(joined), tnum = parseFloat(target);
        switch (rule.operator) {
            case 'empty':      return joined === '';
            case 'not_empty':  return joined !== '';
            case 'is':         return vals.some(v => v === target);
            case 'is_not':     return !vals.some(v => v === target) && joined !== '';
            case 'contains':   return joined.toLowerCase().includes(target.toLowerCase()) && target !== '';
            case 'not_contains': return !joined.toLowerCase().includes(target.toLowerCase());
            case 'gt':         return !isNaN(num) && !isNaN(tnum) && num > tnum;
            case 'lt':         return !isNaN(num) && !isNaN(tnum) && num < tnum;
            default:           return false;
        }
    }

    function shouldShow(cfg) {
        const results = cfg.rules.map(ruleMatches);
        const matched = cfg.logic === 'any' ? results.some(Boolean) : results.every(Boolean);
        return cfg.action === 'hide' ? !matched : matched;
    }

    function applyConditions() {
        if (!condWraps.length) return;
        for (let pass = 0; pass < condWraps.length + 1; pass++) {
            let changed = false;
            condWraps.forEach(({ wrap, cfg }) => {
                const show = shouldShow(cfg);
                const hidden = wrap.dataset.lfHidden === '1';
                if (show && hidden) { wrap.style.display = ''; delete wrap.dataset.lfHidden; changed = true; }
                else if (!show && !hidden) {
                    wrap.style.display = 'none';
                    wrap.dataset.lfHidden = '1';
                    // A field the visitor cannot see must not carry a stale answer into the
                    // submission, and its error must clear.
                    wrap.querySelectorAll('input, select, textarea').forEach(i => {
                        if (i.type === 'checkbox' || i.type === 'radio') i.checked = false;
                        else i.value = '';
                    });
                    clearErr(wrap);
                    changed = true;
                }
            });
            if (!changed) break;
        }
    }

    form.addEventListener('input', applyConditions);
    form.addEventListener('change', applyConditions);
    applyConditions();

    function validate() {
        let ok = true, first = null;

        // 1. Required fields
        form.querySelectorAll('[data-lf-req]').forEach(wrap => {
            // A field hidden by conditional logic is not being asked, so it is not required.
            if (wrap.dataset.lfHidden === '1') return;
            const type = wrap.dataset.lfType;
            const name = wrap.dataset.lfName;
            let empty  = true;

            if (type === 'checkbox') {
                empty = !form.querySelector(`input[name="${name}[]"]:checked`);
            } else if (type === 'radio') {
                empty = !form.querySelector(`input[name="${name}"]:checked`);
            } else if (type === 'file') {
                const inp = form.querySelector(`input[name="${name}"]`);
                empty = !inp || inp.files.length === 0;
            } else {
                const inp = form.querySelector(`[name="${name}"]`);
                empty = !inp || !inp.value.trim();
            }

            if (empty) {
                ok = false;
                showErr(wrap, wrap.dataset.lfErr || 'This field is required.');
                if (!first) first = wrap;
            } else {
                clearErr(wrap);
            }
        });

        // 2. Email format — all email inputs that have a value
        form.querySelectorAll('input[type="email"]').forEach(inp => {
            const val = inp.value.trim();
            if (!val) return; // empty handled by required check above
            if (!emailRe.test(val)) {
                ok = false;
                const wrap = inp.closest('[data-lf-req]') || inp.parentElement;
                if (!wrap.querySelector('.lf-field-err')) {
                    showErr(wrap, wrap.dataset?.lfErr || 'Please enter a valid email address.');
                }
                if (!first) first = wrap;
            }
        });

        // 3. File size check
        form.querySelectorAll('input[type="file"][data-lf-maxsize]').forEach(inp => {
            if (!inp.files || !inp.files.length) return;
            const maxMB = parseFloat(inp.getAttribute('data-lf-maxsize'));
            if (!maxMB) return;
            const fileMB = inp.files[0].size / (1024 * 1024);
            if (fileMB > maxMB) {
                ok = false;
                const wrap = inp.closest('[data-lf-req]') || inp.parentElement;
                if (!wrap.querySelector('.lf-field-err')) showErr(wrap, `File size must not exceed ${maxMB}MB.`);
                if (!first) first = wrap;
            }
        });

        // 4. File accepted types check
        form.querySelectorAll('input[type="file"][accept]').forEach(inp => {
            if (!inp.files || !inp.files.length) return;
            const accept = inp.getAttribute('accept');
            if (!accept) return;
            const file = inp.files[0];
            const patterns = accept.split(',').map(s => s.trim().toLowerCase());
            const valid = patterns.some(p => {
                if (p.startsWith('.')) return file.name.toLowerCase().endsWith(p);
                if (p.endsWith('/*'))  return file.type.toLowerCase().startsWith(p.replace('/*', ''));
                return file.type.toLowerCase() === p;
            });
            if (!valid) {
                ok = false;
                const wrap = inp.closest('[data-lf-req]') || inp.parentElement;
                if (!wrap.querySelector('.lf-field-err')) showErr(wrap, `Allowed file types: ${accept}`);
                if (!first) first = wrap;
            }
        });

        @if($turnstileEnabled)
        // 5. Turnstile check
        const tsResponse = form.querySelector('[name="cf-turnstile-response"]');
        if (!tsResponse || !tsResponse.value) {
            ok = false;
            const tsWrap = form.querySelector('.cf-turnstile');
            if (tsWrap && !tsWrap.querySelector('.lf-field-err')) {
                const errSpan = document.createElement('span');
                errSpan.className = 'lf-field-err';
                errSpan.textContent = 'Please complete the security check.';
                tsWrap.insertAdjacentElement('afterend', errSpan);
            }
        }
        @endif

        if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return ok;
    }

    // ── submit ───────────────────────────────────────────────────────
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        ajaxErr.classList.add('hidden');

        if (!validate()) return;

        const btn     = form.querySelector('button[type=submit]');
        const spinner = btn.querySelector('.btn-spinner');
        const btnText = btn.querySelector('.btn-text');

        btn.disabled = true;
        spinner.classList.remove('hidden');
        btnText.textContent = 'Submitting...';

        try {
            const res  = await fetch('{{ route("frontend.form.submit") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: new FormData(form)
            });
            const data = await res.json();
            if (data.success) {
                form.reset();
                success.classList.remove('hidden');
                success.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                // Hide success after 8s and show form again
                setTimeout(() => {
                    success.classList.add('hidden');
                }, 8000);
            } else {
                ajaxErr.textContent = data.message || 'Something went wrong. Please try again.';
                ajaxErr.classList.remove('hidden');
            }
        } catch (err) {
            ajaxErr.textContent = 'Network error. Please check your connection and try again.';
            ajaxErr.classList.remove('hidden');
        }

        btn.disabled = false;
        spinner.classList.add('hidden');
        btnText.textContent = origLabel;
    });

    // ── floating labels ──────────────────────────────────────────────
    form.querySelectorAll('.lf-field-wrap').forEach(wrap => {
        const inp = wrap.querySelector('.lf-float-input');
        if (!inp) return;

        function updateFill() {
            const filled = inp.tagName === 'SELECT' ? inp.value !== '' : inp.value.trim() !== '';
            wrap.classList.toggle('lf-filled', filled || inp.type === 'date');
        }

        inp.addEventListener('focus',  () => wrap.classList.add('lf-focused'));
        inp.addEventListener('blur',   () => { wrap.classList.remove('lf-focused'); updateFill(); });
        inp.addEventListener('input',  updateFill);
        inp.addEventListener('change', updateFill);

        updateFill();
    });

    // Re-clear floating labels on form reset
    form.addEventListener('reset', () => {
        setTimeout(() => {
            form.querySelectorAll('.lf-field-wrap').forEach(wrap => {
                const inp = wrap.querySelector('.lf-float-input');
                if (inp) wrap.classList.toggle('lf-filled', inp.type === 'date');
                wrap.classList.remove('lf-focused');
            });
        }, 10);
    });
})();
</script>
