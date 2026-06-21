<x-falcon-cms::layouts.admin :title="$taxonomy->name . ' — ' . strtoupper($cptSlug)">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">{{ $taxonomy->name }}
            <span class="text-[14px] text-[#646970] font-normal ml-2">({{ strtoupper($cptSlug) }})</span>
        </h1>    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    <div class="flex flex-col md:flex-row gap-6">
        <!-- Add Term Form -->
        <div class="w-full md:w-1/3">
            <h2 class="text-[14px] font-semibold text-[#1d2327] mb-3">Add New {{ $taxonomy->singular_name ?? $taxonomy->name }}</h2>
            <form action="{{ route('admin.acpt.terms.store', $taxonomy->slug) }}" method="POST">
                @csrf
                <input type="hidden" name="cpt_slug" value="{{ $cptSlug }}">

                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="wp-input w-full">
                    <p class="text-[12px] text-[#646970] mt-1">The name is how it appears on your site.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Slug</label>
                    <input type="text" name="slug" class="wp-input w-full">
                    <p class="text-[12px] text-[#646970] mt-1">URL-friendly version. Lowercase letters, numbers, and hyphens only.</p>
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Parent {{ $taxonomy->singular_name ?? $taxonomy->name }}</label>
                    <select name="parent_id" class="wp-input w-full">
                        <option value="">None</option>
                        @foreach($fullParents as $parent)
                            <option value="{{ $parent->id }}">{{ str_repeat('— ', $parent->level ?? 0) }}{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] text-[#1d2327] mb-1">Description</label>
                    <textarea name="description" rows="5" class="wp-input w-full"></textarea>
                    <p class="text-[12px] text-[#646970] mt-1">The description is not prominent by default; however, some themes may show it.</p>
                </div>

                <button type="submit" class="wp-btn-primary">Add New {{ $taxonomy->singular_name ?? $taxonomy->name }}</button>
            </form>
        </div>

        <!-- Terms Table -->
        <div class="w-full md:w-2/3">
            <div class="flex justify-end mb-2">
                <form action="{{ route('admin.acpt.terms.index', $taxonomy->slug) }}" method="GET" class="flex space-x-1">
                    <input type="hidden" name="cpt" value="{{ $cptSlug }}">
                    <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px]" placeholder="">
                    <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search {{ $taxonomy->name }}</button>
                </form>
            </div>

            <form action="{{ route('admin.acpt.terms.bulk', $taxonomy->slug) }}" method="POST">
                @csrf
                <input type="hidden" name="cpt" value="{{ $cptSlug }}">

                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center space-x-2">
                        <select name="action" class="wp-input py-0">
                            <option value="-1">Bulk actions</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Apply</button>
                    </div>
                    <x-falcon-cms::admin.pagination :paginator="$terms" />
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
                        @forelse($terms as $idx => $term)
                            <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group">
                                <td class="wp-table-cell text-center"><input type="checkbox" name="ids[]" value="{{ $term->id }}" class="cb-select-item rounded-sm border-[#8c8f94] text-[#2271b1]"></td>
                                <td class="wp-table-cell align-top">
                                    <strong><span class="text-[#2271b1]">{{ $term->name }}</span></strong>
                                    @if($term->parent_id)
                                        <span class="text-[#646970] text-[12px]"> — {{ optional($term->parent)->name }}</span>
                                    @endif
                                    <div class="invisible group-hover:visible mt-1 text-[13px] space-x-1">
                                        <a href="{{ route('admin.acpt.terms.edit', [$taxonomy->slug, $term->id, 'cpt' => $cptSlug]) }}" class="text-[#2271b1]">Edit</a>
                                        <span class="text-[#c3c4c7]">|</span>
                                        <button form="delete-form-{{ $term->id }}" type="submit" class="text-[#b32d2e] hover:text-[#8a2424]">Delete</button>
                                    </div>
                                </td>
                                <td class="wp-table-cell text-[#646970]">{{ $term->description ?: '—' }}</td>
                                <td class="wp-table-cell text-[#646970]">{{ $term->slug }}</td>
                                <td class="wp-table-cell text-right"><a href="{{ route('admin.posts.index', ['type' => $cptSlug, 'term_id' => $term->id]) }}" class="text-[#2271b1] font-semibold">{{ $term->posts_count ?? 0 }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="wp-table-cell text-center py-4">No {{ strtolower($taxonomy->name) }} found. Add one on the left!</td>
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
                        <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Apply</button>
                    </div>
                    <x-falcon-cms::admin.pagination :paginator="$terms" />
                </div>
            </form>

            @foreach($terms as $term)
                <form id="delete-form-{{ $term->id }}" action="{{ route('admin.acpt.terms.destroy', [$taxonomy->slug, $term->id]) }}" method="POST" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        </div>
    </div>

    <script>
        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function(master) {
            master.addEventListener('change', function() {
                let isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(cb => cb.checked = isChecked);
                document.getElementById('cb-select-all-1').checked = isChecked;
                document.getElementById('cb-select-all-2').checked = isChecked;
            });
        });
    </script>
</x-falcon-cms::layouts.admin>
