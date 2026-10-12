<?php

namespace FalconCms\Core\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Core Web Vitals from real visitors (RUM): how fast pages are for the people who actually
 * open them, today, instead of Google's 28-day field data or one lab test.
 *
 * A small inline script (no library) watches LCP, INP, CLS and TTFB with the browser's own
 * PerformanceObserver and sends them once, with sendBeacon, as the visitor leaves the page, so it
 * never slows the page down. Google's thresholds and the 75th percentile are used, as in Search
 * Console. Off by default (Customizer → Performance).
 */
final class WebVitals
{
    public const OPTION = 'perf_rum';

    public const KEEP_DAYS = 90;

    /** Rows stored per day at most: a busy site gets a sample, not a flood. */
    public const DAILY_CAP = 20000;

    /** good ≤ first, poor > second (Google's Core Web Vitals thresholds). */
    public const THRESHOLDS = ['lcp' => [2500, 4000], 'inp' => [200, 500], 'cls' => [0.1, 0.25], 'ttfb' => [800, 1800]];

    public static function enabled(): bool
    {
        return (string) get_cms_option(self::OPTION, '0') === '1';
    }

    /** The measuring script, for the end of <body>. */
    public static function script(): string
    {
        $url = json_encode(route('frontend.web-vitals'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        // INP: the worst interaction, ignoring one outlier per 50 (how browsers report it).
        // CLS: the largest burst of shifts (session windows: < 1s apart, < 5s long).
        return <<<HTML
<script data-no-defer>(function(){if(!('PerformanceObserver'in window)||!navigator.sendBeacon)return;var v={},ev=[],cls=0,win=0,first=0,last=0;
function o(t,f,x){try{var p=new PerformanceObserver(function(l){l.getEntries().forEach(f)});p.observe(Object.assign({type:t,buffered:true},x||{}))}catch(e){}}
o('largest-contentful-paint',function(e){v.lcp=Math.round(e.startTime)});
o('layout-shift',function(e){if(e.hadRecentInput)return;if(win&&e.startTime-last<1000&&e.startTime-first<5000){win+=e.value}else{win=e.value;first=e.startTime}last=e.startTime;if(win>cls){cls=win;v.cls=Math.round(cls*10000)/10000}});
o('event',function(e){if(e.interactionId){ev.push(e.duration)}},{durationThreshold:16});
var n=performance.getEntriesByType('navigation')[0];if(n)v.ttfb=Math.round(n.responseStart);
var sent=false;function send(){if(sent||!('lcp'in v))return;sent=true;if(ev.length){ev.sort(function(a,b){return b-a});v.inp=Math.round(ev[Math.min(ev.length-1,Math.floor(ev.length/50))])}
if(!('cls'in v))v.cls=0;v.p=location.pathname;v.d=matchMedia('(max-width: 767px)').matches?'m':'d';navigator.sendBeacon({$url},JSON.stringify(v))}
addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')send()});addEventListener('pagehide',send)})();</script>
HTML;
    }

    /** Store one page view's numbers. Returns false when the sample is rejected. */
    public static function record(Request $request): bool
    {
        if (!self::enabled() || preg_match('/bot|crawl|spider|slurp|lighthouse|headless|pagespeed|gtmetrix/i', (string) $request->userAgent())) {
            return false;
        }
        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return false;
        }
        $path = '/'.ltrim(mb_substr((string) ($data['p'] ?? ''), 0, 500), '/');
        // Plausible values only: a hidden tab can report hours of "LCP".
        $ms = fn ($v, $max) => is_numeric($v) && $v >= 0 && $v <= $max ? (int) round((float) $v) : null;
        $row = [
            'lcp' => $ms($data['lcp'] ?? null, 60000),
            'inp' => $ms($data['inp'] ?? null, 30000),
            'cls' => is_numeric($data['cls'] ?? null) && $data['cls'] >= 0 && $data['cls'] <= 10 ? round((float) $data['cls'], 4) : null,
            'ttfb' => $ms($data['ttfb'] ?? null, 60000),
        ];
        if ($row['lcp'] === null || str_starts_with($path, '/admin')) {
            return false;
        }

        try {
            $today = now()->toDateString();
            if (DB::table('cms_web_vitals')->where('date', $today)->count() >= self::DAILY_CAP) {
                return false;
            }
            DB::table('cms_web_vitals')->insert($row + ['date' => $today, 'path' => $path, 'path_hash' => sha1($path),
                'device' => ($data['d'] ?? '') === 'm' ? 'm' : 'd']);
            if (random_int(1, 200) === 1) {
                DB::table('cms_web_vitals')->where('date', '<', now()->subDays(self::KEEP_DAYS)->toDateString())->delete();
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{samples: int, metrics: array<string, array{p75: float|int|null, rating: ?string, good: int, needs: int, poor: int}>, pages: list<array>}
     */
    public static function report(int $days = 28, string $device = ''): array
    {
        $from = CarbonImmutable::today()->subDays($days - 1)->toDateString();
        $rows = DB::table('cms_web_vitals')->where('date', '>=', $from)
            ->when(in_array($device, ['m', 'd'], true), fn ($q) => $q->where('device', $device))
            ->orderByDesc('id')->limit(100000)
            ->get(['path', 'path_hash', 'lcp', 'inp', 'cls', 'ttfb']);

        $metrics = [];
        foreach (array_keys(self::THRESHOLDS) as $metric) {
            $metrics[$metric] = self::summarise($rows->pluck($metric)->all(), $metric);
        }

        $pages = [];
        foreach ($rows->groupBy('path_hash') as $group) {
            if ($group->count() < 3) {
                continue;
            }
            $page = ['path' => (string) $group->first()->path, 'samples' => $group->count()];
            foreach (['lcp', 'inp', 'cls'] as $metric) {
                $s = self::summarise($group->pluck($metric)->all(), $metric);
                $page[$metric] = $s['p75'];
                $page[$metric.'_rating'] = $s['rating'];
            }
            $pages[] = $page;
        }
        // Worst first: a poor rating outranks a slow-but-good page, then by LCP, then by traffic.
        $rank = ['poor' => 2, 'needs' => 1, 'good' => 0, null => 0];
        usort($pages, fn ($a, $b) => [max($rank[$b['lcp_rating']], $rank[$b['inp_rating']], $rank[$b['cls_rating']]), $b['lcp'] ?? 0, $b['samples']]
            <=> [max($rank[$a['lcp_rating']], $rank[$a['inp_rating']], $rank[$a['cls_rating']]), $a['lcp'] ?? 0, $a['samples']]);

        return ['samples' => $rows->count(), 'metrics' => $metrics, 'pages' => array_slice($pages, 0, 50)];
    }

    public static function rating(string $metric, $value): ?string
    {
        if ($value === null) {
            return null;
        }
        [$good, $poor] = self::THRESHOLDS[$metric];

        return $value <= $good ? 'good' : ($value > $poor ? 'poor' : 'needs');
    }

    /** p75, its rating, and how many samples fall in each band. */
    private static function summarise(array $values, string $metric): array
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));
        $out = ['p75' => null, 'rating' => null, 'good' => 0, 'needs' => 0, 'poor' => 0];
        if ($values === []) {
            return $out;
        }
        $values = array_map(fn ($v) => $metric === 'cls' ? (float) $v : (int) $v, $values);
        sort($values);
        $p75 = $values[(int) max(0, ceil(count($values) * 0.75) - 1)];
        foreach ($values as $v) {
            $out[self::rating($metric, $v)]++;
        }
        $out['p75'] = $metric === 'cls' ? round($p75, 3) : $p75;
        $out['rating'] = self::rating($metric, $p75);

        return $out;
    }
}
