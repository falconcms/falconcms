<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\CodeHighlighter;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * The Code Block element.
 *
 * Its whole design rests on one bet: that a single set of language rules can drive
 * both renderers. The front end highlights in PHP; the canvas highlights the same
 * source in JavaScript from the same rules, handed over as JSON. If the two engines
 * ever read a pattern differently, an editor would style a snippet against one set
 * of colours and publish another — and nothing in the request path would say so.
 * So the parity test below is the important one; the rest guard the edges around it.
 */
class CodeBlockTest extends TestCase
{
    /** Samples chosen for the constructs that break naive highlighters. */
    private function samples(): array
    {
        return [
            'php' => "<?php\n/* block\n   comment */\nnamespace App;\nclass Foo extends Bar {\n    public function run(\$x = 10, \$s = 'hi') {\n        // line\n        return \$x <=> 3.14 ? \"a{\$s}b\" : null;\n    }\n}\n",
            'javascript' => "// note\nimport { a } from './m';\nconst f = async (x = 1) => {\n  const s = `tpl \${x}`;\n  return x === 2 ? [1,2] : {k: 'v'};\n};\n/* multi\nline */\n",
            'typescript' => "interface P { id: number; name?: string }\nexport const f = (x: string): Promise<string> => Promise.resolve(x);\n",
            'html' => "<!-- hi -->\n<div class=\"a\" data-x='1'>\n  <img src=\"a.png\"/>\n</div>\n",
            'blade' => "{{-- note --}}\n@foreach(\$items as \$i)\n  <span class=\"x\">{{ \$i }}</span>\n@endforeach\n",
            'css' => "/* c */\n:root { --accent: #B9720F; }\n.a > .b:hover { padding: 12px 0; color: rgba(0,0,0,.5); }\n",
            'json' => "{\n  \"name\": \"falcon\",\n  \"n\": -12.5,\n  \"ok\": true,\n  \"list\": [1, null]\n}\n",
            'sql' => "-- note\nSELECT id, COUNT(*) FROM posts\nWHERE slug LIKE 'a%' AND id >= 10\nORDER BY id DESC LIMIT 5;\n",
            'bash' => "#!/bin/bash\n# comment\nfor f in \"\$@\"; do\n  echo \"\$f\" | grep -q 'x' && docker compose up -d app\ndone\n",
            'python' => "# note\ndef f(a, b=2):\n    \"\"\"doc\"\"\"\n    return [x for x in range(a) if x != b]\n",
            'yaml' => "# note\nname: falcon\nversion: 1.2\nflag: true\n",
            'xml' => "<root xmlns:a=\"u\">\n  <a:item id=\"1\">text</a:item>\n</root>\n",
        ];
    }

    /**
     * The Code Block is a Pro element, and the builder decides that from one list.
     * Left out of it, the element would be free on every site while the pricing page
     * said otherwise — and the gate is not only cosmetic: the same list stops a locked
     * element being edited, moved or dragged.
     */
    public function test_the_code_block_is_gated_behind_pro(): void
    {
        $scripts = file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        preg_match('/const proElementTypes = \[(.*?)\];/s', $scripts, $m);
        $this->assertNotEmpty($m, 'the Pro element list is gone');
        $this->assertStringContainsString("'code_block'", $m[1], 'the Code Block element is not gated behind Pro');
    }

    // ---- the rules themselves --------------------------------------------------

    /**
     * CodeHighlighter::compile() escapes "/" so a rule can carry a line comment or a
     * closing tag. A rule that escapes its own slash would arrive doubled here — and
     * would reach the canvas, which uses no delimiters at all, as a stray backslash.
     */
    public function test_no_rule_pre_escapes_its_delimiter(): void
    {
        foreach (CodeHighlighter::languages() as $slug => $lang) {
            foreach ($lang['rules'] as [$token, $pattern]) {
                $this->assertStringNotContainsString('\\/', $pattern,
                    "{$slug}/{$token} escapes its own slash; compile() escapes it again");
            }
        }
    }

    /** Every colour a rule can emit has to exist in every theme, or a token renders unstyled. */
    public function test_every_theme_covers_every_token(): void
    {
        $used = [];
        foreach (CodeHighlighter::languages() as $lang) {
            foreach ($lang['rules'] as [$token, $_]) {
                $used[$token] = true;
            }
        }

        foreach (array_keys($used) as $token) {
            $this->assertContains($token, CodeHighlighter::TOKENS, "rule emits unknown token: {$token}");
        }

        foreach (CodeHighlighter::themes() as $slug => $theme) {
            foreach (CodeHighlighter::TOKENS as $token) {
                $this->assertArrayHasKey($token, $theme['tokens'], "theme {$slug} has no colour for {$token}");
            }
            foreach (['bg', 'fg', 'border', 'chrome', 'chromeText', 'lineNo', 'mark', 'name'] as $key) {
                $this->assertArrayHasKey($key, $theme, "theme {$slug} is missing {$key}");
            }
        }
    }

    // ---- the bet ---------------------------------------------------------------

    /**
     * The same rules, the same code, both engines — token for token.
     *
     * PCRE and JavaScript agree on the subset the rules are written in, but only as
     * long as they stay inside it: a lookbehind, a possessive quantifier or a \Z
     * would compile in one and either throw or mean something else in the other.
     * This runs the shared rules through node and compares the streams.
     */
    public function test_php_and_javascript_tokenize_identically(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot check cross-engine parity');
        }

        $samples = $this->samples();
        $dir = sys_get_temp_dir().'/fc-code-'.getmypid();
        @mkdir($dir, 0777, true);

        file_put_contents($dir.'/langs.json', json_encode(CodeHighlighter::languages(), JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/samples.json', json_encode($samples, JSON_UNESCAPED_SLASHES));
        file_put_contents($dir.'/run.js', $this->mirrorScript());

        $cmd = escapeshellarg($node).' '.escapeshellarg($dir.'/run.js')
             .' '.escapeshellarg($dir.'/langs.json').' '.escapeshellarg($dir.'/samples.json').' 2>&1';
        $out = shell_exec($cmd);

        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        $js = json_decode((string) $out, true);
        $this->assertIsArray($js, "the JavaScript mirror did not return JSON:\n".$out);

        foreach ($samples as $lang => $code) {
            $this->assertSame(
                CodeHighlighter::tokenize($code, $lang),
                $js[$lang] ?? null,
                "PHP and JavaScript disagree on {$lang} — the canvas would colour it differently to the page"
            );
        }
    }

    // ---- rendering edges -------------------------------------------------------

    /**
     * A block comment runs past a newline. Splitting the rendered HTML would leave a
     * <span> open at the end of one line and orphaned at the start of the next, so
     * the split happens on the token list instead and the span re-opens per line.
     */
    public function test_a_token_spanning_newlines_produces_valid_markup_on_every_line(): void
    {
        $lines = CodeHighlighter::highlightLines("<?php\n/* one\n   two\n   three */\n\$x = 1;\n", 'php');

        foreach ($lines as $i => $line) {
            $this->assertSame(
                substr_count($line, '<span'),
                substr_count($line, '</span>'),
                'line '.($i + 1).' has unbalanced spans: '.$line
            );
        }

        $this->assertStringContainsString('fc-t-comment', $lines[1]);
        $this->assertStringContainsString('fc-t-comment', $lines[2]);
        $this->assertStringContainsString('fc-t-comment', $lines[3]);
    }

    /** Code is content, not markup: it must never reach the page as live HTML. */
    public function test_code_is_escaped(): void
    {
        $out = CodeHighlighter::highlight('<script>alert(1)</script>', 'html');

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;', $out);
    }

    public function test_an_unknown_language_still_shows_the_code(): void
    {
        $out = CodeHighlighter::highlight('a < b', 'brainfuck');

        $this->assertSame('a &lt; b', $out);
    }

    public function test_line_spec_accepts_numbers_and_ranges(): void
    {
        $this->assertSame([3, 7, 8, 9], CodeHighlighter::parseLineSpec('3, 7-9'));
        $this->assertSame([2, 1], CodeHighlighter::parseLineSpec('2 1'));
        $this->assertSame([], CodeHighlighter::parseLineSpec('nonsense'));
        $this->assertSame([], CodeHighlighter::parseLineSpec(null));
        // Reversed ranges are a typo, not an error.
        $this->assertSame([4, 5, 6], CodeHighlighter::parseLineSpec('6-4'));
    }

    // ---- the element -----------------------------------------------------------

    /**
     * Code carries newlines, quotes and square brackets. An array literal in a
     * snippet would be read back as a nested shortcode, so the body is base64 —
     * this checks a snippet built from exactly those characters survives.
     */
    public function test_shortcode_round_trip_keeps_the_code_byte_for_byte(): void
    {
        $code = "<?php\n\$a = ['x' => \"y\", 'z' => [1, 2]];\nif (\$a['x'] === \"y\") { echo \"[falcon_row]\"; }\n";

        $layout = [[
            'id' => 'r1', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%',
                'settings' => [],
                'elements' => [[
                    'id' => 'e1', 'type' => 'code_block', 'settings' => [
                        'code' => $code,
                        'language' => 'php',
                        'codeTheme' => 'midnight',
                        'showChrome' => true,
                        'filename' => 'app/Test.php',
                        'startLine' => 5,
                        'highlightLines' => '2, 4-5',
                        'copyLabel' => 'Copy code',
                        'typeMode' => 'typewriter',
                        'typeSpeed' => 25,
                        'typeStart' => 'load',
                        'typeCaret' => false,
                        'wrapLines' => true,
                        'maxHeight' => 320,
                        'visibility' => ['mobile' => false, 'tablet' => true, 'desktop' => true],
                    ],
                ]],
            ]],
        ]];

        $shortcode = BuilderShortcodeConverter::jsonToShortcodes(json_encode($layout));
        $this->assertStringContainsString('[falcon_code_block', $shortcode);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($shortcode), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? [];

        $this->assertSame('code_block', $el['type'] ?? null);
        $this->assertSame($code, $el['settings']['code'] ?? null, 'the snippet did not survive the round trip');

        foreach ($layout[0]['columns'][0]['elements'][0]['settings'] as $key => $value) {
            $this->assertSame($value, $el['settings'][$key] ?? '<missing>', "setting {$key} changed");
        }
    }

    /**
     * The body holds the code as written, not a blob.
     *
     * A shortcode is a format people open, diff and edit by hand, and base64 makes all
     * three impossible — the snippet was unreadable in the very place it is stored.
     * Newlines, quotes, brackets and even a nested shortcode all survive a raw body;
     * only this element's own closing tag would truncate it, and that falls back.
     */
    public function test_the_body_holds_the_code_as_written(): void
    {
        $code = "<?php\n\$a = ['x' => \"y\"];\n// even [falcon_row] survives\n";

        $shortcode = BuilderShortcodeConverter::jsonToShortcodes(json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%',
                'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'code_block', 'settings' => ['code' => $code, 'language' => 'php']]],
            ]],
        ]]));

        // Readable means the lines, the indentation and the words are all still there.
        // Only < > & are escaped, because those are what an HTML editor acts on.
        $this->assertStringContainsString("\$a = ['x' =&gt; \"y\"];", $shortcode, 'the snippet is not readable in the shortcode');
        $this->assertStringContainsString('// even [falcon_row] survives', $shortcode);
        $this->assertStringNotContainsString('enc="b64"', $shortcode);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($shortcode), true);
        $this->assertSame($code, $back[0]['columns'][0]['elements'][0]['settings']['code'] ?? null);
    }

    /**
     * A "<" in the body must not reach the classic editor as a "<".
     *
     * The editor is HTML-oriented and reads "<?php" as a tag, then swallows everything
     * up to the next thing it recognises — which took this element's closing tag, the
     * column's and the section's with it. The page did not lose a snippet, it lost
     * everything after one. Escaping the two characters that can do that costs almost
     * nothing in readability and is the whole difference.
     */
    public function test_the_body_never_ships_a_bare_angle_bracket(): void
    {
        $code = "<?php\n\$x = 1 < 2 && 3 > 2;\n// <div class=\"x\">\n";

        $shortcode = $this->wrap(['code' => $code, 'language' => 'php']);

        $this->assertStringNotContainsString('<?php', $shortcode, 'the editor will read this as a tag');
        $this->assertStringNotContainsString('<div', $shortcode);
        $this->assertStringContainsString('&lt;?php', $shortcode);
        $this->assertStringContainsString('[/falcon_code_block]', $shortcode, 'the closing tag must survive');

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($shortcode), true);
        $this->assertSame($code, $back[0]['columns'][0]['elements'][0]['settings']['code'] ?? null);
    }

    /** A shortcode someone typed by hand holds plain code and must still be read. */
    public function test_a_hand_written_body_with_bare_angle_brackets_still_parses(): void
    {
        $sc = '[falcon_section id="r1" type="container"][falcon_col id="c1" width="100%"]'
            .'[falcon_code_block id="e1" language="bash"]echo "a > b"[/falcon_code_block]'
            .'[/falcon_col][/falcon_section]';

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);

        $this->assertSame('echo "a > b"', $back[0]['columns'][0]['elements'][0]['settings']['code'] ?? null);
    }

    /** The one string a raw body cannot hold is the closing tag the parser stops at. */
    public function test_code_holding_the_closing_tag_falls_back_to_base64(): void
    {
        $code = "echo 'before [/falcon_code_block] after';\n";

        $shortcode = BuilderShortcodeConverter::jsonToShortcodes(json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%',
                'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'code_block', 'settings' => ['code' => $code, 'language' => 'php']]],
            ]],
        ]]));

        $this->assertStringContainsString('enc="b64"', $shortcode);

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($shortcode), true);
        $this->assertSame($code, $back[0]['columns'][0]['elements'][0]['settings']['code'] ?? null,
            'the escape hatch lost the snippet it exists for');
    }

    /** A shortcode typed by hand, with plain code in the body, must still work. */
    public function test_a_hand_written_shortcode_with_plain_body_is_accepted(): void
    {
        $sc = '[falcon_section id="r1" type="container"][falcon_col id="c1" width="100%"]'
            .'[falcon_code_block id="e1" language="bash"]composer require falconcms/falconcms[/falcon_code_block]'
            .'[/falcon_col][/falcon_section]';

        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true);
        $el = $back[0]['columns'][0]['elements'][0] ?? [];

        $this->assertSame('code_block', $el['type'] ?? null);
        $this->assertSame('composer require falconcms/falconcms', $el['settings']['code'] ?? null);
        $this->assertSame('bash', $el['settings']['language'] ?? null);
    }

    /**
     * The rendered block interleaves line numbers with the code, so scraping the DOM
     * would copy the numbers too. The button carries the original source instead.
     */
    public function test_the_copy_button_carries_the_original_code_not_the_rendered_lines(): void
    {
        $code = "line one\nline two\n";
        $html = view('falcon-cms::frontend.builder.elements.code-block', ['el' => [
            'id' => 'e1', 'type' => 'code_block',
            'settings' => ['code' => $code, 'language' => 'plain', 'showLineNumbers' => true, 'showCopy' => true],
        ]])->render();

        $this->assertMatchesRegularExpression('/data-code="([^"]+)"/', $html);
        preg_match('/data-code="([^"]+)"/', $html, $m);

        $this->assertSame($code, base64_decode($m[1]),
            'the copy button would put something other than the author\'s code on the clipboard');
        $this->assertStringContainsString('fc-code-no', $html, 'line numbers are not rendered');
    }

    /**
     * Every block must carry the behaviour script, and the script must be safe to
     * repeat.
     *
     * This was originally wrapped in @once, which looked right and was silently wrong:
     * the theme layout renders the builder's content twice per request — once early,
     * to scan it for icon libraries — so the @once was spent on a copy that never
     * reached the page, and the copy that did carried no script. The copy button did
     * nothing, and a block with a typing reveal stayed blank forever because the CSS
     * hides its lines until the script reveals them.
     */
    public function test_every_block_carries_the_behaviour_and_it_is_safe_to_repeat(): void
    {
        $el = fn ($id) => ['id' => $id, 'type' => 'code_block',
            'settings' => ['code' => "a\n", 'language' => 'plain', 'showCopy' => true]];

        $html = Blade::render(
            '@include("falcon-cms::frontend.builder.elements.code-block", ["el" => $a])'
            .'@include("falcon-cms::frontend.builder.elements.code-block", ["el" => $b])',
            ['a' => $el('a'), 'b' => $el('b')]
        );

        $this->assertSame(2, substr_count($html, 'class="fc-code-shell"'), 'both blocks should render');
        $this->assertSame(2, substr_count($html, 'function runTyping'),
            'a block was rendered without the behaviour that reveals and copies it');
        $this->assertStringContainsString('window.__falconCodeBlock', $html,
            'nothing stops the second copy of the script from binding its listeners again');
    }

    /**
     * A block rendered a second time — which the theme layout does on every request —
     * must still ship the script. Guards this against a future @once creeping back.
     */
    public function test_the_behaviour_survives_a_second_render_of_the_same_content(): void
    {
        $el = ['id' => 'e1', 'type' => 'code_block',
            'settings' => ['code' => "a\n", 'language' => 'plain', 'typeMode' => 'typewriter']];

        $first = Blade::render('@include("falcon-cms::frontend.builder.elements.code-block", ["el" => $el])', ['el' => $el]);
        $second = Blade::render('@include("falcon-cms::frontend.builder.elements.code-block", ["el" => $el])', ['el' => $el]);

        foreach (['first' => $first, 'second' => $second] as $which => $html) {
            $this->assertStringContainsString('data-typing="pending"', $html, "{$which} render lost the typing hook");
            $this->assertStringContainsString('function runTyping', $html,
                "the {$which} render carries no script — its lines would stay hidden for good");
        }
    }

    /**
     * Line-by-line has to stay hidden while it runs, not just before it starts.
     *
     * runTyping() marks the block "running" as its first act. When the rule that hides
     * the lines only covered "pending", that one assignment revealed every line at
     * once and the stagger had nothing left to do: the mode looked like no animation
     * at all, while typewriter still worked because it empties text nodes instead of
     * leaning on the stylesheet.
     */
    public function test_lines_stay_hidden_while_the_reveal_is_running(): void
    {
        $html = view('falcon-cms::frontend.builder.elements.code-block', ['el' => [
            'id' => 'e1', 'type' => 'code_block',
            'settings' => ['code' => "a\nb\nc\n", 'language' => 'plain', 'typeMode' => 'lines'],
        ]])->render();

        $this->assertStringContainsString('[data-typing="running"] .fc-code-line', $html,
            'lines are not hidden while the reveal runs, so they all appear at once');
        $this->assertStringContainsString('.fc-code-line.is-shown', $html,
            'there is no per-line opt-in, so nothing can be revealed one at a time');
        $this->assertStringContainsString("classList.add('is-shown')", $html,
            'the script never marks a line as shown');
    }

    /** Reduced motion must override whatever property the reveal hides lines with. */
    public function test_reduced_motion_reveals_everything(): void
    {
        $html = view('falcon-cms::frontend.builder.elements.code-block', ['el' => [
            'id' => 'e1', 'type' => 'code_block',
            'settings' => ['code' => "a\n", 'language' => 'plain', 'typeMode' => 'typewriter'],
        ]])->render();

        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion: reduce\)\s*\{[^}]*\[data-typing\][^{]*\{[^}]*opacity:\s*1\s*!important/s',
            $html,
            'the reduced-motion rule does not clear the property the reveal actually hides with'
        );
    }

    /** A copy that fails must say so; a button that looks dead is worse than an error. */
    public function test_a_failed_copy_still_answers_the_click(): void
    {
        $html = view('falcon-cms::frontend.builder.elements.code-block', ['el' => [
            'id' => 'e1', 'type' => 'code_block',
            'settings' => ['code' => "a\n", 'language' => 'plain', 'showCopy' => true],
        ]])->render();

        $this->assertStringContainsString('.catch(', $html, 'a rejected copy is not handled');
        $this->assertStringContainsString('copyViaTextarea', $html,
            'there is no fallback for the plain-http case, where the Clipboard API is absent');
        $this->assertStringContainsString('setTimeout(function () { finish(false); }, 250)', $html,
            'a clipboard write that never settles would leave the button dead');
    }

    /** One code_block inside a section and column, as a shortcode. */
    private function wrap(array $settings): string
    {
        return BuilderShortcodeConverter::jsonToShortcodes(json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [[
                'id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%',
                'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'code_block', 'settings' => $settings]],
            ]],
        ]]));
    }

    private function nodeBinary(): ?string
    {
        foreach (['node', 'node.exe'] as $bin) {
            $probe = shell_exec(escapeshellarg($bin).' -v 2>&1');
            if (is_string($probe) && preg_match('/^v\d+/', trim($probe))) {
                return $bin;
            }
        }

        return null;
    }

    /** The canvas scanner, reduced to what parity needs. Mirrors CodeHighlighter::tokenize(). */
    private function mirrorScript(): string
    {
        return <<<'JS'
const fs = require('fs');
const langs = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const samples = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
function tokenize(code, lang) {
    const rules = (langs[lang] && langs[lang].rules) || [];
    if (!rules.length) return code === '' ? [] : [[null, code]];
    const compiled = rules.map(([t, p]) => [t, new RegExp(p, 'y')]);
    const out = [];
    let i = 0, plain = '';
    while (i < code.length) {
        let matched = false;
        for (const [token, re] of compiled) {
            re.lastIndex = i;
            const m = re.exec(code);
            if (m && m[0] !== '') {
                if (plain !== '') { out.push([null, plain]); plain = ''; }
                out.push([token, m[0]]);
                i += m[0].length;
                matched = true;
                break;
            }
        }
        if (!matched) { plain += code[i]; i++; }
    }
    if (plain !== '') out.push([null, plain]);
    return out;
}
const result = {};
for (const [lang, code] of Object.entries(samples)) result[lang] = tokenize(code, lang);
process.stdout.write(JSON.stringify(result));
JS;
    }
}
