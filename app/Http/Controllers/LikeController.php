<?php

namespace App\Http\Controllers;

use App\Models\Discussion;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function storeReview(Request $request, Review $review): RedirectResponse
    {
        $review->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return redirect()->to(route('literatures.show', $review->literature).'#review-'.$review->id)
            ->with('success', 'Review liked.');
    }

    public function destroyReview(Request $request, Review $review): RedirectResponse
    {
        $review->likes()->where('user_id', $request->user()->id)->delete();

        return redirect()->to(route('literatures.show', $review->literature).'#review-'.$review->id)
            ->with('success', 'Review like removed.');
    }

    public function storeDiscussion(Request $request, Discussion $discussion): RedirectResponse
    {
        $discussion->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return redirect()->to(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id)
            ->with('success', 'Discussion liked.');
    }

    public function destroyDiscussion(Request $request, Discussion $discussion): RedirectResponse
    {
        $discussion->likes()->where('user_id', $request->user()->id)->delete();

        return redirect()->to(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id)
            ->with('success', 'Discussion like removed.');
    }
}
