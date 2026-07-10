<x-falcon-cms::layouts.admin pro-lock-feature="{{ ($type ?? '') === 'product' ? 'ecommerce' : '' }}" title="{{ ucfirst($type ?? 'Posts') }}" active-menu="{{ ($type ?? 'post') === 'page' ? 'pages' : ($type ?? 'posts') }}">
    <x-falcon-cms::admin.delete-modal />
    <div class="mb-4 flex items-center">
        <h1 class="text-[23px] font-normal text-[#1d2327] inline-block mr-3">{{ ucfirst($type ?? 'Posts') }}</h1>
        <a href="{{ route('admin.posts.create', ['type' => $type ?? 'post']) }}" class="wp-btn-outline">Add New</a>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('error') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-4">
        <div class="flex flex-wrap items-center text-[13px] text-[#646970]">
            <a href="{{ route('admin.posts.index', ['type' => $type]) }}" class="{{ !request('status') ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ $allCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.posts.index', ['type' => $type, 'status' => 'published']) }}" class="{{ request('status') == 'published' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Published <span class="text-[#646970]">({{ $publishedCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.posts.index', ['type' => $type, 'status' => 'scheduled']) }}" class="{{ request('status') == 'scheduled' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Scheduled <span class="text-[#646970]">({{ $scheduledCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.posts.index', ['type' => $type, 'status' => 'draft']) }}" class="{{ request('status') == 'draft' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Draft <span class="text-[#646970]">({{ $draftCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.posts.index', ['type' => $type, 'status' => 'trash']) }}" class="{{ request('status') == 'trash' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Trash <span class="text-[#646970]">({{ $trashCount }})</span></a>
        </div>

        
        <form action="{{ route('admin.posts.index') }}" method="GET" class="flex items-center space-x-1 w-full md:w-auto">
            <input type="hidden" name="type" value="{{ $type }}">
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px] flex-grow md:w-48" placeholder="">
            <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search {{ ucfirst($type) }}s</button>
        </form>
    </div>

    <form id="filter-form" action="{{ route('admin.posts.index') }}" method="GET" class="hidden">
        <input type="hidden" name="type" value="{{ $type }}">
        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
        @if(request('s')) <input type="hidden" name="s" value="{{ request('s') }}"> @endif
    </form>

    <form id="posts-filter" method="POST" action="{{ route('admin.posts.bulk') }}">
    @csrf
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center space-x-1">
                <select name="action" class="wp-input py-0 h-[30px] text-[13px]">
                    <option value="-1">Bulk actions</option>
                    @if(request('status') === 'trash')
                        <option value="restore">Restore</option>
                        <option value="delete">Delete Permanently</option>
                    @else
                        <option value="draft">Move to Draft</option>
                        <option value="published">Publish</option>
                        <option value="trash">Move to Trash</option>
                    @endif
                </select>
                <button type="button" onclick="handleBulkAction('posts-filter')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
            </div>

            @if(request('status') !== 'trash')
            <div class="flex items-center space-x-1 ml-0 md:ml-4">
                <select name="m" form="filter-form" class="wp-input py-0 h-[30px] text-[13px]">
                    <option value="-1">All dates</option>
                    @foreach($dates as $date)
                        @php 
                            $val = $date->year . str_pad($date->month, 2, '0', STR_PAD_LEFT);
                            $name = date("F Y", mktime(0, 0, 0, $date->month, 1, $date->year));
                        @endphp
                        <option value="{{ $val }}" {{ request('m') == $val ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                
                <select name="cat" form="filter-form" class="wp-input py-0 h-[30px] text-[13px]">
                    <option value="-1">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('cat') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                
                <button type="submit" form="filter-form" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Filter</button>
            </div>
            @endif
        </div>
        
        <x-falcon-cms::admin.pagination :paginator="$posts" />
    </div>

    <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-4">
        <thead>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0"><input type="checkbox" id="cb-select-all-1" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left">Title</th>
                <th class="wp-table-header text-left">Author</th>
                <th class="wp-table-header text-left">Slug</th>

                @if(!in_array('categories', $overriddenTaxonomies) && ($type ?? 'post') === 'post')
                    <th class="wp-table-header text-left">Categories</th>
                @endif

                @foreach($assignedTaxonomies as $taxonomy)
                    @php $slugLower = strtolower($taxonomy->slug); @endphp
                    @php 
                        $shouldShow = false;
                        if (!in_array($slugLower, ['categories', 'tags', 'category', 'post_tag'])) {
                            $shouldShow = true;
                        } elseif (in_array($slugLower, ['categories', 'tags', 'category']) && ($type ?? 'post') !== 'product') {
                            $shouldShow = true;
                        } elseif ($slugLower === 'tags' && ($type ?? 'post') === 'product') {
                            $shouldShow = true;
                        }
                    @endphp
                    
                    @if($shouldShow)
                        <th class="wp-table-header text-left">{{ $taxonomy->name }}</th>
                    @endif
                @endforeach

                @if(!in_array('tags', $overriddenTaxonomies) && ($type ?? 'post') === 'post')
                    <th class="wp-table-header text-left">Tags</th>
                @endif
                @if(($type ?? 'post') === 'product')
                    <th class="wp-table-header text-left">Stock</th>
                @endif
                <th class="wp-table-header text-center w-8"><svg class="w-4 h-4 mx-auto text-[#8c8f94]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path></svg></th>
                <th class="wp-table-header text-left">SEO</th>
                <th class="wp-table-header text-left">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $idx => $post)
                <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group">
                    <td class="wp-table-cell text-center"><input type="checkbox" name="post_ids[]" value="{{ $post->id }}" class="cb-select-item rounded-sm border-[#8c8f94] text-[#2271b1]"></td>
                    <td class="wp-table-cell align-top text-[14px] text-left">
                        <strong>
                            <a href="{{ $post->trashed() ? '#' : route('admin.posts.edit', $post) }}" class="text-[#2271b1] hover:text-[#135e96]">{{ $post->title }}</a>
                            @if($post->status === 'draft' && !$post->trashed()) <span class="font-normal text-[#646970]"> — Draft</span> @endif 
                            @if($post->status === 'scheduled' && !$post->trashed()) <span class="font-normal text-[#646970]"> — Scheduled</span> @endif
                            @if(is_lazy_homepage($post)) <span class="font-normal text-[#646970]"> — Front Page</span> @endif
                            @if($post->trashed()) <span class="font-normal text-[#646970]"> — Trash</span> @endif
                        </strong>
                        <div class="invisible group-hover:visible mt-1 text-[13px] space-x-1">
                            @if($post->trashed())
                                <button form="restore-form-{{ $post->id }}" type="submit" class="text-[#2271b1] hover:underline cursor-pointer">Restore</button>
                                <span class="text-[#c3c4c7]">|</span>
                                <button type="button" onclick="confirmForceDelete({{ $post->id }})" class="text-[#b32d2e] hover:text-[#8a2424] hover:underline cursor-pointer">Delete Permanently</button>
                            @else
                                <a href="{{ route('admin.posts.edit', $post) }}" class="text-[#2271b1] hover:underline">Edit</a> <span class="text-[#c3c4c7]">|</span>
                                <button type="button" onclick="moveToTrash({{ $post->id }})" class="text-[#b32d2e] hover:text-[#8a2424] hover:underline cursor-pointer">Trash</button>
                                <span class="text-[#c3c4c7]">|</span>
                                <button type="submit" form="clone-form-{{ $post->id }}" class="text-[#2271b1] hover:underline cursor-pointer">Clone</button>
                                @if(!isset($postType) || $postType->is_public)
                                <span class="text-[#c3c4c7]">|</span>
                                <a href="{{ get_falcon_permalink($post) }}" target="_blank" class="text-[#2271b1] hover:underline">View</a>
                                @endif
                            @endif
                        </div>
                    </td>
                    <td class="wp-table-cell text-[#2271b1] text-left">{{ $post->user?->username ?? $post->user?->name ?? 'admin' }}</td>
                    <td class="wp-table-cell text-[#646970] text-left">{{ $post->slug }}</td>
                    
                    @if(!in_array('categories', $overriddenTaxonomies) && ($type ?? 'post') === 'post')
                    <td class="wp-table-cell text-left">
                        @if($post->categories->count() > 0)
                            {{ $post->categories->pluck('name')->implode(', ') }}
                        @else
                            <span class="text-[#646970]">Uncategorized</span>
                        @endif
                    </td>
                    @endif

                    @foreach($assignedTaxonomies as $taxonomy)
                        @php $slugLower = strtolower($taxonomy->slug); @endphp
                        @php 
                            $shouldShow = false;
                            if (!in_array($slugLower, ['categories', 'tags', 'category', 'post_tag'])) {
                                $shouldShow = true;
                            } elseif (in_array($slugLower, ['categories', 'tags', 'category']) && ($type ?? 'post') !== 'product') {
                                $shouldShow = true;
                            } elseif ($slugLower === 'tags' && ($type ?? 'post') === 'product') {
                                $shouldShow = true;
                            }
                        @endphp

                        @if($shouldShow)
                            <td class="wp-table-cell text-left">
                                @php 
                                    $terms = $post->taxonomyTerms->where('taxonomy_slug', $taxonomy->slug);
                                @endphp
                                @if($terms->count() > 0)
                                    {{ $terms->pluck('name')->implode(', ') }}
                                @else
                                    <span class="text-[#646970]">--</span>
                                @endif
                            </td>
                        @endif
                    @endforeach

                    @if(!in_array('tags', $overriddenTaxonomies) && ($type ?? 'post') === 'post')
                    <td class="wp-table-cell text-[#2c3338] text-left">
                        @if($post->tags->count() > 0)
                            {{ $post->tags->pluck('name')->implode(', ') }}
                        @else
                            <span class="text-[#646970]">--</span>
                        @endif
                    </td>
                    @endif
                    @if(($type ?? 'post') === 'product')
                    <td class="wp-table-cell text-left">
                        @php $stockStatus = $post->shopData?->stock_status ?? 'instock'; @endphp
                        @if($stockStatus === 'instock')
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#f0fdf4] text-[#166534] border border-[#bbf7d0]">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#16a34a]"></span>In Stock
                            </span>
                        @elseif($stockStatus === 'onbackorder')
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#fffbeb] text-[#92400e] border border-[#fde68a]">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#d97706]"></span>Backorder
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#fef2f2] text-[#991b1b] border border-[#fecaca]">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#dc2626]"></span>Out of Stock
                            </span>
                        @endif
                    </td>
                    @endif
                    <td class="wp-table-cell text-center text-[#646970]">-</td>
                    <td class="wp-table-cell text-left">
                        @php $score = $post->getSeoScore(); @endphp
                        <div class="flex items-center gap-1.5" title="SEO Score: {{ $score }}%">
                            <div class="w-2.5 h-2.5 rounded-full {{ $score >= 70 ? 'bg-[#00a32a]' : ($score >= 40 ? 'bg-[#dba617]' : 'bg-[#d63638]') }}"></div>
                            <span class="text-[12px] font-medium">{{ $score }}%</span>
                        </div>
                    </td>
                    <td class="wp-table-cell text-[#2c3338] text-left">
                        @if($post->trashed())
                            Last Modified<br>
                            <span class="text-[#646970] text-[12px]">{{ $post->updated_at ? cms_date($post->updated_at, 'Y/m/d \a\t g:i a') : '' }}</span>
                        @else
                            {{ ucfirst($post->status) }}<br>
                            <span class="text-[#646970] text-[12px]">{{ $post->created_at ? cms_date($post->created_at, 'Y/m/d \a\t g:i a') : '' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr class="bg-[#fff]">
                    <td colspan="7" class="wp-table-cell text-center py-4">No posts found.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0 border-t"><input type="checkbox" id="cb-select-all-2" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left border-t">Title</th>
                <th class="wp-table-header text-left border-t">Author</th>
                <th class="wp-table-header text-left border-t">Slug</th>
                @if(!in_array('categories', $overriddenTaxonomies) && ($type ?? 'post') === 'post')
                    <th class="wp-table-header text-left border-t">Categories</th>
                @endif

                @foreach($assignedTaxonomies as $taxonomy)
                    @php $slugLower = strtolower($taxonomy->slug); @endphp
                    @php 
                        $shouldShow = false;
                        if (!in_array($slugLower, ['categories', 'tags', 'category', 'post_tag'])) {
                            $shouldShow = true;
                        } elseif (in_array($slugLower, ['categories', 'tags', 'category']) && ($type ?? 'post') !== 'product') {
                            $shouldShow = true;
                        } elseif ($slugLower === 'tags' && ($type ?? 'post') === 'product') {
                            $shouldShow = true;
                        }
                    @endphp
                    
                    @if($shouldShow)
                        <th class="wp-table-header text-left border-t">{{ $taxonomy->name }}</th>
                    @endif
                @endforeach

                @if(!in_array('tags', $overriddenTaxonomies) && ($type ?? 'post') === 'post')
                    <th class="wp-table-header text-left border-t">Tags</th>
                @endif
                @if(($type ?? 'post') === 'product')
                    <th class="wp-table-header text-left border-t">Stock</th>
                @endif
                <th class="wp-table-header text-center w-8 border-t"><svg class="w-4 h-4 mx-auto text-[#8c8f94]" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path></svg></th>
                <th class="wp-table-header text-left border-t">SEO</th>
                <th class="wp-table-header text-left border-t">Date</th>
            </tr>
        </tfoot>
    </table>
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-2">
        <div class="flex items-center space-x-2">
            <select name="action2" class="wp-input py-0 h-[30px] text-[13px]">
                <option value="-1">Bulk actions</option>
                @if(request('status') === 'trash')
                    <option value="restore">Restore</option>
                    <option value="delete">Delete Permanently</option>
                @else
                    <option value="draft">Move to Draft</option>
                    <option value="published">Publish</option>
                    <option value="trash">Move to Trash</option>
                @endif
            </select>
            <button type="button" onclick="handleBulkAction('posts-filter', 'action2')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
        </div>
        
        <x-falcon-cms::admin.pagination :paginator="$posts" />
    </div>
    
    </form>

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

            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one item.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.falconConfirm({
                    title: 'Delete Permanently',
                    message: `Are you sure you want to permanently delete ${selected.length} items? This cannot be undone.`,
                    confirmText: 'Delete',
                    isDanger: true
                });

                if (!confirmed) return;
            }

            // If it was action2, we need to sync it to the main action field before submitting
            if (selectName === 'action2') {
                form.querySelector('select[name="action"]').value = action;
            }
            form.submit();
        };

        window.moveToTrash = function(id) {
            document.getElementById(`delete-form-${id}`).submit();
        };

        window.confirmForceDelete = async function(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Permanently',
                message: 'Are you sure you want to permanently delete this item? This action cannot be undone.',
                confirmText: 'Delete',
                isDanger: true
            });
            if (confirmed) {
                document.getElementById(`force-delete-form-${id}`).submit();
            }
        };
    </script>
    
    @foreach($posts as $post)
        <form id="delete-form-{{ $post->id }}" action="{{ route('admin.posts.destroy', $post) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
        <form id="restore-form-{{ $post->id }}" action="{{ route('admin.posts.restore', $post) }}" method="POST" class="hidden">
            @csrf
        </form>
        <form id="force-delete-form-{{ $post->id }}" action="{{ route('admin.posts.force-delete', $post) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
        <form id="clone-form-{{ $post->id }}" action="{{ route('admin.posts.clone', $post->id) }}" method="POST" class="hidden">
            @csrf
        </form>
    @endforeach
 
</x-falcon-cms::layouts.admin>
