<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account — {{ get_cms_option('site_title', 'FalconCMS') }}</title>
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
            max-width: 440px;
            padding: 2.5rem;
            text-align: center;
        }

        .card-logo { margin-bottom: 1.5rem; }
        .card-logo img { height: 40px; object-fit: contain; }
        .card-logo-text { font-size: 1.25rem; font-weight: 800; color: #111827; letter-spacing: -.02em; }
        .card-logo-accent { color: #0091ea; }

        .funny-title { font-family: 'Jua', sans-serif; font-size: 2rem; color: #111827; letter-spacing: -.01em; margin-bottom: 4px; }
        .funny-sub { font-size: .875rem; color: #6b7280; margin-bottom: 1.75rem; }

        .a-error {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
            padding: 11px 14px; font-size: .84rem; color: #b91c1c;
            display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; text-align: left;
        }

        /* Floating label fields — identical to modern theme */
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

        /* Strength bar */
        .strength-bar-track { height: 3px; background: #e5e7eb; border-radius: 99px; overflow: hidden; margin-top: 8px; }
        .strength-bar-fill  { height: 100%; width: 0; transition: width .3s, background-color .3s; border-radius: 99px; }

        /* Email feedback tag */
        .email-tag { font-size: .72rem; font-weight: 800; text-transform: uppercase; min-height: 14px; margin-top: 4px; text-align: left; }

        /* Funny button area */
        .btn-funny-area { height: 90px; position: relative; display: flex; align-items: center; justify-content: center; }

        #register-btn {
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

        .form-link { color: #0091ea; text-decoration: none; font-weight: 600; }
        .form-link:hover { color: #007bc7; }
    </style>
</head>
<body>
    <div class="funny-card">

        <div class="card-logo">
            <img src="{{ get_cms_option('theme_site_logo', asset('vendor/falcon-cms/images/falcon-cms-logo.png')) }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}">
        </div>

        <h1 class="funny-title">JOIN THE TEAM</h1>
        <p class="funny-sub">Create your account to get started</p>

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.register') }}" method="POST" id="funny-form" style="display:flex;flex-direction:column;gap:1rem;text-align:left">
            @csrf

            <div class="lf-field-wrap">
                <input type="text" name="name" id="a_name" placeholder=" " class="lf-input" required autocomplete="off" value="{{ old('name') }}">
                <label class="lf-float-label" for="a_name">Full name</label>
            </div>

            <div>
                <div class="lf-field-wrap">
                    <input type="email" id="email" name="email" placeholder=" " class="lf-input" required autocomplete="off" value="{{ old('email') }}">
                    <label class="lf-float-label" for="email">Email address</label>
                </div>
                <div class="email-tag" id="email-feedback"></div>
            </div>

            <div>
                <div class="lf-field-wrap">
                    <input type="password" id="password" name="password" placeholder=" " class="lf-input has-right-icon" required>
                    <label class="lf-float-label" for="password">Password</label>
                    <button type="button" class="toggle-password" data-target="password" tabindex="-1">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div class="strength-bar-track"><div class="strength-bar-fill" id="strength-bar"></div></div>
                <div style="font-size:.72rem;font-weight:800;min-height:14px;margin-top:4px" id="strength-text"></div>
            </div>

            <div>
                <div class="lf-field-wrap">
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder=" " class="lf-input has-right-icon" required>
                    <label class="lf-float-label" for="password_confirmation">Confirm password</label>
                    <button type="button" class="toggle-password" data-target="password_confirmation" tabindex="-1">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <div style="font-size:.72rem;font-weight:800;min-height:14px;margin-top:4px" id="match-feedback"></div>
            </div>

            <div class="btn-funny-area">
                <button type="submit" id="register-btn">Create Account</button>
            </div>
        </form>

        <p style="margin-top:1.25rem;font-size:.875rem;color:#6b7280;text-align:center">
            Already have an account?
            <a href="{{ route('admin.login') }}" class="form-link">Sign in</a>
        </p>

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

        var btn             = document.getElementById('register-btn');
        var emailInput      = document.getElementById('email');
        var pwd             = document.getElementById('password');
        var confirmPwd      = document.getElementById('password_confirmation');
        var emailFeedback   = document.getElementById('email-feedback');
        var matchFeedback   = document.getElementById('match-feedback');
        var strengthBar     = document.getElementById('strength-bar');
        var strengthText    = document.getElementById('strength-text');
        var emailStatus     = 'invalid';
        var passwordsMatch  = false;

        // Email availability check
        async function checkEmail() {
            var email = emailInput.value;
            if (email.length < 5) return;
            try {
                var res  = await fetch("{{ route('admin.email.check') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ email: email })
                });
                var data = await res.json();
                if (data.exists) {
                    emailStatus = 'exists';
                    emailFeedback.textContent = 'ALREADY TAKEN!';
                    emailFeedback.style.color = '#ef4444';
                } else {
                    emailStatus = 'available';
                    emailFeedback.textContent = 'AVAILABLE!';
                    emailFeedback.style.color = '#10b981';
                }
            } catch (e) {}
        }

        // Password strength + match check
        function checkStrengthAndMatch() {
            var val = pwd.value, score = 0;
            if (val.length > 6)  score++;
            if (/[0-9]/.test(val)) score++;
            if (/[A-Z]/.test(val)) score++;
            if (!val.length) { strengthBar.style.width = '0'; strengthText.textContent = ''; }
            else if (score <= 1) { strengthBar.style.width = '33%'; strengthBar.style.backgroundColor = '#ef4444'; strengthText.textContent = 'WEAK'; strengthText.style.color = '#ef4444'; }
            else if (score === 2) { strengthBar.style.width = '66%'; strengthBar.style.backgroundColor = '#f59e0b'; strengthText.textContent = 'GOOD'; strengthText.style.color = '#f59e0b'; }
            else { strengthBar.style.width = '100%'; strengthBar.style.backgroundColor = '#10b981'; strengthText.textContent = 'STRONG!'; strengthText.style.color = '#10b981'; }
            if (confirmPwd.value.length > 0) {
                if (pwd.value !== confirmPwd.value) { passwordsMatch = false; matchFeedback.textContent = 'NOT MATCHING!'; matchFeedback.style.color = '#ef4444'; }
                else { passwordsMatch = true; matchFeedback.textContent = 'MATCH FOUND!'; matchFeedback.style.color = '#10b981'; }
            }
        }

        emailInput.addEventListener('input', function () { emailStatus = 'invalid'; checkEmail(); });
        pwd.addEventListener('input',        function () { passwordsMatch = false; checkStrengthAndMatch(); });
        confirmPwd.addEventListener('input', function () { passwordsMatch = false; checkStrengthAndMatch(); });

        // Dancing button — stops when form is valid
        document.addEventListener('mousemove', function (e) {
            if (emailStatus === 'available' && passwordsMatch && pwd.value.length >= 4) {
                btn.style.transform = 'translate(0,0)';
                btn.textContent = 'Create Account';
                btn.style.background = 'linear-gradient(135deg,#f83a3a 0%,#ff007a 100%)';
                return;
            }
            var rect = btn.getBoundingClientRect();
            var bx = rect.left + rect.width / 2, by = rect.top + rect.height / 2;
            var dist = Math.sqrt(Math.pow(e.clientX - bx, 2) + Math.pow(e.clientY - by, 2));
            if (dist < 110) {
                var angle = Math.atan2(e.clientY - by, e.clientX - bx) + Math.PI;
                btn.style.transform = 'translate(' + (Math.cos(angle) * 160) + 'px,' + (Math.sin(angle) * 160) + 'px)';
                btn.textContent = "Don't Cheat!";
                btn.style.background = '#fbbf24';
            }
        });
    })();
    </script>
</body>
</html>

