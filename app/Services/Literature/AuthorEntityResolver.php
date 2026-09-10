<?php

namespace App\Services\Literature;

use App\Models\Author;
use App\Models\AuthorAlias;
use Illuminate\Support\Str;

final class AuthorEntityResolver
{
    public function __construct(private AuthorNameNormalizer $names) {}

    public function resolve(
        string $sourceKey,
        NormalizedAuthor $candidate,
        ?KnowledgeGraphEntity $entity = null,
    ): Author {
        $displayName = Str::squish($candidate->name);
        $normalizedName = $this->names->normalize($displayName);
        $externalEntityId = $candidate->externalEntityId ?? $entity?->id;

        $author = $this->findByExternalEntity($externalEntityId)
            ?? $this->findBySourceIdentity($sourceKey, $candidate->externalId)
            ?? $this->findByNormalizedName($normalizedName)
            ?? $this->findLegacyAuthor($normalizedName);

        if ($author === null) {
            $author = Author::query()->create([
                'name' => $this->canonicalName($displayName, $normalizedName, $entity),
                'normalized_name' => $normalizedName,
                'external_entity_id' => $externalEntityId,
                'slug' => $this->uniqueSlug($displayName),
                'biography' => $candidate->biography ?? $entity?->detailedDescription ?? $entity?->description,
                'image_url' => $candidate->imageUrl ?? $entity?->imageUrl,
            ]);
        } else {
            $author->fill([
                'normalized_name' => $author->normalized_name ?? $normalizedName,
                'external_entity_id' => $author->external_entity_id ?? $externalEntityId,
                'biography' => $author->biography
                    ?? $candidate->biography
                    ?? $entity?->detailedDescription
                    ?? $entity?->description,
                'image_url' => $author->image_url ?? $candidate->imageUrl ?? $entity?->imageUrl,
            ]);
            $author->save();
        }

        $this->storeAlias($author, $sourceKey, $candidate, $normalizedName);

        return $author;
    }

    private function findByExternalEntity(?string $externalEntityId): ?Author
    {
        if (blank($externalEntityId)) {
            return null;
        }

        return Author::query()
            ->where('external_entity_id', $externalEntityId)
            ->oldest('id')
            ->first();
    }

    private function findBySourceIdentity(string $sourceKey, ?string $externalId): ?Author
    {
        if (blank($externalId)) {
            return null;
        }

        return AuthorAlias::query()
            ->with('author')
            ->where('source', $sourceKey)
            ->where('external_id', $externalId)
            ->first()
            ?->author;
    }

    private function findByNormalizedName(string $normalizedName): ?Author
    {
        if ($normalizedName === '') {
            return null;
        }

        return Author::query()
            ->where('normalized_name', $normalizedName)
            ->oldest('id')
            ->first()
            ?? AuthorAlias::query()
                ->with('author')
                ->where('normalized_name', $normalizedName)
                ->oldest('id')
                ->first()
                ?->author;
    }

    private function findLegacyAuthor(string $normalizedName): ?Author
    {
        if ($normalizedName === '') {
            return null;
        }

        return Author::query()
            ->whereNull('normalized_name')
            ->oldest('id')
            ->get()
            ->first(fn (Author $author): bool => $this->names->normalize($author->name) === $normalizedName);
    }

    private function canonicalName(
        string $displayName,
        string $normalizedName,
        ?KnowledgeGraphEntity $entity,
    ): string {
        if ($entity !== null && $this->names->normalize($entity->name) === $normalizedName) {
            return $entity->name;
        }

        return $displayName;
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'author';
        $slug = $baseSlug;
        $suffix = 2;

        while (Author::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($baseSlug, 230, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function storeAlias(
        Author $author,
        string $sourceKey,
        NormalizedAuthor $candidate,
        string $normalizedName,
    ): void {
        $displayName = Str::squish($candidate->name);
        $identityByExternalId = filled($candidate->externalId)
            ? AuthorAlias::query()->where([
                'source' => $sourceKey,
                'external_id' => $candidate->externalId,
            ])->first()
            : null;
        $identityByName = AuthorAlias::query()->where([
            'author_id' => $author->id,
            'source' => $sourceKey,
            'name' => $displayName,
        ])->first();

        $identity = $identityByExternalId ?? $identityByName ?? new AuthorAlias;

        $identity->fill([
            'author_id' => $author->id,
            'name' => $identityByExternalId !== null && $identityByName !== null
                && ! $identityByExternalId->is($identityByName)
                    ? $identityByExternalId->name
                    : $displayName,
            'normalized_name' => $normalizedName,
            'source' => $sourceKey,
            'external_id' => $identity->external_id ?? $candidate->externalId,
            'source_url' => $candidate->sourceUrl ?? $identity->source_url,
        ]);
        $identity->save();
    }
}
