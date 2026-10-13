<?php

namespace FalconSeo\Console;

use FalconSeo\Support\SeoReport;
use Illuminate\Console\Command;

/** The weekly SEO email (Pro): Monday mornings while it is turned on in SEO → Settings. */
class SeoReportCommand extends Command
{
    protected $signature = 'falcon:seo-report {--force : Send even when the weekly email is turned off}';

    protected $description = 'Email the weekly SEO summary (scores, drops, broken links)';

    public function handle(): int
    {
        if (!$this->option('force') && !SeoReport::enabled()) {
            $this->line('The weekly SEO email is off (or needs FalconCMS Pro).');

            return self::SUCCESS;
        }
        $sent = SeoReport::send();
        $this->info($sent ? "Sent to {$sent} address(es)." : 'Nothing sent: no recipients, or no page checked yet.');

        return self::SUCCESS;
    }
}
