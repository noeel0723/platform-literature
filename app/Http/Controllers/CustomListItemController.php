<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderCustomListItemsRequest;
use App\Http\Requests\StoreCustomListItemRequest;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Services\Lists\CustomListManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomListItemController extends Controller
{
    public function store(
        StoreCustomListItemRequest $request,
        CustomList $customList,
        CustomListManager $manager,
    ): RedirectResponse|JsonResponse {
        $manager->add($customList, Literature::query()->findOrFail($request->integer('literature_id')));

        if ($request->expectsJson()) {
            return response()->json(['contains' => true]);
        }

        return back()->with('success', 'Literature added to list.');
    }

    public function destroy(
        Request $request,
        CustomList $customList,
        CustomListItem $customListItem,
        CustomListManager $manager,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('update', $customList);
        abort_unless($customListItem->custom_list_id === $customList->id, 404);
        $manager->remove($customList, $customListItem);

        if ($request->expectsJson()) {
            return response()->json(['contains' => false]);
        }

        return back()->with('success', 'Literature removed from list.');
    }

    public function reorder(
        ReorderCustomListItemsRequest $request,
        CustomList $customList,
        CustomListManager $manager,
    ): RedirectResponse {
        $manager->reorder($customList, $request->validated('item_ids'));

        return back()->with('success', 'List order updated.');
    }
}
