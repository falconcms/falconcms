<?php

namespace FalconSeo\Console;

use FalconSeo\Support\RankTracker;
use Illuminate\Console\Command;

/** The Rank Tracker's checks (Pro, with DataForSEO): the keywords that are due, a few at a time. */
class RankCheckCommand extends Command
{
    protected $signature = 'falcon:seo-rank-check {--limit=20 : Keywords checked at most in this run}';

    protected $description = 'Look up the tracked keywords that are due in Google (DataForSEO)';

    public function handle(): int
    {
        if (!RankTracker::available()) {
            $this->line('The Rank Tracker needs FalconCMS Pro and a DataForSEO login in SEO → Settings.');

            return self::SUCCESS;
        }
        $checked = RankTracker::runDue(max(1, (int) $this->option('limit')));
        $this->info("Checked {$checked} keyword(s).");

        return self::SUCCESS;
    }
}
