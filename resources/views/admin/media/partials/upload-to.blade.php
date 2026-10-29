{{--
    "Upload to" with "New folder" — the same everywhere files are uploaded: the upload page and
    the media window (pages, posts, the builder, settings). The new folder goes at the top
    level or inside another one ("Where"), and is then picked for the upload.

    $selectId  id of the folder <select> (the uploader reads its value as folder_id)
    $folders   MediaFolder::tree(), or [] when the list is loaded later (the media window)
    $selected  the folder picked to begin with

    Every copy keeps its list in step with the others: a folder made in one, or a fresh list
    from the media window, is announced with the falcon-media-folders event.
--}}
@php
    $selected = (int) ($selected ?? 0);
@endphp
<div class="falcon-upload-to w-full" data-folders='@json($folders ?? [])' data-selected="{{ $selected ?: '' }}">
    <div class="flex flex-wrap items-center gap-2 text-[13px]">
        <label for="{{ $selectId }}" class="flex items-center gap-1 text-[#1d2327] font-semibold"><span class="material-symbols-outlined text-[18px] text-[#dba617]">folder</span>Upload to</label>
        <select id="{{ $selectId }}" class="falcon-upload-to-select wp-input h-8 text-[13px] py-0 w-full sm:w-64"></select>
        @if(falcon_admin_can_write('admin/media', ''))
            <button type="button" class="falcon-upload-to-new wp-btn-secondary h-8 px-3 text-[13px] inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">create_new_folder</span>New folder</button>
        @endif
    </div>
    <form class="falcon-upload-to-form hidden flex-wrap items-end gap-2 mt-2 p-3 bg-white border border-[#c3c4c7] text-[13px]">
        <div class="w-full sm:w-56">
            <label class="block text-[12px] text-[#646970] mb-1">Folder name</label>
            <input type="text" maxlength="100" class="falcon-upload-to-name wp-input w-full h-8 text-[13px]" placeholder="e.g. Products">
        </div>
        <div class="w-full sm:w-64">
            <label class="block text-[12px] text-[#646970] mb-1">Where</label>
            <select class="falcon-upload-to-parent wp-input w-full h-8 text-[13px] py-0"></select>
        </div>
        <button type="submit" class="wp-btn-primary h-8 px-4 text-[13px]">Create</button>
        <button type="button" class="falcon-upload-to-cancel wp-btn-secondary h-8 px-3 text-[13px]">Cancel</button>
    </form>
</div>

@once
<script>
    (function () {
        const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const label = f => '— '.repeat(f.depth) + f.name;
        const json = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

        function fill(root, folders, pick) {
            root._folders = folders;
            const sel = root.querySelector('.falcon-upload-to-select'), parent = root.querySelector('.falcon-upload-to-parent');
            const keep = pick !== undefined ? pick : sel.value;
            const opts = folders.map(f => `<option value="${f.id}">${esc(label(f))}</option>`).join('');
            sel.innerHTML = '<option value="">Uncategorized</option>' + opts;
            sel.value = folders.some(f => String(f.id) === String(keep)) ? String(keep) : '';
            parent.innerHTML = '<option value="">At the top level (a main folder)</option>' + opts;
        }

        function setup(root) {
            if (root._ready) return;
            root._ready = true;
            fill(root, JSON.parse(root.dataset.folders || '[]'), root.dataset.selected || '');
            const btn = root.querySelector('.falcon-upload-to-new'), form = root.querySelector('.falcon-upload-to-form');
            const name = root.querySelector('.falcon-upload-to-name'), parent = root.querySelector('.falcon-upload-to-parent');
            const sel = root.querySelector('.falcon-upload-to-select');
            const show = on => {
                form.classList.toggle('hidden', !on); form.classList.toggle('flex', on);
                if (on) { parent.value = sel.value; name.value = ''; name.focus(); }
            };
            if (btn) btn.addEventListener('click', () => show(form.classList.contains('hidden')));
            root.querySelector('.falcon-upload-to-cancel').addEventListener('click', () => show(false));
            form.addEventListener('submit', async e => {
                e.preventDefault();
                if (!name.value.trim()) { name.focus(); return; }
                const res = await fetch(@json(route('admin.media.folders.store')), {
                    method: 'POST', headers: Object.assign({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, json),
                    body: JSON.stringify({ name: name.value.trim(), parent_id: parent.value ? Number(parent.value) : null })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) { (window.showToast || alert)(data.message || 'The folder could not be made.', 'error'); return; }
                const list = await fetch(@json(route('admin.media.folders.index')), { headers: json }).then(r => r.json()).catch(() => null);
                show(false);
                fill(root, list ? list.folders : root._folders.concat([{ id: data.folder.id, name: data.folder.name, depth: 0 }]), String(data.folder.id));
                document.dispatchEvent(new CustomEvent('falcon-media-folders', { detail: { folders: root._folders, made: data.folder, from: root } }));
                window.showToast && window.showToast('Folder “' + data.folder.name + '” made — uploads go there');
            });
        }

        // A fresh list from elsewhere (another copy, or the media window): every copy follows it.
        document.addEventListener('falcon-media-folders', e => {
            document.querySelectorAll('.falcon-upload-to').forEach(root => { if (root !== e.detail.from) { setup(root); fill(root, e.detail.folders); } });
        });
        window.falconUploadToSetup = () => document.querySelectorAll('.falcon-upload-to').forEach(setup);
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', window.falconUploadToSetup) : window.falconUploadToSetup();
    })();
</script>
@endonce
