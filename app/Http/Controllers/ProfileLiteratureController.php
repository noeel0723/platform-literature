<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Support\ProfilePagePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileLiteratureController extends Controller
{
    public function __invoke(User $user, ProfilePagePresenter $presenter, Request $request): Response
    {
        $genreSlug = Str::slug(trim((string) $request->query('genre', '')));
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

        $completedLiterature = $user->readingLists()
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
            ])
            ->latest('completed_at')
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
            'genres' => $genres->all(),
        ]);
    }
}
