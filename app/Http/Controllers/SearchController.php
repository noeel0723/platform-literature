<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = Str::limit(Str::squish((string) $request->query('q', '')), 100, '');
        $literatures = collect();
        $authors = collect();
        $members = collect();

        if ($query !== '') {
            $literatures = Literature::query()
                ->with('authors')
                ->where(function (Builder $literatureQuery) use ($query): void {
                    $literatureQuery
                        ->where('title', 'like', "%{$query}%")
                        ->orWhere('original_title', 'like', "%{$query}%")
                        ->orWhereHas('authors', fn (Builder $authors) => $authors->where('name', 'like', "%{$query}%"))
                        ->orWhereHas('categories', fn (Builder $categories) => $categories->where('name', 'like', "%{$query}%"));
                })
                ->latest('updated_at')
                ->limit(12)
                ->get();

            $authors = Author::query()
                ->where(function (Builder $authorQuery) use ($query): void {
                    $authorQuery
                        ->where('name', 'like', "%{$query}%")
                        ->orWhereHas('literatures', function (Builder $literatures) use ($query): void {
                            $literatures
                                ->where('title', 'like', "%{$query}%")
                                ->orWhere('original_title', 'like', "%{$query}%");
                        });
                })
                ->withCount('literatures')
                ->with(['literatures' => fn ($literatures) => $literatures->latest('publication_year')->limit(3)])
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

        return view('search.index', compact('query', 'literatures', 'authors', 'members'));
    }
}
