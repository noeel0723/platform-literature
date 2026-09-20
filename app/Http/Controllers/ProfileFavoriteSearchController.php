<?php

namespace App\Http\Controllers;

use App\Http\Requests\FavoriteSearchRequest;
use App\Models\Author;
use App\Models\Literature;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProfileFavoriteSearchController extends Controller
{
    private const RESULT_LIMIT = 20;

    public function literatures(
        FavoriteSearchRequest $request,
        CanonicalLiteratureSearch $canonicalSearch,
        ProfilePagePresenter $presenter,
    ): JsonResponse {
        $query = Str::squish((string) $request->validated('q'));
        $normalizedQuery = Str::lower($query);

        $results = $canonicalSearch->query($query)
            ->when($query !== '', fn ($literatures) => $literatures->orderByRaw(
                'CASE WHEN LOWER(title) = ? OR LOWER(original_title) = ? THEN 0 ELSE 1 END',
                [$normalizedQuery, $normalizedQuery],
            ))
            ->orderByRaw('COALESCE(original_title, title)')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (Literature $literature) use ($presenter): array {
                $item = $presenter->literature($literature);

                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'author' => $item['author'],
                    'year' => $item['year'],
                    'type_label' => $item['type_label'],
                    'cover_url' => $item['cover_url'],
                    'initials' => $item['initials'],
                ];
            })
            ->values();

        return response()->json(['results' => $results]);
    }

    public function authors(FavoriteSearchRequest $request): JsonResponse
    {
        $query = Str::squish((string) $request->validated('q'));
        $normalizedQuery = Str::lower($query);

        $results = Author::query()
            ->where('name', 'like', "%{$query}%")
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [$normalizedQuery])
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'name', 'image_url'])
            ->map(fn (Author $author): array => [
                'id' => $author->id,
                'name' => $author->name,
                'image_url' => $author->image_url,
                'initials' => $this->initials($author->name),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'LH';
    }
}
