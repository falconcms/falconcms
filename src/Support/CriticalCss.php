<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * "Critical CSS": from the compiled Tailwind stylesheet, just the rules a page uses.
 *
 * The compiled stylesheet (~65 KB) blocks the first paint of every page, which typically uses a
 * tenth of it. This keeps a rule when every class its selector names appears somewhere in the
 * page: class attributes, Alpine bindings and inline scripts alike, since the whole HTML is
 * scanned. Rules that name no class at all (the reset, html, body, img…) are always kept. The
 * page carries the result inline and loads the full stylesheet without blocking, so a class that
 * JavaScript adds later still gets its rules: this decides what is needed first, never what is
 * available at all.
 */
class CriticalCss
{
    /**
     * The rules of a stylesheet, parsed once per file version: each one its selectors, the
     * classes each selector requires, and its declarations; an @media block carries its own.
     *
     * @return list<array<string,mixed>>
     */
    public static function rules(string $file): array
    {
        $parse = fn () => self::parse(preg_replace('#/\*.*?\*/#s', '', (string) @file_get_contents($file)) ?? '');

        try {
            return Cache::remember('falcon_critical_css_'.md5($file).'_'.(int) @filemtime($file), 86400, $parse);
        } catch (Throwable $e) {
            return $parse();
        }
    }

    /** The rules of the stylesheet that the page's HTML needs, ready for a <style> tag. */
    public static function forHtml(string $html, string $file): string
    {
        // Every word the page could put in a class attribute, wherever it is written.
        $used = array_flip(preg_split('/[\s"\'`<>=,{}();]+/', $html, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return self::emit(self::rules($file), $used);
    }

    /**
     * @param  list<array<string,mixed>>  $rules
     * @param  array<string,int>  $used
     */
    private static function emit(array $rules, array $used): string
    {
        $css = '';
        foreach ($rules as $rule) {
            if (isset($rule['children'])) {
                $inner = self::emit($rule['children'], $used);
                if ($inner !== '') {
                    $css .= $rule['at'].'{'.$inner.'}';
                }

                continue;
            }
            if (isset($rule['raw'])) {
                $css .= $rule['raw']; // @keyframes, @font-face and the like: kept whole

                continue;
            }
            $keep = [];
            foreach ($rule['selectors'] as $i => $selector) {
                foreach ($rule['classes'][$i] as $class) {
                    if (!isset($used[$class])) {
                        continue 2;
                    }
                }
                $keep[] = $selector;
            }
            if ($keep) {
                $css .= implode(',', $keep).$rule['body'];
            }
        }

        return $css;
    }

    /** @return list<array<string,mixed>> */
    private static function parse(string $css): array
    {
        $rules = [];
        $len = strlen($css);
        $i = 0;
        while ($i < $len) {
            $open = strpos($css, '{', $i);
            if ($open === false) {
                break;
            }
            $prelude = trim(substr($css, $i, $open - $i));
            $close = self::matching($css, $open);
            $body = substr($css, $open, $close - $open + 1);
            $i = $close + 1;

            if ($prelude === '') {
                continue;
            }
            if ($prelude[0] === '@') {
                if (preg_match('/^@(media|supports|layer)\b/i', $prelude)) {
                    $rules[] = ['at' => $prelude, 'children' => self::parse(substr($body, 1, -1))];
                } else {
                    $rules[] = ['raw' => $prelude.$body];
                }

                continue;
            }

            $selectors = self::splitSelectors($prelude);
            $rules[] = [
                'selectors' => $selectors,
                'classes' => array_map([self::class, 'classesOf'], $selectors),
                'body' => $body,
            ];
        }

        return $rules;
    }

    /** The index of the brace that closes the one at $open. */
    private static function matching(string $css, int $open): int
    {
        $depth = 0;
        $len = strlen($css);
        for ($j = $open; $j < $len; $j++) {
            if ($css[$j] === '{') {
                $depth++;
            } elseif ($css[$j] === '}' && --$depth === 0) {
                return $j;
            }
        }

        return $len - 1;
    }

    /** Split on top-level commas only (not those inside :is(a, b)). @return list<string> */
    private static function splitSelectors(string $prelude): array
    {
        $out = [];
        $depth = 0;
        $start = 0;
        $len = strlen($prelude);
        for ($j = 0; $j < $len; $j++) {
            $c = $prelude[$j];
            if ($c === '\\') {
                $j++;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $out[] = trim(substr($prelude, $start, $j - $start));
                $start = $j + 1;
            }
        }
        $out[] = trim(substr($prelude, $start));

        return $out;
    }

    /**
     * The class names a selector requires, unescaped: `.md\:flex:hover` needs "md:flex". What
     * sits inside :not(…) and friends is not required for the rule to apply, so it is ignored.
     *
     * @return list<string>
     */
    public static function classesOf(string $selector): array
    {
        $plain = '';
        $depth = 0;
        $len = strlen($selector);
        for ($j = 0; $j < $len; $j++) {
            $c = $selector[$j];
            if ($c === '\\' && $j + 1 < $len) {
                if ($depth === 0) {
                    $plain .= $c.$selector[$j + 1];
                }
                $j++;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($depth === 0) {
                $plain .= $c;
            }
        }

        preg_match_all('/\.((?:\\\\[0-9a-fA-F]{1,6}\s?|\\\\.|[A-Za-z0-9_-])+)/', $plain, $m);

        return array_values(array_unique(array_map(function ($escaped) {
            $name = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?/', fn ($h) => mb_chr((int) hexdec($h[1])), $escaped) ?? $escaped;

            return preg_replace('/\\\\(.)/', '$1', $name) ?? $name;
        }, $m[1])));
    }
}
