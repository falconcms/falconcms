<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The builder's JavaScript must parse.
 *
 * Everything the page builder does lives in one enormous inline script, and a syntax
 * error anywhere in it takes the whole builder down — not the element that caused it,
 * the whole thing, with a blank canvas and a message in the console no author will
 * ever look at. Nothing else in this suite can see that: PHPUnit checks PHP, and this
 * file is PHP only until Blade has finished with it.
 *
 * It has happened twice. A `watch()` written above the `const` it watched threw a
 * temporal-dead-zone error and killed the builder on load; a PHP opening tag inside a
 * JavaScript string stopped Blade compiling the file at all. Both shipped, because
 * both look completely fine in a diff.
 *
 * So the script is lifted out, the Blade constructs are reduced to the values they
 * stand for, and node is asked to parse the result. This does not run the code — it
 * cannot, without a browser — but a script that will not parse can never run either,
 * and that is the failure worth catching before a release rather than after one.
 */
class BuilderScriptTest extends TestCase
{
    public function test_the_builder_script_parses(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not on PATH; cannot parse the builder script');
        }

        $source = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php'
        );

        // Inline scripts only. A <script src="…"> has no body to check.
        $this->assertSame(1, preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/', $source, $m),
            'the builder script is no longer one inline block; the extraction below needs updating');

        $js = $this->toJavaScript($m[1][0]);

        $tmp = tempnam(sys_get_temp_dir(), 'fcjs').'.js';
        file_put_contents($tmp, $js);
        $out = [];
        $status = 0;
        exec(escapeshellarg($node).' --check '.escapeshellarg($tmp).' 2>&1', $out, $status);
        @unlink($tmp);

        $this->assertSame(0, $status, "the builder's JavaScript does not parse:\n".implode("\n", $out));
    }

    /**
     * Reduce a Blade-flavoured script to something a JavaScript parser can read.
     *
     * Each construct is replaced by the shape of what it produces, not by nothing. A
     * json directive is a value in the middle of an expression, so it becomes a
     * literal; a printed value is a value too, and often already sits inside quotes, so
     * it becomes a scalar that reads correctly either way.
     *
     * Only the first branch of a conditional is kept. Keeping both would declare the
     * same const twice and fail for a reason that has nothing to do with the code.
     */
    private function toJavaScript(string $js): string
    {
        // @json(…) and @js(…), matched with balanced parentheses — the argument is a
        // PHP call of its own and a naive match stops at its first ")".
        $out = '';
        $i = 0;
        while (preg_match('/@(?:json|js)\(/', $js, $m, PREG_OFFSET_CAPTURE, $i)) {
            $out .= substr($js, $i, $m[0][1] - $i).'null';

            $depth = 1;
            $k = $m[0][1] + strlen($m[0][0]);
            while ($k < strlen($js) && $depth > 0) {
                if ($js[$k] === '(') {
                    $depth++;
                } elseif ($js[$k] === ')') {
                    $depth--;
                }
                $k++;
            }
            $i = $k;
        }
        $out .= substr($js, $i);
        $js = $out;

        $js = preg_replace('/\{\{--[\s\S]*?--\}\}/', '', $js) ?? $js;
        // A printed value. 0 parses both bare and inside the quotes it is often written
        // between, which "" does not — 'a{{ $x }}b' would become 'a""b'.
        $js = preg_replace('/\{!![\s\S]*?!!\}/', '0', $js) ?? $js;
        $js = preg_replace('/\{\{[\s\S]*?\}\}/', '0', $js) ?? $js;

        // Control directives, which in this file always occupy their own line. Only the
        // first branch of a conditional survives.
        $lines = explode("\n", $js);
        $kept = [];
        $skipDepth = 0;
        $stack = [];

        foreach ($lines as $line) {
            $directive = null;
            if (preg_match('/^[ \t]*@(\w+)/', $line, $d)) {
                $directive = $d[1];
            }

            if ($directive === 'if' || $directive === 'isset' || $directive === 'empty' || $directive === 'unless') {
                $stack[] = 'taken';

                continue;
            }

            if ($directive === 'else' || $directive === 'elseif') {
                if ($stack !== []) {
                    $stack[count($stack) - 1] = 'skipping';
                    $skipDepth++;
                }

                continue;
            }

            if ($directive === 'endif' || $directive === 'endisset' || $directive === 'endempty' || $directive === 'endunless') {
                if ($stack !== [] && array_pop($stack) === 'skipping') {
                    $skipDepth--;
                }

                continue;
            }

            // Loops, raw PHP and the rest carry no branching; the body is kept once.
            if ($directive !== null && in_array($directive, [
                'foreach', 'endforeach', 'forelse', 'endforelse', 'for', 'endfor',
                'php', 'endphp', 'once', 'endonce', 'while', 'endwhile',
            ], true)) {
                continue;
            }

            if ($skipDepth === 0) {
                $kept[] = $line;
            }
        }

        return implode("\n", $kept);
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
}
