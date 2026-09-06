<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertReviewRequest;
use App\Models\Literature;
use App\Services\Reading\ReadingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function update(
        UpsertReviewRequest $request,
        Literature $literature,
        ReadingManager $readingManager,
    ): RedirectResponse {
        $data = $request->validated();

        DB::transaction(function () use ($request, $literature, $readingManager, $data): void {
            $request->user()->reviews()->updateOrCreate(
                ['literature_id' => $literature->id],
                [
                    'rating' => $data['rating'],
                    'body' => filled($data['body'] ?? null) ? trim((string) $data['body']) : null,
                    'contains_spoiler' => (bool) ($data['contains_spoiler'] ?? false),
                ],
            );

            $readingManager->update($request->user(), $literature, ['status' => 'completed']);
        });

        return redirect()->to(route('literatures.show', $literature).'#reviews')
            ->with('success', 'Your review has been saved and this literature is marked as completed.');
    }
}
