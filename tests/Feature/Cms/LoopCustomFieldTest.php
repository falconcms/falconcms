<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * get_falcon_posts() narrows by a custom field, and lets plugins order and preload a loop.
 *
 * meta_key / meta_value were listed in the function's defaults from the start and never
 * applied, and the Card element offered "Posts By: Custom Field" on top of them — so a lead-
 * story box filtered on featured = 1 quietly listed every post. Ordering by `views`, which the
 * Card element also offers, named a column no table has and threw on the front end.
 */
class LoopCustomFieldTest extends TestCase
{
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        Post::query()->where('type', 'post')->forceDelete();
        $this->groupId = (int) DB::table('custom_field_groups')->insertGetId(['title' => 'Story', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function makeStory(string $title, string $published = '2026-05-01 10:00'): Post
    {
        return Post::forceCreate([
            'user_id' => 1, 'title' => $title, 'slug' => str($title)->slug()->toString(),
            'content' => '<p>'.$title.'</p>', 'type' => 'post', 'status' => 'published', 'lang_code' => 'en',
            'created_at' => Carbon::parse($published), 'updated_at' => Carbon::parse($published), 'published_at' => Carbon::parse($published),
        ]);
    }

    private function field(string $name): int
    {
        return (int) DB::table('custom_fields')->insertGetId([
            'field_group_id' => $this->groupId, 'label' => ucfirst($name), 'name' => $name,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function value(Post $post, int $fieldId, ?string $value): void
    {
        DB::table('post_custom_field_values')->insert(['post_id' => $post->id, 'field_id' => $fieldId, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_meta_key_and_value_filter_the_loop(): void
    {
        $featured = $this->field('featured');
        $lead = $this->makeStory('Lead story');
        $plain = $this->makeStory('Plain story');
        $other = $this->makeStory('Unfeatured story');
        $this->value($lead, $featured, '1');
        $this->value($other, $featured, '0');

        $titles = fn (array $args) => get_falcon_posts(['post_type' => 'post'] + $args)->pluck('title')->sort()->values()->all();

        $this->assertSame(['Lead story'], $titles(['meta_key' => 'featured', 'meta_value' => '1']));
        // No value: the field just has to be filled in.
        $this->assertSame(['Lead story', 'Unfeatured story'], $titles(['meta_key' => 'featured']));
        $this->assertSame([], $titles(['meta_key' => 'featured', 'meta_value' => 'yes']));
        $this->assertCount(3, $titles([]), 'without meta_key nothing is filtered');
        $this->assertNotNull($plain);
    }

    public function test_a_value_matches_one_item_of_a_list_field_but_not_part_of_one(): void
    {
        $sections = $this->field('sections');
        $post = $this->makeStory('Election night');
        $this->value($post, $sections, json_encode(['politics', 'বাংলাদেশ'], JSON_UNESCAPED_UNICODE));
        $this->makeStory('Weather');

        $this->assertSame(['Election night'], get_falcon_posts(['meta_key' => 'sections', 'meta_value' => 'politics'])->pluck('title')->all());
        $this->assertSame(['Election night'], get_falcon_posts(['meta_key' => 'sections', 'meta_value' => 'বাংলাদেশ'])->pluck('title')->all());
        $this->assertSame([], get_falcon_posts(['meta_key' => 'sections', 'meta_value' => 'polit'])->pluck('title')->all());
    }

    public function test_the_card_element_applies_its_custom_field_filter(): void
    {
        $featured = $this->field('featured');
        $this->value($this->makeStory('Picked by the editor'), $featured, '1');
        $this->makeStory('Everyday story');

        $html = view('falcon-cms::frontend.builder.elements.card', ['el' => ['settings' => [
            'post_type' => 'post', 'posts_by' => 'custom_field', 'posts_by_cf_key' => 'featured', 'posts_by_cf_value' => '1',
        ]]])->render();

        $this->assertStringContainsString('Picked by the editor', $html);
        $this->assertStringNotContainsString('Everyday story', $html);
    }

    public function test_ordering_by_views_without_a_plugin_falls_back_to_the_date(): void
    {
        $this->makeStory('Older', '2026-01-01 10:00');
        $this->makeStory('Newer', '2026-02-01 10:00');

        $this->assertSame(['Newer', 'Older'], get_falcon_posts(['orderby' => 'views'])->pluck('title')->all());
    }

    public function test_a_plugin_can_answer_an_order_and_preload_the_loop(): void
    {
        $this->makeStory('Alpha', '2026-01-01 10:00');
        $this->makeStory('Zulu', '2026-02-01 10:00');

        add_falcon_filter('falcon_posts_orderby', function ($handled, $query, $orderby, $order) {
            if ($orderby !== 'title_reversed') {
                return $handled;
            }
            $query->orderBy('title', $order === 'asc' ? 'desc' : 'asc');

            return true;
        });
        $seen = [];
        add_falcon_action('falcon_posts_fetched', function ($posts, $args) use (&$seen) {
            $seen[] = [count($posts), $args['orderby']];
        });

        $this->assertSame(['Zulu', 'Alpha'], get_falcon_posts(['orderby' => 'title_reversed', 'order' => 'asc'])->pluck('title')->all());
        $this->assertSame([[2, 'title_reversed']], $seen);

        // Paginated loops are announced too.
        $seen = [];
        get_falcon_posts(['paginate' => true, 'limit' => 1]);
        $this->assertSame([[1, 'created_at']], $seen);
    }
}
