@php
    $d = $data;
    $tone = fn ($score) => $score >= 80 ? '#00a32a' : ($score >= 50 ? '#b26200' : '#d63638');
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>SEO this week</title></head>
<body style="margin:0;padding:24px;background:#f0f0f1;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1d2327;">
<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:24px;">
    <h1 style="font-size:18px;margin:0 0 4px;">SEO this week</h1>
    <p style="margin:0 0 20px;color:#646970;font-size:13px;">{{ $siteName }} · {{ number_format($d['checked']) }} of {{ number_format($d['pages']) }} pages checked</p>

    <table role="presentation" width="100%" style="margin-bottom:20px;border-collapse:collapse;">
        <tr>
            <td style="padding:12px;background:#f6f7f7;border-radius:4px;text-align:center;">
                <div style="font-size:28px;font-weight:700;color:{{ $d['average'] !== null ? $tone($d['average']) : '#646970' }};">{{ $d['average'] ?? '–' }}</div>
                <div style="font-size:11px;color:#646970;text-transform:uppercase;">average score</div>
            </td>
            <td style="width:12px;"></td>
            <td style="padding:12px;background:#f6f7f7;border-radius:4px;text-align:center;font-size:13px;">
                <span style="color:#00a32a;font-weight:600;">{{ $d['grades']['good'] }} good</span><br>
                <span style="color:#b26200;font-weight:600;">{{ $d['grades']['ok'] }} need some work</span><br>
                <span style="color:#d63638;font-weight:600;">{{ $d['grades']['poor'] }} need work</span>
            </td>
        </tr>
    </table>

    @if($d['broken'])
        <h2 style="font-size:15px;margin:0 0 8px;color:#d63638;">{{ $d['brokenCount'] }} broken link(s)</h2>
        @foreach($d['broken'] as $page)
            <p style="margin:0 0 2px;font-size:13px;font-weight:600;">{{ $page['title'] ?: '(no title)' }}</p>
            <ul style="margin:0 0 10px;padding-left:18px;font-size:12px;color:#50575e;">
                @foreach(array_slice($page['items'], 0, 5) as $item)<li style="word-break:break-all;">{{ $item }}</li>@endforeach
            </ul>
        @endforeach
    @endif

    @if($d['dropped'])
        <h2 style="font-size:15px;margin:16px 0 8px;">Scores that dropped</h2>
        <table role="presentation" width="100%" style="border-collapse:collapse;font-size:13px;">
            @foreach($d['dropped'] as $row)
                <tr style="border-top:1px solid #f0f0f1;">
                    <td style="padding:6px 0;">{{ $row['title'] ?: '(no title)' }}</td>
                    <td style="padding:6px 0;text-align:right;white-space:nowrap;color:#d63638;">{{ $row['previous_score'] }} → {{ $row['score'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($d['problems'])
        <h2 style="font-size:15px;margin:16px 0 8px;">Most common problems</h2>
        <ul style="margin:0 0 10px;padding-left:18px;font-size:13px;">
            @foreach($d['problems'] as $p)<li>{{ $p['label'] }} <span style="color:#646970;">({{ $p['pages'] }} {{ $p['pages'] === 1 ? 'page' : 'pages' }})</span></li>@endforeach
        </ul>
    @endif

    @if($d['worst'])
        <h2 style="font-size:15px;margin:16px 0 8px;">Fix these first</h2>
        <table role="presentation" width="100%" style="border-collapse:collapse;font-size:13px;">
            @foreach($d['worst'] as $row)
                <tr style="border-top:1px solid #f0f0f1;">
                    <td style="padding:6px 0;">{{ $row['title'] ?: '(no title)' }}</td>
                    <td style="padding:6px 0;text-align:right;font-weight:700;color:{{ $tone((int) $row['score']) }};">{{ $row['score'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p style="margin:24px 0 0;"><a href="{{ $d['url'] }}" style="display:inline-block;background:#2271b1;color:#fff;text-decoration:none;padding:8px 14px;border-radius:4px;font-size:13px;">Open Site Analysis</a></p>
    <p style="margin:16px 0 0;font-size:11px;color:#8c8f94;">Sent weekly by Falcon SEO. Turn it off under SEO → Settings → Weekly SEO email.</p>
</div>
</body>
</html>
