<x-falcon-cms::layouts.admin title="Changes to Review">
    <style>
        .pc-card { background:#fff; border:1px solid #c3c4c7; margin-bottom:16px; }
        .pc-head { padding:8px 12px; border-bottom:1px solid #f0f0f1; font-weight:600; font-size:14px; color:#1d2327; }
        .pc-grid { display:grid; grid-template-columns:1fr 1fr; }
        .pc-cell { padding:10px 12px; font-size:13px; line-height:1.5; white-space:pre-wrap; overflow-wrap:anywhere; min-width:0; }
        .pc-old { background:#fcf0f1; color:#50575e; border-right:1px solid #f0f0f1; }
        .pc-new { background:#edfaef; color:#1d2327; }
        .pc-tag { display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:#646970; margin-bottom:4px; white-space:normal; }
        .pc-img { max-width:160px; max-height:120px; display:inline-block; margin:0 4px 4px 0; }
        .pc-diff { padding:8px 12px; font-size:12.5px; overflow-x:auto; }
        .pc-diff .diff-add, .pc-diff ins { background:#edfaef; }
        .pc-diff .diff-del, .pc-diff del { background:#fcf0f1; }
        @media (max-width: 782px) { .pc-grid { grid-template-columns:1fr; } .pc-old { border-right:0; border-bottom:1px solid #f0f0f1; } }
    </style>

    <h1 class="text-[23px] font-normal text-[#1d2327] mb-1">Changes to Review</h1>
    <p class="text-[13px] text-[#646970] mb-4">
        <a href="{{ \FalconCms\Core\Support\Editorial::editUrl($post) }}" class="text-[#2271b1] font-semibold hover:underline">{{ $post->title ?: 'Untitled' }}</a>
        · Proposed by {{ $change->user_name }}{{ $change->submitted_at ? ' '.\Illuminate\Support\Carbon::parse($change->submitted_at)->diffForHumans() : '' }}
        · {{ $change->status === 'pending' ? 'Waiting for review' : 'Not sent for review yet' }}
    </p>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] p-3 mb-4 rounded-sm text-[13px]">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-[#fff] border-l-4 border-[#d63638] p-3 mb-4 rounded-sm text-[13px]">{{ session('error') }}</div>
    @endif
    @if($change->note)
        <div class="bg-[#fff] border-l-4 border-[#d63638] p-3 mb-4 rounded-sm text-[13px]"><strong>Requested changes:</strong> <span class="whitespace-pre-line">{{ $change->note }}</span></div>
    @endif

    @foreach($rows as $row)
        <div class="pc-card">
            <div class="pc-head">{{ $row['label'] }}</div>
            @if($row['kind'] === 'diff')
                <div class="pc-diff">{!! falcon_revision_diff($row['old'], $row['new']) !!}</div>
            @elseif($row['kind'] === 'image')
                <div class="pc-grid">
                    <div class="pc-cell pc-old"><span class="pc-tag">Live now</span>@if($row['old'])<img class="pc-img" src="{{ str_starts_with($row['old'], 'http') ? $row['old'] : asset('storage/'.$row['old']) }}" alt="">@else None @endif</div>
                    <div class="pc-cell pc-new"><span class="pc-tag">Proposed</span>@if($row['new'])<img class="pc-img" src="{{ str_starts_with($row['new'], 'http') ? $row['new'] : asset('storage/'.$row['new']) }}" alt="">@else None @endif</div>
                </div>
            @elseif($row['kind'] === 'gallery')
                <div class="pc-grid">
                    <div class="pc-cell pc-old"><span class="pc-tag">Live now</span>@forelse($row['old'] as $image)<img class="pc-img" src="{{ asset('storage/'.$image) }}" alt="">@empty None @endforelse</div>
                    <div class="pc-cell pc-new"><span class="pc-tag">Proposed</span>@forelse($row['new'] as $image)<img class="pc-img" src="{{ asset('storage/'.$image) }}" alt="">@empty None @endforelse</div>
                </div>
            @else
                <div class="pc-grid">
                    <div class="pc-cell pc-old"><span class="pc-tag">Live now</span>{{ $row['old'] !== '' ? $row['old'] : 'Empty' }}</div>
                    <div class="pc-cell pc-new"><span class="pc-tag">Proposed</span>{{ $row['new'] !== '' ? $row['new'] : 'Empty' }}</div>
                </div>
            @endif
        </div>
    @endforeach

    <div class="pc-card p-3">
        <p class="text-[13px] text-[#50575e] mb-2">
            <a href="{{ route('admin.posts.changes.preview', $post->id) }}" target="_blank" class="text-[#2271b1] font-semibold hover:underline">Preview with these changes</a>
            · Readers see the live version until this is approved.
        </p>
        <form action="{{ route('admin.posts.changes.decide', $post->id) }}" method="POST" data-no-unsaved-guard>
            @csrf
            @if($canReview)
                <textarea name="note" rows="2" maxlength="2000" class="wp-input w-full max-w-[560px] mb-2 py-1 block" aria-label="Note for the writer" placeholder="Note for the writer (optional)"></textarea>
            @endif
            <div class="flex flex-wrap gap-2">
                @if($canReview)
                    <button type="submit" name="decision" value="approve" class="wp-btn-primary">Approve &amp; Publish</button>
                    <button type="submit" name="decision" value="return" class="wp-btn-secondary">Request Changes</button>
                @endif
                <button type="submit" name="decision" value="discard" class="wp-btn-secondary" style="color:#b32d2e;border-color:#b32d2e" onclick="return confirm('Discard these changes? They cannot be brought back.')">Discard Changes</button>
            </div>
        </form>
    </div>
</x-falcon-cms::layouts.admin>
