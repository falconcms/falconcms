<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        // Mark all unread comments as read since the admin is viewing the page
        Comment::where('is_read', false)->update(['is_read' => true]);

        $status = $request->query('status');
        $query = Comment::with('post', 'user')->latest();

        if ($status === 'pending') {
            $query->where('is_approved', false);
        } elseif ($status === 'approved') {
            $query->where('is_approved', true);
        }

        if ($request->filled('s')) {
            $query->where(function($q) use ($request) {
                $q->where('comment', 'like', '%' . $request->s . '%')
                  ->orWhere('name', 'like', '%' . $request->s . '%')
                  ->orWhere('email', 'like', '%' . $request->s . '%');
            });
        }

        $comments = $query->paginate(10)->withQueryString();
        
        $allCount = Comment::count();
        $pendingCount = Comment::where('is_approved', false)->count();
        $approvedCount = Comment::where('is_approved', true)->count();

        return view('falcon-cms::admin.comments.index', compact('comments', 'allCount', 'pendingCount', 'approvedCount'));
    }

    public function toggleApprove(Comment $comment)
    {
        $comment->is_approved = !$comment->is_approved;
        $comment->save();
        $status = $comment->is_approved ? 'Approved' : 'Unapproved';
        lazy_log_activity('updated', "{$status} comment from: " . ($comment->name ?? $comment->user->name), $comment);

        return back()->with('success', $comment->is_approved ? 'Comment approved.' : 'Comment moved to pending.');
    }

    public function destroy(Comment $comment)
    {
        $author = $comment->name ?? ($comment->user->name ?? 'Unknown');
        $comment->delete();
        lazy_log_activity('deleted', "Deleted comment from: {$author}", $comment);
        return back()->with('success', 'Comment deleted successfully.');
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action');
        $ids = $request->input('comment_ids');

        if (!$ids || $action === '-1') {
            return back()->with('error', 'Please select items and an action.');
        }

        if ($action === 'approve') {
            Comment::whereIn('id', $ids)->update(['is_approved' => true]);
        } elseif ($action === 'unapprove') {
            Comment::whereIn('id', $ids)->update(['is_approved' => false]);
        } elseif ($action === 'delete') {
            Comment::whereIn('id', $ids)->delete();
        }

        return back()->with('success', 'Bulk action applied successfully.');
    }
}
