<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;

final class CanonicalLiteratureProjector
{
    /** @var array<string, array<string, int>> */
    private const SOURCE_PRIORITY = [
        'novel' => [
            'google-books' => 60,
            'open-library' => 45,
        ],
        'manga' => [
            'anilist' => 60,
            'mangadex' => 45,
            'kitsu' => 35,
        ],
        'manhwa' => [
            'anilist' => 60,
            'mangadex' => 45,
            'kitsu' => 35,
        ],
        'western-comic' => [
            'comic-vine' => 60,
            'metron' => 45,
        ],
    ];

    public function refresh(CanonicalWork $canonicalWork): ?Literature
    {
        $canonicalWork->load([
            'sourceMappings.apiSource',
            'sourceMappings.literature.authors',
            'sourceMappings.literature.categories',
        ]);

        $rankedMappings = $canonicalWork->sourceMappings
            ->filter(fn (LiteratureSourceMapping $mapping): bool => $mapping->literature !== null)
            ->map(function (LiteratureSourceMapping $mapping): LiteratureSourceMapping {
                $score = $this->score($mapping);

                if ($mapping->quality_score !== $score) {
                    $mapping->forceFill(['quality_score' => $score])->save();
                }

                return $mapping;
            })
            ->sort(function (LiteratureSourceMapping $left, LiteratureSourceMapping $right): int {
                $scoreComparison = $right->quality_score <=> $left->quality_score;

                return $scoreComparison !== 0
                    ? $scoreComparison
                    : $left->literature_id <=> $right->literature_id;
            });

        $preferred = $rankedMappings->first()?->literature;

        if ($canonicalWork->preferred_literature_id !== $preferred?->id) {
            $canonicalWork->forceFill(['preferred_literature_id' => $preferred?->id])->save();
        }

        return $preferred;
    }

    private function score(LiteratureSourceMapping $mapping): int
    {
        $literature = $mapping->literature;
        $sourceKey = $mapping->apiSource?->key ?? $literature->apiSource?->key ?? '';
        $sourceScore = self::SOURCE_PRIORITY[$literature->type][$sourceKey] ?? 10;

        return $sourceScore
            + ($literature->authors->isNotEmpty() ? 60 : 0)
            + (filled($literature->identifier) ? 25 : 0)
            + (filled($literature->cover_url) ? 45 : 0)
            + (filled($literature->synopsis) ? 35 : 0)
            + (filled($literature->publisher) ? 15 : 0)
            + ($literature->publication_year !== null ? 10 : 0)
            + (filled($literature->original_title) ? 5 : 0)
            + (filled($literature->knowledge_graph_id) ? 30 : 0)
            + ($literature->categories->isNotEmpty() ? 10 : 0)
            + (int) round($mapping->confidence * 10);
    }
}
