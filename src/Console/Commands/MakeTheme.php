<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scaffold a new theme in the app's resources/views/themes directory — a working
 * starter a developer can activate immediately and build on. With --child it
 * scaffolds a child theme that inherits a parent's templates.
 */
class MakeTheme extends Command
{
    protected $signature = 'make:theme
        {name : Theme display name}
        {--child= : Create a child theme of this parent slug (e.g. falcon-theme)}
        {--shop : Also scaffold the e-commerce templates (product, cart, checkout, account)}';

    protected $description = 'Scaffold a new theme (standalone, or a child of another theme)';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $slug = Str::slug($name);
        if ($slug === '') {
            $this->error('Invalid theme name.');
            return self::FAILURE;
        }

        $dir = resource_path('views/themes/' . $slug);
        if (File::isDirectory($dir)) {
            $this->error("Theme '{$slug}' already exists at {$dir}.");
            return self::FAILURE;
        }

        $parent = $this->option('child');

        File::ensureDirectoryExists($dir);

        return $parent
            ? $this->scaffoldChild($dir, $slug, $name, Str::slug($parent))
            : $this->scaffoldStandalone($dir, $slug, $name);
    }

    /** A full, self-contained starter theme. */
    protected function scaffoldStandalone(string $dir, string $slug, string $name): int
    {
        File::ensureDirectoryExists($dir . '/layouts');

        $this->putJson($dir . '/theme.json', [
            'name'        => $name,
            'version'     => '1.0.0',
            'description' => 'A FalconCMS theme.',
            'author'      => '',
            'screenshot'  => 'screenshot.png',
        ]);

        File::put($dir . '/functions.php', $this->functionsStub($name));
        File::put($dir . '/options.php', $this->optionsStub());
        File::put($dir . '/layouts/app.blade.php', $this->render($this->layoutStub(), $slug, $name));
        File::put($dir . '/index.blade.php', $this->render($this->indexStub(), $slug, $name));
        File::put($dir . '/page.blade.php', $this->render($this->pageStub(), $slug, $name));
        File::put($dir . '/single.blade.php', $this->render($this->singleStub(), $slug, $name));
        File::put($dir . '/404.blade.php', $this->render($this->notFoundStub(), $slug, $name));

        if ($this->option('shop')) {
            $count = $this->copyShopTemplates($dir, $slug);
            $this->line($count > 0
                ? "Added {$count} e-commerce templates (wrapped in this theme's layout)."
                : 'Could not locate the base e-commerce templates to copy.');
        }

        $this->info("Theme scaffolded at: {$dir}");
        $this->line('Add a screenshot.png (1200×900) so it looks good on the Themes screen.');
        if (! $this->option('shop')) {
            $this->line('Pass --shop to also scaffold the store templates.');
        }
        $this->line("Activate it under Appearance → Themes, or set active_theme to '{$slug}'.");

        return self::SUCCESS;
    }

    /**
     * Copy the e-commerce templates from the base theme into the new theme,
     * re-pointing their layout to this theme's own. Their partial includes stay
     * absolute (they resolve to the base theme), so the store works immediately
     * and the developer can restyle the page templates they now own.
     */
    protected function copyShopTemplates(string $dir, string $slug): int
    {
        $source = $this->baseThemePath();
        if (! $source) {
            return 0;
        }

        // Top-level product templates + the whole ecommerce/ folder.
        $files = ['archive-product.blade.php', 'single-product.blade.php', 'single-product-variable.blade.php'];
        foreach (File::glob($source . '/ecommerce/*.blade.php') as $eco) {
            $files[] = 'ecommerce/' . basename($eco);
        }

        $copied = 0;
        foreach ($files as $relative) {
            $from = $source . '/' . $relative;
            if (! File::isFile($from)) {
                continue;
            }
            $contents = str_replace(
                "@extends('falcon-cms::themes.falcon-theme.layouts.app')",
                "@extends('themes.{$slug}.layouts.app')",
                File::get($from)
            );
            File::ensureDirectoryExists(dirname($dir . '/' . $relative));
            File::put($dir . '/' . $relative, $contents);
            $copied++;
        }

        return $copied;
    }

    /** Locate the base (falcon-theme) source — app copy first, then the package. */
    protected function baseThemePath(): ?string
    {
        $app = resource_path('views/themes/falcon-theme');
        if (File::isDirectory($app)) {
            return $app;
        }
        $providerDir = dirname((new \ReflectionClass(\FalconCms\Core\FalconCmsServiceProvider::class))->getFileName());
        $package = $providerDir . '/../resources/views/themes/falcon-theme';

        return File::isDirectory($package) ? $package : null;
    }

    /** A child theme that inherits the parent's templates; override selectively. */
    protected function scaffoldChild(string $dir, string $slug, string $name, string $parent): int
    {
        $this->putJson($dir . '/theme.json', [
            'name'        => $name,
            'version'     => '1.0.0',
            'description' => "A child theme of {$parent}.",
            'author'      => '',
            'parent'      => $parent,
            'screenshot'  => 'screenshot.png',
        ]);

        File::put($dir . '/functions.php', $this->functionsStub($name));

        $this->info("Child theme scaffolded at: {$dir}");
        $this->line("It inherits every template from '{$parent}'. Drop a copy of any template");
        $this->line('(e.g. page.blade.php) into this folder to override just that one.');
        if ($this->option('shop')) {
            $this->line("--shop is not needed for a child theme: it already inherits {$parent}'s store templates.");
        }

        return self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function putJson(string $path, array $data): void
    {
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** Replace slug/name placeholders in a Blade stub. */
    protected function render(string $stub, string $slug, string $name): string
    {
        return str_replace(['__SLUG__', '__THEME_NAME__'], [$slug, $name], $stub);
    }

    protected function functionsStub(string $name): string
    {
        return <<<PHP
        <?php

        /**
         * {$name} — theme functions & hooks (auto-loaded while this theme is active).
         */

        // Inject into the <head> of every page.
        add_falcon_action('falcon_head', function () {
            // echo '<style>/* your CSS */</style>';
        });

        // Filter the site title.
        add_falcon_filter('site_title', function (\$title) {
            return \$title;
        });

        PHP;
    }

    protected function optionsStub(): string
    {
        return <<<'PHP'
        <?php

        /**
         * Theme options — surfaced in Appearance → Customize.
         * Return an array of option definitions, or leave empty to start.
         */
        return [];

        PHP;
    }

    protected function layoutStub(): string
    {
        return <<<'BLADE'
        <!DOCTYPE html>
        <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            @include('falcon-cms::components.frontend.seo-meta')
            <title>@yield('title', get_cms_option('site_title', 'FalconCMS'))</title>

            {{-- Starter styling via CDN — replace with your own compiled CSS. --}}
            <script src="https://cdn.tailwindcss.com"></script>

            @yield('styles')
            {{-- Lets the Customizer and plugins inject into <head>. --}}
            {!! do_falcon_action('falcon_head') !!}
        </head>
        <body class="antialiased text-slate-800 bg-white">

            <header class="border-b border-slate-200">
                <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
                    <a href="{{ url('/') }}" class="font-bold text-lg">{{ get_cms_option('site_title', 'FalconCMS') }}</a>
                    <nav class="text-sm text-slate-600">
                        <a href="{{ url('/') }}" class="hover:text-slate-900">Home</a>
                    </nav>
                </div>
            </header>

            <main class="max-w-5xl mx-auto px-4 py-10 min-h-[60vh]">
                @yield('content')
            </main>

            <footer class="border-t border-slate-200 mt-10">
                <div class="max-w-5xl mx-auto px-4 py-6 text-sm text-slate-500">
                    &copy; {{ date('Y') }} {{ get_cms_option('site_title', 'FalconCMS') }}
                </div>
            </footer>

            @yield('scripts')
            {{-- Lets the Customizer and plugins inject before </body>. --}}
            {!! do_falcon_action('falcon_footer') !!}
        </body>
        </html>
        BLADE;
    }

    protected function indexStub(): string
    {
        return <<<'BLADE'
        @extends('themes.__SLUG__.layouts.app')

        @section('content')
            <h1 class="text-2xl font-bold mb-6">Latest Posts</h1>

            @php $posts = get_falcon_posts(['post_type' => 'post', 'limit' => 9, 'paginate' => true]); @endphp

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($posts as $post)
                    <article class="border border-slate-200 rounded-lg overflow-hidden hover:shadow-sm transition">
                        <a href="{{ get_falcon_permalink($post) }}" class="block p-5">
                            <h2 class="font-semibold text-slate-900">{{ $post->title }}</h2>
                            <p class="text-sm text-slate-500 mt-2">
                                {{ \Illuminate\Support\Str::limit(strip_tags($post->content), 110) }}
                            </p>
                        </a>
                    </article>
                @empty
                    <p class="text-slate-500">No posts yet.</p>
                @endforelse
            </div>

            @if(is_object($posts) && method_exists($posts, 'links'))
                <div class="mt-8">{{ $posts->links() }}</div>
            @endif
        @endsection
        BLADE;
    }

    protected function pageStub(): string
    {
        return <<<'BLADE'
        @extends('themes.__SLUG__.layouts.app')

        @section('title', $post->title)

        @section('content')
            {{-- get_lazy_content renders Falcon Builder content, or plain content for classic pages. --}}
            <article class="prose prose-slate max-w-none">
                {!! get_lazy_content($post->content) !!}
            </article>
        @endsection
        BLADE;
    }

    protected function singleStub(): string
    {
        return <<<'BLADE'
        @extends('themes.__SLUG__.layouts.app')

        @section('title', $post->title)

        @section('content')
            <article class="prose prose-slate max-w-none">
                <h1>{{ $post->title }}</h1>
                {!! get_lazy_content($post->content) !!}
            </article>
        @endsection
        BLADE;
    }

    protected function notFoundStub(): string
    {
        return <<<'BLADE'
        @extends('themes.__SLUG__.layouts.app')

        @section('content')
            <div class="text-center py-24">
                <h1 class="text-6xl font-bold text-slate-900">404</h1>
                <p class="mt-3 text-slate-500">The page you’re looking for doesn’t exist.</p>
                <a href="{{ url('/') }}" class="inline-block mt-6 text-blue-600 hover:underline">Go home</a>
            </div>
        @endsection
        BLADE;
    }
}
