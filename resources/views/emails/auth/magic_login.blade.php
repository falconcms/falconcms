<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your magic sign-in link</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; padding: 0; background-color: #f3f4f6; color: #374151; line-height: 1.6; }
        .wrapper { width: 100%; background-color: #f3f4f6; padding: 40px 20px; box-sizing: border-box; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); padding: 36px 32px; text-align: center; }
        .header-logo { max-height: 44px; max-width: 160px; object-fit: contain; display: inline-block; margin-bottom: 16px; }
        .header-site { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: -.02em; margin-bottom: 4px; }
        .header-tagline { font-size: 13px; color: rgba(255,255,255,.75); margin: 0; }
        .body { padding: 36px 32px; }
        .greeting { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 12px; }
        .text { font-size: 15px; color: #4b5563; margin: 0 0 24px; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 36px; border-radius: 8px; letter-spacing: .01em; }
        .expiry-box { background: #fef9c3; border: 1px solid #fde047; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #854d0e; margin: 0 0 24px; text-align: center; }
        .expiry-box strong { color: #713f12; }
        .divider { border: none; border-top: 1px solid #f3f4f6; margin: 24px 0; }
        .link-fallback { font-size: 13px; color: #6b7280; margin: 0 0 8px; }
        .link-text { font-size: 12px; color: #9ca3af; word-break: break-all; }
        .footer { background: #f9fafb; border-top: 1px solid #f3f4f6; padding: 20px 32px; text-align: center; }
        .footer-text { font-size: 12px; color: #9ca3af; margin: 0; }
        .footer-link { color: #6366f1; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">

        {{-- Header --}}
        <div class="header">
            @if(get_cms_option('theme_site_logo'))
                <img src="{{ get_cms_option('theme_site_logo') }}" alt="{{ $siteName }}" class="header-logo">
            @else
                <div class="header-site">{{ $siteName }}</div>
            @endif
            <p class="header-tagline">Passwordless Sign-In</p>
        </div>

        {{-- Body --}}
        <div class="body">
            <p class="greeting">Hi {{ $userName }},</p>
            <p class="text">
                You requested a magic sign-in link. Click the button below to sign in instantly — no password needed.
            </p>

            <div class="btn-wrap">
                <a href="{{ $magicUrl }}" class="btn">Sign In Now</a>
            </div>

            <div class="expiry-box">
                ⏳ <strong>This link expires in 10 minutes</strong> and can only be used once.
            </div>

            <p class="text">
                If you did not request this link, you can safely ignore this email. Your account will not be affected.
            </p>

            <hr class="divider">

            <p class="link-fallback">If the button above doesn't work, copy and paste this link into your browser:</p>
            <p class="link-text">{{ $magicUrl }}</p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p class="footer-text">
                This email was sent by <a href="{{ $siteUrl }}" class="footer-link">{{ $siteName }}</a>.
                If you have any questions, please contact your site administrator.
            </p>
        </div>

    </div>
</div>
</body>
</html>
