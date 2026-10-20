<?php

/**
 * Permissions: what the signed-in user may do on the admin screen in front of them.
 *
 * Loaded by src/helpers.php.
 */

use FalconCms\Core\Support\MenuAccess;

if (!function_exists('falcon_admin_can_write')) {
    /**
     * Whether the signed-in user may change things on an admin screen — false on a list whose
     * section has an Add New the user does not hold, where the list is read-only.
     *
     * A list view wraps everything that changes something (its Add New button, bulk actions,
     * row Edit and Delete links, upload areas) in this, so a role given the list alone sees
     * the list and none of the controls it could not use. AdminMiddleware refuses those
     * requests anyway; this keeps the screen from offering them.
     *
     * Defaults to the current request's path and post type; any screen whose menu has an Add
     * New beside it — a core one or a plugin's — follows without further wiring.
     */
    function falcon_admin_can_write(?string $path = null, ?string $type = null): bool
    {
        $request = request();
        $path ??= $request->path();
        $type ??= $request->query('type') ?? $request->query('cpt_slug');

        try {
            return (new MenuAccess)->canWriteAt(auth()->user(), $path, $type);
        } catch (Throwable $e) {
            // A broken menu registration must not take a list screen down; the middleware
            // still refuses whatever the user may not do.
            return true;
        }
    }
}
