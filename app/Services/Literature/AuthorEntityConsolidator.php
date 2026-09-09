<?php

namespace App\Services\Literature;

use App\Models\Author;
use App\Models\AuthorAlias;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final class AuthorEntityConsolidator
{
    public function __construct(private AuthorNameNormalizer $names) {}

    /**
     * @return array{groups: int, merged_authors: int, skipped_groups: int}
     */
    public function consolidate(bool $dryRun = false, ?string $name = null): array
    {
        $normalizedFilter = filled($name) ? $this->names->normalize($name) : null;
        $duplicateNames = Author::query()
            ->select('normalized_name')
            ->whereNotNull('normalized_name')
            ->where('normalized_name', '!=', '')
            ->when($normalizedFilter !== null, fn ($query) => $query->where('normalized_name', $normalizedFilter))
            ->groupBy('normalized_name')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('normalized_name')
            ->pluck('normalized_name');
        $summary = [
            'groups' => 0,
            'merged_authors' => 0,
            'skipped_groups' => 0,
        ];

        foreach ($duplicateNames as $normalizedName) {
            $authors = $this->authorsFor((string) $normalizedName);

            if ($this->hasConflictingIdentities($authors)) {
                $summary['skipped_groups']++;

                continue;
            }

            $summary['groups']++;
            $summary['merged_authors'] += $authors->count() - 1;

            if (! $dryRun) {
                $this->mergeGroup($authors);
            }
        }

        return $summary;
    }

    /** @return EloquentCollection<int, Author> */
    private function authorsFor(string $normalizedName): EloquentCollection
    {
        return Author::query()
            ->where('normalized_name', $normalizedName)
            ->with('aliases')
            ->withCount(['literatures', 'favoritedByUsers', 'aliases'])
            ->orderBy('id')
            ->get();
    }

    /** @param EloquentCollection<int, Author> $authors */
    private function hasConflictingIdentities(EloquentCollection $authors): bool
    {
        $externalEntityIds = $authors
            ->pluck('external_entity_id')
            ->filter()
            ->unique();

        if ($externalEntityIds->count() > 1) {
            return true;
        }

        return $authors
            ->flatMap->aliases
            ->filter(fn (AuthorAlias $alias): bool => filled($alias->external_id))
            ->groupBy('source')
            ->contains(fn ($aliases): bool => $aliases->pluck('external_id')->unique()->count() > 1);
    }

    /** @param EloquentCollection<int, Author> $authors */
    private function mergeGroup(EloquentCollection $authors): void
    {
        DB::transaction(function () use ($authors): void {
            $lockedAuthors = Author::query()
                ->whereKey($authors->modelKeys())
                ->lockForUpdate()
                ->get()
                ->load(['aliases'])
                ->loadCount(['literatures', 'favoritedByUsers', 'aliases']);
            $canonical = $this->canonicalAuthor($lockedAuthors);

            foreach ($lockedAuthors->where('id', '!=', $canonical->id) as $duplicate) {
                $this->preserveAlias($canonical, $duplicate->name);
                $this->moveLiteratureLinks($canonical, $duplicate);
                $this->moveFavoriteLinks($canonical, $duplicate);
                $this->moveAliases($canonical, $duplicate);
                $this->fillMissingMetadata($canonical, $duplicate);
                $duplicate->delete();
            }
        }, 3);
    }

    /** @param EloquentCollection<int, Author> $authors */
    private function canonicalAuthor(EloquentCollection $authors): Author
    {
        return $authors
            ->sort(function (Author $left, Author $right): int {
                $scoreComparison = $this->canonicalScore($right) <=> $this->canonicalScore($left);

                return $scoreComparison !== 0 ? $scoreComparison : $left->id <=> $right->id;
            })
            ->firstOrFail();
    }

    private function canonicalScore(Author $author): int
    {
        return (filled($author->external_entity_id) ? 10_000 : 0)
            + (filled($author->biography) ? 1_000 : 0)
            + (filled($author->image_url) ? 500 : 0)
            + ((int) $author->literatures_count * 10)
            + ((int) $author->favorited_by_users_count * 5)
            + (int) $author->aliases_count;
    }

    private function preserveAlias(Author $canonical, string $name): void
    {
        AuthorAlias::query()->firstOrCreate(
            [
                'author_id' => $canonical->id,
                'source' => 'legacy-import',
                'name' => $name,
            ],
            ['normalized_name' => $this->names->normalize($name)],
        );
    }

    private function moveLiteratureLinks(Author $canonical, Author $duplicate): void
    {
        $links = DB::table('author_literature')
            ->where('author_id', $duplicate->id)
            ->orderBy('id')
            ->get();

        foreach ($links as $link) {
            $existing = DB::table('author_literature')
                ->where('author_id', $canonical->id)
                ->where('literature_id', $link->literature_id)
                ->first();

            if ($existing === null) {
                DB::table('author_literature')
                    ->where('id', $link->id)
                    ->update(['author_id' => $canonical->id]);

                continue;
            }

            DB::table('author_literature')
                ->where('id', $existing->id)
                ->update([
                    'role' => $existing->role ?? $link->role,
                    'position' => min((int) $existing->position, (int) $link->position),
                    'updated_at' => now(),
                ]);
            DB::table('author_literature')->where('id', $link->id)->delete();
        }
    }

    private function moveFavoriteLinks(Author $canonical, Author $duplicate): void
    {
        $links = DB::table('user_favorite_authors')
            ->where('author_id', $duplicate->id)
            ->get();

        foreach ($links as $link) {
            $alreadyFavorite = DB::table('user_favorite_authors')
                ->where('user_id', $link->user_id)
                ->where('author_id', $canonical->id)
                ->exists();

            if ($alreadyFavorite) {
                DB::table('user_favorite_authors')
                    ->where('user_id', $link->user_id)
                    ->where('author_id', $duplicate->id)
                    ->delete();

                continue;
            }

            DB::table('user_favorite_authors')
                ->where('user_id', $link->user_id)
                ->where('author_id', $duplicate->id)
                ->update(['author_id' => $canonical->id]);
        }
    }

    private function moveAliases(Author $canonical, Author $duplicate): void
    {
        foreach ($duplicate->aliases as $alias) {
            $existing = AuthorAlias::query()
                ->where('id', '!=', $alias->id)
                ->where(function ($query) use ($canonical, $alias): void {
                    $query->when(
                        filled($alias->external_id),
                        fn ($identity) => $identity
                            ->where('source', $alias->source)
                            ->where('external_id', $alias->external_id),
                        fn ($identity) => $identity
                            ->where('author_id', $canonical->id)
                            ->where('source', $alias->source)
                            ->where('name', $alias->name),
                    );
                })
                ->first();

            if ($existing !== null) {
                if (blank($existing->source_url) && filled($alias->source_url)) {
                    $existing->update(['source_url' => $alias->source_url]);
                }

                $alias->delete();

                continue;
            }

            $alias->update(['author_id' => $canonical->id]);
        }
    }

    private function fillMissingMetadata(Author $canonical, Author $duplicate): void
    {
        $canonical->fill([
            'external_entity_id' => $canonical->external_entity_id ?? $duplicate->external_entity_id,
            'biography' => $canonical->biography ?? $duplicate->biography,
            'image_url' => $canonical->image_url ?? $duplicate->image_url,
        ]);
        $canonical->save();
    }
}
