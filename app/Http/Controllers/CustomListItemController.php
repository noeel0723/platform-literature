<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderCustomListItemsRequest;
use App\Http\Requests\StoreCustomListItemRequest;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Services\Lists\CustomListManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CustomListItemController extends Controller
{
    public function store(
        StoreCustomListItemRequest $request,
        CustomList $customList,
        CustomListManager $manager,
    ): RedirectResponse {
        $manager->add($customList, Literature::query()->findOrFail($request->integer('literature_id')));

        return back()->with('success', 'Literature added to list.');
    }

    public function destroy(
        CustomList $customList,
        CustomListItem $customListItem,
        CustomListManager $manager,
    ): RedirectResponse {
        Gate::authorize('update', $customList);
        abort_unless($customListItem->custom_list_id === $customList->id, 404);
        $manager->remove($customList, $customListItem);

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
