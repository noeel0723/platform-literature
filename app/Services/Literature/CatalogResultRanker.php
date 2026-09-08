<?php

namespace App\Services\Literature;

use App\Models\Literature;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CatalogResultRanker
{
    /**
     * @param  Collection<int, Literature>  $literatures
     * @return Collection<int, Literature>
     */
    public function rank(Collection $literatures, string $query): Collection
    {
        $query = Str::squish($query);

        if ($query === '') {
            return $literatures->values();
        }

        return $literatures
            ->sort(function (Literature $left, Literature $right) use ($query): int {
                $scoreComparison = $this->score($right, $query) <=> $this->score($left, $query);

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                $updatedComparison = $right->updated_at <=> $left->updated_at;

                return $updatedComparison !== 0 ? $updatedComparison : $right->id <=> $left->id;
            })
            ->unique(fn (Literature $literature): string => $this->canonicalKey($literature))
            ->values();
    }

    private function score(Literature $literature, string $query): int
    {
        $title = $this->normalize($literature->original_title ?? $literature->title);
        $normalizedQuery = $this->normalize($query);
        $score = 0;

        if ($title === $normalizedQuery) {
            $score += 500;
        } elseif (Str::startsWith($title, $normalizedQuery.' ')) {
            $score += 280;
        } elseif (Str::contains($title, $normalizedQuery)) {
            $score += 180;
        }

        $queryWords = Str::of($normalizedQuery)->explode(' ')->filter()->unique();
        $matchedWords = $queryWords->filter(fn (string $word): bool => Str::contains($title, $word))->count();

        if ($queryWords->isNotEmpty()) {
            $score += (int) round(($matchedWords / $queryWords->count()) * 120);
        }

        $score += $literature->authors->isNotEmpty() ? 90 : -120;
        $score += filled($literature->identifier) ? 70 : -30;
        $score += filled($literature->publisher) ? 35 : 0;
        $score += filled($literature->cover_url) ? 35 : -30;
        $score += $literature->publication_year !== null ? 20 : 0;
        $score += filled($literature->knowledge_graph_id) ? 180 : 0;

        $classificationText = Str::lower(implode(' ', [
            $literature->title,
            $literature->tagline,
            $literature->categories->pluck('name')->implode(' '),
        ]));

        if (preg_match(
            '/\b(unofficial|study guide|teacher(?:\x{2019}|\x{0027})?s? guide|workbook|summary|analysis|companion|quiz|lesson plans?|musical|coloring|colouring|activity book|journal|notebook|handbook)\b/u',
            $classificationText,
        ) === 1) {
            $score -= 320;
        }

        if (preg_match('/\b(fiction|fantasy|novel|children(?:\x{2019}|\x{0027})?s literature)\b/u', $classificationText) === 1) {
            $score += 50;
        }

        return $score;
    }

    private function canonicalKey(Literature $literature): string
    {
        $author = $literature->authors->first()?->name ?? '';

        return $this->normalize($literature->original_title ?? $literature->title)
            .'|'.$this->normalize($author);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
