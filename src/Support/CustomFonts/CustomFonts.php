<?php

namespace FalconCms\Core\Support\CustomFonts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fonts the site owner uploads (Customizer → Typography → Custom Font), offered wherever a
 * font is picked — the theme's typography, the page builder, Motion Visual text — next to
 * the Google list, under "Custom Fonts".
 *
 * Each font is a family with variants (weight + normal/italic); each variant holds the files
 * uploaded for it, one per format. They are declared with @font-face on every page, in the
 * builder and in the Customizer; a browser downloads a file only when text uses it.
 *
 * Kept apart from falcon_google_fonts() on purpose: that list decides what is requested from
 * Google, and Google answers 400 for the whole request when one family is unknown to it.
 *
 * Stored as JSON in the cms option custom_fonts; the files in the public disk under
 * fonts/<font id>/<variant id>.<format>, named by us, never by the upload.
 */
final class CustomFonts
{
    public const OPTION = 'custom_fonts';

    public const DIR = 'fonts';

    public const CATEGORY = 'Custom Fonts';

    /** What text falls back to while the font loads, or if it never does. */
    public const FALLBACKS = ['sans-serif', 'serif', 'monospace', 'cursive', 'system-ui'];

    /** CSS format() names, in the order browsers should try them: smallest first. */
    private const CSS_FORMATS = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

    public const MAX_FONTS = 60;

    /** @var array<int, array>|null */
    private static ?array $cache = null;

    /** @return list<array{id: string, family: string, fallback: string, variants: list<array{id: string, weight: int, style: string, files: array<string, string>, generated?: list<string>}>}> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $raw = function_exists('get_cms_option') ? get_cms_option(self::OPTION, '[]') : '[]';
        $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $fonts = [];
        foreach (is_array($list) ? $list : [] as $f) {
            $family = self::cleanFamily((string) ($f['family'] ?? ''));
            if ($family === '' || !self::validId((string) ($f['id'] ?? ''))) {
                continue;
            }
            $variants = [];
            foreach ((array) ($f['variants'] ?? []) as $v) {
                $files = array_filter((array) ($v['files'] ?? []), fn ($p, $fmt) => isset(self::CSS_FORMATS[$fmt]) && self::validPath((string) $p), ARRAY_FILTER_USE_BOTH);
                if (!$files || !self::validId((string) ($v['id'] ?? ''))) {
                    continue;
                }
                $variants[] = [
                    'id' => (string) $v['id'],
                    'weight' => self::weight($v['weight'] ?? 400),
                    'style' => ($v['style'] ?? '') === 'italic' ? 'italic' : 'normal',
                    'files' => $files,
                    'generated' => array_values(array_intersect((array) ($v['generated'] ?? []), array_keys($files))),
                ];
            }
            usort($variants, fn ($a, $b) => [$a['weight'], $a['style']] <=> [$b['weight'], $b['style']]);
            $fonts[] = [
                'id' => (string) $f['id'],
                'family' => $family,
                'fallback' => in_array($f['fallback'] ?? '', self::FALLBACKS, true) ? $f['fallback'] : 'sans-serif',
                'variants' => $variants,
            ];
        }

        return self::$cache = $fonts;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function find(string $family): ?array
    {
        $key = mb_strtolower(trim(trim($family), "'\""));
        foreach (self::all() as $f) {
            if (mb_strtolower($f['family']) === $key) {
                return $f;
            }
        }

        return null;
    }

    public static function isCustom(string $family): bool
    {
        return self::find($family) !== null;
    }

    /** A full font-family value: the family, then its own fallback. */
    public static function stack(string $family): string
    {
        $f = self::find($family);

        return $f ? "'".$f['family']."', ".$f['fallback'] : '';
    }

    /**
     * The fonts in the shape every font picker already reads (falcon_google_fonts()):
     * family, category, variants (weights) — plus the fallback, for the value it writes.
     *
     * @return list<array{family: string, category: string, variants: list<string>, fallback: string}>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::all() as $f) {
            if (!$f['variants']) {
                continue;
            }
            $weights = array_values(array_unique(array_map(fn ($v) => (string) $v['weight'], $f['variants'])));
            sort($weights);
            $out[] = ['family' => $f['family'], 'category' => self::CATEGORY, 'variants' => $weights, 'fallback' => $f['fallback']];
        }

        return $out;
    }

    /** The @font-face rules for every uploaded variant ('' when there are none). */
    public static function css(): string
    {
        $css = '';
        foreach (self::all() as $f) {
            foreach ($f['variants'] as $v) {
                $src = [];
                foreach (self::CSS_FORMATS as $fmt => $name) {
                    if (!empty($v['files'][$fmt])) {
                        $src[] = "url('".self::url($v['files'][$fmt])."') format('".$name."')";
                    }
                }
                if ($src) {
                    $css .= "@font-face{font-family:'".$f['family']."';src:".implode(',', $src)
                        .';font-weight:'.$v['weight'].';font-style:'.$v['style'].';font-display:swap}';
                }
            }
        }

        return $css;
    }

    public static function styleTag(): string
    {
        $css = self::css();

        return $css === '' ? '' : '<style id="falcon-custom-fonts">'.$css.'</style>';
    }

    /**
     * Uploaded files, sorted into fonts: each file is read for its family, weight and style
     * (the font's own tables, else its name), and joins the variant it belongs to — a WOFF2
     * and a TTF of the same weight become one variant with two formats.
     *
     * $into: add every file to this font instead of the one its name says.
     *
     * @param  list<UploadedFile>  $uploads
     * @return array{added: list<string>, errors: list<string>}
     */
    public static function upload(array $uploads, ?string $into = null): array
    {
        $fonts = self::all();
        $added = [];
        $errors = [];
        foreach ($uploads as $file) {
            $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : 'file';
            try {
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    throw new \RuntimeException('the upload did not arrive');
                }
                if ($file->getSize() > FontFile::MAX_BYTES) {
                    throw new \RuntimeException('it is larger than '.(FontFile::MAX_BYTES / 1024 / 1024).' MB');
                }
                $bytes = (string) file_get_contents($file->getRealPath());
                $format = FontFile::format($bytes);
                if ($format === null) {
                    throw new \RuntimeException('it is not a WOFF2, WOFF, TTF or OTF font');
                }
                $meta = FontFile::describe($bytes);
                $byName = FontFile::fromName($name);
                $family = self::cleanFamily($meta['family'] ?? '') ?: self::cleanFamily($byName['family']) ?: 'Custom Font';
                $weight = $meta ? $meta['weight'] : $byName['weight'];
                $style = ($meta ? $meta['italic'] : $byName['italic']) ? 'italic' : 'normal';

                $fi = null;
                foreach ($fonts as $i => $f) {
                    if ($into !== null ? $f['id'] === $into : mb_strtolower($f['family']) === mb_strtolower($family)) {
                        $fi = $i;
                        break;
                    }
                }
                if ($fi === null) {
                    if (count($fonts) >= self::MAX_FONTS) {
                        throw new \RuntimeException('there are already '.self::MAX_FONTS.' custom fonts');
                    }
                    $fonts[] = ['id' => self::newId(), 'family' => $family, 'fallback' => self::guessFallback($family), 'variants' => []];
                    $fi = array_key_last($fonts);
                }
                $vi = null;
                foreach ($fonts[$fi]['variants'] as $j => $v) {
                    if ($v['weight'] === $weight && $v['style'] === $style) {
                        $vi = $j;
                        break;
                    }
                }
                if ($vi === null) {
                    $fonts[$fi]['variants'][] = ['id' => self::newId(), 'weight' => $weight, 'style' => $style, 'files' => [], 'generated' => []];
                    $vi = array_key_last($fonts[$fi]['variants']);
                }
                $v = &$fonts[$fi]['variants'][$vi];
                $base = self::DIR.'/'.$fonts[$fi]['id'].'/'.$v['id'];
                self::put($base.'.'.$format, $bytes);
                $v['files'][$format] = $base.'.'.$format;
                $v['generated'] = array_values(array_diff($v['generated'] ?? [], [$format]));
                // A TTF / OTF without a WOFF: make one, so every browser has a compact file.
                if (in_array($format, ['ttf', 'otf'], true) && (empty($v['files']['woff']) || in_array('woff', $v['generated'], true))) {
                    $woff = FontFile::toWoff($bytes);
                    if ($woff !== null) {
                        self::put($base.'.woff', $woff);
                        $v['files']['woff'] = $base.'.woff';
                        $v['generated'][] = 'woff';
                        $v['generated'] = array_values(array_unique($v['generated']));
                    }
                }
                unset($v);
                $added[] = $fonts[$fi]['family'].' '.$weight.($style === 'italic' ? ' italic' : '').' ('.strtoupper($format).')';
            } catch (Throwable $e) {
                $errors[] = $name.': '.$e->getMessage();
            }
        }
        self::store($fonts);

        return ['added' => $added, 'errors' => $errors];
    }

    /** Rename a font (joining another of the same name), or change its fallback. */
    public static function update(string $id, ?string $family, ?string $fallback): ?string
    {
        $fonts = self::all();
        $at = self::indexOf($fonts, $id);
        if ($at === null) {
            return 'That font is gone.';
        }
        if ($fallback !== null && in_array($fallback, self::FALLBACKS, true)) {
            $fonts[$at]['fallback'] = $fallback;
        }
        if ($family !== null) {
            $family = self::cleanFamily($family);
            if ($family === '') {
                return 'A font needs a name: letters, numbers, spaces or dashes.';
            }
            foreach ($fonts as $i => $f) {
                if ($i !== $at && mb_strtolower($f['family']) === mb_strtolower($family)) {
                    // Same name as another font: they are one family now. Its variants come over,
                    // and where both have the same weight and style, the one being renamed wins.
                    foreach ($f['variants'] as $v) {
                        $clash = false;
                        foreach ($fonts[$at]['variants'] as $mine) {
                            $clash = $clash || ($mine['weight'] === $v['weight'] && $mine['style'] === $v['style']);
                        }
                        if ($clash) {
                            self::deleteFiles($v['files']);
                        } else {
                            $fonts[$at]['variants'][] = $v;
                        }
                    }
                    unset($fonts[$i]);
                }
            }
            $fonts[$at]['family'] = $family;
        }
        self::store(array_values($fonts));

        return null;
    }

    /** Give a variant another weight or style; it takes the place of one already there. */
    public static function updateVariant(string $fontId, string $variantId, int $weight, string $style): ?string
    {
        $fonts = self::all();
        $at = self::indexOf($fonts, $fontId);
        if ($at === null) {
            return 'That font is gone.';
        }
        $weight = self::weight($weight);
        $style = $style === 'italic' ? 'italic' : 'normal';
        foreach ($fonts[$at]['variants'] as $j => $v) {
            if ($v['id'] !== $variantId && $v['weight'] === $weight && $v['style'] === $style) {
                self::deleteFiles($v['files']);
                unset($fonts[$at]['variants'][$j]);
            }
        }
        foreach ($fonts[$at]['variants'] as &$v) {
            if ($v['id'] === $variantId) {
                $v['weight'] = $weight;
                $v['style'] = $style;
            }
        }
        unset($v);
        $fonts[$at]['variants'] = array_values($fonts[$at]['variants']);
        self::store($fonts);

        return null;
    }

    /** Delete one variant, or the whole font when $variantId is null. Files go too. */
    public static function delete(string $fontId, ?string $variantId = null): void
    {
        $fonts = self::all();
        $at = self::indexOf($fonts, $fontId);
        if ($at === null) {
            return;
        }
        foreach ($fonts[$at]['variants'] as $j => $v) {
            if ($variantId === null || $v['id'] === $variantId) {
                self::deleteFiles($v['files']);
                unset($fonts[$at]['variants'][$j]);
            }
        }
        $fonts[$at]['variants'] = array_values($fonts[$at]['variants']);
        if ($variantId === null || !$fonts[$at]['variants']) {
            try {
                Storage::disk('public')->deleteDirectory(self::DIR.'/'.$fontId);
            } catch (Throwable $e) {
            }
            unset($fonts[$at]);
        }
        self::store(array_values($fonts));
    }

    /** What the Customizer's Custom Font screen shows: the fonts with file addresses. */
    public static function forEditor(): array
    {
        return array_map(function ($f) {
            $f['variants'] = array_map(function ($v) {
                $v['urls'] = array_map([self::class, 'url'], $v['files']);

                return $v;
            }, $f['variants']);

            return $f;
        }, self::all());
    }

    /** Where a stored file is served: the site's own /storage, relative, so APP_URL cannot misdirect it. */
    public static function url(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    private static function store(array $fonts): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => self::OPTION], ['value' => json_encode(array_values($fonts), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        self::flush();
        if (function_exists('forget_cms_options_cache')) {
            forget_cms_options_cache();
        }
        // Every page declares the fonts in its <head>, so cached pages are out of date.
        if (function_exists('clear_page_cache')) {
            clear_page_cache();
        }
    }

    private static function put(string $path, string $bytes): void
    {
        if (!Storage::disk('public')->put($path, $bytes)) {
            throw new \RuntimeException('the file could not be saved');
        }
    }

    private static function deleteFiles(array $files): void
    {
        foreach ($files as $path) {
            if (self::validPath((string) $path)) {
                try {
                    Storage::disk('public')->delete($path);
                } catch (Throwable $e) {
                }
            }
        }
    }

    private static function indexOf(array $fonts, string $id): ?int
    {
        foreach ($fonts as $i => $f) {
            if ($f['id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Letters and their marks in any script (the vowel signs of Bangla, Hindi, Arabic are marks),
     * numbers, spaces, dashes, dots: safe inside a quoted CSS family name.
     */
    public static function cleanFamily(string $family): string
    {
        $family = preg_replace('/[^\p{L}\p{M}\p{N} \-_.]/u', '', $family) ?? '';

        return trim(mb_substr(preg_replace('/\s+/', ' ', $family) ?? '', 0, 60));
    }

    private static function guessFallback(string $family): string
    {
        $f = mb_strtolower($family);

        return match (true) {
            (bool) preg_match('/mono|code|courier|console/', $f) => 'monospace',
            (bool) preg_match('/serif/', $f) && !str_contains($f, 'sans') => 'serif',
            (bool) preg_match('/script|hand|brush|marker/', $f) => 'cursive',
            default => 'sans-serif',
        };
    }

    private static function weight($w): int
    {
        return max(100, min(900, (int) round(((int) $w ?: 400) / 100) * 100));
    }

    private static function newId(): string
    {
        return strtolower(Str::random(10));
    }

    private static function validId(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9]{6,24}$/', $id);
    }

    private static function validPath(string $p): bool
    {
        return (bool) preg_match('#^'.self::DIR.'/[a-z0-9]{6,24}/[a-z0-9]{6,24}\.(woff2|woff|ttf|otf)$#', $p);
    }
}
