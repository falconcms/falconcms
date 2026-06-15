<template v-if="ctxMenu.show">
    <!-- Backdrop to close on outside click -->
    <div class="fixed inset-0 z-[99998]" @click="closeCtxMenu()" @contextmenu.prevent="closeCtxMenu()"></div>

    <!-- Menu -->
    <div class="fixed z-[99999] bg-[#1e1e1e] text-white shadow-[0_8px_32px_rgba(0,0,0,0.6)] rounded-lg overflow-hidden min-w-[200px] select-none border border-white/10"
         :style="{ top: ctxMenu.y + 'px', left: ctxMenu.x + 'px' }"
         @click.stop>

        <!-- Title -->
        <div class="px-3 py-2 bg-[#111111] text-[9px] font-black uppercase tracking-[0.2em] text-white/40 border-b border-white/10 flex items-center gap-1.5">
            <i class="fa fa-layer-group text-[9px]"></i>
            @{{ ctxMenuTitle }}
            <span v-if="ctxClipboard" class="ml-auto text-[8px] text-green-400/70 normal-case font-normal">📋 @{{ ctxClipboard.type }}</span>
        </div>

        <!-- Actions -->
        <div class="py-1">
            <button @click="ctxEdit()"
                    class="w-full px-3 py-2 text-left text-[12.5px] text-white/90 hover:bg-white/10 hover:text-white transition-colors flex items-center gap-2.5">
                <i class="fa fa-pen w-3.5 text-center text-white/40 text-[11px]"></i> Edit
            </button>
            <button v-if="ctxMenu.type !== 'nested-row'"
                    @click="ctxSave()"
                    class="w-full px-3 py-2 text-left text-[12.5px] text-white/90 hover:bg-white/10 hover:text-white transition-colors flex items-center gap-2.5">
                <i class="fa fa-hdd w-3.5 text-center text-white/40 text-[11px]"></i> Save to Library
            </button>
            <button @click="ctxClone()"
                    class="w-full px-3 py-2 text-left text-[12.5px] text-white/90 hover:bg-white/10 hover:text-white transition-colors flex items-center gap-2.5">
                <i class="fa fa-copy w-3.5 text-center text-white/40 text-[11px]"></i> Clone
            </button>

            <template v-if="ctxMenu.type === 'container'">
                <div class="border-t border-white/10 my-1 mx-2"></div>
                <button v-if="!layout[ctxMenu.ci]?.settings?.global_id"
                        @click="ctxSaveAsGlobal()"
                        class="w-full px-3 py-2 text-left text-[12.5px] text-purple-300 hover:bg-purple-500/20 hover:text-purple-200 transition-colors flex items-center gap-2.5">
                    <i class="fa fa-globe w-3.5 text-center text-[11px]"></i> Save as Global
                </button>
                <button v-if="layout[ctxMenu.ci]?.settings?.global_id"
                        @click="unlinkGlobal(ctxMenu.ci); closeCtxMenu()"
                        class="w-full px-3 py-2 text-left text-[12.5px] text-amber-300 hover:bg-amber-500/20 hover:text-amber-200 transition-colors flex items-center gap-2.5">
                    <i class="fa fa-unlink w-3.5 text-center text-[11px]"></i> Unlink Global
                </button>
            </template>

            <div class="border-t border-white/10 my-1 mx-2"></div>

            <button @click="ctxRemove()"
                    class="w-full px-3 py-2 text-left text-[12.5px] text-red-400 hover:bg-red-500/15 hover:text-red-300 transition-colors flex items-center gap-2.5">
                <i class="fa fa-trash-alt w-3.5 text-center text-[11px]"></i> Remove
            </button>

            <div class="border-t border-white/10 my-1 mx-2"></div>

            <button @click="ctxCopy()"
                    class="w-full px-3 py-2 text-left text-[12.5px] text-white/90 hover:bg-white/10 hover:text-white transition-colors flex items-center gap-2.5">
                <i class="fa fa-clipboard w-3.5 text-center text-white/40 text-[11px]"></i> Copy
            </button>
            <button @click="ctxClipboard && ctxClipboard.type === ctxMenu.type && ctxPaste('start')"
                    :class="ctxClipboard && ctxClipboard.type === ctxMenu.type ? 'text-white/90 hover:bg-white/10 hover:text-white cursor-pointer' : 'text-white/25 cursor-not-allowed'"
                    class="w-full px-3 py-2 text-left text-[12.5px] transition-colors flex items-center gap-2.5">
                <i class="fa fa-arrow-up w-3.5 text-center text-[11px]" :class="ctxClipboard && ctxClipboard.type === ctxMenu.type ? 'text-white/40' : 'text-white/20'"></i> Paste at Start
            </button>
            <button @click="ctxClipboard && ctxClipboard.type === ctxMenu.type && ctxPaste('end')"
                    :class="ctxClipboard && ctxClipboard.type === ctxMenu.type ? 'text-white/90 hover:bg-white/10 hover:text-white cursor-pointer' : 'text-white/25 cursor-not-allowed'"
                    class="w-full px-3 py-2 text-left text-[12.5px] transition-colors flex items-center gap-2.5">
                <i class="fa fa-arrow-down w-3.5 text-center text-[11px]" :class="ctxClipboard && ctxClipboard.type === ctxMenu.type ? 'text-white/40' : 'text-white/20'"></i> Paste at End
            </button>
        </div>
    </div>
</template>
