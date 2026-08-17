<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * Reviews are customer-authored and admin-moderated, so ownership and
 * moderation are two distinct rules.
 *
 * Super admin bypasses all of this via Gate::before in AppServiceProvider.
 */
class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view review');
    }

    /** Approved reviews are public; anything else is author-or-admin only. */
    public function view(User $user, Review $review): bool
    {
        return $review->status === 'approved'
            || $review->user_id === $user->id
            || $user->can('view review');
    }

    public function create(User $user): bool
    {
        return true; // Verified-purchase checks belong to the review flow itself.
    }

    /** The author may edit their own review while it is still pending. */
    public function update(User $user, Review $review): bool
    {
        if ($review->user_id === $user->id && $review->status === 'pending') {
            return true;
        }

        return $user->can('update review');
    }

    /** Approve, reject and admin_reply are moderation, not authorship. */
    public function moderate(User $user, Review $review): bool
    {
        return $user->can('update review');
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->can('delete review');
    }
}
