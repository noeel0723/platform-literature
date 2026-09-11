<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request, CanonicalLiteratureSearch $canonicalSearch): Response
    {
        $query = Str::limit(Str::squish((string) $request->query('q', '')), 100, '');
        $scope = (string) $request->query('scope', 'all');
        $scope = in_array($scope, ['all', 'literature', 'authors', 'readers'], true) ? $scope : 'all';
        $literatures = collect();
        $authors = collect();
        $members = collect();

        if ($query !== '') {
            $literatures = $canonicalSearch->query($query)
                ->latest('updated_at')
                ->limit(12)
                ->get();

            $authors = Author::query()
                ->where(function (Builder $authorQuery) use ($query): void {
                    $authorQuery
                        ->where('name', 'like', "%{$query}%")
                        ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->where('name', 'like', "%{$query}%"))
                        ->orWhereHas('literatures', function (Builder $literatures) use ($query): void {
                            $literatures
                                ->whereIn('type', Literature::supportedTypes())
                                ->where(function (Builder $titles) use ($query): void {
                                    $titles
                                        ->where('title', 'like', "%{$query}%")
                                        ->orWhere('original_title', 'like', "%{$query}%");
                                });
                        });
                })
                ->withCount(['literatures' => fn (Builder $literatures) => $literatures
                    ->whereIn('type', Literature::supportedTypes())
                    ->canonicalRepresentatives()])
                ->with(['literatures' => fn ($literatures) => $literatures
                    ->whereIn('type', Literature::supportedTypes())
                    ->canonicalRepresentatives()
                    ->latest('publication_year')
                    ->limit(3)])
                ->orderBy('name')
                ->limit(8)
                ->get();

            $members = User::query()
                ->whereNull('deactivated_at')
                ->where(function (Builder $users) use ($query): void {
                    $users
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('username', 'like', "%{$query}%");
                })
                ->withCount([
                    'reviews' => fn (Builder $reviews) => $reviews->whereNull('hidden_at'),
                    'readingLists as completed_literature_count' => fn (Builder $readingLists) => $readingLists->where('status', 'completed'),
                ])
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $literatureResults = $literatures
            ->map(fn (Literature $literature): array => $this->presentLiterature($literature))
            ->values()
            ->all();
        $authorResults = $authors
            ->map(fn (Author $author): array => $this->presentAuthor($author))
            ->values()
            ->all();
        $readerResults = $members
            ->map(fn (User $member): array => $this->presentReader($member))
            ->values()
            ->all();

        return Inertia::render('Search/Index', [
            'query' => $query,
            'scope' => $scope,
            'literatures' => $literatureResults,
            'authors' => $authorResults,
            'readers' => $readerResults,
            'counts' => [
                'all' => count($literatureResults) + count($authorResults) + count($readerResults),
                'literature' => count($literatureResults),
                'authors' => count($authorResults),
                'readers' => count($readerResults),
            ],
            'routes' => [
                'search' => route('search.index'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentLiterature(Literature $literature): array
    {
        $title = $literature->displayTitle();
        $language = Str::lower((string) $literature->displayLanguage());

        return [
            'id' => $literature->id,
            'title' => $title,
            'url' => route('literatures.show', $literature),
            'cover_url' => $literature->displayCoverUrl(),
            'initials' => $this->initials($title),
            'year' => $literature->displayPublicationYear(),
            'author' => $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable',
            'synopsis' => in_array($language, ['', 'en', 'eng', 'english'], true)
                ? ($literature->displaySynopsis() ?: 'English synopsis unavailable.')
                : 'English synopsis unavailable.',
            'type_label' => $literature->typeLabel(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentAuthor(Author $author): array
    {
        return [
            'id' => $author->id,
            'name' => $author->name,
            'url' => route('authors.show', $author),
            'image_url' => $author->image_url,
            'initials' => $this->initials($author->name),
            'literatures_count' => $author->literatures_count,
            'literature_titles' => $author->literatures
                ->map(fn (Literature $literature): string => $literature->displayTitle())
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReader(User $reader): array
    {
        return [
            'id' => $reader->id,
            'name' => $reader->name,
            'username' => $reader->username,
            'url' => route('profiles.show', $reader),
            'avatar_url' => $reader->avatarUrl(),
            'initials' => $this->initials($reader->name),
            'completed_count' => $reader->completed_literature_count,
            'reviews_count' => $reader->reviews_count,
        ];
    }

    private function initials(string $value): string
    {
        return collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'LH';
    }
}
