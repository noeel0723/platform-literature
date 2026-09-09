<?php

namespace App\Services\Literature;

use App\Models\Literature;
use Illuminate\Support\Str;

class LiteratureIdentityNormalizer
{
    public function normalizeTitle(string $title): string
    {
        $normalized = Str::lower(Str::squish(html_entity_decode(strip_tags($title))));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return Str::squish($normalized);
    }

    /**
     * @return list<array{scheme: string, value: string, source_key: string|null}>
     */
    public function identifiers(Literature $literature): array
    {
        $sourceKey = $literature->apiSource->key;
        $identifiers = [[
            'scheme' => "source:{$sourceKey}",
            'value' => trim($literature->external_id),
            'source_key' => $sourceKey,
        ]];

        if (filled($literature->knowledge_graph_id)) {
            $identifiers[] = [
                'scheme' => 'google-kg',
                'value' => trim($literature->knowledge_graph_id),
                'source_key' => 'google-knowledge-graph',
            ];
        }

        if (filled($literature->identifier)) {
            $identifiers[] = $this->normalizeCatalogIdentifier($literature->identifier, $sourceKey);
        }

        return collect($identifiers)
            ->filter(fn (array $identifier): bool => $identifier['value'] !== '')
            ->unique(fn (array $identifier): string => $identifier['scheme']."\0".$identifier['value'])
            ->values()
            ->all();
    }

    /** @return array{scheme: string, value: string, source_key: string|null} */
    private function normalizeCatalogIdentifier(string $identifier, string $sourceKey): array
    {
        $value = Str::squish($identifier);
        $looksLikeIsbn = preg_match('/^ISBN(?:-1[03])?\s*:?/i', $value) === 1
            || preg_match('/^[0-9X\-\s]+$/i', $value) === 1;
        $isbnValue = preg_replace('/^ISBN(?:-1[03])?\s*:?\s*/i', '', $value) ?? $value;
        $isbn = Str::upper(preg_replace('/[^0-9X]/i', '', $isbnValue) ?? '');

        if ($looksLikeIsbn && $this->isValidIsbn($isbn)) {
            return ['scheme' => 'isbn', 'value' => $isbn, 'source_key' => $sourceKey];
        }

        if ($looksLikeIsbn) {
            return [
                'scheme' => "catalog:{$sourceKey}",
                'value' => Str::lower($value),
                'source_key' => $sourceKey,
            ];
        }

        if (preg_match('/^([A-Z][A-Z0-9_-]*)\s*:\s*(.+)$/i', $value, $matches) === 1) {
            return [
                'scheme' => Str::lower(str_replace('_', '-', $matches[1])),
                'value' => Str::squish($matches[2]),
                'source_key' => $sourceKey,
            ];
        }

        return [
            'scheme' => "catalog:{$sourceKey}",
            'value' => Str::lower($value),
            'source_key' => $sourceKey,
        ];
    }

    private function isValidIsbn(string $isbn): bool
    {
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
}
