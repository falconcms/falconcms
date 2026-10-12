<?php

namespace FalconCms\Core\Support;

use FalconCms\Core\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * IndexNow: tell Bing, Yandex, Naver, Seznam and Yep the moment a page is published, changed
 * or removed, instead of waiting for their crawlers. One ping to api.indexnow.org reaches
 * every participating engine. Bing's index also feeds Copilot and ChatGPT search, which is
 * most of the value today; Google does not take part.
 *
 * The site proves it owns the URLs with a key: a random string kept in the options and
 * served at /{key}.txt. URLs are collected while a request saves posts and sent once, after
 * the response has gone out, so an editor never waits on it.
 *
 * Off unless the owner turns it on (SEO settings); never sent from a local or test host.
 */
final class IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** @var array<string, true> URLs waiting for this request's ping */
    private static array $pending = [];

    private static bool $flushScheduled = false;

    public static function enabled(): bool
    {
        return (string) get_cms_option('indexnow_enabled', '0') === '1' && self::publicHost();
    }

    /** The site's key, created the first time it is asked for. */
    public static function key(): string
    {
        $key = (string) get_cms_option('indexnow_key', '');
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            $key = Str::lower(bin2hex(random_bytes(16)));
            update_cms_option('indexnow_key', $key);
        }

        return $key;
    }

    public static function keyUrl(): string
    {
        return url('/'.self::key().'.txt');
    }

    /** Watch posts: published, changed, unpublished, moved or deleted pages are announced. */
    public static function register(): void
    {
        Post::saved(fn (Post $post) => self::postSaved($post));
        Post::deleted(fn (Post $post) => self::postGone($post));
    }

    public static function postSaved(Post $post): void
    {
        if (!self::enabled()) {
            return;
        }
        $wasLive = $post->getOriginal('status') === 'published';
        $isLive = $post->status === 'published';
        if (!$wasLive && !$isLive) {
            return; // drafts coming and going are nobody's business
        }
        if (!$post->wasRecentlyCreated && !$post->wasChanged(['status', 'slug', 'title', 'content', 'seo_meta', 'excerpt', 'featured_image', 'type', 'lang_code'])) {
            return;
        }

        $seo = is_array($post->seo_meta) ? $post->seo_meta : [];
        if ($isLive && ($seo['noindex'] ?? '0') != '1') {
            self::queue(get_falcon_permalink($post));
        }
        // The address it used to have: gone (unpublished) or moved (new slug). Engines should re-check it.
        if ($wasLive && ($post->wasChanged('slug') || $post->wasChanged('type') || !$isLive)) {
            $old = clone $post;
            $old->slug = $post->getOriginal('slug');
            $old->type = $post->getOriginal('type');
            self::queue(get_falcon_permalink($old));
        }
    }

    public static function postGone(Post $post): void
    {
        if (self::enabled() && $post->getOriginal('status') === 'published') {
            self::queue(get_falcon_permalink($post));
        }
    }

    public static function queue(?string $url): void
    {
        if (!$url || $url === '#' || !str_starts_with($url, 'http')) {
            return;
        }
        self::$pending[$url] = true;
        if (!self::$flushScheduled) {
            self::$flushScheduled = true;
            app()->terminating(fn () => self::flush());
        }
    }

    /** Send what is pending in one request. Returns the HTTP status, or null when nothing was sent. */
    public static function flush(): ?int
    {
        $urls = array_keys(self::$pending);
        self::$pending = [];
        self::$flushScheduled = false;
        if ($urls === [] || !self::enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(8)->acceptJson()->post(self::ENDPOINT, [
                'host' => parse_url(url('/'), PHP_URL_HOST),
                'key' => self::key(),
                'keyLocation' => self::keyUrl(),
                'urlList' => array_values(array_slice($urls, 0, 10000)),
            ]);
            $status = $response->status();
        } catch (Throwable $e) {
            $status = 0;
        }

        update_cms_option('indexnow_last', json_encode(['at' => now()->toDateTimeString(), 'urls' => count($urls), 'status' => $status]));

        return $status;
    }

    /** The last ping: ['at', 'urls', 'status'], or null. */
    public static function last(): ?array
    {
        $data = json_decode((string) get_cms_option('indexnow_last', ''), true);

        return is_array($data) ? $data : null;
    }

    /** A plain-language reading of an IndexNow response status. */
    public static function describe(?int $status): string
    {
        return match (true) {
            $status === 200, $status === 202 => 'accepted',
            $status === 403 => 'key not verified yet (the engine could not read the key file)',
            $status === 422 => 'some URLs do not belong to this site',
            $status === 429 => 'too many requests, will retry with the next change',
            $status === 0, $status === null => 'could not reach IndexNow',
            default => 'HTTP '.$status,
        };
    }

    /** Local and test hosts are never announced: the engines cannot reach them anyway. */
    private static function publicHost(): bool
    {
        $host = strtolower((string) parse_url(url('/'), PHP_URL_HOST));

        return $host !== '' && !in_array($host, ['localhost', '127.0.0.1'], true)
            && !str_ends_with($host, '.test') && !str_ends_with($host, '.local') && !str_ends_with($host, '.localhost');
    }

    /** For tests: forget anything queued. */
    public static function reset(): void
    {
        self::$pending = [];
        self::$flushScheduled = false;
    }
}
