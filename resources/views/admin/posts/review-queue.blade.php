<x-falcon-cms::layouts.admin title="Pending Review">
    <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Pending Review</h1>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">{{ session('error') }}</div>
    @endif

    @if(!empty($changes))
        <h2 class="text-[15px] font-semibold text-[#1d2327] mb-2">Changes to Live Content</h2>
        <p class="text-[13px] text-[#646970] mb-2">Readers see the current version until these are approved.</p>
        <table class="w-full bg-white border border-[#c3c4c7] mb-6">
            <thead>
                <tr>
                    <th class="wp-table-header text-left">Title</th>
                    <th class="wp-table-header text-left">Type</th>
                    <th class="wp-table-header text-left">Proposed by</th>
                    <th class="wp-table-header text-left">Sent</th>
                    <th class="wp-table-header text-left">Decision</th>
                </tr>
            </thead>
            <tbody>
                @foreach($changes as $row)
                    <tr>
                        <td class="wp-table-cell align-top text-[14px]">
                            <a href="{{ route('admin.posts.changes', $row['post']->id) }}" class="text-[#2271b1] font-semibold hover:text-[#135e96]">{{ $row['post']->title ?: 'Untitled' }}</a>
                            @isset($locks[$row['post']->id])
                                <div class="text-[12px] text-[#b32d2e] mt-1">{{ $locks[$row['post']->id]['name'] }} is editing this</div>
                            @endisset
                        </td>
                        <td class="wp-table-cell align-top capitalize">{{ str_replace(['_', '-'], ' ', $row['post']->type) }}</td>
                        <td class="wp-table-cell align-top">{{ $row['change']->user_name }}</td>
                        <td class="wp-table-cell align-top whitespace-nowrap">{{ $row['change']->submitted_at ? \Illuminate\Support\Carbon::parse($row['change']->submitted_at)->diffForHumans() : '—' }}</td>
                        <td class="wp-table-cell align-top"><a href="{{ route('admin.posts.changes', $row['post']->id) }}" class="wp-btn-primary no-underline">Review Changes</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <h2 class="text-[15px] font-semibold text-[#1d2327] mb-2">New Content</h2>
    @endif

    <table class="w-full bg-white border border-[#c3c4c7]">
        <thead>
            <tr>
                <th class="wp-table-header text-left">Title</th>
                <th class="wp-table-header text-left">Type</th>
                <th class="wp-table-header text-left">Writer</th>
                <th class="wp-table-header text-left">Sent</th>
                <th class="wp-table-header text-left">Decision</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="wp-table-cell align-top text-[14px]">
                        <a href="{{ \FalconCms\Core\Support\Editorial::editUrl($item) }}" class="text-[#2271b1] font-semibold hover:text-[#135e96]">{{ $item->title ?: 'Untitled' }}</a>
                        @isset($locks[$item->id])
                            <div class="text-[12px] text-[#b32d2e] mt-1">{{ $locks[$item->id]['name'] }} is editing this</div>
                        @endisset
                        <div class="mt-1 text-[13px]">
                            <a href="{{ route('admin.falcon-builder.preview', $item->id) }}" target="_blank" class="text-[#2271b1] hover:underline">Preview</a>
                        </div>
                    </td>
                    <td class="wp-table-cell align-top capitalize">{{ str_replace(['_', '-'], ' ', $item->type) }}</td>
                    <td class="wp-table-cell align-top">{{ $item->user?->name ?? $item->user?->username ?? '—' }}</td>
                    <td class="wp-table-cell align-top whitespace-nowrap">
                        @php $at = $submittedAt[$item->id] ?? $item->updated_at; @endphp
                        {{ \Illuminate\Support\Carbon::parse($at)->diffForHumans() }}
                    </td>
                    <td class="wp-table-cell align-top min-w-[260px]">
                        <form action="{{ route('admin.posts.review', $item->id) }}" method="POST" data-no-unsaved-guard>
                            @csrf
                            <textarea name="note" rows="1" maxlength="2000" class="wp-input w-full mb-2 py-1 block" aria-label="Note for the writer" placeholder="Note for the writer (optional)"></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" name="decision" value="approve" class="wp-btn-primary">Approve &amp; Publish</button>
                                <button type="submit" name="decision" value="return" class="wp-btn-secondary">Request Changes</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="wp-table-cell text-center text-[#646970] py-8">Nothing is waiting for review.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4 flex justify-end">
        <x-falcon-cms::admin.pagination :paginator="$items" />
    </div>
</x-falcon-cms::layouts.admin>
