<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pro feature — {{ get_cms_option('site_title', config('app.name', 'FalconCMS')) }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f6f8; color: #171c23; padding: 24px;
        }
        .card {
            background: #fff; border: 1px solid #e1e5ea; border-radius: 14px;
            max-width: 460px; width: 100%; padding: 44px 38px; text-align: center;
            box-shadow: 0 2px 4px rgba(23,28,35,.05), 0 24px 50px -22px rgba(23,28,35,.25);
        }
        .icon {
            width: 64px; height: 64px; margin: 0 auto 22px; border-radius: 16px;
            display: grid; place-items: center; color: #b9720f; background: #fbe9cf;
        }
        h1 { font-size: 22px; font-weight: 800; letter-spacing: -.02em; margin: 0 0 10px; }
        p { font-size: 15.5px; line-height: 1.6; color: #434e5a; margin: 0 0 26px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            font-size: 14.5px; font-weight: 600; padding: 11px 20px; border-radius: 8px;
            background: #e8912b; color: #171c23; transition: transform .15s ease, box-shadow .2s ease;
            box-shadow: 0 6px 18px -8px rgba(232,145,43,.7);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px -10px rgba(232,145,43,.8); }
        .tag {
            display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: #b9720f; margin-bottom: 14px;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0f1318; color: #e8ecf0; }
            .card { background: #161c23; border-color: #262e38; }
            .icon { background: #2a2114; color: #f2a63c; }
            p { color: #aeb8c3; }
            .tag { color: #f2a63c; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="10" width="16" height="11" rx="2"/>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            </svg>
        </div>
        <span class="tag">Pro feature</span>
        <h1>Not available right now</h1>
        <p>{{ $message ?? 'This feature is available in the Pro version.' }}</p>
        <a class="btn" href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener">Upgrade to Pro</a>
        <a class="btn" href="{{ url('/') }}" style="background:transparent;color:inherit;box-shadow:none;border:1px solid rgba(148,163,184,.4);margin-left:8px;">Back to home</a>
    </div>
</body>
</html>
