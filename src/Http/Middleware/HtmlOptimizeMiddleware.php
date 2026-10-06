<?php

namespace FalconCms\Core\Http\Middleware;

use Closure;
use FalconCms\Core\Support\LocalGoogleFonts;
use FalconCms\Core\Support\LucideIcons;
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
 *  - perf_inline_icons: swaps the icon libraries' stylesheets for the few rules the page uses.
 *  - perf_local_google_fonts: serves Google Fonts from the site itself, their CSS inline.
 *
 * Only successful, non-admin, HTML GET responses are touched; never JSON, downloads, the admin,
 * or anything inside a logged-in session where correctness matters more than bytes.
 */
class HtmlOptimizeMiddleware
{
    /**
     * Set on a response that is a stand-in while something it needs is still being prepared
     * (local Google Fonts on their first request). PageCacheMiddleware does not keep it.
     */
    public const PROVISIONAL_HEADER = 'X-Falcon-Provisional';

    /** How many images, from the top of the page, are never made lazy. */
    private const EAGER_IMAGES = 3;

    /**
     * Scripts that must run before the page paints — never deferred. "data-no-defer" lets any
     * script opt out: a file moved out of an inline <script> keeps running exactly where it did.
     */
    private const DEFER_SKIP = ['tailwind', 'cdn.tailwindcss', 'alpine', 'turnstile', 'recaptcha', 'data-no-defer'];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$this->optimizable($request, $response)) {
            return $response;
        }

        $lazy = get_cms_option('perf_lazy_images', '0') === '1';
        $defer = get_cms_option('perf_defer_js', '0') === '1';
        $minifyHtml = get_cms_option('perf_minify_html', '0') === '1';
        $minifyCss = get_cms_option('perf_minify_css', '0') === '1';
        $conditional = get_cms_option('perf_conditional_assets', '0') === '1';
        $inlineIcons = get_cms_option('perf_inline_icons', '0') === '1';
        $localFonts = get_cms_option('perf_local_google_fonts', '0') === '1';
        if (!$lazy && !$defer && !$minifyHtml && !$minifyCss && !$conditional && !$inlineIcons && !$localFonts) {
            return $response;
        }

        $html = $response->getContent();
        if (!is_string($html) || $html === '') {
            return $response;
        }

        if ($conditional) {
            $html = $this->inlineLucide($html);
            $html = $this->conditionalSweetAlert($html);
        }
        if ($inlineIcons) {
            $html = $this->inlineIcons($html);
        }
        if ($localFonts) {
            [$html, $complete] = $this->localGoogleFonts($html);
            if (!$complete) {
                $response->headers->set(self::PROVISIONAL_HEADER, '1');
            }
        }
        if ($lazy) {
            $html = $this->lazyImages($html);
        }
        if ($defer) {
            $html = $this->deferScripts($html);
        }
        // CSS minify (inline <style>) and HTML minify are independent switches, but both are done
        // in one pass so the HTML collapse never disturbs CSS it should have shrunk first.
        if ($minifyHtml || $minifyCss) {
            $html = $this->minify($html, $minifyHtml, $minifyCss);
        }

        $response->setContent($html);

        return $response;
    }

    private function optimizable(Request $request, $response): bool
    {
        // Applies to logged-in visitors too: minifying, deferring and lazy-loading change how
        // the page is delivered, not what it says, so they are safe for everyone — unlike the
        // page cache, which must never serve one visitor's page to another. Only the admin and
        // the API are left out.
        if (!$request->isMethod('get') || $request->is('admin*') || $request->is('api*')) {
            return false;
        }
        if (!method_exists($response, 'getStatusCode') || $response->getStatusCode() !== 200) {
            return false;
        }
        // Only real HTML — not a JSON payload, a file download or a redirect.
        $type = strtolower((string) $response->headers->get('Content-Type'));

        return $type === '' || str_contains($type, 'text/html');
    }

    /**
     * Render the theme's Lucide icons (<i data-lucide="x">) as inline SVG so the 390 KB
     * lucide.min.js need not load. An icon whose SVG we do not have is left as-is and the full
     * library is kept to draw it, so nothing a page uses can disappear; when every icon on the
     * page is one we have, the library's <script> is dropped entirely.
     */
    private function inlineLucide(string $html): string
    {
        if (stripos($html, 'data-lucide') === false) {
            return $html;
        }

        $html = preg_replace_callback('/<i\b([^>]*?)data-lucide="([a-z0-9-]+)"([^>]*?)>\s*<\/i>/i',
            function ($m) {
                $name = $m[2];
                if (!LucideIcons::has($name)) {
                    return $m[0]; // unknown → leave for the full library
                }
                // Carry the element's own class onto the SVG so utility sizing (w-5 h-5) still
                // applies, exactly as the Lucide script does.
                $attrs = $m[1].$m[3];
                $class = preg_match('/class="([^"]*)"/i', $attrs, $c) ? $c[1].' ' : '';

                return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"'
                    .' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
                    .' class="'.$class.'lucide lucide-'.$name.'" aria-hidden="true">'.LucideIcons::ICONS[$name].'</svg>';
            }, $html) ?? $html;

        // If no Lucide placeholders are left, the library has nothing to do — drop its script.
        if (stripos($html, 'data-lucide') === false) {
            $html = preg_replace('#<script\b[^>]*\bsrc="[^"]*lucide[^"]*\.js"[^>]*>\s*</script>#i', '', $html) ?? $html;
        }

        return $html;
    }

    /**
     * Drop the ~45 KB SweetAlert2 bundle on pages that never call it. The theme wraps the library
     * and its one patch in <!--falcon-swal--> … <!--/falcon-swal-->; everything OUTSIDE that block
     * is the page's own markup, so if none of it references Swal the bundle is removed, and if any
     * of it does (a storefront handler, a builder card, anything added later) the block is kept
     * verbatim. Either way the marker comments themselves are stripped. This sees the finished page,
     * so it needs no list of which templates use Swal.
     */
    private function conditionalSweetAlert(string $html): string
    {
        if (stripos($html, '<!--falcon-swal-->') === false) {
            return $html;
        }

        // Scan the page with BOTH SweetAlert blocks removed — the eager bundle and the always-present
        // lazy loader — so only the page's own Swal use counts. The lazy loader references Swal and
        // the bundle URL itself, which must not pin the bundle to every page.
        $scan = preg_replace('/<!--falcon-swal-->.*?<!--\/falcon-swal-->/is', '', $html, 1) ?? $html;
        $scan = preg_replace('/<!--falcon-swal-lazy-->.*?<!--\/falcon-swal-lazy-->/is', '', $scan, 1) ?? $scan;

        $usesSwal = preg_match('/\bSwal\s*\.\s*\w+/', $scan) === 1
            || preg_match('/\bswal\s*\(/i', $scan) === 1;

        if (!$usesSwal) {
            // Nothing else on the page calls Swal → drop the eager bundle; the lazy loader stays
            // (markers stripped) so the mini-cart toast can still fetch it on demand.
            $html = preg_replace('/<!--falcon-swal-->.*?<!--\/falcon-swal-->/is', '', $html, 1) ?? $html;
        }

        // Either way, remove the marker comments.
        return str_replace(
            ['<!--falcon-swal-->', '<!--/falcon-swal-->', '<!--falcon-swal-lazy-->', '<!--/falcon-swal-lazy-->'],
            '', $html
        );
    }

    /**
     * Replace the icon libraries' stylesheets (Font Awesome and the extra sets) with one inline
     * <style> holding only the rules for the icons this page shows.
     *
     * Each library is a render-blocking 70–140 KB stylesheet, and a page typically uses a
     * handful of its icons. The finished HTML is scanned as a whole, inline scripts and Alpine
     * expressions included, so an icon a widget swaps in on click is kept. A library the page
     * uses no icon from is dropped entirely. Works on any theme: it only needs the CMS's own
     * stylesheet URLs, wherever the theme put them.
     */
    private function inlineIcons(string $html): string
    {
        $assets = [];
        foreach (falcon_icon_inline_sets() as $set => $def) {
            $assets[strtolower(basename($def['asset']))] = $set;
        }
        $names = implode('|', array_map(fn ($a) => preg_quote($a, '#'), array_keys($assets)));
        if (!preg_match_all('#<link\b[^>]*\bhref=["\'][^"\']*/vendor/falcon-cms/css/('.$names.')(?:\?[^"\']*)?["\'][^>]*>#i', $html, $links, PREG_SET_ORDER)) {
            return $html;
        }

        $sets = array_values(array_unique(array_map(fn ($l) => $assets[strtolower($l[1])], $links)));
        // the links themselves name no icons; keep them out of the scan
        $scan = str_replace(array_column($links, 0), '', $html);
        try {
            $css = falcon_icon_set_inline_css($scan, $sets);
        } catch (\Throwable $e) {
            return $html; // never worse than before: keep the stylesheets
        }

        $first = true;
        foreach ($links as $link) {
            $replacement = '';
            if ($first && $css !== '') {
                $replacement = '<style id="falcon-icon-css">'.$css.'</style>';
            }
            $first = false;
            $html = preg_replace('/'.preg_quote($link[0], '/').'/', $replacement, $html, 1) ?? $html;
        }

        return $html;
    }

    /**
     * Serve the page's Google Fonts from the site: each fonts.googleapis.com stylesheet link
     * becomes its @font-face rules inline, pointing at local copies of the font files, and the
     * preconnect hints to Google go once nothing needs them.
     *
     * A stylesheet not copied yet keeps Google's link for this request and is downloaded after
     * the response has gone out; the second element of the result says whether every link was
     * served locally, so a stand-in page is not kept in the page cache.
     *
     * @return array{0:string,1:bool}
     */
    private function localGoogleFonts(string $html): array
    {
        if (stripos($html, 'fonts.googleapis.com') === false) {
            return [$html, true];
        }

        $complete = true;
        $html = preg_replace_callback('#<link\b[^>]*\bhref=["\']((?:https?:)?//fonts\.googleapis\.com/css2?\?[^"\']+)["\'][^>]*>#i',
            function ($m) use (&$complete) {
                if (!preg_match('/\brel=["\']?stylesheet/i', $m[0])) {
                    return $m[0]; // a preconnect or preload; dealt with below
                }
                $css = LocalGoogleFonts::css($m[1]);
                if ($css === null) {
                    LocalGoogleFonts::scheduleDownload($m[1]);
                    $complete = false;

                    return $m[0];
                }

                return '<style class="falcon-local-fonts">'.$css.'</style>';
            }, $html) ?? $html;

        if ($complete) {
            $html = preg_replace('#<link\b(?=[^>]*\brel=["\']?(?:preconnect|dns-prefetch))[^>]*\bhref=["\'](?:https?:)?//fonts\.(?:googleapis|gstatic)\.com/?["\'][^>]*>\s*#i', '', $html) ?? $html;
        }

        return [$html, $complete];
    }

    /** Add loading="lazy" + decoding="async" to images that have neither, leaving the first few eager. */
    private function lazyImages(string $html): string
    {
        $seen = 0;
        // The first image after the site header is the hero on nearly every page, and with it
        // the largest-contentful paint: it is fetched first (fetchpriority) and never lazy.
        // Only on a page that marks its header; without one there is nothing to go by.
        $headerEnd = stripos($html, '</header>');
        $heroDone = $headerEnd === false;

        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$seen, &$heroDone, $headerEnd) {
            [$tag, $offset] = $m[0];
            $seen++;
            if (!$heroDone && $offset > $headerEnd) {
                $heroDone = true;
                if (stripos($tag, 'fetchpriority=') === false && !preg_match('/loading=["\']?lazy/i', $tag)) {
                    return preg_replace('/\s*\/?>$/', ' fetchpriority="high">', $tag, 1);
                }
            }
            // The first images are usually above the fold: the logo, then the hero and whatever
            // sits beside it. Only the first stayed eager, so a hero image, the largest-contentful
            // paint on most pages, was lazy and waited for layout. Three, as WordPress does.
            if ($seen <= self::EAGER_IMAGES || stripos($tag, 'loading=') !== false) {
                return $tag;
            }
            $add = ' loading="lazy"';
            if (stripos($tag, 'decoding=') === false) {
                $add .= ' decoding="async"';
            }

            return preg_replace('/\s*\/?>$/', $add.'>', $tag, 1);
        }, $html, -1, $count, PREG_OFFSET_CAPTURE) ?? $html;
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

    /**
     * Collapse whitespace between tags. pre / textarea / script keep their contents verbatim;
     * inline <style> is minified as CSS (the builder emits a lot of it, so it is worth shrinking
     * rather than leaving alone).
     */
    private function minify(string $html, bool $minifyHtml, bool $minifyCss): string
    {
        $protected = [];
        $shield = function (string $content) use (&$protected) {
            $key = "\x01".count($protected)."\x01";
            $protected[$key] = $content;

            return $key;
        };

        // Each <style> is minified when CSS minify is on, and always shielded so the HTML pass
        // never collapses CSS it should not touch.
        $html = preg_replace_callback('/(<style\b[^>]*>)(.*?)(<\/style>)/is',
            fn ($m) => $shield($m[1].($minifyCss ? $this->minifyCss($m[2]) : $m[2]).$m[3]), $html) ?? $html;

        if (!$minifyHtml) {
            // CSS-only: nothing else changes, just restore the (minified) style blocks.
            return strtr($html, $protected);
        }

        // Contents whose whitespace is meaningful: left exactly as they are.
        $html = preg_replace_callback('/<(pre|textarea|script)\b[^>]*>.*?<\/\1>/is',
            fn ($m) => $shield($m[0]), $html) ?? $html;

        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html) ?? $html; // keep IE conditionals
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;             // between tags
        $html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;            // runs within text

        return strtr($html, $protected);
    }

    /** Conservative CSS minify: drop comments and the whitespace that carries no meaning. */
    private function minifyCss(string $css): string
    {
        $css = preg_replace('#/\*(?!!).*?\*/#s', '', $css) ?? $css;  // comments (keep /*! ... */)
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;             // all whitespace runs → one space
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css) ?? $css; // around separators
        $css = str_replace(';}', '}', $css);                         // last semicolon in a block

        return trim($css);
    }
}
