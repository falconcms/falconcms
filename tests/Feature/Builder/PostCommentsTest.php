<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Models\Comment;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

/**
 * The Comments element (post_comments): the comment list and form of the post a layout is
 * shown for, and the comment flow behind it.
 */
class PostCommentsTest extends TestCase
{
    // ── rendering ────────────────────────────────────────────────────────────

    public function test_it_lists_the_approved_comments_of_the_post_being_viewed(): void
    {
        $post = $this->makePost('hello');
        $first = $this->comment($post, 'Ada', 'First thoughts here', true);
        $this->comment($post, 'Bob', 'A reply to Ada', true, $first->id);
        $this->comment($post, 'Spam', 'Waiting for approval', false);
        $this->comment($this->makePost('other'), 'Eve', 'On another post', true);

        $html = $this->render([], $post);

        $this->assertStringContainsString('First thoughts here', $html);
        $this->assertStringContainsString('A reply to Ada', $html);
        $this->assertStringNotContainsString('Waiting for approval', $html);
        $this->assertStringNotContainsString('On another post', $html);
        $this->assertStringContainsString('(2)', $html, 'the count covers approved comments and replies');
        $this->assertStringContainsString('name="post_id" value="'.$post->id.'"', $html);
        $this->assertStringContainsString('name="parent_id" value="'.$first->id.'"', $html);
    }

    public function test_the_page_current_post_is_used_when_the_layout_gives_none(): void
    {
        $post = $this->makePost('shared');
        $this->comment($post, 'Ada', 'Found through current_post', true);
        view()->share('current_post', $post);

        $html = view('falcon-cms::frontend.builder.elements.post-comments', ['el' => [
            'id' => 'c1', 'type' => 'post_comments', 'settings' => [],
        ]])->render();

        $this->assertStringContainsString('Found through current_post', $html);
    }

    public function test_without_a_post_it_renders_nothing(): void
    {
        $html = view('falcon-cms::frontend.builder.elements.post-comments', ['el' => [
            'id' => 'c1', 'type' => 'post_comments', 'settings' => [],
        ]])->render();

        $this->assertSame('', trim($html));
    }

    public function test_settings_shape_the_output(): void
    {
        $post = $this->makePost('settings');
        $old = $this->comment($post, 'Old', 'Older comment', true);
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $this->comment($post, 'New', 'Newer comment', true);

        $html = $this->render([
            'title' => '', 'order' => 'newest', 'showForm' => false,
            'accentColor' => 'red;}</style><script>x</script>',
        ], $post);

        $this->assertLessThan(strpos($html, 'Older comment'), strpos($html, 'Newer comment'));
        $this->assertStringNotContainsString('class="fcm-title"', $html, 'a cleared heading shows no heading');
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('class="fcm-reply-btn"', $html, 'no form, no reply buttons');
        $this->assertStringNotContainsString('<script>x</script>', $html, 'a colour cannot break out of the style');
    }

    public function test_the_notice_shows_only_on_the_post_it_belongs_to(): void
    {
        $post = $this->makePost('notice');
        $other = $this->makePost('elsewhere');
        session()->flash('success', 'Your comment is awaiting moderation.');
        session()->flash('falcon_comment_posted', $other->id);

        $this->assertStringNotContainsString('awaiting moderation', $this->render([], $post));
        $this->assertStringContainsString('awaiting moderation', $this->render([], $other));
    }

    // ── the round trip ───────────────────────────────────────────────────────

    public function test_every_setting_survives_the_shortcode_round_trip(): void
    {
        $settings = [
            'title' => '', 'showCount' => false, 'order' => 'newest', 'allowReplies' => false,
            'showForm' => true, 'formTitle' => 'Say something', 'buttonText' => 'Send',
            'emptyText' => 'Quiet here', 'accentColor' => '#ff0000', 'radius' => 4,
            'cm_title_size' => '30px', 'marginTop_mobile' => 12, 'cssClass' => 'mine',
        ];
        $sc = BuilderShortcodeConverter::jsonToShortcodes((string) json_encode([[
            'id' => 'c', 'columns' => [['id' => 'k', 'elements' => [['id' => 'e', 'type' => 'post_comments', 'settings' => $settings]]]],
        ]]));

        $this->assertStringContainsString('[falcon_post_comments', $sc);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson($sc), true)[0]['columns'][0]['elements'][0];

        $this->assertSame('post_comments', $back['type']);
        foreach ($settings as $key => $value) {
            $this->assertEquals($value, $back['settings'][$key] ?? null, "{$key} was lost");
        }
    }

    // ── posting ──────────────────────────────────────────────────────────────

    public function test_a_guest_comment_goes_back_to_the_comments_marked_for_its_post(): void
    {
        $post = $this->makePost('posting');

        $response = $this->from('/post/posting')->post(route('frontend.comment.store'), [
            'post_id' => $post->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'comment' => 'Hello there',
        ]);

        $response->assertRedirect('/post/posting#comments');
        $response->assertSessionHas('falcon_comment_posted', $post->id);
        $this->assertDatabaseHas('comments', ['post_id' => $post->id, 'name' => 'Ada', 'is_approved' => false]);
    }

    public function test_a_guest_must_give_a_name_and_an_email(): void
    {
        $post = $this->makePost('anonymous');

        $this->post(route('frontend.comment.store'), ['post_id' => $post->id, 'comment' => 'No name'])
            ->assertSessionHasErrors(['name', 'email']);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_a_filled_honeypot_is_thanked_and_dropped(): void
    {
        $post = $this->makePost('bots');

        $this->post(route('frontend.comment.store'), [
            'post_id' => $post->id, 'name' => 'Bot', 'email' => 'bot@example.com',
            'comment' => 'Buy things', 'website' => 'https://spam.example',
        ])->assertRedirect();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_an_unpublished_post_takes_no_comments(): void
    {
        $post = $this->makePost('draft', 'draft');

        $this->post(route('frontend.comment.store'), [
            'post_id' => $post->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'comment' => 'Hello there',
        ])->assertNotFound();
    }

    public function test_a_reply_must_belong_to_the_same_post_and_joins_its_thread(): void
    {
        $post = $this->makePost('threads');
        $top = $this->comment($post, 'Ada', 'Top level', true);
        $reply = $this->comment($post, 'Bob', 'A reply', true, $top->id);
        $elsewhere = $this->comment($this->makePost('away'), 'Eve', 'Elsewhere', true);

        $base = ['post_id' => $post->id, 'name' => 'Cy', 'email' => 'cy@example.com', 'comment' => 'Replying'];

        $this->post(route('frontend.comment.store'), $base + ['parent_id' => $elsewhere->id])->assertStatus(422);

        $this->post(route('frontend.comment.store'), $base + ['parent_id' => $reply->id])->assertRedirect();
        $this->assertDatabaseHas('comments', ['name' => 'Cy', 'parent_id' => $top->id]);
    }

    // ── security ─────────────────────────────────────────────────────────────

    public function test_script_in_a_name_or_comment_is_shown_as_text_everywhere(): void
    {
        $post = $this->makePost('xss');
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $c = $this->comment($post, 'Mallory '.$payload, 'Hi '.$payload.' "quoted" \' end', true);

        foreach ([
            $this->render([], $post),
            view('falcon-cms::themes.falcon-theme.partials.comments', ['post' => $post->fresh()])->render(),
        ] as $html) {
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        }

        $this->actingAs($this->admin());
        $admin = $this->get(route('admin.comments.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $admin);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $admin);
    }

    public function test_tags_and_invisible_characters_are_taken_out_of_a_name(): void
    {
        $post = $this->makePost('names');

        $this->submit($post, ['name' => "<b>Ad\u{202E}min</b>\u{200B}", 'comment' => "Line one\r\n\r\n\r\n\r\nLine\u{0007} two"]);

        $c = Comment::latest('id')->first();
        $this->assertSame('Admin', $c->name);
        $this->assertSame("Line one\n\nLine two", $c->comment);
    }

    public function test_the_redirect_never_follows_the_referer(): void
    {
        $post = $this->makePost('redirect');

        $this->withHeader('referer', 'https://evil.example/phish')
            ->post(route('frontend.comment.store'), $this->fields($post))
            ->assertRedirect(get_falcon_permalink($post).'#comments');

        $this->withHeader('referer', 'https://evil.example/phish')
            ->post(route('frontend.comment.store'), $this->fields($post, ['website' => 'x']))
            ->assertRedirect(get_falcon_permalink($post).'#comments');
    }

    public function test_approval_cannot_be_asked_for_in_the_request(): void
    {
        $post = $this->makePost('mass');

        $this->submit($post, ['is_approved' => 1, 'user_id' => 1, 'comment' => 'Let me in please']);

        $this->assertDatabaseHas('comments', ['comment' => 'Let me in please', 'is_approved' => false, 'user_id' => null]);
    }

    public function test_a_known_email_alone_does_not_publish_a_guest_comment(): void
    {
        $post = $this->makePost('spoof');
        $this->comment($post, 'Ada', 'Earlier and approved', true); // ada@example.com

        // A stranger claiming Ada's address under another name waits for a moderator.
        $this->submit($post, ['name' => 'Not Ada', 'email' => 'ada@example.com', 'comment' => 'Spoofed words']);
        $this->assertDatabaseHas('comments', ['comment' => 'Spoofed words', 'is_approved' => false]);

        // Ada herself, same name and address, goes straight through…
        $this->submit($post, ['name' => 'Ada', 'email' => 'ADA@example.com', 'comment' => 'Ada again']);
        $this->assertDatabaseHas('comments', ['comment' => 'Ada again', 'is_approved' => true]);

        // …unless she posts a link.
        $this->submit($post, ['name' => 'Ada', 'email' => 'ada@example.com', 'comment' => 'See https://spam.example/buy']);
        $this->assertDatabaseHas('comments', ['comment' => 'See https://spam.example/buy', 'is_approved' => false]);
    }

    public function test_a_members_email_does_not_publish_a_guest_comment(): void
    {
        $post = $this->makePost('member');
        $admin = $this->admin();
        Comment::create(['post_id' => $post->id, 'name' => $admin->name, 'email' => $admin->email, 'comment' => 'Old guest one', 'is_approved' => true]);

        $this->submit($post, ['name' => $admin->name, 'email' => $admin->email, 'comment' => 'Pretending to be the admin']);

        $this->assertDatabaseHas('comments', ['comment' => 'Pretending to be the admin', 'is_approved' => false]);
    }

    public function test_an_unapproved_comment_cannot_be_replied_to(): void
    {
        $post = $this->makePost('pending-parent');
        $pending = $this->comment($post, 'Spam', 'Pending one', false);

        $this->post(route('frontend.comment.store'), $this->fields($post, ['parent_id' => $pending->id]))->assertStatus(422);
    }

    public function test_the_same_comment_twice_is_kept_once(): void
    {
        $post = $this->makePost('double');

        $this->submit($post, ['comment' => 'Clicked twice']);
        $this->submit($post, ['comment' => 'Clicked twice']);

        $this->assertSame(1, Comment::where('comment', 'Clicked twice')->count());
    }

    public function test_sql_looking_input_is_stored_as_plain_text(): void
    {
        $post = $this->makePost('sql');
        $text = "x'); DROP TABLE comments; -- \" OR 1=1";

        $this->submit($post, ['name' => "Robert'); --", 'comment' => $text]);

        $this->assertDatabaseHas('comments', ['comment' => $text]);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('comments'));
    }

    // ── the page cache ───────────────────────────────────────────────────────

    public function test_a_comment_change_drops_its_posts_cached_page(): void
    {
        $post = $this->makePost('cached');
        $url = get_falcon_permalink($post);
        Cache::put('page_cache_'.md5($url), ['content' => 'old', 'type' => 'text/html', 'csrf' => true], 600);
        Cache::put('page_cache_'.md5(url('/')), ['content' => 'home', 'type' => 'text/html', 'csrf' => true], 600);

        $this->comment($post, 'Ada', 'Approved now', true);

        $this->assertFalse(Cache::has('page_cache_'.md5($url)), 'the post page kept its old comment list');
        $this->assertTrue(Cache::has('page_cache_'.md5(url('/'))), 'an unrelated page was dropped too');
    }

    public function test_a_page_showing_flashed_data_is_neither_served_from_nor_put_in_the_cache(): void
    {
        $this->setCmsOptions(['performance_static_caching' => '1']);

        $this->get('/')->assertOk()->assertHeader('X-Lazy-Cache', 'MISS');

        $this->withSession(['success' => 'Your comment is awaiting moderation.', '_flash' => ['old' => ['success'], 'new' => []]])
            ->get('/')->assertOk()->assertHeaderMissing('X-Lazy-Cache');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function fields(Post $post, array $extra = []): array
    {
        return $extra + ['post_id' => $post->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'comment' => 'Hello there'];
    }

    private function submit(Post $post, array $extra = []): void
    {
        $this->post(route('frontend.comment.store'), $this->fields($post, $extra))->assertRedirect();
    }

    private function admin(): \App\Models\User
    {
        return \App\Models\User::where('email', 'admin-xss@example.com')->first() ?? \App\Models\User::forceCreate([
            'name' => 'Site Admin', 'email' => 'admin-xss@example.com', 'password' => bcrypt('secret-'.uniqid()),
            'role_id' => (int) \Illuminate\Support\Facades\DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function render(array $settings, Post $post): string
    {
        return view('falcon-cms::frontend.builder.elements.post-comments', [
            'el' => ['id' => 'c1', 'type' => 'post_comments', 'settings' => $settings],
            'post' => $post,
        ])->render();
    }

    private function makePost(string $slug, string $status = 'published'): Post
    {
        return Post::create([
            'title' => ucfirst($slug), 'slug' => $slug, 'type' => 'post',
            'status' => $status, 'lang_code' => 'en', 'content' => '',
        ]);
    }

    private function comment(Post $post, string $name, string $text, bool $approved, ?int $parentId = null): Comment
    {
        return Comment::create([
            'post_id' => $post->id, 'name' => $name, 'email' => strtolower($name).'@example.com',
            'comment' => $text, 'is_approved' => $approved, 'parent_id' => $parentId,
        ]);
    }
}
