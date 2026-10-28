/*
 * Falcon AI — Write with AI in the post editor.
 *
 *   1. Brief: topic, focus keyword, audience, language, tone, length, notes.
 *   2. Outline: a title and sections the author edits, reorders, adds or removes.
 *   3. Article: each section written in turn and streamed in as a preview; the HTML the server
 *      sends at the end of each section is already cleaned.
 *   4. Insert into the editor (replace or append), then SEO title, description, excerpt and tags.
 *
 * Plus "Improve selection" (rewrite, expand, shorten, simplify, translate) and the "Suggest with
 * AI" buttons in Falcon SEO's box. Nothing is saved: the author saves the post as usual.
 *
 * Text from the AI is only ever put into the page as text (textContent), except the cleaned HTML
 * the server returns for the editor.
 */
(function () {
    'use strict';

    var configNode = document.getElementById('falcon-ai-config');
    if (!configNode) return;
    var CFG = JSON.parse(configNode.textContent);

    // ---- helpers ---------------------------------------------------------------------------------
    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            if (key === 'text') node.textContent = attrs[key];
            else if (key === 'class') node.className = attrs[key];
            else if (key.indexOf('on') === 0) node.addEventListener(key.slice(2), attrs[key]);
            else node.setAttribute(key, attrs[key]);
        });
        (children || []).forEach(function (child) { if (child) node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child); });
        return node;
    }

    function options(select, map, chosen) {
        Object.keys(map).forEach(function (value) {
            var option = el('option', { value: value, text: map[value] });
            if (value === chosen) option.selected = true;
            select.appendChild(option);
        });
        return select;
    }

    function editor() { return window.tinymce && window.tinymce.get('wp-editor'); }
    function builderActive() { var t = document.getElementById('editor_type'); return t && t.value === 'builder'; }
    function editorHtml() {
        var ed = editor();
        if (ed) return ed.getContent();
        var area = document.querySelector('textarea[name="content"]');
        return area ? area.value : '';
    }
    function postTitleInput() { return document.getElementById('title-input') || document.querySelector('input[name="title"]'); }
    function focusKeyword() { var k = document.getElementById('seo-focus-keyword'); return k ? k.value.trim() : ''; }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CFG.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (res.ok) return data;
                var message = data.message || (data.errors && Object.values(data.errors)[0] && Object.values(data.errors)[0][0]) || 'The request failed (' + res.status + ').';
                throw new Error(message);
            });
        });
    }

    /**
     * One section as server-sent events. onText gets raw text for the preview; resolves with the
     * cleaned HTML of the "done" event.
     */
    function streamSection(body, onText, signal) {
        return fetch(CFG.routes.section, {
            method: 'POST',
            credentials: 'same-origin',
            signal: signal,
            headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': CFG.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (res) {
            if (!res.ok || !res.body) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    throw new Error(data.message || (data.errors && Object.values(data.errors)[0][0]) || 'The request failed (' + res.status + ').');
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            var html = null;
            function handle(block) {
                var event = 'message';
                var data = '';
                block.split('\n').forEach(function (line) {
                    if (line.indexOf('event:') === 0) event = line.slice(6).trim();
                    else if (line.indexOf('data:') === 0) data += line.slice(5).trim();
                });
                if (!data) return;
                var payload = JSON.parse(data);
                if (event === 'text') onText(payload.text || '');
                else if (event === 'done') html = payload.html || '';
                else if (event === 'error') throw new Error(payload.message || 'Something went wrong while writing.');
            }
            function pump() {
                return reader.read().then(function (part) {
                    buffer += decoder.decode(part.value || new Uint8Array(), { stream: !part.done });
                    var at;
                    while ((at = buffer.indexOf('\n\n')) !== -1) {
                        handle(buffer.slice(0, at));
                        buffer = buffer.slice(at + 2);
                    }
                    if (part.done) {
                        if (buffer.trim()) handle(buffer);
                        if (html === null) throw new Error('The connection ended before the section was finished.');
                        return html;
                    }
                    return pump();
                });
            }
            return pump();
        });
    }

    /**
     * Turn a <select> into a searchable one (Tom Select, shipped with the CMS). Loaded once, the
     * first time it is needed; until then, or if it cannot load, the plain select works.
     */
    var tomSelectLoading = null;
    function searchable(select) {
        if (!CFG.tomSelect) return;
        if (!tomSelectLoading) {
            tomSelectLoading = window.TomSelect ? Promise.resolve() : new Promise(function (resolve, reject) {
                document.head.appendChild(el('link', { rel: 'stylesheet', href: CFG.tomSelect.css }));
                var script = el('script', { src: CFG.tomSelect.js });
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        tomSelectLoading.then(function () {
            if (window.TomSelect && document.contains(select) && !select.tomselect) {
                new window.TomSelect(select, {
                    maxOptions: 200, create: false, dropdownParent: 'body', plugins: ['dropdown_input'],
                    wrapperClass: 'ts-wrapper falcon-ai-ts', dropdownClass: 'ts-dropdown falcon-ai-ts-dropdown'
                });
            }
        }).catch(function () { /* the plain select stays */ });
    }

    // ---- the modal ---------------------------------------------------------------------------------
    var state = { brief: null, outline: null, html: [], controller: null };
    var modal, body, footer, opener;

    function openModal(trigger) {
        opener = trigger;
        if (!modal) {
            body = el('div', { class: 'falcon-ai-modal__body' });
            footer = el('div', { class: 'falcon-ai-modal__footer' });
            modal = el('div', { class: 'falcon-ai-modal', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'falcon-ai-title' }, [
                el('div', { class: 'falcon-ai-modal__backdrop', onclick: closeModal }),
                el('div', { class: 'falcon-ai-modal__panel', tabindex: '-1' }, [
                    el('div', { class: 'falcon-ai-modal__header' }, [
                        el('h2', { id: 'falcon-ai-title', text: '✨ Write with AI' }),
                        el('button', { type: 'button', class: 'falcon-ai-modal__close', 'aria-label': 'Close', text: '×', onclick: closeModal })
                    ]),
                    body, footer
                ])
            ]);
            document.body.appendChild(modal);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal && !modal.hidden) closeModal(); });
        }
        modal.hidden = false;
        document.documentElement.classList.add('falcon-ai-open');
        if (!CFG.ready) showNotReady(); else if (state.outline) showOutline(); else showBrief();
        modal.querySelector('.falcon-ai-modal__panel').focus();
    }

    function closeModal() {
        if (state.controller) state.controller.abort();
        modal.hidden = true;
        document.documentElement.classList.remove('falcon-ai-open');
        if (opener) opener.focus();
    }

    function setFooter(buttons) {
        footer.textContent = '';
        buttons.forEach(function (b) { if (b) footer.appendChild(b); });
    }

    function button(label, onClick, primary) {
        return el('button', { type: 'button', class: 'falcon-ai-btn' + (primary ? ' falcon-ai-btn--primary' : ''), text: label, onclick: onClick });
    }

    function message(text, kind) {
        return el('p', { class: 'falcon-ai-msg' + (kind ? ' falcon-ai-msg--' + kind : ''), role: kind === 'error' ? 'alert' : 'status', text: text });
    }

    function showNotReady() {
        body.textContent = '';
        body.appendChild(message('AI writing is not set up yet: an administrator adds an API key (Claude or OpenAI) under AI → Settings.'));
        setFooter([CFG.routes.settings ? el('a', { class: 'falcon-ai-btn falcon-ai-btn--primary', href: CFG.routes.settings, text: 'Open AI settings' }) : null, button('Close', closeModal)]);
    }

    // Step 1 ------------------------------------------------------------------------------------------
    function field(label, input, hint) {
        var id = 'falcon-ai-' + Math.random().toString(36).slice(2, 8);
        input.id = id;
        return el('div', { class: 'falcon-ai-field' }, [el('label', { for: id, text: label }), input, hint ? el('small', { text: hint }) : null]);
    }

    function showBrief(error) {
        var previous = state.brief || {};
        var titleInput = postTitleInput();
        var topic = el('input', { type: 'text', maxlength: '300', required: 'required', placeholder: 'What should the article be about?' });
        topic.value = previous.topic || (titleInput ? titleInput.value : '');
        var keyword = el('input', { type: 'text', maxlength: '120', placeholder: 'Optional' });
        keyword.value = previous.keyword || focusKeyword();
        var audience = el('input', { type: 'text', maxlength: '200', placeholder: 'e.g. small shop owners in Dhaka' });
        audience.value = previous.audience || '';
        var language = options(el('select'), CFG.languages, previous.language || CFG.language);
        var tone = options(el('select'), CFG.tones, previous.tone || CFG.tone);
        var length = options(el('select'), { short: 'Short (~600 words)', medium: 'Medium (~1,300 words)', long: 'Long (~2,200 words)' }, previous.length || 'medium');
        var notes = el('textarea', { rows: '3', maxlength: '2000', placeholder: 'Anything it must include or avoid (optional)' });
        notes.value = previous.notes || '';

        body.textContent = '';
        if (error) body.appendChild(message(error, 'error'));
        body.appendChild(field('Topic', topic));
        body.appendChild(el('div', { class: 'falcon-ai-grid' }, [field('Focus keyword', keyword), field('Audience', audience)]));
        body.appendChild(el('div', { class: 'falcon-ai-grid falcon-ai-grid--3' }, [field('Language', language), field('Tone', tone), field('Length', length)]));
        body.appendChild(field('Notes', notes));
        searchable(language);

        var go = button('Plan the outline', function () {
            if (topic.value.trim().length < 3) { topic.focus(); return; }
            state.brief = {
                topic: topic.value.trim(), keyword: keyword.value.trim(), audience: audience.value.trim(),
                language: language.value, tone: tone.value, length: length.value, notes: notes.value.trim()
            };
            requestOutline();
        }, true);
        setFooter([button('Cancel', closeModal), go]);
        topic.focus();
    }

    function requestOutline() {
        body.textContent = '';
        body.appendChild(el('div', { class: 'falcon-ai-loading' }, [el('span', { class: 'falcon-ai-spinner' }), document.createTextNode('Planning the outline…')]));
        setFooter([]);
        post(CFG.routes.outline, state.brief).then(function (data) {
            state.outline = data;
            showOutline();
        }).catch(function (e) { showBrief(e.message); });
    }

    // Step 2 ------------------------------------------------------------------------------------------
    function showOutline(error) {
        var outline = state.outline;
        var title = el('input', { type: 'text', maxlength: '300' });
        title.value = outline.title || '';
        title.addEventListener('input', function () { outline.title = title.value; });

        var list = el('ol', { class: 'falcon-ai-outline' });
        function draw() {
            list.textContent = '';
            outline.sections.forEach(function (section, i) {
                var heading = el('input', { type: 'text', maxlength: '200', 'aria-label': 'Section ' + (i + 1) + ' heading' });
                heading.value = section.heading;
                heading.addEventListener('input', function () { section.heading = heading.value; });
                var points = el('textarea', { rows: '2', 'aria-label': 'Points for section ' + (i + 1) + ', one per line' });
                points.value = (section.points || []).join('\n');
                points.addEventListener('input', function () { section.points = points.value.split('\n').map(function (p) { return p.trim(); }).filter(Boolean).slice(0, 8); });
                var move = function (by) { return function () { var j = i + by; if (j < 0 || j >= outline.sections.length) return; outline.sections.splice(j, 0, outline.sections.splice(i, 1)[0]); draw(); }; };
                list.appendChild(el('li', {}, [
                    el('div', { class: 'falcon-ai-outline__row' }, [
                        heading,
                        el('button', { type: 'button', class: 'falcon-ai-icon', title: 'Move up', 'aria-label': 'Move up', text: '↑', onclick: move(-1) }),
                        el('button', { type: 'button', class: 'falcon-ai-icon', title: 'Move down', 'aria-label': 'Move down', text: '↓', onclick: move(1) }),
                        el('button', { type: 'button', class: 'falcon-ai-icon', title: 'Remove', 'aria-label': 'Remove section', text: '✕', onclick: function () { if (outline.sections.length > 1) { outline.sections.splice(i, 1); draw(); } } })
                    ]),
                    points
                ]));
            });
        }
        draw();

        body.textContent = '';
        if (error) body.appendChild(message(error, 'error'));
        body.appendChild(message('Edit anything before the article is written: headings, the points under them (one per line), the order.'));
        body.appendChild(field('Title', title));
        body.appendChild(list);
        body.appendChild(button('+ Add a section', function () {
            if (outline.sections.length >= 12) return;
            outline.sections.push({ heading: '', points: [] });
            draw();
            list.lastChild.querySelector('input').focus();
        }));

        setFooter([
            button('← Brief', function () { showBrief(); }),
            button('New outline', requestOutline),
            button('Write the article', function () {
                outline.sections = outline.sections.filter(function (s) { return s.heading.trim() !== ''; });
                if (!outline.sections.length) return;
                state.html = [];
                writeArticle(0);
            }, true)
        ]);
    }

    // Step 3 ------------------------------------------------------------------------------------------
    function writeArticle(from) {
        var outline = state.outline;
        var headings = outline.sections.map(function (s) { return s.heading.trim(); });
        var progress = el('p', { class: 'falcon-ai-progress' });
        var preview = el('div', { class: 'falcon-ai-preview', 'aria-live': 'polite' });
        var done = el('div', { class: 'falcon-ai-written' });
        state.html.slice(0, from).forEach(function (h, i) { done.appendChild(el('p', { class: 'falcon-ai-written__item', text: '✓ ' + headings[i] })); });

        body.textContent = '';
        body.appendChild(progress);
        body.appendChild(done);
        body.appendChild(preview);
        state.controller = new AbortController();
        setFooter([button('Stop', function () { state.controller.abort(); })]);

        function next(i) {
            if (i >= headings.length) { state.controller = null; showFinish(); return; }
            progress.textContent = 'Writing section ' + (i + 1) + ' of ' + headings.length + ': ' + headings[i];
            preview.textContent = '';
            var section = outline.sections[i];
            streamSection(Object.assign({}, state.brief, {
                title: outline.title || state.brief.topic, headings: headings, index: i, heading: headings[i], points: section.points || []
            }), function (text) {
                preview.textContent += text;
                preview.scrollTop = preview.scrollHeight;
            }, state.controller.signal).then(function (html) {
                state.html[i] = html;
                done.appendChild(el('p', { class: 'falcon-ai-written__item', text: '✓ ' + headings[i] }));
                next(i + 1);
            }).catch(function (e) {
                state.controller = null;
                var stopped = e.name === 'AbortError';
                body.textContent = '';
                body.appendChild(message(stopped ? 'Stopped. ' + i + ' of ' + headings.length + ' sections are written.' : e.message, stopped ? '' : 'error'));
                setFooter([
                    button('← Outline', function () { showOutline(); }),
                    i > 0 ? button('Use what is written', showFinish) : null,
                    button(stopped ? 'Continue' : 'Try again', function () { writeArticle(i); }, true)
                ]);
            });
        }
        next(from);
    }

    // Step 4 ------------------------------------------------------------------------------------------
    function showFinish() {
        var html = state.html.filter(Boolean).join('\n');
        var hasContent = editorHtml().replace(/<[^>]*>/g, '').trim() !== '';
        var titleInput = postTitleInput();

        var replace = el('input', { type: 'radio', name: 'falcon-ai-mode', value: 'replace' });
        var append = el('input', { type: 'radio', name: 'falcon-ai-mode', value: 'append' });
        (hasContent ? append : replace).checked = true;
        var useTitle = el('input', { type: 'checkbox' });
        useTitle.checked = !titleInput || titleInput.value.trim() === '';
        var seo = el('input', { type: 'checkbox' });
        seo.checked = true;

        body.textContent = '';
        body.appendChild(message('The article is ready: ' + state.html.filter(Boolean).length + ' sections. Read it through before you publish: AI can get facts wrong.'));
        if (builderActive()) body.appendChild(message('The Page Builder is open: switch to the Rich Editor to put the article in.', 'error'));
        if (hasContent) {
            body.appendChild(el('div', { class: 'falcon-ai-choice' }, [
                el('label', {}, [replace, ' Replace what is in the editor']),
                el('label', {}, [append, ' Add it after what is there'])
            ]));
        }
        body.appendChild(el('div', { class: 'falcon-ai-choice' }, [
            titleInput ? el('label', {}, [useTitle, ' Use the title “' + (state.outline.title || '') + '”']) : null,
            el('label', {}, [seo, ' Then write the SEO title, description, excerpt and tags'])
        ]));

        var put = button('Put it in the editor', function () {
                if (builderActive()) return;
                insert(html, replace.checked || !hasContent);
                if (titleInput && useTitle.checked && state.outline.title) {
                    titleInput.value = state.outline.title;
                    titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (seo.checked) writeMeta(); else closeModal();
            }, true);
        // With the Page Builder open there is no editor to put it in (the message above says so).
        put.disabled = builderActive();
        setFooter([button('← Outline', function () { showOutline(); }), put]);
    }

    function insert(html, replaceAll) {
        var ed = editor();
        if (ed) {
            ed.undoManager.transact(function () {
                if (replaceAll) ed.setContent(html);
                else { ed.selection.select(ed.getBody(), true); ed.selection.collapse(false); ed.insertContent(html); }
            });
            ed.fire('change');
        } else {
            var area = document.querySelector('textarea[name="content"]');
            if (area) area.value = replaceAll ? html : area.value + '\n' + html;
        }
    }

    function writeMeta() {
        body.textContent = '';
        body.appendChild(el('div', { class: 'falcon-ai-loading' }, [el('span', { class: 'falcon-ai-spinner' }), document.createTextNode('Writing the SEO title, description, excerpt and tags…')]));
        setFooter([]);
        post(CFG.routes.meta, {
            title: (postTitleInput() && postTitleInput().value) || state.outline.title || state.brief.topic,
            content: editorHtml(), keyword: state.brief.keyword, language: state.brief.language
        }).then(function (data) {
            var filled = [];
            filled.push(fill(document.getElementById('seo-title-input'), data.seo_title, 'SEO title'));
            filled.push(fill(document.getElementById('seo-desc-input'), data.meta_description, 'meta description'));
            filled.push(fill(document.getElementById('excerpt'), data.excerpt, 'excerpt'));
            var tagInput = document.getElementById('tag-input');
            var tagButton = document.getElementById('add-tag-btn');
            if (tagInput && tagButton && data.tags && data.tags.length) {
                // Through the page's own tag box, so its list and hidden field stay in step.
                tagInput.value = data.tags.join(', ');
                tagButton.click();
                filled.push('tags');
            }
            body.textContent = '';
            body.appendChild(message('Done. Filled: ' + (filled.filter(Boolean).join(', ') || 'nothing (those fields already had text)') + '. Review everything, then save the post.'));
            if (data.seo_title) body.appendChild(el('p', { class: 'falcon-ai-small', text: 'SEO title: ' + data.seo_title }));
            if (data.meta_description) body.appendChild(el('p', { class: 'falcon-ai-small', text: 'Description: ' + data.meta_description }));
            setFooter([button('Close', function () { state.outline = null; closeModal(); }, true)]);
        }).catch(function (e) {
            body.textContent = '';
            body.appendChild(message('The article is in the editor. The SEO part failed: ' + e.message, 'error'));
            setFooter([button('Try again', writeMeta), button('Close', closeModal, true)]);
        });
    }

    /** Fill a field only when it is empty: never overwrite what the author wrote. */
    function fill(input, value, label) {
        if (!input || !value || input.value.trim() !== '') return null;
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return label;
    }

    // ---- Improve selection ---------------------------------------------------------------------------
    function setupImprove() {
        var toggle = document.querySelector('[data-ai-improve-toggle]');
        var menu = document.querySelector('[data-ai-improve-menu]');
        var status = document.querySelector('[data-ai-status]');
        if (!toggle || !menu) return;

        function close() { menu.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
        toggle.addEventListener('click', function () { menu.hidden = !menu.hidden; toggle.setAttribute('aria-expanded', String(!menu.hidden)); });
        document.addEventListener('click', function (e) { if (!e.target.closest('.falcon-ai-improve')) close(); });

        menu.querySelectorAll('[data-ai-action]').forEach(function (item) {
            item.addEventListener('click', function () {
                close();
                var ed = editor();
                var selected = ed ? ed.selection.getContent() : '';
                if (!ed || builderActive() || selected.replace(/<[^>]*>/g, '').trim() === '') {
                    status.textContent = 'Select some text in the Rich Editor first.';
                    return;
                }
                var bookmark = ed.selection.getBookmark(2, true);
                status.textContent = 'Working…';
                toggle.disabled = true;
                post(CFG.routes.rewrite, { html: selected, action: item.getAttribute('data-ai-action') }).then(function (data) {
                    ed.selection.moveToBookmark(bookmark);
                    ed.undoManager.transact(function () { ed.selection.setContent(data.html); });
                    ed.fire('change');
                    status.textContent = 'Done. Ctrl+Z puts it back.';
                }).catch(function (e) {
                    status.textContent = e.message;
                }).then(function () { toggle.disabled = false; });
            });
        });
    }

    // ---- Falcon SEO: "Suggest with AI" -----------------------------------------------------------------
    function setupSeo() {
        // Falcon SEO's Analysis tab offers "Suggest with AI" for a weak title or description through this.
        window.falconSeoAi = {
            suggest: function (field) {
                var trigger = document.getElementById('falcon-ai-seo-' + field);
                if (trigger) { trigger.scrollIntoView({ block: 'center' }); trigger.click(); }
            }
        };
        [['title', 'seo-title-count', 'seo-title-input'], ['description', 'seo-desc-count', 'seo-desc-input']].forEach(function (f) {
            var counter = document.getElementById(f[1]);
            var input = document.getElementById(f[2]);
            if (!counter || !input || document.getElementById('falcon-ai-seo-' + f[0])) return;
            var box = el('div', { class: 'falcon-ai-seo-box' });
            input.parentNode.insertBefore(box, input.nextSibling);
            var trigger = el('button', { type: 'button', id: 'falcon-ai-seo-' + f[0], class: 'falcon-ai-seo-btn', text: '✨ Suggest with AI' });
            counter.parentNode.insertBefore(trigger, counter);

            trigger.addEventListener('click', function () {
                box.textContent = '';
                if (!CFG.ready) { box.appendChild(message('Add an API key under AI → Settings first.', 'error')); return; }
                box.appendChild(message('Writing…'));
                trigger.disabled = true;
                post(CFG.routes.seo, {
                    field: f[0],
                    title: (postTitleInput() && postTitleInput().value) || '',
                    keyword: focusKeyword(),
                    current: input.value || '',
                    content: editorHtml()
                }).then(function (data) {
                    box.textContent = '';
                    var list = el('div', { class: 'falcon-ai-seo-options' });
                    (data.options || []).forEach(function (o) {
                        list.appendChild(el('button', { type: 'button', class: 'falcon-ai-seo-option', onclick: function () {
                            input.value = o.text;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            box.textContent = '';
                        } }, [el('span', { text: o.text }), el('small', { text: o.text.length + ' characters · ' + o.why })]));
                    });
                    list.appendChild(el('small', { text: 'Click one to use it, then save the page.' }));
                    box.appendChild(list);
                }).catch(function (e) {
                    box.textContent = '';
                    box.appendChild(message(e.message, 'error'));
                }).then(function () { trigger.disabled = false; });
            });
        });
    }

    function init() {
        var open = document.querySelector('[data-ai-open]');
        if (open) open.addEventListener('click', function () { openModal(open); });
        setupImprove();
        setupSeo();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
