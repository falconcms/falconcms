<x-falcon-cms::layouts.admin>
    <x-slot name="title">Submissions {{ $form ? '— ' . $form->title : '(All Forms)' }}</x-slot>
    <x-falcon-cms::admin.delete-modal />

    {{-- Centered Detail Modal --}}
    <div id="sub-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 overflow-y-auto">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-bold text-gray-900">Submission Details</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                        <span class="material-symbols-outlined text-[22px]">close</span>
                    </button>
                </div>
                <div id="sub-modal-body" class="p-6"></div>
            </div>
        </div>
    </div>

    <div class="px-6 py-4">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">{{ $form ? $form->title : 'All Forms' }} — Submissions</h1>
                <p class="text-gray-400 text-sm mt-0.5">{{ $submissions->total() }} total {{ Str::plural('entry', $submissions->total()) }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if($form)
                    <a href="{{ route('admin.forms.builder', $form->id) }}"
                       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 border border-gray-200 px-3 py-1.5 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-[15px]">edit</span> Builder
                    </a>
                @endif
                <a href="{{ route('admin.forms.index') }}" class="text-sm text-gray-500 hover:text-gray-800 border border-gray-200 px-3 py-1.5 rounded-lg transition-colors">
                    ← All Forms
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if($submissions->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-16 text-center shadow-sm">
                <span class="material-symbols-outlined text-6xl text-gray-300">inbox</span>
                <h3 class="text-lg font-bold text-gray-600 mt-4">No submissions yet</h3>
                <p class="text-gray-400 text-sm mt-2">Submissions will appear here once users fill out the form.</p>
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-10">#</th>
                            @if(!$form)
                                <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Form</th>
                            @endif
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Preview</th>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-36">IP Address</th>
                            <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider w-36">Date</th>
                            <th class="px-5 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($submissions as $i => $sub)
                            @php
                                $preview = collect($sub->data)
                                    ->filter(fn($v) => !is_array($v) && !str_starts_with((string)$v, 'form-uploads/'))
                                    ->take(3)
                                    ->map(fn($v, $k) =>
                                        '<span class="text-gray-400 capitalize">' . e(str_replace('_', ' ', $k)) . ':</span> '
                                        . e(html_entity_decode(strip_tags((string)$v), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                                    )
                                    ->implode('<span class="mx-2 text-gray-200">·</span>');
                            @endphp
                            <tr class="hover:bg-blue-50/40 cursor-pointer transition-colors group"
                                onclick="openModal({{ $sub->id }})">
                                <td class="px-5 py-3.5 text-gray-400 text-xs font-mono">
                                    {{ $submissions->firstItem() + $i }}
                                </td>
                                @if(!$form)
                                    <td class="px-5 py-3.5 font-bold text-blue-600 text-xs">
                                        {{ $sub->form->title ?? 'Deleted Form' }}
                                    </td>
                                @endif
                                <td class="px-5 py-3.5 text-gray-700 max-w-0 w-full">
                                    <div class="truncate text-xs">{!! $preview !!}</div>
                                </td>
                                <td class="px-5 py-3.5 text-gray-500 text-xs font-mono whitespace-nowrap">
                                    {{ $sub->ip_address }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-400 text-xs whitespace-nowrap">
                                    {{ $sub->created_at->diffForHumans() }}
                                </td>
                                <td class="px-5 py-3.5 text-right" onclick="event.stopPropagation()">
                                    <form id="delete-sub-{{ $sub->id }}" action="{{ route('admin.forms.submissions.destroy', $sub->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                onclick="confirmDeleteSubmission({{ $sub->id }})"
                                                class="opacity-0 group-hover:opacity-100 p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition-all">
                                            <span class="material-symbols-outlined text-[17px]">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($submissions->hasPages())
                <div class="mt-4">{{ $submissions->links() }}</div>
            @endif
        @endif
    </div>

    {{-- Embed submission data for modal --}}
    <script>
    const subsData = {
        @foreach($submissions as $sub)
        @php
            $subFields = collect($sub->data)->mapWithKeys(function($v, $k) {
                $label  = ucwords(str_replace('_', ' ', $k));
                $isFile = is_string($v) && str_starts_with($v, 'form-uploads/');
                $clean  = is_array($v)
                    ? implode(', ', $v)
                    : html_entity_decode(strip_tags((string)$v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return [$label => ['value' => $clean, 'is_file' => $isFile, 'path' => $isFile ? $v : null]];
            })->all();
        @endphp
        {{ $sub->id }}: {
            date: @json(cms_date($sub->created_at, 'd M Y, H:i')),
            ip:   @json($sub->ip_address),
            ua:   @json($sub->user_agent),
            data: @json($subFields)
        },
        @endforeach
    };

    function openModal(id) {
        const s = subsData[id];
        if (!s) return;

        let rows = '';
        for (const [label, item] of Object.entries(s.data)) {
            let displayVal;
            const isFile = item.is_file;
            if (isFile) {
                displayVal = item.path
                    ? `<a href="/storage/${item.path}" target="_blank"
                          class="inline-flex items-center gap-1.5 text-blue-600 hover:underline text-sm font-medium">
                          <span class="material-symbols-outlined text-[15px]">download</span> Download File
                       </a>`
                    : '<span class="text-gray-400 italic text-xs">No file uploaded</span>';
            } else {
                displayVal = item.value
                    ? `<p class="text-sm text-gray-800 break-words leading-relaxed">${item.value}</p>`
                    : '<span class="text-gray-400 italic text-xs">—</span>';
            }
            rows += `<div class="bg-gray-50 rounded-xl px-4 py-3 ${isFile ? 'col-span-2' : ''}">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">${label}</p>
                ${displayVal}
            </div>`;
        }

        document.getElementById('sub-modal-body').innerHTML = `
            <div class="grid grid-cols-3 gap-3 bg-blue-50 border border-blue-100 rounded-xl px-5 py-4 mb-5">
                <div>
                    <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-1">Submitted</p>
                    <p class="text-sm font-semibold text-gray-800">${s.date}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-1">IP Address</p>
                    <p class="text-sm font-mono text-gray-700">${s.ip}</p>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest mb-1">Browser</p>
                    <p class="text-xs text-gray-500 truncate" title="${s.ua ?? ''}">${s.ua ?? '—'}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">${rows}</div>
        `;
        document.getElementById('sub-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('sub-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    window.confirmDeleteSubmission = async function(id) {
        const confirmed = await window.falconConfirm({
            title: 'Delete Submission',
            message: 'Are you sure you want to permanently delete this form submission? This action cannot be undone.',
            confirmText: 'Delete Permanently',
            isDanger: true
        });

        if (confirmed) {
            document.getElementById(`delete-sub-${id}`).submit();
        }
    };
    </script>
</x-falcon-cms::layouts.admin>
