<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;

/**
 * Guards the "schedule only on future time" rule (server-side, timezone-safe).
 */
class ScheduleStatusTest extends TestCase
{
    public function test_future_publish_date_becomes_scheduled(): void
    {
        $status = Post::resolveStatusForSchedule('published', Carbon::now()->addMinutes(10));
        $this->assertSame('scheduled', $status);
    }

    public function test_past_publish_date_becomes_published(): void
    {
        $status = Post::resolveStatusForSchedule('published', Carbon::now()->subMinutes(10));
        $this->assertSame('published', $status);
    }

    public function test_scheduled_in_the_past_is_published(): void
    {
        // A "scheduled" item whose time already passed should flip to published.
        $status = Post::resolveStatusForSchedule('scheduled', Carbon::now()->subSecond());
        $this->assertSame('published', $status);
    }

    public function test_draft_stays_draft_regardless_of_date(): void
    {
        $this->assertSame('draft', Post::resolveStatusForSchedule('draft', Carbon::now()->addDay()));
    }

    public function test_empty_date_keeps_status(): void
    {
        $this->assertSame('published', Post::resolveStatusForSchedule('published', null));
    }
}
