{{--
    One person edits an item at a time. Included by every editor: classic posts, products and custom
    post types, pages, and the builder. Styled inline because the builder has its own stylesheet.
--}}
@php
    $__lockId = (int) $post->id;
    $__holder = \FalconCms\Core\Support\PostLock::ready()
        ? \FalconCms\Core\Support\PostLock::acquire($__lockId, (int) auth()->id())
        : null;
    $__lockOn = \FalconCms\Core\Support\PostLock::ready();
    $__backUrl = $post->type === 'page'
        ? route('admin.pages.index')
        : route('admin.posts.index', ['type' => $post->type]);
@endphp
@if($__lockOn)
<div id="falcon-edit-lock" style="display:{{ $__holder ? 'flex' : 'none' }};position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;padding:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
    <div role="alertdialog" aria-modal="true" aria-labelledby="falcon-edit-lock-title" style="background:#fff;border-radius:6px;max-width:440px;width:100%;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,.35);color:#1d2327">
        <h2 id="falcon-edit-lock-title" style="margin:0 0 8px;font-size:18px;font-weight:600;line-height:1.3"></h2>
        <p id="falcon-edit-lock-text" style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#50575e"></p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end">
            <button type="button" id="falcon-lock-stay" style="display:none;padding:7px 14px;font-size:13px;background:#fff;border:1px solid #8c8f94;border-radius:3px;color:#1d2327;cursor:pointer">Stay and copy my work</button>
            <a id="falcon-lock-back" href="{{ $__backUrl }}" style="padding:7px 14px;font-size:13px;background:#fff;border:1px solid #2271b1;border-radius:3px;color:#2271b1;text-decoration:none">Go back</a>
            <button type="button" id="falcon-lock-take" style="padding:7px 14px;font-size:13px;background:#2271b1;border:1px solid #2271b1;border-radius:3px;color:#fff;cursor:pointer">Take over</button>
        </div>
    </div>
</div>
<div id="falcon-edit-lock-bar" style="display:none;position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#d63638;color:#fff;font:13px/1.4 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;padding:8px 16px;text-align:center"></div>
<script>
(function () {
    var lockUrl = @json(route('admin.posts.lock', $__lockId));
    var unlockUrl = @json(route('admin.posts.unlock', $__lockId));
    var token = @json(csrf_token());
    var everyMs = @json(\FalconCms\Core\Support\PostLock::HEARTBEAT_SECONDS * 1000);
    var modal = document.getElementById('falcon-edit-lock');
    var title = document.getElementById('falcon-edit-lock-title');
    var text = document.getElementById('falcon-edit-lock-text');
    var stay = document.getElementById('falcon-lock-stay');
    var take = document.getElementById('falcon-lock-take');
    var bar = document.getElementById('falcon-edit-lock-bar');

    // waiting: opened while somebody else holds it, so nothing here has been touched.
    // lost: was editing until somebody took over, so this page may hold unsaved work.
    var state = @json($__holder ? 'waiting' : 'editing');
    var holderName = @json($__holder['name'] ?? '');

    function render() {
        if (state === 'waiting') {
            title.textContent = 'Someone is editing this';
            text.textContent = holderName + ' is editing this now. Taking over stops their saves, and they may lose work they have not saved.';
            take.textContent = 'Take over';
            stay.style.display = 'none';
        } else if (state === 'lost') {
            title.textContent = holderName + ' took over';
            text.textContent = 'Your saves are switched off, because ' + holderName + ' is editing this now. Copy anything you want to keep, or take it back.';
            take.textContent = 'Take back';
            stay.style.display = '';
        }
        modal.style.display = (state === 'waiting' || state === 'lost') ? 'flex' : 'none';
    }

    function send(force) {
        var body = new FormData();
        body.append('_token', token);
        if (force) body.append('force', '1');

        return window.fetch(lockUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.ok ? r.json() : null; });
    }

    function beat() {
        send(false).then(function (d) {
            if (!d) return; // Signed out, or the network dropped: the next beat tries again.
            if (d.locked) {
                holderName = (d.holder && d.holder.name) || 'Another user';
                if (state === 'editing') {
                    state = 'lost';
                    render();
                }
                return;
            }
            // The other person left. Whoever was waiting reloads to see what they saved.
            if (state === 'waiting') {
                window.location.reload();
            } else if (state === 'lost' || state === 'copying') {
                state = 'editing';
                bar.style.display = 'none';
                render();
            }
        }).catch(function () {});
    }

    take.addEventListener('click', function () {
        take.disabled = true;
        send(true).then(function (d) {
            take.disabled = false;
            if (!d || d.locked) return;
            if (state === 'waiting') {
                window.location.reload();

                return;
            }
            state = 'editing';
            bar.style.display = 'none';
            render();
        }).catch(function () { take.disabled = false; });
    });

    stay.addEventListener('click', function () {
        state = 'copying';
        bar.textContent = holderName + ' is editing this. Your saves are switched off until they leave.';
        bar.style.display = 'block';
        render();
    });

    // Refuse here as well as on the server, so a click on Update does not reload away typed work.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if ((state === 'lost' || state === 'copying') && form && (form.method || '').toLowerCase() === 'post') {
            e.preventDefault();
            e.stopImmediatePropagation();
            bar.textContent = holderName + ' is editing this, so that was not saved. Copy your work, or take it back.';
            bar.style.display = 'block';
        }
    }, true);

    window.setInterval(beat, everyMs);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) beat(); });

    // Leaving frees it straight away, rather than after the lock lapses.
    window.addEventListener('pagehide', function () {
        if (state !== 'editing' || !navigator.sendBeacon) return;
        var body = new FormData();
        body.append('_token', token);
        navigator.sendBeacon(unlockUrl, body);
    });
    // Back to this page from the browser's memory: it no longer holds the lock.
    window.addEventListener('pageshow', function (e) { if (e.persisted) beat(); });

    render();
})();
</script>
@endif
