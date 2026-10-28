<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Falcon SEO's Pro tools: keyword data from DataForSEO, one-click fixes and the weekly SEO
 * email. They need a FalconCMS Pro licence; the keys they use are the site's own, stored
 * encrypted, and nothing is sent anywhere without one. (AI writing is the Falcon AI plugin's.)
 */
final class Pro
{
    /** The licence feature; any active Pro licence includes it. */
    public const FEATURE = 'seo_pro';

    public const DATAFORSEO_LOGIN = 'seo_dataforseo_login';

    public const DATAFORSEO_PASSWORD = 'seo_dataforseo_password';

    public const REPORT_ENABLED = 'seo_weekly_report';

    public const REPORT_TO = 'seo_weekly_report_to';

    /** Whether the licence covers the Pro tools: the feature itself, or any active Pro licence. */
    public static function active(): bool
    {
        if (!function_exists('falcon_pro')) {
            return false;
        }

        return falcon_pro(self::FEATURE) || falcon_pro();
    }

    /** @return array{0: string, 1: string} login, password */
    public static function dataForSeo(): array
    {
        return [trim((string) get_cms_option(self::DATAFORSEO_LOGIN, '')), self::secret(self::DATAFORSEO_PASSWORD)];
    }

    private static function secret(string $key): string
    {
        $stored = (string) get_cms_option($key, '');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable $e) {
            return '';
        }
    }
}
