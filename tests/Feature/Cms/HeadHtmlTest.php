<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Http\Controllers\Admin\CustomizerController;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use ReflectionMethod;

/**
 * Head HTML: markup that goes into the document head exactly as written.
 *
 * The Customizer already had Head Script and Footer Script, but both take JavaScript only —
 * the theme wraps whatever you type in a <script> tag. That leaves no way to add the things
 * a site actually needs in its head and which are not script: a Search Console or Facebook
 * verification <meta>, a <link>, or a loader that arrives as <script src="...">, which is how
 * Google Tag Manager, Meta Pixel and Lemon Squeezy are all published. Sites had to edit a
 * theme file, which an update then overwrites.
 *
 * The delicate part is what must NOT change. The two script fields keep wrapping their
 * contents, because every site that has already saved JavaScript in them would otherwise
 * start printing that JavaScript on the page as visible text.
 */
class HeadHtmlTest extends TestCase
{
    /** @return array<string, mixed> */
    private function customScriptFields(): array
    {
        $sections = new ReflectionMethod(CustomizerController::class, 'sections');
        $sections->setAccessible(true);

        $all = $sections->invoke(app(CustomizerController::class));

        $this->assertArrayHasKey('custom_scripts', $all, 'the Custom Scripts section is gone');

        return $all['custom_scripts']['fields'];
    }

    private function layoutSource(): string
    {
        return (string) file_get_contents(
            __DIR__.'/../../../resources/views/themes/falcon-theme/layouts/app.blade.php'
        );
    }

    public function test_the_head_html_field_exists(): void
    {
        $fields = $this->customScriptFields();

        $this->assertArrayHasKey('theme_head_html', $fields,
            'there is still no way to put non-script markup in the head');
        $this->assertSame('html', $fields['theme_head_html']['type'],
            'the field is not typed as html, so the editor will highlight it as the wrong language');
    }

    /**
     * The regression this feature could most easily cause: if either script field stopped
     * being wrapped, the JavaScript sites have already saved would render as visible text.
     */
    public function test_the_existing_script_fields_are_untouched(): void
    {
        $fields = $this->customScriptFields();

        foreach (['theme_head_script', 'theme_footer_script'] as $key) {
            $this->assertArrayHasKey($key, $fields, "{$key} was removed");
            $this->assertSame('script', $fields[$key]['type'], "{$key} changed type");
        }

        $layout = $this->layoutSource();

        $this->assertStringContainsString(
            "<script>{!! get_cms_option('theme_head_script') !!}</script>",
            $layout,
            'the head script is no longer wrapped in a script tag — saved JavaScript would print as text'
        );
        $this->assertStringContainsString(
            "<script>{!! get_cms_option('theme_footer_script') !!}</script>",
            $layout,
            'the footer script is no longer wrapped in a script tag — saved JavaScript would print as text'
        );
    }

    public function test_head_html_is_emitted_verbatim_inside_the_head(): void
    {
        $layout = $this->layoutSource();

        $this->assertStringContainsString("{!! get_cms_option('theme_head_html') !!}", $layout,
            'the layout never outputs theme_head_html, so the field would silently do nothing');

        // Verbatim means unwrapped: a <meta> tag inside a <script> tag is not a meta tag.
        $this->assertStringNotContainsString(
            "<script>{!! get_cms_option('theme_head_html') !!}</script>",
            $layout,
            'head HTML is wrapped in a script tag, which defeats the whole point of the field'
        );

        $headEnd = strpos($layout, '</head>');
        $output = strpos($layout, "{!! get_cms_option('theme_head_html') !!}");

        $this->assertNotFalse($headEnd);
        $this->assertNotFalse($output);
        $this->assertLessThan($headEnd, $output,
            'head HTML is emitted after </head>, so verification meta tags would be ignored');
    }

    /** An unset option must add nothing at all, not an empty line in every page. */
    public function test_nothing_is_emitted_when_the_field_is_empty(): void
    {
        $this->assertStringContainsString(
            "@if(get_cms_option('theme_head_html'))",
            $this->layoutSource(),
            'the output is not guarded, so every page carries a blank line for an unused field'
        );
    }

    /** The Customizer renders css/script fields in Monaco; html has to reach it too. */
    public function test_the_editor_renders_the_html_field(): void
    {
        $view = (string) file_get_contents(
            __DIR__.'/../../../resources/views/admin/customizer/index.blade.php'
        );

        $this->assertStringContainsString("\$type === 'html'", $view,
            'the customizer has no branch for html fields, so the field renders as nothing');
        $this->assertStringContainsString("'html' => 'html'", $view,
            'the editor is not told the field is html, so it highlights it as JavaScript');
    }

    /**
     * Both edited templates must still compile. A Blade file is only parsed when something
     * renders it, so a broken directive in the Customizer would not surface until an admin
     * opened the page — and a broken layout would take down every page on the site.
     */
    public function test_the_edited_templates_still_compile(): void
    {
        $templates = [
            'themes/falcon-theme/layouts/app.blade.php',
            'admin/customizer/index.blade.php',
        ];

        foreach ($templates as $relative) {
            $path = __DIR__.'/../../../resources/views/'.$relative;
            $compiled = Blade::compileString((string) file_get_contents($path));

            $tmp = tempnam(sys_get_temp_dir(), 'blade').'.php';
            file_put_contents($tmp, $compiled);
            exec('php -l '.escapeshellarg($tmp).' 2>&1', $out, $status);
            @unlink($tmp);

            $this->assertSame(0, $status, $relative.' does not compile: '.implode(' ', $out));
        }
    }
}
