<?php

namespace FalconCms\Core\Support;

/**
 * The Callout element's variants and visual presets — one source for both renderers.
 *
 * A callout is the box a documentation page uses to say "careful" or "worth knowing":
 * an admonition. Two things decide how one looks, and they are deliberately separate.
 *
 * The VARIANT says what the box means — note, warning, danger — and carries the colour
 * and icon that meaning has everywhere: amber for a warning, red for a danger, a
 * lightbulb for a tip. An author picks it by what they are saying, not by what they
 * want it to look like.
 *
 * The PRESET says how that meaning is drawn — a left border, a soft fill, an outline —
 * and applies to every variant alike, so a page's callouts look like a set rather than
 * six unrelated boxes. This is why the two are not one list of twelve styles: an author
 * changing "note" to "warning" must not lose the look they chose, and an author
 * restyling the page must not have to revisit what each box means.
 *
 * Everything either supplies is overridable per element; they only provide the default.
 * The canvas receives these same arrays through @json(), so the Vue preview and the
 * Blade front end cannot drift.
 */
class CalloutStyles
{
    /**
     * What a callout can mean. Colour and icon per variant; the names and icons follow
     * what documentation tooling has already settled on, so a writer moving from
     * VitePress, Docusaurus or MkDocs finds what they expect.
     *
     * @return array<string, array{name: string, icon: string, accent: string, tint: string, ink: string}>
     */
    public static function variants(): array
    {
        return [
            'note' => [
                'name' => 'Note', 'icon' => 'fas fa-circle-info',
                'accent' => '#3B7DD8', 'tint' => '#EFF5FD', 'ink' => '#1B3F72',
            ],
            'tip' => [
                'name' => 'Tip', 'icon' => 'fas fa-lightbulb',
                'accent' => '#3E9C6D', 'tint' => '#EDF8F2', 'ink' => '#1D4F36',
            ],
            'success' => [
                'name' => 'Success', 'icon' => 'fas fa-circle-check',
                'accent' => '#3E7D4F', 'tint' => '#EDF6EF', 'ink' => '#1E4029',
            ],
            'important' => [
                'name' => 'Important', 'icon' => 'fas fa-star',
                'accent' => '#7C5BD9', 'tint' => '#F3F0FD', 'ink' => '#3A2A6B',
            ],
            'warning' => [
                'name' => 'Warning', 'icon' => 'fas fa-triangle-exclamation',
                'accent' => '#C8811A', 'tint' => '#FDF5E9', 'ink' => '#6B4310',
            ],
            'danger' => [
                'name' => 'Danger', 'icon' => 'fas fa-circle-exclamation',
                'accent' => '#C0392B', 'tint' => '#FDF0EE', 'ink' => '#6B211A',
            ],
            'question' => [
                'name' => 'Question', 'icon' => 'fas fa-circle-question',
                'accent' => '#2F8C99', 'tint' => '#ECF7F8', 'ink' => '#17494F',
            ],
            'example' => [
                'name' => 'Example', 'icon' => 'fas fa-flask',
                'accent' => '#6B7480', 'tint' => '#F4F6F8', 'ink' => '#2C333B',
            ],
            'quote' => [
                'name' => 'Quote', 'icon' => 'fas fa-quote-left',
                'accent' => '#8A8F98', 'tint' => '#F6F7F9', 'ink' => '#333940',
            ],
        ];
    }

    /**
     * How a callout is drawn. Each preset applies to every variant: the variant's accent
     * colour is poured into whichever of these shapes is chosen.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            'bar' => [
                'name' => 'Left bar',
                'fill' => 'tint', 'barWidth' => 4, 'borderWidth' => 0,
                'radius' => 8, 'padY' => 14, 'padX' => 16, 'gap' => 12,
                'iconStyle' => 'plain', 'iconSize' => 16,
                'titleWeight' => '700', 'titleSize' => 14, 'bodySize' => 15,
            ],
            'soft' => [
                'name' => 'Soft fill',
                'fill' => 'tint', 'barWidth' => 0, 'borderWidth' => 0,
                'radius' => 10, 'padY' => 16, 'padX' => 18, 'gap' => 12,
                'iconStyle' => 'badge', 'iconSize' => 15,
                'titleWeight' => '700', 'titleSize' => 14, 'bodySize' => 15,
            ],
            'outline' => [
                'name' => 'Outlined',
                'fill' => 'none', 'barWidth' => 0, 'borderWidth' => 1,
                'radius' => 10, 'padY' => 15, 'padX' => 17, 'gap' => 12,
                'iconStyle' => 'plain', 'iconSize' => 16,
                'titleWeight' => '700', 'titleSize' => 14, 'bodySize' => 15,
            ],
            'solid' => [
                'name' => 'Solid header',
                'fill' => 'tint', 'barWidth' => 0, 'borderWidth' => 1,
                'radius' => 10, 'padY' => 14, 'padX' => 16, 'gap' => 10,
                'iconStyle' => 'header', 'iconSize' => 14,
                'titleWeight' => '700', 'titleSize' => 13, 'bodySize' => 15,
            ],
            'minimal' => [
                'name' => 'Minimal',
                'fill' => 'none', 'barWidth' => 2, 'borderWidth' => 0,
                'radius' => 0, 'padY' => 4, 'padX' => 14, 'gap' => 10,
                'iconStyle' => 'none', 'iconSize' => 15,
                'titleWeight' => '700', 'titleSize' => 14, 'bodySize' => 15,
            ],
            'card' => [
                'name' => 'Card',
                'fill' => 'white', 'barWidth' => 0, 'borderWidth' => 1,
                'radius' => 12, 'padY' => 18, 'padX' => 20, 'gap' => 13,
                'iconStyle' => 'badge', 'iconSize' => 16,
                'titleWeight' => '700', 'titleSize' => 15, 'bodySize' => 15,
                'shadow' => true,
            ],
        ];
    }

    /** @return array<string, string> */
    public static function variantOptions(): array
    {
        return array_map(static fn ($v) => $v['name'], self::variants());
    }

    /** @return array<string, string> */
    public static function presetOptions(): array
    {
        return array_map(static fn ($p) => $p['name'], self::presets());
    }

    /**
     * One variant, always. An unknown name — an old shortcode, a typo in a hand-written
     * one — falls back to note rather than rendering a box with no colour at all.
     *
     * @return array{name: string, icon: string, accent: string, tint: string, ink: string}
     */
    public static function variant(?string $key): array
    {
        $all = self::variants();

        return $all[$key ?? ''] ?? $all['note'];
    }

    /**
     * One preset, always, for the same reason.
     *
     * @return array<string, mixed>
     */
    public static function preset(?string $key): array
    {
        $all = self::presets();

        return $all[$key ?? ''] ?? $all['bar'];
    }

    /**
     * The body's own block styles, as one template both renderers fill in.
     *
     * These cannot be inline styles. The body is rendered from a string of HTML — the
     * paragraphs and list items are produced by InlineMarkup, not written by hand — so
     * the only way to reach them is a stylesheet, and the canvas needs the same one the
     * page gets. It especially needs it: the admin runs a CSS reset that strips list
     * markers and paragraph margins from everything, so a list that looked right on the
     * page came out in the canvas as plain lines, and the two previews disagreed about
     * something as basic as whether a bullet was a bullet.
     *
     * Lists sit flush with the text above them rather than indented. A callout is a
     * short aside two or three lines long, and an indent inside a box that is already
     * indented from the page reads as a mistake; the marker goes inside the line box so
     * the left edge stays true.
     *
     * The placeholders are filled by str_replace here and by the same replacement in
     * the canvas, so there is one copy of the rules and no way for them to drift.
     */
    public static function bodyCssTemplate(): string
    {
        return <<<'CSS'
{sel} > p { margin: 0 0 .7em; }
{sel} ul, {sel} ol { margin: .2em 0 .7em; padding-left: 0; list-style-position: inside; }
{sel} ul { list-style-type: disc; }
{sel} ol { list-style-type: decimal; }
{sel} li { margin: .25em 0; }
{sel} > :last-child { margin-bottom: 0; }
{sel} a { color: {accent}; }
{sel} code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: .88em;
    background: rgba(125,135,150,.14);
    padding: .12em .38em;
    border-radius: 4px;
}
{sel} i { font-style: normal; }
{sel} .fc-mk-yes { color: #3E7D4F; }
{sel} .fc-mk-no { color: #B0392B; }
{sel} a.fc-mk-btn {
    display: inline-flex; align-items: center; gap: 7px;
    margin-top: .35em;
    padding: .5em .95em; border-radius: 6px;
    border: 1px solid transparent;
    font-weight: 600; font-size: .92em; line-height: 1.2;
    text-decoration: none; white-space: nowrap;
    transition: filter .15s ease, transform .15s ease, border-color .15s ease;
}
{sel} a.fc-mk-btn-primary { background: {accent}; color: #FFFFFF; }
{sel} a.fc-mk-btn-ghost { background: transparent; color: {text}; border-color: {accent}55; }
{sel} a.fc-mk-btn-soft {
    background: transparent;
    background: color-mix(in srgb, {accent} 14%, transparent);
    color: {accent};
}
{sel} a.fc-mk-btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
@media (prefers-reduced-motion: reduce) {
    {sel} a.fc-mk-btn { transition: none; }
    {sel} a.fc-mk-btn:hover { transform: none; }
}
CSS;
    }

    /**
     * The template above, filled in for one callout.
     *
     * Every button rule is written as `{sel} a.fc-mk-…` rather than as a bare class,
     * because the link rule above it matches a button too — a button IS an <a> inside
     * the body — and the descendant selector out-specifies a lone class whichever order
     * they are written in. Left bare, the label took the accent colour on top of an
     * accent-coloured button and the button came out blank.
     */
    public static function bodyCss(string $selector, string $accent, string $textColor): string
    {
        return str_replace(
            ['{sel}', '{accent}', '{text}'],
            [$selector, $accent, $textColor],
            self::bodyCssTemplate()
        );
    }

    /**
     * An icon class an author may type, reduced to something that cannot leave the
     * attribute it is written into.
     *
     * The Design tab offers a picker, but the field is free text — it has to be, since
     * a site can load any icon set — and free text in an attribute is how a class field
     * becomes an onclick. Letters, digits, spaces, dashes, underscores and colons is
     * enough for every icon library's class names and cannot close a quote.
     */
    public static function safeIcon(?string $class): string
    {
        return trim(preg_replace('/[^A-Za-z0-9 _:-]/', '', (string) $class) ?? '');
    }

    /**
     * The title a callout shows when the author has not written one.
     *
     * Blank is a real choice — a callout with no title is a common look — so an empty
     * string is honoured rather than replaced. This is only for a title that was never
     * set at all, which is what a freshly dropped element has.
     */
    public static function defaultTitle(?string $variant): string
    {
        return self::variant($variant)['name'];
    }
}
