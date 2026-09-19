<?php

namespace App\Services\Literature;

use App\Models\Category;
use App\Models\Literature;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class LiteraturePresenter
{
    /** @return array<string, mixed> */
    public function present(Literature $literature): array
    {
        $authorNames = $literature->authors->pluck('name');
        $displayTitle = $literature->displayTitle();
        $displayLanguage = $literature->displayLanguage();
        $metadataOverride = $literature->effectiveMetadataOverride();
        $hasCuratedSynopsis = filled($metadataOverride?->synopsis);
        $metadataIsEnglish = $hasCuratedSynopsis
            || $literature->apiSource->key !== 'google-books'
            || $displayLanguage === null
            || $displayLanguage === 'en'
            || $literature->synopsis_source_name === 'Wikipedia EN';
        $formatLabels = [
            'Book' => 'Novel',
            'Buku' => 'Novel',
            'Light Novel' => 'Novel',
            'Light novel' => 'Novel',
            'Komik Barat' => 'Comic',
            'Western Comic' => 'Comic',
            'Manhwa satu bab' => 'One-shot Manhwa',
        ];
        $languageLabels = [
            'en' => 'English',
            'id' => 'Indonesian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
        ];
        $categories = $this->categories($literature);

        return [
            'id' => $literature->id,
            'slug' => $literature->slug,
            'url' => route('literatures.show', $literature),
            'title' => $displayTitle,
            'edition_title' => $literature->alternateTitle(),
            'alternate_title_label' => in_array($literature->type, ['manga', 'manhwa'], true)
                ? 'Original title'
                : 'Edition title',
            'year' => $literature->displayPublicationYear() === null ? 'Year unavailable' : (string) $literature->displayPublicationYear(),
            'type' => $literature->type,
            'type_label' => $literature->typeLabel(),
            'author' => $authorNames->isEmpty() ? 'Author unavailable' : $authorNames->implode(' & '),
            'authors' => $authorNames->all(),
            'author_links' => $literature->authors
                ->map(fn ($author): array => [
                    'name' => $author->name,
                    'url' => route('authors.show', $author),
                ])
                ->values()
                ->all(),
            'source' => $literature->apiSource->name,
            'tagline' => $metadataIsEnglish ? $literature->displayTagline() ?? 'Short description unavailable.' : 'Short description unavailable in English.',
            'synopsis' => $metadataIsEnglish ? $literature->displaySynopsis() ?? 'Synopsis unavailable from the metadata source.' : 'Synopsis unavailable in English.',
            'synopsis_source_name' => $hasCuratedSynopsis ? 'Literahaven curated metadata' : ($metadataIsEnglish ? $literature->synopsis_source_name : null),
            'synopsis_source_url' => $hasCuratedSynopsis ? $metadataOverride?->source_url : ($metadataIsEnglish ? $literature->synopsis_source_url : null),
            'publisher' => $literature->displayPublisher() ?? 'Unavailable',
            'language' => $languageLabels[$displayLanguage] ?? $displayLanguage ?? 'Unavailable',
            'format' => $formatLabels[$literature->displayFormat()] ?? $literature->displayFormat() ?? $literature->typeLabel(),
            'genres' => $categories->pluck('name')->all(),
            'genre_links' => $categories
                ->map(fn ($category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'url' => route('literatures.genre', $category),
                ])
                ->values()
                ->all(),
            'identifier' => $literature->identifier ?? $literature->external_id,
            'cover_url' => $literature->displayCoverUrl(),
            'backdrop_url' => $literature->displayBackdropUrl(),
            'theme' => $literature->theme,
            'is_curated' => $literature->hasCuratedMetadata(),
            'initials' => $this->initials($displayTitle),
        ];
    }

    /** @return Collection<int, Category> */
    private function categories(Literature $literature): Collection
    {
        $categories = $literature->categories;

        if (! $literature->relationLoaded('sourceMapping')) {
            return $categories->unique('id')->sortBy('name')->values();
        }

        $canonicalWork = $literature->sourceMapping?->canonicalWork;

        if ($canonicalWork === null || ! $canonicalWork->relationLoaded('literatures')) {
            return $categories->unique('id')->sortBy('name')->values();
        }

        return $categories
            ->merge($canonicalWork->literatures->flatMap->categories)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function initials(string $title): string
    {
        $words = Str::of($title)->squish()->explode(' ')->filter();

        if ($words->count() === 1) {
            return Str::upper(Str::substr((string) $words->first(), 0, 2));
        }

        return Str::upper(
            Str::substr((string) $words->first(), 0, 1)
            .Str::substr((string) $words->last(), 0, 1),
        );
    }
}
