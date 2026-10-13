<?php

namespace FalconSeo\Support;

/**
 * Two plain choices about AI crawlers, written into robots.txt for the owner:
 *
 *  - keep the site's content out of AI model training;
 *  - keep the site out of AI search answers (ChatGPT search, Claude, Perplexity, Meta AI).
 *
 * Neither touches Google or Bing search: Google-Extended and Applebot-Extended are separate
 * tokens that, per Google and Apple, do not affect search inclusion. The tokens below are the
 * ones each vendor documents (checked October 2026). Fetchers acting for a single user
 * (ChatGPT-User, Perplexity-User, meta-externalfetcher) are left out: their vendors say they
 * may not follow robots.txt, so listing them would promise what robots.txt cannot deliver.
 */
final class AiCrawlers
{
    /** Crawlers that collect content for training AI models. */
    public const TRAINING = [
        'GPTBot' => 'OpenAI model training',
        'Google-Extended' => 'Gemini training (not Google Search)',
        'ClaudeBot' => 'Anthropic model training',
        'Applebot-Extended' => 'Apple Intelligence training (not Apple search)',
        'CCBot' => 'Common Crawl, a dataset many AI models train on',
        'meta-externalagent' => 'Meta AI model training',
    ];

    /** Crawlers that index sites for AI search answers, which link back. */
    public const SEARCH = [
        'OAI-SearchBot' => 'ChatGPT search',
        'Claude-SearchBot' => 'Claude search',
        'PerplexityBot' => 'Perplexity search',
        'meta-webindexer' => 'Meta AI search',
    ];

    public static function blockTraining(): bool
    {
        return (string) get_cms_option('ai_block_training', '0') === '1';
    }

    public static function blockSearch(): bool
    {
        return (string) get_cms_option('ai_block_search', '0') === '1';
    }

    /**
     * The robots.txt groups for what the owner chose, skipping any crawler the owner's own
     * robots.txt already names (theirs wins). Empty when nothing is blocked.
     */
    public static function rules(string $existing = ''): string
    {
        $bots = array_merge(
            self::blockTraining() ? self::TRAINING : [],
            self::blockSearch() ? self::SEARCH : [],
        );

        $groups = [];
        foreach ($bots as $token => $purpose) {
            if (preg_match('/^\s*user-agent:\s*'.preg_quote($token, '/').'\s*$/im', $existing)) {
                continue;
            }
            $groups[] = "# {$purpose}\nUser-agent: {$token}\nDisallow: /";
        }

        return $groups === [] ? '' : "\n\n# AI crawlers (SEO → Settings)\n".implode("\n\n", $groups)."\n";
    }
}
