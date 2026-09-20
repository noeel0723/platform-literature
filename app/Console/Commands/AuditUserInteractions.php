<?php

namespace App\Console\Commands;

use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditUserInteractions extends Command
{
    protected $signature = 'catalog:audit-user-interactions
        {--fix : Canonicalize safe favorite and custom-list duplicates}';

    protected $description = 'Report user interactions duplicated across literature records in the same canonical work';

    public function __construct(private readonly CanonicalWorkIdentity $identity)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $reviews = Review::query()->with('literature.sourceMapping.canonicalWork.preferredLiterature')->get();
        $readingLists = ReadingList::query()->with('literature.sourceMapping.canonicalWork.preferredLiterature')->get();
        $favoriteRows = DB::table('user_favorite_literatures')->orderBy('position')->get();
        $customItems = CustomListItem::query()
            ->with('literature.sourceMapping.canonicalWork.preferredLiterature')
            ->orderBy('position')
            ->get();
        $favoriteLiteratures = Literature::query()
            ->whereKey($favoriteRows->pluck('literature_id'))
            ->with('sourceMapping.canonicalWork.preferredLiterature')
            ->get()
            ->keyBy('id');

        $reviewConflicts = $this->duplicateGroups($reviews, 'user_id');
        $readingConflicts = $this->duplicateGroups($readingLists, 'user_id');
        $favoriteConflicts = $favoriteRows
            ->groupBy(fn (object $row): string => $row->user_id.'|'.$this->keyFor($favoriteLiteratures->get($row->literature_id)))
            ->filter(fn (Collection $rows): bool => $rows->count() > 1);
        $customListConflicts = $customItems
            ->groupBy(fn (CustomListItem $item): string => $item->custom_list_id.'|'.$this->keyFor($item->literature, $item->canonical_work_id))
            ->filter(fn (Collection $items): bool => $items->count() > 1);

        $this->table(
            ['Interaction', 'Duplicate groups', 'Resolution'],
            [
                ['reviews', $reviewConflicts->count(), 'manual review required'],
                ['reading_lists', $readingConflicts->count(), 'manual review required'],
                ['favorites', $favoriteConflicts->count(), $this->option('fix') ? 'fixed when safe' : 'report only'],
                ['custom_list_items', $customListConflicts->count(), $this->option('fix') ? 'fixed when safe' : 'report only'],
            ],
        );

        $this->reportConflicts('reviews', $reviewConflicts);
        $this->reportConflicts('reading_lists', $readingConflicts);

        if ($this->option('fix')) {
            $fixedFavorites = $this->fixFavorites($favoriteRows, $favoriteLiteratures);
            $fixedCustomItems = $this->fixCustomListItems($customItems);
            $this->info("Canonicalized {$fixedFavorites} favorite row(s) and {$fixedCustomItems} custom-list item(s).");
        } elseif ($favoriteConflicts->isNotEmpty() || $customListConflicts->isNotEmpty()) {
            $this->warn('Run again with --fix to canonicalize only safe favorite/custom-list duplicates.');
        }

        if ($reviewConflicts->isNotEmpty() || $readingConflicts->isNotEmpty()) {
            $this->error('Conflicting reviews or reading entries require manual resolution; no row was removed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param Collection<int, Review|ReadingList> $models */
    private function duplicateGroups(Collection $models, string $ownerColumn): Collection
    {
        return $models
            ->groupBy(fn (Review|ReadingList $model): string => $model->{$ownerColumn}.'|'.$this->keyFor($model->literature))
            ->filter(fn (Collection $group): bool => $group->count() > 1);
    }

    private function keyFor(?Literature $literature, ?int $fallbackCanonicalWorkId = null): string
    {
        if ($literature !== null) {
            return $this->identity->key($literature);
        }

        return $fallbackCanonicalWorkId === null
            ? 'missing'
            : 'canonical:'.$fallbackCanonicalWorkId;
    }

    private function reportConflicts(string $label, Collection $groups): void
    {
        foreach ($groups as $key => $models) {
            $this->warn(sprintf(
                '%s conflict %s: row ids [%s]',
                $label,
                $key,
                $models->pluck('id')->implode(', '),
            ));
        }
    }

    private function fixFavorites(Collection $rows, Collection $literatures): int
    {
        $changed = 0;

        DB::transaction(function () use ($rows, $literatures, &$changed): void {
            $groups = $rows->groupBy(fn (object $row): string => $row->user_id.'|'.$this->keyFor($literatures->get($row->literature_id)));

            foreach ($groups as $group) {
                $first = $group->sortBy('position')->first();
                $literature = $literatures->get($first->literature_id);

                if ($literature === null) {
                    continue;
                }

                $representativeId = (int) $this->identity->representative($literature)->getKey();

                if ($group->count() === 1 && (int) $first->literature_id === $representativeId) {
                    continue;
                }

                DB::table('user_favorite_literatures')
                    ->where('user_id', $first->user_id)
                    ->whereIn('literature_id', $group->pluck('literature_id'))
                    ->delete();
                DB::table('user_favorite_literatures')->insert([
                    'user_id' => $first->user_id,
                    'literature_id' => $representativeId,
                    'position' => $group->min('position'),
                    'created_at' => $first->created_at,
                    'updated_at' => now(),
                ]);
                $changed += $group->count();
            }
        });

        return $changed;
    }

    private function fixCustomListItems(Collection $items): int
    {
        $changed = 0;

        DB::transaction(function () use ($items, &$changed): void {
            $groups = $items->groupBy(fn (CustomListItem $item): string => $item->custom_list_id.'|'.$this->keyFor($item->literature, $item->canonical_work_id));

            foreach ($groups as $group) {
                /** @var CustomListItem $first */
                $first = $group->sortBy('position')->first();

                if ($first->literature === null || $first->literature->sourceMapping === null) {
                    continue;
                }

                $representative = $this->identity->representative($first->literature);
                $canonicalWorkId = $first->literature->sourceMapping->canonical_work_id;

                if ($group->count() === 1
                    && $first->literature_id === $representative->id
                    && $first->canonical_work_id === $canonicalWorkId) {
                    continue;
                }

                CustomListItem::query()->whereKey($group->pluck('id')->reject(fn ($id): bool => $id === $first->id))->delete();
                $first->update([
                    'canonical_work_id' => $canonicalWorkId,
                    'literature_id' => $representative->id,
                    'position' => $group->min('position'),
                ]);
                $changed += $group->count();
            }
        });

        return $changed;
    }
}
