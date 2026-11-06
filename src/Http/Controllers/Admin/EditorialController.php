<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Http\Controllers\FrontendController;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\Editorial;
use FalconCms\Core\Support\PostChanges;
use FalconCms\Core\Support\PostLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The review queue, approving and returning work, and the editors' lock. AdminMiddleware leaves
 * everything under /admin/posts to the controller, so each action checks for itself.
 */
class EditorialController extends Controller
{
    /** Everything waiting for review, in the types this reviewer can write. */
    public function queue(): View
    {
        abort_unless(Editorial::canPublish(), 403, 'You do not have permission to review content.');

        $sections = FalconBuilderController::SECTION_TYPES;
        $types = Post::query()->where('status', 'pending')->whereNotIn('type', $sections)
            ->distinct()->pluck('type')
            ->filter(fn ($type) => Editorial::canWriteType((string) $type))
            ->values()->all();

        $items = Post::query()->with('user')->where('status', 'pending')->whereIn('type', $types)
            ->orderBy('updated_at')->paginate(20);

        $ids = $items->pluck('id')->all();

        // Edits to live content, in every type this reviewer can write.
        $changes = [];
        if (PostChanges::ready()) {
            $waiting = DB::table(PostChanges::TABLE)->where('status', 'pending')->orderBy('submitted_at')->limit(100)->pluck('post_id')->all();
            $posts = Post::query()->whereIn('id', $waiting)->get()->keyBy('id');
            $found = PostChanges::forPosts($waiting);
            foreach ($waiting as $postId) {
                if (isset($posts[$postId]) && Editorial::canWriteType((string) $posts[$postId]->type)) {
                    $changes[] = ['post' => $posts[$postId], 'change' => $found[$postId]];
                }
            }
        }

        return view('falcon-cms::admin.posts.review-queue', [
            'items' => $items,
            'changes' => $changes,
            'locks' => PostLock::holders(array_merge($ids, array_map(fn ($c) => (int) $c['post']->id, $changes)), auth()->id()),
            'submittedAt' => $ids === [] ? [] : DB::table(Editorial::REVIEWS)
                ->whereIn('post_id', $ids)->where('action', Editorial::SUBMITTED)
                ->groupBy('post_id')->selectRaw('post_id, MAX(created_at) as at')->pluck('at', 'post_id')->all(),
        ]);
    }

    /** Approve or return one item. Publishes what was last saved. */
    public function review(Request $request, int $id): RedirectResponse
    {
        $post = Post::query()->findOrFail($id);
        $this->authorizeWrite($post);
        abort_unless(Editorial::canPublish(), 403, 'You do not have permission to review content.');

        $data = $request->validate([
            'decision' => 'required|in:approve,return',
            'note' => 'nullable|string|max:2000',
        ]);

        if ($post->status !== 'pending') {
            return back()->with('error', 'This is no longer waiting for review.');
        }

        PostLock::guardReview($post);

        Editorial::withNote($data['note'] ?? null, function () use ($post, $data) {
            if ($data['decision'] === 'return') {
                $post->update(['status' => 'draft']);

                return;
            }

            // A date already in the future stays, and schedules the item; anything else goes
            // live now, not on the day the writer first started it.
            $future = $post->published_at instanceof Carbon && $post->published_at->isFuture();
            $post->update([
                'status' => $future ? 'scheduled' : 'published',
                'published_at' => $future ? $post->published_at : now(),
            ]);
        });

        clear_page_cache();

        return back()->with('success', $data['decision'] === 'approve'
            ? 'Approved'.($post->status === 'scheduled' ? ' and scheduled.' : ' and published.')
            : 'Returned to the writer as a draft.');
    }

    /** An edit to live content, then and now. Its writer and every reviewer can read it. */
    public function changes(int $id): View|RedirectResponse
    {
        $post = Post::query()->findOrFail($id);
        abort_unless(Editorial::canWriteType((string) $post->type), 403, 'You do not have permission to change this item.');

        $change = PostChanges::find((int) $post->id);
        if (!$change) {
            return redirect(Editorial::editUrl($post))->with('success', 'There are no changes waiting on this.');
        }

        return view('falcon-cms::admin.posts.changes', [
            'post' => $post,
            'change' => $change,
            'rows' => PostChanges::rows($post, $change),
            'canReview' => Editorial::canPublish(),
        ]);
    }

    /** The page as it would read with the change. Nothing is saved. */
    public function previewChanges(int $id)
    {
        $post = Post::query()->findOrFail($id);
        abort_unless(Editorial::canWriteType((string) $post->type), 403, 'You do not have permission to change this item.');
        PostChanges::overlay($post);

        return app(FrontendController::class)->preview($post);
    }

    /** Approve or return (a reviewer), or discard (a reviewer, or anyone who can write it). */
    public function decideChanges(Request $request, int $id): RedirectResponse
    {
        $post = Post::query()->findOrFail($id);
        abort_unless(Editorial::canWriteType((string) $post->type), 403, 'You do not have permission to change this item.');

        $data = $request->validate([
            'decision' => 'required|in:approve,return,discard',
            'note' => 'nullable|string|max:2000',
        ]);
        abort_if($data['decision'] !== 'discard' && !Editorial::canPublish(), 403, 'You do not have permission to review content.');

        if (!PostChanges::find((int) $post->id)) {
            return redirect(Editorial::editUrl($post))->with('error', 'Those changes are no longer waiting.');
        }

        PostLock::guardReview($post);

        return match ($data['decision']) {
            'approve' => tap(redirect(Editorial::editUrl($post))->with('success', 'Changes approved. They are live.'), fn () => PostChanges::approve($post, $data['note'] ?? null)),
            'return' => tap(redirect(route('admin.posts.review-queue'))->with('success', 'Returned to the writer. Readers still see the current version.'), fn () => PostChanges::sendBack($post, $data['note'] ?? null)),
            default => tap(redirect(Editorial::editUrl($post))->with('success', 'Changes discarded.'), fn () => PostChanges::discard($post)),
        };
    }

    /** Heartbeat from an open editor: take or renew the lock. force takes it from its holder. */
    public function lock(Request $request, int $id): JsonResponse
    {
        $post = Post::query()->findOrFail($id);
        abort_unless(Editorial::canWriteType((string) $post->type), 403, 'You do not have permission to change this item.');

        $holder = PostLock::acquire((int) $post->id, (int) auth()->id(), $request->boolean('force'));

        return response()->json(['locked' => $holder !== null, 'holder' => $holder]);
    }

    public function unlock(int $id): JsonResponse
    {
        PostLock::release($id, (int) auth()->id());

        return response()->json(['released' => true]);
    }

    private function authorizeWrite(Post $post): void
    {
        abort_unless(
            Editorial::canWriteType((string) $post->type) && Editorial::canChange($post),
            403,
            'You do not have permission to change this item.'
        );
    }
}
