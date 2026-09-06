<?php

namespace App\Http\Controllers;

use App\Models\ReadingList;
use App\Models\ReadingLog;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingDiaryController extends Controller
{
    public function index(Request $request): View
    {
        $readingLists = $request->user()
            ->readingLists()
            ->with(['literature.apiSource', 'progress'])
            ->latest('updated_at')
            ->get();

        $logs = ReadingLog::query()
            ->whereHas('readingList', fn ($query) => $query->whereBelongsTo($request->user()))
            ->with('readingList.literature')
            ->latest('occurred_at')
            ->limit(100)
            ->get();

        $readingActivities = $logs->map(fn (ReadingLog $log): array => [
            'kind' => 'reading',
            'occurred_at' => $log->occurred_at,
            'label' => ReadingLog::EVENT_LABELS[$log->event_type] ?? $log->event_type,
            'literature' => $log->readingList->literature,
            'progress_value' => $log->progress_value,
            'progress_total' => $log->progress_total,
            'progress_unit' => $log->progress_unit,
            'note' => $log->note,
            'rating' => null,
        ]);

        $reviewActivities = $request->user()
            ->reviews()
            ->with('literature')
            ->get()
            ->map(fn (Review $review): array => [
                'kind' => 'review',
                'occurred_at' => $review->updated_at,
                'label' => filled($review->body) ? 'Rated and reviewed' : 'Rated literature',
                'literature' => $review->literature,
                'progress_value' => null,
                'progress_total' => null,
                'progress_unit' => null,
                'note' => $review->body,
                'rating' => $review->rating,
            ]);

        $activities = $readingActivities
            ->concat($reviewActivities)
            ->sortByDesc(fn (array $activity): int => $activity['occurred_at']->getTimestamp())
            ->take(100)
            ->values();

        return view('diary.index', [
            'readingLists' => $readingLists,
            'activities' => $activities,
            'statusLabels' => ReadingList::STATUS_LABELS,
        ]);
    }
}
