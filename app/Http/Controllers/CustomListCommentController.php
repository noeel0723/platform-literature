<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomListCommentRequest;
use App\Models\CustomList;
use App\Models\CustomListComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomListCommentController extends Controller
{
    public function store(StoreCustomListCommentRequest $request, CustomList $customList): RedirectResponse
    {
        $customList->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return $this->redirectToComments($request, $customList);
    }

    public function destroy(Request $request, CustomList $customList, CustomListComment $comment): RedirectResponse
    {
        Gate::authorize('deleteComment', [$customList, $comment]);
        $comment->delete();

        return $this->redirectToComments($request, $customList);
    }

    private function redirectToComments(Request $request, CustomList $customList): RedirectResponse
    {
        $parameters = ['customList' => $customList];
        if ($request->integer('page') > 1) {
            $parameters['page'] = $request->integer('page');
        }

        return redirect()->to(route('custom-lists.show', $parameters).'#comments');
    }
}
