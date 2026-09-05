<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hide the site while it is being worked on.
 *
 * Everyone is turned away except the people doing the work. That used to mean every
 * logged-in user, which on a site with accounts is not the same thing at all: a customer
 * who registered last year, or anyone who signed up in the minute before maintenance was
 * switched on, walked straight through a page meant to hide a half-finished site. The
 * bypass now belongs to whoever can turn the setting on — which is who it was for.
 *
 * That leaves the person who switched it on unable to see the difference, since they
 * still get the site rather than the notice. So they get a marker instead: a small bar
 * saying the site is dark and only they can see it. Without it, "is maintenance mode
 * working?" is a question only answerable by opening a private window, and the honest
 * answer people reach first is that it is broken.
 */
class MaintenanceModeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (get_cms_option('maintenance_mode', '0') !== '1') {
            return $next($request);
        }

        if ($this->canWorkOnTheSite()) {
            return $this->withBanner($next($request));
        }

        $message = get_cms_option('maintenance_message', "We are currently performing scheduled maintenance. We'll be back shortly!");

        return response()->view('falcon-cms::maintenance', ['message' => $message], 503);
    }

    /**
     * Who gets through: administrators.
     *
     * Not "has the manage_settings permission", which was the first answer and the wrong
     * one — on a real site the subscriber role turned out to hold eighteen permissions
     * including that one, so it let through exactly the people it was meant to stop.
     * isAdmin() is a question about the role itself, and it is the same one the admin
     * area asks before letting anyone in unrestricted.
     *
     * An application that has not applied the CMS permission trait to its user model
     * cannot answer this, and there the old rule stands. Being unable to view your own
     * site while fixing it is worse than the hole, and the admin area is on its own
     * routes — so a site can always be recovered either way.
     */
    private function canWorkOnTheSite(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (!method_exists($user, 'isAdmin')) {
            return true;
        }

        return (bool) $user->isAdmin();
    }

    /**
     * Mark the page for the one person who is allowed to see it.
     *
     * Only on a page that was actually rendered for a reader: a redirect, a download and
     * a JSON response from the same route group all pass through untouched.
     */
    private function withBanner(Response $response): Response
    {
        // A redirect carries an HTML body of its own that nobody ever reads, so it looks
        // like a page and is not one.
        if (!$response->isSuccessful()) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        if ($type !== '' && !str_contains($type, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || stripos($content, '</body>') === false) {
            return $response;
        }

        $banner = '<div style="position:fixed;left:0;right:0;bottom:0;z-index:2147483647;'
            .'background:#7c2d12;color:#fff;font:600 13px/1.4 system-ui,-apple-system,Segoe UI,sans-serif;'
            .'padding:10px 16px;text-align:center;box-shadow:0 -2px 12px rgba(0,0,0,.25);">'
            .'Maintenance mode is on — visitors see the maintenance page. You can see the site because you are an administrator.'
            .'</div>';

        // Before the last </body>, so it is not swallowed by one inside a script or a
        // comment earlier in the page.
        $at = strripos($content, '</body>');
        $response->setContent(substr($content, 0, $at).$banner.substr($content, $at));

        return $response;
    }
}
