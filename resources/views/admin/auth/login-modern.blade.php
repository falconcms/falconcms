<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ get_cms_option('site_title', 'Lazy CMS') }}</title>
    <link href="{{ asset('vendor/cms-dashboard/css/inter.css') }}" rel="stylesheet">
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

        .form-head { margin-bottom: 1.75rem; }
        .form-head h1 { font-size: 1.45rem; font-weight: 800; color: #111827; letter-spacing: -.025em; margin-bottom: 4px; }
        .form-head p  { font-size: .875rem; color: #6b7280; }

        .a-error   { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 11px 14px; font-size: .84rem; color: #b91c1c; display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; }
        .a-success { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 11px 14px; font-size: .84rem; color: #166534; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

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
        .btn-submit:disabled { background: #93c5fd; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-submit:active { transform: translateY(0); }

        .remember-row { display: flex; align-items: center; gap: 8px; font-size: .875rem; color: #374151; cursor: pointer; }
        .remember-row input { width: 15px; height: 15px; accent-color: #0091ea; cursor: pointer; }

        .form-link { color: #0091ea; text-decoration: none; font-weight: 600; }
        .form-link:hover { color: #007bc7; }

        .email-status { font-size: .76rem; font-weight: 600; min-height: 14px; margin-top: 5px; }
    </style>
</head>
<body>
<div class="form-wrap">

    <div class="form-logo">
        @if(get_cms_option('theme_site_logo'))
            <img src="{{ get_cms_option('theme_site_logo') }}" alt="{{ get_cms_option('site_title', 'Lazy CMS') }}">
        @else
            <span class="form-logo-text">{{ get_cms_option('site_title', 'Lazy') }}<span class="form-logo-accent"> CMS</span></span>
        @endif
    </div>

    <div class="form-card">

@php $magicEnabled = get_cms_option('magic_login_enabled'); @endphp

@if($magicEnabled && session('magic_sent'))
        <div class="form-head">
            <h1>Check your inbox</h1>
            <p>A magic sign-in link has been sent. Click it to access your account — it expires in 10 minutes.</p>
        </div>
        <div class="a-success">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Magic link sent successfully.
        </div>
        <p style="font-size:.84rem;color:#6b7280">
            Didn't receive it? <a href="{{ route('admin.login') }}" class="form-link">Try again</a>
        </p>

@elseif($magicEnabled)
        <div class="form-head">
            <h1>Welcome back</h1>
            <p>Enter your email and we'll send you a magic sign-in link</p>
        </div>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.magic.request') }}" method="POST" style="display:flex;flex-direction:column;gap:1rem">
            @csrf
            <div>
                <div class="lf-field-wrap">
                    <input type="email" name="email" id="a_email" placeholder=" " class="lf-input" required autofocus
                           value="{{ old('email') }}"
                           pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                           autocomplete="email">
                    <label class="lf-float-label" for="a_email">Email address</label>
                </div>
                <div class="email-status" id="email-status"></div>
            </div>
            <button type="submit" id="magic-btn" class="btn-submit" disabled>Send Magic Link</button>
        </form>

@else
        <div class="form-head">
            <h1>Welcome back</h1>
            <p>Sign in to your account to continue</p>
        </div>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.login') }}" method="POST" style="display:flex;flex-direction:column;gap:1rem">
            @csrf
            <div class="lf-field-wrap">
                <input type="email" name="email" id="a_email" placeholder=" " class="lf-input" required autofocus value="{{ old('email') }}">
                <label class="lf-float-label" for="a_email">Email address</label>
            </div>
            <div>
                <div class="lf-field-wrap">
                    <input type="password" name="password" id="a_password" placeholder=" " class="lf-input has-right-icon" required>
                    <label class="lf-float-label" for="a_password">Password</label>
                    <button type="button" class="toggle-password" data-target="a_password" tabindex="-1">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div style="text-align:right;margin-top:6px">
                    <a href="{{ route('admin.password.request') }}" class="form-link" style="font-size:.78rem">Forgot password?</a>
                </div>
            </div>
            <label class="remember-row">
                <input type="checkbox" name="remember">
                Remember me
            </label>
            <button type="submit" class="btn-submit">Sign In</button>
        </form>
@endif

    </div>{{-- /form-card --}}

    @if(get_cms_option('users_can_register', '0') == '1')
    <p style="text-align:center;margin-top:1.25rem;font-size:.875rem;color:#6b7280">
        Don't have an account? <a href="{{ route('admin.register') }}" class="form-link">Create one</a>
    </p>
    @endif

</div>{{-- /form-wrap --}}

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

@if(get_cms_option('magic_login_enabled') && !session('magic_sent'))
    (function() {
        var emailInp  = document.getElementById('a_email');
        var statusEl  = document.getElementById('email-status');
        var magicBtn  = document.getElementById('magic-btn');
        if (!emailInp) return;
        var timer = null;
        var csrf  = document.querySelector('meta[name="csrf-token"]').content;

        function isValid(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); }

        function checkEmail(email) {
            statusEl.textContent = 'Checking...'; statusEl.style.color = '#9ca3af';
            magicBtn.disabled = true;
            fetch('{{ route("shop.magic.email.check") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ email: email })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.exists) { statusEl.textContent = '✓ Account found'; statusEl.style.color = '#16a34a'; magicBtn.disabled = false; }
                else { statusEl.textContent = '✗ No account found with this email'; statusEl.style.color = '#dc2626'; magicBtn.disabled = true; }
            }).catch(function() { statusEl.textContent = ''; magicBtn.disabled = false; });
        }

        emailInp.addEventListener('input', function() {
            clearTimeout(timer); var val = this.value.trim();
            statusEl.textContent = ''; magicBtn.disabled = true;
            if (!val) return;
            if (!isValid(val)) { statusEl.textContent = 'Please enter a valid email'; statusEl.style.color = '#dc2626'; return; }
            timer = setTimeout(function() { checkEmail(val); }, 400);
        });
        if (emailInp.value.trim() && isValid(emailInp.value.trim())) checkEmail(emailInp.value.trim());
    })();
@endif
})();
</script>
</body>
</html>
