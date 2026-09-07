<?php

namespace App\Http\Controllers;

use App\Models\Literature;
use App\Models\User;
use Illuminate\Contracts\View\View;

class ReadlistController extends Controller
{
    public function __invoke(User $user): View
    {
        $isOwner = auth()->id() === $user->id;
        $readlist = $user->readingLists()
            ->where('status', 'want_to_read')
            ->with(['literature.authors'])
            ->latest('updated_at')
            ->paginate(24);

        $readlistSuggestions = $isOwner
            ? Literature::query()
                ->whereDoesntHave('readingLists', fn ($query) => $query->whereBelongsTo($user))
                ->with('authors')
                ->latest()
                ->limit(12)
                ->get()
            : collect();

        return view('readlist.index', compact('user', 'readlist', 'readlistSuggestions', 'isOwner'));
    }
}
