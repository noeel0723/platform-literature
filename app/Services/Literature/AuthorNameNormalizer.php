<?php

namespace App\Services\Literature;

use Illuminate\Support\Str;

final class AuthorNameNormalizer
{
    public function normalize(string $name): string
    {
        $normalized = Str::squish(html_entity_decode(strip_tags($name)));
        $normalized = Str::lower($normalized);
        $normalized = preg_replace('/[\.\x{FF0E}\'\x{2019}`]+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        $initials = '';

        foreach ($tokens as $token) {
            if (Str::length($token) === 1) {
                $initials .= $token;

                continue;
            }

            if ($initials !== '') {
                $parts[] = $initials;
                $initials = '';
            }

            $parts[] = $token;
        }

        if ($initials !== '') {
            $parts[] = $initials;
        }

        return implode(' ', $parts);
    }
}
