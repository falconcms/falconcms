<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — {{ get_cms_option('site_title', 'FalconCMS') }}</title>
    <link href="{{ asset('vendor/falcon-cms/css/inter.css') }}" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f3f4f6; padding: 2rem 1rem;
        }
        .form-wrap { width: 100%; max-width: 380px; }

        .form-logo { text-align: center; margin-bottom: 2rem; }
        .form-logo img { height: 36px; object-fit: contain; }
        .form-logo-text { font-size: 1.25rem; font-weight: 800; color: #111827; letter-spacing: -.02em; }
        .form-logo-accent { color: #0091ea; }

        .form-card { background: #fff; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.05); padding: 2.5rem 2rem; }

        .icon-wrap { width: 52px; height: 52px; border-radius: 14px; background: #e0f3fd; display: flex; align-items: center; justify-content: center; margin-bottom: 18px; }
        .form-head { margin-bottom: 1.75rem; }
        .form-head h1 { font-size: 1.45rem; font-weight: 800; color: #111827; letter-spacing: -.025em; margin-bottom: 4px; }
        .form-head p  { font-size: .875rem; color: #6b7280; }

        .a-error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 11px 14px; font-size: .84rem; color: #b91c1c; display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; }

        .lf-field-wrap { position: relative; }
        .lf-input {
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            padding: 1.25rem 14px .45rem; width: 100%;
            font-size: .9rem; font-family: inherit; color: #111827;
            background: #fafafa; display: block;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .lf-input:focus { outline: none; border-color: #0091ea; background: #fff; box-shadow: 0 0 0 3px rgba(0,145,234,.1); }
        .lf-input.has-right-icon { padding-right: 44px; }
        .lf-float-label {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            pointer-events: none; color: #9ca3af; font-size: .875rem; font-weight: 500; line-height: 1;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 28px); z-index: 1;
            transition: top .15s, transform .15s, color .15s, background-color .15s;
        }
        .lf-field-wrap.lf-focused .lf-float-label,
        .lf-field-wrap.lf-filled  .lf-float-label {
            top: 0; transform: translateY(-50%) scale(.78); transform-origin: left center;
            padding: 0 3px; background: #fff; color: #374151; max-width: none; overflow: visible;
        }
        .lf-field-wrap.lf-focused .lf-float-label { color: #0091ea; }

        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; line-height: 0; z-index: 10; }
        .toggle-password:hover { color: #6b7280; }

        .btn-submit { background: #0091ea; color: #fff; padding: 13px; border: none; border-radius: 10px; font-weight: 700; font-size: .9rem; font-family: inherit; width: 100%; cursor: pointer; letter-spacing: .01em; transition: background .2s, transform .1s, box-shadow .2s; }
        .btn-submit:hover { background: #007bc7; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,145,234,.3); }
        .btn-submit:active { transform: translateY(0); }
    </style>
</head>
<body>
<div class="form-wrap">

    <div class="form-logo">
        <img src="{{ get_cms_option('theme_site_logo', asset('vendor/falcon-cms/images/falcon-cms-logo.png')) }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}">
    </div>

    <div class="form-card">
        <div class="form-head">
            <div class="icon-wrap">
                <svg width="24" height="24" fill="none" stroke="#0091ea" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h1>Set new password</h1>
            <p>Choose a strong password for your account.</p>
        </div>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.password.update') }}" method="POST" style="display:flex;flex-direction:column;gap:1rem">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="lf-field-wrap">
                <input type="email" name="email" id="a_email" placeholder=" " class="lf-input" required autofocus value="{{ old('email', $email ?? '') }}">
                <label class="lf-float-label" for="a_email">Email address</label>
            </div>

            <div class="lf-field-wrap">
                <input type="password" name="password" id="a_password" placeholder=" " class="lf-input has-right-icon" required>
                <label class="lf-float-label" for="a_password">New password</label>
                <button type="button" class="toggle-password" data-target="a_password" tabindex="-1">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>

            <div>
                <div class="lf-field-wrap">
                    <input type="password" name="password_confirmation" id="a_password2" placeholder=" " class="lf-input has-right-icon" required>
                    <label class="lf-float-label" for="a_password2">Confirm new password</label>
                    <button type="button" class="toggle-password" data-target="a_password2" tabindex="-1">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div style="font-size:.78rem;font-weight:600;min-height:16px;margin-top:5px" id="match-msg"></div>
            </div>

            <button type="submit" class="btn-submit">Update Password</button>
        </form>
    </div>

</div>

<script>
(function () {
    document.querySelectorAll('.lf-field-wrap').forEach(function (wrap) {
        var inp = wrap.querySelector('.lf-input');
        if (!inp) return;
        function update() { wrap.classList.toggle('lf-filled', inp.value.trim() !== ''); }
        inp.addEventListener('focus', function () { wrap.classList.add('lf-focused'); });
        inp.addEventListener('blur',  function () { wrap.classList.remove('lf-focused'); update(); });
        inp.addEventListener('input', update);
        update();
    });
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(this.dataset.target);
            inp.type = inp.type === 'password' ? 'text' : 'password';
            this.style.color = inp.type === 'text' ? '#0091ea' : '#9ca3af';
        });
    });
    var pwd  = document.getElementById('a_password');
    var pwd2 = document.getElementById('a_password2');
    var msg  = document.getElementById('match-msg');
    function checkMatch() {
        if (!pwd2.value.length) { msg.textContent = ''; return; }
        if (pwd.value === pwd2.value) { msg.textContent = 'Passwords match'; msg.style.color = '#10b981'; }
        else { msg.textContent = 'Passwords do not match'; msg.style.color = '#ef4444'; }
    }
    pwd.addEventListener('input', checkMatch);
    pwd2.addEventListener('input', checkMatch);
})();
</script>
</body>
</html>

