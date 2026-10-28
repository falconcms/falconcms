<?php

namespace FalconAi\Support;

/**
 * Who may do what.
 *
 *  - The settings and usage screens: the `manage_ai` permission (the AI menu, so it is offered
 *    on Users → Roles; administrators have it).
 *  - AI writing (the editor's Write with AI, SEO suggestions): administrators, and the roles
 *    chosen in the settings — and only someone who can write posts or pages, because the text
 *    goes into a post. Each request costs money on the site's own account, so it is not open to
 *    every account that can sign in.
 */
final class Access
{
    public static function canWrite($user): bool
    {
        if (!$user || !method_exists($user, 'hasPermission')) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (!$user->hasPermission('manage_posts') && !$user->hasPermission('manage_pages')) {
            return false;
        }
        foreach (Settings::writerRoles() as $role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public static function canManage($user): bool
    {
        return $user && method_exists($user, 'hasPermission') && $user->hasPermission(Settings::PERMISSION);
    }
}
