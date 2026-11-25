<?php

namespace FalconCms\Core\Support;

use Carbon\Carbon;
use Throwable;

/**
 * The Countdown builder element (Pro, builder_pro).
 *
 * Three kinds of deadline:
 *   - fixed      one date and time, read in the site's timezone (Settings → General);
 *   - evergreen  a length of time that starts when each visitor first sees it, kept in
 *                their browser so a reload does not start it again;
 *   - daily      a time of day on chosen weekdays ("order before 17:00 for same-day
 *                shipping"), rolling on to the next one when it passes.
 *
 * Four designs (boxes, circles, flip, minimal) share one markup; only the stylesheet differs.
 *
 * The page is drawn with the right numbers already in it, worked out here, so it reads
 * correctly before any script runs and with none at all. The script
 * (components/frontend/countdown-runtime) then ticks it, and finds the deadline again from
 * data attributes on every tick — which is also why a cached copy of the page still counts
 * from the real time and not from when it was cached.
 *
 * Renderer: resources/views/frontend/builder/elements/countdown/render.blade.php. The builder
 * canvas asks the server for that same template ('preview' => 'server'), and the runtime is
 * loaded in the builder as well, so the preview ticks too.
 */
final class Countdown
{
    public const TYPE = 'countdown';

    public const MODES = ['fixed', 'evergreen', 'daily'];

    public const DESIGNS = ['boxes', 'circles', 'flip', 'minimal'];

    /** Unit => [seconds, default label]. In display order. */
    public const UNITS = [
        'days' => [86400, 'Days'],
        'hours' => [3600, 'Hours'],
        'minutes' => [60, 'Minutes'],
        'seconds' => [1, 'Seconds'],
    ];

    /** What happens at zero. */
    public const EXPIRE = ['message', 'hide', 'redirect', 'zero'];

    /** Weekday keys in PHP's and JavaScript's numbering (0 = Sunday). */
    public const WEEKDAYS = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '0' => 'Sun'];

    /** On falcon_builder_elements. */
    public static function register(array $elements): array
    {
        $tz = self::timezone();
        // A new countdown points a week ahead, so it shows something real the moment it
        // is dropped in. Worked out per request; a saved element keeps the date it got.
        try {
            $weekAhead = Carbon::now($tz)->addDays(7)->setTime(18, 0)->format('Y-m-d\TH:i');
        } catch (Throwable $e) {
            $weekAhead = date('Y-m-d\TH:i', time() + 7 * 86400);
        }
        $showHide = ['show' => 'Show', 'hide' => 'Hide'];

        $elements[self::TYPE] = [
            'type' => self::TYPE,
            'name' => 'Countdown',
            'icon' => 'fa fa-hourglass-half',
            'template' => 'falcon-cms::frontend.builder.elements.countdown.render',
            'preview' => 'server',
            'fields' => [
                // ── General ─────────────────────────────────────────────
                'mode' => ['type' => 'select', 'label' => 'Count down to', 'default' => 'fixed', 'options' => [
                    'fixed' => 'A date and time',
                    'evergreen' => 'A set time from each visitor\'s first visit',
                    'daily' => 'A time of day, every day',
                ]],
                'end_at' => ['type' => 'datetime', 'label' => 'Ends at', 'default' => $weekAhead,
                    'condition' => ['field' => 'mode', 'value' => 'fixed'],
                    'description' => 'In the site\'s timezone ('.$tz.', Settings → General). Every visitor sees the same deadline.'],
                'ever_days' => ['type' => 'number', 'label' => 'Days', 'default' => 1, 'min' => 0, 'max' => 365,
                    'condition' => ['field' => 'mode', 'value' => 'evergreen']],
                'ever_hours' => ['type' => 'number', 'label' => 'Hours', 'default' => 0, 'min' => 0, 'max' => 23,
                    'condition' => ['field' => 'mode', 'value' => 'evergreen']],
                'ever_minutes' => ['type' => 'number', 'label' => 'Minutes', 'default' => 0, 'min' => 0, 'max' => 59,
                    'condition' => ['field' => 'mode', 'value' => 'evergreen'],
                    'description' => 'Starts when a visitor first sees it and is remembered in their browser, so reloading does not reset it.'],
                'ever_restart' => ['type' => 'select', 'label' => 'When it runs out', 'default' => 'stop', 'options' => [
                    'stop' => 'Stay ended for that visitor',
                    'restart' => 'Start again on their next visit',
                ], 'condition' => ['field' => 'mode', 'value' => 'evergreen']],
                'daily_time' => ['type' => 'time', 'label' => 'Every day until', 'default' => '17:00',
                    'condition' => ['field' => 'mode', 'value' => 'daily'],
                    'description' => 'In the site\'s timezone ('.$tz.'). After it passes, it counts to the next one.'],
                'daily_days' => ['type' => 'checkbox', 'label' => 'On these days', 'default' => array_keys(self::WEEKDAYS),
                    'options' => self::WEEKDAYS, 'condition' => ['field' => 'mode', 'value' => 'daily']],
                'units' => ['type' => 'checkbox', 'label' => 'Show', 'default' => array_keys(self::UNITS), 'options' => [
                    'days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds',
                ], 'description' => 'A unit left out is carried by the one before it: without Days, Hours can go past 24.'],
                'labels' => ['type' => 'select', 'label' => 'Labels', 'default' => 'below', 'options' => [
                    'below' => 'Below the numbers', 'above' => 'Above the numbers', 'short' => 'Short, beside (5d 04h)', 'hide' => 'Hidden',
                ]],
                'label_days' => ['type' => 'text', 'label' => 'Days label', 'default' => 'Days', 'condition' => ['field' => 'labels', 'operator' => 'in', 'value' => ['below', 'above']]],
                'label_hours' => ['type' => 'text', 'label' => 'Hours label', 'default' => 'Hours', 'condition' => ['field' => 'labels', 'operator' => 'in', 'value' => ['below', 'above']]],
                'label_minutes' => ['type' => 'text', 'label' => 'Minutes label', 'default' => 'Minutes', 'condition' => ['field' => 'labels', 'operator' => 'in', 'value' => ['below', 'above']]],
                'label_seconds' => ['type' => 'text', 'label' => 'Seconds label', 'default' => 'Seconds', 'condition' => ['field' => 'labels', 'operator' => 'in', 'value' => ['below', 'above']]],
                'leading_zero' => ['type' => 'select', 'label' => 'Leading zero (05)', 'default' => 'show', 'options' => $showHide],
                'expire_action' => ['type' => 'select', 'label' => 'At zero', 'default' => 'message', 'options' => [
                    'message' => 'Show a message',
                    'hide' => 'Hide the countdown',
                    'redirect' => 'Go to another page',
                    'zero' => 'Stay at zero',
                ]],
                'expire_message' => ['type' => 'text', 'label' => 'Message', 'default' => 'This offer has ended.',
                    'condition' => ['field' => 'expire_action', 'value' => 'message']],
                'expire_url' => ['type' => 'text', 'label' => 'Go to (URL)', 'default' => '', 'placeholder' => 'https://… or /page',
                    'condition' => ['field' => 'expire_action', 'value' => 'redirect']],

                // ── Design ──────────────────────────────────────────────
                'design' => ['type' => 'visual_select', 'label' => 'Design', 'default' => 'boxes', 'tab' => 'design', 'options' => [
                    'boxes' => ['label' => 'Boxes', 'icon' => 'fa fa-table-cells-large'],
                    'circles' => ['label' => 'Circles', 'icon' => 'fa fa-circle-notch'],
                    'flip' => ['label' => 'Flip clock', 'icon' => 'fa fa-clone'],
                    'minimal' => ['label' => 'Minimal', 'icon' => 'fa fa-font'],
                ]],
                'align' => ['type' => 'select', 'label' => 'Alignment', 'default' => 'center', 'tab' => 'design', 'responsive' => true,
                    'options' => ['flex-start' => 'Left', 'center' => 'Center', 'flex-end' => 'Right']],
                'item_size' => ['type' => 'slider', 'label' => 'Size', 'default' => 96, 'min' => 40, 'max' => 240, 'step' => 2, 'unit' => 'px', 'tab' => 'design'],
                'item_size_mobile' => ['type' => 'slider', 'label' => 'Size on phones', 'default' => 68, 'min' => 32, 'max' => 200, 'step' => 2, 'unit' => 'px', 'tab' => 'design',
                    'description' => 'A row that is still too wide wraps onto a second line.'],
                'gap' => ['type' => 'slider', 'label' => 'Space between', 'default' => 14, 'min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'px', 'tab' => 'design'],
                'separator' => ['type' => 'select', 'label' => 'Separator', 'default' => 'auto', 'tab' => 'design', 'options' => [
                    'auto' => 'As the design has it', 'colon' => 'Colon (:)', 'none' => 'None',
                ]],
                'radius' => ['type' => 'slider', 'label' => 'Corners', 'default' => 12, 'min' => 0, 'max' => 60, 'step' => 1, 'unit' => 'px', 'tab' => 'design',
                    'condition' => ['field' => 'design', 'operator' => 'in', 'value' => ['boxes', 'flip']]],
                'ring_width' => ['type' => 'slider', 'label' => 'Ring thickness', 'default' => 6, 'min' => 1, 'max' => 20, 'step' => 1, 'unit' => 'px', 'tab' => 'design',
                    'condition' => ['field' => 'design', 'value' => 'circles']],
                'num_color' => ['type' => 'color', 'label' => 'Numbers', 'default' => '', 'hint' => 'Design default', 'tab' => 'design'],
                'label_color' => ['type' => 'color', 'label' => 'Labels', 'default' => '', 'hint' => 'Design default', 'tab' => 'design'],
                'box_bg' => ['type' => 'color', 'label' => 'Box background', 'default' => '', 'hint' => 'Design default', 'tab' => 'design',
                    'condition' => ['field' => 'design', 'operator' => 'in', 'value' => ['boxes', 'flip']]],
                'box_border' => ['type' => 'color', 'label' => 'Box border', 'default' => '', 'hint' => 'None', 'tab' => 'design',
                    'condition' => ['field' => 'design', 'operator' => 'in', 'value' => ['boxes', 'flip']]],
                'ring_color' => ['type' => 'color', 'label' => 'Ring', 'default' => '', 'hint' => '#2271b1', 'tab' => 'design',
                    'condition' => ['field' => 'design', 'value' => 'circles']],
                'ring_track' => ['type' => 'color', 'label' => 'Ring track', 'default' => '', 'hint' => '#e5e7eb', 'tab' => 'design',
                    'condition' => ['field' => 'design', 'value' => 'circles']],
                'sep_color' => ['type' => 'color', 'label' => 'Separator', 'default' => '', 'hint' => 'Numbers colour', 'tab' => 'design'],
                'num_typo' => ['type' => 'typography', 'label' => 'Numbers typography', 'tab' => 'design'],
                'label_typo' => ['type' => 'typography', 'label' => 'Labels typography', 'tab' => 'design'],
                'msg_color' => ['type' => 'color', 'label' => 'Message colour', 'default' => '', 'hint' => 'Text colour', 'tab' => 'design'],
                'msg_typo' => ['type' => 'typography', 'label' => 'Message typography', 'tab' => 'design'],
                'margin' => ['type' => 'dimensions', 'label' => 'Margin', 'tab' => 'design'],
            ],
        ];

        return $elements;
    }

    public static function timezone(): string
    {
        return function_exists('cms_timezone') ? (string) cms_timezone() : (string) config('app.timezone', 'UTC');
    }

    /**
     * The settings, checked and filled in: everything the template and the script need.
     *
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    public static function config(array $s): array
    {
        $get = fn (string $k, $d) => ElementStyle::get($s, $k, $d);

        $units = is_array($s['units'] ?? null) ? $s['units'] : (is_string($s['units'] ?? null) ? explode(',', $s['units']) : array_keys(self::UNITS));
        $units = array_values(array_intersect(array_keys(self::UNITS), array_map('strval', $units)));
        if (!$units) {
            $units = array_keys(self::UNITS);
        }

        $days = $s['daily_days'] ?? null;
        $days = is_array($days) ? $days : (is_string($days) && $days !== '' ? explode(',', $days) : array_keys(self::WEEKDAYS));
        $days = array_values(array_unique(array_filter(array_map('intval', $days), fn ($d) => $d >= 0 && $d <= 6)));
        // No day ticked would be a countdown that never ends; it is read as every day.
        if (!$days) {
            $days = [0, 1, 2, 3, 4, 5, 6];
        }
        sort($days);

        $labels = [];
        foreach (self::UNITS as $unit => [, $label]) {
            $labels[$unit] = trim((string) $get('label_'.$unit, $label));
        }

        $everSeconds = ElementStyle::int($s['ever_days'] ?? 1, 1, 0, 365) * 86400
            + ElementStyle::int($s['ever_hours'] ?? 0, 0, 0, 23) * 3600
            + ElementStyle::int($s['ever_minutes'] ?? 0, 0, 0, 59) * 60;

        $url = trim((string) $get('expire_url', ''));
        $url = $url !== '' ? DualButton::safeHref(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';

        return [
            'mode' => ElementStyle::pick($get('mode', 'fixed'), self::MODES, 'fixed'),
            'endMs' => self::fixedEndMs((string) $get('end_at', '')),
            'everSeconds' => max(60, $everSeconds),
            'everRestart' => $get('ever_restart', 'stop') === 'restart',
            'dailyTime' => self::time((string) $get('daily_time', '17:00')),
            'dailyDays' => $days,
            'tz' => self::timezone(),
            'units' => $units,
            'labels' => $labels,
            'labelMode' => ElementStyle::pick($get('labels', 'below'), ['below', 'above', 'short', 'hide'], 'below'),
            'zero' => $get('leading_zero', 'show') !== 'hide',
            'expire' => ElementStyle::pick($get('expire_action', 'message'), self::EXPIRE, 'message'),
            'message' => (string) $get('expire_message', 'This offer has ended.'),
            'url' => $url,
            'design' => ElementStyle::pick($get('design', 'boxes'), self::DESIGNS, 'boxes'),
        ];
    }

    /** "HH:MM", or 17:00 for anything that is not a time. */
    public static function time(string $value): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '17:00';
    }

    /** A fixed deadline in epoch milliseconds, read in the site's timezone; null when there is none. */
    public static function fixedEndMs(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return (int) Carbon::parse(str_replace('T', ' ', $value), self::timezone())->getTimestamp() * 1000;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * The next daily deadline after $nowMs: today's time if it is still ahead and today is
     * one of the days, otherwise the first chosen day after. Mirrored by dailyEnd() in the
     * runtime script.
     *
     * @param  list<int>  $days
     */
    public static function dailyEndMs(string $time, array $days, string $tz, int $nowMs): int
    {
        [$h, $m] = array_map('intval', explode(':', self::time($time)));
        try {
            $now = Carbon::createFromTimestampMs($nowMs)->setTimezone($tz);
            for ($i = 0; $i < 8; $i++) {
                $at = $now->copy()->startOfDay()->addDays($i)->setTime($h, $m);
                if (in_array((int) $at->dayOfWeek, $days, true) && $at->getTimestamp() * 1000 > $nowMs) {
                    return $at->getTimestamp() * 1000;
                }
            }
        } catch (Throwable $e) {
        }

        return $nowMs;
    }

    /**
     * The numbers to draw at $nowMs. Evergreen starts full, as a first visit does.
     *
     * @param  array<string, mixed>  $cfg  from config()
     * @return array{values: array<string, int>, expired: bool, remaining: int}
     */
    public static function state(array $cfg, int $nowMs): array
    {
        $remaining = match ($cfg['mode']) {
            'evergreen' => $cfg['everSeconds'],
            'daily' => intdiv(max(0, self::dailyEndMs($cfg['dailyTime'], $cfg['dailyDays'], $cfg['tz'], $nowMs) - $nowMs), 1000),
            default => $cfg['endMs'] === null ? 0 : intdiv(max(0, $cfg['endMs'] - $nowMs), 1000),
        };

        return [
            'values' => self::split($remaining, $cfg['units']),
            'expired' => $remaining <= 0 && $cfg['mode'] === 'fixed',
            'remaining' => $remaining,
        ];
    }

    /**
     * Seconds shared out over the units on show. A unit left out is carried by the one
     * before it, so with Days hidden, Hours can read 50.
     *
     * @param  list<string>  $units
     * @return array<string, int>
     */
    public static function split(int $seconds, array $units): array
    {
        $out = [];
        $rest = max(0, $seconds);
        foreach (self::UNITS as $unit => [$size]) {
            if (!in_array($unit, $units, true)) {
                continue;
            }
            $out[$unit] = intdiv($rest, $size);
            $rest -= $out[$unit] * $size;
        }

        return $out;
    }

    /** A number as the element writes it: two digits when leading zeros are on. */
    public static function pad(int $value, bool $zero): string
    {
        return $zero ? str_pad((string) $value, 2, '0', STR_PAD_LEFT) : (string) $value;
    }

    /**
     * The designs' shared rules. Per-element values arrive as custom properties on the
     * element (--fcd-size, --fcd-num …), so this text is the same for every countdown and
     * a page with several carries identical copies, which the browser simply merges.
     *
     * Sizes follow --fcd-size: numbers, labels, ring and tiles all scale with the one
     * slider, and the typography fields override any of them.
     */
    public static function css(): string
    {
        return '.fcd{display:block;width:100%;box-sizing:border-box;line-height:1.2;--fcd-size:96px;--fcd-gap:14px;--fcd-radius:12px}'
            .'.fcd *,.fcd *::before,.fcd *::after{box-sizing:border-box}'
            .'.fcd-units{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:var(--fcd-justify,center);gap:var(--fcd-gap)}'
            .'.fcd-units[hidden],.fcd-expired[hidden]{display:none!important}'
            .'.fcd-item{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-width:var(--fcd-size)}'
            .'.fcd-num{display:block;font-size:calc(var(--fcd-size) * .42);font-weight:700;line-height:1;color:var(--fcd-num,#111827);font-variant-numeric:tabular-nums;letter-spacing:0}'
            .'.fcd-label{display:block;margin-top:calc(var(--fcd-size) * .08);font-size:calc(var(--fcd-size) * .11 + 3px);line-height:1.2;text-transform:uppercase;letter-spacing:.06em;color:var(--fcd-label,#6b7280)}'
            .'.fcd--labels-above .fcd-units{align-items:flex-end}'
            .'.fcd--labels-above .fcd-item{flex-direction:column-reverse}'
            .'.fcd--labels-above .fcd-label{margin-top:0;margin-bottom:calc(var(--fcd-size) * .08)}'
            .'.fcd--labels-short .fcd-item{flex-direction:row;align-items:baseline;gap:2px}'
            .'.fcd--labels-short .fcd-label{margin:0;text-transform:none;letter-spacing:0;font-size:calc(var(--fcd-size) * .2)}'
            .'.fcd-sep{display:flex;align-items:center;height:var(--fcd-sep-h,var(--fcd-size));font-size:calc(var(--fcd-size) * .4);font-weight:700;line-height:1;color:var(--fcd-sep,var(--fcd-num,#111827))}'
            .'.fcd-expired{text-align:center;color:var(--fcd-msg,inherit);font-size:18px;font-weight:600}'
            // Boxes
            .'.fcd--boxes .fcd-item{min-height:var(--fcd-size);padding:calc(var(--fcd-size) * .14) calc(var(--fcd-size) * .1);border-radius:var(--fcd-radius);background:var(--fcd-bg,#f3f4f6);border:1px solid var(--fcd-border,transparent)}'
            .'.fcd--boxes.fcd--labels-short .fcd-item{min-height:0}'
            // Circles
            .'.fcd--circles .fcd-item{width:var(--fcd-size);height:var(--fcd-size);min-width:var(--fcd-size);border-radius:50%}'
            .'.fcd--circles .fcd-num{font-size:calc(var(--fcd-size) * .32)}'
            .'.fcd--circles .fcd-label{margin-top:calc(var(--fcd-size) * .04);font-size:calc(var(--fcd-size) * .09 + 2px)}'
            .'.fcd-ring{position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg);overflow:visible;pointer-events:none}'
            .'.fcd-ring circle{fill:none;stroke-width:var(--fcd-ring-w,6px);vector-effect:non-scaling-stroke}'
            .'.fcd-track{stroke:var(--fcd-track,#e5e7eb)}'
            .'.fcd-bar{stroke:var(--fcd-ring,#2271b1);stroke-linecap:round;transition:stroke-dashoffset .6s ease}'
            // Flip clock
            .'.fcd--flip{--fcd-sep-h:calc(var(--fcd-size) * 1.05)}'
            .'.fcd--flip .fcd-item{min-width:0}'
            .'.fcd--flip .fcd-num{position:relative;display:flex;align-items:center;justify-content:center;min-width:var(--fcd-size);height:calc(var(--fcd-size) * 1.05);padding:0 calc(var(--fcd-size) * .1);'
                .'border-radius:var(--fcd-radius);background:var(--fcd-bg,#1f2937);color:var(--fcd-num,#ffffff);border:1px solid var(--fcd-border,transparent);'
                .'background-image:linear-gradient(to bottom,rgba(255,255,255,.07) 50%,rgba(0,0,0,.08) 50%);box-shadow:0 6px 14px rgba(0,0,0,.18);font-size:calc(var(--fcd-size) * .5);transform-origin:50% 50%}'
            .'.fcd--flip .fcd-num::after{content:"";position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.35)}'
            .'.fcd--flip .fcd-num.is-flip{animation:fcdFlip .5s ease}'
            .'@keyframes fcdFlip{0%{transform:perspective(400px) rotateX(0)}45%{transform:perspective(400px) rotateX(-90deg)}55%{transform:perspective(400px) rotateX(90deg)}100%{transform:perspective(400px) rotateX(0)}}'
            .'.fcd--flip.fcd--labels-short .fcd-label{color:var(--fcd-label,#6b7280)}'
            // Minimal
            .'.fcd--minimal{--fcd-sep-h:calc(var(--fcd-size) * .5)}'
            .'.fcd--minimal .fcd-item{min-width:calc(var(--fcd-size) * .7)}'
            .'.fcd--minimal .fcd-num{font-size:calc(var(--fcd-size) * .5);font-weight:800}'
            .'@media(prefers-reduced-motion:reduce){.fcd--flip .fcd-num.is-flip{animation:none}.fcd-bar{transition:none}}';
    }
}
