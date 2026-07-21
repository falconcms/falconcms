<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Support\PluginManager;
use Illuminate\Console\Command;

class PluginActivate extends Command
{
    protected $signature = 'plugin:activate {slug}';

    protected $description = 'Activate a plugin by slug (runs its migrations)';

    public function handle(PluginManager $plugins): int
    {
        $result = $plugins->activate($this->argument('slug'));

        $result['ok'] ? $this->info($result['message']) : $this->error($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
