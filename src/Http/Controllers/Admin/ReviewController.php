<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $query = Review::with('post', 'user')->latest();

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

        $reviews = $query->paginate(10)->withQueryString();
        
        $allCount = Review::count();
        $pendingCount = Review::where('is_approved', false)->count();
        $approvedCount = Review::where('is_approved', true)->count();

        return view('falcon-cms::admin.shop.reviews.index', compact('reviews', 'allCount', 'pendingCount', 'approvedCount'));
    }

    public function toggleApprove(Review $review)
    {
        $review->is_approved = !$review->is_approved;
        $review->save();
        $status = $review->is_approved ? 'Approved' : 'Unapproved';
        falcon_log_activity('updated', "{$status} review from: " . ($review->name ?? $review->user->name), $review);

        return back()->with('success', $review->is_approved ? 'Review approved.' : 'Review moved to pending.');
    }

    public function destroy(Review $review)
    {
        $author = $review->name ?? ($review->user->name ?? 'Unknown');
        $review->delete();
        falcon_log_activity('deleted', "Deleted review from: {$author}", $review);
        return back()->with('success', 'Review deleted successfully.');
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action');
        $ids = $request->input('review_ids');

        if (!$ids || $action === '-1') {
            return back()->with('error', 'Please select items and an action.');
        }

        if ($action === 'approve') {
            Review::whereIn('id', $ids)->update(['is_approved' => true]);
        } elseif ($action === 'unapprove') {
            Review::whereIn('id', $ids)->update(['is_approved' => false]);
        } elseif ($action === 'delete') {
            Review::whereIn('id', $ids)->delete();
        }

        return back()->with('success', 'Bulk action applied successfully.');
    }
}
