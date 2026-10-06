<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function moderate(User $user, Review $review): bool
    {
        return $user->hasPermission('reviews.manage');
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->hasPermission('reviews.manage');
    }
}
