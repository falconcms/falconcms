<?php

namespace FalconCms\Core\Support;

/**
 * The small inline markup a builder element's plain-text field understands, and the
 * one renderer that applies it.
 *
 * Several elements need the same thing: a field an author types into that is text,
 * not HTML — so it can never carry script and no sanitiser has to stand between the
 * editor and the page — but that still lets them write a word in bold, a bit of
 * code, a link or a button. The Table's cells were the first; the Callout's body is
 * the second. Written twice they would drift, and an author would find that `code`
 * works in one and not the other.
 *
 * The rules are also the canvas's rules. The builder has two independent renderers
 * that must agree — the Vue canvas in the admin and Blade on the front end — so the
 * patterns are written in the subset PCRE and JavaScript's RegExp read the same way,
 * shipped to the canvas through @json(), and checked in both engines by running the
 * same strings through node in InlineMarkupTest.
 */
class InlineMarkup
{
    /**
     * Inline markup, applied in order. Each rule is [name, pattern, replacement],
     * with the pattern written without delimiters or flags so both engines compile it.
     *
     * Order matters: code spans come first so that `**not bold**` inside backticks
     * stays literal.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function rules(): array
    {
        return [
            ['code', '`([^`]+)`', '<code>$1</code>'],

            // Buttons, before links and for the same reason links come before the icon
            // tokens: [button Buy Pro](…) is also a valid link, one whose label happens
            // to start with the word button, so whichever rule is tried first decides.
            // A pricing table's last row is a row of buttons, and an author should not
            // have to leave the table to get one.
            //
            // The variant is one of three fixed words or nothing at all, so what lands
            // in the class attribute can only ever be one of those three.
            ['button', '\\[(?:button|btn)(?::(primary|ghost|soft))?\\s+([^\\]]+)\\]\\(([^)\\s]+)\\)',
                '<a class="fc-mk-btn fc-mk-btn-primary" href="$3">$2</a>'],

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
            ['iconyes', '\\[(?:check|yes|tick)\\]', '<i class="fas fa-check fc-mk-yes"></i>'],
            ['iconno', '\\[(?:cross|no|x)\\]', '<i class="fas fa-times fc-mk-no"></i>'],
            ['icon', '\\[icon\\s+([A-Za-z0-9 _-]+)\\]', '<i class="$1"></i>'],
            ['bold', '\\*\\*([^*]+)\\*\\*', '<strong>$1</strong>'],
            ['italic', '\\*([^*]+)\\*', '<em>$1</em>'],
            // Matched in its escaped form: cell() escapes before the rules run, so a
            // typed <br> has already become &lt;br&gt; by the time this is tried.
            ['break', '&lt;br\\s*/?&gt;', '<br>'],
        ];
    }

    /**
     * Render one field: escape everything, then apply the inline markup.
     *
     * Escaping first is what makes this safe — by the time the rules run there is no
     * markup left in the text for them to complete, so the only tags in the result are
     * the ones they added themselves.
     */
    public static function render(?string $text): string
    {
        $in = htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rules = self::rules();

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

                if ($name === 'link' || $name === 'button') {
                    // The button rule captures its variant first, so its label and href
                    // sit one group further along than a link's.
                    $isButton = $name === 'button';
                    $label = $isButton ? $m[2] : $m[1];
                    $href = $isButton ? $m[3] : $m[2];

                    // Escaping already neutralised quotes and angle brackets; this stops
                    // the one scheme that would still execute.
                    if (preg_match('/^\s*javascript:/i', html_entity_decode($href, ENT_QUOTES, 'UTF-8'))) {
                        $href = '#';
                    }

                    $class = $isButton
                        ? ' class="fc-mk-btn fc-mk-btn-'.(($m[1] ?? '') !== '' ? $m[1] : 'primary').'"'
                        : '';

                    $out .= '<a'.$class.' href="'.$href.'">'.$label.'</a>';
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
     * Render a multi-line field: paragraphs, lists, and the inline markup inside them.
     *
     * A table cell is one line, so `render()` is all it needs. A Callout's body is
     * prose — two or three sentences, often with a short list — and an author typing
     * into a plain textarea expects a blank line to start a new paragraph and a line
     * beginning "- " to start a bullet, because that is what every other box they type
     * into does.
     *
     * Deliberately not Markdown. There are no headings, no nesting and no block quotes:
     * a Callout containing a heading is a Callout that should have been a section, and
     * every construct added here is one more thing the canvas has to mirror exactly.
     * What is here covers what these boxes actually hold.
     */
    public static function blocks(?string $text): string
    {
        $lines = preg_split('/
||
/', (string) $text) ?: [];
        $out = '';
        $para = [];
        $list = [];
        $listTag = '';

        $flushPara = function () use (&$out, &$para) {
            if ($para !== []) {
                $out .= '<p>'.implode('<br>', array_map([self::class, 'render'], $para)).'</p>';
                $para = [];
            }
        };
        $flushList = function () use (&$out, &$list, &$listTag) {
            if ($list !== []) {
                $out .= '<'.$listTag.'>';
                foreach ($list as $item) {
                    $out .= '<li>'.self::render($item).'</li>';
                }
                $out .= '</'.$listTag.'>';
                $list = [];
                $listTag = '';
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushList();
                $flushPara();

                continue;
            }

            // "- " and "* " open a bullet; "1. " opens a numbered list. The space is what
            // separates a bullet from *italic*, which has none after its asterisk.
            if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $m)) {
                $flushPara();
                if ($listTag !== 'ul') {
                    $flushList();
                    $listTag = 'ul';
                }
                $list[] = $m[1];

                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/', $trimmed, $m)) {
                $flushPara();
                if ($listTag !== 'ol') {
                    $flushList();
                    $listTag = 'ol';
                }
                $list[] = $m[1];

                continue;
            }

            $flushList();
            $para[] = $trimmed;
        }

        $flushList();
        $flushPara();

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
}
