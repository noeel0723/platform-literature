<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Literature;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $viewer = $request->user();
        $friendIds = $viewer?->following()
            ->whereNull('deactivated_at')
            ->pluck('users.id') ?? collect();

        $activities = collect();
        $popularLiteratures = collect();

        if ($friendIds->isNotEmpty()) {
            $activities = Activity::query()
                ->visibleToReaders()
                ->with(['user', 'literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride', 'review'])
                ->whereIn('user_id', $friendIds)
                ->whereIn('type', [
                    Activity::TYPE_COMPLETED,
                    Activity::TYPE_RATED,
                    Activity::TYPE_REVIEWED,
                ])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->unique(fn (Activity $activity): string => $activity->user_id.'-'.$activity->literature_id)
                ->take(6)
                ->values();

            $popularLiteratures = Literature::query()
                ->with(['authors', 'metadataOverride', 'sourceMapping.canonicalWork.metadataOverride', 'readingLists' => function ($readingLists) use ($friendIds): void {
                    $readingLists
                        ->whereIn('user_id', $friendIds)
                        ->whereIn('status', ['reading', 'completed'])
                        ->with('user')
                        ->latest('updated_at');
                }])
                ->withCount(['readingLists as friends_count' => function (Builder $readingLists) use ($friendIds): void {
                    $readingLists
                        ->whereIn('user_id', $friendIds)
                        ->whereIn('status', ['reading', 'completed']);
                }])
                ->whereHas('readingLists', function (Builder $readingLists) use ($friendIds): void {
                    $readingLists
                        ->whereIn('user_id', $friendIds)
                        ->whereIn('status', ['reading', 'completed']);
                })
                ->orderByDesc('friends_count')
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get();
        }

        return view('home', [
            'activities' => $activities,
            'popularLiteratures' => $popularLiteratures,
            'viewer' => $viewer,
        ]);
    }
}
