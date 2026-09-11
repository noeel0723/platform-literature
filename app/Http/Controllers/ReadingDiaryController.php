<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\User;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadingDiaryController extends Controller
{
    public function index(Request $request, ProfilePagePresenter $presenter): Response
    {
        return $this->renderDiary($request->user(), $request, $presenter);
    }

    public function show(Request $request, User $user, ProfilePagePresenter $presenter): Response
    {
        $viewer = $request->user();

        if (! $viewer->is($user)) {
            abort_unless($viewer->isFollowing($user) && $user->isFollowing($viewer), 403);
        }

        return $this->renderDiary($user, $request, $presenter);
    }

    private function renderDiary(User $reader, Request $request, ProfilePagePresenter $presenter): Response
    {
        $isOwner = $request->user()->is($reader);
        $activities = $reader
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
                'edit_url' => $isOwner ? route('literatures.show', $review->literature).'?review=edit' : null,
            ]);

        return Inertia::render('Profile/Diary', [
            'profile' => $presenter->user($reader),
            'navigation' => $presenter->navigation($reader, 'diary', $request->user()),
            'activities' => $activities->values()->all(),
        ]);
    }
}
