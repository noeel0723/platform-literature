<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $viewer = $request->user();
        $scope = $request->string('scope')->lower()->toString();
        $scope = in_array($scope, ['all', 'you', 'friends'], true) ? $scope : 'all';
        $friendIds = $viewer->following()
            ->whereNull('deactivated_at')
            ->pluck('users.id');

        $userIds = match ($scope) {
            'you' => collect([$viewer->id]),
            'friends' => $friendIds,
            default => $friendIds->prepend($viewer->id),
        };

        $activities = Activity::query()
            ->visibleToReaders()
            ->with(['user', 'literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride', 'review', 'discussion', 'comment'])
            ->whereIn('user_id', $userIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('activity.index', compact('viewer', 'activities', 'scope'));
    }
}
