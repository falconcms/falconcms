<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — {{ get_cms_option('site_title', 'FalconCMS') }}</title>
    <link href="{{ asset('vendor/falcon-cms/css/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/falcon-cms/css/funny-fonts.css') }}" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 1.5rem;
            background: #f0f4f8;
            background-image:
                radial-gradient(ellipse at 15% 15%, rgba(0,145,234,.12) 0%, transparent 45%),
                radial-gradient(ellipse at 85% 85%, rgba(248,58,58,.08) 0%, transparent 45%),
                radial-gradient(ellipse at 80% 10%, rgba(255,184,0,.07) 0%, transparent 35%);
        }

        .funny-card { background: #fff; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,.1), 0 1px 3px rgba(0,0,0,.06); width: 100%; max-width: 420px; padding: 2.5rem; text-align: center; }

        .card-logo { margin-bottom: 1.5rem; }
        .card-logo img { height: 40px; object-fit: contain; }
        .card-logo-text { font-size: 1.25rem; font-weight: 800; color: #111827; letter-spacing: -.02em; }
        .card-logo-accent { color: #0091ea; }

        .funny-title { font-family: 'Jua', sans-serif; font-size: 2rem; color: #111827; letter-spacing: -.01em; margin-bottom: 4px; }
        .funny-sub { font-size: .875rem; color: #6b7280; margin-bottom: 1.75rem; }

        .a-error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 11px 14px; font-size: .84rem; color: #b91c1c; display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; text-align: left; }
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

        .remember-row { display: flex; align-items: center; gap: 8px; font-size: .875rem; color: #374151; cursor: pointer; }
        .remember-row input { width: 15px; height: 15px; accent-color: #0091ea; cursor: pointer; }

        /* Funny dancing button */
        .btn-funny-area { height: 90px; position: relative; display: flex; align-items: center; justify-content: center; }
        #login-btn {
            background: linear-gradient(135deg, #f83a3a 0%, #ff007a 100%);
            color: #fff; border: none; padding: 13px 40px;
            font-size: 1rem; font-weight: 800; font-family: 'Outfit', 'Inter', sans-serif;
            border-radius: 12px; cursor: pointer;
            box-shadow: 0 8px 24px rgba(248,58,58,.3);
            white-space: nowrap;
            transition: transform .5s cubic-bezier(.175,.885,.32,1.275), background .3s;
            position: relative; z-index: 100; will-change: transform;
        }

        /* Magic button */
        .btn-magic { background: linear-gradient(135deg, #0091ea 0%, #6366f1 100%); color: #fff; border: none; padding: 13px; border-radius: 10px; font-weight: 700; font-size: .9rem; font-family: inherit; width: 100%; cursor: pointer; transition: opacity .2s; }
        .btn-magic:disabled { opacity: .45; cursor: not-allowed; }
        .btn-magic:not(:disabled):hover { opacity: .88; }

        .email-status { font-size: .76rem; font-weight: 600; min-height: 14px; margin-top: 5px; text-align: left; }
        .form-link { color: #0091ea; text-decoration: none; font-weight: 600; }
        .form-link:hover { color: #007bc7; }
    </style>
</head>
<body>
    <div class="funny-card">

        <div class="card-logo">
            @if(get_cms_option('theme_site_logo'))
                <img src="{{ get_cms_option('theme_site_logo') }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}">
            @else
                <span class="card-logo-text">{{ get_cms_option('site_title', 'Lazy') }}<span class="card-logo-accent"> CMS</span></span>
            @endif
        </div>

@php $magicEnabled = get_cms_option('magic_login_enabled'); @endphp

@if($magicEnabled && session('magic_sent'))
        {{-- ── Magic link sent ── --}}
        <h1 class="funny-title">CHECK INBOX</h1>
        <p class="funny-sub">Your magic link is on its way</p>
        <div class="a-success">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Magic link sent! It expires in 10 minutes.
        </div>
        <p style="font-size:.84rem;color:#6b7280">
            Didn't receive it? <a href="{{ route('admin.login') }}" class="form-link">Try again</a>
        </p>

@elseif($magicEnabled)
        {{-- ── Magic login form ── --}}
        <h1 class="funny-title">WELCOME BACK</h1>
        <p class="funny-sub">Enter your email to get a magic sign-in link</p>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.magic.request') }}" method="POST" style="display:flex;flex-direction:column;gap:1rem;text-align:left">
            @csrf
            <div>
                <div class="lf-field-wrap">
                    <input type="email" id="email" name="email" placeholder=" " class="lf-input" required autofocus
                           value="{{ old('email') }}"
                           pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                           autocomplete="email">
                    <label class="lf-float-label" for="email">Email address</label>
                </div>
                <div class="email-status" id="email-status"></div>
            </div>
            <button type="submit" id="magic-btn" class="btn-magic" disabled>Send Magic Link</button>
        </form>

@else
        {{-- ── Standard password login ── --}}
        <h1 class="funny-title">WELCOME BACK</h1>
        <p class="funny-sub">Securely enter your portal</p>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.login') }}" method="POST" id="funny-form" style="display:flex;flex-direction:column;gap:1rem;text-align:left">
            @csrf
            <div class="lf-field-wrap">
                <input type="email" id="email" name="email" placeholder=" " class="lf-input" required autocomplete="off" value="{{ old('email') }}">
                <label class="lf-float-label" for="email">Email address</label>
            </div>
            <div>
                <div class="lf-field-wrap">
                    <input type="password" id="password" name="password" placeholder=" " class="lf-input has-right-icon" required>
                    <label class="lf-float-label" for="password">Password</label>
                    <button type="button" class="toggle-password" data-target="password" tabindex="-1">
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
            <div class="btn-funny-area">
                <button type="submit" id="login-btn">Unlock Portal</button>
            </div>
        </form>
@endif

        @if(get_cms_option('users_can_register', '0') == '1')
        <p style="margin-top:1.25rem;font-size:.875rem;color:#6b7280;text-align:center">
            Don't have an account?
            <a href="{{ route('admin.register') }}" class="form-link">Create one</a>
        </p>
        @endif

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

@if(!get_cms_option('magic_login_enabled'))
        // Dancing button (only in standard login mode)
        var btn        = document.getElementById('login-btn');
        var emailInput = document.getElementById('email');
        var pwdInput   = document.getElementById('password');
        var isValidCreds = false;

        async function verify() {
            var email = emailInput.value, password = pwdInput.value;
            if (email.length < 5 || password.length < 3) { isValidCreds = false; return; }
            try {
                var res  = await fetch("{{ route('admin.login.check') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ email: email, password: password })
                });
                var data = await res.json();
                isValidCreds = data.valid;
                if (isValidCreds) { btn.style.transform = 'translate(0,0)'; btn.textContent = 'Unlock Portal'; btn.style.background = 'linear-gradient(135deg,#f83a3a 0%,#ff007a 100%)'; }
            } catch (e) {}
        }

        emailInput.addEventListener('input', function () { isValidCreds = false; verify(); });
        pwdInput.addEventListener('input',   function () { isValidCreds = false; verify(); });

        document.addEventListener('mousemove', function (e) {
            if (isValidCreds) return;
            var rect = btn.getBoundingClientRect();
            var bx = rect.left + rect.width / 2, by = rect.top + rect.height / 2;
            var dist = Math.sqrt(Math.pow(e.clientX - bx, 2) + Math.pow(e.clientY - by, 2));
            if (dist < 110) {
                var angle = Math.atan2(e.clientY - by, e.clientX - bx) + Math.PI;
                btn.style.transform = 'translate(' + (Math.cos(angle) * 150) + 'px,' + (Math.sin(angle) * 150) + 'px)';
                btn.textContent = "Don't Cheat!";
                btn.style.background = '#fbbf24';
            }
        });
@endif

@if(get_cms_option('magic_login_enabled') && !session('magic_sent'))
        // Real-time email check for magic login
        var emailInp   = document.getElementById('email');
        var statusEl   = document.getElementById('email-status');
        var magicBtn   = document.getElementById('magic-btn');
        var emailTimer = null;
        var csrfToken  = document.querySelector('meta[name="csrf-token"]').content;

        function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); }

        function checkEmail(email) {
            statusEl.textContent = 'Checking...';
            statusEl.style.color = '#9ca3af';
            magicBtn.disabled = true;

            fetch("{{ route('shop.magic.email.check') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ email: email })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.exists) {
                    statusEl.textContent = '✓ Account found — ready to send magic link';
                    statusEl.style.color = '#16a34a';
                    magicBtn.disabled = false;
                } else {
                    statusEl.textContent = '✗ No account found with this email';
                    statusEl.style.color = '#dc2626';
                    magicBtn.disabled = true;
                }
            })
            .catch(function() { statusEl.textContent = ''; magicBtn.disabled = false; });
        }

        if (emailInp) {
            emailInp.addEventListener('input', function() {
                clearTimeout(emailTimer);
                var val = this.value.trim();
                statusEl.textContent = '';
                magicBtn.disabled = true;
                if (!val) return;
                if (!isValidEmail(val)) {
                    statusEl.textContent = 'Please enter a valid email address';
                    statusEl.style.color = '#dc2626';
                    return;
                }
                emailTimer = setTimeout(function() { checkEmail(val); }, 400);
            });
            if (emailInp.value.trim() && isValidEmail(emailInp.value.trim())) {
                checkEmail(emailInp.value.trim());
            }
        }
@endif
    })();
    </script>
</body>
</html>
