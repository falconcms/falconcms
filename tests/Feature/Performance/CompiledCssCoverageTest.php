<?php

namespace FalconCms\Core\Tests\Feature\Performance;

use FalconCms\Core\Support\CompiledCssCoverage;
use FalconCms\Core\Support\TailwindLite;
use FalconCms\Core\Tests\TestCase;

/**
 * Compiled CSS only knows the classes in the theme's templates. Classes that arrive with the
 * content are found on the finished page: the common ones are written on the server
 * (TailwindLite), and only what is left that the runtime build would make loads that build.
 */
class CompiledCssCoverageTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $this->css = sys_get_temp_dir().'/falcon-compiled-'.uniqid().'.css';
        file_put_contents($this->css, '.flex{display:flex}.mt-4{margin-top:1rem}.md\\:flex{}.text-primary{}.hover\\:text-primary:hover{}');
    }

    protected function tearDown(): void
    {
        @unlink($this->css);
        parent::tearDown();
    }

    private function page(string $body, string $head = ''): string
    {
        return "<html><head>$head</head><body>$body<script>el.innerHTML = '<p class=\"text-[99px]\"></p>'</script></body></html>";
    }

    public function test_only_tailwind_classes_the_compiled_file_lacks_are_reported(): void
    {
        $html = $this->page('<div class="flex mt-4 md:flex text-primary md:text-3xl falcon-rich-text text-block-container-1785438522109 own"></div>',
            '<style>.own{color:red}</style>');

        $this->assertSame(['md:text-3xl', 'text-block-container-1785438522109'], CompiledCssCoverage::missing($html, $this->css),
            'known classes, non-Tailwind names, the page\'s own styled classes and markup inside scripts are left out');
    }

    public function test_common_utilities_are_written_on_the_server_with_their_variants(): void
    {
        $made = TailwindLite::generate(['hover:text-[#0091ea]', 'md:text-3xl', 'mt-5', '-mx-2', 'w-1/2', 'lg:p-[12px]', '!text-center', 'dark:text-white']);

        $this->assertStringContainsString('.hover\\:text-\\[\\#0091ea\\]:hover{color:#0091ea}', $made['css']);
        $this->assertStringContainsString('@media (min-width:768px){.md\\:text-3xl{font-size:1.875rem;line-height:2.25rem}}', $made['css']);
        $this->assertStringContainsString('.mt-5{margin-top:1.25rem}', $made['css']);
        $this->assertStringContainsString('.-mx-2{margin-left:-0.5rem;margin-right:-0.5rem}', $made['css']);
        $this->assertStringContainsString('.w-1\\/2{width:50%}', $made['css']);
        $this->assertStringContainsString('@media (min-width:1024px){.lg\\:p-\\[12px\\]{padding:12px}}', $made['css']);
        $this->assertStringContainsString('.\\!text-center{text-align:center !important}', $made['css']);
        $this->assertSame(['dark:text-white'], $made['leftover']);
        // Base rules come before states, and screens last (smallest first), as in Tailwind.
        $this->assertLessThan(strpos($made['css'], '@media (min-width:768px)'), strpos($made['css'], '.mt-5{'));
        $this->assertLessThan(strpos($made['css'], '@media (min-width:1024px)'), strpos($made['css'], '@media (min-width:768px)'));
    }

    public function test_the_runtime_build_is_needed_only_for_what_it_would_make(): void
    {
        $this->assertTrue(CompiledCssCoverage::runtimeCanMake('dark:text-red-500'));
        $this->assertTrue(CompiledCssCoverage::runtimeCanMake('group-hover:bg-[#fff]'));
        $this->assertFalse(CompiledCssCoverage::runtimeCanMake('text-muted-foreground'), 'a colour the config never defined');
        $this->assertFalse(CompiledCssCoverage::runtimeCanMake('font-display'));
        $this->assertFalse(CompiledCssCoverage::runtimeCanMake('text-block-container-1785438522109'), 'a generated id');

        $plan = CompiledCssCoverage::plan($this->page('<h2 class="md:text-3xl text-muted-foreground">x</h2>'), $this->css);
        $this->assertFalse($plan['runtime']);
        $this->assertStringContainsString('.md\\:text-3xl', $plan['css']);

        $plan = CompiledCssCoverage::plan($this->page('<p class="dark:bg-slate-900">x</p>'), $this->css);
        $this->assertTrue($plan['runtime']);
    }
}
