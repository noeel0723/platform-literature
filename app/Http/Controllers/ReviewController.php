<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertReviewRequest;
use App\Models\Literature;
use App\Services\ActivityRecorder;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function update(
        UpsertReviewRequest $request,
        Literature $literature,
        ReadingManager $readingManager,
        ActivityRecorder $activityRecorder,
        CanonicalWorkIdentity $canonicalIdentity,
    ): RedirectResponse {
        $data = $request->validated();
        $representative = $canonicalIdentity->representative($literature);
        $equivalentIds = $canonicalIdentity->equivalentLiteratureIds($representative);

        DB::transaction(function () use ($request, $representative, $equivalentIds, $readingManager, $activityRecorder, $data): void {
            $body = filled($data['body'] ?? null) ? trim((string) $data['body']) : null;
            $reviews = $request->user()->reviews()
                ->whereIn('literature_id', $equivalentIds)
                ->lockForUpdate()
                ->get();

            if ($reviews->count() > 1) {
                throw ValidationException::withMessages([
                    'rating' => 'This work has conflicting legacy reviews. Run the interaction audit and resolve them before updating.',
                ]);
            }

            $review = $reviews->first() ?? $request->user()->reviews()->make();
            $review->literature()->associate($representative);
            $review->fill([
                'rating' => $data['rating'],
                'body' => $body,
                'contains_spoiler' => (bool) ($data['contains_spoiler'] ?? false),
            ]);
            $review->save();
            $shouldRecordActivity = $review->wasRecentlyCreated
                || $review->wasChanged(['rating', 'body', 'contains_spoiler']);

            $readingList = $readingManager->update($request->user(), $representative, [
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

    public function destroy(
        Request $request,
        Literature $literature,
        CanonicalWorkIdentity $canonicalIdentity,
    ): RedirectResponse {
        $reviews = $request->user()->reviews()
            ->whereIn('literature_id', $canonicalIdentity->equivalentLiteratureIds($literature))
            ->get();

        if ($reviews->count() > 1) {
            throw ValidationException::withMessages([
                'review' => 'This work has conflicting legacy reviews. Run the interaction audit and resolve them before deleting.',
            ]);
        }

        $reviews->first()?->delete();

        return redirect()->to(route('literatures.show', $literature).'#reviews')
            ->with('success', 'Your review has been deleted.');
    }
}
