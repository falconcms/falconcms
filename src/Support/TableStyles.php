<?php

namespace FalconCms\Core\Support;

/**
 * The Table element's presets, cell formatting and importers — one source for both
 * renderers.
 *
 * The builder has two independent renderers that must agree: the Vue canvas in the
 * admin and the Blade template on the front end. Anything duplicated between them
 * drifts, so the visual presets and the inline-markup rules live here once, in PHP,
 * and the canvas receives the same arrays through @json().
 *
 * Cells hold plain text with a small, fixed markup — `code`, **bold**, *italic*,
 * [text](url) — rather than HTML. Two reasons. It is exactly what an author pastes
 * out of a Markdown table, which is how most of these tables will arrive; and it
 * means a cell can never carry script, so no sanitiser has to stand between the
 * editor and the page. The rules below are written in the subset PCRE and
 * JavaScript's RegExp both read the same way, and TableTest checks that by running
 * the same cells through PHP and through node.
 */
class TableStyles
{
    /** Column alignments a cell can take. */
    public const ALIGNMENTS = ['left', 'center', 'right'];

    /**
     * Inline markup, applied in order. Each rule is [name, pattern, replacement],
     * with the pattern written without delimiters or flags so both engines compile it.
     *
     * Order matters: code spans come first so that `**not bold**` inside backticks
     * stays literal.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function inlineRules(): array
    {
        return [
            ['code', '`([^`]+)`', '<code>$1</code>'],

            // Links come before the icon tokens on purpose. A link needs the "](" that
            // no icon token has, so trying it first costs nothing — and trying it second
            // meant [check](https://…) became a tick with a stray "(https://…)" after
            // it, rather than a link whose label happens to be the word check.
            ['link', '\\[([^\\]]+)\\]\\(([^)\\s]+)\\)', '<a href="$2">$1</a>'],

            // Icons. A comparison or pricing table is mostly ticks and crosses, and an
            // author should not have to leave the cell to get one, so the two common
            // ones have their own token and anything else takes a Font Awesome class.
            // The class is limited to letters, digits, spaces, dashes and underscores —
            // there is no way to close the attribute and open another.
            ['iconyes', '\\[(?:check|yes|tick)\\]', '<i class="fas fa-check fc-tbl-yes"></i>'],
            ['iconno', '\\[(?:cross|no|x)\\]', '<i class="fas fa-times fc-tbl-no"></i>'],
            ['icon', '\\[icon\\s+([A-Za-z0-9 _-]+)\\]', '<i class="$1"></i>'],
            ['bold', '\\*\\*([^*]+)\\*\\*', '<strong>$1</strong>'],
            ['italic', '\\*([^*]+)\\*', '<em>$1</em>'],
            // Matched in its escaped form: cell() escapes before the rules run, so a
            // typed <br> has already become &lt;br&gt; by the time this is tried.
            ['break', '&lt;br\\s*/?&gt;', '<br>'],
        ];
    }

    /**
     * Visual presets. Each supplies the values the Design tab would otherwise ask an
     * author to pick one at a time; every one of them stays editable afterwards.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            'docs' => [
                'name' => 'Documentation',
                'textColor' => '#434E5A', 'bodyBg' => 'transparent',
                'headerBg' => '#F4F6F8', 'headerColor' => '#171C23', 'headerWeight' => '600',
                'borderColor' => '#DEE3E9', 'borders' => 'horizontal',
                'stripe' => false, 'stripeBg' => '#FAFBFC',
                'hover' => true, 'hoverBg' => '#F4F6F8',
                'cellPaddingY' => 10, 'cellPaddingX' => 14, 'fontSize' => 15, 'radius' => 8,
            ],
            'bordered' => [
                'name' => 'Bordered',
                'textColor' => '#434E5A', 'bodyBg' => 'transparent',
                'headerBg' => '#EAEEF2', 'headerColor' => '#171C23', 'headerWeight' => '700',
                'borderColor' => '#C9D0D8', 'borders' => 'all',
                'stripe' => false, 'stripeBg' => '#FAFBFC',
                'hover' => false, 'hoverBg' => '#F4F6F8',
                'cellPaddingY' => 10, 'cellPaddingX' => 14, 'fontSize' => 15, 'radius' => 6,
            ],
            'striped' => [
                'name' => 'Striped',
                'textColor' => '#434E5A', 'bodyBg' => 'transparent',
                'headerBg' => '#171C23', 'headerColor' => '#FFFFFF', 'headerWeight' => '600',
                'borderColor' => '#DEE3E9', 'borders' => 'none',
                'stripe' => true, 'stripeBg' => '#F4F6F8',
                'hover' => true, 'hoverBg' => '#EAEEF2',
                'cellPaddingY' => 11, 'cellPaddingX' => 14, 'fontSize' => 15, 'radius' => 8,
            ],
            'minimal' => [
                'name' => 'Minimal',
                'textColor' => '#434E5A', 'bodyBg' => 'transparent',
                'headerBg' => 'transparent', 'headerColor' => '#79838F', 'headerWeight' => '600',
                'borderColor' => '#EAEEF2', 'borders' => 'horizontal',
                'stripe' => false, 'stripeBg' => '#FAFBFC',
                'hover' => false, 'hoverBg' => '#F7F9FB',
                'cellPaddingY' => 12, 'cellPaddingX' => 6, 'fontSize' => 15, 'radius' => 0,
            ],
            'compact' => [
                'name' => 'Compact',
                'textColor' => '#434E5A', 'bodyBg' => 'transparent',
                'headerBg' => '#F4F6F8', 'headerColor' => '#434E5A', 'headerWeight' => '600',
                'borderColor' => '#E5E9EE', 'borders' => 'horizontal',
                'stripe' => false, 'stripeBg' => '#FAFBFC',
                'hover' => true, 'hoverBg' => '#F7F9FB',
                'cellPaddingY' => 6, 'cellPaddingX' => 10, 'fontSize' => 13.5, 'radius' => 6,
            ],
            'dark' => [
                'name' => 'Dark',
                'textColor' => '#C3CCD8', 'bodyBg' => '#11161D',
                'headerBg' => '#161C25', 'headerColor' => '#E7ECF2', 'headerWeight' => '600',
                'borderColor' => '#252E38', 'borders' => 'horizontal',
                'stripe' => true, 'stripeBg' => '#131922',
                'hover' => true, 'hoverBg' => '#1D242D',
                'cellPaddingY' => 10, 'cellPaddingX' => 14, 'fontSize' => 15, 'radius' => 8,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function presetOptions(): array
    {
        return array_map(static fn ($p) => $p['name'], self::presets());
    }

    /**
     * Render one cell: escape everything, then apply the inline markup.
     *
     * Escaping first is what makes this safe — by the time the rules run there is no
     * markup left in the text for them to complete, so the only tags in the result are
     * the ones they added themselves.
     */
    public static function cell(?string $text): string
    {
        $in = htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rules = self::inlineRules();

        $out = '';
        $len = strlen($in);
        $i = 0;

        // A single left-to-right scan, first matching rule wins, and what a rule
        // produces is never scanned again.
        //
        // Running the rules one after another over the whole string instead — the
        // obvious way — let the bold rule reach inside a code span the code rule had
        // already produced, so `**not bold**` came out bold. In Markdown backticks win,
        // and an author showing literal asterisks in a cell has no other way to do it.
        while ($i < $len) {
            $matched = false;

            foreach ($rules as [$name, $pattern, $replacement]) {
                if (preg_match(self::compile($pattern), $in, $m, 0, $i) !== 1 || $m[0] === '') {
                    continue;
                }

                if ($name === 'link') {
                    $href = $m[2];
                    // Escaping already neutralised quotes and angle brackets; this stops
                    // the one scheme that would still execute.
                    if (preg_match('/^\s*javascript:/i', html_entity_decode($href, ENT_QUOTES, 'UTF-8'))) {
                        $href = '#';
                    }
                    $out .= '<a href="'.$href.'">'.$m[1].'</a>';
                } else {
                    $out .= preg_replace_callback(
                        '/\$(\d)/',
                        static fn (array $g) => $m[(int) $g[1]] ?? '',
                        $replacement
                    ) ?? $replacement;
                }

                $i += strlen($m[0]);
                $matched = true;
                break;
            }

            if (!$matched) {
                $out .= $in[$i];
                $i++;
            }
        }

        return $out;
    }

    /**
     * Wrap a shared pattern for PCRE.
     *
     * The rules are written without delimiters because the canvas compiles the same
     * strings as JavaScript RegExps, which have none. One of them contains a literal
     * "/" — the self-closing slash in a <br/> — so the slash is escaped here rather
     * than in the rule; escaping it in the rule would reach the canvas as a stray
     * backslash. TableTest asserts no rule ships a pre-escaped slash, which would
     * double up here.
     */
    private static function compile(string $pattern): string
    {
        return '/'.str_replace('/', '\\/', $pattern).'/A';
    }

    /**
     * Escape one cell for a Markdown row.
     *
     * A pipe would end the cell and a newline would end the row, so both are escaped —
     * along with the backslash itself, or a cell ending in one would eat the separator
     * that follows it.
     */
    public static function escapeCell(?string $text): string
    {
        // & < > go out as entities so a cell's own <br> — which the renderer turns into
        // a line break, and which authors do type — can never be mistaken for one the
        // classic editor inserted in place of a newline. splitRow() decodes them again,
        // so what comes back is exactly what went out.
        $text = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string) $text);

        return str_replace(
            ['\\', '|', "\r\n", "\n", "\r"],
            ['\\\\', '\\|', '\\n', '\\n', '\\n'],
            $text
        );
    }

    /**
     * Render a grid as a Markdown table, which is what a Table element stores in its
     * shortcode body.
     *
     * The body is written to be read: a shortcode is a format people open, diff and
     * edit by hand, and a base64 blob makes all three impossible. Alignments and widths
     * travel as attributes rather than being inferred, so nothing is lost when there is
     * no header row to hang an alignment line under; the alignment line is still
     * written when there is one, because that is what makes it look like a table.
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, array<string, mixed>>  $cols
     */
    public static function toMarkdown(array $rows, array $cols, bool $headerRow = true): string
    {
        $rows = self::rectangular($rows);
        if ($rows === []) {
            return '';
        }

        $width = count($rows[0]);
        $lines = [];

        foreach ($rows as $i => $row) {
            $cells = array_map(static fn ($c) => self::escapeCell($c), $row);
            $lines[] = '| '.implode(' | ', $cells).' |';

            if ($i === 0 && $headerRow) {
                $rule = [];
                for ($c = 0; $c < $width; $c++) {
                    $align = $cols[$c]['align'] ?? 'left';
                    $rule[] = match ($align) {
                        'center' => ':---:',
                        'right' => '---:',
                        default => ':---',
                    };
                }
                $lines[] = '| '.implode(' | ', $rule).' |';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Split one Markdown row into cells, honouring the escapes escapeCell() writes.
     *
     * Scanned rather than split on a regex: a lookbehind for a backslash cannot tell
     * an escaped pipe from a pipe following an escaped backslash, so a cell ending in
     * "\" would swallow the separator after it.
     *
     * @return array<int, string>
     */
    public static function splitRow(string $line): array
    {
        $line = preg_replace('/^\|/', '', $line) ?? $line;
        $line = preg_replace('/\|$/', '', $line) ?? $line;

        $cells = [];
        $cur = '';
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];

            if ($ch === '\\' && $i + 1 < $len) {
                $next = $line[$i + 1];
                $cur .= match ($next) {
                    '|' => '|',
                    'n' => "\n",
                    '\\' => '\\',
                    default => '\\'.$next,
                };
                $i++;

                continue;
            }

            if ($ch === '|') {
                $cells[] = trim($cur, " \t");
                $cur = '';

                continue;
            }

            $cur .= $ch;
        }

        $cells[] = trim($cur, " \t");

        // Undo escapeCell()'s entities. A hand-written table gets the same treatment,
        // which is what an author typing &amp; into a cell means anyway.
        return array_map(
            static fn ($c) => html_entity_decode($c, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $cells
        );
    }

    /**
     * Parse a pasted Markdown table into rows plus per-column alignment.
     *
     * This is the reason the element exists in a usable form: the docs already hold
     * well over a thousand Markdown table rows, and retyping them into a grid cell by
     * cell is not a migration anyone would finish. A GitHub-style alignment row
     * (---, :---, :---:, ---:) is read as the column alignment and dropped.
     *
     * @return array{rows: array<int, array<int, string>>, align: array<int, string>}|null
     */
    public static function parseMarkdown(string $text): ?array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split("/\r\n|\r|\n/", $text) ?: []),
            static fn ($l) => $l !== ''
        ));

        if (count($lines) < 1) {
            return null;
        }

        // One splitter for both directions, so what toMarkdown() writes is exactly what
        // comes back — and a pipe the author escaped stays part of the text.
        $split = static fn (string $line): array => self::splitRow($line);

        $isAlignRow = static fn (string $line): bool => (bool) preg_match('/^\|?[\s:|-]+\|?$/', $line)
            && str_contains($line, '-');

        $rows = [];
        $align = [];

        foreach ($lines as $i => $line) {
            if ($i === 1 && $isAlignRow($line)) {
                foreach ($split($line) as $spec) {
                    $left = str_starts_with($spec, ':');
                    $right = str_ends_with($spec, ':');
                    $align[] = $left && $right ? 'center' : ($right ? 'right' : 'left');
                }

                continue;
            }
            $rows[] = $split($line);
        }

        if ($rows === []) {
            return null;
        }

        return ['rows' => self::rectangular($rows), 'align' => $align];
    }

    /**
     * Parse delimited text. The separator is detected from the first line rather than
     * asked for, because a spreadsheet paste is tab separated and a file is usually
     * comma separated, and an author should not have to know which they have.
     *
     * @return array<int, array<int, string>>|null
     */
    public static function parseDelimited(string $text, ?string $sep = null): ?array
    {
        $lines = array_values(array_filter(preg_split("/\r\n|\r|\n/", $text) ?: [], static fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return null;
        }

        if ($sep === null) {
            $sep = substr_count($lines[0], "\t") >= substr_count($lines[0], ',') ? "\t" : ',';
        }

        $rows = [];
        foreach ($lines as $line) {
            $parts = str_getcsv($line, $sep, '"', '\\');
            $rows[] = array_map(static fn ($c) => trim((string) $c), $parts);
        }

        return self::rectangular($rows);
    }

    /**
     * Pad every row to the widest one. A ragged grid would otherwise render rows with
     * missing trailing cells, which reads as a broken table rather than an empty one.
     *
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array<int, string>>
     */
    public static function rectangular(array $rows): array
    {
        $width = 0;
        foreach ($rows as $row) {
            $width = max($width, count($row));
        }
        $width = max(1, $width);

        return array_map(
            static fn ($row) => array_pad(array_values($row), $width, ''),
            array_values($rows)
        );
    }

    /**
     * Turn "2, 5-6" into [2, 5, 6], for the rows and columns an author wants picked
     * out. Numbers are 1-based and counted the way the table reads: row 1 is the first
     * body row, and the header is not a row you can highlight — it already stands out.
     *
     * Anything unparseable is skipped rather than throwing: this comes from a text
     * field someone typed into.
     *
     * @return array<int, int>
     */
    public static function parseSpec(?string $spec): array
    {
        $out = [];
        foreach (preg_split('/[,\s]+/', (string) $spec, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
                $from = min((int) $m[1], (int) $m[2]);
                $to = max((int) $m[1], (int) $m[2]);
                for ($i = $from; $i <= $to && $i - $from < 500; $i++) {
                    $out[] = $i;
                }
            } elseif (ctype_digit($part)) {
                $out[] = (int) $part;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Sort keys for a column, used by the front end's client-side sorting.
     *
     * Numbers, sizes and versions in a documentation table have to sort as values, not
     * as strings, or "10" lands before "9" and "v2.10.0" before "v2.9.0".
     */
    public static function sortKey(string $text): string
    {
        return trim(strip_tags($text));
    }
}
