<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Compiled CSS knows only the Tailwind classes written in the theme's templates. A page whose
 * content carries others (imported HTML with "md:text-3xl", a builder element's "CSS Class"
 * field) would lose those styles. This finds such classes in a finished page, so the page can
 * load the runtime Tailwind build as well and look exactly as it does without Compiled CSS.
 * Every other page keeps the compiled file alone.
 */
final class CompiledCssCoverage
{
    /** Utility names Tailwind generates (the part after any "md:" / "hover:" variants). */
    private const UTILITY = '/^-?(?:
        (?:m|p)[trblxyse]?-|space-[xy]-|gap-|w-|h-|min-[wh]-|max-[wh]-|size-|inset-|top-|right-|bottom-|left-|z-|order-|
        basis-|grow|shrink|flex|grid|col-|row-|auto-(?:cols|rows)-|justify-|items-|content-|self-|place-|
        text-|font-|leading-|tracking-|line-clamp-|list-|indent-|align-|whitespace-|break-|hyphens-|
        underline|overline|line-through|no-underline|decoration-|underline-offset-|uppercase|lowercase|capitalize|normal-case|
        italic|not-italic|truncate|antialiased|subpixel-antialiased|
        bg-|from-|via-|to-|border|divide-|outline|ring|shadow|opacity-|mix-blend-|
        rounded|blur|brightness-|contrast-|grayscale|invert|saturate-|sepia|backdrop-|drop-shadow|
        transition|duration-|ease-|delay-|animate-|transform|scale-|rotate-|translate-|skew-|origin-|
        cursor-|pointer-events-|select-|resize|scroll-|snap-|touch-|will-change-|appearance-|accent-|caret-|
        fill-|stroke-|object-|overflow-|overscroll-|aspect-|columns-|float-|clear-|isolat|
        block$|inline|hidden$|contents$|table|static$|fixed$|absolute$|relative$|sticky$|visible$|invisible$|collapse$|
        container$|sr-only$|not-sr-only$|box-|
        \[ )/x';

    private const PALETTE = '(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(?:50|[1-9]00|950)';

    /** Colour names the theme's Tailwind config defines (see the falcon theme's app.blade.php). */
    private const THEME_COLORS = 'primary|primary-hover|secondary|heading|body|link|link-hover';

    /**
     * What a page needs beyond the compiled file: CSS made here for the common utilities, and
     * whether the runtime build must load for the rest.
     *
     * @return array{css: string, runtime: bool, missing: list<string>, leftover: list<string>}
     */
    public static function plan(string $html, string $cssFile): array
    {
        $missing = self::missing($html, $cssFile);
        if ($missing === []) {
            return ['css' => '', 'runtime' => false, 'missing' => [], 'leftover' => []];
        }
        $made = TailwindLite::generate($missing);
        // Only what the runtime build would actually produce is worth loading it for.
        $leftover = array_values(array_filter($made['leftover'], [self::class, 'runtimeCanMake']));

        return ['css' => $made['css'], 'runtime' => $leftover !== [], 'missing' => $missing, 'leftover' => $leftover];
    }

    /** @return list<string> Tailwind classes on the page that the compiled file does not have */
    public static function missing(string $html, string $cssFile): array
    {
        $known = self::index($cssFile);
        if ($known === []) {
            return [];
        }
        // A class the page styles itself (a builder element's own <style>) is not Tailwind's to make.
        $known += self::inlineSelectors($html);

        $missing = [];
        if (preg_match_all('/\sclass\s*=\s*(["\'])(.*?)\1/is', self::body($html), $m)) {
            foreach ($m[2] as $list) {
                foreach (preg_split('/\s+/', html_entity_decode($list, ENT_QUOTES | ENT_HTML5)) ?: [] as $class) {
                    if ($class === '' || isset($known[$class]) || isset($missing[$class]) || str_contains($class, '{{')) {
                        continue;
                    }
                    if (self::isUtility($class)) {
                        $missing[$class] = true;
                    }
                }
            }
        }

        return array_keys($missing);
    }

    public static function isUtility(string $class): bool
    {
        // Strip variants ("md:", "hover:", "dark:", "[&>p]:"), "!" importance and a "/50" opacity.
        $base = preg_replace('/^(?:[^:\[\]]+:|\[[^\]]*\]:)+/', '', $class) ?? $class;
        $base = ltrim($base, '!');

        return $base !== '' && preg_match(self::UTILITY, $base) === 1;
    }

    /**
     * Whether the runtime build (with the theme's config) would generate CSS for the class:
     * colours from Tailwind's palette, the theme's own colour names or arbitrary values, not a
     * design system's names it was never told about ("text-muted-foreground", "font-display").
     */
    public static function runtimeCanMake(string $class): bool
    {
        $base = ltrim(preg_replace('/^(?:[^:\[\]]+:|\[[^\]]*\]:)+/', '', $class) ?? $class, '!-');
        if (!self::isUtility($class) || preg_match('/\d{5,}|[a-z]+\d+[a-z]+\d+/i', $base)) {
            return false; // generated ids ("text-block-1785438522109") are no utility
        }
        if (str_contains($base, '[')) {
            return true;
        }
        $color = '(?:'.self::PALETTE.'|'.self::THEME_COLORS.'|inherit|current|transparent|black|white)(?:\/\d{1,3})?';
        $rules = [
            'text' => '(?:xs|sm|base|lg|xl|[2-9]xl|left|center|right|justify|start|end|wrap|nowrap|balance|pretty|ellipsis|clip|'.$color.')',
            'bg' => '(?:fixed|local|scroll|auto|cover|contain|center|top|bottom|left|right|(?:left|right)-(?:top|bottom)|none|repeat(?:-x|-y|-round|-space)?|no-repeat|gradient-to-(?:t|tr|r|br|b|bl|l|tl)|(?:clip|origin|blend)-[a-z-]+|'.$color.')',
            'border' => '(?:\d+|[xytrblse](?:-\d+)?|solid|dashed|dotted|double|hidden|none|collapse|separate|spacing-.+|(?:[xytrblse]-)?'.$color.')',
            'font' => '(?:sans|serif|mono|thin|extralight|light|normal|medium|semibold|bold|extrabold|black)',
            'shadow' => '(?:sm|md|lg|xl|2xl|inner|none|'.$color.')',
            'ring' => '(?:\d+|inset|offset-\d+|offset-'.$color.'|'.$color.')',
            'outline' => '(?:none|dashed|dotted|double|\d+|offset-\d+|'.$color.')',
            'divide' => '(?:[xy](?:-\d+)?(?:-reverse)?|solid|dashed|dotted|double|none|'.$color.')',
            'decoration' => '(?:solid|double|dotted|dashed|wavy|auto|from-font|clone|slice|\d+|'.$color.')',
            '(?:from|via|to|accent|caret|placeholder)' => $color,
            '(?:fill|stroke)' => '(?:none|\d+|'.$color.')',
        ];
        foreach ($rules as $prefix => $value) {
            if (preg_match('/^'.$prefix.'-/', $base)) {
                return preg_match('/^'.$prefix.'-'.$value.'$/', $base) === 1;
            }
        }

        return true;
    }

    /** Class names selected in the page's own <style> blocks. */
    private static function inlineSelectors(string $html): array
    {
        $names = [];
        if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $html, $blocks)) {
            foreach ($blocks[1] as $css) {
                if (preg_match_all('/\.((?:\\\\.|[A-Za-z0-9_-])+)/', $css, $m)) {
                    foreach ($m[1] as $escaped) {
                        $names[preg_replace('/\\\\(.)/', '$1', $escaped)] = true;
                    }
                }
            }
        }

        return $names;
    }

    /** Class names the compiled stylesheet defines, unescaped ("md\:text-3xl" → "md:text-3xl"). */
    private static function index(string $cssFile): array
    {
        if (!is_file($cssFile)) {
            return [];
        }
        $key = 'falcon:compiled-css-index:'.md5($cssFile.'|'.filemtime($cssFile).'|'.filesize($cssFile));

        static $memo = [];
        if (isset($memo[$key])) {
            return $memo[$key];
        }
        try {
            return $memo[$key] = Cache::rememberForever($key, function () use ($cssFile) {
                preg_match_all('/\.((?:\\\\.|[A-Za-z0-9_-])+)/', (string) file_get_contents($cssFile), $m);
                $names = [];
                foreach ($m[1] as $escaped) {
                    $names[preg_replace('/\\\\(.)/', '$1', $escaped)] = true;
                }

                return $names;
            });
        } catch (Throwable $e) {
            return [];
        }
    }

    /** The page's <body>, without its scripts: classes in <head> (none that style the page) are not looked at. */
    private static function body(string $html): string
    {
        $start = stripos($html, '<body');
        $body = $start === false ? $html : substr($html, $start);

        // Markup inside scripts (Alpine templates, JS strings) is not on the page yet.
        return preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $body) ?? $body;
    }
}
