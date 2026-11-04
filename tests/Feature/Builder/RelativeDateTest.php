<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use Carbon\Carbon;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Support\RelativeDates;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Post Date "Show As: Time ago" — "3 hours ago" for news.
 *
 * The words are written on the server (the site's language, Carbon) inside a <time> that a
 * footer script sets again from the reader's clock, so a page served from the page cache does
 * not keep saying "3 hours ago" the next day. Past the limit (7 days by default) the date shows.
 */
class RelativeDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00'));
        RelativeDates::used(true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app()->setLocale('en');
        parent::tearDown();
    }

    private function postAt(string $published, ?string $updated = null): object
    {
        return (object) ['id' => 7, 'title' => 'T', 'type' => 'post', 'status' => 'published',
            'published_at' => $published, 'created_at' => $published, 'updated_at' => $updated ?? $published];
    }

    private function relative(array $extra = []): array
    {
        return falcon_dynamic_config(array_merge(['dynamic_date_display' => 'relative'], $extra));
    }

    public function test_the_words_step_through_the_units(): void
    {
        $now = Carbon::now();
        $this->assertSame('just now', RelativeDates::describe($now)['text']);
        $this->assertSame('5 minutes ago', RelativeDates::describe($now->copy()->subMinutes(5))['text']);
        $this->assertSame('3 hours ago', RelativeDates::describe($now->copy()->subHours(3)->subMinutes(20))['text']);
        $this->assertSame('1 day ago', RelativeDates::describe($now->copy()->subHours(30))['text']);
        $this->assertSame('6 days ago', RelativeDates::describe($now->copy()->subDays(6))['text']);
    }

    public function test_past_the_limit_the_date_shows_in_the_chosen_format(): void
    {
        $old = Carbon::now()->subDays(8);
        $info = RelativeDates::describe($old, ['date_format' => 'M j, Y']);
        $this->assertFalse($info['relative']);
        $this->assertSame('Oct 27, 2026', $info['text']);

        // A limit of its own, or 0 = always "ago".
        $this->assertSame('1 week ago', RelativeDates::describe($old, ['date_relative_days' => 0])['text']);
        $this->assertSame('1 week ago', RelativeDates::describe($old, ['date_relative_days' => '30'])['text']);
        $this->assertFalse(RelativeDates::describe(Carbon::now()->subDays(2), ['date_relative_days' => 1])['relative']);
        // Empty or nonsense falls back to the default 7 days.
        $this->assertSame(7, RelativeDates::days(''));
        $this->assertSame(7, RelativeDates::days('abc'));
        $this->assertSame(0, RelativeDates::days('-3'));
    }

    public function test_bangla_digits_and_the_site_language(): void
    {
        $three = Carbon::now()->subHours(3);
        $this->assertSame('৩ hours ago', RelativeDates::describe($three, ['digits' => 'bengali'])['text']);

        app()->setLocale('bn');
        $text = RelativeDates::describe($three, ['digits' => 'bengali'])['text'];
        $this->assertStringContainsString('৩', $text);
        $this->assertStringNotContainsString('hours', $text, 'a Bangla site gets Bangla words: '.$text);
    }

    public function test_the_dynamic_source_writes_a_time_the_browser_keeps_current(): void
    {
        $post = $this->postAt('2026-11-04 09:00:00');
        $out = falcon_resolve_dynamic_value('post_date', $post, $this->relative(['dynamic_before' => 'Published: ', 'dynamic_after' => ' <b>']));
        $this->assertInstanceOf(Htmlable::class, $out);
        $html = $out->toHtml();
        $this->assertStringContainsString('<time class="falcon-time-ago" datetime="2026-11-04T09:00:00', $html);
        $this->assertStringContainsString('data-falcon-ago="7"', $html);
        $this->assertStringContainsString('data-date="Nov 4, 2026"', $html);
        $this->assertStringContainsString('>3 hours ago</time>', $html);
        // Before and After are text, as everywhere else.
        $this->assertStringStartsWith('Published: <time', $html);
        $this->assertStringEndsWith('</time> &lt;b&gt;', $html);

        // Modified date, when asked for.
        $out = falcon_resolve_dynamic_value('post_date', $this->postAt('2026-10-01 09:00:00', '2026-11-04 11:30:00'), $this->relative(['dynamic_date_type' => 'modified']));
        $this->assertStringContainsString('>30 minutes ago</time>', $out->toHtml());

        // "Date" (the default) is unchanged: plain text in the format.
        $this->assertSame('Nov 4, 2026', falcon_resolve_dynamic_value('post_date', $post, falcon_dynamic_config([])));
        $this->assertSame('৪ Nov ২০২৬', falcon_resolve_dynamic_value('post_date', $post, falcon_dynamic_config(['dynamic_date_format' => 'j M Y', 'dynamic_digits' => 'bengali'])));
    }

    public function test_title_button_and_text_block_put_the_time_on_the_page(): void
    {
        $post = $this->postAt('2026-11-04 09:00:00');
        $s = ['dynamic_source' => 'post_date', 'dynamic_date_display' => 'relative', 'titleColor' => '#222', 'htmlTag' => 'p'];
        foreach (['title', 'button', 'text-block'] as $view) {
            $html = view('falcon-cms::frontend.builder.elements.'.$view, ['el' => ['id' => 'e1', 'type' => str_replace('-', '_', $view), 'settings' => $s + ['linkUrl' => '#']], 'post' => $post])->render();
            $this->assertStringContainsString('<time class="falcon-time-ago"', $html, $view);
            $this->assertStringContainsString('3 hours ago</time>', $html, $view);
            $this->assertStringNotContainsString('&lt;time', $html, $view.' must not show the tag as text');
        }
    }

    public function test_the_footer_carries_the_script_only_where_it_is_used(): void
    {
        RelativeDates::used(true);
        ob_start();
        do_falcon_action('falcon_footer');
        $this->assertStringNotContainsString('falconTimeAgo', (string) ob_get_clean());

        falcon_resolve_dynamic_value('post_date', $this->postAt('2026-11-04 09:00:00'), $this->relative());
        ob_start();
        do_falcon_action('falcon_footer');
        $footer = (string) ob_get_clean();
        $this->assertSame(1, substr_count($footer, 'if(window.falconTimeAgo)return'));
        $this->assertStringContainsString('data-no-defer', $footer);
    }

    public function test_the_choice_survives_the_shortcode_round_trip(): void
    {
        $json = json_encode([[
            'id' => 'r1', 'settings' => [],
            'columns' => [['id' => 'c1', 'basis' => '100%', 'basis_tablet' => null, 'basis_mobile' => '100%', 'settings' => [],
                'elements' => [['id' => 'e1', 'type' => 'title', 'settings' => ['title' => 'x', 'dynamic_source' => 'post_date',
                    'dynamic_date_display' => 'relative', 'dynamic_date_relative_days' => 3, 'dynamic_digits' => 'bengali']]]]],
        ]]);
        $back = json_decode(BuilderShortcodeConverter::shortcodesToJson(BuilderShortcodeConverter::jsonToShortcodes($json)), true)[0]['columns'][0]['elements'][0]['settings'];
        $this->assertSame('post_date', $back['dynamic_source']);
        $this->assertSame('relative', $back['dynamic_date_display']);
        $this->assertEquals(3, $back['dynamic_date_relative_days']);
        $this->assertSame('bengali', $back['dynamic_digits']);
    }

    public function test_a_plugin_element_gets_plain_words(): void
    {
        $settings = falcon_apply_custom_dynamic(['label_dynamic' => 'post_date', 'dynamic_date_display' => 'relative'], $this->postAt('2026-11-04 09:00:00'));
        $this->assertSame('3 hours ago', $settings['label']);
    }
}
