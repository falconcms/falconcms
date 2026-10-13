<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconCms\Core\Models\Post;
use FalconSeo\Support\Countries;
use FalconSeo\Support\KeywordDemand;
use FalconSeo\Support\KeywordSuggestions;
use FalconSeo\Support\PageAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Editor → SEO → Analysis: the page's check-up, score and keyword advice. */
class PageAnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        $data = $request->validate([
            'post_id' => 'nullable|integer',
            'mode' => 'nullable|in:live,editor',
            'keyword' => 'nullable|string|max:200',
            'title' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'slug' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:2000000',
            'country' => 'nullable|string|size:2',
            'countries' => 'nullable|array|max:'.count(Countries::ALL),
            'countries.*' => 'string|size:2',
            'check_links' => 'nullable|boolean',
        ]);
        // Several countries may be chosen; the first is the market the keyword advice is for.
        $countries = array_values(array_filter(array_map('strtolower', (array) ($data['countries'] ?? [])), [Countries::class, 'valid']));
        if ($countries === [] && !empty($data['country']) && Countries::valid($data['country'])) {
            $countries = [strtolower($data['country'])];
        }
        $data['country'] = $countries[0] ?? null;
        $post = !empty($data['post_id']) ? Post::find($data['post_id']) : null;
        $keyword = trim((string) ($data['keyword'] ?? ''));
        $context = ['keyword' => $keyword, 'slug' => (string) ($data['slug'] ?? $post?->slug ?? ''), 'check_links' => (bool) ($data['check_links'] ?? true)];

        // The published page as visitors get it, unless the editor's version is asked for or
        // there is no published page to fetch.
        $live = ($data['mode'] ?? 'live') === 'live' && $post ? PageAnalyzer::fetchLive($post) : null;
        if ($live) {
            $result = PageAnalyzer::analyze($live['html'], $context + ['mode' => 'live', 'url' => $live['url']]);
        } else {
            $input = array_map(fn ($v) => (string) $v, array_intersect_key($data, array_flip(['title', 'seo_title', 'description', 'content'])));
            if (trim($input['content'] ?? '') === '' && $post) {
                $input['content'] = (string) $post->content;
            }
            $result = PageAnalyzer::analyze(PageAnalyzer::editorPage($input), $context + [
                'mode' => 'editor', 'url' => $post && $post->status === 'published' ? (string) get_falcon_permalink($post) : url('/'),
            ]);
            $result['notice'] = ($data['mode'] ?? 'live') === 'live' && $post
                ? ($post->status === 'published' ? 'The published page could not be fetched, so the editor\'s content was analysed instead.' : 'Not published yet: the editor\'s content was analysed. The theme\'s own heading, menus and footer are not included.')
                : 'The editor\'s content was analysed (unsaved changes included). The theme\'s own heading, menus and footer are not included.';
        }

        $result['keyword'] = $this->keywordAdvice($keyword, $data, $post);
        $result['keyword']['countries'] = array_map(fn ($c) => ['country' => $c, 'name' => Countries::name($c)], $countries);

        return response()->json($result);
    }

    /**
     * How much the focus keyword is searched, and phrases that may serve the page better: ones
     * searched more, ones Google already shows the page for, and ones the page itself is about.
     */
    private function keywordAdvice(string $keyword, array $data, ?Post $post): array
    {
        $ideas = KeywordSuggestions::for([
            'post_id' => (int) ($post->id ?? 0),
            'title' => (string) ($data['title'] ?? $post->title ?? ''),
            'seo_title' => (string) ($data['seo_title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'slug' => (string) ($data['slug'] ?? $post->slug ?? ''),
            'content' => (string) ($data['content'] ?? ''),
            'keyword' => $keyword,
        ]);
        $demand = $keyword !== '' ? KeywordDemand::lookup($keyword, $data['country'] ?? null) : null;
        $focusVolume = self::volume($demand['volume'] ?? null, $demand['bing'] ?? null);

        $alternatives = [];
        foreach ($demand['related'] ?? [] as $row) {
            $volume = self::volume($row, isset($row['bing']) ? ['impressions' => $row['bing']] : null);
            if ($volume !== null && ($focusVolume === null || $volume > $focusVolume)) {
                $alternatives[$row['query']] = ['phrase' => $row['query'], 'volume' => $volume,
                    'why' => $focusVolume === null ? 'Searched about '.number_format($volume).' times a month' : 'Searched more: about '.number_format($volume).' vs '.number_format($focusVolume)];
            }
        }
        foreach ($ideas['google'] ?? [] as $row) {
            $phrase = (string) ($row['query'] ?? $row['phrase'] ?? '');
            if ($phrase !== '' && $phrase !== mb_strtolower($keyword) && !isset($alternatives[$phrase])) {
                $alternatives[$phrase] = ['phrase' => $phrase, 'volume' => null,
                    'why' => 'Google already shows this page for it'.(isset($row['position']) ? ' (position '.round((float) $row['position'], 1).')' : '')];
            }
        }
        foreach (array_slice($ideas['phrases'] ?? [], 0, 6) as $row) {
            $phrase = (string) ($row['phrase'] ?? '');
            if ($phrase !== '' && $phrase !== mb_strtolower($keyword) && !isset($alternatives[$phrase])) {
                $alternatives[$phrase] = ['phrase' => $phrase, 'volume' => null,
                    'why' => 'What the page is most about: used '.(int) ($row['count'] ?? 0).' time(s), rarely elsewhere on the site'];
            }
        }

        return [
            'keyword' => $keyword,
            'country' => $demand['country'] ?? strtolower((string) get_cms_option(KeywordDemand::COUNTRY, 'us')),
            'sources' => KeywordDemand::sources(),
            'volume' => $focusVolume,
            'planner' => $demand['volume'] ?? null,
            'bing' => $demand['bing'] ?? null,
            'suggestions' => $demand['suggestions'] ?? [],
            'errors' => $demand['errors'] ?? [],
            'alternatives' => array_slice(array_values($alternatives), 0, 10),
        ];
    }

    /** Monthly searches: Google's figure (top of the Keyword Planner range) where there is one, else Bing's. */
    private static function volume(?array $planner, ?array $bing): ?int
    {
        if (isset($planner['searches_high'])) {
            return (int) $planner['searches_high'];
        }

        return isset($bing['impressions']) ? (int) $bing['impressions'] : null;
    }
}
