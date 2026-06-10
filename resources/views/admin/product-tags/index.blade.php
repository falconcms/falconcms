<x-cms-dashboard::layouts.admin title="Product Tags">
    <x-cms-dashboard::admin.delete-modal />
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Product Tags</h1>
        <div class="flex space-x-1">
            <button class="bg-white border border-[#c3c4c7] px-2 py-1 text-[13px] text-[#2c3338] hover:bg-[#f6f7f7]">Screen Options <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
            <button class="bg-white border border-[#c3c4c7] px-2 py-1 text-[13px] text-[#2c3338] hover:bg-[#f6f7f7]">Help <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p>{{ session('success') }}</p>
        </div>
    @endif
    
    @if($errors->any())
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p class="font-bold mb-2">Error: Please check the following fields:</p>
            <ul class="list-disc list-inside text-[#d63638] space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
    </div>

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Add Tag Form -->
        <div class="w-full md:w-1/3">
            <h2 class="text-[14px] font-semibold text-[#1d2327] mb-3">Add New Tag</h2>
            <form action="{{ route('admin.product-tags.store') }}" method="POST">
                @csrf
                @php $activeLanguages = \Acme\CmsDashboard\Models\Language::where('status', true)->get(); @endphp
                @if($activeLanguages->count() > 1)
                    <div class="mb-4">
                        <label class="block text-[13px] text-[#1d2327] mb-1">Language</label>
                        <select name="lang_code" class="wp-input w-full h-8 py-0">
                            @foreach($activeLanguages as $lang)
                                <option value="{{ $lang->code }}" {{ $lang->code == app()->getLocale() ? 'selected' : '' }}>
                                    {{ $lang->flag }} {{ $lang->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="lang_code" value="{{ app()->getLocale() }}">
                @endif
                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Name</label>
                    <input type="text" name="name" class="wp-input w-full @error('name') border-[#d63638] @enderror" value="{{ old('name') }}" required>
                    @error('name')
                        <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-[12px] text-[#646970] mt-1">The name is how it appears on your site.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Slug</label>
                    <input type="text" name="slug" class="wp-input w-full">
                    <p class="text-[12px] text-[#646970] mt-1">The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Description</label>
                    <textarea name="description" rows="5" class="wp-input w-full"></textarea>
                    <p class="text-[12px] text-[#646970] mt-1">The description is not prominent by default; however, some themes may show it.</p>
                </div>

                <button type="submit" class="wp-btn-primary">Add New Tag</button>
            </form>
        </div>

        <!-- Tags Table -->
        <div class="w-full md:w-2/3">
            <div class="flex justify-end mb-2">
                <form action="{{ route('admin.product-tags.index') }}" method="GET" class="flex space-x-1">
                    <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px]" placeholder="">
                    <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search Tags</button>
                </form>
            </div>

            <form action="{{ route('admin.product-tags.bulk') }}" method="POST" id="tags-bulk-form">
                @csrf
            <div class="flex justify-between items-center mb-2">
                <div class="flex items-center space-x-2">
                    <select name="action" class="wp-input py-0">
                        <option value="-1">Bulk actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="button" onclick="handleBulkAction('tags-bulk-form')" class="wp-btn-secondary h-[30px] leading-[1]">Apply</button>
                </div>
                <x-cms-dashboard::admin.pagination :paginator="$tags" />
            </div>

            <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)]">
                <thead>
                    <tr>
                        <th class="wp-table-header w-8 text-center pb-0"><input type="checkbox" id="cb-select-all-1" class="rounded-sm border-[#8c8f94] text-[#2271b1]"></th>
                        <th class="wp-table-header text-left">Name</th>
                        <th class="wp-table-header text-left">Description</th>
                        <th class="wp-table-header text-left">Slug</th>
                        <th class="wp-table-header text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tags as $idx => $tag)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group">
                            <td class="wp-table-cell text-center"><input type="checkbox" name="ids[]" value="{{ $tag->id }}" class="cb-select-item rounded-sm border-[#8c8f94] text-[#2271b1]"></td>
                            <td class="wp-table-cell align-top">
                                <strong><a href="{{ route('admin.product-tags.edit', [$tag, 'type' => 'post']) }}" class="text-[#2271b1] hover:text-[#135e96]">{{ $tag->name }}</a></strong>
                                <div class="invisible group-hover:visible mt-1 text-[13px] space-x-1">
                                    <a href="{{ route('admin.product-tags.edit', [$tag, 'type' => 'post']) }}" class="text-[#2271b1]">Edit</a> <span class="text-[#c3c4c7]">|</span>
                                    <button type="button" onclick="confirmDeleteTag({{ $tag->id }})" class="text-[#b32d2e] hover:text-[#8a2424] cursor-pointer">Delete</button>
                                     <span class="text-[#c3c4c7]">|</span>
                                    <a href="{{ url('product-tag/' . $tag->slug) }}" target="_blank" class="text-[#2271b1]">View</a>
                                </div>
                            </td>
                            <td class="wp-table-cell text-[#646970]">{{ $tag->description ?: '—' }}</td>
                            <td class="wp-table-cell text-[#646970]">{{ $tag->slug }}</td>
                            <td class="wp-table-cell text-right"><a href="{{ route('admin.posts.index', ['type' => 'product', 'product_tag' => $tag->id]) }}" class="text-[#2271b1] font-semibold">{{ $tag->posts_count }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="wp-table-cell text-center py-4">No tags found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th class="wp-table-header w-8 text-center border-t pb-0"><input type="checkbox" id="cb-select-all-2" class="rounded-sm border-[#8c8f94] text-[#2271b1]"></th>
                        <th class="wp-table-header text-left border-t">Name</th>
                        <th class="wp-table-header text-left border-t">Description</th>
                        <th class="wp-table-header text-left border-t">Slug</th>
                        <th class="wp-table-header text-right border-t">Count</th>
                    </tr>
                </tfoot>
            </table>
            <div class="flex justify-between items-center mt-2 mb-6">
                <div class="flex items-center space-x-2">
                    <select name="action2" class="wp-input py-0">
                        <option value="-1">Bulk actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="button" onclick="handleBulkAction('tags-bulk-form', 'action2')" class="wp-btn-secondary h-[30px] leading-[1]">Apply</button>
                </div>
                <x-cms-dashboard::admin.pagination :paginator="$tags" />
            </div>
            </form>
            
            @foreach($tags as $tag)
                <form id="delete-form-{{ $tag->id }}" action="{{ route('admin.product-tags.destroy', $tag) }}" method="POST" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        </div>
    </div>

    <script>
        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function(master) {
            master.addEventListener('change', function() {
                let isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(function(item) {
                    item.checked = isChecked;
                });
                document.getElementById('cb-select-all-1').checked = isChecked;
                document.getElementById('cb-select-all-2').checked = isChecked;
            });
        });

        window.handleBulkAction = async function(formId, selectName = 'action') {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');

            if (action === '-1' || action === 'none') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one item.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.lazyConfirm({
                    title: 'Bulk Delete Tags',
                    message: `Are you sure you want to delete ${selected.length} selected tags? This action cannot be undone.`,
                    confirmText: 'Delete',
                    isDanger: true
                });

                if (confirmed) {
                    if (selectName === 'action2') {
                        form.querySelector('select[name="action"]').value = action;
                    }
                    form.submit();
                }
            } else {
                form.submit();
            }
        };

        window.confirmDeleteTag = async function(id) {
            const confirmed = await window.lazyConfirm({
                title: 'Delete Tag',
                message: 'Are you sure you want to delete this tag? This action cannot be undone.',
                confirmText: 'Delete',
                isDanger: true
            });
            if (confirmed) {
                document.getElementById(`delete-form-${id}`).submit();
            }
        };
    </script>
</x-cms-dashboard::layouts.admin>
