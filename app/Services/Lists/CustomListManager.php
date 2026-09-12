<?php

namespace App\Services\Lists;

use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomListManager
{
    public function __construct(private readonly CanonicalWorkIdentity $identity) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $user, array $attributes): CustomList
    {
        return DB::transaction(function () use ($user, $attributes): CustomList {
            $list = $user->customLists()->create([
                'title' => $attributes['title'],
                'slug' => $this->uniqueSlug((string) $attributes['title']),
                'description' => $attributes['description'] ?? null,
                'is_private' => (bool) ($attributes['is_private'] ?? false),
            ]);

            if (isset($attributes['literature_id'])) {
                $this->add($list, Literature::query()->findOrFail($attributes['literature_id']));
            }

            return $list;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(CustomList $list, array $attributes): CustomList
    {
        $list->update([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'is_private' => (bool) ($attributes['is_private'] ?? false),
        ]);

        return $list->refresh();
    }

    public function add(CustomList $list, Literature $literature): CustomListItem
    {
        $canonicalWork = $this->identity->canonicalWork($literature);

        return DB::transaction(function () use ($list, $literature, $canonicalWork): CustomListItem {
            $existing = $list->items()
                ->where('canonical_work_id', $canonicalWork->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $position = ((int) $list->items()->lockForUpdate()->max('position')) + 1;

            return $list->items()->create([
                'canonical_work_id' => $canonicalWork->id,
                'literature_id' => $canonicalWork->preferred_literature_id ?? $literature->id,
                'position' => $position,
            ]);
        });
    }

    /** @param list<int> $itemIds */
    public function reorder(CustomList $list, array $itemIds): void
    {
        DB::transaction(function () use ($list, $itemIds): void {
            $requestedIds = collect($itemIds)->map(fn ($id): int => (int) $id)->values();
            $storedItems = $list->items()
                ->whereKey($requestedIds)
                ->lockForUpdate()
                ->get(['id', 'position']);

            if ($storedItems->count() !== $requestedIds->count()) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Every item must belong to this list.',
                ]);
            }

            $temporaryOffset = ((int) $list->items()->max('position')) + count($itemIds) + 1;
            $positions = $storedItems->pluck('position')->sort()->values();

            foreach ($itemIds as $index => $itemId) {
                $list->items()->whereKey($itemId)->update(['position' => $temporaryOffset + $index]);
            }

            foreach ($itemIds as $index => $itemId) {
                $list->items()->whereKey($itemId)->update(['position' => $positions[$index]]);
            }
        });
    }

    public function remove(CustomList $list, CustomListItem $item): void
    {
        DB::transaction(function () use ($list, $item): void {
            $item->delete();

            $list->items()->orderBy('position')->get()->each(
                fn (CustomListItem $remaining, int $index) => $remaining->update(['position' => $index + 1]),
            );
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'list';
        $slug = $base;
        $suffix = 2;

        while (CustomList::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
