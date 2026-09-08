<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Tests\TestCase;

/**
 * The builder's element partials are Vue templates, and a JS comment inside one is fatal.
 *
 * These files look like JavaScript — most of what they contain is a `:style="[{ ... }]"`
 * binding — so writing `/* ... *\/` next to a property reads as the natural thing to do. It
 * is not: the binding is an HTML attribute value, and everything in it lives between two
 * quotes. A double quote anywhere in that comment ends the attribute early, Vue then fails
 * to compile the whole template, and the builder renders as a blank white page — not a
 * broken button, the entire editor. It shipped that way once.
 *
 * Nothing in the PHP test suite runs Vue, so this is a static rule instead: explanations in
 * these files go in Blade comments, which are stripped before the browser ever sees them.
 */
class BuilderTemplateSyntaxTest extends TestCase
{
    /** @return list<string> */
    private function componentTemplates(): array
    {
        $dir = __DIR__.'/../../../resources/views/admin/falcon-builder/partials/components';

        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function test_the_component_partials_carry_no_javascript_block_comments(): void
    {
        $this->assertNotEmpty($this->componentTemplates(), 'the component templates must be found');

        foreach ($this->componentTemplates() as $path) {
            $source = (string) file_get_contents($path);

            // Blade comments never reach the browser, so one may describe this very rule.
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? '';

            $this->assertStringNotContainsString(
                '/*',
                $source,
                basename($path).' contains a /* ... */ comment. These files are Vue templates: '
                .'a comment inside a binding sits inside an HTML attribute value, and one double '
                .'quote in it breaks the whole template and blanks the builder. Use {{-- --}}.'
            );
        }
    }
}
