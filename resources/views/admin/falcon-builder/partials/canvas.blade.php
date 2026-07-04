<main class="builder-canvas-area flex flex-col bg-white">
    <div class="canvas-container"
         @click="clearEditingContext"
         :class="[isPreview ? 'preview-mode' : '', device]"
         :style="canvasStyle">

        {{-- Read-only HEADER preview (real theme header for this page/post/CPT/product).
             Edited only from the Layout Builder — non-interactive here.
             NOTE: the frame CSS + resize JS live in index.blade.php OUTSIDE the Vue mount,
             because Vue strips <style>/<script> from its template. --}}
        @if(!empty($frameHeaderUrl ?? null))
        <div v-if="!isPreview" class="builder-frame builder-frame-header">
            <iframe src="{{ $frameHeaderUrl }}" data-falcon-frame="header" scrolling="no" loading="eager"
                    style="width:100%;border:0;display:block;height:90px;pointer-events:none;"></iframe>
            <a href="{{ $frameHeaderEditUrl ?? '#' }}" target="_blank" rel="noopener" class="builder-frame-edit" aria-label="Edit Header Layout Section">
                <i class="fa fa-window-maximize"></i>
                <span class="builder-frame-tip">Edit Header Layout Section</span>
            </a>
        </div>
        @endif

        {{-- Read-only PAGE TITLE BAR preview (only when a PTB Layout section is enabled). --}}
        @if(!empty($frameTitleBarUrl ?? null))
        <div v-if="!isPreview" class="builder-frame builder-frame-titlebar">
            <iframe src="{{ $frameTitleBarUrl }}" data-falcon-frame="titlebar" scrolling="no" loading="eager"
                    style="width:100%;border:0;display:block;height:120px;pointer-events:none;"></iframe>
            <a href="{{ $frameTitleBarEditUrl ?? '#' }}" target="_blank" rel="noopener" class="builder-frame-edit" aria-label="Edit Page Title Bar Layout Section">
                <i class="fa fa-window-maximize"></i>
                <span class="builder-frame-tip">Edit Page Title Bar Section</span>
            </a>
        </div>
        @endif

        <!-- Empty State -->
        <div v-if="layout.length === 0" class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)] bg-white">
            <div class="w-full mx-auto border-2 border-dashed border-slate-200 rounded-lg p-20 flex flex-col items-center text-center">
                @if(($postCardMode ?? false))
                <h2 class="text-[32px] font-medium text-[#444] mb-4">Design your post card layout</h2>
                <p class="text-[15px] text-slate-500 mb-10">Start by adding a column layout, then place elements inside the columns.</p>
                <div class="flex items-center gap-4">
                    <button @click="openColumnModal(null)" class="flex items-center gap-3 bg-[#2271b1] hover:bg-[#1a5a96] text-white px-8 py-3.5 rounded font-bold text-sm uppercase tracking-wide transition-all shadow-lg shadow-blue-500/20">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                        Add Column Layout
                    </button>
                </div>
                @else
                <h2 class="text-[32px] font-medium text-[#444] mb-4">To get started, add a Container, or add a prebuilt page.</h2>
                <p class="text-[15px] text-slate-500 mb-10">The building process always starts with a container, then columns, then elements.</p>
                <div class="flex items-center gap-4">
                    <button @click="addContainer" class="flex items-center gap-3 bg-[#2271b1] hover:bg-[#1a5a96] text-white px-8 py-3.5 rounded font-bold text-sm uppercase tracking-wide transition-all shadow-lg shadow-blue-500/20">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                        Add Container
                    </button>
                </div>
                @endif
            </div>
        </div>

        <!-- Actual Layout -->
        <div v-else class="w-full bg-white min-h-full flex flex-col {{ ($postCardMode ?? false) ? 'justify-center' : '' }}">
            <template v-for="(container, ci) in layout" :key="container.id">
                @include('falcon-cms::admin.falcon-builder.partials.components.container.row')
            </template>
        </div>

        {{-- Read-only FOOTER preview --}}
        @if(!empty($frameFooterUrl ?? null))
        <div v-if="!isPreview" class="builder-frame builder-frame-footer">
            <iframe src="{{ $frameFooterUrl }}" data-falcon-frame="footer" scrolling="no" loading="eager"
                    style="width:100%;border:0;display:block;height:300px;pointer-events:none;"></iframe>
            <a href="{{ $frameFooterEditUrl ?? '#' }}" target="_blank" rel="noopener" class="builder-frame-edit" aria-label="Edit Footer Layout Section">
                <i class="fa fa-window-maximize"></i>
                <span class="builder-frame-tip">Edit Footer Layout Section</span>
            </a>
        </div>
        @endif
    </div>
</main>
