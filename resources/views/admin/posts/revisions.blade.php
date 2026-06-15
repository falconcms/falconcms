<x-falcon-cms::layouts.admin>
    <x-slot name="title">Revisions — {{ $post->title }}</x-slot>

    <style>
        .diff-line { font-family: ui-monospace, 'Cascadia Code', 'Fira Code', monospace; font-size: 12px; line-height: 1.6; padding: 1px 10px 1px 6px; white-space: pre-wrap; word-break: break-word; border-left: 3px solid transparent; }
        .diff-sign { display: inline-block; width: 12px; color: #94a3b8; user-select: none; }
        .diff-add { background: #e6ffed; border-left-color: #2da44e; color: #11622b; }
        .diff-del { background: #ffebe9; border-left-color: #cf222e; color: #82071e; }
        .diff-eq  { color: #57606a; }
        .diff-note { padding: 24px; text-align: center; color: #646970; font-size: 13px; }
    </style>

    <div class="px-2">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-[23px] font-normal text-[#1d2327]">Revisions</h1>
                <p class="text-[13px] text-[#646970] mt-0.5">Compare versions of <strong>{{ $post->title }}</strong> and restore any of them.</p>
            </div>
            <div class="flex items-center gap-2">
                @if(count($entries) > 1)
                <form action="{{ route('admin.posts.revisions.clear', $post->id) }}" method="POST"
                      onsubmit="return confirm('Delete ALL revisions for this {{ $post->type }}? This cannot be undone (live content stays intact).')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[#b32d2e] hover:text-[#8a2424] text-[13px] underline bg-transparent border-0 cursor-pointer">Delete all revisions</button>
                </form>
                @endif
                <a href="{{ route('admin.posts.edit', $post) }}" class="wp-btn-secondary">&larr; Back to editor</a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-5 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif

        @if(count($entries) <= 1)
            <div class="bg-white border border-[#dfdfdf] rounded p-10 text-center text-[#646970] text-[14px]">
                No revisions yet. Edit and update this {{ $post->type }} to start building its revision history.
            </div>
        @else
        {{-- Version pickers --}}
        <form method="GET" action="{{ route('admin.posts.revisions', $post->id) }}" id="compare-form"
              class="bg-white border border-[#dfdfdf] rounded p-4 mb-5 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[11px] font-bold text-[#646970] uppercase mb-1">Compare from (older)</label>
                <select name="from" onchange="document.getElementById('compare-form').submit()"
                        class="wp-input text-[13px] min-w-[260px]">
                    @foreach($entries as $key => $e)
                        <option value="{{ $key }}" {{ (string)$from === (string)$key ? 'selected' : '' }}>{{ $e['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="text-[#646970] pb-2">&rarr;</div>
            <div>
                <label class="block text-[11px] font-bold text-[#646970] uppercase mb-1">To (newer)</label>
                <select name="to" onchange="document.getElementById('compare-form').submit()"
                        class="wp-input text-[13px] min-w-[260px]">
                    @foreach($entries as $key => $e)
                        <option value="{{ $key }}" {{ (string)$to === (string)$key ? 'selected' : '' }}>{{ $e['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="flex flex-col lg:flex-row gap-5">
            {{-- Diff viewer --}}
            <div class="flex-grow min-w-0">
                {{-- Title diff (if changed) --}}
                @php $fromTitle = $entries[$from]['title'] ?? ''; $toTitle = $entries[$to]['title'] ?? ''; @endphp
                @if($fromTitle !== $toTitle)
                <div class="bg-white border border-[#dfdfdf] rounded mb-4 overflow-hidden">
                    <div class="px-4 py-2 bg-[#f6f7f7] border-b border-[#dfdfdf] text-[12px] font-bold text-[#1d2327] uppercase">Title</div>
                    <div class="p-3">
                        <div class="diff-line diff-del"><span class="diff-sign">-</span>{{ $fromTitle }}</div>
                        <div class="diff-line diff-add"><span class="diff-sign">+</span>{{ $toTitle }}</div>
                    </div>
                </div>
                @endif

                <div class="bg-white border border-[#dfdfdf] rounded overflow-hidden">
                    <div class="px-4 py-2 bg-[#f6f7f7] border-b border-[#dfdfdf] flex items-center justify-between">
                        <span class="text-[12px] font-bold text-[#1d2327] uppercase">Content differences</span>
                        <span class="text-[11px] text-[#646970]">
                            <span class="inline-block px-2 py-0.5 rounded bg-[#ffebe9] text-[#82071e] mr-1">removed</span>
                            <span class="inline-block px-2 py-0.5 rounded bg-[#e6ffed] text-[#11622b]">added</span>
                        </span>
                    </div>
                    <div class="max-h-[60vh] overflow-y-auto py-2">
                        {!! $diff !!}
                    </div>
                </div>
            </div>

            {{-- Revision timeline --}}
            <div class="w-full lg:w-[320px] shrink-0">
                <div class="bg-white border border-[#dfdfdf] rounded overflow-hidden">
                    <div class="px-4 py-2 bg-[#f6f7f7] border-b border-[#dfdfdf] text-[12px] font-bold text-[#1d2327] uppercase">All Versions</div>
                    <div class="max-h-[70vh] overflow-y-auto">
                        @foreach($entries as $key => $e)
                        <div class="px-4 py-3 border-b border-[#f0f0f1] {{ (string)$to === (string)$key ? 'bg-[#f0f6fc]' : '' }}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-[12px] font-semibold text-[#1d2327] flex items-center gap-1.5">
                                        @if(($e['type'] ?? '') === 'autosave')
                                            <span class="text-[8px] font-bold uppercase bg-[#fcf3d6] text-[#8a6d3b] px-1 py-0.5 rounded">Autosave</span>
                                        @elseif(($e['type'] ?? '') === 'current')
                                            <span class="text-[8px] font-bold uppercase bg-[#e6ffed] text-[#11622b] px-1 py-0.5 rounded">Live</span>
                                        @endif
                                        <span class="truncate">{{ $e['label'] }}</span>
                                    </div>
                                    <div class="text-[10px] text-[#646970] truncate">{{ $e['meta'] }}</div>
                                </div>
                                @if($key !== 'current')
                                <div class="flex items-center gap-2 shrink-0">
                                    <form action="{{ route('admin.posts.revisions.restore', ['id' => $post->id, 'revision' => $key]) }}" method="POST"
                                          onsubmit="return confirm('Restore this version? The current content will be saved as a revision first.')">
                                        @csrf
                                        <button type="submit" class="text-[11px] font-bold text-[#2271b1] hover:underline">Restore</button>
                                    </form>
                                    <form action="{{ route('admin.posts.revisions.delete', ['id' => $post->id, 'revision' => $key]) }}" method="POST"
                                          onsubmit="return confirm('Delete this revision permanently?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-[11px] font-bold text-[#b32d2e] hover:underline">Delete</button>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-falcon-cms::layouts.admin>
