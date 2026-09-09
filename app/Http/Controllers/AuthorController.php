<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Literature;
use App\Services\Literature\AniListAuthorEnricher;
use App\Services\Literature\KnowledgeGraphEnricher;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AuthorController extends Controller
{
    public function __invoke(
        Author $author,
        KnowledgeGraphEnricher $knowledgeGraph,
        AniListAuthorEnricher $aniListAuthors,
    ): View {
        $entity = $knowledgeGraph->findAuthor($author->name);
        $isMangaCreator = $author->literatures()
            ->whereIn('type', ['manga', 'manhwa'])
            ->exists();
        $aniListAuthor = $author->image_url === null
            && $isMangaCreator
            && $entity?->imageUrl === null
            ? $aniListAuthors->find($author->name)
            : null;

        if (($entity !== null || $aniListAuthor !== null)
            && ($author->image_url === null || $author->biography === null)) {
            $author->fill([
                'image_url' => $author->image_url ?? $entity?->imageUrl ?? $aniListAuthor?->imageUrl,
                'biography' => $author->biography
                    ?? $entity?->detailedDescription
                    ?? $entity?->description
                    ?? $aniListAuthor?->biography,
            ]);
            $author->save();
        }

        $literatures = $author->literatures()
            ->whereIn('type', Literature::supportedTypes())
            ->canonicalRepresentatives()
            ->with(['apiSource', 'authors'])
            ->withAvg([
                'reviews' => fn (Builder $reviews) => $reviews->whereNull('hidden_at'),
            ], 'rating')
            ->orderByDesc('publication_year')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Literature $literature): array => $this->presentCard($literature));

        $author->loadCount(['literatures' => fn (Builder $literatures) => $literatures
            ->whereIn('type', Literature::supportedTypes())
            ->canonicalRepresentatives()]);

        return view('authors.show', [
            'author' => $author,
            'literatures' => $literatures,
            'profileSourceUrl' => $entity?->sourceUrl
                ?? $entity?->officialUrl
                ?? $aniListAuthor?->sourceUrl,
            'imageLicenseUrl' => $entity?->imageLicenseUrl,
        ]);
    }

    /** @return array<string, mixed> */
    private function presentCard(Literature $literature): array
    {
        $displayTitle = $literature->displayTitle();

        return [
            'slug' => $literature->slug,
            'title' => $displayTitle,
            'year' => $literature->publication_year === null ? '—' : (string) $literature->publication_year,
            'type_label' => $literature->typeLabel(),
            'author' => $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable',
            'source' => $literature->apiSource->name,
            'cover_url' => $literature->cover_url,
            'theme' => $literature->theme,
            'initials' => $this->initials($displayTitle),
            'rating' => $literature->reviews_avg_rating === null
                ? null
                : (float) $literature->reviews_avg_rating,
        ];
    }

    private function initials(string $title): string
    {
        return Str::of($title)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }
}
