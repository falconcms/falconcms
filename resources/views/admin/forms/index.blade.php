<x-falcon-cms::layouts.admin>
    <x-slot name="title">Forms - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-6 py-4">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Forms</h1>
                <p class="text-gray-500 text-sm mt-1">Create and manage your contact forms.</p>
            </div>
            <a href="{{ route('admin.forms.create') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                <span class="material-symbols-outlined text-[18px]">add</span>
                New Form
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 text-sm">{{ session('success') }}</div>
        @endif

        @if($forms->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-16 text-center shadow-sm">
                <span class="material-symbols-outlined text-6xl text-gray-300">wysiwyg</span>
                <h3 class="text-lg font-bold text-gray-600 mt-4">No forms yet</h3>
                <p class="text-gray-400 text-sm mt-2">Create your first form to get started.</p>
                <a href="{{ route('admin.forms.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold mt-6 hover:bg-blue-700 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span> Create Form
                </a>
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Title</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Shortcode</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Submissions</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($forms as $form)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ $form->title }}</td>
                                <td class="px-4 py-3">
                                    <code class="bg-gray-100 text-blue-700 px-2 py-1 rounded text-xs">[falcon_form slug="{{ $form->slug }}"]</code>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $form->submissions_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $form->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $form->status ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.forms.builder', $form->id) }}" class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                            <span class="material-symbols-outlined text-[14px]">build</span> Builder
                                        </a>
                                        <a href="{{ route('admin.forms.submissions', $form->id) }}" class="inline-flex items-center gap-1 bg-purple-50 text-purple-700 hover:bg-purple-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                            <span class="material-symbols-outlined text-[14px]">inbox</span> Entries
                                        </a>
                                        <form id="delete-form-{{ $form->id }}" method="POST" action="{{ route('admin.forms.destroy', $form) }}">
                                            @csrf @method('DELETE')
                                            <button type="button" onclick="confirmFormDelete({{ $form->id }})" class="inline-flex items-center gap-1 bg-red-50 text-red-700 hover:bg-red-100 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                                <span class="material-symbols-outlined text-[14px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t border-gray-100">{{ $forms->links() }}</div>
            </div>
        @endif
    </div>
    <script>
        window.confirmFormDelete = async function(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Form',
                message: 'Are you sure you want to delete this form? This action cannot be undone and will delete all associated submissions.',
                confirmText: 'Delete Form',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-form-${id}`).submit();
            }
        };
    </script>
</x-falcon-cms::layouts.admin>
