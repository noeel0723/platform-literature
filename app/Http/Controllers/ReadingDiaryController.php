<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadingDiaryController extends Controller
{
    public function index(Request $request, ProfilePagePresenter $presenter): Response
    {
        $reader = $request->user();
        $activities = $request->user()
            ->reviews()
            ->whereNull('hidden_at')
            ->with(['literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride'])
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Review $review): array => [
                'review_id' => $review->id,
                'occurred_at' => $review->updated_at->utc()->toIso8601String(),
                'literature' => $presenter->literature($review->literature),
                'rating' => $review->rating,
                'review' => $review->body,
                'contains_spoiler' => $review->contains_spoiler,
                'edit_url' => route('literatures.show', $review->literature).'?review=edit',
            ]);

        return Inertia::render('Profile/Diary', [
            'profile' => $presenter->user($reader),
            'navigation' => $presenter->navigation($reader, 'diary', $reader),
            'activities' => $activities->values()->all(),
        ]);
    }
}
