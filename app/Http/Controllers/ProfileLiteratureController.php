<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Support\ProfilePagePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileLiteratureController extends Controller
{
    public function __invoke(User $user, ProfilePagePresenter $presenter, Request $request): Response
    {
        $genreSlug = Str::slug(trim((string) $request->query('genre', '')));
        $ratingOrder = match ($request->query('rating')) {
            'highest' => 'desc',
            'lowest' => 'asc',
            default => null,
        };
        $genre = $genreSlug === ''
            ? null
            : Category::query()->where('slug', $genreSlug)->first();
        $genres = Category::query()
            ->where(function (Builder $categories) use ($user): void {
                $categories
                    ->whereHas('literatures.readingLists', fn (Builder $readingLists) => $readingLists
                        ->whereBelongsTo($user)
                        ->where('status', 'completed'))
                    ->orWhereHas(
                        'literatures.sourceMapping.canonicalWork.literatures.readingLists',
                        fn (Builder $readingLists) => $readingLists
                            ->whereBelongsTo($user)
                            ->where('status', 'completed'),
                    );
            })
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->unique('slug')
            ->values()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
            ]);

        $completedLiteratureQuery = $user->readingLists()
            ->where('status', 'completed')
            ->when($genreSlug !== '', function ($readingLists) use ($genre): void {
                if ($genre === null) {
                    $readingLists->whereRaw('1 = 0');

                    return;
                }

                $readingLists->whereHas('literature', function (Builder $literatures) use ($genre): void {
                    $literatures->where(function (Builder $genreMatches) use ($genre): void {
                        $genreMatches
                            ->whereHas(
                                'categories',
                                fn (Builder $categories) => $categories->whereKey($genre->id),
                            )
                            ->orWhereHas(
                                'sourceMapping.canonicalWork.literatures.categories',
                                fn (Builder $categories) => $categories->whereKey($genre->id),
                            );
                    });
                });
            })
            ->with([
                'literature.authors',
                'literature.metadataOverride',
                'literature.sourceMapping.canonicalWork.metadataOverride',
                'literature.reviews' => fn ($reviews) => $reviews
                    ->whereBelongsTo($user)
                    ->whereNull('hidden_at'),
            ]);

        if ($ratingOrder !== null) {
            $completedLiteratureQuery
                ->select('reading_lists.*')
                ->leftJoin('reviews as profile_ratings', function (JoinClause $join) use ($user): void {
                    $join->on('profile_ratings.literature_id', '=', 'reading_lists.literature_id')
                        ->where('profile_ratings.user_id', '=', $user->id)
                        ->whereNull('profile_ratings.hidden_at');
                })
                ->orderByRaw('CASE WHEN profile_ratings.rating IS NULL THEN 1 ELSE 0 END')
                ->orderBy('profile_ratings.rating', $ratingOrder)
                ->orderByDesc('reading_lists.completed_at')
                ->orderByDesc('reading_lists.id');
        } else {
            $completedLiteratureQuery->latest('completed_at');
        }

        $completedLiterature = $completedLiteratureQuery
            ->paginate(48)
            ->withQueryString();

        $completedLiterature->through(fn ($item): array => [
            'id' => $item->id,
            'completed_at' => $item->completed_at?->utc()->toIso8601String(),
            'rating' => $item->literature->reviews->first()?->rating,
            'literature' => $presenter->literature($item->literature),
        ]);

        return Inertia::render('Profile/Literature', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'literature', request()->user()),
            'completedLiterature' => $completedLiterature,
            'activeGenre' => $genreSlug === '' ? null : [
                'name' => $genre?->name ?? Str::headline($genreSlug),
                'slug' => $genreSlug,
            ],
            'activeRating' => match ($ratingOrder) {
                'desc' => 'highest',
                'asc' => 'lowest',
                default => null,
            },
            'genres' => $genres->all(),
        ]);
    }
}
