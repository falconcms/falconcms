<?php

namespace FalconCms\Core\Support;

use App\Models\User;
use FalconCms\Core\Http\Controllers\Admin\FalconBuilderController;
use FalconCms\Core\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * The review workflow for posts, pages, products and custom post types.
 *
 * Writing and publishing are separate permissions. A user who can write a section but does not
 * hold publish_content works on drafts and sends them for review; somebody who holds it approves
 * them or returns them with a note. The rule is enforced on the model, so no editor, bulk action,
 * API call or plugin can publish around it.
 */
final class Editorial
{
    public const PERMISSION = 'publish_content';

    public const REVIEWS = 'cms_post_reviews';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    public const WITHDRAWN = 'withdrawn';

    public const CHANGES_SUBMITTED = 'changes_submitted';

    public const CHANGES_APPROVED = 'changes_approved';

    public const CHANGES_RETURNED = 'changes_returned';

    public const CHANGES_DISCARDED = 'changes_discarded';

    /** A note the next status change should carry into the review history. */
    private static ?string $note = null;

    public static function ready(): bool
    {
        // Only a yes is remembered: an update migrates in the same process that first asked.
        $memo = falcon_request_memo('editorial_ready');
        if (!empty($memo['ready'])) {
            return true;
        }

        try {
            return $memo['ready'] = Schema::hasTable(self::REVIEWS);
        } catch (Throwable $e) {
            return false;
        }
    }

    // ── who may do what ─────────────────────────────────────────────────

    /** Until the migration has run nothing is restricted, so an update cannot lock a site out. */
    public static function canPublish($user = null): bool
    {
        if (!self::ready()) {
            return true;
        }

        $user ??= auth()->user();

        return $user && $user->hasPermission(self::PERMISSION);
    }

    /** Layout sections (header, footer and so on) belong to the Layout Builder, not this flow. */
    public static function exempt(string $type): bool
    {
        return in_array($type, FalconBuilderController::SECTION_TYPES, true);
    }

    /**
     * Whether the user may change this item. Without publish_content, what is live or scheduled
     * is out of reach: saving over it would put unreviewed work in front of readers.
     */
    public static function canChange(Post $post, $user = null): bool
    {
        return self::exempt((string) $post->type)
            || self::canPublish($user)
            || in_array($post->status, ['draft', 'pending'], true);
    }

    /** Whether the user may write this post type: its Add New permission, as the editors check. */
    public static function canWriteType(string $type, $user = null): bool
    {
        $user ??= auth()->user();
        if (!$user) {
            return false;
        }

        $decided = (new MenuAccess)->canWriteType($user, $type);
        if ($decided !== null) {
            return $decided;
        }

        foreach (array_unique([$type, Str::plural($type)]) as $name) {
            foreach (['manage_', 'access_', 'access_all_'] as $prefix) {
                if ($user->hasPermission($prefix.$name)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ── on the model ────────────────────────────────────────────────────

    /** Before a save: going live without the permission becomes a request for review instead. */
    public static function gate(Post $post): void
    {
        if (!in_array($post->status, ['published', 'scheduled'], true)
            || ($post->exists && !$post->isDirty('status'))
            || !request()->is('admin/*', 'api/*')
            || self::exempt((string) $post->type)
            || !auth()->check()
            || self::canPublish()) {
            return;
        }

        $post->status = 'pending';
    }

    /** After a save: write the change into the review history and tell whoever needs to know. */
    public static function recordTransition(Post $post): void
    {
        if (!self::ready() || self::exempt((string) $post->type)) {
            return;
        }

        $from = $post->getOriginal('status');
        $to = $post->status;
        if ($from === $to) {
            return;
        }

        $action = match (true) {
            $to === 'pending' => self::SUBMITTED,
            $from !== 'pending' => null,
            in_array($to, ['published', 'scheduled'], true) => self::APPROVED,
            $to === 'draft' => self::lastSubmitter($post) === auth()->id() ? self::WITHDRAWN : self::RETURNED,
            default => null,
        };
        if ($action === null) {
            return;
        }

        $note = self::$note;
        self::$note = null;

        try {
            DB::table(self::REVIEWS)->insert([
                'post_id' => $post->id,
                'user_id' => auth()->id(),
                'action' => $action,
                'note' => $note,
                'created_at' => now(),
            ]);

            if (function_exists('falcon_log_activity')) {
                falcon_log_activity('updated', self::activityText($action, (string) $post->title), $post);
            }

            do_falcon_action('falcon_post_review_changed', $post, $action, $note);
            self::notify($post, $action, $note);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** A step in the life of an edit to live content: into the history, and to whoever needs it. */
    public static function recordChange(Post $post, string $action, ?string $note = null, ?int $writer = null): void
    {
        try {
            DB::table(self::REVIEWS)->insert([
                'post_id' => $post->id,
                'user_id' => auth()->id(),
                'action' => $action,
                'note' => $note,
                'created_at' => now(),
            ]);

            if (function_exists('falcon_log_activity')) {
                falcon_log_activity('updated', self::activityText($action, (string) $post->title), $post);
            }

            do_falcon_action('falcon_post_review_changed', $post, $action, $note);
            self::notify($post, $action, $note, $writer);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Give the next status change a note, then make the change. */
    public static function withNote(?string $note, callable $change): void
    {
        self::$note = $note !== null && trim($note) !== '' ? trim($note) : null;
        try {
            $change();
        } finally {
            self::$note = null;
        }
    }

    private static function activityText(string $action, string $title): string
    {
        return match ($action) {
            self::SUBMITTED => "Submitted for review: {$title}",
            self::APPROVED => "Approved: {$title}",
            self::RETURNED => "Returned for changes: {$title}",
            self::CHANGES_SUBMITTED => "Sent changes for review: {$title}",
            self::CHANGES_APPROVED => "Approved changes: {$title}",
            self::CHANGES_RETURNED => "Returned changes: {$title}",
            self::CHANGES_DISCARDED => "Discarded changes: {$title}",
            default => "Withdrawn from review: {$title}",
        };
    }

    // ── reading ─────────────────────────────────────────────────────────

    public static function label(?string $status): string
    {
        return $status === 'pending' ? 'Pending Review' : ucfirst((string) $status);
    }

    /** How many items wait for review, for the admin bar. */
    public static function pendingCount(): int
    {
        if (!self::ready()) {
            return 0;
        }

        $sections = FalconBuilderController::SECTION_TYPES;

        return Post::query()->where('status', 'pending')->whereNotIn('type', $sections)->count()
            + PostChanges::pendingCount();
    }

    /** The newest entry in an item's review history, with who wrote it. */
    public static function lastReview(int $postId): ?object
    {
        if (!self::ready()) {
            return null;
        }

        $row = DB::table(self::REVIEWS)->where('post_id', $postId)->orderByDesc('id')->first();
        if ($row) {
            $user = $row->user_id ? User::query()->find($row->user_id) : null;
            $row->user_name = $user ? ($user->name ?: $user->username) : 'Someone';
        }

        return $row;
    }

    /** The address of an item's editor. */
    public static function editUrl(Post $post): string
    {
        return $post->type === 'page'
            ? route('admin.pages.edit', $post->id)
            : route('admin.posts.edit', $post->id);
    }

    private static function lastSubmitter(Post $post): ?int
    {
        $id = DB::table(self::REVIEWS)->where('post_id', $post->id)->where('action', self::SUBMITTED)
            ->orderByDesc('id')->value('user_id');

        return $id ? (int) $id : ($post->user_id ? (int) $post->user_id : null);
    }

    // ── telling people ──────────────────────────────────────────────────

    /** @return Collection<int, object> Everyone who can publish, with an address to write to. */
    private static function reviewers(): Collection
    {
        $roleIds = DB::table('roles')
            ->leftJoin('role_permission', 'roles.id', '=', 'role_permission.role_id')
            ->leftJoin('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where(fn ($q) => $q->where('permissions.slug', self::PERMISSION)
                ->orWhereIn('roles.slug', ['super-admin', 'administrator', 'admin']))
            ->distinct()
            ->pluck('roles.id');

        return User::query()
            ->where(fn ($q) => $q->whereIn('role_id', $roleIds)
                ->orWhereIn('id', DB::table('role_user')->whereIn('role_id', $roleIds)->select('user_id')))
            ->limit(50)
            ->get(['id', 'name', 'email'])
            ->filter(fn ($u) => !empty($u->email));
    }

    /** After the response, so a slow mail server never holds a save up, and never breaks one. */
    private static function notify(Post $post, string $action, ?string $note, ?int $writer = null): void
    {
        if ((string) get_cms_option('editorial_notify', '1') !== '1') {
            return;
        }

        $actor = auth()->id();
        $title = (string) ($post->title ?: 'Untitled');
        $link = self::editUrl($post);
        $who = auth()->user()?->name ?: 'Someone';

        if ($action === self::SUBMITTED) {
            $recipients = self::reviewers()->reject(fn ($u) => (int) $u->id === (int) $actor);
            $subject = "Review requested: {$title}";
            $body = "{$who} sent \"{$title}\" for review.";
        } elseif ($action === self::CHANGES_SUBMITTED) {
            $recipients = self::reviewers()->reject(fn ($u) => (int) $u->id === (int) $actor);
            $subject = "Changes to review: {$title}";
            $body = "{$who} proposed changes to \"{$title}\". Readers still see the current version.";
            $link = route('admin.posts.changes', $post->id);
        } elseif (in_array($action, [self::CHANGES_APPROVED, self::CHANGES_RETURNED], true)) {
            $recipients = $writer && $writer !== (int) $actor
                ? User::query()->whereKey($writer)->get(['id', 'name', 'email'])->filter(fn ($u) => !empty($u->email))
                : collect();
            $subject = $action === self::CHANGES_APPROVED ? "Changes approved: {$title}" : "Changes requested: {$title}";
            $body = $action === self::CHANGES_APPROVED
                ? "{$who} approved your changes to \"{$title}\". They are live."
                : "{$who} asked you to rework your changes to \"{$title}\". Readers still see the current version.";
        } elseif (in_array($action, [self::APPROVED, self::RETURNED], true)) {
            $writer = self::lastSubmitter($post);
            $recipients = $writer && $writer !== (int) $actor
                ? User::query()->whereKey($writer)->get(['id', 'name', 'email'])->filter(fn ($u) => !empty($u->email))
                : collect();
            $subject = $action === self::APPROVED ? "Approved: {$title}" : "Changes requested: {$title}";
            $body = $action === self::APPROVED
                ? "{$who} approved \"{$title}\"."
                : "{$who} asked for changes to \"{$title}\".";
        } else {
            return;
        }

        if ($note) {
            $body .= "\n\nNote: {$note}";
        }
        $body .= "\n\nOpen it: {$link}";

        $addresses = $recipients->pluck('email')->all();
        if ($addresses === []) {
            return;
        }

        app()->terminating(function () use ($addresses, $subject, $body) {
            foreach ($addresses as $address) {
                try {
                    Mail::raw($body, fn ($message) => $message->to($address)->subject($subject));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
