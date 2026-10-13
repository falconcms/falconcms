<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Falcon SEO's Pro tools: AI writing (Claude), keyword data from DataForSEO, one-click fixes
 * and the weekly SEO email. They need a FalconCMS Pro licence; the keys they use are the
 * site's own, stored encrypted, and nothing is sent anywhere without one.
 */
final class Pro
{
    /** The licence feature; any active Pro licence includes it. */
    public const FEATURE = 'seo_pro';

    public const CLAUDE_KEY = 'seo_ai_api_key';

    public const CLAUDE_MODEL = 'seo_ai_model';

    public const DATAFORSEO_LOGIN = 'seo_dataforseo_login';

    public const DATAFORSEO_PASSWORD = 'seo_dataforseo_password';

    public const REPORT_ENABLED = 'seo_weekly_report';

    public const REPORT_TO = 'seo_weekly_report_to';

    /** The models offered for AI writing, best first. */
    public const MODELS = [
        'claude-opus-5-5' => 'Claude Opus 5.5 (best results)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (faster, half the cost)',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 (fastest, cheapest)',
    ];

    /** Whether the licence covers the Pro tools: the feature itself, or any active Pro licence. */
    public static function active(): bool
    {
        if (!function_exists('falcon_pro')) {
            return false;
        }

        return falcon_pro(self::FEATURE) || falcon_pro();
    }

    public static function claudeKey(): string
    {
        return self::secret(self::CLAUDE_KEY);
    }

    public static function claudeModel(): string
    {
        $model = (string) get_cms_option(self::CLAUDE_MODEL, 'claude-opus-5-5');

        return isset(self::MODELS[$model]) ? $model : 'claude-opus-5-5';
    }

    /** @return array{0: string, 1: string} login, password */
    public static function dataForSeo(): array
    {
        return [trim((string) get_cms_option(self::DATAFORSEO_LOGIN, '')), self::secret(self::DATAFORSEO_PASSWORD)];
    }

    /** Settings → SEO save: the secrets are stored encrypted, and an empty field keeps the one saved. */
    public static function encryptSecrets(array $data, $request = null): array
    {
        foreach ([self::CLAUDE_KEY, self::DATAFORSEO_PASSWORD] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            $remove = $request ? $request->boolean($key.'_remove') : !empty($data[$key.'_remove']);
            unset($data[$key], $data[$key.'_remove']);
            if ($remove) {
                $data[$key] = '';
            } elseif ($value !== '') {
                $data[$key] = Crypt::encryptString($value);
            }
        }
        if (isset($data[self::DATAFORSEO_LOGIN])) {
            $data[self::DATAFORSEO_LOGIN] = trim((string) $data[self::DATAFORSEO_LOGIN]);
        }

        return $data;
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
