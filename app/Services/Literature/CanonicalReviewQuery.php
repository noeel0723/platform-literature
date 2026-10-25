<?php

namespace App\Services\Literature;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class CanonicalReviewQuery
{
    /**
     * Keep the latest visible review per reader before applying written-only
     * filters, summary aggregates or pagination. Never hydrate the full history.
     *
     * @param  list<int>  $literatureIds
     * @return Builder<Review>
     */
    public function query(array $literatureIds): Builder
    {
        return Review::query()
            ->whereIn('reviews.literature_id', $literatureIds)
            ->whereNull('reviews.hidden_at')
            ->whereNotExists(function (QueryBuilder $newer) use ($literatureIds): void {
                $newer->selectRaw('1')
                    ->from('reviews as newer_reviews')
                    ->whereColumn('newer_reviews.user_id', 'reviews.user_id')
                    ->whereIn('newer_reviews.literature_id', $literatureIds)
                    ->whereNull('newer_reviews.hidden_at')
                    ->where(function (QueryBuilder $moreRecent): void {
                        $moreRecent->whereColumn('newer_reviews.updated_at', '>', 'reviews.updated_at')
                            ->orWhere(function (QueryBuilder $tied): void {
                                $tied->whereColumn('newer_reviews.updated_at', 'reviews.updated_at')
                                    ->whereColumn('newer_reviews.id', '>', 'reviews.id');
                            });
                    });
            });
    }
}
