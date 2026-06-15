<?php

namespace FalconCms\Core\Console\Commands;

use FalconCms\Core\Models\Menu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeDashboardPage extends Command
{
    protected $signature = 'make:falcon-page {name}';
    protected $description = 'Scaffold a new dashboard page';

    public function handle()
    {
        $name = $this->argument('name');
        $pluralName = Str::pluralStudly($name);
        $slug = Str::slug(Str::plural($name));
        $controllerName = "{$pluralName}Controller";

        $this->info("Creating Dashboard Page: {$name}");

        $path = app_path("Http/Controllers/Admin/{$controllerName}.php");
        File::ensureDirectoryExists(dirname($path));
        
        $stub = "<?php\n\nnamespace App\Http\Controllers\Admin;\n\nuse Illuminate\Routing\Controller;\n\nclass {$controllerName} extends Controller\n{\n    public function index()\n    {\n        return view('admin.{$slug}.index');\n    }\n}\n";
        File::put($path, $stub);

        $viewPath = resource_path("views/admin/{$slug}/index.blade.php");
        File::ensureDirectoryExists(dirname($viewPath));
        $viewStub = "<x-falcon-cms::layouts.admin title=\"{$pluralName}\">\n    <h1 class=\"text-2xl font-semibold mb-6\">{$pluralName}</h1>\n    <div class=\"bg-white shadow-sm ring-1 ring-gray-200 rounded-lg p-6\">Hello {$name}</div>\n</x-falcon-cms::layouts.admin>";
        File::put($viewPath, $viewStub);

        $routeFile = base_path('routes/web.php');
        $routeCode = "\nRoute::prefix('admin')->name('admin.')->middleware(['web'])->group(function () {\n    Route::get('/{$slug}', [\App\Http\Controllers\Admin\\{$controllerName}::class, 'index'])->name('{$slug}.index');\n});\n";
        File::append($routeFile, $routeCode);

        Menu::firstOrCreate(['route' => "admin.{$slug}.index"], [
            'title' => $pluralName,
            'icon' => '<svg class="w-5 h-5 mr-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
            'group' => 'Custom',
            'order' => 50,
        ]);

        $this->info("Scaffolded successfully!");
    }
}
