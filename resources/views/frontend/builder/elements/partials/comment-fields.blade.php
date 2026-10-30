{{-- The fields of a comment form (the Comments element's main form and its reply forms).
     $cPost, $parentId, $uid (unique id prefix), $placeholder, $buttonText, $cancel, $rows. --}}
@csrf
<input type="hidden" name="post_id" value="{{ $cPost->id }}">
@if($parentId)<input type="hidden" name="parent_id" value="{{ $parentId }}">@endif
{{-- Left empty by people; a bot that fills every field is turned away. --}}
<div class="fcm-hp" aria-hidden="true">
    <label for="{{ $uid }}-website">Website</label>
    <input type="text" id="{{ $uid }}-website" name="website" tabindex="-1" autocomplete="off">
</div>

@guest
    <div class="fcm-row">
        <div>
            <label class="fcm-label" for="{{ $uid }}-name">Name <span aria-hidden="true">*</span></label>
            <input class="fcm-input" type="text" id="{{ $uid }}-name" name="name" required maxlength="255" autocomplete="name" value="{{ $parentId ? '' : old('name') }}">
        </div>
        <div>
            <label class="fcm-label" for="{{ $uid }}-email">Email <span aria-hidden="true">*</span></label>
            <input class="fcm-input" type="email" id="{{ $uid }}-email" name="email" required maxlength="255" autocomplete="email" value="{{ $parentId ? '' : old('email') }}">
        </div>
    </div>
@else
    <p class="fcm-who">Logged in as <strong>{{ auth()->user()->name }}</strong></p>
@endguest

<div>
    <label class="fcm-label" for="{{ $uid }}-comment">{{ $parentId ? 'Reply' : 'Comment' }} <span aria-hidden="true">*</span></label>
    <textarea class="fcm-input" id="{{ $uid }}-comment" name="comment" rows="{{ $rows }}" required minlength="3" maxlength="3000" placeholder="{{ $placeholder }}">{{ $parentId ? '' : old('comment') }}</textarea>
</div>

<div class="fcm-actions">
    <button type="submit" class="fcm-submit">{{ $buttonText }}</button>
    @if($cancel)<button type="button" class="fcm-cancel" data-fcm-cancel>Cancel</button>@endif
</div>
