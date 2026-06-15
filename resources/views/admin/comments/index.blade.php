<x-falcon-cms::layouts.admin title="Comments">
    <x-falcon-cms::admin.delete-modal />
    <div class="flex items-center mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327] mr-3">Comments</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px] flex justify-between items-center">
            <p>{{ session('success') }}</p>
            <button type="button" class="text-[#646970] hover:text-black" onclick="this.parentElement.remove()">×</button>
        </div>
    @endif

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2 gap-4">
        <div class="flex items-center text-[13px] text-[#646970]">
            <a href="{{ route('admin.comments.index') }}" class="{{ !request('status') ? 'text-black font-semibold' : 'text-[#2271b1]' }}">All <span class="text-[#646970]">({{ $allCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.comments.index', ['status' => 'pending']) }}" class="{{ request('status') == 'pending' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Pending <span class="text-[#646970]">({{ $pendingCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.comments.index', ['status' => 'approved']) }}" class="{{ request('status') == 'approved' ? 'text-black font-semibold' : 'text-[#2271b1]' }}">Approved <span class="text-[#646970]">({{ $approvedCount }})</span></a>
            <span class="mx-1 text-[#c3c4c7]">|</span>
            <a href="{{ route('admin.shop.reviews.index') }}" class="text-[#2271b1]">Product Reviews <span class="text-[#646970]">({{ \FalconCms\Core\Models\Review::count() }})</span></a>
        </div>
        
        <form action="{{ route('admin.comments.index') }}" method="GET" class="flex items-center space-x-1 w-full md:w-auto">
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <input type="text" name="s" value="{{ request('s') }}" class="wp-input h-[30px] flex-grow md:w-48" placeholder="Search comments">
            <button type="submit" class="wp-btn-secondary h-[30px] leading-[1]">Search Comments</button>
        </form>
    </div>

    <form id="comments-bulk" method="POST" action="{{ route('admin.comments.bulk') }}">
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
                <button type="button" onclick="handleBulkCommentAction('comments-bulk', 'action')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
            </div>
        </div>
        
        <x-falcon-cms::admin.pagination :paginator="$comments" />
    </div>

    <table class="w-full bg-[#fff] border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,.04)] mb-4">
        <thead>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0"><input type="checkbox" id="cb-select-all-1" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left">Author</th>
                <th class="wp-table-header text-left">Comment</th>
                <th class="wp-table-header text-left">In Response To</th>
                <th class="wp-table-header text-left">Submitted On</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comments as $idx => $comment)
                <tr class="{{ $idx % 2 === 0 ? 'bg-[#f6f7f7]' : 'bg-[#fff]' }} group {{ !$comment->is_approved ? 'bg-[#fef7f1] border-l-4 border-l-[#dba617]' : '' }}">
                    <td class="wp-table-cell text-center"><input type="checkbox" name="comment_ids[]" value="{{ $comment->id }}" class="cb-select-item rounded-sm border-[#8c8f94] text-[#2271b1]"></td>
                    <td class="wp-table-cell align-top text-[14px] w-48">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="w-8 h-8 rounded-sm bg-gray-100 flex items-center justify-center text-gray-400 font-bold">
                                {{ substr($comment->name, 0, 1) }}
                            </div>
                            <strong class="text-black">{{ $comment->name }}</strong>
                        </div>
                        <a href="mailto:{{ $comment->email }}" class="text-[#2271b1] text-[13px]">{{ $comment->email }}</a>
                    </td>
                    <td class="wp-table-cell align-top text-[14px] text-left">
                        <div class="text-[#2c3338] mb-2 leading-relaxed">
                            {{ $comment->comment }}
                        </div>
                        <div class="invisible group-hover:visible text-[13px] space-x-1">
                            <button form="toggle-approve-form-{{ $comment->id }}" type="submit" class="{{ $comment->is_approved ? 'text-[#dba617]' : 'text-[#00a32a]' }} hover:underline cursor-pointer">
                                {{ $comment->is_approved ? 'Unapprove' : 'Approve' }}
                            </button>
                            <span class="text-[#c3c4c7]">|</span>
                            <button type="button" class="text-[#b32d2e] hover:underline cursor-pointer" onclick="confirmCommentTrash({{ $comment->id }})">Trash</button>
                        </div>
                    </td>
                    <td class="wp-table-cell align-top text-[14px] text-left w-48">
                        @if($comment->parent_id)
                            <div class="mb-2">
                                <span class="bg-blue-100 text-[#2271b1] text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Reply</span>
                                <span class="text-[11px] text-[#646970] block mt-1">To: {{ $comment->parent->name ?? 'Unknown' }}</span>
                            </div>
                        @else
                            <div class="mb-2">
                                <span class="bg-gray-100 text-[#646970] text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Comment</span>
                            </div>
                        @endif
                        
                        @if($comment->post)
                            <a href="{{ route('admin.posts.edit', $comment->post) }}" class="text-[#2271b1] font-semibold hover:underline block mb-1 leading-tight">{{ $comment->post->title }}</a>
                            <a href="{{ route('frontend.show', ['typeOrSlug' => $comment->post->type, 'slug' => $comment->post->slug]) }}" target="_blank" class="text-[#646970] text-[12px] hover:text-[#2271b1]">View Post</a>
                        @else
                            <span class="text-[#646970] italic">(Deleted Post)</span>
                        @endif
                    </td>
                    <td class="wp-table-cell align-top text-[#2c3338] text-left w-40">
                        <span class="text-[13px]">{{ cms_date($comment->created_at, 'Y/m/d \a\t g:i a') }}</span>
                    </td>
                </tr>
            @empty
                <tr class="bg-[#fff]">
                    <td colspan="5" class="wp-table-cell text-center py-4">No comments found.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th class="wp-table-header w-8 text-center pb-0 border-t"><input type="checkbox" id="cb-select-all-2" class="rounded-sm border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1]"></th>
                <th class="wp-table-header text-left border-t">Author</th>
                <th class="wp-table-header text-left border-t">Comment</th>
                <th class="wp-table-header text-left border-t">In Response To</th>
                <th class="wp-table-header text-left border-t">Submitted On</th>
            </tr>
        </tfoot>
    </table>
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-2">
        <div class="flex items-center space-x-2">
            <select name="action2" class="wp-input py-0 h-[30px] text-[13px]">
                <option value="-1">Bulk actions</option>
                <option value="approve">Approve</option>
                <option value="unapprove">Unapprove</option>
                <option value="delete">Delete Permanently</option>
            </select>
            <button type="button" onclick="handleBulkCommentAction('comments-bulk', 'action2')" class="wp-btn-secondary h-[30px] leading-[1] text-[13px]">Apply</button>
        </div>
        
        <x-falcon-cms::admin.pagination :paginator="$comments" />
    </div>
    </form>

    @foreach($comments as $comment)
        <form id="toggle-approve-form-{{ $comment->id }}" action="{{ route('admin.comments.toggle-approve', $comment) }}" method="POST" class="hidden">
            @csrf
        </form>
        <form id="delete-form-{{ $comment->id }}" action="{{ route('admin.comments.destroy', $comment) }}" method="POST" class="hidden">
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
                document.getElementById('cb-select-all-1').checked = isChecked;
                document.getElementById('cb-select-all-2').checked = isChecked;
            });
        });

        window.confirmCommentTrash = async function(id) {
            const confirmed = await window.lazyConfirm({
                title: 'Trash Comment',
                message: 'Are you sure you want to move this comment to trash? This action can be undone from the trash section later.',
                confirmText: 'Move to Trash',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-form-${id}`).submit();
            }
        };

        window.handleBulkCommentAction = async function(formId, selectName) {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');

            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one comment.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.lazyConfirm({
                    title: 'Delete Comments Permanently',
                    message: `Are you sure you want to permanently delete ${selected.length} comments? This action cannot be undone.`,
                    confirmText: 'Delete Permanently',
                    isDanger: true
                });

                if (confirmed) {
                    // Sync action2 if action is selected or vice-versa
                    form.querySelector('select[name="action"]').value = action;
                    if(form.querySelector('select[name="action2"]')) form.querySelector('select[name="action2"]').value = action;
                    form.submit();
                }
            } else {
                form.querySelector('select[name="action"]').value = action;
                if(form.querySelector('select[name="action2"]')) form.querySelector('select[name="action2"]').value = action;
                form.submit();
            }
        };
    </script>
</x-falcon-cms::layouts.admin>
