    <!-- Left Section -->
    <div class="flex items-center gap-1 h-full">
        <div class="topbar-icon">
            <svg class="w-6 h-6 text-[#0091ea]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div class="h-6 w-px bg-white/10 mx-2"></div>
        <div class="topbar-icon" title="Layout" @click="activeTab='navigator'">
            <i class="fa fa-th-large text-sm"></i>
        </div>
        <div @click="undo()" :title="canUndo ? 'Undo (Ctrl+Z)' : 'Nothing to undo'"
             :class="canUndo ? 'cursor-pointer' : 'opacity-30 cursor-not-allowed pointer-events-none'"
             class="topbar-icon">
            <i class="fa fa-undo text-sm"></i>
        </div>
        <div @click="redo()" :title="canRedo ? 'Redo (Ctrl+Y)' : 'Nothing to redo'"
             :class="canRedo ? 'cursor-pointer' : 'opacity-30 cursor-not-allowed pointer-events-none'"
             class="topbar-icon">
            <i class="fa fa-redo text-sm"></i>
        </div>
        <div class="topbar-icon" title="Responsive" @click="device = device === 'desktop' ? 'tablet' : (device === 'tablet' ? 'mobile' : 'desktop')">
            <i class="fa" :class="device==='desktop' ? 'fa-desktop' : (device==='tablet' ? 'fa-tablet-alt' : 'fa-mobile-alt')"></i>
        </div>
        <div class="topbar-icon" title="Clear All" @click="layout = []">
            <i class="fa fa-trash text-sm text-red-400"></i>
        </div>
    </div>

    <!-- Center Section (Page Title) -->
    <div class="hidden md:block">
        <span class="text-[11px] font-bold text-white/40 uppercase tracking-[0.3em]">Editing: {{ $builderTitle ?? $post->title }}</span>
    </div>

    <!-- Right Section -->
    <div class="flex items-center gap-4 h-full">
        <span v-if="autosaveStatus" class="hidden lg:inline text-[10px] text-white/40 whitespace-nowrap">@{{ autosaveStatus }}</span>
        <div class="topbar-icon" v-if="!postCardMode" title="Revision History" @click="openRevisions()">
            <i class="fa fa-history text-sm"></i>
        </div>
        <div class="topbar-icon" @click="isPreview = !isPreview" title="Preview">
            <i class="fa" :class="isPreview ? 'fa-eye-slash' : 'fa-eye'"></i>
        </div>
        
        <div class="h-6 w-px bg-white/10 mx-1"></div>
        
        <button @click="isDirty && saveLayout()" :disabled="isSaving || !isDirty" class="btn-save"
                :style="{ backgroundColor: isDirty ? '#4CAF50' : '#4A5259' }"
                :class="!isDirty ? 'opacity-50 cursor-not-allowed' : ''">
            <span v-if="isSaving"><i class="fa fa-spinner fa-spin mr-2"></i> Saving</span>
            <span v-else>Save</span>
        </button>
        
        <a href="{{ $builderBackUrl ?? route('admin.posts.index') }}" class="topbar-icon hover:bg-red-500/20 text-white/60">
            <i class="fa fa-times"></i>
        </a>
    </div>

    {{-- ── Revision History panel (slide-over) ── --}}
    <div v-if="showRevisions" class="fixed inset-0 z-[3000]" @click.self="showRevisions=false" style="background:rgba(0,0,0,.4)">
        <div class="absolute right-0 top-0 h-full w-[340px] bg-white shadow-2xl flex flex-col">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
                <h3 class="text-[13px] font-bold text-slate-700"><i class="fa fa-history mr-2 text-[#0091ea]"></i>Revision History</h3>
                <button @click="showRevisions=false" class="text-slate-400 hover:text-slate-700"><i class="fa fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto">
                <div v-if="!revisionList.length" class="p-8 text-center text-[12px] text-slate-400">No revisions yet. Save the page to create one.</div>
                <div v-for="rev in revisionList" :key="rev.id" class="px-4 py-3 border-b border-slate-100 flex items-center justify-between hover:bg-slate-50 group">
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold text-slate-700 flex items-center gap-1.5">
                            <span v-if="rev.is_autosave" class="text-[8px] font-bold uppercase bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">Autosave</span>
                            <span>@{{ rev.ago }}</span>
                        </div>
                        <div class="text-[10px] text-slate-400 truncate">@{{ rev.time }} · @{{ rev.user }}</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-2">
                        <button @click="restoreRevision(rev.id)" :disabled="isRestoring"
                                class="text-[11px] font-bold text-[#0091ea] hover:underline disabled:opacity-40">Restore</button>
                        <button @click="deleteRevisionItem(rev.id)" title="Delete revision"
                                class="text-[11px] text-slate-400 hover:text-red-500"><i class="fa fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Autosave recovery banner ── --}}
    <div v-if="autosaveBanner" class="fixed top-16 left-1/2 -translate-x-1/2 z-[3000] bg-amber-50 border border-amber-300 rounded-lg shadow-lg px-4 py-2.5 flex items-center gap-3">
        <i class="fa fa-history text-amber-500"></i>
        <span class="text-[12px] text-amber-800">An autosaved draft from <b>@{{ autosaveBanner.time }}</b> is available.</span>
        <button @click="restoreRevision(autosaveBanner.id)" class="text-[11px] font-bold bg-amber-500 text-white px-3 py-1 rounded hover:bg-amber-600">Restore</button>
        <button @click="dismissAutosaveBanner()" class="text-amber-500 hover:text-amber-700"><i class="fa fa-times text-[11px]"></i></button>
    </div>
