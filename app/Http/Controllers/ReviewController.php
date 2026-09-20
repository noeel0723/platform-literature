<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertReviewRequest;
use App\Models\Literature;
use App\Services\ActivityRecorder;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function update(
        UpsertReviewRequest $request,
        Literature $literature,
        ReadingManager $readingManager,
        ActivityRecorder $activityRecorder,
    ): RedirectResponse {
        $data = $request->validated();

        DB::transaction(function () use ($request, $literature, $readingManager, $activityRecorder, $data): void {
            $body = filled($data['body'] ?? null) ? trim((string) $data['body']) : null;
            $review = $request->user()->reviews()->updateOrCreate(
                ['literature_id' => $literature->id],
                [
                    'rating' => $data['rating'],
                    'body' => $body,
                    'contains_spoiler' => (bool) ($data['contains_spoiler'] ?? false),
                ],
            );
            $shouldRecordActivity = $review->wasRecentlyCreated
                || $review->wasChanged(['rating', 'body', 'contains_spoiler']);

            $readingList = $readingManager->update($request->user(), $literature, [
                'status' => 'completed',
                'completed_at' => $data['completed_at'] ?? now()->toDateString(),
                'activity_occurred_at' => now(),
                'force_completed_activity' => $body === null
                    && ($review->wasRecentlyCreated || $review->wasChanged('body')),
            ]);

            if ($shouldRecordActivity || $readingList->wasChanged('completed_at')) {
                $activityRecorder->recordReview($review);
            }
        });

        $message = filled($data['body'] ?? null)
            ? 'Your review has been saved and this literature is marked as completed.'
            : 'Your rating has been saved and this literature is marked as completed.';

        return redirect()->to(route('literatures.show', $literature).'#reviews')
            ->with('success', $message);
    }

    public function destroy(Request $request, Literature $literature): RedirectResponse
    {
        $request->user()->reviews()
            ->whereBelongsTo($literature)
            ->first()
            ?->delete();

        return redirect()->to(route('literatures.show', $literature).'#reviews')
            ->with('success', 'Your review has been deleted.');
    }
}
