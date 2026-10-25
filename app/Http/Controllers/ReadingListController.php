<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReadingListRequest;
use App\Models\Literature;
use App\Services\ActionFeedback;
use App\Services\ActivityRecorder;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReadingListController extends Controller
{
    public function update(
        UpdateReadingListRequest $request,
        Literature $literature,
        ReadingManager $readingManager,
        ActionFeedback $feedback,
    ): RedirectResponse|JsonResponse {
        $message = $request->boolean('reread') ? 'Started a reread.' : match ($request->validated('status')) {
            'want_to_read' => 'Added to your Readlist.',
            'completed' => 'Marked as completed.',
            default => 'Reading status updated.',
        };
        $action = $feedback->run($request->user(), 'reading', ['literature_id' => $literature->id],
            fn () => $readingManager->update($request->user(), $literature, $request->validated()), $message);
        $readingList = $action['result'];

        if ($request->expectsJson()) {
            return response()->json(['reading_status' => $readingList->status, 'feedback' => $action['feedback']]);
        }

        if ($request->validated('return_to') === 'readlist') {
            return redirect()->route('profiles.readlist', $request->user())
                ->with('action_feedback', $action['feedback'])
                ->with('success', 'This literature has been added to your Readlist.');
        }

        return redirect()->route('literatures.show', $literature)
            ->with('action_feedback', $action['feedback'])
            ->with('success', 'Your reading status and progress have been saved.');
    }

    public function destroy(
        Request $request,
        Literature $literature,
        ActivityRecorder $activityRecorder,
        CanonicalWorkIdentity $canonicalIdentity,
        ActionFeedback $feedback,
    ): RedirectResponse|JsonResponse {
        $action = $feedback->run($request->user(), 'reading', ['literature_id' => $literature->id], function () use ($request, $literature, $activityRecorder, $canonicalIdentity): ?string {
            $readingLists = $request->user()->readingLists()
                ->whereIn('literature_id', $canonicalIdentity->equivalentLiteratureIds($literature))
                ->lockForUpdate()
                ->get();

            if ($readingLists->count() > 1) {
                throw ValidationException::withMessages([
                    'status' => 'This work has conflicting legacy reading entries. Run the interaction audit and resolve them before deleting.',
                ]);
            }

            $readingList = $readingLists->first();

            if ($readingList === null) {
                return null;
            }

            $previousStatus = $readingList->status;
            $activityRecorder->removeReadingStatus($request->user(), $literature);
            $readingList->delete();

            return $previousStatus;
        }, 'Removed from your reading activity.');
        $action['feedback']['message'] = match ($action['result']) {
            'want_to_read' => 'Removed from your Readlist.',
            'completed' => 'Completed status removed.',
            default => 'Removed from your reading activity.',
        };

        if ($request->expectsJson()) {
            return response()->json(['reading_status' => null, 'feedback' => $action['feedback']]);
        }

        return redirect()->route('literatures.show', $literature)
            ->with('action_feedback', $action['feedback'])
            ->with('success', 'This literature has been removed from your reading activity.');
    }
}
