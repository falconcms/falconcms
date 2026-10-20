<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * Reading time, and dynamic sources added by plugins.
 *
 * Reading time used str_word_count(), which knows only the Latin alphabet: a Bangla article of
 * any length counted its stray digits and English words and came out at "1 min read", and so
 * did every builder post, whose content is JSON settings rather than prose.
 */
class ReadingTimeTest extends TestCase
{
    private function makeStory(string $content): object
    {
        return (object) ['id' => random_int(1000, 999999), 'content' => $content, 'updated_at' => null, 'type' => 'post'];
    }

    public function test_words_are_counted_in_any_script(): void
    {
        $this->assertSame(6, falcon_word_count('আমার সোনার বাংলা, আমি তোমায় ভালোবাসি।'));
        $this->assertSame(4, falcon_word_count("Don't stop well-known reporting"));
        $this->assertSame(3, falcon_word_count('नमस्ते दुनिया 2026'));
        $this->assertSame(0, falcon_word_count(''));
    }

    public function test_a_bangla_article_gets_its_real_reading_time(): void
    {
        $article = '<p>'.str_repeat('বাংলাদেশের রাজধানী ঢাকায় আজ বৃষ্টি হয়েছে। ', 100).'</p>'; // 600 words

        $this->assertSame(3, falcon_reading_time($this->makeStory($article)));
        $this->assertSame(6, falcon_reading_time($this->makeStory($article), 100));
    }

    public function test_builder_content_is_read_as_its_text_not_its_settings(): void
    {
        $layout = [[
            'type' => 'container',
            'settings' => ['bgColor' => '#ffffff', 'paddingTop' => 40],
            'columns' => [[
                'settings' => ['width' => '100%'],
                'elements' => [
                    ['type' => 'title', 'settings' => ['title' => 'Budget passed', 'fontSize' => 32, 'color' => '#111']],
                    ['type' => 'text_block', 'settings' => ['content' => '<p>'.str_repeat('word ', 450).'</p>', 'fontFamily' => 'Inter']],
                ],
            ]],
        ]];
        $post = $this->makeStory(json_encode($layout));

        $this->assertStringStartsWith('Budget passed word word', falcon_post_plain_text($post));
        $this->assertStringNotContainsString('#ffffff', falcon_post_plain_text($post));
        $this->assertSame(3, falcon_reading_time($post)); // 452 words
    }

    public function test_the_dynamic_source_takes_a_label_speed_and_digits(): void
    {
        $post = $this->makeStory('<p>'.str_repeat('খবর ', 600).'</p>');

        $this->assertSame('3 min read', falcon_resolve_dynamic_value('post_reading_time', $post, falcon_dynamic_config([])));
        $this->assertSame('৩ মিনিট', falcon_resolve_dynamic_value('post_reading_time', $post, falcon_dynamic_config([
            'dynamic_reading_label' => 'মিনিট', 'dynamic_digits' => 'bengali',
        ])));
        $this->assertSame('পড়তে ৬ মিনিট লাগবে', falcon_resolve_dynamic_value('post_reading_time', $post, falcon_dynamic_config([
            'dynamic_reading_label' => 'পড়তে {min} মিনিট লাগবে', 'dynamic_reading_wpm' => 100, 'dynamic_digits' => 'bengali',
        ])));
        $this->assertSame('3 min read', falcon_resolve_tokens('{lazy:post_reading_time}', $post));
    }

    public function test_a_plugin_can_add_a_dynamic_source_with_its_own_options(): void
    {
        add_falcon_filter('falcon_dynamic_sources', function (array $sources) {
            $sources['text'][] = ['key' => 'post_shares', 'label' => 'Shares', 'group' => 'Post', 'sample' => '42 shares',
                'subFields' => [['key' => 'dynamic_shares_net', 'label' => 'Network', 'type' => 'select']]];
            $sources['text'][] = ['key' => 'Bad Key!', 'label' => 'Ignored'];
            $sources['nowhere'][] = ['key' => 'ignored_too'];

            return $sources;
        });
        add_falcon_filter('falcon_dynamic_value', function ($value, $source, $post, $config) {
            return $source === 'post_shares' ? ($config['settings']['dynamic_shares_net'] ?? 'all').':'.$post->id : $value;
        });

        $sources = falcon_extra_dynamic_sources();
        $this->assertSame(['post_shares'], array_column($sources['text'], 'key'));
        $this->assertSame([], $sources['link']);
        $this->assertSame('42 shares', $sources['text'][0]['sample']);

        $post = (object) ['id' => 7, 'content' => ''];
        $this->assertSame('x:7', falcon_resolve_dynamic_value('post_shares', $post, falcon_dynamic_config(['dynamic_shares_net' => 'x'])));
        $this->assertSame('all:7', falcon_resolve_tokens('{lazy:post_shares}', $post));
        // A source nobody answers still shows the element's fallback.
        $this->assertSame('—', falcon_resolve_dynamic_value('post_nobody', $post, ['fallback' => '—']));
    }
}
