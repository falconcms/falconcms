<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify your email — {{ get_cms_option('site_title') ?: config('app.name', 'Falcon CMS') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; min-height: 100vh; background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%); color: #374151; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.06); overflow: hidden; }
        .top { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); padding: 32px; text-align: center; color: #fff; }
        .icon { width: 56px; height: 56px; border-radius: 50%; background: rgba(255,255,255,.18); display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 12px; }
        .top h1 { font-size: 20px; font-weight: 800; margin: 0; letter-spacing: -.01em; }
        .body { padding: 28px 32px 32px; }
        .lead { font-size: 14.5px; color: #4b5563; margin: 0 0 18px; line-height: 1.6; }
        .lead strong { color: #111827; }
        .alert { padding: 12px 14px; border-radius: 10px; font-size: 13.5px; margin: 0 0 18px; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .note { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: 12.5px; margin: 0 0 18px; text-align: center; }
        label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin: 0 0 6px; }
        input[type=email] { width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 11px 14px; font-size: 14px; outline: none; transition: border-color .15s; }
        input[type=email]:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
        .btn { width: 100%; margin-top: 14px; background: #4f46e5; color: #fff; border: none; border-radius: 10px; padding: 12px; font-size: 14.5px; font-weight: 700; cursor: pointer; transition: background .15s; }
        .btn:hover { background: #4338ca; }
        .foot { text-align: center; margin-top: 18px; font-size: 13px; }
        .foot a { color: #6366f1; text-decoration: none; font-weight: 600; }
        .foot a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="top">
            <div class="icon">✉️</div>
            <h1>Verify your email</h1>
        </div>
        <div class="body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <p class="lead">
                @if(!empty($email))
                    We've sent a verification link to <strong>{{ $email }}</strong>.
                @else
                    We've sent you a verification link.
                @endif
                Click the link in that email to activate your account and sign in.
            </p>

            <div class="note">⏳ The link expires in <strong>5 minutes</strong>. Didn't get it or it expired? Resend below.</div>

            <form action="{{ route('admin.verify.resend') }}" method="POST">
                @csrf
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="{{ $email ?? old('email') }}" placeholder="you@example.com" required>
                <button type="submit" class="btn">Resend verification link</button>
            </form>

            <div class="foot">
                <a href="{{ route('admin.login') }}">&larr; Back to sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
