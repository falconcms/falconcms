<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Core\HookManager;
use FalconCms\Core\Tests\TestCase;

/**
 * The hook tags that still carried the old brand are falcon_* now, and the old names still work.
 *
 * A renamed tag is worse than a renamed function: nothing throws. A theme with
 * add_falcon_filter('lazy_invoice_title', …) in its functions.php would simply stop being
 * called, and the only symptom is an invoice that quietly says the wrong thing. So the two
 * names are one hook — whichever a caller registers on, fires, removes or asks about.
 */
class RenamedHookTest extends TestCase
{
    public function test_a_callback_on_the_old_name_runs_when_the_new_one_fires(): void
    {
        foreach (HookManager::renamedTags() as $old => $new) {
            add_falcon_filter($old, fn ($value) => $value.'|via-'.$old);

            $this->assertSame(
                'x|via-'.$old,
                apply_falcon_filters($new, 'x'),
                "a filter registered on {$old} did not run when {$new} was applied"
            );
        }
    }

    public function test_a_callback_on_the_new_name_runs_when_the_old_one_fires(): void
    {
        // Third-party code applies these too, not just registers on them.
        foreach (HookManager::renamedTags() as $old => $new) {
            add_falcon_filter($new, fn ($value) => $value.'|via-'.$new);

            $this->assertSame(
                'y|via-'.$new,
                apply_falcon_filters($old, 'y'),
                "a filter registered on {$new} did not run when {$old} was applied"
            );
        }
    }

    public function test_actions_alias_the_same_way(): void
    {
        $ran = 0;
        add_falcon_action('lazy_invoice_title', function () use (&$ran) { $ran++; });

        do_falcon_action('falcon_invoice_title');
        do_falcon_action('lazy_invoice_title');

        $this->assertSame(2, $ran);
    }

    public function test_has_and_remove_see_through_the_old_name(): void
    {
        $callback = fn ($value) => $value;
        add_falcon_filter('falcon_api_post_data', $callback);

        $this->assertTrue(has_falcon_filter('lazy_api_post_data'));
        $this->assertTrue(remove_falcon_filter('lazy_api_post_data', $callback));
        $this->assertFalse(has_falcon_filter('falcon_api_post_data'));
    }

    public function test_the_field_group_filters_alias_by_shape(): void
    {
        // A theme picks the key, so this family cannot be a fixed list: hooks.delivery in a
        // theme's options makes lazy_delivery_fields, a tag the CMS has never heard of.
        add_falcon_filter('lazy_delivery_fields', fn ($fields) => array_merge($fields, ['extra']));

        $this->assertSame(['extra'], apply_falcon_filters('falcon_delivery_fields', []));
        $this->assertSame('falcon_billing_fields', HookManager::canonical('lazy_billing_fields'));
        $this->assertSame('falcon_delivery_fields', HookManager::canonical('lazy_delivery_fields'));
    }

    public function test_the_shape_rule_does_not_catch_unrelated_tags(): void
    {
        // Only lazy_*_fields. Anything else keeps its own name unless it is on the map.
        $this->assertSame('lazy_fields_of_mine', HookManager::canonical('lazy_fields_of_mine'));
        $this->assertSame('my_lazy_fields', HookManager::canonical('my_lazy_fields'));
    }

    public function test_a_tag_that_was_never_renamed_is_left_alone(): void
    {
        $this->assertSame('falcon_head', HookManager::canonical('falcon_head'));
        $this->assertSame('something_of_my_own', HookManager::canonical('something_of_my_own'));
    }

    public function test_the_cms_no_longer_fires_a_tag_with_the_old_name_in_it(): void
    {
        $hits = [];
        foreach ([__DIR__.'/../../../src', __DIR__.'/../../../resources'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if (!$file->isFile() || !preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                if (preg_match('/(apply_falcon_filters|do_falcon_action)\(\s*["\'][a-z_{}$]*lazy/', $source)) {
                    $hits[] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $hits, 'these still fire a hook named "lazy": '.implode(', ', $hits));
    }
}
