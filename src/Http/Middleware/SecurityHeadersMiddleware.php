<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeadersMiddleware
{
    /**
     * A baseline policy that restricts nothing the builder relies on (inline scripts and
     * styles, fonts, embeds, analytics) but shuts the doors that are never legitimately
     * used: framing by other sites, <base> hijacking, plugin objects and plain-http assets.
     * Sites that want a stricter policy replace it through the `falcon_content_security_policy`
     * filter; returning an empty string sends none.
     */
    public const CONTENT_SECURITY_POLICY = "upgrade-insecure-requests; frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! $response->headers->has('Content-Security-Policy')) {
            $csp = function_exists('apply_falcon_filters')
                ? (string) apply_falcon_filters('falcon_content_security_policy', self::CONTENT_SECURITY_POLICY, $request)
                : self::CONTENT_SECURITY_POLICY;

            if ($csp !== '') {
                $response->headers->set('Content-Security-Policy', $csp);
            }
        }

        // PHP adds X-Powered-By itself (expose_php) outside the response object, so removing
        // it from the Symfony headers alone never took it off the wire.
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
