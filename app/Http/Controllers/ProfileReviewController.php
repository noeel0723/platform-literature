<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ProfilePagePresenter;
use Inertia\Inertia;
use Inertia\Response;

class ProfileReviewController extends Controller
{
    public function __invoke(User $user, ProfilePagePresenter $presenter): Response
    {
        $visibleReviews = $user->reviews()->whereNull('hidden_at');

        $reviews = (clone $visibleReviews)
            ->with(['literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride'])
            ->withCount('likes')
            ->latest('updated_at')
            ->paginate(12);

        $reviews->through(fn ($review): array => [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'contains_spoiler' => $review->contains_spoiler,
            'likes_count' => $review->likes_count,
            'updated_at' => $review->updated_at->utc()->toIso8601String(),
            'literature' => $presenter->literature($review->literature),
            'edit_url' => auth()->id() === $user->id
                ? route('literatures.show', $review->literature).'?review=edit'
                : null,
        ]);

        return Inertia::render('Profile/Reviews', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'reviews', request()->user()),
            'reviews' => $reviews,
            'summary' => [
                'average_rating' => (float) ((clone $visibleReviews)->avg('rating') ?? 0),
                'total_reviews' => (clone $visibleReviews)->count(),
            ],
        ]);
    }
}
