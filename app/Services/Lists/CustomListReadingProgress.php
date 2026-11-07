<?php

namespace App\Services\Lists;

use App\Models\CustomList;
use App\Models\User;
use Illuminate\Database\Query\Builder;

class CustomListReadingProgress
{
    /** @return array{completed: int, total: int, percentage: int, item_ids: array<int>}|null */
    public function forViewer(CustomList $list, ?User $viewer, int $total, array $visibleItemIds): ?array
    {
        if ($viewer === null) {
            return null;
        }

        // EXISTS counts each list item once, even when several API editions are completed.
        $completedItems = $list->items()->whereExists(function (Builder $reading) use ($viewer): void {
            $reading->selectRaw('1')->from('reading_lists')
                ->where('reading_lists.user_id', $viewer->id)->where('reading_lists.status', 'completed')
                ->where(function (Builder $matches): void {
                    $matches->whereColumn('reading_lists.literature_id', 'custom_list_items.literature_id')
                        ->orWhereExists(fn (Builder $mapping) => $mapping->selectRaw('1')->from('literature_source_mappings')
                            ->whereColumn('literature_source_mappings.literature_id', 'reading_lists.literature_id')
                            ->whereColumn('literature_source_mappings.canonical_work_id', 'custom_list_items.canonical_work_id'))
                        ->orWhereExists(fn (Builder $canonical) => $canonical->selectRaw('1')->from('canonical_works')
                            ->whereColumn('canonical_works.id', 'custom_list_items.canonical_work_id')
                            ->whereColumn('canonical_works.preferred_literature_id', 'reading_lists.literature_id'));
                });
        });
        $completed = (clone $completedItems)->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total === 0 ? 0 : (int) floor($completed * 100 / $total),
            'item_ids' => $visibleItemIds === [] ? [] : $completedItems->whereKey($visibleItemIds)->pluck('custom_list_items.id')->all(),
        ];
    }
}
