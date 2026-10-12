<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * Two choices about AI crawlers, written into robots.txt: no training, and (separately) no
 * AI search. Off by default; Google and Bing search are never affected.
 */
class AiCrawlersTest extends TestCase
{
    public function test_robots_txt_is_unchanged_by_default(): void
    {
        $this->get('/robots.txt')->assertOk()->assertDontSee('GPTBot')->assertDontSee('AI crawlers');
    }

    public function test_blocking_training_adds_the_training_crawlers_only(): void
    {
        $this->setCmsOptions(['ai_block_training' => '1']);

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['GPTBot', 'Google-Extended', 'ClaudeBot', 'Applebot-Extended', 'CCBot', 'meta-externalagent'] as $bot) {
            $this->assertStringContainsString("User-agent: {$bot}\nDisallow: /", $body);
        }
        $this->assertStringNotContainsString('OAI-SearchBot', $body, 'AI search stays allowed');
        $this->assertStringNotContainsString("User-agent: Googlebot\nDisallow", $body, 'Google Search is never blocked');
        $this->assertStringStartsWith('User-agent: *', $body, "the owner's own rules come first");
    }

    public function test_blocking_ai_search_is_a_separate_choice(): void
    {
        $this->setCmsOptions(['ai_block_search' => '1']);

        $body = $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString("User-agent: OAI-SearchBot\nDisallow: /", $body);
        $this->assertStringContainsString("User-agent: PerplexityBot\nDisallow: /", $body);
        $this->assertStringNotContainsString('GPTBot', $body);
    }

    public function test_a_crawler_the_owner_already_named_is_left_to_their_rule(): void
    {
        $this->setCmsOptions(['ai_block_training' => '1', 'robots_txt' => "User-agent: *\nAllow: /\n\nUser-agent: GPTBot\nAllow: /blog/"]);

        $body = $this->get('/robots.txt')->getContent();

        $this->assertSame(1, substr_count($body, 'User-agent: GPTBot'));
        $this->assertStringContainsString("User-agent: ClaudeBot\nDisallow: /", $body);
    }
}
