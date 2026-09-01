<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PruneActivityLogs extends Command
{
    protected $signature = 'falcon:prune-activity-logs {--before= : Delete everything older than this instead of the configured retention}';

    protected $description = 'Delete activity log entries older than the retention configured in Settings → General.';

    public function handle(): int
    {
        $before = $this->option('before');

        if ($before) {
            try {
                $cutoff = Carbon::parse($before, cms_timezone())->utc();
            } catch (\Throwable $e) {
                $this->error("Could not read --before={$before} as a date.");

                return self::FAILURE;
            }
        } else {
            $cutoff = falcon_activity_log_cutoff();
        }

        if (!$cutoff) {
            $this->info('Automatic removal is off — nothing to prune.');

            return self::SUCCESS;
        }

        $total = 0;

        // Chunked so a long-neglected table never goes at it in one locking statement.
        do {
            $count = DB::table('activity_logs')->where('created_at', '<', $cutoff)->limit(5000)->delete();
            $total += $count;
        } while ($count > 0);

        $this->info("Pruned {$total} activity log entr(ies) older than ".cms_date($cutoff).'.');

        return self::SUCCESS;
    }
}
