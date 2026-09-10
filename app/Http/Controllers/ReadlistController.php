<?php

namespace App\Http\Controllers;

use App\Models\Literature;
use App\Models\User;
use App\Support\ProfilePagePresenter;
use Inertia\Inertia;
use Inertia\Response;

class ReadlistController extends Controller
{
    public function __invoke(User $user, ProfilePagePresenter $presenter): Response
    {
        $isOwner = auth()->id() === $user->id;
        $readlist = $user->readingLists()
            ->where('status', 'want_to_read')
            ->with(['literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride'])
            ->latest('updated_at')
            ->paginate(24);

        $readlistSuggestions = $isOwner
            ? Literature::query()
                ->whereDoesntHave('readingLists', fn ($query) => $query->whereBelongsTo($user))
                ->with(['authors', 'metadataOverride', 'sourceMapping.canonicalWork.metadataOverride'])
                ->latest()
                ->limit(12)
                ->get()
            : collect();

        $readlist->through(fn ($item): array => [
            'id' => $item->id,
            'saved_at' => $item->updated_at->utc()->toIso8601String(),
            'literature' => $presenter->literature($item->literature),
        ]);

        return Inertia::render('Profile/Readlist', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'readlist', request()->user()),
            'readlist' => $readlist,
            'suggestions' => $readlistSuggestions
                ->map(fn (Literature $literature): array => [
                    ...$presenter->literature($literature),
                    'update_url' => route('reading-list.update', $literature),
                ])
                ->values()
                ->all(),
            'isOwner' => $isOwner,
            'viewerAuthenticated' => request()->user() !== null,
            'routes' => [
                'catalog' => route('literatures.index'),
                'login' => route('login'),
            ],
        ]);
    }
}
