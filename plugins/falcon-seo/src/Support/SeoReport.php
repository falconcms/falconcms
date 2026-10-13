<?php

namespace FalconSeo\Support;

use FalconSeo\Mail\SeoReportMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly SEO email (Pro): the site's average score, the pages whose score dropped since
 * their last check, every broken link and the pages that need work most. Sent on Monday
 * mornings by the scheduler, to the addresses in SEO → Settings (else the admin email).
 */
final class SeoReport
{
    /** A drop this big is worth a look. */
    public const DROP = 10;

    public static function enabled(): bool
    {
        return Pro::active() && (string) get_cms_option(Pro::REPORT_ENABLED, '0') === '1';
    }

    /** @return list<string> */
    public static function recipients(): array
    {
        $saved = trim((string) get_cms_option(Pro::REPORT_TO, ''));
        $list = $saved !== '' ? preg_split('/[\s,;]+/', $saved) : [get_cms_option('admin_email') ?: config('mail.from.address')];

        return array_values(array_unique(array_filter(array_map('trim', (array) $list), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    public static function data(): array
    {
        $summary = SiteScan::summary();
        $rows = collect($summary['rows'])->map(fn ($r) => (array) $r);

        $dropped = Schema::hasColumn(SiteScan::TABLE, 'previous_score')
            ? $rows->filter(fn ($r) => $r['previous_score'] !== null && (int) $r['previous_score'] - (int) $r['score'] >= self::DROP)
                ->sortByDesc(fn ($r) => (int) $r['previous_score'] - (int) $r['score'])->values()->all()
            : [];
        $broken = collect($summary['problems'])->firstWhere('id', 'broken_links');

        return [
            'average' => $summary['average'],
            'checked' => $summary['checked'],
            'pages' => $summary['pages'],
            'grades' => $summary['grades'],
            'dropped' => array_slice($dropped, 0, 10),
            'broken' => $broken ? array_slice($broken['pages'], 0, 15) : [],
            'brokenCount' => $broken ? array_sum(array_map(fn ($p) => count($p['items']), $broken['pages'])) : 0,
            'worst' => $rows->sortBy('score')->take(5)->values()->all(),
            'problems' => array_slice(array_map(fn ($p) => ['label' => $p['label'], 'pages' => count($p['pages']), 'status' => $p['status']], $summary['problems']), 0, 6),
            'url' => route('admin.settings.site-analysis'),
        ];
    }

    /** Send to every recipient. Returns how many it went to (none when nothing was checked yet). */
    public static function send(): int
    {
        $to = self::recipients();
        if ($to === [] || !DB::table(SiteScan::TABLE)->exists()) {
            return 0;
        }
        Mail::to($to)->send(new SeoReportMail(self::data()));

        return count($to);
    }
}
