<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingDiaryController extends Controller
{
    public function index(Request $request): View
    {
        $activities = $request->user()
            ->reviews()
            ->whereNull('hidden_at')
            ->with('literature.authors')
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Review $review): array => [
                'review_id' => $review->id,
                'occurred_at' => $review->updated_at,
                'literature' => $review->literature,
                'rating' => $review->rating,
                'review' => $review->body,
                'contains_spoiler' => $review->contains_spoiler,
            ]);

        return view('diary.index', [
            'activities' => $activities,
        ]);
    }
}
