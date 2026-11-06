<?php

namespace FalconCms\Core\Support;

use App\Models\User;
use FalconCms\Core\Models\Post;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One person edits an item at a time. The open editor renews its lock with a heartbeat; a closed
 * or crashed one simply stops, and the lock lapses. Another editor may take over, and from then on
 * the server refuses the previous holder's saves, so nobody overwrites anybody by accident.
 */
final class PostLock
{
    public const TABLE = 'cms_post_locks';

    /** Long enough to outlast a background tab, whose timers a browser slows to once a minute. */
    public const TTL_SECONDS = 150;

    public const HEARTBEAT_SECONDS = 30;

    public static function ready(): bool
    {
        // Only a yes is remembered: an update migrates in the same process that first asked.
        $memo = falcon_request_memo('post_lock_ready');
        if (!empty($memo['ready'])) {
            return true;
        }

        try {
            return $memo['ready'] = Schema::hasTable(self::TABLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Take or renew the lock. Returns null when the user now holds it, or the person who does.
     *
     * @return array{id: int, name: string}|null
     */
    public static function acquire(int $postId, int $userId, bool $force = false): ?array
    {
        if (!self::ready()) {
            return null;
        }

        $now = now();

        DB::table(self::TABLE)->insertOrIgnore([
            'post_id' => $postId,
            'user_id' => $userId,
            'locked_at' => $now,
            'heartbeat_at' => $now,
        ]);

        // Renewing keeps the original time; taking it from somebody else starts a new one.
        DB::table(self::TABLE)->where('post_id', $postId)->where('user_id', $userId)
            ->update(['heartbeat_at' => $now]);

        DB::table(self::TABLE)->where('post_id', $postId)->where('user_id', '!=', $userId)
            ->where(function ($q) use ($force, $now) {
                $q->where('heartbeat_at', '<', $now->copy()->subSeconds(self::TTL_SECONDS));
                if ($force) {
                    $q->orWhereRaw('1 = 1');
                }
            })
            ->update(['user_id' => $userId, 'locked_at' => $now, 'heartbeat_at' => $now]);

        return self::holder($postId, $userId);
    }

    /**
     * Who else is editing an item right now.
     *
     * @return array{id: int, name: string}|null
     */
    public static function holder(int $postId, ?int $exceptUserId = null): ?array
    {
        return self::holders([$postId], $exceptUserId)[$postId] ?? null;
    }

    /**
     * Who else is editing each of these items, in one query, for the lists.
     *
     * @param  array<int, int>  $postIds
     * @return array<int, array{id: int, name: string}>
     */
    public static function holders(array $postIds, ?int $exceptUserId = null): array
    {
        if ($postIds === [] || !self::ready()) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->whereIn('post_id', $postIds)
            ->where('heartbeat_at', '>=', now()->subSeconds(self::TTL_SECONDS))
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->get(['post_id', 'user_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()->whereIn('id', $rows->pluck('user_id')->unique())->get(['id', 'name', 'username'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->name ?: ($u->username ?: 'Another user')]);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->post_id] = [
                'id' => (int) $row->user_id,
                'name' => (string) ($names[$row->user_id] ?? 'Another user'),
            ];
        }

        return $out;
    }

    public static function release(int $postId, int $userId): void
    {
        if (self::ready()) {
            DB::table(self::TABLE)->where('post_id', $postId)->where('user_id', $userId)->delete();
        }
    }

    /** Stop a save while somebody else holds the lock, and say who. The typed work comes back. */
    public static function guard(Post $post): void
    {
        $holder = self::holder((int) $post->id, auth()->id());
        if (!$holder) {
            return;
        }

        $message = "{$holder['name']} is editing this now, so your change was not saved.";
        $request = request();

        throw new HttpResponseException(
            $request->isJson() || $request->wantsJson() || $request->ajax()
                ? response()->json(['success' => false, 'locked' => true, 'message' => $message], 423)
                : back()->withInput()->withErrors(['lock' => $message])->with('error', $message)
        );
    }

    /**
     * A decision on review stops only for somebody who could overwrite it. A writer left on the
     * editor after sending their work must not hold up the reviewer: whatever they save next is
     * measured against the new version and goes to review itself.
     */
    public static function guardReview(Post $post): void
    {
        $holder = self::holder((int) $post->id, auth()->id());
        if ($holder && Editorial::canPublish(User::query()->find($holder['id']))) {
            self::guard($post);
        }
    }

    /** Daily: drop locks nobody renewed. */
    public static function prune(): int
    {
        return self::ready()
            ? DB::table(self::TABLE)->where('heartbeat_at', '<', now()->subDay())->delete()
            : 0;
    }
}
