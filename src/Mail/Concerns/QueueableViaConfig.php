<?php

namespace FalconCms\Core\Mail\Concerns;

/**
 * Lets a Mailable be queued or sent synchronously based on the
 * `falcon-options.queue_mail` config flag.
 *
 * The mailable declares `implements ShouldQueue`; calling configureQueue() in
 * its constructor forces the connection to "sync" when queuing is disabled, so
 * the default (no worker) behaviour is unchanged and mail never gets stuck.
 */
trait QueueableViaConfig
{
    protected function configureQueue(): void
    {
        $this->connection = config('falcon-options.queue_mail', false)
            ? config('queue.default')
            : 'sync';

        // Retry transient SMTP failures a couple of times before failing.
        $this->tries = 3;
        $this->backoff = 10;

        // Only queue after the surrounding DB transaction commits, so an order
        // email never references a row that was rolled back.
        $this->afterCommit();
    }
}
