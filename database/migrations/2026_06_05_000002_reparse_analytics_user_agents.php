<?php

use Illuminate\Database\Migrations\Migration;
use FalconCms\Core\Models\Analytics;
use FalconCms\Core\Support\UserAgentParser;

/**
 * Re-derives os / browser / device_type for every existing analytics row from its stored
 * user_agent, using the smarter UserAgentParser. Fixes historical mislabels such as Android
 * counted as "Linux" and iPhone counted as "Mac OS", so the Analytics charts show real data.
 *
 * Uses the Analytics model so the correct table name is always resolved.
 * Idempotent: safe to re-run (it recomputes from the stored user agent each time).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(Analytics::class)) return;

        try {
            // Load id + user_agent up front (avoids "commands out of sync" while updating).
            $rows = Analytics::query()->select('id', 'user_agent')->get();
        } catch (\Throwable $e) {
            return; // analytics table not present yet
        }

        foreach ($rows as $row) {
            Analytics::query()->where('id', $row->id)->update([
                'os'          => UserAgentParser::os($row->user_agent),
                'browser'     => UserAgentParser::browser($row->user_agent),
                'device_type' => UserAgentParser::device($row->user_agent),
            ]);
        }
    }

    public function down(): void
    {
        // Re-parsing is non-destructive (user_agent is preserved); nothing to revert.
    }
};
