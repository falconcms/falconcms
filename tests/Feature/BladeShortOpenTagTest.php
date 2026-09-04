<?php

namespace FalconCms\Core\Tests\Feature;

use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Blade templates must not contain the two characters that open a PHP tag.
 *
 * Blade does not compile a template as plain text: it runs token_get_all() over the source
 * and applies its directives only to the T_INLINE_HTML tokens that come back. On a server
 * with short_open_tag=On — which plenty of shared hosts and Docker images ship, including
 * our own demo — PHP's tokenizer treats a bare "<?" as the start of PHP code, so everything
 * from that point down is handed back as PHP tokens and passed through untouched. Blade
 * directives in that region are never compiled; they land in the cached view verbatim, and
 * the visitor gets a fatal parse error rather than a page.
 *
 * The sitemap hit this exactly: its XML declaration was written literally, so on the demo
 * server the whole file came out uncompiled and /sitemap.xml returned a 500 while every
 * other page was fine. Nothing in the request path reveals the cause — the template looks
 * correct, and it renders perfectly wherever short_open_tag is Off.
 *
 * So the rule is enforced on the source instead: assemble such a string from pieces, or
 * emit it from PHP, but never let the bigram appear in a .blade.php file.
 */
class BladeShortOpenTagTest extends TestCase
{
    /** @return list<string> */
    private function bladeFiles(): array
    {
        $root = realpath(__DIR__.'/../../resources/views');
        $files = [];

        /** @var \SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_no_template_opens_a_short_php_tag(): void
    {
        $files = $this->bladeFiles();
        $this->assertNotEmpty($files, 'no blade templates were found to scan');

        $offenders = [];

        foreach ($files as $path) {
            $lines = explode("\n", (string) file_get_contents($path));

            foreach ($lines as $i => $line) {
                // "<?php" and "<?=" are understood by the tokenizer whatever the ini says;
                // anything else beginning "<?" flips it into PHP mode.
                if (preg_match('/<\?(?!php\b|=)/', $line)) {
                    $offenders[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(realpath(__DIR__.'/../../'))))
                        .':'.($i + 1).'  '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['these templates open a short PHP tag and will be served uncompiled where short_open_tag=On:'],
            $offenders,
            ['build the string from pieces instead, e.g. \'<\'.\'?xml ... ?\'.\'>\'']
        )));
    }

    /**
     * Every template must compile under short_open_tag=On as well as Off.
     *
     * The static scan above only rejects a bare "<?", because "<?php" is legitimate in a
     * Blade file — it is how raw PHP blocks are written. But an opening tag inside a
     * JavaScript string is not a PHP block, and Blade cannot tell the difference: it
     * runs token_get_all() over the source, so the tag puts the tokenizer into PHP mode
     * and everything below it is passed through uncompiled. The Code Block element
     * shipped a default snippet beginning with one, and the whole page builder died with
     * "unexpected identifier App" on every server with short_open_tag On — while
     * compiling perfectly here, where it is Off.
     *
     * A scan cannot catch that; only compiling can. Templates with no "<?" in them at
     * all cannot hit it, so they are skipped and the check stays quick.
     */
    public function test_every_template_compiles_with_short_open_tag_on(): void
    {
        $php = PHP_BINARY;
        $checked = 0;
        $broken = [];

        foreach ($this->bladeFiles() as $path) {
            $source = (string) file_get_contents($path);
            if (!str_contains($source, '<?')) {
                continue;
            }

            $checked++;
            $compiled = Blade::compileString($source);

            $tmp = tempnam(sys_get_temp_dir(), 'fcblade').'.php';
            file_put_contents($tmp, $compiled);
            $out = [];
            $status = 0;
            exec(escapeshellarg($php).' -d short_open_tag=1 -l '.escapeshellarg($tmp).' 2>&1', $out, $status);
            @unlink($tmp);

            if ($status !== 0) {
                $broken[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(realpath(__DIR__.'/../../'))))
                    .'  '.trim(implode(' ', $out));
            }
        }

        $this->assertGreaterThan(0, $checked, 'nothing was checked; the scan found no templates');
        $this->assertSame([], $broken, implode("\n", array_merge(
            ['these templates do not compile where short_open_tag is On:'],
            $broken,
            ['an opening tag inside a string must be joined at runtime, e.g. "<" + "?php"']
        )));
    }

    /**
     * An XML declaration is only a declaration when it is the first thing in the document;
     * a stray newline ahead of it — a Blade comment above it is enough — makes the file
     * invalid XML for strict parsers.
     */
    public function test_the_sitemap_declaration_is_the_first_thing_emitted(): void
    {
        $source = (string) file_get_contents(
            __DIR__.'/../../resources/views/frontend/sitemap.blade.php'
        );

        $compiled = Blade::compileString($source);

        $this->assertStringStartsWith('<?php', $compiled,
            'something is emitted before the sitemap XML declaration, so it is no longer at byte 0');

        $this->assertStringContainsString('?xml version="1.0" encoding="UTF-8"?', $compiled,
            'the sitemap no longer emits an XML declaration at all');
    }
}
