<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReadingListRequest;
use App\Models\Literature;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReadingListController extends Controller
{
    public function update(
        UpdateReadingListRequest $request,
        Literature $literature,
        ReadingManager $readingManager,
    ): RedirectResponse {
        $readingManager->update($request->user(), $literature, $request->validated());

        return redirect()->route('literatures.show', $literature)
            ->with('success', 'Your reading status and progress have been saved.');
    }

    public function destroy(Request $request, Literature $literature): RedirectResponse
    {
        $request->user()->readingLists()
            ->whereBelongsTo($literature)
            ->first()
            ?->delete();

        return redirect()->route('literatures.show', $literature)
            ->with('success', 'This literature has been removed from your reading activity.');
    }
}
