<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscussionRequest;
use App\Models\Literature;
use App\Services\ActivityRecorder;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Http\RedirectResponse;

class DiscussionController extends Controller
{
    public function store(
        StoreDiscussionRequest $request,
        Literature $literature,
        ActivityRecorder $activityRecorder,
        CanonicalWorkIdentity $canonicalIdentity,
    ): RedirectResponse {
        $data = $request->validated();
        $routeLiterature = $literature;
        $literature = $canonicalIdentity->representative($literature);

        $discussion = $request->user()->discussions()->create([
            'literature_id' => $literature->id,
            'title' => trim($data['title']),
            'body' => trim($data['discussion_body']),
            'contains_spoiler' => (bool) ($data['discussion_contains_spoiler'] ?? false),
        ]);

        $activityRecorder->recordDiscussion($discussion);

        return redirect()->to(route('literatures.show', $routeLiterature).'#discussion-'.$discussion->id)
            ->with('success', 'Your discussion has been published.');
    }
}
