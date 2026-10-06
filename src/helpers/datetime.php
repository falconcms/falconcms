<?php

/**
 * Site timezone and date helpers.
 *
 * Loaded by src/helpers.php.
 */

use Carbon\Carbon;
use FalconCms\Core\Models\Post;

if (!function_exists('cms_timezone')) {
    /**
     * The CMS display/input timezone chosen in Settings → General.
     * Storage stays UTC; this is only used to render/interpret dates for the admin.
     */
    function cms_timezone(): string
    {
        try {
            $tz = get_cms_option('timezone');
            if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
                return $tz;
            }
        } catch (Throwable $e) {
        }

        return config('app.timezone') ?: 'UTC';
    }
}

if (!function_exists('cms_now')) {
    /** Current time in the CMS timezone. Use for display and for building day-boundary queries. */
    function cms_now(): Illuminate\Support\Carbon
    {
        return Illuminate\Support\Carbon::now(cms_timezone());
    }
}

if (!function_exists('cms_date')) {
    /**
     * Format a datetime in the CMS timezone.
     * Accepts Carbon, DateTime, or a date string. Returns '—' for null/empty.
     */
    function cms_date($dt, string $format = 'M j, Y H:i'): string
    {
        if (!$dt) {
            return '—';
        }
        $c = $dt instanceof Carbon ? $dt : Illuminate\Support\Carbon::parse($dt);

        return $c->timezone(cms_timezone())->format($format);
    }
}

if (!function_exists('falcon_timezone_list')) {
    /**
     * All PHP timezones grouped by region, each labelled with its CURRENT UTC offset
     * (e.g. "(UTC+06:00) Asia/Dhaka"). Offsets are computed live, so DST/changes stay correct.
     *
     * @return array<string, array<string,string>> region => [identifier => label]
     */
    function falcon_timezone_list(): array
    {
        $groups = [];
        foreach (timezone_identifiers_list() as $tz) {
            try {
                $offset = (new DateTime('now', new DateTimeZone($tz)))->getOffset();
            } catch (Throwable $e) {
                continue;
            }
            $sign = $offset < 0 ? '-' : '+';
            $abs = abs($offset);
            $label = sprintf('(UTC%s%02d:%02d) %s', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60), $tz);
            $region = strpos($tz, '/') !== false ? explode('/', $tz)[0] : 'Other';
            $groups[$region][$tz] = $label;
        }

        return $groups;
    }
}

if (!function_exists('falcon_normalize_publish')) {
    /**
     * Normalise a save payload's publish fields:
     *  - interpret the incoming naive `published_at` in the CMS timezone and convert to UTC for storage,
     *  - then set `status` (scheduled vs published) from that UTC time on the server.
     * Keeps the DB in UTC while letting the admin work in their chosen timezone.
     */
    function falcon_normalize_publish(array $data): array
    {
        if (!empty($data['published_at'])) {
            try {
                $data['published_at'] = Illuminate\Support\Carbon::parse($data['published_at'], cms_timezone())
                    ->utc()->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
            }
        }
        $data['status'] = Post::resolveStatusForSchedule(
            $data['status'] ?? null,
            $data['published_at'] ?? null
        );

        return $data;
    }
}
