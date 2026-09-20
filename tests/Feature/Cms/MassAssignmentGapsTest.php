<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Form;
use FalconCms\Core\Models\Menu;
use FalconCms\Core\Models\NavigationMenuItem;
use FalconCms\Core\Models\PostType;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

/**
 * Columns that are written but were not mass-assignable.
 *
 * Eloquent drops a non-fillable key without a word, so this kind of gap never announces
 * itself: the create succeeds, the row is saved, and one column is quietly empty. Seeding hid
 * the worst of it, because Laravel's db:seed wraps the seeder in Model::unguarded() — so the
 * menus table looked right on every real site while any other code path silently lost the same
 * value.
 */
class MassAssignmentGapsTest extends TestCase
{
    public function test_a_menu_keeps_the_params_that_tell_it_apart_from_posts(): void
    {
        // Products → All Products and Posts → All Posts are the same route. The only thing
        // distinguishing them is params, and creating a menu outside a seeder used to lose it,
        // so the menu pointed at every post on the site.
        $menu = Menu::create([
            'title' => 'All Products',
            'route' => 'admin.posts.index',
            'params' => json_encode(['type' => 'product']),
            'group' => 'Main',
            'order' => 10,
        ]);

        $this->assertSame('{"type":"product"}', $menu->fresh()->params);
    }

    public function test_a_form_keeps_the_language_it_was_created_in(): void
    {
        // FormController::store() passes lang_code on every create; without it on the model
        // every form was saved with none at all.
        $form = Form::create([
            'title' => 'Contact', 'slug' => 'contact', 'status' => true,
            'lang_code' => 'bn',
        ]);

        $this->assertSame('bn', $form->fresh()->lang_code);
    }

    public function test_no_column_anything_writes_is_left_out_of_fillable(): void
    {
        // A standing guard rather than one assertion per column: every column these models
        // have, minus the ones Eloquent manages itself and the vestigial ones nothing writes.
        $ignore = ['id', 'created_at', 'updated_at', 'deleted_at'];

        $vestigial = [
            // post_types carries these from an older schema; nothing reads or writes them, and
            // making them fillable would only suggest otherwise.
            'post_types' => ['has_archive', 'public', 'show_in_rest', 'hierarchical', 'exclude_from_search', 'publicly_queryable'],
        ];

        foreach ([
            'menus' => Menu::class,
            'cms_forms' => Form::class,
            'navigation_menu_items' => NavigationMenuItem::class,
            'post_types' => PostType::class,
        ] as $table => $class) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $missing = array_diff(
                Schema::getColumnListing($table),
                (new $class)->getFillable(),
                $ignore,
                $vestigial[$table] ?? []
            );

            $this->assertSame([], array_values($missing),
                "{$table} has columns outside \$fillable: ".implode(', ', $missing));
        }
    }
}
