<?php

namespace FalconCms\Core\Support;

/**
 * Speculation Rules: the browser starts fetching the next page as the visitor presses on a
 * link, so it opens almost at once. WordPress ships the same in core since 6.8 (measured on
 * 50,000+ sites: about 2% more pages passing LCP).
 *
 * "conservative" eagerness: the fetch starts on pointer-down / touch-start, when the click is
 * already happening, so it is not wasted work and the server-side visit count stays honest.
 * Prefetch only (HTML, no scripts run), never for the admin, the API, the login and register
 * pages, the shop's cart/checkout/account, downloads, nofollow links or anything marked
 * .no-prefetch. Chromium browsers use it; others ignore the script.
 */
final class SpeculativeLoading
{
    public static function enabled(): bool
    {
        return get_cms_option('perf_speculative_loading', '0') === '1';
    }

    public static function script(): string
    {
        $exclude = ['/admin*', '/api/*', '/cart*', '/checkout*', '/account*', '/logout*', '/*\\?*', '/*.pdf', '/*.zip'];
        foreach (['login_url' => 'falcon-admin', 'register_url' => 'falcon-registration'] as $option => $default) {
            $slug = trim((string) get_cms_option($option, $default), '/');
            if ($slug !== '') {
                $exclude[] = '/'.$slug.'*';
            }
        }

        $rules = ['prefetch' => [[
            'source' => 'document',
            'where' => ['and' => [
                ['href_matches' => '/*'],
                ['not' => ['href_matches' => array_values(array_unique($exclude))]],
                ['not' => ['selector_matches' => '[rel~=nofollow], [download], [target=_blank], .no-prefetch, .no-prefetch a']],
            ]],
            'eagerness' => 'conservative',
        ]]];

        return '<script type="speculationrules">'.json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).'</script>';
    }
}
