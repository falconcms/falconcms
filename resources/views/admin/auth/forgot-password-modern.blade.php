<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — {{ get_cms_option('site_title', 'FalconCMS') }}</title>
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
        .form-head p  { font-size: .875rem; color: #6b7280; line-height: 1.55; }

        .a-error   { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 11px 14px; font-size: .84rem; color: #b91c1c; display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; }
        .a-success { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 11px 14px; font-size: .84rem; color: #166534; display: flex; align-items: flex-start; gap: 8px; margin-bottom: 18px; }

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

        .btn-submit { background: #0091ea; color: #fff; padding: 13px; border: none; border-radius: 10px; font-weight: 700; font-size: .9rem; font-family: inherit; width: 100%; cursor: pointer; letter-spacing: .01em; transition: background .2s, transform .1s, box-shadow .2s; }
        .btn-submit:hover { background: #007bc7; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,145,234,.3); }
        .btn-submit:active { transform: translateY(0); }

        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: .84rem; color: #0091ea; text-decoration: none; font-weight: 600; }
        .back-link:hover { color: #007bc7; }
    </style>
</head>
<body>
<div class="form-wrap">

    <div class="form-logo">
        @if(get_cms_option('theme_site_logo'))
            <img src="{{ get_cms_option('theme_site_logo') }}" alt="{{ get_cms_option('site_title', 'FalconCMS') }}">
        @else
            <span class="form-logo-text">{{ get_cms_option('site_title', 'Lazy') }}<span class="form-logo-accent"> CMS</span></span>
        @endif
    </div>

    <div class="form-card">
        <div class="form-head">
            <div class="icon-wrap">
                <svg width="24" height="24" fill="none" stroke="#0091ea" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h1>Forgot password?</h1>
            <p>Enter your email and we'll send you reset instructions.</p>
        </div>

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

        <form action="{{ route('admin.password.email') }}" method="POST" style="display:flex;flex-direction:column;gap:1.25rem">
            @csrf
            <div class="lf-field-wrap">
                <input type="email" name="email" id="a_email" placeholder=" " class="lf-input" required autofocus value="{{ old('email') }}">
                <label class="lf-float-label" for="a_email">Email address</label>
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>

        <p style="text-align:center;margin-top:1.5rem">
            <a href="{{ route('admin.login') }}" class="back-link">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to sign in
            </a>
        </p>
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
})();
</script>
</body>
</html>
