<?php

namespace FalconCms\Core\Support;

/**
 * Syntax highlighting for the Code Block element — the single source of truth for
 * both renderers.
 *
 * The builder has two independent renderers that must agree: the Vue canvas in the
 * admin and the Blade template on the front end. Anything duplicated between them
 * drifts, so the language rules and the colour themes live here once, in PHP. The
 * front end calls {@see highlight()} directly; the canvas receives the very same
 * arrays through @json() and runs them through a scanner that mirrors this one.
 *
 * That only works if every pattern below means the same thing to PCRE and to
 * JavaScript's RegExp, so the rules stay inside the portable subset both engines
 * share: no lookbehind, no possessive quantifiers, no atomic groups, no named
 * groups, no \Z. Lookahead is fine — both support it. CodeHighlighterTest checks
 * this by running the same code through PHP and through node and comparing the
 * token streams, so a pattern that quietly means something different in one engine
 * fails the suite rather than the page.
 *
 * Scanning is deliberately simple: at each offset try every rule in order, first
 * match wins, and a position that matches nothing emits a single plain character.
 * Rule order is therefore meaningful — comments and strings come before the rules
 * that would otherwise chew through their contents.
 */
class CodeHighlighter
{
    /** Token names a theme can colour. Anything else renders in the theme's plain text colour. */
    public const TOKENS = [
        'comment', 'string', 'keyword', 'number', 'function',
        'variable', 'tag', 'attr', 'builtin', 'operator', 'punctuation',
    ];

    /**
     * Language rules, ordered. Each rule is [token, pattern] with the pattern written
     * without delimiters or flags so both engines can compile it.
     *
     * @return array<string, array{name: string, rules: array<int, array{0: string, 1: string}>}>
     */
    public static function languages(): array
    {
        $dq = '"(?:\\\\.|[^"\\\\])*"';
        $sq = "'(?:\\\\.|[^'\\\\])*'";
        $bt = '`(?:\\\\.|[^`\\\\])*`';
        $num = '\\b\\d[\\d_]*(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b';
        // A name immediately followed by "(" reads as a call. Lookahead, not lookbehind.
        $call = '\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()';

        return [
            'plain' => ['name' => 'Plain text', 'rules' => []],

            'php' => ['name' => 'PHP', 'rules' => [
                ['tag', '<\\?php|<\\?=|\\?>'],
                ['comment', '/\\*[\\s\\S]*?\\*/'],
                ['comment', '//[^\\n]*'],
                ['comment', '#[^\\n]*'],
                ['string', $dq],
                ['string', $sq],
                ['variable', '\\$[A-Za-z_][A-Za-z0-9_]*'],
                ['keyword', '\\b(?:abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enum|extends|final|finally|fn|for|foreach|function|global|if|implements|include_once|include|instanceof|insteadof|interface|isset|list|match|namespace|new|or|print|private|protected|public|readonly|require_once|require|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield|true|false|null|self|parent|this)\\b'],
                ['builtin', '\\b(?:int|float|string|bool|void|iterable|object|mixed|never)\\b'],
                ['number', $num],
                ['function', $call],
                ['operator', '(?:=>|->|::|\\?\\?|\\.\\.\\.|[+\\-*/%=<>!&|^~?:.])+'],
                ['punctuation', '[{}()\\[\\];,]'],
            ]],

            'javascript' => ['name' => 'JavaScript', 'rules' => [
                ['comment', '/\\*[\\s\\S]*?\\*/'],
                ['comment', '//[^\\n]*'],
                ['string', $dq],
                ['string', $sq],
                ['string', $bt],
                ['keyword', '\\b(?:async|await|break|case|catch|class|const|continue|debugger|default|delete|do|else|export|extends|finally|for|from|function|get|if|import|in|instanceof|let|new|of|return|set|static|super|switch|this|throw|try|typeof|var|void|while|with|yield|true|false|null|undefined)\\b'],
                ['builtin', '\\b(?:Array|Boolean|Date|Error|JSON|Map|Math|Number|Object|Promise|RegExp|Set|String|Symbol|console|document|window)\\b'],
                ['number', $num],
                ['function', $call],
                ['operator', '(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\.\\.\\.|[+\\-*/%=<>!&|^~?:.])+'],
                ['punctuation', '[{}()\\[\\];,]'],
            ]],

            'typescript' => ['name' => 'TypeScript', 'rules' => [
                ['comment', '/\\*[\\s\\S]*?\\*/'],
                ['comment', '//[^\\n]*'],
                ['string', $dq],
                ['string', $sq],
                ['string', $bt],
                ['keyword', '\\b(?:abstract|any|as|async|await|break|case|catch|class|const|continue|declare|default|delete|do|else|enum|export|extends|finally|for|from|function|implements|import|in|instanceof|interface|keyof|let|namespace|new|of|private|protected|public|readonly|return|satisfies|static|super|switch|this|throw|try|type|typeof|var|void|while|yield|true|false|null|undefined)\\b'],
                ['builtin', '\\b(?:boolean|number|string|unknown|never|object|symbol|bigint|Array|Promise|Record|Partial)\\b'],
                ['number', $num],
                ['function', $call],
                ['operator', '(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\.\\.\\.|[+\\-*/%=<>!&|^~?:.])+'],
                ['punctuation', '[{}()\\[\\];,]'],
            ]],

            'html' => ['name' => 'HTML', 'rules' => [
                ['comment', '<!--[\\s\\S]*?-->'],
                ['tag', '</?[A-Za-z][A-Za-z0-9-]*'],
                ['attr', '[A-Za-z_:@][A-Za-z0-9_:.@-]*(?=\\s*=)'],
                ['string', $dq],
                ['string', $sq],
                ['punctuation', '/?>|[=]'],
            ]],

            'blade' => ['name' => 'Blade', 'rules' => [
                ['comment', '\\{\\{--[\\s\\S]*?--\\}\\}'],
                ['comment', '<!--[\\s\\S]*?-->'],
                ['keyword', '@[A-Za-z]+'],
                ['variable', '\\{\\{!?!?|!!\\}\\}|\\}\\}'],
                ['tag', '</?[A-Za-z][A-Za-z0-9-]*'],
                ['attr', '[A-Za-z_:@][A-Za-z0-9_:.@-]*(?=\\s*=)'],
                ['string', $dq],
                ['string', $sq],
                ['variable', '\\$[A-Za-z_][A-Za-z0-9_]*'],
                ['punctuation', '/?>|[=]'],
            ]],

            'css' => ['name' => 'CSS', 'rules' => [
                ['comment', '/\\*[\\s\\S]*?\\*/'],
                ['keyword', '@[A-Za-z-]+'],
                ['string', $dq],
                ['string', $sq],
                ['builtin', '#[0-9a-fA-F]{3,8}\\b'],
                ['attr', '[-A-Za-z]+(?=\\s*:)'],
                ['function', $call],
                ['number', '\\b\\d[\\d.]*(?:px|em|rem|%|vh|vw|s|ms|deg|fr|ch)?\\b'],
                ['variable', '--[A-Za-z0-9-]+'],
                ['punctuation', '[{}();,:]'],
            ]],

            'json' => ['name' => 'JSON', 'rules' => [
                ['attr', $dq.'(?=\\s*:)'],
                ['string', $dq],
                ['keyword', '\\b(?:true|false|null)\\b'],
                ['number', '-?'.$num],
                ['punctuation', '[{}\\[\\],:]'],
            ]],

            'sql' => ['name' => 'SQL', 'rules' => [
                ['comment', '--[^\\n]*'],
                ['comment', '/\\*[\\s\\S]*?\\*/'],
                ['string', $sq],
                ['string', '`[^`]*`'],
                ['keyword', '\\b(?:ALTER|AND|AS|ASC|BETWEEN|BY|CASE|COLUMN|CREATE|DATABASE|DEFAULT|DELETE|DESC|DISTINCT|DROP|ELSE|END|EXISTS|FROM|FULL|GROUP|HAVING|IN|INDEX|INNER|INSERT|INTO|IS|JOIN|LEFT|LIKE|LIMIT|NOT|NULL|OFFSET|ON|OR|ORDER|OUTER|PRIMARY|RIGHT|SELECT|SET|TABLE|THEN|UNION|UNIQUE|UPDATE|VALUES|WHEN|WHERE|WITH)\\b'],
                ['keyword', '\\b(?:alter|and|as|asc|between|by|case|column|create|database|default|delete|desc|distinct|drop|else|end|exists|from|full|group|having|in|index|inner|insert|into|is|join|left|like|limit|not|null|offset|on|or|order|outer|primary|right|select|set|table|then|union|unique|update|values|when|where|with)\\b'],
                ['function', $call],
                ['number', $num],
                ['operator', '[=<>!+\\-*/%]+'],
                ['punctuation', '[(),;.]'],
            ]],

            'bash' => ['name' => 'Bash', 'rules' => [
                ['comment', '#[^\\n]*'],
                ['string', $dq],
                ['string', $sq],
                ['variable', '\\$\\{[A-Za-z_][A-Za-z0-9_]*\\}|\\$[A-Za-z_][A-Za-z0-9_]*|\\$[0-9@?#*]'],
                ['keyword', '\\b(?:if|then|else|elif|fi|for|while|until|do|done|case|esac|function|return|in|export|local|source|exit|break|continue)\\b'],
                ['builtin', '\\b(?:cd|echo|cat|grep|sed|awk|curl|wget|chmod|chown|mkdir|rm|cp|mv|ls|composer|php|npm|node|git|docker|artisan|sudo|apt|systemctl)\\b'],
                ['operator', '(?:&&|\\|\\||>>|<<|[|&<>=!])+'],
                ['number', $num],
                ['punctuation', '[{}()\\[\\];,]'],
            ]],

            'python' => ['name' => 'Python', 'rules' => [
                ['comment', '#[^\\n]*'],
                ['string', '"""[\\s\\S]*?"""'],
                ['string', "'''[\\s\\S]*?'''"],
                ['string', $dq],
                ['string', $sq],
                ['keyword', '\\b(?:and|as|assert|async|await|break|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|nonlocal|not|or|pass|raise|return|try|while|with|yield|True|False|None|self)\\b'],
                ['builtin', '\\b(?:print|len|range|dict|list|set|tuple|int|float|str|bool|open|enumerate|zip|map|filter|sorted|sum)\\b'],
                ['number', $num],
                ['function', $call],
                ['operator', '(?:==|!=|<=|>=|\\*\\*|//|[+\\-*/%=<>!&|^~])+'],
                ['punctuation', '[{}()\\[\\];,:.]'],
            ]],

            'yaml' => ['name' => 'YAML', 'rules' => [
                ['comment', '#[^\\n]*'],
                ['attr', '^[ \\t]*[-A-Za-z0-9_.]+(?=\\s*:)'],
                ['string', $dq],
                ['string', $sq],
                ['keyword', '\\b(?:true|false|null|yes|no|on|off)\\b'],
                ['number', $num],
                ['punctuation', '[:\\-\\[\\]{},]'],
            ]],

            'xml' => ['name' => 'XML', 'rules' => [
                ['comment', '<!--[\\s\\S]*?-->'],
                ['tag', '</?[A-Za-z_:][A-Za-z0-9_:.-]*'],
                ['attr', '[A-Za-z_:][A-Za-z0-9_:.-]*(?=\\s*=)'],
                ['string', $dq],
                ['string', $sq],
                ['punctuation', '/?>|[=?]'],
            ]],
        ];
    }

    /**
     * Colour themes. `chrome` paints the optional window bar above the code.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function themes(): array
    {
        return [
            'falcon-dark' => [
                'name' => 'Falcon Dark',
                'bg' => '#11161D', 'fg' => '#D7DEE8', 'border' => '#222B36',
                'chrome' => '#161C25', 'chromeText' => '#8A96A6', 'lineNo' => '#4A5666',
                'mark' => 'rgba(232,145,43,.14)',
                'tokens' => [
                    'comment' => '#5B6875', 'string' => '#9ECE6A', 'keyword' => '#E8912B',
                    'number' => '#E5C07B', 'function' => '#7AA2F7', 'variable' => '#F7768E',
                    'tag' => '#F7768E', 'attr' => '#7AA2F7', 'builtin' => '#2AC3DE',
                    'operator' => '#89DDFF', 'punctuation' => '#8A96A6',
                ],
            ],
            'falcon-light' => [
                'name' => 'Falcon Light',
                'bg' => '#F7F9FB', 'fg' => '#171C23', 'border' => '#DEE3E9',
                'chrome' => '#EDF1F5', 'chromeText' => '#5A6675', 'lineNo' => '#A9B4C0',
                'mark' => 'rgba(185,114,15,.10)',
                'tokens' => [
                    'comment' => '#8A94A0', 'string' => '#3E7D4F', 'keyword' => '#B9720F',
                    'number' => '#9A6700', 'function' => '#2A5DB0', 'variable' => '#B0392B',
                    'tag' => '#B0392B', 'attr' => '#2A5DB0', 'builtin' => '#0B7285',
                    'operator' => '#3C5064', 'punctuation' => '#79838F',
                ],
            ],
            'midnight' => [
                'name' => 'Midnight',
                'bg' => '#0B1020', 'fg' => '#C8D3F5', 'border' => '#1B2440',
                'chrome' => '#111731', 'chromeText' => '#7A86B6', 'lineNo' => '#3B4468',
                'mark' => 'rgba(122,162,247,.16)',
                'tokens' => [
                    'comment' => '#4E5A87', 'string' => '#C3E88D', 'keyword' => '#C792EA',
                    'number' => '#F78C6C', 'function' => '#82AAFF', 'variable' => '#F07178',
                    'tag' => '#F07178', 'attr' => '#FFCB6B', 'builtin' => '#89DDFF',
                    'operator' => '#89DDFF', 'punctuation' => '#7A86B6',
                ],
            ],
            'paper' => [
                'name' => 'Paper',
                'bg' => '#FFFFFF', 'fg' => '#24292F', 'border' => '#D8DEE4',
                'chrome' => '#F6F8FA', 'chromeText' => '#57606A', 'lineNo' => '#B1B9C1',
                'mark' => 'rgba(9,105,218,.08)',
                'tokens' => [
                    'comment' => '#6E7781', 'string' => '#0A3069', 'keyword' => '#CF222E',
                    'number' => '#0550AE', 'function' => '#8250DF', 'variable' => '#953800',
                    'tag' => '#116329', 'attr' => '#0550AE', 'builtin' => '#0550AE',
                    'operator' => '#CF222E', 'punctuation' => '#57606A',
                ],
            ],
            'mono' => [
                'name' => 'Mono',
                'bg' => '#1A1A1A', 'fg' => '#E6E6E6', 'border' => '#2C2C2C',
                'chrome' => '#222222', 'chromeText' => '#9A9A9A', 'lineNo' => '#5A5A5A',
                'mark' => 'rgba(255,255,255,.07)',
                'tokens' => [
                    'comment' => '#6E6E6E', 'string' => '#BDBDBD', 'keyword' => '#FFFFFF',
                    'number' => '#D6D6D6', 'function' => '#D6D6D6', 'variable' => '#BDBDBD',
                    'tag' => '#FFFFFF', 'attr' => '#BDBDBD', 'builtin' => '#D6D6D6',
                    'operator' => '#9A9A9A', 'punctuation' => '#7A7A7A',
                ],
            ],
        ];
    }

    /** Dropdown options, A-Z, with Plain text pinned first because it is the escape hatch. */
    public static function languageOptions(): array
    {
        $langs = self::languages();
        $plain = ['plain' => $langs['plain']['name']];
        unset($langs['plain']);
        $rest = array_map(static fn ($l) => $l['name'], $langs);
        asort($rest);

        return $plain + $rest;
    }

    /** @return array<string, string> */
    public static function themeOptions(): array
    {
        return array_map(static fn ($t) => $t['name'], self::themes());
    }

    /**
     * Turn "3, 7-9" into [3, 7, 8, 9]. Anything unparseable is skipped rather than
     * throwing: this comes from a text field an editor typed into.
     *
     * @return array<int, int>
     */
    public static function parseLineSpec(?string $spec): array
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
     * Scan $code into a flat list of [token|null, text] pairs.
     *
     * Everything else is built on this: {@see highlight()} joins the pairs, and
     * {@see highlightLines()} distributes them across lines. Tokens never nest, so a
     * flat list is enough and a token that spans newlines can be split cleanly.
     *
     * @return array<int, array{0: string|null, 1: string}>
     */
    public static function tokenize(string $code, string $lang): array
    {
        $rules = self::languages()[$lang]['rules'] ?? [];

        if ($rules === []) {
            return $code === '' ? [] : [[null, $code]];
        }

        $out = [];
        $len = strlen($code);
        $i = 0;
        $plain = '';

        while ($i < $len) {
            $matched = false;

            foreach ($rules as [$token, $pattern]) {
                // The A modifier anchors the match at $i — the same semantics as
                // JavaScript's sticky flag, which is what the canvas uses on these
                // very patterns.
                if (preg_match(self::compile($pattern), $code, $m, 0, $i) === 1 && $m[0] !== '') {
                    if ($plain !== '') {
                        $out[] = [null, $plain];
                        $plain = '';
                    }
                    $out[] = [$token, $m[0]];
                    $i += strlen($m[0]);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $plain .= $code[$i];
                $i++;
            }
        }

        if ($plain !== '') {
            $out[] = [null, $plain];
        }

        return $out;
    }

    /**
     * Highlight $code as $lang, returning HTML-escaped markup with token spans.
     *
     * Unknown languages fall through to escaped plain text rather than erroring —
     * a code block whose language was renamed should still show its code.
     */
    public static function highlight(string $code, string $lang): string
    {
        $out = '';
        foreach (self::tokenize($code, $lang) as [$token, $text]) {
            $out .= $token === null
                ? self::esc($text)
                : '<span class="fc-t-'.$token.'">'.self::esc($text).'</span>';
        }

        return $out;
    }

    /**
     * Highlight, then split into one HTML string per line, so the template can number
     * lines, mark some of them, and reveal them one at a time.
     *
     * The split happens on the token list rather than on the finished HTML: a block
     * comment or a heredoc runs across newlines, and cutting the rendered markup
     * would leave a <span> open at the end of one line and orphaned at the start of
     * the next. Splitting the token's text instead re-opens the span on each line, so
     * every line is valid markup on its own.
     *
     * @return array<int, string>
     */
    public static function highlightLines(string $code, string $lang): array
    {
        $lines = [''];

        foreach (self::tokenize($code, $lang) as [$token, $text]) {
            $pieces = preg_split("/\r\n|\r|\n/", $text);
            foreach ($pieces as $k => $piece) {
                if ($k > 0) {
                    $lines[] = '';
                }
                if ($piece === '') {
                    continue;
                }
                $lines[count($lines) - 1] .= $token === null
                    ? self::esc($piece)
                    : '<span class="fc-t-'.$token.'">'.self::esc($piece).'</span>';
            }
        }

        return $lines;
    }

    /**
     * Wrap a shared pattern for PCRE.
     *
     * The rules are written without delimiters because the canvas compiles the same
     * strings as JavaScript RegExps, which have none. Several of them contain a
     * literal "/" (a line comment, a block comment, a closing tag), so the slash is
     * escaped here rather than in the rule — escaping it in the rule would reach the
     * canvas as a stray backslash. CodeHighlighterTest asserts no rule ships a
     * pre-escaped slash, which would double up here.
     */
    private static function compile(string $pattern): string
    {
        return '/'.str_replace('/', '\\/', $pattern).'/A';
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
