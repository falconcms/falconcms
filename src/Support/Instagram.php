<?php

namespace FalconCms\Core\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The connected Instagram account and its latest posts, for the Instagram Feed builder element
 * (a Pro element: builder_pro). Connected under Appearance → Customizer → Instagram.
 *
 * Uses the Instagram API with Instagram Login (graph.instagram.com) with a long-lived access
 * token from a Business or Creator account. The posts are fetched in the background and kept
 * in a file, so a page never waits on Instagram: when the copy is older than the cache time,
 * the page is served from it and a fresh copy is fetched after the response (and hourly by
 * falcon:instagram-sync). If Instagram is down, or the token lapses, the last good copy keeps
 * showing.
 *
 * Pictures are saved on this site by default. Instagram's own image addresses are signed and
 * expire after some days, and loading them asks the reader's browser to call Meta.
 */
final class Instagram
{
    private const API = 'https://graph.instagram.com';

    /** Posts fetched per sync — more than any feed shows, so changing the count needs no fetch. */
    public const FETCH = 30;

    /** The licence feature the element needs. */
    public const FEATURE = 'builder_pro';

    private const DISK_DIR = 'falcon-instagram';

    /** How long a picture of a post that left the feed is kept (see prunePictures). */
    private const RETIRE_SECONDS = 172800;

    /** Option key => default. */
    public const OPTIONS = [
        'instagram_token' => '',
        'instagram_token_refreshed_at' => '',
        'instagram_account' => '',
        'instagram_cache_minutes' => '60',
        'instagram_local_images' => '1',
        'instagram_last_error' => '',
        'instagram_synced_at' => '',
    ];

    /** Pictures waiting out that time, as prunePictures() leaves them for the data file. */
    private static array $retired = [];

    public static function licensed(): bool
    {
        return function_exists('falcon_pro') && falcon_pro(self::FEATURE);
    }

    // ── account ─────────────────────────────────────────────────────────

    public static function connected(): bool
    {
        return self::token() !== '';
    }

    public static function token(): string
    {
        $stored = self::option('instagram_token');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable $e) {
            return '';
        }
    }

    /** @return array<string, mixed> */
    public static function account(): array
    {
        $account = json_decode(self::option('instagram_account'), true);

        return is_array($account) ? $account : [];
    }

    public static function lastError(): string
    {
        return self::option('instagram_last_error');
    }

    public static function syncedAt(): ?Carbon
    {
        $at = self::option('instagram_synced_at');
        try {
            return $at !== '' ? Carbon::parse($at) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Check a token against Instagram and keep it. Throws with Instagram's own message when it
     * is refused, so the Customizer can say why.
     *
     * @return array<string, mixed> the account
     */
    public static function connect(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Paste an access token first.');
        }

        $account = self::fetchAccount($token);

        self::save([
            'instagram_token' => Crypt::encryptString($token),
            'instagram_token_refreshed_at' => now()->toIso8601String(),
            'instagram_account' => json_encode($account, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'instagram_last_error' => '',
        ]);

        self::sync();

        return $account;
    }

    /** Forget the token, the account and the saved posts and pictures. */
    public static function disconnect(): void
    {
        self::save([
            'instagram_token' => '',
            'instagram_token_refreshed_at' => '',
            'instagram_account' => '',
            'instagram_last_error' => '',
            'instagram_synced_at' => '',
        ]);
        File::delete(self::dataFile());
        try {
            Storage::disk('public')->deleteDirectory(self::DISK_DIR);
        } catch (Throwable $e) {
        }
    }

    // ── posts ───────────────────────────────────────────────────────────

    /**
     * The saved posts, newest first. Never calls Instagram while the page waits: a copy older
     * than the cache time is refreshed after the response.
     *
     * @return list<array{id: string, caption: string, type: string, image: string, permalink: string, timestamp: ?string}>
     */
    public static function media(): array
    {
        $data = self::data();
        if (self::connected() && self::licensed() && self::stale()) {
            self::refreshLater();
        }

        return is_array($data['media'] ?? null) ? $data['media'] : [];
    }

    public static function stale(): bool
    {
        $synced = self::syncedAt();
        if ($synced === null) {
            return true;
        }
        $minutes = max(5, min(1440, (int) self::option('instagram_cache_minutes')));

        return $synced->copy()->addMinutes($minutes)->isPast();
    }

    /**
     * Fetch the account and its latest posts now, save the pictures, and keep the copy. On a
     * failure the previous copy stays and the error is recorded for the Customizer.
     */
    public static function sync(): bool
    {
        $token = self::token();
        if ($token === '') {
            return false;
        }

        try {
            self::refreshTokenIfDue($token);
            $token = self::token();

            $account = self::fetchAccount($token);
            $response = Http::timeout(20)->acceptJson()->get(self::API.'/me/media', [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
                'limit' => self::FETCH,
                'access_token' => $token,
            ]);
            self::throwIfFailed($response);

            $local = self::option('instagram_local_images') === '1';
            $keep = [];
            $media = [];
            foreach ((array) $response->json('data', []) as $row) {
                $item = self::normalize((array) $row);
                if ($item === null) {
                    continue;
                }
                if ($local) {
                    $saved = self::saveImage($item['id'], $item['image']);
                    if ($saved !== null) {
                        $item['image'] = $saved['url'];
                        $keep[] = $saved['path'];
                    }
                }
                $media[] = $item;
            }

            self::$retired = [];
            if ($local) {
                $account['profile_picture_url'] = self::saveAvatar((string) ($account['profile_picture_url'] ?? ''), $keep);
                self::prunePictures($keep);
            }

            File::ensureDirectoryExists(dirname(self::dataFile()));
            File::put(self::dataFile(), json_encode([
                'account' => $account, 'media' => $media, 'synced_at' => now()->toIso8601String(), 'retired' => self::$retired,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            self::save([
                'instagram_account' => json_encode($account, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'instagram_synced_at' => now()->toIso8601String(),
                'instagram_last_error' => '',
            ]);

            return true;
        } catch (Throwable $e) {
            self::save([
                'instagram_last_error' => mb_substr($e->getMessage(), 0, 500),
                // Wait a full cache period before trying again, rather than on every page.
                'instagram_synced_at' => now()->toIso8601String(),
            ]);

            return false;
        }
    }

    // ── token ───────────────────────────────────────────────────────────

    /**
     * A long-lived token lasts 60 days and can be renewed once it is a day old. Renewed weekly,
     * so a site that is visited (or whose scheduler runs) never lets it lapse.
     */
    public static function refreshTokenIfDue(?string $token = null): void
    {
        $token ??= self::token();
        if ($token === '') {
            return;
        }
        $last = self::option('instagram_token_refreshed_at');
        try {
            if ($last !== '' && Carbon::parse($last)->addDays(7)->isFuture()) {
                return;
            }
        } catch (Throwable $e) {
        }

        $response = Http::timeout(20)->acceptJson()->get(self::API.'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]);
        if ($response->successful() && is_string($response->json('access_token')) && $response->json('access_token') !== '') {
            self::save([
                'instagram_token' => Crypt::encryptString($response->json('access_token')),
                'instagram_token_refreshed_at' => now()->toIso8601String(),
            ]);
        }
        // A refusal is not fatal here: the token may simply be too new to renew. The media call
        // that follows says whether it still works.
    }

    /**
     * The canonical address of an Instagram post or reel, for Instagram's own embed, or null
     * when the link is not one: https://www.instagram.com/p/ABC123/. Accepts what Share → Copy
     * link gives (with ?igsh=… and the like), a username in the path, and instagr.am.
     */
    public static function embedPermalink(string $url): ?string
    {
        $url = trim($url);
        if (!preg_match('#^(?:https?://)?(?:www\.|m\.)?(?:instagram\.com|instagr\.am)/(?:[A-Za-z0-9_.]+/)?(p|reel|reels|tv)/([A-Za-z0-9_-]{5,40})#i', $url, $m)) {
            return null;
        }
        $kind = strtolower($m[1]) === 'reels' ? 'reel' : strtolower($m[1]);

        return 'https://www.instagram.com/'.$kind.'/'.$m[2].'/';
    }

    /** A follower count as people write it: 950, 15.4K, 2.1M. */
    public static function shortNumber(int $n): string
    {
        foreach ([1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'K'] as $size => $suffix) {
            if ($n >= $size) {
                $value = $n / $size;
                $text = number_format($value, $value < 100 ? 1 : 0, '.', '');

                return (str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text).$suffix;
            }
        }

        return (string) $n;
    }

    // ── internals ───────────────────────────────────────────────────────

    private static function option(string $key): string
    {
        return (string) get_cms_option($key, self::OPTIONS[$key] ?? '');
    }

    /** @param  array<string, string>  $values */
    private static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            update_cms_option($key, (string) $value);
        }
    }

    /** @return array<string, mixed> */
    private static function fetchAccount(string $token): array
    {
        $response = Http::timeout(20)->acceptJson()->get(self::API.'/me', [
            'fields' => 'user_id,username,name,profile_picture_url,followers_count,media_count',
            'access_token' => $token,
        ]);
        // Fewer fields for a token whose account type does not offer them all.
        if (!$response->successful()) {
            $retry = Http::timeout(20)->acceptJson()->get(self::API.'/me', ['fields' => 'id,username', 'access_token' => $token]);
            if ($retry->successful()) {
                $response = $retry;
            }
        }
        self::throwIfFailed($response);

        $username = (string) $response->json('username', '');
        if ($username === '') {
            throw new RuntimeException('Instagram did not return an account for this token.');
        }

        return [
            'id' => (string) ($response->json('user_id') ?? $response->json('id') ?? ''),
            'username' => $username,
            'name' => (string) $response->json('name', ''),
            'profile_picture_url' => (string) $response->json('profile_picture_url', ''),
            'followers_count' => $response->json('followers_count') !== null ? (int) $response->json('followers_count') : null,
            'media_count' => $response->json('media_count') !== null ? (int) $response->json('media_count') : null,
        ];
    }

    private static function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }
        $message = (string) ($response->json('error.message') ?? '');
        throw new RuntimeException($message !== '' ? 'Instagram: '.$message : 'Instagram answered with HTTP '.$response->status().'.');
    }

    /** @return array{id: string, caption: string, type: string, image: string, permalink: string, timestamp: ?string}|null */
    private static function normalize(array $row): ?array
    {
        $type = strtoupper((string) ($row['media_type'] ?? 'IMAGE'));
        // A video's picture is its thumbnail; media_url would be the video file.
        $image = (string) ($type === 'VIDEO' ? ($row['thumbnail_url'] ?? '') : ($row['media_url'] ?? ''));
        if (empty($row['id']) || $image === '') {
            return null;
        }

        return [
            'id' => (string) preg_replace('/[^0-9A-Za-z_]/', '', (string) $row['id']),
            'caption' => (string) ($row['caption'] ?? ''),
            'type' => in_array($type, ['IMAGE', 'VIDEO', 'CAROUSEL_ALBUM'], true) ? $type : 'IMAGE',
            'image' => $image,
            'permalink' => (string) ($row['permalink'] ?? ''),
            'timestamp' => isset($row['timestamp']) ? (string) $row['timestamp'] : null,
        ];
    }

    /** @return array{path: string, url: string}|null */
    private static function saveImage(string $id, string $remote): ?array
    {
        $path = self::DISK_DIR.'/'.$id.'.jpg';
        try {
            $disk = Storage::disk('public');
            if (!$disk->exists($path)) {
                $response = Http::timeout(20)->get($remote);
                if (!$response->successful() || !str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                    return null;
                }
                $disk->put($path, $response->body());
            }

            return ['path' => $path, 'url' => self::publicUrl($path)];
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @param  list<string>  $keep */
    private static function saveAvatar(string $remote, array &$keep): string
    {
        if ($remote === '') {
            return '';
        }
        $path = self::DISK_DIR.'/avatar-'.substr(sha1($remote), 0, 12).'.jpg';
        try {
            $disk = Storage::disk('public');
            if (!$disk->exists($path)) {
                $response = Http::timeout(20)->get($remote);
                if (!$response->successful() || !str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                    return $remote;
                }
                $disk->put($path, $response->body());
            }
            $keep[] = $path;

            return self::publicUrl($path);
        } catch (Throwable $e) {
            return $remote;
        }
    }

    /**
     * Remove saved pictures of posts no longer in the feed — two days after they left it, not
     * at once: the page cache keeps a page for a day, and a cached page still showing a post
     * that dropped out must not show a broken picture.
     *
     * @param  list<string>  $keep
     */
    private static function prunePictures(array $keep): void
    {
        $retired = self::data()['retired'] ?? [];
        $retired = is_array($retired) ? $retired : [];
        try {
            $disk = Storage::disk('public');
            foreach ($disk->files(self::DISK_DIR) as $file) {
                if (in_array($file, $keep, true)) {
                    unset($retired[$file]);

                    continue;
                }
                $since = (int) ($retired[$file] ?? 0);
                if ($since === 0) {
                    $retired[$file] = now()->getTimestamp();
                } elseif ($since < now()->getTimestamp() - self::RETIRE_SECONDS) {
                    $disk->delete($file);
                    unset($retired[$file]);
                }
            }
        } catch (Throwable $e) {
        }
        self::$retired = $retired;
    }

    /** A path on the public disk as an address relative to the site, so it survives a domain move. */
    private static function publicUrl(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    private static function refreshLater(): void
    {
        $memo = falcon_request_memo('instagram_feed');
        if ($memo->offsetExists('queued')) {
            return;
        }
        $memo['queued'] = true;

        app()->terminating(function () {
            try {
                $lock = Cache::lock('instagram_feed_sync', 120);
                if ($lock->get()) {
                    try {
                        self::sync();
                    } finally {
                        $lock->release();
                    }
                }
            } catch (Throwable $e) {
            }
        });
    }

    /** @return array<string, mixed> */
    private static function data(): array
    {
        $file = self::dataFile();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) File::get($file), true);

        return is_array($data) ? $data : [];
    }

    private static function dataFile(): string
    {
        return storage_path('app/falcon-instagram/instagram.json');
    }
}
