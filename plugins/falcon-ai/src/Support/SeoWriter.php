<?php

namespace FalconAi\Support;

use FalconAi\Exceptions\AiException;
use FalconAi\Prompts\SeoPrompts;

/**
 * SEO writing for Falcon SEO: title and description options in the editor's SEO box, and the
 * AI fixes in SEO → Site Analysis. Falcon SEO asks through its own filters, so neither plugin
 * needs the other:
 *
 *   falcon_seo_ai_available    (bool)
 *   falcon_seo_ai_suggestions  (?array $options, string $field, array $page)
 *   falcon_seo_ai_alt_texts    (?array $alts, list<array{src, context}> $images, array $page)
 */
final class SeoWriter
{
    /**
     * @param  array<string, string>  $page
     * @return list<array{text: string, why: string}>
     *
     * @throws AiException
     */
    public static function suggest(string $field, array $page): array
    {
        if (!in_array($field, ['title', 'description'], true)) {
            throw new AiException('Unknown field.');
        }
        $answer = app(AiManager::class)->json(SeoPrompts::suggestions($field, $page))->data ?? [];

        return array_values(array_filter(array_map(fn ($o) => [
            'text' => mb_substr(trim((string) ($o['text'] ?? '')), 0, 300),
            'why' => mb_substr(trim((string) ($o['why'] ?? '')), 0, 300),
        ], (array) ($answer['options'] ?? [])), fn ($o) => $o['text'] !== ''));
    }

    /**
     * @param  list<array{src: string, context: string}>  $images
     * @return array<string, string> src => alt
     *
     * @throws AiException
     */
    public static function altTexts(array $images, array $page = []): array
    {
        $images = array_slice(array_values($images), 0, SeoPrompts::MAX_IMAGES);
        if ($images === []) {
            return [];
        }
        $answer = app(AiManager::class)->json(SeoPrompts::altTexts($images, $page))->data ?? [];

        $out = [];
        foreach ((array) ($answer['alts'] ?? []) as $row) {
            $i = (int) ($row['number'] ?? 0) - 1;
            $alt = trim(strip_tags((string) ($row['alt'] ?? '')));
            if (isset($images[$i]) && $alt !== '') {
                $out[$images[$i]['src']] = mb_substr($alt, 0, 200);
            }
        }

        return $out;
    }

    /** `falcon_seo_ai_available` */
    public static function available($available = false): bool
    {
        return (bool) $available || app(AiManager::class)->available();
    }

    /** `falcon_seo_ai_suggestions` */
    public static function suggestionsFilter($options = null, $field = '', $page = []): ?array
    {
        return is_array($options) ? $options : self::suggest((string) $field, (array) $page);
    }

    /** `falcon_seo_ai_alt_texts` */
    public static function altTextsFilter($alts = null, $images = [], $page = []): ?array
    {
        return is_array($alts) ? $alts : self::altTexts((array) $images, (array) $page);
    }
}
