@php
    /*
    |--------------------------------------------------------------------------
    | Comments — frontend renderer
    |--------------------------------------------------------------------------
    | The approved comments of the post being viewed, their replies and the form to add one,
    | for a Layout Builder section (or any builder layout on a single post). The post is the
    | one the layout is rendering for: $post from the layout's post context, else the page's
    | current_post. On a page that is not about one post there is nothing to show.
    |
    | The form posts to frontend.comment.store, which answers with a redirect back to
    | #comments and flashes falcon_comment_posted (the post id) so only this post's element
    | shows the notice. Admin canvas: admin/falcon-builder/partials/components/elements/post-comments.
    */
    use FalconCms\Core\Models\Comment;
    use FalconCms\Core\Models\Post;
    use FalconCms\Core\Support\Typography;

    $s = $el['settings'] ?? [];

    $cPost = (isset($post) && $post instanceof Post) ? $post : (view()->getShared()['current_post'] ?? null);
    if (!$cPost instanceof Post || !$cPost->exists) {
        $cPost = null;
    }

    $v = $s['visibility'] ?? ['mobile' => true, 'tablet' => true, 'desktop' => true];
    $visibilityClasses = '';
    if (!($v['mobile']  ?? true)) $visibilityClasses .= ' falcon-hide-mobile';
    if (!($v['tablet']  ?? true)) $visibilityClasses .= ' falcon-hide-tablet';
    if (!($v['desktop'] ?? true)) $visibilityClasses .= ' falcon-hide-desktop';

    $uid = 'fc-cm-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? uniqid('', false)));

    $txt = fn (string $key, string $default) => array_key_exists($key, $s) && $s[$key] !== null ? trim((string) $s[$key]) : $default;
    $title      = $txt('title', 'Comments');
    $formTitle  = $txt('formTitle', 'Leave a Reply');
    $emptyText  = $txt('emptyText', 'No comments yet. Be the first to share your thoughts!');
    $buttonText = trim((string) ($s['buttonText'] ?? '')) ?: 'Post Comment';
    $showCount    = ($s['showCount'] ?? true) !== false;
    $allowReplies = ($s['allowReplies'] ?? true) !== false;
    $showForm     = ($s['showForm'] ?? true) !== false;
    $showAvatars  = ($s['showAvatars'] ?? true) !== false;
    $showDates    = ($s['showDates'] ?? true) !== false;
    $newestFirst  = ($s['order'] ?? 'oldest') === 'newest';

    // Colours: empty means the theme's own (its primary colour for the accent).
    $col = fn (string $key, string $default) => (is_string($s[$key] ?? null) && trim($s[$key]) !== '') ? trim($s[$key]) : $default;
    $vars = [
        '--fcm-accent'   => $col('accentColor', 'var(--primary, #0091ea)'),
        '--fcm-btn-text' => $col('buttonTextColor', '#ffffff'),
        '--fcm-title'    => $col('titleColor', '#111827'),
        '--fcm-text'     => $col('textColor', '#4b5563'),
        '--fcm-meta'     => $col('metaColor', '#9ca3af'),
        '--fcm-name'     => $col('nameColor', '#111827'),
        '--fcm-card'     => $col('cardBg', 'transparent'),
        '--fcm-border'   => $col('borderColor', '#e5e7eb'),
        '--fcm-input'    => $col('inputBg', '#ffffff'),
        '--fcm-radius'   => max(0, min(40, (int) ($s['radius'] ?? 8))) . 'px',
        '--fcm-gap'      => max(0, min(80, (int) ($s['itemGap'] ?? 28))) . 'px',
    ];
    $varCss = '';
    foreach ($vars as $k => $val) {
        // A colour is a colour: anything that could close the declaration is refused.
        if (preg_match('/[;{}<>]/', $val)) continue;
        $varCss .= $k . ':' . $val . ';';
    }

    $titleTypo = Typography::css($s, 'cm_title');
    $bodyTypo  = Typography::css($s, 'cm_body');

    $marginTop    = ($s['marginTop']    ?? 0) . ($s['marginTopUnit']    ?? 'px');
    $marginBottom = ($s['marginBottom'] ?? 0) . ($s['marginBottomUnit'] ?? 'px');

    $bpSm  = (int) get_cms_option('theme_small_screen_breakpoint',  '800');
    $bpMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
    $respCss = falcon_elem_resp_css($s, $bpSm, $bpMed, [
        ['prop' => 'marginTop',    'unitProp' => 'marginTopUnit',    'sel' => ".{$uid}"],
        ['prop' => 'marginBottom', 'unitProp' => 'marginBottomUnit', 'sel' => ".{$uid}"],
    ]);

    $comments = collect();
    $total = 0;
    if ($cPost) {
        $comments = $cPost->comments()
            ->with(['replies' => fn ($q) => $q->orderBy('created_at')])
            ->orderBy('created_at', $newestFirst ? 'desc' : 'asc')
            ->get();
        $total = Comment::where('post_id', $cPost->id)->where('is_approved', true)->count();
    }

    // The notice belongs to the post the comment was left on, not to every comment list.
    $notice = ($cPost && (int) session('falcon_comment_posted') === (int) $cPost->id) ? session('success') : null;
    $errorBag = (isset($errors) && $errors instanceof \Illuminate\Support\ViewErrorBag) ? $errors : null;
    $hasErrors = $cPost && $errorBag && $errorBag->any() && (int) old('post_id') === (int) $cPost->id;
    // A refusal that is not a validation error — Falcon Security's "you have sent a lot in a short
    // time" — comes back as an 'error' flash with the form's input.
    $refusal = ($cPost && (int) old('post_id') === (int) $cPost->id && is_string(session('error'))) ? session('error') : null;
    $initial = fn ($name) => mb_strtoupper(mb_substr(trim((string) $name) ?: '?', 0, 1));
@endphp

@if($cPost)
@if($respCss){!! '<style>' . $respCss . '</style>' !!}@endif
<div class="falcon-comments {{ $uid }}{{ $visibilityClasses }} {{ $s['cssClass'] ?? '' }}"
     @if(!empty($s['cssId'])) id="{{ $s['cssId'] }}" @endif
     style="width:100%;margin-top:{{ $marginTop }};margin-bottom:{{ $marginBottom }};{{ $varCss }}">

    <style>
        .{{ $uid }} { color: var(--fcm-text); }
        .{{ $uid }} .fcm-title { margin: 0 0 24px; color: var(--fcm-title); font-size: 26px; font-weight: 800; line-height: 1.25; {{ $titleTypo }} }
        .{{ $uid }} .fcm-list { list-style: none; margin: 0 0 40px; padding: 0; display: flex; flex-direction: column; gap: var(--fcm-gap); }
        .{{ $uid }} .fcm-item { display: flex; gap: 16px; background: var(--fcm-card); border-radius: var(--fcm-radius); }
        .{{ $uid }} .fcm-item.has-card { padding: 18px; border: 1px solid var(--fcm-border); }
        .{{ $uid }} .fcm-avatar { flex-shrink: 0; width: 44px; height: 44px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: var(--fcm-accent); background: color-mix(in srgb, var(--fcm-accent) 12%, transparent); }
        .{{ $uid }} .fcm-replies .fcm-avatar { width: 36px; height: 36px; font-size: 15px; }
        .{{ $uid }} .fcm-main { flex: 1; min-width: 0; }
        .{{ $uid }} .fcm-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; margin-bottom: 6px; }
        .{{ $uid }} .fcm-name { font-weight: 700; color: var(--fcm-name); }
        .{{ $uid }} .fcm-date { font-size: 12px; color: var(--fcm-meta); }
        .{{ $uid }} .fcm-body { margin: 0; line-height: 1.7; white-space: pre-line; overflow-wrap: anywhere; {{ $bodyTypo }} }
        .{{ $uid }} .fcm-reply-btn { margin-top: 8px; padding: 0; border: 0; background: none; cursor: pointer; font-size: 12px; font-weight: 700; color: var(--fcm-accent); }
        .{{ $uid }} .fcm-reply-btn:hover { text-decoration: underline; }
        .{{ $uid }} .fcm-replies { list-style: none; margin: 18px 0 0; padding: 0 0 0 18px; border-left: 2px solid var(--fcm-border); display: flex; flex-direction: column; gap: 18px; }
        .{{ $uid }} .fcm-empty { margin: 0 0 40px; font-style: italic; color: var(--fcm-meta); }
        .{{ $uid }} .fcm-notice { margin: 0 0 24px; padding: 12px 14px; border-radius: var(--fcm-radius); font-size: 14px; font-weight: 600; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .{{ $uid }} .fcm-errors { margin: 0 0 16px; padding: 12px 14px; border-radius: var(--fcm-radius); font-size: 14px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .{{ $uid }} .fcm-errors ul { margin: 0; padding-left: 18px; }
        .{{ $uid }} .fcm-form-title { margin: 0 0 16px; color: var(--fcm-title); font-size: 20px; font-weight: 700; {{ $titleTypo }} }
        .{{ $uid }} .fcm-form { display: flex; flex-direction: column; gap: 14px; }
        .{{ $uid }} .fcm-reply-form { margin-top: 14px; }
        .{{ $uid }} .fcm-reply-form[hidden] { display: none; }
        .{{ $uid }} .fcm-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 640px) { .{{ $uid }} .fcm-row { grid-template-columns: 1fr; } }
        .{{ $uid }} .fcm-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: var(--fcm-name); }
        .{{ $uid }} .fcm-input { width: 100%; box-sizing: border-box; padding: 10px 12px; font: inherit; font-size: 14px; color: var(--fcm-name); background: var(--fcm-input); border: 1px solid var(--fcm-border); border-radius: var(--fcm-radius); outline: none; transition: border-color .15s, box-shadow .15s; }
        .{{ $uid }} .fcm-input:focus { border-color: var(--fcm-accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--fcm-accent) 18%, transparent); }
        .{{ $uid }} textarea.fcm-input { resize: vertical; min-height: 120px; }
        .{{ $uid }} .fcm-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .{{ $uid }} .fcm-submit { padding: 11px 24px; border: 0; border-radius: var(--fcm-radius); cursor: pointer; font-size: 14px; font-weight: 700; color: var(--fcm-btn-text); background: var(--fcm-accent); transition: opacity .15s; }
        .{{ $uid }} .fcm-submit:hover { opacity: .9; }
        .{{ $uid }} .fcm-cancel { padding: 0; border: 0; background: none; cursor: pointer; font-size: 13px; color: var(--fcm-meta); }
        .{{ $uid }} .fcm-who { margin: 0; font-size: 13px; color: var(--fcm-meta); }
        .{{ $uid }} .fcm-who strong { color: var(--fcm-name); }
        .{{ $uid }} .fcm-hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    </style>

    {{-- The address a posted comment returns to. --}}
    <span id="comments" style="display:block;position:relative;top:-100px;visibility:hidden;"></span>

    @if($title !== '')
        <h2 class="fcm-title">{{ $title }}@if($showCount) <span class="fcm-count">({{ $total }})</span>@endif</h2>
    @endif

    @if($notice)
        <div class="fcm-notice" role="status">{{ $notice }}</div>
    @endif

    @if($comments->isEmpty())
        @if($emptyText !== '')<p class="fcm-empty">{{ $emptyText }}</p>@endif
    @else
        <ol class="fcm-list">
            @foreach($comments as $comment)
                <li class="fcm-item{{ ($s['cardBg'] ?? '') !== '' ? ' has-card' : '' }}" id="comment-{{ $comment->id }}">
                    @if($showAvatars)<div class="fcm-avatar" aria-hidden="true">{{ $initial($comment->name) }}</div>@endif
                    <div class="fcm-main">
                        <div class="fcm-head">
                            <span class="fcm-name">{{ $comment->name }}</span>
                            @if($showDates && $comment->created_at)
                                <time class="fcm-date" datetime="{{ $comment->created_at->toAtomString() }}" title="{{ $comment->created_at->format('M j, Y g:i a') }}">{{ $comment->created_at->diffForHumans() }}</time>
                            @endif
                        </div>
                        <p class="fcm-body">{{ $comment->comment }}</p>

                        @if($allowReplies && $showForm)
                            <button type="button" class="fcm-reply-btn" data-fcm-reply="{{ $uid }}-r{{ $comment->id }}" aria-expanded="false">Reply</button>
                        @endif

                        @if($comment->replies->isNotEmpty())
                            <ol class="fcm-replies">
                                @foreach($comment->replies as $reply)
                                    <li class="fcm-item" id="comment-{{ $reply->id }}">
                                        @if($showAvatars)<div class="fcm-avatar" aria-hidden="true">{{ $initial($reply->name) }}</div>@endif
                                        <div class="fcm-main">
                                            <div class="fcm-head">
                                                <span class="fcm-name">{{ $reply->name }}</span>
                                                @if($showDates && $reply->created_at)
                                                    <time class="fcm-date" datetime="{{ $reply->created_at->toAtomString() }}" title="{{ $reply->created_at->format('M j, Y g:i a') }}">{{ $reply->created_at->diffForHumans() }}</time>
                                                @endif
                                            </div>
                                            <p class="fcm-body">{{ $reply->comment }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @if($allowReplies && $showForm)
                            {{-- One level of replies, as the theme's own comments: a reply to a reply joins its thread. --}}
                            <form id="{{ $uid }}-r{{ $comment->id }}" class="fcm-form fcm-reply-form" action="{{ route('frontend.comment.store') }}" method="POST" hidden>
                                @include('falcon-cms::frontend.builder.elements.partials.comment-fields', ['cPost' => $cPost, 'parentId' => $comment->id, 'uid' => $uid . '-r' . $comment->id, 'placeholder' => 'Reply to ' . $comment->name . '…', 'buttonText' => 'Post Reply', 'cancel' => true, 'rows' => 3])
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if($showForm)
        <div class="fcm-respond">
            @if($formTitle !== '')<h3 class="fcm-form-title">{{ $formTitle }}</h3>@endif
            @if($hasErrors || $refusal)
                <div class="fcm-errors" role="alert"><ul>
                    @if($refusal)<li>{{ $refusal }}</li>@endif
                    @if($hasErrors)@foreach($errorBag->all() as $message)<li>{{ $message }}</li>@endforeach @endif
                </ul></div>
            @endif
            <form class="fcm-form" action="{{ route('frontend.comment.store') }}" method="POST">
                @include('falcon-cms::frontend.builder.elements.partials.comment-fields', ['cPost' => $cPost, 'parentId' => null, 'uid' => $uid, 'placeholder' => '', 'buttonText' => $buttonText, 'cancel' => false, 'rows' => 6])
            </form>
        </div>
    @endif
</div>

{{-- One script for every comments element, guarded by a window flag rather than @once (the
     Content element pre-renders a post's layout in a pass that would spend @once). --}}
<script>
(function () {
    if (window.__falconComments) return;
    window.__falconComments = true;
    document.addEventListener('click', function (ev) {
        var t = ev.target;
        var open = t.closest ? t.closest('[data-fcm-reply]') : null;
        if (open) {
            var form = document.getElementById(open.getAttribute('data-fcm-reply'));
            if (!form) return;
            form.hidden = !form.hidden;
            open.setAttribute('aria-expanded', form.hidden ? 'false' : 'true');
            if (!form.hidden) { var box = form.querySelector('.fcm-input'); /* not a hidden spam trap */ if (box) box.focus(); }
            return;
        }
        var cancel = t.closest ? t.closest('[data-fcm-cancel]') : null;
        if (cancel) {
            var f = cancel.closest('form');
            if (f) { f.hidden = true; var b = document.querySelector('[data-fcm-reply="' + f.id + '"]'); if (b) { b.setAttribute('aria-expanded', 'false'); b.focus(); } }
        }
    });
})();
</script>
@endif
