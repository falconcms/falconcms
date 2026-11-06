{{-- Where an item stands in review, and for a reviewer the decision. Outside the editor's form. --}}
@php
    $__exempt = \FalconCms\Core\Support\Editorial::exempt((string) $post->type);
    $__review = !$__exempt && in_array($post->status, ['pending', 'draft'], true)
        ? \FalconCms\Core\Support\Editorial::lastReview((int) $post->id)
        : null;
    $__canReview = \FalconCms\Core\Support\Editorial::canPublish();
    $__live = !$__exempt && in_array($post->status, ['published', 'scheduled'], true);
    $__change = $__live ? \FalconCms\Core\Support\PostChanges::find((int) $post->id) : null;
    $__proposing = $__live && \FalconCms\Core\Support\PostChanges::proposing($post);
@endphp
@if($__proposing)
    <div id="editorial-banner" class="bg-[#fff] border-l-4 border-[#2271b1] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
        @if($__change && $__change->status === 'draft' && $__change->note)
            <p class="text-[#1d2327]"><strong>Changes were requested.</strong> Rework them below and send them again.</p>
            <p class="mt-1 mb-2 text-[#50575e] whitespace-pre-line">{{ $__change->note }}</p>
        @elseif($__change && $__change->status === 'pending')
            <p class="text-[#1d2327]"><strong>Your changes are waiting for review.</strong> You can keep editing them until then.</p>
        @elseif($__change)
            <p class="text-[#1d2327]"><strong>You have saved changes that are not sent yet.</strong> Send them for review when they are ready.</p>
        @else
            <p class="text-[#1d2327]"><strong>This is live.</strong> Your edits go live after review. Until then, readers keep seeing the current version.</p>
        @endif
        <p class="text-[#646970] mt-1">Address, publish date, price and stock, and custom fields are for those who can publish, so they are locked here.</p>
        @if($__change)
            <p class="mt-2">
                <a href="{{ route('admin.posts.changes', $post->id) }}" class="text-[#2271b1] font-semibold hover:underline">View my changes</a>
                <span class="text-[#c3c4c7] mx-1">|</span>
                <a href="{{ route('admin.posts.changes.preview', $post->id) }}" target="_blank" class="text-[#2271b1] font-semibold hover:underline">Preview</a>
            </p>
        @endif
    </div>
    <script>
    // Only what review covers stays open. The server ignores the rest either way; this says so
    // before anybody types into a field that would not be kept.
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('post-form') || document.getElementById('page-form');
        if (!form) return;
        var open = /^(_token|_method|type|status|title|content|excerpt|editor_type|featured_image|remove_featured_image|gallery\[\]|gallery_submitted|categories\[\]|tax_terms\[\]|tags|visibility|seo\[.*\]|published_at)$/;
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) {
            if (!open.test(el.name)) { el.disabled = true; el.required = false; }
        });
        ['edit-slug-btn', 'edit-slug'].forEach(function (id) { var b = document.getElementById(id); if (b) b.style.display = 'none'; });
        document.querySelectorAll('button[form="trash-form-main"]').forEach(function (b) { b.style.display = 'none'; });
    });
    </script>
@elseif($__live && $__change && $__canReview)
    <div id="editorial-banner" class="bg-[#fff] border-l-4 border-[#dba617] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
        <p class="text-[#1d2327]"><strong>{{ $__change->user_name }} proposed changes to this.</strong> {{ $__change->status === 'pending' ? 'They are waiting for review.' : 'They have not sent them for review yet.' }} Below is the live version.</p>
        <p class="mt-2"><a href="{{ route('admin.posts.changes', $post->id) }}" class="wp-btn-primary no-underline">Review Changes</a></p>
    </div>
@elseif($post->status === 'pending' && !$__exempt)
    <div id="editorial-banner" class="bg-[#fff] border-l-4 border-[#dba617] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
        <p class="text-[#1d2327]">
            <strong>Pending Review.</strong>
            @if($__review && $__review->action === \FalconCms\Core\Support\Editorial::SUBMITTED)
                Sent by {{ $__review->user_name }} {{ \Illuminate\Support\Carbon::parse($__review->created_at)->diffForHumans() }}.
            @endif
            @unless($__canReview) It goes live once someone approves it. @endunless
        </p>
        @if($__canReview)
            <p class="text-[#646970] mt-1">Approving publishes what was last saved, so save any edits first.</p>
            <form action="{{ route('admin.posts.review', $post->id) }}" method="POST" class="mt-2" data-no-unsaved-guard>
                @csrf
                <textarea name="note" rows="2" maxlength="2000" class="wp-input w-full max-w-[560px] mb-2 py-1 block" aria-label="Note for the writer" placeholder="Note for the writer (optional)"></textarea>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" name="decision" value="approve" class="wp-btn-primary">Approve &amp; Publish</button>
                    <button type="submit" name="decision" value="return" class="wp-btn-secondary">Request Changes</button>
                </div>
            </form>
        @endif
    </div>
@elseif($post->status === 'draft' && $__review && $__review->action === \FalconCms\Core\Support\Editorial::RETURNED)
    <div id="editorial-banner" class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
        <p class="text-[#1d2327]"><strong>{{ $__review->user_name }} asked for changes.</strong> Send it for review again when it is ready.</p>
        @if($__review->note)
            <p class="mt-1 text-[#50575e] whitespace-pre-line">{{ $__review->note }}</p>
        @endif
    </div>
@endif
