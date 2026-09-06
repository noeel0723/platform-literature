<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertReviewRequest;
use App\Models\Literature;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function update(UpsertReviewRequest $request, Literature $literature): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->reviews()->updateOrCreate(
            ['literature_id' => $literature->id],
            [
                'rating' => $data['rating'],
                'body' => filled($data['body'] ?? null) ? trim((string) $data['body']) : null,
                'contains_spoiler' => (bool) ($data['contains_spoiler'] ?? false),
            ],
        );

        return redirect()->to(route('literatures.show', $literature).'#reviews')
            ->with('success', 'Your rating and review have been saved.');
    }
}
