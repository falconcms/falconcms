<?php

namespace FalconSeo;

use FalconCms\Core\Models\Post;
use FalconSeo\Http\Middleware\SearchVisibilityMiddleware;
use FalconSeo\Support\AiCrawlers;
use FalconSeo\Support\AvifImages;
use FalconSeo\Support\Cloudflare;
use FalconSeo\Support\IndexNow;
use FalconSeo\Support\KeywordDemand;
use FalconSeo\Support\NotFoundLog;
use FalconSeo\Support\ScoreCell;
use FalconSeo\Support\SearchVisibility;
use FalconSeo\Support\SiteScan;
use FalconSeo\Support\SpeculativeLoading;
use FalconSeo\Support\StructuredData;
use FalconSeo\Support\WebVitals;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Falcon SEO's entry point, registered only while the plugin is active.
 *
 * Everything the plugin does reaches the CMS through hooks the core fires (robots.txt, the
 * page head, the HTML optimizer, the media library, the settings screens, the editor's SEO
 * panel). With the plugin off nobody answers them and the core does its basic SEO alone.
 */
class SeoServiceProvider extends ServiceProvider
{
    /** Classes that lived in the CMS core before they moved here, by their old name. */
    private const MOVED_CLASSES = [
        'FalconCms\\Core\\Support\\AiCrawlers' => AiCrawlers::class,
        'FalconCms\\Core\\Support\\AvifImages' => AvifImages::class,
        'FalconCms\\Core\\Support\\Cloudflare' => Cloudflare::class,
        'FalconCms\\Core\\Support\\IndexNow' => IndexNow::class,
        'FalconCms\\Core\\Support\\KeywordDemand' => KeywordDemand::class,
        'FalconCms\\Core\\Support\\KeywordSuggestions' => Support\KeywordSuggestions::class,
        'FalconCms\\Core\\Support\\LinkSuggestions' => Support\LinkSuggestions::class,
        'FalconCms\\Core\\Support\\NotFoundLog' => NotFoundLog::class,
        'FalconCms\\Core\\Support\\SearchVisibility' => SearchVisibility::class,
        'FalconCms\\Core\\Support\\SeoAudit' => Support\SeoAudit::class,
        'FalconCms\\Core\\Support\\SpeculativeLoading' => SpeculativeLoading::class,
        'FalconCms\\Core\\Support\\StructuredData' => StructuredData::class,
        'FalconCms\\Core\\Support\\WebVitals' => WebVitals::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\BulkSeoController' => Http\Controllers\Admin\BulkSeoController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\KeywordResearchController' => Http\Controllers\Admin\KeywordResearchController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\SeoAuditController' => Http\Controllers\Admin\SeoAuditController::class,
        'FalconCms\\Core\\Http\\Controllers\\Admin\\WebVitalsController' => Http\Controllers\Admin\WebVitalsController::class,
        'FalconCms\\Core\\Http\\Middleware\\SearchVisibilityMiddleware' => SearchVisibilityMiddleware::class,
    ];

    public function register(): void
    {
        // Code written against the old names (a theme, a site's own code) keeps working.
        foreach (self::MOVED_CLASSES as $old => $new) {
            if (!class_exists($old, false)) {
                class_alias($new, $old);
            }
        }
    }

    public function boot(): void
    {
        $this->bootSearchOutput();
        $this->bootSpeed();
        $this->bootAdmin();
        $this->bootSiteScan();
    }

    /** Every page's score: checked in the background, shown in the posts list and in Site Analysis. */
    private function bootSiteScan(): void
    {
        // A page that changed is checked again on the next run; its last score stays on show.
        Post::saved(fn (Post $post) => SiteScan::markChanged((int) $post->id));
        add_falcon_action('falcon_admin_list_seo_cell', [ScoreCell::class, 'render']);

        if ($this->app->runningInConsole()) {
            $this->commands([Console\SeoScanCommand::class]);
        }
        // A few pages an hour: a large site fills in over a day without ever loading the server.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('falcon:seo-scan --limit=10')->hourly()->withoutOverlapping()->runInBackground();
        });
    }

    /** What search engines see: visibility, robots, hreflang, structured data, IndexNow, 404s. */
    private function bootSearchOutput(): void
    {
        // Announce published, changed and removed pages to IndexNow (off unless turned on).
        IndexNow::register();

        // "Hide this site from search engines": noindex everywhere, no sitemap, no IndexNow.
        $this->app['router']->pushMiddlewareToGroup('web', SearchVisibilityMiddleware::class);
        add_falcon_filter('falcon_site_hidden_from_search', fn ($hidden) => $hidden || SearchVisibility::hidden());

        add_falcon_filter('falcon_robots_txt', function ($content) {
            $content = rtrim((string) $content).AiCrawlers::rules((string) $content);
            // Hidden from search engines: crawling stays allowed (Google has to fetch a page to see
            // its noindex), only the sitemap pointer goes, since the sitemap is withdrawn.
            if (SearchVisibility::hidden()) {
                $content = trim(preg_replace('/^\s*Sitemap:.*$\R?/mi', '', $content) ?? $content);
            }

            return $content;
        });

        // Lets Google show a large image (Discover, results): it needs this and a 1200px+ image.
        add_falcon_filter('falcon_seo_robots', function ($directives) {
            if (get_cms_option('seo_large_image_preview', '1') === '0') {
                return $directives;
            }

            return ['index', in_array('nofollow', (array) $directives, true) ? 'nofollow' : 'follow',
                'max-image-preview:large', 'max-snippet:-1', 'max-video-preview:-1'];
        });

        add_falcon_action('falcon_seo_head_links', function ($post = null, $noindex = false) {
            if ($noindex) {
                return;
            }
            foreach (falcon_hreflang_links($post) as $hreflang => $alternate) {
                echo '<link rel="alternate" hreflang="'.e($hreflang).'" href="'.e($alternate).'">'."\n";
            }
        });

        // The organization, the site, this page and its author, as one linked graph.
        add_falcon_filter('falcon_seo_schema', fn ($schema, $page = []) => StructuredData::graph((array) $page));

        // Remember addresses that turned out not to exist, for SEO → Redirects.
        add_falcon_action('falcon_response_sent', [NotFoundLog::class, 'observe']);
        add_falcon_action('falcon_redirect_saved', fn ($from) => NotFoundLog::forget((string) $from));
        add_falcon_action('falcon_redirects_screen', function () {
            echo view('falcon-seo::admin.partials.not-found-log')->render();
        });
    }

    /** AVIF copies, speculative loading, Web Vitals and Cloudflare purge. */
    private function bootSpeed(): void
    {
        add_falcon_filter('falcon_optimize_html', function ($html) {
            $html = (string) $html;
            if (AvifImages::enabled()) {
                $html = AvifImages::pictures($html);
            }
            if (SpeculativeLoading::enabled() && ($head = stripos($html, '</head>')) !== false && stripos($html, 'type="speculationrules"') === false) {
                $html = substr_replace($html, SpeculativeLoading::script(), $head, 0);
            }
            // Real visitors' Web Vitals: a few hundred bytes before </body>, sent as the visitor leaves.
            if (WebVitals::enabled() && ($body = strripos($html, '</body>')) !== false && !str_contains($html, '/falcon-vitals')) {
                $html = substr_replace($html, WebVitals::script(), $body, 0);
            }

            return $html;
        });

        // The media library keeps an AVIF copy next to each image (Customizer → Performance).
        add_falcon_filter('falcon_media_image_stored', fn ($made, $path = '') => AvifImages::generate((string) $path) || $made);
        add_falcon_action('falcon_media_file_deleted', fn ($path) => AvifImages::delete((string) $path));
        add_falcon_action('falcon_media_library_actions', function () {
            echo view('falcon-seo::admin.partials.media-avif')->render();
        });

        add_falcon_filter('falcon_customizer_sections', [self::class, 'performanceFields']);

        // Cloudflare's copy of the pages goes whenever the CMS empties its own page cache.
        add_falcon_action('falcon_page_cache_cleared', [Cloudflare::class, 'purgeSoon']);
        add_falcon_action('falcon_integrations_settings_form_bottom', function () {
            echo view('falcon-seo::admin.partials.integrations-cloudflare')->render();
        });
        add_falcon_action('falcon_integrations_settings_after_form', function () {
            echo view('falcon-seo::admin.partials.integrations-cloudflare-purge')->render();
        });
        add_falcon_action('falcon_integrations_settings_saved', [self::class, 'saveCloudflare']);
    }

    /** Settings → SEO sections and the editor's SEO tabs. */
    private function bootAdmin(): void
    {
        add_falcon_action('falcon_seo_settings_form_top', function () {
            echo view('falcon-seo::admin.partials.settings-visibility')->render();
        });
        add_falcon_action('falcon_seo_settings_sections', function ($settings = []) {
            echo view('falcon-seo::admin.partials.settings-sections', ['settings' => (array) $settings])->render();
        });
        add_falcon_filter('falcon_seo_settings_checkboxes', fn ($boxes) => array_merge((array) $boxes, [
            'indexnow_enabled', 'seo_large_image_preview', 'ai_block_training', 'ai_block_search', 'seo_404_log', 'seo_hide_site', 'keyword_autocomplete',
        ]));
        add_falcon_filter('falcon_seo_settings_data', [self::class, 'encryptBingKey']);

        add_falcon_action('falcon_seo_metabox_tabs', function ($post = null) {
            echo view('falcon-seo::admin.partials.editor-tabs', ['post' => $post])->render();
        });
        add_falcon_action('falcon_seo_metabox_panels', function ($post = null) {
            echo view('falcon-seo::admin.partials.editor-analysis', ['post' => $post])->render();
            echo view('falcon-seo::admin.partials.editor-panels', ['post' => $post])->render();
        });
    }

    /**
     * Settings → SEO save: the Bing key is stored encrypted, and an empty field keeps the one
     * already saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function encryptBingKey($data, ?Request $request = null): array
    {
        $data = (array) $data;
        $bingKey = trim((string) ($data[KeywordDemand::BING_KEY] ?? ''));
        $remove = $request ? $request->boolean('bing_webmaster_api_key_remove') : !empty($data['bing_webmaster_api_key_remove']);
        unset($data[KeywordDemand::BING_KEY], $data['bing_webmaster_api_key_remove']);
        if ($remove) {
            $data[KeywordDemand::BING_KEY] = '';
        } elseif ($bingKey !== '') {
            $data[KeywordDemand::BING_KEY] = Crypt::encryptString($bingKey);
        }

        return $data;
    }

    /** Settings → Integrations save: Cloudflare purge. The token is stored encrypted, and an empty field keeps it. */
    public static function saveCloudflare(Request $request): void
    {
        $settings = [Cloudflare::ENABLED => $request->boolean(Cloudflare::ENABLED) ? '1' : '0',
            Cloudflare::ZONE => trim((string) $request->input(Cloudflare::ZONE, ''))];
        $token = trim((string) $request->input(Cloudflare::TOKEN, ''));
        if ($request->boolean('cloudflare_api_token_remove')) {
            $settings[Cloudflare::TOKEN] = '';
        } elseif ($token !== '') {
            $settings[Cloudflare::TOKEN] = Crypt::encryptString($token);
        }
        foreach ($settings as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }
    }

    /**
     * Customizer → Performance: the plugin's switches, right after "Optimise Images".
     *
     * @param  array<string, array<string, mixed>>  $sections
     * @return array<string, array<string, mixed>>
     */
    public static function performanceFields($sections): array
    {
        $sections = (array) $sections;
        if (!isset($sections['performance']['fields']) || !is_array($sections['performance']['fields'])) {
            return $sections;
        }
        $ours = [
            'perf_avif' => [
                'type' => 'toggle',
                'label' => 'Serve AVIF Images',
                'desc' => 'Library images also get an AVIF copy (usually 20-30% smaller than WebP), offered to browsers that support it; others keep the original. New uploads get one automatically; for images already in the library, press Media → Generate AVIF copies once (originals are not changed). Needs PHP GD with AVIF support.',
                'default' => '0',
                // Locked off where PHP's GD cannot write AVIF: it would be "on" and do nothing.
                'unavailable' => AvifImages::supported() ? '' : 'Not available on this server: its PHP (GD) cannot write AVIF images. Ask your host to enable AVIF in GD.',
            ],
            'perf_speculative_loading' => [
                'type' => 'toggle',
                'label' => 'Instant Page Loads (Speculation Rules)',
                'desc' => 'The next page starts loading the moment a visitor presses a link, so it opens almost instantly in Chrome and Edge (other browsers are unaffected). Never for the admin, login, cart, checkout or account pages. The same feature WordPress ships in core.',
                'default' => '0',
            ],
            'perf_rum' => [
                'type' => 'toggle',
                'label' => 'Measure Real Visitor Speed (Web Vitals)',
                'desc' => 'Measures how fast each page loads and responds for your real visitors (LCP, INP, CLS) and shows it under Analytics → Web Vitals, by page, for mobile and desktop. A tiny script with no outside service; it reports as the visitor leaves the page, so it never slows the page down. No personal data is stored.',
                'default' => '0',
            ],
        ];

        $fields = [];
        foreach ($sections['performance']['fields'] as $key => $field) {
            $fields[$key] = $field;
            if ($key === 'perf_lazy_images') {
                $fields += $ours;
            }
        }
        $sections['performance']['fields'] = $fields + $ours;

        return $sections;
    }
}
