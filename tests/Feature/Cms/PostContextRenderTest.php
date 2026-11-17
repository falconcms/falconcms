<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * The post context (_falcon_layout_post_context) renders the viewed post's content so a Content
 * or Post Meta element in a layout can show it. That throwaway render used to run inside the
 * page's own render: it spent the @once guards, so the copy the visitor saw lost its one-time
 * <style>. The blog's paginated post grid then showed its arrows as two giant SVGs. It also ran
 * again for every layout section, querying the blog's posts eight times per page.
 */
class PostContextRenderTest extends TestCase
{
    private int $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = (int) DB::table('users')->insertGetId([
            'name' => 'A', 'email' => uniqid().'@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePost(array $attrs): Post
    {
        return Post::create($attrs + [
            'slug' => 'p-'.uniqid(), 'type' => 'post', 'status' => 'published',
            'published_at' => now()->subDay(), 'user_id' => $this->author,
        ]);
    }

    /** A builder page: one paginated post grid, one post per page. */
    private function blogPage(): Post
    {
        $layout = [[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'e1', 'type' => 'card',
                'settings' => ['post_type' => 'post', 'posts_count' => 1, 'pagination_type' => 'numbered'],
            ]]]],
        ]];

        return $this->makePost(['title' => 'Blog', 'type' => 'page', 'editor_type' => 'builder', 'content' => json_encode($layout)]);
    }

    public function test_a_paginated_grid_keeps_its_pagination_styles_inside_the_page_render(): void
    {
        $this->makePost(['title' => 'One', 'content' => '<p>One</p>']);
        $this->makePost(['title' => 'Two', 'content' => '<p>Two</p>']);
        $page = $this->blogPage();
        View::share('current_post', $page);

        // Inside an outer view, as on a real page: the context render and the visible render
        // share the factory's once-guards.
        $html = Blade::render('<main>{!! get_falcon_post_content($c) !!}</main>', ['c' => $page->content]);

        $this->assertStringContainsString('class="falcon-pagination"', $html);
        $this->assertStringContainsString('.falcon-pagination{', $html, 'the pagination lost its one-time styles');
    }

    public function test_the_content_is_rendered_only_for_a_layout_that_shows_it(): void
    {
        $post = $this->makePost(['title' => 'Story', 'content' => '<p>Hello world</p>']);

        $plain = [['type' => 'container', 'columns' => [['elements' => [['type' => 'title', 'settings' => []]]]]]];
        $shows = [['type' => 'container', 'columns' => [['elements' => [['type' => 'post_content', 'settings' => []]]]]]];

        $this->assertSame('', _falcon_layout_post_context($post, $plain)['postContent']);
        $this->assertStringContainsString('Hello world', _falcon_layout_post_context($post, $shows)['postContent']);
        $this->assertStringContainsString('Hello world', _falcon_layout_post_context($post)['postContent'], 'no layout: as before');
    }

    public function test_the_context_render_leaves_the_view_factory_as_it_was(): void
    {
        $post = $this->makePost(['title' => 'Story', 'content' => '<p>Hi</p>']);
        $factory = view();
        $factory->markAsRenderedOnce('outer-guard');
        $factory->startPush('head', '<meta name="x">');

        _falcon_post_context_content($post);

        $this->assertTrue($factory->hasRenderedOnce('outer-guard'));
        $this->assertSame('<meta name="x">', $factory->yieldPushContent('head'));
    }
}
