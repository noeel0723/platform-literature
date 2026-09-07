<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class ProfileReviewController extends Controller
{
    public function __invoke(User $user): View
    {
        $visibleReviews = $user->reviews()->whereNull('hidden_at');

        $reviews = (clone $visibleReviews)
            ->with(['literature.authors'])
            ->withCount('likes')
            ->latest('updated_at')
            ->paginate(12);

        return view('profiles.reviews', [
            'user' => $user,
            'reviews' => $reviews,
            'averageRating' => (float) ((clone $visibleReviews)->avg('rating') ?? 0),
            'totalReviews' => (clone $visibleReviews)->count(),
        ]);
    }
}
