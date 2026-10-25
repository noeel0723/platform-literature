<?php

namespace App\Support;

use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\CanonicalWorkIdentity;

final class ReviewDraftContext
{
    public function __construct(private readonly CanonicalWorkIdentity $identity) {}

    /** @return array{user_id: int, work_key: string, revision: string}|null */
    public function present(?User $user, Literature $literature, ?Review $review, ?string $completedAt): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'user_id' => (int) $user->id,
            'work_key' => $this->identity->key($literature),
            'revision' => hash('sha256', json_encode([
                $review?->id,
                $review?->updated_at?->format('Y-m-d H:i:s.u'),
                $review === null ? null : (float) $review->rating,
                $review?->body ?? '',
                (bool) $review?->contains_spoiler,
                $completedAt,
            ], JSON_THROW_ON_ERROR)),
        ];
    }
}
