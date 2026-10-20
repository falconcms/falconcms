<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Support\Instagram;
use Illuminate\Console\Command;

/**
 * Hourly: fetch the connected Instagram account's latest posts when the saved copy is older
 * than the cache time, and renew the access token before it lapses. --force fetches regardless.
 */
class InstagramSync extends Command
{
    protected $signature = 'falcon:instagram-sync {--force : Fetch even if the saved posts are still fresh}';

    protected $description = 'Fetch the latest Instagram posts for the Instagram Feed element and renew the access token';

    public function handle(): int
    {
        if (!Instagram::licensed() || !Instagram::connected()) {
            $this->line('No Instagram account connected (or no Pro licence); nothing to do.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !Instagram::stale()) {
            Instagram::refreshTokenIfDue();
            $this->line('Posts are still fresh.');

            return self::SUCCESS;
        }

        if (Instagram::sync()) {
            $this->info('Fetched '.count(Instagram::media()).' Instagram posts.');

            return self::SUCCESS;
        }

        $this->error('Could not fetch the posts: '.Instagram::lastError());

        return self::FAILURE;
    }
}
