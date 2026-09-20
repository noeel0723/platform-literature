<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReadingListRequest;
use App\Models\Literature;
use App\Services\ActivityRecorder;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadingListController extends Controller
{
    public function update(
        UpdateReadingListRequest $request,
        Literature $literature,
        ReadingManager $readingManager,
    ): RedirectResponse {
        $readingManager->update($request->user(), $literature, $request->validated());

        if ($request->validated('return_to') === 'readlist') {
            return redirect()->route('profiles.readlist', $request->user())
                ->with('success', 'This literature has been added to your Readlist.');
        }

        return redirect()->route('literatures.show', $literature)
            ->with('success', 'Your reading status and progress have been saved.');
    }

    public function destroy(
        Request $request,
        Literature $literature,
        ActivityRecorder $activityRecorder,
        CanonicalWorkIdentity $canonicalIdentity,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $literature, $activityRecorder, $canonicalIdentity): void {
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
                return;
            }

            $activityRecorder->removeReadingStatus($request->user(), $literature);
            $readingList->delete();
        });

        return redirect()->route('literatures.show', $literature)
            ->with('success', 'This literature has been removed from your reading activity.');
    }
}
