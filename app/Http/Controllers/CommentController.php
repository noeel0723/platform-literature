<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Discussion;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Discussion $discussion): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->comments()->create([
            'discussion_id' => $discussion->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => trim($data['comment_body']),
            'contains_spoiler' => (bool) ($data['comment_contains_spoiler'] ?? false),
        ]);

        return redirect()->to(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id)
            ->with('success', filled($data['parent_id'] ?? null) ? 'Your reply has been published.' : 'Your comment has been published.');
    }
}
