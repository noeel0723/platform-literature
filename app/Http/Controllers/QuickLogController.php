<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickLogSearchRequest;
use App\Models\Literature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuickLogController extends Controller
{
    public function __invoke(QuickLogSearchRequest $request): JsonResponse
    {
        $query = Str::squish((string) $request->validated('q', ''));
        $normalizedQuery = Str::lower($query);

        $literatures = Literature::query()
            ->select(['id', 'slug', 'title', 'original_title', 'type', 'publication_year', 'cover_url', 'updated_at'])
            ->whereIn('type', Literature::supportedTypes())
            ->with(['authors:id,name'])
            ->with(['reviews' => fn (HasMany $reviews): HasMany => $reviews
                ->select(['id', 'user_id', 'literature_id', 'rating', 'body', 'contains_spoiler'])
                ->whereBelongsTo($request->user())
                ->whereNull('hidden_at')])
            ->when($query !== '', function (Builder $literatures) use ($query, $normalizedQuery): void {
                $literatures
                    ->where(function (Builder $matches) use ($query): void {
                        $matches
                            ->where('title', 'like', "%{$query}%")
                            ->orWhere('original_title', 'like', "%{$query}%")
                            ->orWhereHas('authors', fn (Builder $authors): Builder => $authors->where('name', 'like', "%{$query}%"));
                    })
                    ->orderByRaw(
                        'CASE WHEN LOWER(title) = ? OR LOWER(original_title) = ? THEN 0 ELSE 1 END',
                        [$normalizedQuery, $normalizedQuery],
                    );
            })
            ->latest('updated_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (Literature $literature): array {
                $review = $literature->reviews->first();

                return [
                    'id' => $literature->id,
                    'title' => $literature->original_title ?? $literature->title,
                    'year' => $literature->publication_year,
                    'type' => $literature->typeLabel(),
                    'cover_url' => $literature->cover_url,
                    'authors' => $literature->authors->pluck('name')->values()->all(),
                    'review_url' => route('reviews.update', $literature),
                    'review' => $review === null ? null : [
                        'rating' => $review->rating,
                        'body' => $review->body,
                        'contains_spoiler' => $review->contains_spoiler,
                    ],
                ];
            });

        return response()->json(['data' => $literatures]);
    }
}
