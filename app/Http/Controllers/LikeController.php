<?php

namespace App\Http\Controllers;

use App\Models\CustomList;
use App\Models\Discussion;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LikeController extends Controller
{
    public function storeList(Request $request, CustomList $customList): RedirectResponse
    {
        Gate::authorize('like', $customList);
        $customList->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return $this->redirectToList($request, $customList);
    }

    public function destroyList(Request $request, CustomList $customList): RedirectResponse
    {
        Gate::authorize('like', $customList);
        $customList->likes()->where('user_id', $request->user()->id)->delete();

        return $this->redirectToList($request, $customList);
    }

    private function redirectToList(Request $request, CustomList $customList): RedirectResponse
    {
        $parameters = [];
        foreach (['page', 'comments_page'] as $page) {
            if ($request->integer($page) > 1) {
                $parameters[$page] = $request->integer($page);
            }
        }

        return redirect()->route('custom-lists.show', [$customList, ...$parameters]);
    }

    public function storeReview(Request $request, Review $review): RedirectResponse
    {
        $review->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return redirect()->to(route('literatures.show', $review->literature).'#review-'.$review->id)
            ->with('success', 'Review liked.');
    }

    public function destroyReview(Request $request, Review $review): RedirectResponse
    {
        $review->likes()->where('user_id', $request->user()->id)->delete();

        return redirect()->to(route('literatures.show', $review->literature).'#review-'.$review->id)
            ->with('success', 'Review like removed.');
    }

    public function storeDiscussion(Request $request, Discussion $discussion): RedirectResponse
    {
        $discussion->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return redirect()->to(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id)
            ->with('success', 'Discussion liked.');
    }

    public function destroyDiscussion(Request $request, Discussion $discussion): RedirectResponse
    {
        $discussion->likes()->where('user_id', $request->user()->id)->delete();

        return redirect()->to(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id)
            ->with('success', 'Discussion like removed.');
    }
}
