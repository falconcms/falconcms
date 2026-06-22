<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAnalytics extends Command
{
    protected $signature = 'falcon:prune-analytics {--days= : Override retention days (min 7)}';
    protected $description = 'Delete raw analytics visits older than the retention window to keep the table lean.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: get_cms_option('analytics_retention_days', 365));
        if ($days < 7) $days = 7; // safety floor — never prune very recent data

        $cutoff = now()->subDays($days);
        $total  = 0;

        // Chunked delete so a huge backlog never locks the table in one statement.
        do {
            $count = DB::table('cms_analytics')->where('created_at', '<', $cutoff)->limit(5000)->delete();
            $total += $count;
        } while ($count > 0);

        $this->info("Pruned {$total} analytics row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
