<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Optional front-end HTML optimisations, each driven by a Customizer → Performance toggle and
 * each off by default, so a site only changes when its owner asks. The work happens on the
 * finished HTML, not in the templates, so it applies the same to the default theme and to a
 * page built with the builder, and touches nothing when every toggle is off.
 *
 *  - perf_lazy_images:  adds loading="lazy" + decoding="async" to <img> that lack them, so
 *    off-screen images are not fetched until needed. The first image is left eager so the
 *    largest-contentful paint is not delayed.
 *  - perf_defer_js:     adds defer to external <script src> that is not already defer/async and
 *    is not on the must-run-early list, so scripts stop blocking the parser.
 *  - perf_minify_html:  collapses the whitespace between tags.
 *
 * Only successful, non-admin, HTML GET responses are touched; never JSON, downloads, the admin,
 * or anything inside a logged-in session where correctness matters more than bytes.
 */
class HtmlOptimizeMiddleware
{
    /** Scripts that must run before the page paints — never deferred. */
    private const DEFER_SKIP = ['tailwind', 'cdn.tailwindcss', 'alpine', 'turnstile', 'recaptcha'];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$this->optimizable($request, $response)) {
            return $response;
        }

        $lazy = get_cms_option('perf_lazy_images', '0') === '1';
        $defer = get_cms_option('perf_defer_js', '0') === '1';
        $minify = get_cms_option('perf_minify_html', '0') === '1';
        if (!$lazy && !$defer && !$minify) {
            return $response;
        }

        $html = $response->getContent();
        if (!is_string($html) || $html === '') {
            return $response;
        }

        if ($lazy) {
            $html = $this->lazyImages($html);
        }
        if ($defer) {
            $html = $this->deferScripts($html);
        }
        if ($minify) {
            $html = $this->minify($html);
        }

        $response->setContent($html);

        return $response;
    }

    private function optimizable(Request $request, $response): bool
    {
        if (!$request->isMethod('get') || $request->is('admin*') || $request->is('api*') || auth()->check()) {
            return false;
        }
        if (!method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 200) {
            return false;
        }
        // Only real HTML — not a JSON payload, a file download or a redirect.
        $type = strtolower((string) $response->headers->get('Content-Type'));

        return $type === '' || str_contains($type, 'text/html');
    }

    /** Add loading="lazy" + decoding="async" to images that have neither, leaving the first eager. */
    private function lazyImages(string $html): string
    {
        $seen = 0;

        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$seen) {
            $tag = $m[0];
            $seen++;
            // The first image is usually above the fold (hero, logo): keeping it eager avoids
            // pushing back the largest-contentful paint.
            if ($seen === 1 || stripos($tag, 'loading=') !== false) {
                return $tag;
            }
            $add = ' loading="lazy"';
            if (stripos($tag, 'decoding=') === false) {
                $add .= ' decoding="async"';
            }

            return preg_replace('/\s*\/?>$/', $add.'>', $tag, 1);
        }, $html) ?? $html;
    }

    /** Add defer to parser-blocking external scripts that can safely wait. */
    private function deferScripts(string $html): string
    {
        return preg_replace_callback('/<script\b[^>]*\bsrc=["\'][^"\']+["\'][^>]*><\/script>/i', function ($m) {
            $tag = $m[0];
            if (preg_match('/\b(defer|async|type=["\']module["\'])/i', $tag)) {
                return $tag;
            }
            foreach (self::DEFER_SKIP as $skip) {
                if (stripos($tag, $skip) !== false) {
                    return $tag;
                }
            }

            return preg_replace('/<script\b/i', '<script defer', $tag, 1);
        }, $html) ?? $html;
    }

    /** Collapse whitespace between tags, but never inside pre / textarea / script / style. */
    private function minify(string $html): string
    {
        // Protect the elements whose whitespace is meaningful.
        $protected = [];
        $html = preg_replace_callback('/<(pre|textarea|script|style)\b[^>]*>.*?<\/\1>/is', function ($m) use (&$protected) {
            $key = "\x01".count($protected)."\x01";
            $protected[$key] = $m[0];

            return $key;
        }, $html) ?? $html;

        // Whitespace between tags, and runs of whitespace within text.
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;
        $html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;
        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html) ?? $html; // keep IE conditionals

        return strtr($html, $protected);
    }
}
