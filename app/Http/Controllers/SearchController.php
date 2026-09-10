<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request, CanonicalLiteratureSearch $canonicalSearch): View
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

        return view('search.index', compact('query', 'scope', 'literatures', 'authors', 'members'));
    }
}
