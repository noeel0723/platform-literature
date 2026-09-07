<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $viewer = $request->user();
        $visibleUserIds = $viewer === null
            ? null
            : $viewer->following()->pluck('users.id')->push($viewer->id)->unique();

        $activities = Activity::query()
            ->with(['user', 'literature.authors', 'review'])
            ->whereHas('user', fn ($users) => $users->whereNull('deactivated_at'))
            ->where(function ($query): void {
                $query
                    ->whereNotIn('type', [Activity::TYPE_RATED, Activity::TYPE_REVIEWED])
                    ->orWhereHas('review', fn ($reviews) => $reviews->whereNull('hidden_at'));
            })
            ->when(
                $visibleUserIds !== null,
                fn ($query) => $query->whereIn('user_id', $visibleUserIds),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(18);

        return view('home', [
            'activities' => $activities,
            'feedLabel' => $viewer === null ? 'Community activity' : 'You and readers you follow',
        ]);
    }
}
