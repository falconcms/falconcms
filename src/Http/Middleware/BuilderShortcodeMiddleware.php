<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use FalconCms\Core\Services\BuilderShortcodeConverter;

class BuilderShortcodeMiddleware
{
    /**
     * Server-side fallback: converts [falcon_section] shortcodes to builder JSON
     * before the request reaches the controller. This ensures that saving from
     * the rich editor with shortcode content always produces valid builder output.
     */
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && $request->has('content')) {
            $content = $request->input('content', '');

            if (!empty($content) && !BuilderShortcodeConverter::isBuilderJson($content)) {
                // Normalize HTML-entity-encoded brackets (some editors encode [ as &#91; etc.)
                $normalized = str_replace(
                    ['&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;'],
                    ['[',     ']',     '[',         ']',         '[',       ']'],
                    $content
                );

                if (BuilderShortcodeConverter::isBuilderShortcode($normalized)) {
                    $json = BuilderShortcodeConverter::shortcodesToJson($normalized);
                    $request->merge(['content' => $json]);
                }
            }
        }

        return $next($request);
    }
}
