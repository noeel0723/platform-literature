<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LiteratureController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        'book' => 'Book',
        'novel' => 'Novel',
        'western-comic' => 'Western Comic',
        'manga' => 'Manga',
        'manhwa' => 'Manhwa',
        'light-novel' => 'Light Novel',
    ];

    public function index(Request $request, CatalogSyncService $catalogSync): View
    {
        $query = trim((string) $request->query('q', ''));
        $selectedType = trim((string) $request->query('type', ''));
        $unavailableSources = [];

        if ($query !== '' && in_array($selectedType, ['', 'book', 'novel'], true)) {
            try {
                $catalogSync->syncGoogleBooks($query, $selectedType === '' ? 'all' : $selectedType);
            } catch (LiteratureSourceUnavailable) {
                $unavailableSources[] = 'Google Books';
            }
        }

        if ($query !== '' && in_array($selectedType, ['', 'manga', 'manhwa', 'light-novel'], true)) {
            try {
                $catalogSync->syncAniList($query, $selectedType === '' ? 'all' : $selectedType);
            } catch (LiteratureSourceUnavailable) {
                $unavailableSources[] = 'AniList';
            }
        }

        if ($query !== '' && in_array($selectedType, ['', 'western-comic'], true)) {
            try {
                $catalogSync->syncComicVine($query);
            } catch (LiteratureSourceUnavailable) {
                $unavailableSources[] = 'Comic Vine';
            }
        }

        $sourceWarning = $unavailableSources === []
            ? null
            : implode(' and ', array_unique($unavailableSources))
                .' cannot be reached right now. Results from the local catalog are still available.';

        $literatures = Literature::query()
            ->with(['apiSource', 'authors', 'categories'])
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $search) use ($query): void {
                    $search
                        ->where('title', 'like', "%{$query}%")
                        ->orWhere('original_title', 'like', "%{$query}%")
                        ->orWhereHas('authors', function (Builder $authors) use ($query): void {
                            $authors->where('name', 'like', "%{$query}%");
                        })
                        ->orWhereHas('categories', function (Builder $categories) use ($query): void {
                            $categories->where('name', 'like', "%{$query}%");
                        });
                });
            })
            ->when($selectedType !== '', function (Builder $builder) use ($selectedType): void {
                $builder->where('type', $selectedType);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Literature $literature): array => $this->present($literature));

        return view('catalog.index', [
            'literatures' => $literatures,
            'query' => $query,
            'selectedType' => $selectedType,
            'sourceWarning' => $sourceWarning,
            'types' => self::TYPES,
        ]);
    }

    public function show(Literature $literature): View
    {
        $userId = request()->user()?->id ?? 0;

        $literature->load([
            'apiSource',
            'authors',
            'categories',
            'reviews' => fn ($reviews) => $reviews
                ->with([
                    'user',
                    'likes' => fn ($likes) => $likes->where('user_id', $userId),
                ])
                ->withCount('likes'),
        ]);

        $readingList = request()->user()?->readingLists()
            ->whereBelongsTo($literature)
            ->with('progress')
            ->first();
        $currentReview = request()->user()?->reviews()
            ->whereBelongsTo($literature)
            ->first();
        $discussions = $literature->discussions()
            ->with([
                'user',
                'likes' => fn ($likes) => $likes->where('user_id', $userId),
                'topLevelComments.user',
                'topLevelComments.replies.user',
            ])
            ->withCount(['comments', 'likes'])
            ->latest()
            ->limit(20)
            ->get();

        return view('catalog.show', [
            'literature' => $this->present($literature),
            'readingList' => $readingList,
            'readingStatuses' => ReadingList::STATUS_LABELS,
            'reviews' => $literature->reviews->sortByDesc('created_at')->values(),
            'currentReview' => $currentReview,
            'averageRating' => $literature->reviews->avg('rating'),
            'discussions' => $discussions,
            'discussionCount' => $literature->discussions()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Literature $literature): array
    {
        $authorNames = $literature->authors->pluck('name');
        $displayTitle = $literature->original_title ?? $literature->title;
        $metadataIsEnglish = $literature->apiSource->key !== 'google-books'
            || $literature->language === null
            || $literature->language === 'en'
            || $literature->synopsis_source_name === 'Wikipedia EN';
        $formatLabels = [
            'Buku' => 'Book',
            'Komik Barat' => 'Western Comic',
            'Manhwa satu bab' => 'One-shot Manhwa',
        ];
        $languageLabels = [
            'en' => 'English',
            'id' => 'Indonesian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
        ];

        return [
            'slug' => $literature->slug,
            'title' => $displayTitle,
            'edition_title' => $displayTitle === $literature->title ? null : $literature->title,
            'year' => $literature->publication_year === null ? 'Year unavailable' : (string) $literature->publication_year,
            'type' => $literature->type,
            'type_label' => self::TYPES[$literature->type] ?? Str::headline($literature->type),
            'author' => $authorNames->isEmpty() ? 'Author unavailable' : $authorNames->implode(' & '),
            'authors' => $authorNames->all(),
            'source' => $literature->apiSource->name,
            'tagline' => $metadataIsEnglish ? $literature->tagline ?? 'Short description unavailable.' : 'Short description unavailable in English.',
            'synopsis' => $metadataIsEnglish ? $literature->synopsis ?? 'Synopsis unavailable from the metadata source.' : 'Synopsis unavailable in English.',
            'synopsis_source_name' => $metadataIsEnglish ? $literature->synopsis_source_name : null,
            'synopsis_source_url' => $metadataIsEnglish ? $literature->synopsis_source_url : null,
            'publisher' => $literature->publisher ?? 'Unavailable',
            'language' => $languageLabels[$literature->language] ?? $literature->language ?? 'Unavailable',
            'format' => $formatLabels[$literature->format] ?? $literature->format ?? self::TYPES[$literature->type] ?? Str::headline($literature->type),
            'genres' => $literature->categories->pluck('name')->all(),
            'identifier' => $literature->identifier ?? $literature->external_id,
            'cover_url' => $literature->cover_url,
            'theme' => $literature->theme,
            'initials' => $this->initials($displayTitle),
        ];
    }

    private function initials(string $title): string
    {
        $words = Str::of($title)->squish()->explode(' ')->filter();

        if ($words->count() === 1) {
            return Str::upper(Str::substr((string) $words->first(), 0, 2));
        }

        return Str::upper(
            Str::substr((string) $words->first(), 0, 1)
            .Str::substr((string) $words->last(), 0, 1),
        );
    }
}
