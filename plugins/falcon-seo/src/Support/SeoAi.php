<?php

namespace FalconSeo\Support;

use RuntimeException;

/**
 * AI writing for Site Analysis fixes, asked of whichever plugin provides it (Falcon AI, which
 * also puts "Suggest with AI" in the editor's SEO box). Falcon SEO holds no AI key of its own.
 *
 *   falcon_seo_ai_available    (bool)
 *   falcon_seo_ai_suggestions  (?array $options, string $field, array $page)  list<{text, why}>
 *   falcon_seo_ai_alt_texts    (?array $alts, list<{src, context}> $images, array $page)  src => alt
 *
 * A provider throws an exception with a message an editor can act on; it is passed on as is.
 */
final class SeoAi
{
    public const NOT_SET_UP = 'AI writing needs the Falcon AI plugin with an API key (AI → Settings).';

    public static function available(): bool
    {
        return (bool) apply_falcon_filters('falcon_seo_ai_available', false);
    }

    /**
     * SEO title or meta description options.
     *
     * @param  array{title?: string, content?: string, keyword?: string, current?: string}  $page
     * @return list<array{text: string, why: string}>
     */
    public static function suggest(string $field, array $page): array
    {
        $options = self::available() ? apply_falcon_filters('falcon_seo_ai_suggestions', null, $field, $page) : null;
        if (!is_array($options)) {
            throw new RuntimeException(self::NOT_SET_UP);
        }

        return array_values(array_filter(array_map(fn ($o) => [
            'text' => trim((string) ($o['text'] ?? '')),
            'why' => trim((string) ($o['why'] ?? '')),
        ], $options), fn ($o) => $o['text'] !== ''));
    }

    /**
     * Alt text for images, from their file names and the text around them.
     *
     * @param  list<array{src: string, context: string}>  $images
     * @return array<string, string> src => alt
     */
    public static function altTexts(array $images, array $page = []): array
    {
        if ($images === []) {
            return [];
        }
        $alts = self::available() ? apply_falcon_filters('falcon_seo_ai_alt_texts', null, $images, $page) : null;
        if (!is_array($alts)) {
            throw new RuntimeException(self::NOT_SET_UP);
        }

        return array_filter(array_map(fn ($alt) => mb_substr(trim(strip_tags((string) $alt)), 0, 200), $alts));
    }
}
