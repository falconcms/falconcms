<?php

namespace FalconCms\Core\Support\CustomFonts;

/**
 * One uploaded font file: what it is (by its own bytes, not its name), what it says about
 * itself (family, weight, italic), and — for TrueType / OpenType — a WOFF copy of it.
 *
 * Why the WOFF copy: WOFF is the same font, its tables zlib-compressed, and every browser in
 * use reads it. WOFF2 (Brotli) is smaller and read by every modern browser, but cannot be
 * made here without extra libraries, so an uploaded WOFF2 is served as it is. With a TTF or
 * OTF upload the page always has WOFF first, then the original as a last resort.
 *
 * Reading the font's own tables (name, OS/2, head) is what fills in the family and weight on
 * upload; a WOFF2 cannot be read without Brotli, so its file name is used instead.
 */
final class FontFile
{
    public const FORMATS = ['woff2', 'woff', 'ttf', 'otf'];

    /** Largest font accepted, in bytes. A full CJK font is about this size. */
    public const MAX_BYTES = 12 * 1024 * 1024;

    private const WEIGHT_WORDS = [
        'hairline' => 100, 'thin' => 100, 'extralight' => 200, 'ultralight' => 200, 'light' => 300,
        'book' => 400, 'regular' => 400, 'normal' => 400, 'roman' => 400, 'medium' => 500,
        'semibold' => 600, 'demibold' => 600, 'demi' => 600, 'bold' => 700,
        'extrabold' => 800, 'ultrabold' => 800, 'heavy' => 900, 'black' => 900, 'ultra' => 900,
    ];

    /** woff2 | woff | ttf | otf from the file's first bytes; null for anything else. */
    public static function format(string $bytes): ?string
    {
        $sig = substr($bytes, 0, 4);

        return match (true) {
            $sig === 'wOF2' => 'woff2',
            $sig === 'wOFF' => 'woff',
            $sig === "\x00\x01\x00\x00" || $sig === 'true' => 'ttf',
            $sig === 'OTTO' => 'otf',
            default => null,
        };
    }

    /**
     * What the font says about itself, where it can be read.
     *
     * @return array{family: string, weight: int, italic: bool}|null null when the tables cannot be read
     */
    public static function describe(string $bytes): ?array
    {
        $tables = self::tables($bytes);
        if ($tables === null) {
            return null;
        }
        $family = isset($tables['name']) ? self::familyName($tables['name']) : '';
        $weight = 400;
        $italic = false;
        if (isset($tables['OS/2']) && strlen($tables['OS/2']) >= 64) {
            $weight = self::roundWeight(unpack('n', $tables['OS/2'], 4)[1]);
            $italic = (bool) (unpack('n', $tables['OS/2'], 62)[1] & 1);
        }
        if (!$italic && isset($tables['head']) && strlen($tables['head']) >= 46) {
            $italic = (bool) (unpack('n', $tables['head'], 44)[1] & 2);
        }

        return ['family' => $family, 'weight' => $weight, 'italic' => $italic];
    }

    /**
     * Family, weight and italic from a file name: "OpenSans-SemiBoldItalic.woff2" →
     * Open Sans, 600, italic. Used for WOFF2, and as the family when the font has none.
     *
     * @return array{family: string, weight: int, italic: bool}
     */
    public static function fromName(string $fileName): array
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $base = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $base) ?? $base; // OpenSans → Open Sans
        $words = preg_split('/[\s_\-.]+/', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $weight = 400;
        $italic = false;
        $family = [];
        for ($i = 0; $i < count($words); $i++) {
            $w = strtolower($words[$i]);
            $pair = isset($words[$i + 1]) ? $w.strtolower($words[$i + 1]) : '';
            if (isset(self::WEIGHT_WORDS[$pair])) {   // "Semi Bold", "Extra Light"
                $weight = self::WEIGHT_WORDS[$pair];
                $i++;
            } elseif (isset(self::WEIGHT_WORDS[$w])) {
                $weight = self::WEIGHT_WORDS[$w];
            } elseif ($w === 'italic' || $w === 'oblique' || $w === 'it') {
                $italic = true;
            } elseif (preg_match('/^(\d)00$/', $w)) {
                $weight = (int) $w;
            } elseif (!in_array($w, ['webfont', 'web', 'font', 'vf', 'variable'], true)) {
                $family[] = $words[$i];
            }
        }

        return ['family' => ucwords(trim(implode(' ', $family))), 'weight' => $weight, 'italic' => $italic];
    }

    /** The same TrueType / OpenType font as WOFF 1.0, or null when it cannot be made. */
    public static function toWoff(string $sfnt): ?string
    {
        if (!function_exists('gzcompress') || !in_array(self::format($sfnt), ['ttf', 'otf'], true)) {
            return null;
        }
        $num = unpack('n', $sfnt, 4)[1];
        $flavor = substr($sfnt, 0, 4);
        $entries = [];
        for ($i = 0; $i < $num; $i++) {
            $rec = substr($sfnt, 12 + 16 * $i, 16);
            if (strlen($rec) < 16) {
                return null;
            }
            $r = unpack('a4tag/Nsum/Noffset/Nlength', $rec);
            $data = substr($sfnt, $r['offset'], $r['length']);
            if (strlen($data) !== $r['length']) {
                return null;
            }
            $entries[$r['tag']] = ['sum' => $r['sum'], 'data' => $data];
        }
        ksort($entries, SORT_STRING); // the directory is in ascending tag order

        $headerSize = 44 + 20 * count($entries);
        $offset = $headerSize;
        $dir = '';
        $body = '';
        $sfntSize = 12 + 16 * count($entries);
        foreach ($entries as $tag => $t) {
            $orig = strlen($t['data']);
            $packed = gzcompress($t['data'], 9);
            $store = ($packed !== false && strlen($packed) < $orig) ? $packed : $t['data'];
            $dir .= pack('a4NNNN', $tag, $offset, strlen($store), $orig, $t['sum']);
            $pad = (4 - strlen($store) % 4) % 4;
            $body .= $store.str_repeat("\0", $pad);
            $offset += strlen($store) + $pad;
            $sfntSize += $orig + (4 - $orig % 4) % 4;
        }
        $header = 'wOFF'.$flavor.pack('NnnNnnNNNNN', $offset, count($entries), 0, $sfntSize, 1, 0, 0, 0, 0, 0, 0);

        return $header.$dir.$body;
    }

    /** @return array<string, string>|null the font's tables by tag, uncompressed */
    public static function tables(string $bytes): ?array
    {
        $format = self::format($bytes);
        $tables = [];
        if ($format === 'ttf' || $format === 'otf') {
            $num = unpack('n', $bytes, 4)[1];
            for ($i = 0; $i < $num; $i++) {
                $rec = substr($bytes, 12 + 16 * $i, 16);
                if (strlen($rec) < 16) {
                    return null;
                }
                $r = unpack('a4tag/Nsum/Noffset/Nlength', $rec);
                $tables[$r['tag']] = substr($bytes, $r['offset'], $r['length']);
            }

            return $tables;
        }
        if ($format === 'woff' && strlen($bytes) >= 44) {
            $num = unpack('n', $bytes, 12)[1];
            for ($i = 0; $i < $num; $i++) {
                $rec = substr($bytes, 44 + 20 * $i, 20);
                if (strlen($rec) < 20) {
                    return null;
                }
                $r = unpack('a4tag/Noffset/Ncomp/Norig/Nsum', $rec);
                $data = substr($bytes, $r['offset'], $r['comp']);
                if ($r['comp'] < $r['orig']) {
                    $data = function_exists('gzuncompress') ? @gzuncompress($data) : false;
                    if ($data === false) {
                        return null;
                    }
                }
                $tables[$r['tag']] = $data;
            }

            return $tables;
        }

        return null; // WOFF2 needs Brotli to read
    }

    /** The family from the name table: the typographic family (16) if given, else 1. */
    private static function familyName(string $name): string
    {
        if (strlen($name) < 6) {
            return '';
        }
        $h = unpack('nformat/ncount/nstrings', $name);
        $found = [];
        for ($i = 0; $i < $h['count']; $i++) {
            $rec = substr($name, 6 + 12 * $i, 12);
            if (strlen($rec) < 12) {
                break;
            }
            $r = unpack('nplatform/nencoding/nlanguage/nid/nlength/noffset', $rec);
            if ($r['id'] !== 1 && $r['id'] !== 16) {
                continue;
            }
            $raw = substr($name, $h['strings'] + $r['offset'], $r['length']);
            if ($r['platform'] === 3 || $r['platform'] === 0) {
                $text = (string) @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
            } elseif ($r['platform'] === 1) {
                $text = (string) @mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
            } else {
                continue;
            }
            // English (US) first, then whatever is there
            $rank = ($r['platform'] === 3 && $r['language'] === 0x409) ? 0 : 1;
            $found[$r['id']][$rank] ??= trim($text);
        }
        foreach ([16, 1] as $id) {
            if (!empty($found[$id])) {
                ksort($found[$id]);

                return (string) reset($found[$id]);
            }
        }

        return '';
    }

    private static function roundWeight(int $w): int
    {
        return max(100, min(900, (int) round($w / 100) * 100));
    }
}
