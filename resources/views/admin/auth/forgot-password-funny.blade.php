<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password — {{ get_cms_option('site_title', 'FalconCMS') }}</title>
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
        .funny-sub { font-size: .875rem; color: #6b7280; margin-bottom: 1.75rem; line-height: 1.6; }

        .a-error {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
            padding: 11px 14px; font-size: .84rem; color: #b91c1c;
            display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; text-align: left;
        }
        .a-success {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
            padding: 11px 14px; font-size: .84rem; color: #166534;
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

        /* Funny button area */
        .btn-funny-area { height: 90px; position: relative; display: flex; align-items: center; justify-content: center; }

        #forgot-btn {
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

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: .84rem; color: #0091ea; text-decoration: none; font-weight: 600;
        }
        .back-link:hover { color: #007bc7; }
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
            <svg width="24" height="24" fill="none" stroke="#0091ea" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>

        <h1 class="funny-title">FORGOT PASSWORD?</h1>
        <p class="funny-sub">Enter your email and we'll send you a link to get back in.</p>

        @if(session('status'))
        <div class="a-success">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('status') }}
        </div>
        @endif

        @if($errors->any())
        <div class="a-error">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('admin.password.email') }}" method="POST" style="display:flex;flex-direction:column;gap:1.25rem;text-align:left">
            @csrf
            <div class="lf-field-wrap">
                <input type="email" name="email" id="a_email" placeholder=" " class="lf-input" required autofocus value="{{ old('email') }}">
                <label class="lf-float-label" for="a_email">Email address</label>
            </div>
            <div class="btn-funny-area">
                <button type="submit" id="forgot-btn">Send Reset Link</button>
            </div>
        </form>

        <p style="text-align:center;margin-top:1.75rem">
            <a href="{{ route('admin.login') }}" class="back-link">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to sign in
            </a>
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

        // Dancing button — flees when email not registered, stays still when found
        var btn        = document.getElementById('forgot-btn');
        var emailInput = document.getElementById('a_email');
        var emailExists = false;

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
                if (emailExists) {
                    btn.style.transform = 'translate(0,0)';
                    btn.textContent = 'Send Reset Link';
                    btn.style.background = 'linear-gradient(135deg,#f83a3a 0%,#ff007a 100%)';
                }
            } catch (e) {}
        }

        emailInput.addEventListener('input', function () { emailExists = false; checkEmail(); });

        document.addEventListener('mousemove', function (e) {
            if (emailExists) return;
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
