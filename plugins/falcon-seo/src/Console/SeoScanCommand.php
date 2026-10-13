<?php

namespace FalconSeo\Console;

use FalconSeo\Support\SiteScan;
use Illuminate\Console\Command;

/** Check the pages that are due (never checked, changed, or older than a week). Runs hourly. */
class SeoScanCommand extends Command
{
    protected $signature = 'falcon:seo-scan {--limit=10 : Pages to check in this run} {--no-links : Skip the broken-link check}';

    protected $description = 'Run the SEO check-up on pages that are due (SEO → Site Analysis)';

    public function handle(): int
    {
        $done = SiteScan::run(max(1, (int) $this->option('limit')), !$this->option('no-links'));
        $this->info($done.' page(s) checked.');

        return self::SUCCESS;
    }
}
