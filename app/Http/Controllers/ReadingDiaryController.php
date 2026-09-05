<?php

namespace App\Http\Controllers;

use App\Models\ReadingList;
use App\Models\ReadingLog;
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

        return view('diary.index', [
            'readingLists' => $readingLists,
            'logs' => $logs,
            'statusLabels' => ReadingList::STATUS_LABELS,
            'eventLabels' => ReadingLog::EVENT_LABELS,
        ]);
    }
}
