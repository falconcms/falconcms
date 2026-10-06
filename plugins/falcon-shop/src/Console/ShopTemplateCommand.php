<?php

namespace FalconShop\Console;

use FalconShop\Templates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copy a shop template into a theme to restyle it, or see which ones a theme overrides.
 *
 *   php artisan shop:template                     every template, and the theme's copies
 *   php artisan shop:template ecommerce/cart      copy the cart into the active theme
 *   php artisan shop:template cart --theme=aurora --force
 */
class ShopTemplateCommand extends Command
{
    protected $signature = 'shop:template
        {template? : The template to copy, e.g. ecommerce/cart or single-product; leave out to list them}
        {--theme= : The theme to copy into (default: the active theme)}
        {--force : Replace the theme\'s existing copy}';

    protected $description = 'Copy a shop template into a theme to customise it, or list the theme\'s overrides';

    public function handle(): int
    {
        $theme = (string) ($this->option('theme') ?: get_cms_option('active_theme', 'falcon-theme'));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $theme)) {
            $this->error("Not a theme name: {$theme}");

            return self::FAILURE;
        }

        $template = $this->argument('template');

        return $template === null ? $this->list($theme) : $this->copy($theme, (string) $template);
    }

    private function list(string $theme): int
    {
        $rows = [];
        foreach (Templates::status($theme) as $row) {
            $state = match (true) {
                $row['override'] === null => 'plugin default',
                $row['outdated'] => 'OUTDATED — theme copy is '.($row['override_version'] ?? 'unversioned'),
                default => 'overridden',
            };
            $rows[] = [
                str_replace('.', '/', $row['name']),
                $row['version'] ?? '-',
                $state,
                $row['override'] ? $this->relative($row['override']['file']) : '',
            ];
        }
        $this->table(['Template', 'Version', 'Used on '.$theme, 'Theme file'], $rows);
        $this->line('Copy one into the theme to restyle it: php artisan shop:template ecommerce/cart');

        return self::SUCCESS;
    }

    private function copy(string $theme, string $template): int
    {
        $name = Templates::normalize($template);
        if (!in_array($name, Templates::overridable(), true)) {
            // "cart" is enough when only one template has that name
            $matches = array_values(array_filter(Templates::overridable(), fn ($n) => str_ends_with($n, '.'.$name)));
            if (count($matches) !== 1) {
                $this->error("No shop template called '{$template}'. Run php artisan shop:template to see them all.");

                return self::FAILURE;
            }
            $name = $matches[0];
        }

        $relative = str_replace('.', '/', $name).'.blade.php';
        $target = resource_path("views/themes/{$theme}/{$relative}");
        if (!File::isDirectory(resource_path("views/themes/{$theme}"))) {
            $this->error("The theme '{$theme}' is not in resources/views/themes.");

            return self::FAILURE;
        }
        if (File::exists($target) && !$this->option('force')) {
            $this->error("The theme already has {$relative}. Pass --force to replace it (your changes there are lost).");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy(Templates::defaultFile($name), $target);

        $this->info('Copied '.$relative.' into '.$this->relative($target));
        $this->line('The shop now renders the theme\'s copy. Keep the {{-- @version --}} line: it is how an outdated copy is spotted after an update.');

        return self::SUCCESS;
    }

    private function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()).'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
