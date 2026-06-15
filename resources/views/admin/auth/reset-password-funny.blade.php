<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password — {{ get_cms_option('site_title', 'FalconCMS') }}</title>
    <link href="{{ asset('vendor/falcon-cms/css/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/falcon-cms/css/funny-fonts.css') }}" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #f0f4f8;
            background-image:
                radial-gradient(ellipse at 15% 15%, rgba(0,145,234,.12) 0%, transparent 45%),
                radial-gradient(ellipse at 85% 85%, rgba(248,58,58,.08) 0%, transparent 45%),
                radial-gradient(ellipse at 80% 10%, rgba(255,184,0,.07) 0%, transparent 35%);
        }

        .funny-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.1), 0 1px 3px rgba(0,0,0,.06);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            text-align: center;
        }

        .card-logo { margin-bottom: 1.5rem; }
        .card-logo img { height: 40px; object-fit: contain; }
        .card-logo-text { font-size: 1.25rem; font-weight: 800; color: #111827; letter-spacing: -.02em; }
        .card-logo-accent { color: #0091ea; }

        .icon-wrap {
            width: 52px; height: 52px; border-radius: 14px;
            background: #e0f3fd; display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }

        .funny-title { font-family: 'Jua', sans-serif; font-size: 1.9rem; color: #111827; letter-spacing: -.01em; margin-bottom: 6px; }
        .funny-sub { font-size: .875rem; color: #6b7280; margin-bottom: 1.75rem; }

        .a-error {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
            padding: 11px 14px; font-size: .84rem; color: #b91c1c;
            display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; text-align: left;
        }

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

        .toggle-password {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af;
            padding: 0; line-height: 0; z-index: 10;
        }
        .toggle-password:hover { color: #6b7280; }

        .strength-bar-track { height: 3px; background: #e5e7eb; border-radius: 99px; overflow: hidden; margin-top: 8px; }
        .strength-bar-fill  { height: 100%; width: 0; transition: width .3s, background-color .3s; border-radius: 99px; }

        /* Funny button area */
        .btn-funny-area { height: 90px; position: relative; display: flex; align-items: center; justify-content: center; }

        #reset-btn {
            background: linear-gradient(135deg, #f83a3a 0%, #ff007a 100%);
            color: #fff;
            border: none;
            padding: 13px 40px;
            font-size: 1rem;
            font-weight: 800;
            font-family: 'Outfit', 'Inter', sans-serif;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(248,58,58,.3);
            white-space: nowrap;
            transition: transform .5s cubic-bezier(.175,.885,.32,1.275), background .3s;
            position: relative;
            z-index: 100;
            will-change: transform;
        }
    </style>
</head>
<body>
    <div class="funny-card">

        <div class="card-logo">
            @if(get_cms_option('theme_site_logo'))
                <img src="{{ get_cms_option('theme_site_logo') }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}">
            @else
                <span class="card-logo-text">{{ get_cms_option('site_title', 'Falcon') }}<span class="card-logo-accent"> CMS</span></span>
            @endif
        </div>

        <div class="icon-wrap">
            <svg width="24" height="24" fill="none" stroke="#0091ea" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>

        <h1 class="funny-title">NEW PASSWORD</h1>
        <p class="funny-sub">Choose a strong password for your account.</p>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.password.update') }}" method="POST" style="display:flex;flex-direction:column;gap:1rem;text-align:left">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="lf-field-wrap">
                <input type="email" name="email" id="a_email" placeholder=" " class="lf-input" required autofocus value="{{ old('email', $email ?? '') }}">
                <label class="lf-float-label" for="a_email">Email address</label>
            </div>

            <div>
                <div class="lf-field-wrap">
                    <input type="password" name="password" id="a_password" placeholder=" " class="lf-input has-right-icon" required>
                    <label class="lf-float-label" for="a_password">New password</label>
                    <button type="button" class="toggle-password" data-target="a_password" tabindex="-1">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div class="strength-bar-track"><div class="strength-bar-fill" id="strength-bar"></div></div>
                <div style="font-size:.72rem;font-weight:800;min-height:14px;margin-top:4px" id="strength-text"></div>
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

            <div class="btn-funny-area">
                <button type="submit" id="reset-btn">Update Password</button>
            </div>
        </form>

    </div>

    <script>
    (function () {
        // Floating labels
        document.querySelectorAll('.lf-field-wrap').forEach(function (wrap) {
            var inp = wrap.querySelector('.lf-input');
            if (!inp) return;
            function update() { wrap.classList.toggle('lf-filled', inp.value.trim() !== ''); }
            inp.addEventListener('focus', function () { wrap.classList.add('lf-focused'); });
            inp.addEventListener('blur',  function () { wrap.classList.remove('lf-focused'); update(); });
            inp.addEventListener('input', update);
            update();
        });

        // Password toggle
        document.querySelectorAll('.toggle-password').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var inp = document.getElementById(this.dataset.target);
                inp.type = inp.type === 'password' ? 'text' : 'password';
                this.style.color = inp.type === 'text' ? '#0091ea' : '#9ca3af';
            });
        });

        // Password strength + match checker
        var pwd          = document.getElementById('a_password');
        var pwd2         = document.getElementById('a_password2');
        var msg          = document.getElementById('match-msg');
        var strengthBar  = document.getElementById('strength-bar');
        var strengthText = document.getElementById('strength-text');

        function checkStrength() {
            var val = pwd.value, score = 0;
            if (val.length > 6)    score++;
            if (/[0-9]/.test(val)) score++;
            if (/[A-Z]/.test(val)) score++;
            if (!val.length) {
                strengthBar.style.width = '0'; strengthText.textContent = '';
            } else if (score <= 1) {
                strengthBar.style.width = '33%'; strengthBar.style.backgroundColor = '#ef4444';
                strengthText.textContent = 'WEAK'; strengthText.style.color = '#ef4444';
            } else if (score === 2) {
                strengthBar.style.width = '66%'; strengthBar.style.backgroundColor = '#f59e0b';
                strengthText.textContent = 'GOOD'; strengthText.style.color = '#f59e0b';
            } else {
                strengthBar.style.width = '100%'; strengthBar.style.backgroundColor = '#10b981';
                strengthText.textContent = 'STRONG!'; strengthText.style.color = '#10b981';
            }
        }

        function checkMatch() {
            if (!pwd2.value.length) { msg.textContent = ''; return; }
            if (pwd.value === pwd2.value) { msg.textContent = 'Passwords match'; msg.style.color = '#10b981'; }
            else { msg.textContent = 'Passwords do not match'; msg.style.color = '#ef4444'; }
        }
        pwd.addEventListener('input',  function () { checkStrength(); checkMatch(); });
        pwd2.addEventListener('input', checkMatch);

        // Dancing button — flees until email exists + passwords match
        var btn        = document.getElementById('reset-btn');
        var emailInput = document.getElementById('a_email');
        var emailExists = false;
        var pwdsOk = false;

        async function checkEmail() {
            var email = emailInput.value;
            if (email.length < 5) { emailExists = false; return; }
            try {
                var res  = await fetch("{{ route('admin.email.check') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ email: email })
                });
                var data = await res.json();
                emailExists = !!data.exists;
            } catch (e) {}
        }

        function isFormReady() { return emailExists && pwdsOk; }

        // Re-check match whenever password fields change
        function recheckPwds() {
            pwdsOk = pwd.value.length >= 4 && pwd.value === pwd2.value;
        }
        pwd.addEventListener('input',  recheckPwds);
        pwd2.addEventListener('input', recheckPwds);

        emailInput.addEventListener('input', function () { emailExists = false; checkEmail(); });

        // Check on load since email is pre-filled from the reset link
        if (emailInput.value.length >= 5) checkEmail();

        document.addEventListener('mousemove', function (e) {
            if (isFormReady()) {
                btn.style.transform = 'translate(0,0)';
                btn.textContent = 'Update Password';
                btn.style.background = 'linear-gradient(135deg,#f83a3a 0%,#ff007a 100%)';
                return;
            }
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
    })();
    </script>
</body>
</html>
