<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;

/**
 * Guards timezone-aware publishing: the admin picks a time in the CMS timezone,
 * we store it as UTC, and "future" is decided correctly.
 *
 * No database needed: with no `timezone` row stored, cms_timezone() falls back to
 * config('app.timezone'), which we set here to Asia/Dhaka (UTC+6).
 */
class PublishTimezoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Dhaka']);
    }

    public function test_future_local_time_is_scheduled_and_stored_as_utc(): void
    {
        // User picks "now + 10 min" in Dhaka local time.
        $localPick = Carbon::now('Asia/Dhaka')->addMinutes(10)->format('Y-m-d H:i:s');

        $result = falcon_normalize_publish(['status' => 'published', 'published_at' => $localPick]);

        // Future -> scheduled
        $this->assertSame('scheduled', $result['status']);

        // Stored value equals the Dhaka pick converted to UTC (6h earlier).
        $expectedUtc = Carbon::parse($localPick, 'Asia/Dhaka')->utc()->format('Y-m-d H:i:s');
        $this->assertSame($expectedUtc, $result['published_at']);
    }

    public function test_past_local_time_is_published(): void
    {
        $localPick = Carbon::now('Asia/Dhaka')->subMinutes(10)->format('Y-m-d H:i:s');

        $result = falcon_normalize_publish(['status' => 'published', 'published_at' => $localPick]);

        $this->assertSame('published', $result['status']);
    }
}
