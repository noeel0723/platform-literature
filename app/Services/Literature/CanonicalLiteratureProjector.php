<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CanonicalLiteratureProjector
{
    /** @var array<string, array<string, int>> */
    private const SOURCE_PRIORITY = [
        'novel' => [
            'manual-curated' => 120,
            'hardcover' => 90,
            'google-books' => 40,
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

        $preferredMapping = $rankedMappings->first();
        $preferred = $preferredMapping?->literature;

        if ($preferred !== null && $preferredMapping !== null) {
            $this->inheritFallbackCover($preferred, $preferredMapping, $rankedMappings);
        }

        if ($canonicalWork->preferred_literature_id !== $preferred?->id) {
            $canonicalWork->forceFill(['preferred_literature_id' => $preferred?->id])->save();
        }

        return $preferred;
    }

    /** @param Collection<int, LiteratureSourceMapping> $rankedMappings */
    private function inheritFallbackCover(
        Literature $preferred,
        LiteratureSourceMapping $preferredMapping,
        Collection $rankedMappings,
    ): void {
        $coverMapping = filled($preferred->cover_url)
            ? $rankedMappings->first(fn (LiteratureSourceMapping $mapping): bool => $mapping->id !== $preferredMapping->id
                && $mapping->literature?->cover_url === $preferred->cover_url)
            : $rankedMappings
                ->filter(fn (LiteratureSourceMapping $mapping): bool => filled($mapping->literature?->cover_url))
                ->sortByDesc(fn (LiteratureSourceMapping $mapping): int => $this->coverQualityScore($mapping->literature?->cover_url))
                ->first();

        if ($coverMapping === null) {
            return;
        }

        if (blank($preferred->cover_url)) {
            $preferred->forceFill(['cover_url' => $coverMapping->literature->cover_url])->save();
        }

        $fieldProvenance = $preferredMapping->field_provenance ?? [];
        $fieldProvenance['cover_url'] = $coverMapping->apiSource?->key
            ?? $coverMapping->literature->apiSource?->key
            ?? 'fallback';

        $preferredMapping->forceFill([
            'field_provenance' => $fieldProvenance,
            'quality_score' => $this->score($preferredMapping),
        ])->save();
    }

    private function score(LiteratureSourceMapping $mapping): int
    {
        $literature = $mapping->literature;
        $sourceKey = $mapping->apiSource?->key ?? $literature->apiSource?->key ?? '';
        $sourceScore = self::SOURCE_PRIORITY[$literature->type][$sourceKey] ?? 10;

        return $sourceScore
            + ($literature->authors->isNotEmpty() ? 60 : 0)
            + (filled($literature->identifier) ? 25 : 0)
            + $this->coverQualityScore($literature->cover_url)
            + (filled($literature->synopsis) ? 35 : 0)
            + (filled($literature->publisher) ? 15 : 0)
            + ($literature->publication_year !== null ? 10 : 0)
            + (filled($literature->original_title) ? 5 : 0)
            + (filled($literature->knowledge_graph_id) ? 30 : 0)
            + ($literature->categories->isNotEmpty() ? 10 : 0)
            + $this->editionQualityScore($literature->title)
            + (int) round($mapping->confidence * 10);
    }

    private function coverQualityScore(?string $coverUrl): int
    {
        if (blank($coverUrl)) {
            return 0;
        }

        $url = Str::lower($coverUrl);

        return match (true) {
            str_contains($url, 'assets.hardcover.app') => 60,
            str_contains($url, 'books.google') && preg_match('/(?:zoom=6|w=(?:9\d\d|[1-9]\d{3,}))/', $url) === 1 => 60,
            str_contains($url, 'books.google') && preg_match('/(?:zoom=[34]|w=[5-8]\d\d)/', $url) === 1 => 52,
            str_contains($url, 'books.google') && str_contains($url, 'zoom=1') => 15,
            default => 45,
        };
    }

    private function editionQualityScore(string $title): int
    {
        return preg_match(
            '/\b(?:unofficial|study guide|workbook|summary|analysis|companion|quiz|lesson plans?|musical|coloring|activity book|journal|notebook|collected works?|complete collections?)\b/i',
            $title,
        ) === 1 ? -80 : 0;
    }
}
