<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ProfilePagePresenter;
use Inertia\Inertia;
use Inertia\Response;

class ProfileLiteratureController extends Controller
{
    public function __invoke(User $user, ProfilePagePresenter $presenter): Response
    {
        $completedLiterature = $user->readingLists()
            ->where('status', 'completed')
            ->with([
                'literature.authors',
                'literature.metadataOverride',
                'literature.sourceMapping.canonicalWork.metadataOverride',
                'literature.reviews' => fn ($reviews) => $reviews
                    ->whereBelongsTo($user)
                    ->whereNull('hidden_at'),
            ])
            ->latest('completed_at')
            ->paginate(48);

        $completedLiterature->through(fn ($item): array => [
            'id' => $item->id,
            'completed_at' => $item->completed_at?->utc()->toIso8601String(),
            'rating' => $item->literature->reviews->first()?->rating,
            'literature' => $presenter->literature($item->literature),
        ]);

        return Inertia::render('Profile/Literature', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'literature', request()->user()),
            'completedLiterature' => $completedLiterature,
        ]);
    }
}
