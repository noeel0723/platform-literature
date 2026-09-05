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
        'book' => 'Buku',
        'novel' => 'Novel',
        'western-comic' => 'Komik Barat',
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
            : implode(' dan ', array_unique($unavailableSources))
                .' sementara tidak dapat dihubungkan. Hasil dari katalog lokal tetap ditampilkan.';

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
        $literature->load(['apiSource', 'authors', 'categories']);

        $readingList = request()->user()?->readingLists()
            ->whereBelongsTo($literature)
            ->with('progress')
            ->first();

        return view('catalog.show', [
            'literature' => $this->present($literature),
            'readingList' => $readingList,
            'readingStatuses' => ReadingList::STATUS_LABELS,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Literature $literature): array
    {
        $authorNames = $literature->authors->pluck('name');

        return [
            'slug' => $literature->slug,
            'title' => $literature->title,
            'year' => $literature->publication_year === null ? 'Tahun belum tersedia' : (string) $literature->publication_year,
            'type' => $literature->type,
            'type_label' => self::TYPES[$literature->type] ?? Str::headline($literature->type),
            'author' => $authorNames->isEmpty() ? 'Pengarang belum tersedia' : $authorNames->implode(' & '),
            'authors' => $authorNames->all(),
            'source' => $literature->apiSource->name,
            'tagline' => $literature->tagline ?? 'Informasi ringkas belum tersedia.',
            'synopsis' => $literature->synopsis ?? 'Sinopsis belum tersedia dari sumber metadata.',
            'publisher' => $literature->publisher ?? 'Belum tersedia',
            'language' => $literature->language ?? 'Belum tersedia',
            'format' => $literature->format ?? self::TYPES[$literature->type] ?? Str::headline($literature->type),
            'genres' => $literature->categories->pluck('name')->all(),
            'identifier' => $literature->identifier ?? $literature->external_id,
            'cover_url' => $literature->cover_url,
            'theme' => $literature->theme,
            'initials' => $this->initials($literature->title),
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
