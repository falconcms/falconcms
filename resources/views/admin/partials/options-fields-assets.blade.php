{{--
    Shared CSS + JS for the option/settings field widgets (multiselect, tabs,
    repeater, wysiwyg, media pickers). Rendered inline and guarded by @once so it
    can be included by both the Options Page view and the native-settings
    extension renderer without duplicating or depending on @stack timing.
--}}
@once
    <style>
        .fms-multiselect { position: relative; }
        .fms-control { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; min-height: 38px; padding: 5px 9px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: text; }
        .fms-control.fms-open, .fms-control:focus-within { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .fms-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; }
        .fms-chips:empty { display: none; }
        .fms-chip { display: inline-flex; align-items: center; gap: 5px; background: #eef2f7; border: 1px solid #d6dde5; color: #1d2327; font-size: 12.5px; line-height: 1.4; padding: 2px 5px 2px 9px; border-radius: 3px; }
        .fms-chip button { border: 0; background: transparent; color: #646970; cursor: pointer; font-size: 15px; line-height: 1; padding: 0; }
        .fms-chip button:hover { color: #b32d2e; }
        .fms-search { flex: 1; min-width: 90px; border: 0; outline: none; font-size: 13.5px; padding: 3px 2px; background: transparent; color: #1d2327; }
        .fms-dropdown { position: absolute; z-index: 30; left: 0; right: 0; margin-top: 4px; max-height: 250px; overflow-y: auto; background: #fff; border: 1px solid #aeb3b8; border-radius: 7px; box-shadow: 0 10px 30px -8px rgba(0,0,0,.22); padding: 5px; }
        .fms-dropdown::-webkit-scrollbar { width: 9px; }
        .fms-dropdown::-webkit-scrollbar-thumb { background: #d0d5da; border-radius: 6px; border: 2px solid #fff; }
        .fms-option { padding: 8px 11px; font-size: 13.5px; cursor: pointer; color: #1d2327; border-radius: 5px; transition: background .12s ease, color .12s ease; }
        .fms-option:hover, .fms-option.fms-active { background: #2271b1; color: #fff; }
        .fms-empty { padding: 10px 11px; font-size: 12.5px; color: #8a8f94; }
        /* Tabs */
        .fms-tabs-nav { display: flex; flex-wrap: wrap; gap: 2px; border-bottom: 1px solid #c3c4c7; margin-bottom: 16px; }
        .fms-tab-btn { padding: 8px 14px; font-size: 13.5px; font-weight: 600; color: #50575e; background: transparent; border: 0; border-bottom: 2px solid transparent; margin-bottom: -1px; cursor: pointer; display: inline-flex; align-items: center; }
        .fms-tab-btn:hover { color: #2271b1; }
        .fms-tab-btn.fms-tab-active { color: #2271b1; border-bottom-color: #2271b1; }
    </style>

    <script>
        // Image fields open the shared media modal (window.openMediaModal, provided by
        // the admin layout) and store the chosen URL in the field's hidden input.
        window.falconOptPickImage = function (name) {
            if (typeof window.openMediaModal !== 'function') {
                if (window.showToast) window.showToast('Media picker is not available on this page.', 'error');
                return;
            }
            window.openMediaModal(function (attachment) {
                var url = attachment.full_url || attachment.url || attachment.path || attachment.guid || '';
                if (!url) return;
                if (url.indexOf('media/') === 0) url = '/storage/' + url;
                else if (url.indexOf('http') !== 0 && url.indexOf('/') !== 0) url = '/' + url;
                var input = document.getElementById('opt-' + name);
                var prev  = document.getElementById('opt-prev-' + name);
                if (input) input.value = url;
                if (prev) { prev.src = url; prev.style.display = 'block'; }
            });
        };
        window.falconOptClearImage = function (name) {
            var input = document.getElementById('opt-' + name);
            var prev  = document.getElementById('opt-prev-' + name);
            if (input) input.value = '';
            if (prev) { prev.src = ''; prev.style.display = 'none'; }
        };

        // File field — same media modal, but shows a file link instead of a preview.
        window.falconOptPickFile = function (name) {
            if (typeof window.openMediaModal !== 'function') {
                if (window.showToast) window.showToast('Media picker is not available on this page.', 'error');
                return;
            }
            window.openMediaModal(function (attachment) {
                var url = attachment.full_url || attachment.url || attachment.path || attachment.guid || '';
                if (!url) return;
                if (url.indexOf('media/') === 0) url = '/storage/' + url;
                else if (url.indexOf('http') !== 0 && url.indexOf('/') !== 0) url = '/' + url;
                document.getElementById('opt-' + name).value = url;
                var link = document.getElementById('opt-file-' + name);
                var rm   = document.getElementById('opt-file-rm-' + name);
                if (link) { link.href = url; link.textContent = url.split('/').pop(); link.style.display = ''; }
                if (rm) rm.style.display = '';
            });
        };
        window.falconOptClearFile = function (name) {
            document.getElementById('opt-' + name).value = '';
            var link = document.getElementById('opt-file-' + name);
            var rm   = document.getElementById('opt-file-rm-' + name);
            if (link) link.style.display = 'none';
            if (rm) rm.style.display = 'none';
        };

        // Tabs — client-side switching within the form; the active tab is mirrored to
        // ?tab= (for deep links) and to a hidden input (so save returns to the same tab).
        function falconInitTabs() {
            document.querySelectorAll('.fms-tabs-nav').forEach(function (nav) {
                if (nav._fmst) return; nav._fmst = true;
                var scope  = nav.closest('form') || document;
                var btns   = nav.querySelectorAll('.fms-tab-btn');
                var panels = scope.querySelectorAll('.fms-tab-panel');
                var hidden = scope.querySelector('#fms-active-tab');
                function activate(id) {
                    btns.forEach(function (b) { b.classList.toggle('fms-tab-active', b.dataset.tab === id); });
                    panels.forEach(function (p) { p.hidden = (p.dataset.tab !== id); });
                    if (hidden) hidden.value = id;
                    try { var u = new URL(window.location.href); u.searchParams.set('tab', id); history.replaceState(null, '', u.toString()); } catch (e) {}
                }
                var ids = Array.prototype.map.call(btns, function (b) { return b.dataset.tab; });
                btns.forEach(function (b) { b.addEventListener('click', function () { activate(b.dataset.tab); }); });
                var initial = new URLSearchParams(window.location.search).get('tab');
                activate(ids.indexOf(initial) !== -1 ? initial : ids[0]);
            });
        }

        // Smart multi-select (Select2-style: searchable dropdown + removable chips),
        // dependency-free. Selected values submit as name[] hidden inputs.
        function falconInitMultiselects() {
            document.querySelectorAll('.fms-multiselect').forEach(function (root) {
                if (root._fms) return; root._fms = true;

                var field    = root.dataset.field;
                var cfg      = JSON.parse(root.dataset.config || '{}');
                var options  = cfg.options || {};                       // { value: label }
                var selected = (cfg.selected || []).map(String);
                var ph       = root.dataset.placeholder || 'Type to search…';
                var isTags   = !!root.dataset.tags;                     // free-form entry mode
                var control  = root.querySelector('.fms-control');
                var chips    = root.querySelector('.fms-chips');
                var search   = root.querySelector('.fms-search');
                var dropdown = root.querySelector('.fms-dropdown');
                var active   = -1;

                function syncHidden() {
                    root.querySelectorAll('input.fms-hidden').forEach(function (i) { i.remove(); });
                    selected.forEach(function (v) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden'; inp.name = field + '[]'; inp.value = v; inp.className = 'fms-hidden';
                        root.appendChild(inp);
                    });
                }
                function renderChips() {
                    chips.innerHTML = '';
                    selected.forEach(function (v) {
                        var chip = document.createElement('span'); chip.className = 'fms-chip';
                        var lbl = document.createElement('span'); lbl.textContent = (options[v] != null ? options[v] : v);
                        var x = document.createElement('button'); x.type = 'button'; x.innerHTML = '&times;'; x.setAttribute('aria-label', 'Remove');
                        x.addEventListener('click', function (e) { e.stopPropagation(); remove(v); });
                        chip.appendChild(lbl); chip.appendChild(x); chips.appendChild(chip);
                    });
                    search.placeholder = selected.length ? '' : ph;
                }
                function matches() {
                    var q = search.value.toLowerCase();
                    return Object.keys(options).filter(function (v) {
                        return selected.indexOf(String(v)) === -1 && String(options[v] || '').toLowerCase().indexOf(q) !== -1;
                    });
                }
                function renderDropdown() {
                    var avail = matches(); dropdown.innerHTML = '';
                    if (!avail.length) {
                        var e = document.createElement('div'); e.className = 'fms-empty';
                        e.textContent = isTags ? 'Press Enter to add a tag' : 'No matches';
                        dropdown.appendChild(e); return;
                    }
                    avail.forEach(function (v, i) {
                        var o = document.createElement('div');
                        o.className = 'fms-option' + (i === active ? ' fms-active' : '');
                        o.textContent = options[v]; o.dataset.value = v;
                        o.addEventListener('mousedown', function (e) { e.preventDefault(); add(v); });
                        dropdown.appendChild(o);
                    });
                }
                function open()  { dropdown.hidden = false; control.classList.add('fms-open'); active = -1; renderDropdown(); }
                function close() { dropdown.hidden = true; control.classList.remove('fms-open'); }
                function add(v)  { v = String(v).trim(); if (!v) return; if (isTags && options[v] === undefined) options[v] = v; if (selected.indexOf(v) === -1) { selected.push(v); renderChips(); syncHidden(); } search.value = ''; active = -1; renderDropdown(); search.focus(); }
                function remove(v) { v = String(v); selected = selected.filter(function (x) { return x !== v; }); renderChips(); syncHidden(); renderDropdown(); }

                control.addEventListener('click', function () { search.focus(); open(); });
                search.addEventListener('focus', open);
                search.addEventListener('input', function () { active = -1; renderDropdown(); });
                search.addEventListener('keydown', function (e) {
                    var avail = matches();
                    if (e.key === 'ArrowDown')      { e.preventDefault(); active = Math.min(avail.length - 1, active + 1); renderDropdown(); }
                    else if (e.key === 'ArrowUp')   { e.preventDefault(); active = Math.max(0, active - 1); renderDropdown(); }
                    else if (e.key === 'Enter')     { e.preventDefault(); if (active >= 0 && avail[active]) add(avail[active]); else if (isTags && search.value.trim()) add(search.value.trim()); }
                    else if (isTags && e.key === ',') { e.preventDefault(); if (search.value.trim()) add(search.value.trim()); }
                    else if (e.key === 'Backspace' && !search.value && selected.length) { remove(selected[selected.length - 1]); }
                    else if (e.key === 'Escape')    { close(); }
                });
                document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });

                renderChips(); syncHidden();
            });
        }

        // Repeater — add/remove rows cloned from a <template>; each row's inputs are
        // named name[index][subfield] and post as an array the controller stores as JSON.
        function falconInitRepeaters() {
            document.querySelectorAll('.fms-repeater').forEach(function (root) {
                if (root._fmsr) return; root._fmsr = true;
                var rows = root.querySelector('.fms-rows');
                var tpl = root.querySelector('.fms-row-tpl');
                var addBtn = root.querySelector('.fms-add-row');
                var counter = 0;
                function bindRemove(node) {
                    var rm = node.querySelector('.fms-row-remove');
                    if (rm) rm.addEventListener('click', function () { node.remove(); });
                }
                rows.querySelectorAll('.fms-row').forEach(bindRemove);
                if (addBtn && tpl) addBtn.addEventListener('click', function () {
                    var idx = 'n' + (counter++) + '_' + Date.now();
                    var html = (tpl.innerHTML || '').replace(/__INDEX__/g, idx).trim();
                    var tmp = document.createElement('div'); tmp.innerHTML = html;
                    var node = tmp.firstElementChild;
                    if (node) { rows.appendChild(node); bindRemove(node); }
                });
            });
        }

        // Rich-text — reuse the CMS's TinyMCE (loaded root-relative so APP_URL can't break it).
        function falconInitWysiwyg() {
            if (!document.querySelector('.fms-wysiwyg')) return;
            function boot() {
                if (typeof tinymce === 'undefined') return;
                // The bundled core loads its skins/themes/plugins from this CDN base
                // (only tinymce.min.js is shipped locally) — same as the Falcon Builder.
                tinymce.baseURL = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';
                tinymce.init({
                    selector: '.fms-wysiwyg', menubar: false, height: 320, branding: false, license_key: 'gpl',
                    plugins: 'lists link code table wordcount preview fullscreen',
                    toolbar: 'blocks | bold italic underline strikethrough | bullist numlist | alignleft aligncenter alignright | link table | code fullscreen',
                    entity_encoding: 'raw', valid_elements: '*[*]', extended_valid_elements: '*[*]',
                    content_style: 'body{font-family:-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px;padding:10px;}',
                    setup: function (ed) { ed.on('change keyup', function () { ed.save(); }); }
                });
            }
            if (typeof tinymce !== 'undefined') { boot(); return; }
            var s = document.createElement('script');
            s.src = '{{ \Illuminate\Support\Str::start((string) parse_url(asset('vendor/falcon-cms/js/tinymce.min.js'), PHP_URL_PATH), '/') }}';
            s.onload = boot;
            document.head.appendChild(s);
        }

        function falconInitOptionFields() {
            falconInitTabs();
            falconInitMultiselects();
            falconInitRepeaters();
            falconInitWysiwyg();
        }
        document.addEventListener('DOMContentLoaded', falconInitOptionFields);
        // Run now too, in case the DOM is already parsed when this script executes.
        falconInitOptionFields();
    </script>
@endonce
