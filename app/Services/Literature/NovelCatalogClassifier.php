<?php

namespace App\Services\Literature;

use App\Models\Literature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class NovelCatalogClassifier
{
    private const EXCLUDED_TITLES = [
        'unofficial', 'study guide', 'study notes', 'workbook', 'summary',
        'teacher guide', 'teacher s guide',
        'lesson plan', 'lesson plans', 'reading guide', 'reader guide',
        'reader s guide', 'companion guide',
        'critical perspectives', 'literary criticism', 'coloring book', 'colouring book',
        'activity book', 'quiz book', 'musical script', 'screenplay',
    ];

    private const EXCLUDED_CATEGORIES = [
        'nonfiction', 'non fiction', 'literary criticism', 'literary collections',
        'study aids', 'education', 'reference', 'biography autobiography',
        'biography and autobiography', 'comics graphic novels', 'comics and graphic novels',
        'language arts disciplines', 'language arts and disciplines',
    ];

    /** @param Builder<Literature> $query */
    public function constrainSearch(Builder $query, string $titleExpression): void
    {
        $query->where(function (Builder $eligible) use ($titleExpression): void {
            $eligible->where('literatures.type', '!=', 'novel')
                ->orWhere(function (Builder $novels) use ($titleExpression): void {
                    $this->excludePhrases($novels, $titleExpression, self::EXCLUDED_TITLES);
                    $novels->whereDoesntHave('categories', function (Builder $categories): void {
                        $categories->where(function (Builder $excluded): void {
                            $this->excludePhrases($excluded, 'categories.name', self::EXCLUDED_CATEGORIES, true);
                        });
                    });
                });
        });
    }

    /** @param list<string> $phrases */
    private function excludePhrases(Builder $query, string $expression, array $phrases, bool $matchAny = false): void
    {
        $normalized = "LOWER(COALESCE({$expression}, ''))";

        foreach (["'", '’', '-', '/', '&', '.', ',', ':', ';', '!', '?', '(', ')'] as $punctuation) {
            $literal = str_replace("'", "''", $punctuation);
            $normalized = "REPLACE({$normalized}, '{$literal}', ' ')";
        }

        for ($pass = 0; $pass < 3; $pass++) {
            $normalized = "REPLACE({$normalized}, '  ', ' ')";
        }

        $padded = $query->getConnection()->getDriverName() === 'sqlite'
            ? "(' ' || {$normalized} || ' ')"
            : "CONCAT(' ', {$normalized}, ' ')";

        foreach ($phrases as $phrase) {
            if ($matchAny) {
                $query->orWhereRaw("{$padded} LIKE ?", ['% '.$phrase.' %']);
            } else {
                $query->whereRaw("{$padded} NOT LIKE ?", ['% '.$phrase.' %']);
            }
        }
    }

    /**
     * @param  list<string>  $categories
     * @param  list<string>  $authors
     */
    public function accepts(
        string $title,
        array $categories,
        ?string $description,
        array $authors,
        ?string $publisher,
        ?string $identifier,
        string $query,
    ): bool {
        if ($authors === [] || ! $this->matchesQuery($title, $query)) {
            return false;
        }

        $normalizedTitle = $this->normalize($title);
        $normalizedCategories = $this->normalize(implode(' ', $categories));
        $normalizedDescription = $this->normalize($description ?? '');

        if ($this->isExplicitlyExcluded($title, $categories)) {
            return false;
        }

        if ($this->hasNovelEvidence($normalizedCategories, $normalizedDescription)) {
            return true;
        }

        if ($normalizedTitle === $this->normalize($query)) {
            return true;
        }

        return filled($publisher) || $this->isValidIsbn($identifier);
    }

    /** @param list<string> $categories */
    public function isExplicitlyExcluded(string $title, array $categories): bool
    {
        return $this->hasExcludedTitle($this->normalize($title))
            || $this->hasExcludedCategory($this->normalize(implode(' ', $categories)));
    }

    private function matchesQuery(string $title, string $query): bool
    {
        $normalizedTitle = $this->normalize($title);
        $normalizedQuery = $this->normalize($query);

        if ($normalizedTitle === '' || $normalizedQuery === '') {
            return false;
        }

        if (Str::contains($normalizedTitle, $normalizedQuery)) {
            return true;
        }

        $queryWords = Str::of($normalizedQuery)->explode(' ')->filter()->unique();

        if ($queryWords->isEmpty()) {
            return false;
        }

        $matchedWords = $queryWords
            ->filter(fn (string $word): bool => Str::contains($normalizedTitle, $word))
            ->count();

        return $matchedWords / $queryWords->count() >= 0.75;
    }

    private function hasExcludedTitle(string $title): bool
    {
        return $this->containsExcludedPhrase($title, self::EXCLUDED_TITLES);
    }

    private function hasExcludedCategory(string $categories): bool
    {
        return $this->containsExcludedPhrase($categories, self::EXCLUDED_CATEGORIES);
    }

    /** @param list<string> $phrases */
    private function containsExcludedPhrase(string $value, array $phrases): bool
    {
        return Str::contains(' '.$value.' ', array_map(fn (string $phrase): string => ' '.$phrase.' ', $phrases));
    }

    private function hasNovelEvidence(string $categories, string $description): bool
    {
        return preg_match(
            '/\b(?:fiction|fiksi|novel|novels|romance|romansa|fantasy|fantasi|science fiction|juvenile fiction|young adult fiction)\b/u',
            $categories,
        ) === 1 || preg_match(
            '/\b(?:a novel|the novel|fantasy novel|science fiction novel|historical novel|romance novel|young adult novel|novel ini|sebuah novel|work of fiction|fictional story)\b/u',
            $description,
        ) === 1;
    }

    private function isValidIsbn(?string $identifier): bool
    {
        if (blank($identifier)) {
            return false;
        }

        $isbn = Str::upper(preg_replace('/[^0-9X]/i', '', $identifier) ?? '');

        if (preg_match('/^\d{13}$/', $isbn) === 1) {
            $sum = 0;

            foreach (str_split($isbn) as $position => $digit) {
                $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
            }

            return $sum % 10 === 0;
        }

        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split($isbn) as $position => $digit) {
            $value = $digit === 'X' ? 10 : (int) $digit;
            $sum += $value * (10 - $position);
        }

        return $sum % 11 === 0;
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
