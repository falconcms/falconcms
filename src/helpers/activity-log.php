<?php

/**
 * Activity log: settings, writing and pruning.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists('falcon_activity_log_enabled')) {
    /**
     * Whether activity logging is switched on in Settings → General.
     *
     * Defaults to on: a site upgrading into this option was already logging, and
     * silently stopping would leave a gap in the audit trail nobody asked for.
     */
    function falcon_activity_log_enabled(): bool
    {
        return get_cms_option('activity_log_enabled', '1') === '1';
    }
}

if (!function_exists('falcon_activity_log_cutoff')) {
    /**
     * The moment before which activity logs should be deleted, or null when nothing
     * is due to be removed.
     *
     * The presets are ages ("older than 24 hours"); Custom is an absolute moment the
     * admin picked. Both end up as the same thing — one instant, and everything older
     * than it goes. The custom value is entered and read in the CMS timezone
     * (Settings → General), so a cutoff of "1 Sep, 10:00 PM" means ten at night where
     * the site is, not on whatever clock the server happens to keep.
     */
    function falcon_activity_log_cutoff(): ?Carbon
    {
        if (!falcon_activity_log_enabled() || get_cms_option('activity_log_autoprune', '0') !== '1') {
            return null;
        }

        $retention = (string) get_cms_option('activity_log_retention', '72');

        if ($retention === 'custom') {
            $raw = trim((string) get_cms_option('activity_log_prune_before', ''));
            if ($raw === '') {
                return null;
            }

            try {
                return Carbon::parse($raw, cms_timezone())->utc();
            } catch (Throwable $e) {
                // A malformed stored value must not start deleting from the epoch.
                return null;
            }
        }

        $hours = (int) $retention;

        // Anything unrecognised falls back to the longest preset rather than the
        // shortest: a bad value should keep more history, never less.
        if (!in_array($hours, [24, 48, 72], true)) {
            $hours = 72;
        }

        return cms_now()->subHours($hours)->utc();
    }
}

if (!function_exists('falcon_prune_activity_logs')) {
    /**
     * Delete activity log entries older than the configured cutoff. Returns how many
     * rows went, so a caller can say so rather than leaving the admin guessing.
     *
     * Deleted in chunks: a table left alone for months should not go at it in one
     * statement and lock everyone else out. $maxBatches caps that work for callers
     * running inside a web request; 0 means sweep until there is nothing left.
     */
    function falcon_prune_activity_logs(?Carbon $cutoff = null, int $maxBatches = 0): int
    {
        $cutoff ??= falcon_activity_log_cutoff();

        if (!$cutoff) {
            return 0;
        }

        $total = 0;
        $batches = 0;

        do {
            $count = DB::table('activity_logs')
                ->where('created_at', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $total += $count;
            $batches++;
        } while ($count > 0 && ($maxBatches === 0 || $batches < $maxBatches));

        return $total;
    }
}

if (!function_exists('falcon_prune_activity_logs_throttled')) {
    /**
     * The hourly-throttled prune behind the cron-independent fallback, kept here so
     * it can be exercised without standing up a whole request.
     *
     * The cutoff is resolved BEFORE the lock is claimed, and that order is the whole
     * point: claim first and a request arriving while automatic removal is switched
     * off burns the hour on nothing — then switching it on a minute later removes
     * nothing until that hour is up, which reads exactly like the feature being
     * broken. One batch only; the scheduled command does the full sweep.
     */
    function falcon_prune_activity_logs_throttled(): int
    {
        $cutoff = falcon_activity_log_cutoff();

        if (!$cutoff) {
            return 0;
        }

        if (!Cache::add('falcon_activity_log_prune_lock', 1, now()->addHour())) {
            return 0;
        }

        return falcon_prune_activity_logs($cutoff, 1);
    }
}

if (!function_exists('falcon_log_activity')) {
    function falcon_log_activity($action, $description, $model = null, $properties = [])
    {
        // One choke point for every caller in the package: switched off in settings
        // means no row is written at all, not merely hidden from the screen.
        if (!falcon_activity_log_enabled()) {
            return null;
        }

        try {
            $ip = request()->ip();
            $country = null;
            $countryCode = null;

            // Simple IP to Country Cache/Lookup
            if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
                try {
                    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,countryCode");
                    if ($response) {
                        $data = json_decode($response, true);
                        if ($data && $data['status'] === 'success') {
                            $country = $data['country'];
                            $countryCode = $data['countryCode'];
                        }
                    }
                } catch (Exception $e) {
                }
            }

            return ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->id : null,
                'description' => $description,
                'properties' => $properties,
                'ip_address' => $ip,
                'country' => $country,
                'country_code' => $countryCode,
                'user_agent' => request()->userAgent(),
            ]);
        } catch (Exception $e) {
            return null;
        }
    }
}
