<?php

namespace FalconCms\Core\Support;

use App\Models\User;
use FalconCms\Core\Models\Category;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Models\Revision;
use FalconCms\Core\Models\Tag;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Edits to live content that go live after review.
 *
 * A user who can write a section but cannot publish still edits what is live; readers keep seeing
 * the current version until somebody approves. Only the fields that differ are kept, so the
 * reviewer sees exactly what changed, and an unrelated edit made to the post in the meantime
 * survives the approval.
 *
 * Price, stock, custom fields, address and the like are not offered in this mode: they stay with
 * those who can publish, so nothing reaches readers without review.
 */
final class PostChanges
{
    public const TABLE = 'cms_post_changes';

    /** Plain columns a change may carry. Categories, tags and terms are handled beside them. */
    private const COLUMNS = ['title', 'content', 'excerpt', 'featured_image', 'gallery', 'seo_meta', 'editor_type'];

    public static function ready(): bool
    {
        $memo = falcon_request_memo('post_changes_ready');
        if (!empty($memo['ready'])) {
            return true;
        }

        try {
            return $memo['ready'] = Schema::hasTable(self::TABLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Whether this user's edits to this item wait for review, rather than saving straight away. */
    public static function proposing(Post $post, $user = null): bool
    {
        return self::ready()
            && in_array($post->status, ['published', 'scheduled'], true)
            && !Editorial::exempt((string) $post->type)
            && !Editorial::canPublish($user);
    }

    // ── reading ─────────────────────────────────────────────────────────

    /** @return object|null The waiting change, with its fields decoded and who sent it. */
    public static function find(int $postId): ?object
    {
        if (!self::ready()) {
            return null;
        }

        $row = DB::table(self::TABLE)->where('post_id', $postId)->first();

        return $row ? self::decorate($row) : null;
    }

    /**
     * @param  array<int, int>  $postIds
     * @return array<int, object>
     */
    public static function forPosts(array $postIds): array
    {
        if ($postIds === [] || !self::ready()) {
            return [];
        }

        return DB::table(self::TABLE)->whereIn('post_id', $postIds)->get()
            ->mapWithKeys(fn ($row) => [(int) $row->post_id => self::decorate($row)])->all();
    }

    private static function decorate(object $row): object
    {
        $row->data = json_decode((string) $row->data, true) ?: [];
        $user = $row->user_id ? User::query()->find($row->user_id) : null;
        $row->user_name = $user ? ($user->name ?: $user->username) : 'Someone';

        return $row;
    }

    public static function pendingCount(): int
    {
        if (!self::ready()) {
            return 0;
        }

        return DB::table(self::TABLE)->join('posts', 'posts.id', '=', self::TABLE.'.post_id')
            ->where(self::TABLE.'.status', 'pending')->whereNull('posts.deleted_at')->count();
    }

    /** Show an item as it would be with this change. On the object only; nothing is saved. */
    public static function overlay(Post $post, ?object $change = null): ?object
    {
        $change ??= self::find((int) $post->id);
        if (!$change) {
            return null;
        }

        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $change->data)) {
                $post->setAttribute($column, $change->data[$column]);
            }
        }
        if (array_key_exists('categories', $change->data)) {
            $post->setRelation('categories', Category::query()->whereIn('id', $change->data['categories'])->get());
        }
        if (array_key_exists('tags', $change->data)) {
            $post->setRelation('tags', collect($change->data['tags'])->map(fn ($name) => new Tag(['name' => $name])));
        }

        return $change;
    }

    // ── writing ─────────────────────────────────────────────────────────

    /** The fields of an editor's form this mode takes. Everything else on the form is ignored. */
    public static function fromRequest(Request $request, Post $post): array
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'editor_type' => 'nullable|string|in:rich,builder',
            'seo' => 'nullable|array',
            'gallery' => 'nullable|array',
        ]);

        $fields = [];
        foreach (['title', 'excerpt'] as $key) {
            if ($request->has($key) && (($validated[$key] ?? '') !== '' || $key === 'excerpt')) {
                $fields[$key] = $validated[$key] ?? null;
            }
        }
        if ($request->has('content')) {
            // The rich editor shows a placeholder for a builder page: staying in builder mode with
            // anything but a layout means the content was not edited here, as on a normal save.
            $target = $validated['editor_type'] ?? $post->editor_type;
            $incoming = (string) ($validated['content'] ?? '');
            $isLayout = Str::startsWith($incoming, ['[', '{']);
            $current = (string) $post->getOriginal('content');
            $isCurrentLayout = $post->getOriginal('editor_type') === 'builder' || Str::startsWith($current, ['[', '{']);
            if (!($isCurrentLayout && $target === 'builder' && !$isLayout)) {
                $fields['content'] = $incoming;
            }
        }
        if (!empty($validated['editor_type'])) {
            $fields['editor_type'] = $validated['editor_type'];
        }
        if ($request->has('seo')) {
            $fields['seo_meta'] = $validated['seo'] ?? [];
        }
        // Every image removed posts no gallery at all, which the marker tells from no gallery box.
        if ($request->has('gallery') || $request->has('gallery_submitted')) {
            $fields['gallery'] = array_values((array) ($validated['gallery'] ?? []));
        }

        if ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
            if (in_array(strtolower($file->getClientOriginalExtension()), $allowedExts, true) && in_array($file->getMimeType(), $allowedMimes, true)) {
                $fields['featured_image'] = $file->store('posts', 'public');
            }
        } elseif ($request->input('remove_featured_image') === '1') {
            $fields['featured_image'] = null;
        } elseif ($request->filled('featured_image')) {
            $fields['featured_image'] = (string) $request->input('featured_image');
        }

        if ($request->has('categories')) {
            $fields['categories'] = self::ids($request->input('categories'));
        }
        if ($request->has('tax_terms')) {
            $fields['tax_terms'] = self::ids($request->input('tax_terms'));
        } elseif ($post->type !== 'post') {
            $fields['tax_terms'] = [];
        }
        if ($request->has('tags')) {
            $fields['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('tags')))));
        }

        return $fields;
    }

    /**
     * Keep a user's edits, on top of any they already have waiting. Only what differs from the
     * live version stays. Returns the change, or null when it comes to nothing.
     */
    public static function save(Post $post, array $fields, string $status = 'pending'): ?object
    {
        $status = $status === 'draft' ? 'draft' : 'pending';
        $existing = self::find((int) $post->id);

        $data = array_merge($existing->data ?? [], $fields);
        foreach ($data as $key => $value) {
            if (self::same($post, $key, $value)) {
                unset($data[$key]);
            }
        }
        // Which editor it was in means nothing unless the content changed with it.
        if (array_keys($data) === ['editor_type']) {
            $data = [];
        }

        Revision::clearAutosave($post);

        if ($data === []) {
            DB::table(self::TABLE)->where('post_id', $post->id)->delete();

            return null;
        }

        $now = now();
        $submitted = $status === 'pending' && ($existing->status ?? null) !== 'pending';

        DB::table(self::TABLE)->updateOrInsert(['post_id' => $post->id], [
            'user_id' => auth()->id(),
            'status' => $status,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            // A note answered by sending it back is finished with; one still being worked on stays.
            'note' => $status === 'pending' ? null : ($existing->note ?? null),
            'submitted_at' => $status === 'pending' ? $now : null,
            'created_at' => $existing->created_at ?? $now,
            'updated_at' => $now,
        ]);

        if ($submitted) {
            Editorial::recordChange($post, Editorial::CHANGES_SUBMITTED);
        }

        return self::find((int) $post->id);
    }

    /** Put a waiting change live. The version it replaces goes into the item's revisions. */
    public static function approve(Post $post, ?string $note = null): bool
    {
        $change = self::find((int) $post->id);
        if (!$change) {
            return false;
        }

        Revision::snapshot($post, 'revision');

        $columns = array_intersect_key($change->data, array_flip(self::COLUMNS));
        if ($columns !== []) {
            $post->update($columns);
        }
        if (array_key_exists('categories', $change->data)) {
            $post->categories()->sync($change->data['categories']);
        }
        if (array_key_exists('tax_terms', $change->data)) {
            $post->taxonomyTerms()->sync($change->data['tax_terms']);
        }
        if (array_key_exists('tags', $change->data)) {
            $ids = [];
            foreach ($change->data['tags'] as $name) {
                $ids[] = Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
            }
            $post->tags()->sync($ids);
        }

        DB::table(self::TABLE)->where('post_id', $post->id)->delete();
        Revision::clearAutosave($post);
        clear_page_cache();

        Editorial::recordChange($post, Editorial::CHANGES_APPROVED, $note, $change->user_id ? (int) $change->user_id : null);

        return true;
    }

    /** Send a change back to whoever made it, who keeps working on it. Readers are unaffected. */
    public static function sendBack(Post $post, ?string $note = null): bool
    {
        $change = self::find((int) $post->id);
        if (!$change) {
            return false;
        }

        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        DB::table(self::TABLE)->where('post_id', $post->id)->update(['status' => 'draft', 'note' => $note, 'updated_at' => now()]);
        Editorial::recordChange($post, Editorial::CHANGES_RETURNED, $note, $change->user_id ? (int) $change->user_id : null);

        return true;
    }

    public static function discard(Post $post): bool
    {
        $change = self::find((int) $post->id);
        if (!$change) {
            return false;
        }

        DB::table(self::TABLE)->where('post_id', $post->id)->delete();
        Revision::clearAutosave($post);
        Editorial::recordChange($post, Editorial::CHANGES_DISCARDED, null, $change->user_id ? (int) $change->user_id : null);

        return true;
    }

    /** Called once an item is gone for good. */
    public static function forget(int $postId): void
    {
        if (self::ready()) {
            DB::table(self::TABLE)->where('post_id', $postId)->delete();
        }
    }

    // ── comparing ───────────────────────────────────────────────────────

    /** Whether a field's value is what the live version already has. */
    private static function same(Post $post, string $key, $value): bool
    {
        return match ($key) {
            'categories' => self::idSet($post->categories()->pluck('categories.id')->all()) === self::idSet($value),
            'tax_terms' => self::idSet($post->taxonomyTerms()->pluck('taxonomy_terms.id')->all()) === self::idSet($value),
            'tags' => self::textSet($post->tags()->pluck('name')->all()) === self::textSet($value),
            'seo_meta' => self::cleanMap((array) $post->getOriginal('seo_meta')) === self::cleanMap((array) $value),
            'gallery' => array_values((array) $post->getOriginal('gallery')) === array_values((array) $value),
            'content' => self::contentKey($post->getOriginal('content')) === self::contentKey($value),
            default => (string) $post->getOriginal($key) === (string) $value,
        };
    }

    /** Content as text. A layout is compared as its shortcodes: a round trip through the editor renumbers it. */
    private static function contentKey($content): string
    {
        $text = (string) $content;
        try {
            if (BuilderShortcodeConverter::isBuilderJson($text)) {
                $text = BuilderShortcodeConverter::jsonToShortcodes($text);
            }
        } catch (Throwable $e) {
            // Compared as it stands.
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    private static function ids($values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $values))));
    }

    private static function idSet($values): array
    {
        $ids = self::ids($values);
        sort($ids);

        return $ids;
    }

    private static function textSet($values): array
    {
        $out = array_map('strval', (array) $values);
        sort($out);

        return $out;
    }

    private static function cleanMap(array $map): array
    {
        $map = array_filter($map, fn ($v) => $v !== null && $v !== '' && $v !== []);
        ksort($map);

        return array_map(fn ($v) => is_scalar($v) ? (string) $v : $v, $map);
    }

    /**
     * What a reviewer reads: each changed field, then and now.
     *
     * @return list<array{label: string, kind: string, old: mixed, new: mixed}>
     */
    public static function rows(Post $post, object $change): array
    {
        $rows = [];
        $data = $change->data;

        if (array_key_exists('title', $data)) {
            $rows[] = ['label' => 'Title', 'kind' => 'text', 'old' => (string) $post->title, 'new' => (string) $data['title']];
        }
        if (array_key_exists('content', $data)) {
            $rows[] = ['label' => 'Content', 'kind' => 'diff', 'old' => (string) $post->content, 'new' => (string) $data['content']];
        }
        if (array_key_exists('excerpt', $data)) {
            $rows[] = ['label' => 'Excerpt', 'kind' => 'text', 'old' => (string) $post->excerpt, 'new' => (string) $data['excerpt']];
        }
        if (array_key_exists('featured_image', $data)) {
            $rows[] = ['label' => 'Featured image', 'kind' => 'image', 'old' => $post->featured_image, 'new' => $data['featured_image']];
        }
        if (array_key_exists('gallery', $data)) {
            $rows[] = ['label' => 'Gallery', 'kind' => 'gallery', 'old' => array_values((array) $post->gallery), 'new' => array_values((array) $data['gallery'])];
        }
        if (array_key_exists('categories', $data)) {
            $rows[] = ['label' => 'Categories', 'kind' => 'text',
                'old' => $post->categories()->pluck('name')->implode(', '),
                'new' => Category::query()->whereIn('id', $data['categories'])->pluck('name')->implode(', ')];
        }
        if (array_key_exists('tags', $data)) {
            $rows[] = ['label' => 'Tags', 'kind' => 'text', 'old' => $post->tags()->pluck('name')->implode(', '), 'new' => implode(', ', $data['tags'])];
        }
        if (array_key_exists('tax_terms', $data)) {
            $rows[] = ['label' => 'Terms', 'kind' => 'text',
                'old' => $post->taxonomyTerms()->pluck('name')->implode(', '),
                'new' => DB::table('taxonomy_terms')->whereIn('id', $data['tax_terms'])->pluck('name')->implode(', ')];
        }
        if (array_key_exists('seo_meta', $data)) {
            $old = self::cleanMap((array) $post->seo_meta);
            $new = self::cleanMap((array) $data['seo_meta']);
            foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
                if (($old[$key] ?? '') !== ($new[$key] ?? '')) {
                    $rows[] = ['label' => 'SEO: '.ucwords(str_replace('_', ' ', (string) $key)), 'kind' => 'text',
                        'old' => is_scalar($old[$key] ?? '') ? (string) ($old[$key] ?? '') : json_encode($old[$key]),
                        'new' => is_scalar($new[$key] ?? '') ? (string) ($new[$key] ?? '') : json_encode($new[$key])];
                }
            }
        }

        return $rows;
    }
}
