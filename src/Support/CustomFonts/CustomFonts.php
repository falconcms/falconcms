<?php

namespace FalconCms\Core\Support\CustomFonts;

use FalconCms\Core\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fonts the site owner adds (Customizer → Typography → Custom Font), offered wherever a font is
 * picked — the theme's typography, the page builder, Motion Visual text — next to the Google
 * list, under "Custom Fonts".
 *
 * Each entry is one font face: a name, a weight, a style, and a file per format, picked from
 * the Media Library. Entries that share a name are one family with several weights. Files are
 * never taken on trust: a file is used only if it is in the Media Library and its own bytes
 * are the format of the slot it was picked for. Where a TTF / OTF has no WOFF beside it, one is
 * made (FontFile::toWoff), so every browser has a compact file to load.
 *
 * Kept apart from falcon_google_fonts() on purpose: that list decides what is requested from
 * Google, and Google answers 400 for the whole request when one family is unknown to it.
 *
 * Stored as JSON in the cms option custom_fonts, saved with the Customizer's Save.
 */
final class CustomFonts
{
    public const OPTION = 'custom_fonts';

    public const CATEGORY = 'Custom Fonts';

    /** Made WOFF copies live here, named by us; media files stay where the library put them. */
    public const DIR = 'fonts';

    /** What text falls back to while a font loads, or if it never does. */
    public const FALLBACKS = ['sans-serif', 'serif', 'monospace', 'cursive', 'system-ui'];

    /** CSS format() names, in the order browsers should try them: smallest first. */
    public const CSS_FORMATS = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

    public const MAX_FONTS = 100;

    /** @var list<array>|null */
    private static ?array $cache = null;

    /**
     * Every saved font face, checked as it is read back.
     *
     * @return list<array{id: string, family: string, weight: int, style: string, files: array<string, string>, made: array<string, string>}>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $raw = function_exists('get_cms_option') ? get_cms_option(self::OPTION, '[]') : '[]';
        $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $out = [];
        foreach (is_array($list) ? $list : [] as $e) {
            if (!is_array($e) || !self::validId((string) ($e['id'] ?? ''))) {
                continue;
            }
            $family = self::cleanFamily((string) ($e['family'] ?? ''));
            $files = array_filter((array) ($e['files'] ?? []), fn ($p, $fmt) => isset(self::CSS_FORMATS[$fmt]) && self::mediaPath((string) $p, (string) $fmt), ARRAY_FILTER_USE_BOTH);
            $made = array_filter((array) ($e['made'] ?? []), fn ($p, $fmt) => $fmt === 'woff' && self::madePath((string) $p), ARRAY_FILTER_USE_BOTH);
            if ($family === '' || (!$files && !$made)) {
                continue;
            }
            $out[] = [
                'id' => (string) $e['id'],
                'family' => $family,
                'weight' => self::weight($e['weight'] ?? 400),
                'style' => ($e['style'] ?? '') === 'italic' ? 'italic' : 'normal',
                'files' => $files,
                'made' => $made,
            ];
        }

        return self::$cache = $out;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** A family by name (any case): its name as saved, fallback and weights. */
    public static function find(string $family): ?array
    {
        $key = mb_strtolower(trim(trim($family), "'\""));
        $found = null;
        foreach (self::all() as $e) {
            if (mb_strtolower($e['family']) !== $key) {
                continue;
            }
            $found ??= ['family' => $e['family'], 'fallback' => self::fallbackFor($e['family']), 'weights' => []];
            $found['weights'][] = $e['weight'];
        }
        if ($found) {
            $found['weights'] = array_values(array_unique($found['weights']));
            sort($found['weights']);
        }

        return $found;
    }

    public static function isCustom(string $family): bool
    {
        return self::find($family) !== null;
    }

    /** A full font-family value: the family, then a fallback. */
    public static function stack(string $family): string
    {
        $f = self::find($family);

        return $f ? "'".$f['family']."', ".$f['fallback'] : '';
    }

    /**
     * The families in the shape every font picker already reads (falcon_google_fonts()):
     * family, category, variants (weights) — plus the fallback, for the value it writes.
     *
     * @return list<array{family: string, category: string, variants: list<string>, fallback: string}>
     */
    public static function catalog(): array
    {
        $seen = [];
        $out = [];
        foreach (self::all() as $e) {
            $key = mb_strtolower($e['family']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $f = self::find($e['family']);
            $out[] = ['family' => $f['family'], 'category' => self::CATEGORY, 'variants' => array_map('strval', $f['weights']), 'fallback' => $f['fallback']];
        }

        return $out;
    }

    /** The @font-face rules ('' when there are none). */
    public static function css(): string
    {
        $css = '';
        foreach (self::all() as $e) {
            $src = [];
            foreach (self::CSS_FORMATS as $fmt => $name) {
                $path = $e['files'][$fmt] ?? $e['made'][$fmt] ?? null;
                if ($path) {
                    $src[] = "url('".self::url($path)."') format('".$name."')";
                }
            }
            if ($src) {
                $css .= "@font-face{font-family:'".$e['family']."';src:".implode(',', $src)
                    .';font-weight:'.$e['weight'].';font-style:'.$e['style'].';font-display:swap}';
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
     * Save what the Customizer's Custom Font screen sent. Every file is checked again here —
     * the screen is not trusted: it must be a Media Library font of the slot's format. An entry
     * with nothing usable is dropped with a note. Returns the notes (empty when all was kept).
     *
     * @return list<string>
     */
    public static function save(array $entries): array
    {
        $notes = [];
        $keep = [];
        foreach (array_slice(array_values($entries), 0, self::MAX_FONTS) as $n => $e) {
            if (!is_array($e)) {
                continue;
            }
            $label = 'Font '.($n + 1);
            $family = self::cleanFamily((string) ($e['family'] ?? ''));
            $files = [];
            foreach (self::CSS_FORMATS as $fmt => $_) {
                $path = trim((string) ($e['files'][$fmt] ?? ''));
                if ($path === '') {
                    continue;
                }
                if (!self::verify($path, $fmt)) {
                    $notes[] = $label.': the '.strtoupper($fmt).' file is not a '.strtoupper($fmt).' font from the Media Library, so it was left out.';

                    continue;
                }
                $files[$fmt] = $path;
            }
            if (!$files) {
                if ($family !== '') {
                    $notes[] = $label.' ('.$family.'): it has no font file yet, so it was not saved.';
                }

                continue;
            }
            if ($family === '') {
                $family = self::familyFromFile(reset($files));
                $notes[] = $label.': it had no name; "'.$family.'" was read from its file.';
            }
            $id = self::validId((string) ($e['id'] ?? '')) ? (string) $e['id'] : strtolower(Str::random(10));
            $keep[] = [
                'id' => $id,
                'family' => $family,
                'weight' => self::weight($e['weight'] ?? 400),
                'style' => ($e['style'] ?? '') === 'italic' ? 'italic' : 'normal',
                'files' => $files,
                'made' => self::makeWoff($id, $files),
            ];
        }

        self::store($keep);
        self::sweep($keep);
        // Every page declares the fonts in its <head>, so cached pages are out of date.
        if (function_exists('clear_page_cache')) {
            clear_page_cache();
        }

        return $notes;
    }

    /** What the Custom Font screen starts from: the saved entries, with addresses for previews. */
    public static function forEditor(): array
    {
        return array_map(function ($e) {
            $e['names'] = array_map(fn ($p) => basename($p), $e['files']);

            return $e;
        }, self::all());
    }

    /** Where a stored file is served: the site's own /storage, relative, so APP_URL cannot misdirect it. */
    public static function url(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    /** Letters and their marks in any script (Bangla, Hindi and Arabic vowel signs are marks), numbers, spaces, dashes, dots. */
    public static function cleanFamily(string $family): string
    {
        $family = preg_replace('/[^\p{L}\p{M}\p{N} \-_.]/u', '', $family) ?? '';

        return trim(mb_substr(preg_replace('/\s+/', ' ', $family) ?? '', 0, 60));
    }

    /** A Media Library font whose own bytes are the slot's format (TTF and OTF are both sfnt). */
    public static function verify(string $path, string $format): bool
    {
        if (!self::mediaPath($path, $format)) {
            return false;
        }
        try {
            if (!Media::where('path', $path)->exists() || !Storage::disk('public')->exists($path)) {
                return false;
            }
            $head = (string) Storage::disk('public')->get($path);
        } catch (Throwable $e) {
            return false;
        }
        $actual = FontFile::format($head);

        return $actual === $format || (in_array($actual, ['ttf', 'otf'], true) && in_array($format, ['ttf', 'otf'], true));
    }

    /** A WOFF made from the TTF / OTF when no WOFF was picked; ['woff' => path] or []. */
    private static function makeWoff(string $id, array $files): array
    {
        if (!empty($files['woff'])) {
            return [];
        }
        $source = $files['ttf'] ?? $files['otf'] ?? null;
        if (!$source) {
            return [];
        }
        try {
            $disk = Storage::disk('public');
            // Named after the source, so a different file makes a new copy and the old one is swept.
            $path = self::DIR.'/'.$id.'-'.substr(sha1($source.'|'.$disk->size($source)), 0, 10).'.woff';
            if (!$disk->exists($path)) {
                $woff = FontFile::toWoff((string) $disk->get($source));
                if ($woff === null || !$disk->put($path, $woff)) {
                    return [];
                }
            }

            return ['woff' => $path];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Made copies no entry uses any more. Only our own folder is touched — never the library. */
    private static function sweep(array $entries): void
    {
        $used = [];
        foreach ($entries as $e) {
            foreach ($e['made'] as $p) {
                $used[$p] = true;
            }
        }
        try {
            foreach (Storage::disk('public')->files(self::DIR) as $file) {
                if (self::madePath($file) && !isset($used[$file])) {
                    Storage::disk('public')->delete($file);
                }
            }
        } catch (Throwable $e) {
        }
    }

    private static function store(array $entries): void
    {
        DB::table('cms_settings')->updateOrInsert(['key' => self::OPTION], ['value' => json_encode(array_values($entries), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        self::flush();
        if (function_exists('forget_cms_options_cache')) {
            forget_cms_options_cache();
        }
    }

    private static function familyFromFile(string $path): string
    {
        try {
            $meta = FontFile::describe((string) Storage::disk('public')->get($path));
        } catch (Throwable $e) {
            $meta = null;
        }

        return self::cleanFamily($meta['family'] ?? '') ?: self::cleanFamily(FontFile::fromName(basename($path))['family']) ?: 'Custom Font';
    }

    private static function fallbackFor(string $family): string
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

    private static function validId(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9]{6,24}$/', $id);
    }

    /** media/…/name.<format>, no way out of the folder. */
    private static function mediaPath(string $p, string $format): bool
    {
        return (bool) preg_match('#^media/[A-Za-z0-9_\-/.]+\.'.preg_quote($format, '#').'$#', $p) && !str_contains($p, '..');
    }

    private static function madePath(string $p): bool
    {
        return (bool) preg_match('#^'.self::DIR.'/[a-z0-9]{6,24}-[a-f0-9]{10}\.woff$#', $p);
    }
}
