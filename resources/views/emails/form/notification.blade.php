<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Form Submission — {{ $form->title }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;">

@php
    $siteName = function_exists('get_cms_option') ? get_cms_option('site_name', config('app.name', 'FalconCMS')) : config('app.name', 'FalconCMS');
    if (!isset($introText)) {
        $tplData = json_decode(get_cms_option('email_template_form_notification', '{}'), true) ?: [];
        $introText = $tplData['intro'] ?? 'You have received a new submission. Review the details below to follow up promptly.';
        $footerText = $tplData['footer'] ?? 'This is an automated notification — no reply is needed.';
    }
@endphp

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:40px 16px;">
    <tr>
        <td align="center" valign="top">

            {{-- ── Outer card ── --}}
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);border:1px solid #e2e8f0;">

                {{-- ── HEADER ── --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#1e40af 0%,#3b82f6 60%,#60a5fa 100%);padding:36px 40px 32px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td valign="middle" style="padding-right:18px;width:56px;">
                                    {{-- envelope icon --}}
                                    <div style="width:52px;height:52px;background:rgba(255,255,255,0.18);border-radius:14px;text-align:center;line-height:52px;">
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;margin-top:1px;">
                                            <rect x="2" y="4" width="20" height="16" rx="3" fill="rgba(255,255,255,0.25)" stroke="white" stroke-width="1.5"/>
                                            <path d="M2 8l10 6 10-6" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                </td>
                                <td valign="middle">
                                    <p style="margin:0 0 3px;font-size:11px;font-weight:700;color:rgba(255,255,255,0.65);text-transform:uppercase;letter-spacing:1px;">New Submission</p>
                                    <h1 style="margin:0;font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.4px;line-height:1.2;">{{ $form->title }}</h1>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── META STRIP ── --}}
                <tr>
                    <td style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:12px 40px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#64748b;">
                                    <span style="margin-right:18px;">
                                        <span style="font-weight:600;color:#475569;">&#128197; Submitted:</span>
                                        <span style="color:#334155;font-weight:600;margin-left:4px;">{{ $submittedAt }}</span>
                                    </span>
                                    <span>
                                        <span style="font-weight:600;color:#475569;">&#127760; IP Address:</span>
                                        <span style="color:#334155;font-weight:600;margin-left:4px;">{{ $ip }}</span>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ── BODY ── --}}
                <tr>
                    <td style="padding:32px 40px 24px;">

                        <p style="margin:0 0 22px;font-size:14px;color:#475569;line-height:1.6;">
                            {{ $introText }}
                        </p>

                        {{-- ── FIELDS TABLE ── --}}
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">

                            @php $i = 0; @endphp
                            @foreach($rows as $row)
                                @php $bg = ($i % 2 === 0) ? '#ffffff' : '#f8fafc'; $i++; @endphp
                                <tr style="background-color:{{ $bg }};">
                                    <td style="padding:13px 18px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.6px;width:36%;border-bottom:1px solid #f1f5f9;vertical-align:top;white-space:nowrap;">
                                        {{ $row['label'] }}
                                    </td>
                                    <td style="padding:13px 18px;font-size:14px;color:#111827;border-bottom:1px solid #f1f5f9;word-break:break-word;line-height:1.55;">
                                        @if($row['is_file'])
                                            <a href="{{ $row['display'] }}" style="display:inline-block;background:#eff6ff;color:#2563eb;text-decoration:none;font-size:12px;font-weight:600;padding:5px 12px;border-radius:6px;border:1px solid #bfdbfe;">
                                                &#128206; Download File
                                            </a>
                                        @elseif($row['is_empty'])
                                            <span style="color:#9ca3af;font-style:italic;font-size:13px;">—</span>
                                        @else
                                            {!! $row['display'] !!}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                        </table>

                    </td>
                </tr>

                {{-- ── DIVIDER ── --}}
                <tr>
                    <td style="padding:0 40px;">
                        <div style="height:1px;background:#f1f5f9;"></div>
                    </td>
                </tr>

                {{-- ── FOOTER NOTE ── --}}
                <tr>
                    <td style="padding:20px 40px 28px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
                            {{ $footerText }}
                        </p>
                    </td>
                </tr>

                {{-- ── BOTTOM BAR ── --}}
                <tr>
                    <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 40px;text-align:center;">
                        <p style="margin:0;font-size:11px;color:#9ca3af;">
                            &copy; {{ date('Y') }} {{ $siteName }} &mdash; Powered by <strong>FalconCMS</strong>
                        </p>
                    </td>
                </tr>

            </table>
            {{-- /card --}}

        </td>
    </tr>
</table>

</body>
</html>
