<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\CanonicalWorkLink;

final class CanonicalWorkAvailabilityService
{
    /** @var list<string> */
    private const OFFICIAL_PROVIDERS = [
        'Google Books',
        'Google Play Books',
        'MANGA Plus',
        'WEBTOON',
        'VIZ',
        'Kodansha',
        'Marvel Unlimited',
        'DC Universe Infinite',
    ];

    /** @param list<NormalizedLiteratureLink> $links */
    public function sync(CanonicalWork $canonicalWork, array $links): int
    {
        $synced = 0;

        foreach ($links as $link) {
            if (! $this->isEligible($link)) {
                continue;
            }

            $attributes = [
                'canonical_work_id' => $canonicalWork->id,
                'provider' => $link->provider,
                'url' => $link->url,
                'link_type' => $link->type,
            ];
            $existing = CanonicalWorkLink::query()->where($attributes)->first();

            // A provider refresh must never downgrade an administrator-curated row.
            if ($existing !== null && $this->isCurated($existing->source)) {
                continue;
            }

            CanonicalWorkLink::query()->updateOrCreate($attributes, [
                'region' => $link->region,
                'language' => $link->language,
                'source' => $link->source,
                'is_official' => true,
                'is_active' => true,
                'verified_at' => now(),
            ]);
            $synced++;
        }

        return $synced;
    }

    public function hasVerifiedLinks(CanonicalWork $canonicalWork): bool
    {
        return $canonicalWork->links()
            ->where('is_official', true)
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->whereIn('link_type', array_keys(CanonicalWorkLink::TYPE_LABELS))
            ->get(['url'])
            ->contains(fn (CanonicalWorkLink $link): bool => $this->hasHttpUrl($link->url));
    }

    private function isEligible(NormalizedLiteratureLink $link): bool
    {
        return $link->isOfficial
            && in_array($link->provider, self::OFFICIAL_PROVIDERS, true)
            && array_key_exists($link->type, CanonicalWorkLink::TYPE_LABELS)
            && $this->hasHttpUrl($link->url);
    }

    private function hasHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function isCurated(?string $source): bool
    {
        return in_array(strtolower(trim((string) $source)), ['admin', 'curated', 'manual'], true);
    }
}
