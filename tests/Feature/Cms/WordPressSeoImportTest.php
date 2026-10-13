<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Redirect;
use FalconCms\Core\Services\WordPressImporter;
use FalconCms\Core\Services\WordPressSeoMeta;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Moving from WordPress keeps the rankings: the SEO plugin's titles, descriptions, keywords and
 * robots settings come across, and every old address redirects to its new one.
 */
class WordPressSeoImportTest extends TestCase
{
    private function item(int $id, string $slug, string $link, string $meta, string $type = 'post', string $title = 'Green tea guide'): string
    {
        return <<<XML
  <item>
    <title>{$title}</title>
    <link>{$link}</link>
    <content:encoded><![CDATA[<p>Text</p>]]></content:encoded>
    <excerpt:encoded><![CDATA[]]></excerpt:encoded>
    <wp:post_id>{$id}</wp:post_id>
    <wp:post_date>2024-05-02 09:00:00</wp:post_date>
    <wp:post_name>{$slug}</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_type>{$type}</wp:post_type>
    {$meta}
  </item>
XML;
    }

    private function meta(array $pairs): string
    {
        return implode('', array_map(fn ($k, $v) => '<wp:postmeta><wp:meta_key>'.$k.'</wp:meta_key><wp:meta_value><![CDATA['.$v.']]></wp:meta_value></wp:postmeta>', array_keys($pairs), $pairs));
    }

    private function wxr(string $items): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>Tea House</title>
  <link>https://old.example</link>
  <wp:base_site_url>https://old.example</wp:base_site_url>
{$items}
</channel>
</rss>
XML;
    }

    public function test_yoast_rank_math_aioseo_and_seopress_are_read(): void
    {
        $yoast = WordPressSeoMeta::fromMeta([
            '_yoast_wpseo_title' => 'Best %%title%% %%sep%% %%sitename%%', '_yoast_wpseo_metadesc' => 'All about green tea &amp; brewing.',
            '_yoast_wpseo_focuskw' => 'green tea', '_yoast_wpseo_meta-robots-noindex' => '1', '_yoast_wpseo_opengraph-image' => 'https://old.example/og.jpg',
        ], 'Green tea guide', 'Tea House');
        $this->assertSame('Best Green tea guide - Tea House', $yoast['title']);
        $this->assertSame('All about green tea & brewing.', $yoast['description']);
        $this->assertSame('green tea', $yoast['focus_keyword']);
        $this->assertSame('1', $yoast['noindex']);
        $this->assertSame('https://old.example/og.jpg', $yoast['og_image']);

        $rank = WordPressSeoMeta::fromMeta(['rank_math_title' => '%title% %sep% %sitename%', 'rank_math_description' => 'Brew it well.',
            'rank_math_focus_keyword' => 'green tea,matcha', 'rank_math_robots' => 'a:2:{i:0;s:5:"index";i:1;s:8:"nofollow";}'], 'Green tea guide', 'Tea House');
        $this->assertArrayNotHasKey('title', $rank, 'a title that is only the default template is left to the CMS');
        $this->assertSame('green tea', $rank['focus_keyword'], 'the first of several keywords');
        $this->assertSame('1', $rank['nofollow']);
        $this->assertArrayNotHasKey('noindex', $rank, '"index" is not "noindex"');

        $aioseo = WordPressSeoMeta::fromMeta(['_aioseo_description' => 'From AIOSEO', '_aioseo_keyphrases' => '{"focus":{"keyphrase":"oolong"}}']);
        $this->assertSame(['description' => 'From AIOSEO', 'focus_keyword' => 'oolong'], $aioseo);

        $seopress = WordPressSeoMeta::fromMeta(['_seopress_titles_title' => 'SEOPress title', '_seopress_robots_index' => 'yes']);
        $this->assertSame(['title' => 'SEOPress title', 'noindex' => '1'], $seopress);

        $this->assertNull(WordPressSeoMeta::fromMeta(['_thumbnail_id' => '5']));
    }

    public function test_an_import_keeps_the_seo_and_redirects_old_addresses(): void
    {
        DB::table('posts')->delete();
        $xml = $this->wxr(
            $this->item(10, 'green-tea-guide', 'https://old.example/2024/05/green-tea-guide/', $this->meta([
                '_yoast_wpseo_title' => 'Green tea: the complete guide', '_yoast_wpseo_metadesc' => 'How to choose and brew green tea.', '_yoast_wpseo_focuskw' => 'green tea',
            ]))
            .$this->item(20, 'about', 'https://old.example/about/', '', 'page', 'About')
            .$this->item(30, 'team', 'https://old.example/about/team/', '', 'page', 'Team')
            .$this->item(40, 'plain', 'https://old.example/?p=40', '')
        );

        $summary = (new WordPressImporter)->importFromXml($xml, ['lang' => 'en']);

        $post = Post::where('slug', 'green-tea-guide')->first();
        $this->assertSame('Green tea: the complete guide', $post->seo_meta['title']);
        $this->assertSame('green tea', $post->seo_meta['focus_keyword']);
        $this->assertSame(1, $summary['seo']);

        // /2024/05/green-tea-guide/ → the post's new address; /about/ is already right; a child page moves up.
        $this->assertSame(parse_url(get_falcon_permalink($post), PHP_URL_PATH), Redirect::where('old_url', '/2024/05/green-tea-guide')->value('new_url'));
        $this->assertSame('/team', Redirect::where('old_url', '/about/team')->value('new_url'));
        $this->assertFalse(Redirect::where('old_url', '/about')->exists());
        $this->assertSame(2, $summary['redirects'], 'no redirect for a ?p= address or one that did not move');

        $this->get('/2024/05/green-tea-guide')->assertRedirect(parse_url(get_falcon_permalink($post), PHP_URL_PATH))->assertStatus(301);
    }

    public function test_importing_again_tops_up_missing_seo_but_keeps_a_hand_made_redirect(): void
    {
        DB::table('posts')->delete();
        Post::forceCreate(['title' => 'Green tea guide', 'slug' => 'green-tea-guide', 'type' => 'post', 'status' => 'published', 'content' => '', 'lang_code' => 'en']);
        Redirect::create(['old_url' => '/2024/05/green-tea-guide', 'new_url' => '/somewhere-else', 'status_code' => 302]);

        $summary = (new WordPressImporter)->importFromXml($this->wxr($this->item(10, 'green-tea-guide', 'https://old.example/2024/05/green-tea-guide/',
            $this->meta(['_yoast_wpseo_metadesc' => 'Kept on the second run.']))), ['lang' => 'en']);

        $this->assertSame('Kept on the second run.', Post::where('slug', 'green-tea-guide')->first()->seo_meta['description']);
        $this->assertSame(1, $summary['seo']);
        $this->assertSame('/somewhere-else', Redirect::where('old_url', '/2024/05/green-tea-guide')->value('new_url'));
    }
}
