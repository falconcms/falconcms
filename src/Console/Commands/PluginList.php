<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Support\PluginManager;
use Illuminate\Console\Command;

class PluginList extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List discovered plugins and their status';

    public function handle(PluginManager $plugins): int
    {
        $all = $plugins->all();

        if (empty($all)) {
            $this->info('No plugins found in ' . $plugins->path() . '.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($all as $slug => $p) {
            $rows[] = [
                $slug,
                $p['name'] ?? $slug,
                $p['version'] ?? '—',
                $p['active'] ? 'active' : ($p['installed'] ? 'inactive' : 'not installed'),
            ];
        }

        $this->table(['Slug', 'Name', 'Version', 'Status'], $rows);
        return self::SUCCESS;
    }
}
