<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReadingListRequest;
use App\Models\Literature;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\RedirectResponse;

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
}
