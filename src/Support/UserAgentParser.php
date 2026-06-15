<?php

namespace FalconCms\Core\Support;

/**
 * Smart User-Agent parser — detects OS family, browser and device type.
 *
 * Order matters: many UAs are nested (an iPhone says "like Mac OS X", Android says
 * "Linux", Edge/Opera/Samsung all contain "Chrome", Chrome contains "Safari"), so the
 * most specific signature is always checked first.
 */
class UserAgentParser
{
    /** Operating system family. */
    public static function os(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') return 'Unknown';

        return match (true) {
            (bool) preg_match('/windows phone|iemobile/i', $ua)        => 'Windows Phone',
            (bool) preg_match('/windows nt|windows|win32|win64/i', $ua) => 'Windows',
            (bool) preg_match('/android/i', $ua)                        => 'Android',          // before Linux
            (bool) preg_match('/iphone|ipad|ipod/i', $ua)               => 'iOS',               // before macOS
            (bool) preg_match('/\bcros\b/i', $ua)                       => 'Chrome OS',         // before Linux
            (bool) preg_match('/mac os x|macintosh|\bmac\b/i', $ua)     => 'macOS',
            (bool) preg_match('/ubuntu/i', $ua)                         => 'Ubuntu',
            (bool) preg_match('/linux|x11|freebsd|openbsd/i', $ua)      => 'Linux',
            default                                                     => 'Other',
        };
    }

    /** Browser name. */
    public static function browser(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') return 'Unknown';

        return match (true) {
            self::isBot($ua)                                  => 'Bot / Crawler',
            (bool) preg_match('/edg(a|ios|e)?\//i', $ua)      => 'Edge',              // before Chrome
            (bool) preg_match('/opr\/|opera|opios/i', $ua)    => 'Opera',             // before Chrome
            (bool) preg_match('/samsungbrowser/i', $ua)       => 'Samsung Internet',  // before Chrome
            (bool) preg_match('/vivaldi/i', $ua)              => 'Vivaldi',
            (bool) preg_match('/ucbrowser|ucweb/i', $ua)      => 'UC Browser',
            (bool) preg_match('/yabrowser/i', $ua)            => 'Yandex',
            (bool) preg_match('/brave/i', $ua)                => 'Brave',
            (bool) preg_match('/firefox|fxios/i', $ua)        => 'Firefox',
            (bool) preg_match('/msie|trident/i', $ua)         => 'Internet Explorer',
            (bool) preg_match('/chromium/i', $ua)             => 'Chromium',
            (bool) preg_match('/chrome|crios/i', $ua)         => 'Chrome',            // after Edge/Opera/Samsung
            (bool) preg_match('/safari/i', $ua)               => 'Safari',            // after Chrome
            default                                           => 'Other',
        };
    }

    /** Device type: desktop | mobile | tablet | bot. */
    public static function device(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') return 'desktop';
        if (self::isBot($ua)) return 'bot';

        // Tablets: iPad, generic "tablet", and Android without "mobile".
        if (preg_match('/ipad|playbook|silk|tablet|kindle|(android(?!.*mobile))/i', $ua)) return 'tablet';

        if (preg_match('/mobile|iphone|ipod|windows phone|blackberry|bb10|opera mini|iemobile|webos/i', $ua)) return 'mobile';

        return 'desktop';
    }

    /** Known crawlers / bots / link-preview fetchers. */
    public static function isBot(?string $ua): bool
    {
        $ua = (string) $ua;
        if ($ua === '') return false;

        return (bool) preg_match(
            '/bot\b|crawl|spider|slurp|mediapartners|adsbot|bingpreview|facebookexternalhit|facebot|'
            . 'whatsapp|telegrambot|twitterbot|linkedinbot|embedly|quora link|pinterest|redditbot|'
            . 'googlebot|baiduspider|yandex(bot|images)|duckduckbot|sogou|exabot|semrush|ahrefs|mj12bot|'
            . 'dotbot|petalbot|applebot|bytespider|gptbot|claudebot|ccbot|headlesschrome|python-requests|'
            . 'curl\/|wget\/|go-http-client|axios\/|okhttp/i',
            $ua
        );
    }
}
