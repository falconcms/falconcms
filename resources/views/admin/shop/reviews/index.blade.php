<x-falcon-cms::layouts.admin title="Product Reviews">
    <x-falcon-cms::admin.delete-modal />
    <div class="flex items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327] mr-3">Product Reviews</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-4">
        <div class="flex items-center text-[13px] text-[#646970]">
            <a href="{{ route('admin.shop.reviews.index') }}" class="{{ !request('status') ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ $allCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.shop.reviews.index', ['status' => 'pending']) }}" class="{{ request('status') == 'pending' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Pending <span class="text-[#646970]">({{ $pendingCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.shop.reviews.index', ['status' => 'approved']) }}" class="{{ request('status') == 'approved' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Approved <span class="text-[#646970]">({{ $approvedCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.comments.index') }}" class="text-[#2271b1]">Comments <span class="text-[#646970]">({{ \FalconCms\Core\Models\Comment::count() }})</span></a>
        </div>
        
        <form action="{{ route('admin.shop.reviews.index') }}" method="GET" class="flex items-center space-x-1 w-full md:w-auto">
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px] flex-grow md:w-48" placeholder="Search reviews">
            <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search Reviews</button>
        </form>
    </div>

    <form id="reviews-bulk" method="POST" action="{{ route('admin.shop.reviews.bulk') }}">
    @csrf
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center space-x-1">
                <select name="action" class="wp-input py-0 h-[30px] text-[13px]">
                    <option value="-1">Bulk actions</option>
                    <option value="approve">Approve</option>
                    <option value="unapprove">Unapprove</option>
                    <option value="delete">Delete Permanently</option>
                </select>
                <button type="button" onclick="handleBulkAction('reviews-bulk', 'action')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
            </div>
        </div>
        
        <x-falcon-cms::admin.pagination :paginator="$reviews" />
    </div>

    <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-4">
        <thead>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0"><input type="checkbox" id="cb-select-all-1" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left">Author</th>
                <th class="wp-table-header text-left">Review</th>
                <th class="wp-table-header text-left">Product</th>
                <th class="wp-table-header text-left">Submitted On</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviews as $idx => $review)
                <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group {{ !$review->is_approved ? 'bg-[#fef7f1] border-l-4 border-l-[#dba617]' : '' }}">
                    <td class="wp-table-cell text-center"><input type="checkbox" name="review_ids[]" value="{{ $review->id }}" class="cb-select-item rounded-sm border-[#8c8f94] text-[#2271b1]"></td>
                    <td class="wp-table-cell align-top text-[14px] w-48">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="w-8 h-8 rounded-sm bg-gray-100 flex items-center justify-center text-gray-400 font-bold uppercase">
                                {{ substr($review->name, 0, 1) }}
                            </div>
                            <strong class="text-black">{{ $review->name }}</strong>
                        </div>
                        <a href="mailto:{{ $review->email }}" class="text-[#2271b1] text-[13px]">{{ $review->email }}</a>
                    </td>
                    <td class="wp-table-cell align-top text-[14px] text-left">
                        <div class="flex items-center gap-1 mb-2">
                            @for($i=1; $i<=5; $i++)
                                <svg class="w-3 h-3 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }} fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            @endfor
                        </div>
                        <div class="text-[#2c3338] mb-2 leading-relaxed">
                            {{ $review->comment }}
                        </div>
                        <div class="invisible group-hover:visible text-[13px] space-x-1">
                            <button form="toggle-approve-form-{{ $review->id }}" type="submit" class="{{ $review->is_approved ? 'text-[#dba617]' : 'text-[#00a32a]' }} hover:underline cursor-pointer">
                                {{ $review->is_approved ? 'Unapprove' : 'Approve' }}
                            </button>
                            <span class="text-[#c3c4c7]">|</span>
                            <button type="button" class="text-[#b32d2e] hover:underline cursor-pointer" onclick="confirmDelete({{ $review->id }})">Trash</button>
                        </div>
                    </td>
                    <td class="wp-table-cell align-top text-[14px] text-left w-48">
                        @if($review->post)
                            <a href="{{ route('admin.posts.edit', $review->post) }}" class="text-[#2271b1] font-semibold hover:underline block mb-1 leading-tight">{{ $review->post->title }}</a>
                            <a href="{{ url('/product/' . $review->post->slug) }}" target="_blank" class="text-[#646970] text-[12px] hover:text-[#2271b1]">View Product</a>
                        @else
                            <span class="text-[#646970] italic">(Deleted Product)</span>
                        @endif
                    </td>
                    <td class="wp-table-cell align-top text-[#2c3338] text-left w-40">
                        <span class="text-[13px]">{{ cms_date($review->created_at, 'Y/m/d \a\t g:i a') }}</span>
                    </td>
                </tr>
            @empty
                <tr class="bg-[#fff]">
                    <td colspan="5" class="wp-table-cell text-center py-4">No reviews found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-2">
        <div class="flex items-center space-x-2">
            <select name="action2" class="wp-input py-0 h-[30px] text-[13px]">
                <option value="-1">Bulk actions</option>
                <option value="approve">Approve</option>
                <option value="unapprove">Unapprove</option>
                <option value="delete">Delete Permanently</option>
            </select>
            <button type="button" onclick="handleBulkAction('reviews-bulk', 'action2')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
        </div>
        
        <x-falcon-cms::admin.pagination :paginator="$reviews" />
    </div>
    </form>

    @foreach($reviews as $review)
        <form id="toggle-approve-form-{{ $review->id }}" action="{{ route('admin.shop.reviews.toggle-approve', $review) }}" method="POST" class="hidden">
            @csrf
        </form>
        <form id="delete-form-{{ $review->id }}" action="{{ route('admin.shop.reviews.destroy', $review) }}" method="POST" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach

    <script>
        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function(master) {
            master.addEventListener('change', function() {
                let isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(function(item) {
                    item.checked = isChecked;
                });
            });
        });

        window.confirmDelete = async function(id) {
            const confirmed = await window.lazyConfirm({
                title: 'Delete Review',
                message: 'Are you sure you want to delete this review?',
                confirmText: 'Delete',
                isDanger: true
            });
            if (confirmed) document.getElementById(`delete-form-${id}`).submit();
        };

        window.handleBulkAction = async function(formId, selectName) {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');
            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one item.', 'warning');
                return;
            }
            form.querySelector('select[name="action"]').value = action;
            form.submit();
        };
    </script>
</x-falcon-cms::layouts.admin>
