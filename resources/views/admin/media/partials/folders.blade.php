{{--
    Media library → the folder panel: All files, Uncategorized and the folder tree with file
    counts. Click a folder to see its files; with Media → Add New, add a folder (at the top or
    inside another), rename it, move it (drag it onto another folder or onto "top level", or use
    its Move button — the way on a phone), delete it, and drop files on a folder to move them
    there. Deleting a folder never deletes files: they go back to Uncategorized.
--}}
@php
    $folderCfg = [
        'folders' => $folders,
        'totals' => $folderTotals,
        'current' => (string) $folder,
        'canWrite' => $canWrite,
        'routes' => [
            'store' => route('admin.media.folders.store'),
            'base' => url('admin/media/folders'),
            'move' => route('admin.media.move'),
        ],
        'csrf' => csrf_token(),
    ];
@endphp
<aside class="w-full md:w-60 shrink-0" x-data="falconMediaFolders(@js($folderCfg))">
    {{-- On a phone the panel folds away behind the name of the folder that is open. --}}
    <button type="button" class="md:hidden w-full flex items-center justify-between bg-white border border-[#c3c4c7] px-3 h-9 text-[13px] mb-2" @click="open = !open">
        <span class="flex items-center gap-2 min-w-0"><span class="material-symbols-outlined text-[18px] text-[#646970]">folder_open</span><span class="truncate" x-text="currentName()"></span></span>
        <span class="material-symbols-outlined text-[18px] text-[#646970]" x-text="open ? 'expand_less' : 'expand_more'"></span>
    </button>

    <div class="bg-white border border-[#c3c4c7] md:block" :class="open ? 'block' : 'hidden'">
        <div class="flex items-center justify-between px-3 h-10 border-b border-[#f0f0f1]">
            <span class="text-[12px] font-bold uppercase tracking-wide text-[#646970]">Folders</span>
            <button type="button" x-show="canWrite" @click="startCreate()" class="flex items-center gap-1 text-[12px] text-[#2271b1] hover:text-[#135e96]" title="New folder">
                <span class="material-symbols-outlined text-[18px]">create_new_folder</span><span>New</span>
            </button>
        </div>

        {{-- New folder --}}
        <form x-show="creating" x-cloak @submit.prevent="create()" class="px-3 py-2 border-b border-[#f0f0f1] bg-[#f6f7f7]">
            <input type="text" x-ref="newName" x-model="newName" maxlength="100" placeholder="Folder name" class="wp-input w-full h-8 text-[13px]" @keydown.escape="creating = false">
            <label class="block text-[11px] text-[#646970] mt-2 mb-0.5">Where</label>
            <select x-model="newParent" class="wp-input w-full h-8 text-[12px] py-0">
                <option value="" :selected="newParent === ''">At the top level (a main folder)</option>
                <template x-for="o in folders" :key="o.id"><option :value="String(o.id)" :selected="newParent === String(o.id)" x-text="'— '.repeat(o.depth) + o.name"></option></template>
            </select>
            <div class="flex gap-2 mt-2">
                <button type="submit" class="wp-btn-primary h-7 px-3 text-[12px]" :disabled="busy">Create</button>
                <button type="button" class="wp-btn-secondary h-7 px-3 text-[12px]" @click="creating = false">Cancel</button>
            </div>
        </form>

        <ul class="py-1 text-[13px] max-h-[60vh] overflow-y-auto">
            <li>
                <a :href="href('')" class="flex items-center gap-2 px-3 h-8 hover:bg-[#f6f7f7]" :class="current === '' ? 'bg-[#f0f6fc] text-[#2271b1] font-semibold' : 'text-[#1d2327]'">
                    <span class="material-symbols-outlined text-[18px]">perm_media</span><span class="grow">All files</span><span class="text-[11px] text-[#646970]" x-text="totals.all"></span>
                </a>
            </li>
            <li @dragover.prevent="over = 'none'" @dragleave="over = null" @drop.prevent="drop($event, null)">
                <a :href="href('none')" class="flex items-center gap-2 px-3 h-8 hover:bg-[#f6f7f7]"
                   :class="[current === 'none' ? 'bg-[#f0f6fc] text-[#2271b1] font-semibold' : 'text-[#1d2327]', over === 'none' ? 'ring-2 ring-inset ring-[#2271b1] bg-[#f0f6fc]' : '']">
                    <span class="material-symbols-outlined text-[18px]">folder_off</span><span class="grow">Uncategorized</span><span class="text-[11px] text-[#646970]" x-text="totals.none"></span>
                </a>
            </li>
            <template x-for="f in folders" :key="f.id">
                <li class="group relative" :draggable="canWrite && renaming !== f.id && moving !== f.id" @dragstart="dragFolder($event, f)" @dragend="draggingFolder = null; over = null"
                    @dragover.prevent="over = f.id" @dragleave="over = null" @drop.prevent="drop($event, f.id)">
                    <template x-if="renaming !== f.id">
                        <a :href="href(String(f.id))" class="flex items-center gap-2 pr-3 h-8 hover:bg-[#f6f7f7]" :style="'padding-left:' + (12 + f.depth * 14) + 'px'"
                           :class="[current === String(f.id) ? 'bg-[#f0f6fc] text-[#2271b1] font-semibold' : 'text-[#1d2327]', over === f.id ? 'ring-2 ring-inset ring-[#2271b1] bg-[#f0f6fc]' : '']">
                            <span class="material-symbols-outlined text-[18px] text-[#dba617]" x-text="current === String(f.id) ? 'folder_open' : 'folder'"></span>
                            <span class="grow truncate" x-text="f.name" :title="f.name"></span>
                            <span class="text-[11px] text-[#646970]" :class="canWrite ? 'md:group-hover:hidden' : ''" x-text="f.count"></span>
                            {{-- Shown on hover with a mouse; always on a phone, which has no hover. --}}
                            <span x-show="canWrite" class="flex md:hidden md:group-hover:flex items-center gap-0.5">
                                <button type="button" class="p-0.5 text-[#646970] hover:text-[#2271b1]" title="Rename" @click.prevent.stop="startRename(f)"><span class="material-symbols-outlined text-[16px]">edit</span></button>
                                <button type="button" class="p-0.5 text-[#646970] hover:text-[#2271b1]" title="Move folder" @click.prevent.stop="startMove(f)"><span class="material-symbols-outlined text-[16px]">drive_file_move</span></button>
                                <button type="button" class="p-0.5 text-[#646970] hover:text-[#b32d2e]" title="Delete folder" @click.prevent.stop="remove(f)"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                            </span>
                        </a>
                    </template>
                    <template x-if="renaming === f.id">
                        <form class="flex items-center gap-1 pr-2 h-9" :style="'padding-left:' + (12 + f.depth * 14) + 'px'" @submit.prevent="rename(f)">
                            <input type="text" x-model="renameValue" maxlength="100" class="wp-input grow h-7 text-[13px]" x-init="$nextTick(() => { $el.focus(); $el.select(); })" @keydown.escape="renaming = null">
                            <button type="submit" class="p-1 text-[#2271b1]" title="Save"><span class="material-symbols-outlined text-[18px]">check</span></button>
                        </form>
                    </template>
                    <template x-if="moving === f.id">
                        <form class="pr-2 pb-2 pt-1 bg-[#f6f7f7]" :style="'padding-left:' + (12 + f.depth * 14) + 'px'" @submit.prevent="moveFolder(f.id, moveTarget === '' ? null : Number(moveTarget))">
                            <label class="block text-[11px] text-[#646970] mb-0.5">Move “<span x-text="f.name"></span>” to</label>
                            <select x-model="moveTarget" class="wp-input w-full h-8 text-[12px] py-0">
                                <option value="" :selected="moveTarget === ''">The top level (a main folder)</option>
                                <template x-for="o in targetsFor(f)" :key="o.id"><option :value="String(o.id)" :selected="moveTarget === String(o.id)" x-text="'— '.repeat(o.depth) + o.name"></option></template>
                            </select>
                            <div class="flex gap-2 mt-1.5">
                                <button type="submit" class="wp-btn-primary h-7 px-3 text-[12px]" :disabled="busy">Move</button>
                                <button type="button" class="wp-btn-secondary h-7 px-3 text-[12px]" @click="moving = null">Cancel</button>
                            </div>
                        </form>
                    </template>
                </li>
            </template>
            {{-- Below the list, so the rows above do not shift when a folder starts to move. --}}
            <li x-show="draggingFolder" x-cloak @dragover.prevent="over = 'top'" @dragleave="over = null" @drop.prevent="dropTop($event)">
                <div class="mx-2 my-1 flex items-center gap-2 px-2 h-8 border-2 border-dashed rounded text-[12px]" :class="over === 'top' ? 'border-[#2271b1] bg-[#f0f6fc] text-[#2271b1]' : 'border-[#c3c4c7] text-[#646970]'">
                    <span class="material-symbols-outlined text-[18px]">vertical_align_top</span>Drop here to make it a main folder
                </div>
            </li>
        </ul>
        <p x-show="canWrite && folders.length" class="px-3 pb-3 pt-1 text-[11px] text-[#646970] hidden md:block">Drag files onto a folder to move them. Drag a folder onto another to put it inside, or use its <span class="material-symbols-outlined text-[13px] align-middle">drive_file_move</span> button.</p>
        <p x-show="canWrite && !folders.length" class="px-3 pb-3 pt-1 text-[11px] text-[#646970]">No folders yet. Use <strong>New</strong> to make one.</p>
    </div>
</aside>

<script>
    window.falconMediaFolders = function (cfg) {
        return {
            folders: cfg.folders, totals: cfg.totals, current: cfg.current, canWrite: cfg.canWrite,
            open: false, creating: false, newName: '', newParent: '', renaming: null, renameValue: '', moving: null, moveTarget: '', draggingFolder: null, over: null, busy: false,

            href(folder) {
                const url = new URL(window.location.href);
                url.searchParams.delete('page');
                folder === '' ? url.searchParams.delete('folder') : url.searchParams.set('folder', folder);
                return url.pathname + url.search;
            },
            folderName(id) { const f = this.folders.find(f => String(f.id) === String(id)); return f ? f.name : ''; },
            currentName() { return this.current === '' ? 'All files' : (this.current === 'none' ? 'Uncategorized' : this.folderName(this.current) || 'All files'); },
            // A folder can go anywhere but inside itself or one of its own subfolders.
            descendants(id) {
                const out = new Set(); let level = [id];
                while (level.length) { level = this.folders.filter(f => level.includes(f.parent_id)).map(f => f.id); level.forEach(i => out.add(i)); }
                return out;
            },
            targetsFor(f) { const no = this.descendants(f.id); return this.folders.filter(o => o.id !== f.id && !no.has(o.id)); },

            async send(url, method, body) {
                this.busy = true;
                try {
                    const res = await fetch(url, { method: method === 'GET' ? 'GET' : 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.csrf },
                        body: method === 'GET' ? undefined : JSON.stringify(Object.assign({ _method: method }, body || {})) });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        window.showToast ? window.showToast(data.message || 'That did not work. Please try again.', 'error') : alert(data.message || 'Error');
                        return null;
                    }
                    return data;
                } finally { this.busy = false; }
            },

            startCreate() { this.open = true; this.creating = true; this.newName = ''; this.newParent = /^\d+$/.test(this.current) ? this.current : ''; this.$nextTick(() => this.$refs.newName.focus()); },
            async create() {
                if (!this.newName.trim()) return;
                const parent = this.newParent ? Number(this.newParent) : null;
                if (await this.send(cfg.routes.store, 'POST', { name: this.newName, parent_id: parent })) location.reload();
            },
            startRename(f) { this.moving = null; this.renaming = f.id; this.renameValue = f.name; },
            startMove(f) { this.renaming = null; this.moving = f.id; this.moveTarget = f.parent_id ? String(f.parent_id) : ''; },
            async moveFolder(id, parent) {
                const f = this.folders.find(o => o.id === id);
                if (!f || (f.parent_id || null) === parent) { this.moving = null; return; }
                if (await this.send(cfg.routes.base + '/' + id, 'PUT', { parent_id: parent })) location.reload();
            },
            dragFolder(e, f) {
                if (!this.canWrite) return;
                this.draggingFolder = f.id;
                e.dataTransfer.setData('application/x-falcon-folder', String(f.id));
                e.dataTransfer.effectAllowed = 'move';
            },
            dropTop(e) { this.over = null; const id = Number(e.dataTransfer.getData('application/x-falcon-folder')); this.draggingFolder = null; if (id) this.moveFolder(id, null); },
            async rename(f) {
                const name = this.renameValue.trim();
                if (!name || name === f.name) { this.renaming = null; return; }
                const data = await this.send(cfg.routes.base + '/' + f.id, 'PUT', { name });
                if (data) { f.name = data.folder.name; this.renaming = null; window.showToast && window.showToast('Folder renamed'); }
            },
            async remove(f) {
                const ok = window.falconConfirm
                    ? await window.falconConfirm({ title: 'Delete folder', message: 'Delete the folder “' + f.name + '”? Its files are not deleted — they move to Uncategorized, and its folders move up one level.', confirmText: 'Delete folder', isDanger: true })
                    : confirm('Delete the folder "' + f.name + '"? Its files move to Uncategorized.');
                if (!ok) return;
                if (await this.send(cfg.routes.base + '/' + f.id, 'DELETE')) {
                    if (this.current === String(f.id)) { window.location.href = this.href(''); return; }
                    location.reload();
                }
            },
            async drop(e, folderId) {
                this.over = null;
                if (!this.canWrite) return;
                // A folder dropped on a folder goes inside it (never into itself or below itself).
                const dragged = Number(e.dataTransfer.getData('application/x-falcon-folder'));
                this.draggingFolder = null;
                if (dragged) {
                    if (folderId === null) return;
                    if (folderId === dragged || this.descendants(dragged).has(folderId)) { window.showToast && window.showToast('A folder cannot go inside itself.', 'error'); return; }
                    return this.moveFolder(dragged, folderId);
                }
                let ids = [];
                try { ids = JSON.parse(e.dataTransfer.getData('application/x-falcon-media') || '[]'); } catch (err) {}
                if (!ids.length) return;
                if (await this.send(cfg.routes.move, 'POST', { ids, folder_id: folderId })) location.reload();
            },
        };
    };
</script>
