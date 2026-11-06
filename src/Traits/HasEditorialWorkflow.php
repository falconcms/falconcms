<?php

namespace FalconCms\Core\Traits;

use FalconCms\Core\Support\Editorial;
use FalconCms\Core\Support\PostChanges;

/**
 * The review workflow on the model. A trait because its boot method runs for Post and for every
 * class extending it: Page defines its own booted(), which would hide one on Post.
 */
trait HasEditorialWorkflow
{
    protected static function bootHasEditorialWorkflow(): void
    {
        static::saving(fn ($post) => Editorial::gate($post));
        static::saved(fn ($post) => Editorial::recordTransition($post));
        static::forceDeleted(fn ($post) => PostChanges::forget((int) $post->id));
    }
}
