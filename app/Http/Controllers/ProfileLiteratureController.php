<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class ProfileLiteratureController extends Controller
{
    public function __invoke(User $user): View
    {
        $completedLiterature = $user->readingLists()
            ->where('status', 'completed')
            ->with([
                'literature.authors',
                'literature.reviews' => fn ($reviews) => $reviews
                    ->whereBelongsTo($user)
                    ->whereNull('hidden_at'),
            ])
            ->latest('completed_at')
            ->paginate(48);

        return view('profiles.literature', compact('user', 'completedLiterature'));
    }
}
