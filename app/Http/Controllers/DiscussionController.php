<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscussionRequest;
use App\Models\Literature;
use Illuminate\Http\RedirectResponse;

class DiscussionController extends Controller
{
    public function store(StoreDiscussionRequest $request, Literature $literature): RedirectResponse
    {
        $data = $request->validated();

        $discussion = $request->user()->discussions()->create([
            'literature_id' => $literature->id,
            'title' => trim($data['title']),
            'body' => trim($data['discussion_body']),
            'contains_spoiler' => (bool) ($data['discussion_contains_spoiler'] ?? false),
        ]);

        return redirect()->to(route('literatures.show', $literature).'#discussion-'.$discussion->id)
            ->with('success', 'Your discussion has been published.');
    }
}
