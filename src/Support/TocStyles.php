<?php

namespace FalconCms\Core\Support;

/**
 * The Table of Contents element's presets and anchor rules — one source for both
 * renderers, and for the script that builds the list in the browser.
 *
 * Why this element is filled in by JavaScript, when nothing else in the builder is:
 * a table of contents is a list of the headings AROUND it. Those headings live in
 * sibling elements — a Heading here, a Text Block there, a Post Content pulling
 * markup out of the database — and the front-end renderer draws one element at a
 * time, so at the moment this one renders, the headings it is meant to list either
 * have not been rendered yet or are not the renderer's to see. Scanning the finished
 * document is not a shortcut around that; it is the only place the full list exists.
 *
 * That decision buys the two features a table of contents is actually judged on —
 * highlighting the section being read, and knowing how far down the page the reader
 * is — because both need the rendered document too. What it costs is a reader with
 * JavaScript disabled, who gets nothing; so the element renders nothing at all rather
 * than an empty box with a heading over it.
 *
 * Anchors are the other half. A heading can only be linked to if it has an id, and
 * most do not, so the script gives them one — which means PHP and the browser must
 * agree on how a heading becomes an id, or every link would point at nothing. The
 * slug rule below is the agreement, and TocTest checks both engines produce the same
 * ids for the same headings.
 */
class TocStyles
{
    /** Heading levels a table of contents can list. h1 is the page title, not a section. */
    public const LEVELS = [2, 3, 4, 5, 6];

    /**
     * Visual presets. As everywhere in the builder, a preset only supplies defaults —
     * every value stays editable, and an untouched one still follows the preset when
     * the author switches.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            'card' => [
                'name' => 'Card',
                'bg' => '#F7F9FB', 'borderColor' => '#E2E8EE', 'borderWidth' => 1,
                'radius' => 10, 'padY' => 16, 'padX' => 18,
                'titleColor' => '#171C23', 'titleSize' => 13, 'titleWeight' => '700',
                'linkColor' => '#434E5A', 'activeColor' => '#B9720F', 'hoverColor' => '#171C23',
                'fontSize' => 14, 'itemGap' => 7, 'indent' => 14,
                'guide' => false, 'marker' => 'none',
            ],
            'sidebar' => [
                'name' => 'Sidebar',
                'bg' => 'transparent', 'borderColor' => '#E2E8EE', 'borderWidth' => 0,
                'radius' => 0, 'padY' => 0, 'padX' => 0,
                'titleColor' => '#79838F', 'titleSize' => 11, 'titleWeight' => '700',
                'linkColor' => '#5A6572', 'activeColor' => '#B9720F', 'hoverColor' => '#171C23',
                'fontSize' => 13.5, 'itemGap' => 8, 'indent' => 12,
                'guide' => true, 'marker' => 'none',
            ],
            'inline' => [
                'name' => 'Inline',
                'bg' => 'transparent', 'borderColor' => '#E2E8EE', 'borderWidth' => 0,
                'radius' => 0, 'padY' => 6, 'padX' => 0,
                'titleColor' => '#171C23', 'titleSize' => 14, 'titleWeight' => '700',
                'linkColor' => '#B9720F', 'activeColor' => '#B9720F', 'hoverColor' => '#8A5509',
                'fontSize' => 14.5, 'itemGap' => 6, 'indent' => 16,
                'guide' => false, 'marker' => 'disc',
            ],
            'numbered' => [
                'name' => 'Numbered',
                'bg' => 'transparent', 'borderColor' => '#E2E8EE', 'borderWidth' => 0,
                'radius' => 0, 'padY' => 4, 'padX' => 0,
                'titleColor' => '#171C23', 'titleSize' => 14, 'titleWeight' => '700',
                'linkColor' => '#434E5A', 'activeColor' => '#B9720F', 'hoverColor' => '#171C23',
                'fontSize' => 14.5, 'itemGap' => 7, 'indent' => 18,
                'guide' => false, 'marker' => 'decimal',
            ],
            'minimal' => [
                'name' => 'Minimal',
                'bg' => 'transparent', 'borderColor' => '#EAEEF2', 'borderWidth' => 0,
                'radius' => 0, 'padY' => 0, 'padX' => 12,
                'titleColor' => '#79838F', 'titleSize' => 11, 'titleWeight' => '600',
                'linkColor' => '#5A6572', 'activeColor' => '#171C23', 'hoverColor' => '#171C23',
                'fontSize' => 13.5, 'itemGap' => 9, 'indent' => 12,
                'guide' => true, 'marker' => 'none',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function presetOptions(): array
    {
        return array_map(static fn ($p) => $p['name'], self::presets());
    }

    /**
     * One preset, always — an unknown key falls back rather than rendering unstyled.
     *
     * @return array<string, mixed>
     */
    public static function preset(?string $key): array
    {
        $all = self::presets();

        return $all[$key ?? ''] ?? $all['card'];
    }

    /**
     * Turn a heading's text into an id.
     *
     * This is the contract between PHP and the browser: the script assigns ids with
     * the same rule, so a link written on one side finds a heading marked on the
     * other. Lowercase, spaces and punctuation to single dashes, no leading or
     * trailing dash.
     *
     * Non-ASCII is kept rather than stripped. A page of Bengali headings would
     * otherwise slug every one of them to the empty string, and a table of contents
     * whose links all point at "#" is worse than none; ids may hold any character but
     * a space, and browsers have resolved percent-encoded fragments for twenty years.
     * The prefix below guarantees the result is never empty and never starts with a
     * digit, which a bare id may not do.
     */
    public static function slug(?string $text): string
    {
        $s = trim((string) $text);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

        // Anything that is not part of a word becomes a dash. \p{L} and \p{N} are what
        // keep non-Latin headings addressable; \p{M} keeps the combining marks that
        // carry the vowels in Bengali and most Indic scripts, without which a heading
        // would be slugged down to its bare consonants.
        $s = preg_replace('/[^\\p{L}\\p{N}\\p{M}]+/u', '-', $s) ?? '';
        $s = trim($s, '-');

        return $s === '' ? '' : $s;
    }

    /**
     * The id a heading gets, given the slug and how many headings before it produced
     * the same one.
     *
     * Two sections called "Installation" is not a mistake an author should have to
     * notice, so the second gets a -2 rather than a duplicate id that no browser would
     * scroll to. A heading whose text slugs to nothing — an icon, a number in a script
     * PHP cannot lowercase — falls back to its position, so it is still addressable.
     */
    public static function anchorId(string $slug, int $seen, int $index): string
    {
        $base = $slug === '' ? 'section-'.($index + 1) : 'h-'.$slug;

        return $seen > 0 ? $base.'-'.($seen + 1) : $base;
    }

    /**
     * Build the anchored list for a set of headings.
     *
     * Only the front end's fallback path and the tests use this — the browser builds
     * the live list — but it is the definition both mirror, and the one place the
     * numbering and de-duplication are written down.
     *
     * @param  array<int, array{level: int, text: string}>  $headings
     * @return array<int, array{level: int, text: string, id: string}>
     */
    public static function outline(array $headings, int $min = 2, int $max = 3): array
    {
        $out = [];
        $seen = [];

        foreach (array_values($headings) as $i => $h) {
            $level = (int) ($h['level'] ?? 0);
            if ($level < $min || $level > $max) {
                continue;
            }

            $slug = self::slug($h['text'] ?? '');
            $count = $seen[$slug] ?? 0;
            $seen[$slug] = $count + 1;

            $out[] = [
                'level' => $level,
                'text' => trim((string) ($h['text'] ?? '')),
                'id' => self::anchorId($slug, $count, $i),
            ];
        }

        return $out;
    }

    /**
     * A CSS selector an author may type, reduced to something that cannot escape the
     * attribute it is written into.
     *
     * The Scope and Exclude fields take selectors, which is what makes the element
     * useful on a theme it has never met — but a selector is free text going into an
     * HTML attribute, so quotes, angle brackets and backslashes come out. What is left
     * is enough for every selector these fields are for: tags, classes, ids,
     * descendants, attributes and lists.
     */
    public static function safeSelector(?string $selector): string
    {
        $s = preg_replace('/[^A-Za-z0-9 _.,:#>()\\[\\]="\'*+~^$|-]/', '', (string) $selector) ?? '';

        return trim(str_replace(['"', "'"], '', $s));
    }
}
