<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Support\PluginManager;
use Illuminate\Console\Command;

class PluginDeactivate extends Command
{
    protected $signature = 'plugin:deactivate {slug}';

    protected $description = 'Deactivate a plugin by slug';

    public function handle(PluginManager $plugins): int
    {
        $result = $plugins->deactivate($this->argument('slug'));

        $result['ok'] ? $this->info($result['message']) : $this->error($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
