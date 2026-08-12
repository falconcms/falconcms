<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;

/**
 * Rebuilds the attribute filter index from the products themselves.
 *
 * The index is kept up to date on every product save, so this is only needed after a bulk import,
 * a direct database edit, or if something ever leaves it out of step.
 */
class ReindexProductAttributes extends Command
{
    protected $signature = 'falcon:reindex-attributes';

    protected $description = 'Rebuild the product attribute index used by the shop filters';

    public function handle(): int
    {
        if (!function_exists('falcon_reindex_all_product_attributes')) {
            $this->error('FalconCMS helpers are not loaded.');
            return self::FAILURE;
        }

        $this->info('Rebuilding the product attribute index...');
        $count = falcon_reindex_all_product_attributes();

        $rows = \Illuminate\Support\Facades\DB::table('shop_product_attribute_values')->count();
        $this->info("Done: {$count} product(s) indexed, {$rows} attribute value(s) available to filter on.");

        return self::SUCCESS;
    }
}
