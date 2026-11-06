<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Support\PageTime;
use FalconCms\Core\Support\PostLock;
use FalconCms\Core\Support\PostViews;
use Illuminate\Console\Command;

/** Daily: drop day-by-day post view figures older than the kept number of days. */
class PrunePostViews extends Command
{
    protected $signature = 'falcon:prune-post-views';

    protected $description = 'Remove daily post-view figures older than Settings → General → Post Views keeps';

    public function handle(): int
    {
        $this->info('Removed '.PostViews::prune().' old daily view rows.');
        $this->info('Removed '.PageTime::prune().' old daily time rows.');
        $this->info('Removed '.PostLock::prune().' abandoned edit locks.');

        return self::SUCCESS;
    }
}
