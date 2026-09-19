<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Literature;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Services\Literature\LiteraturePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    public function __invoke(
        Request $request,
        Category $category,
        CanonicalLiteratureSearch $canonicalSearch,
        LiteraturePresenter $presenter,
    ): Response {
        $selectedType = trim((string) $request->query('type', ''));
        $selectedType = array_key_exists($selectedType, Literature::TYPE_LABELS) ? $selectedType : '';
        $selectedSort = trim((string) $request->query('sort', 'latest'));
        $selectedSort = in_array($selectedSort, ['latest', 'title', 'year'], true) ? $selectedSort : 'latest';
        $query = $canonicalSearch->query('', $selectedType, $category);

        match ($selectedSort) {
            'title' => $query->orderBy('title')->orderBy('id'),
            'year' => $query->orderByDesc('publication_year')->orderBy('title'),
            default => $query->latest('updated_at')->latest('id'),
        };

        $results = $query
            ->paginate(18)
            ->withQueryString()
            ->through(fn (Literature $literature): array => $presenter->present($literature));

        return Inertia::render('Catalog/Genre', [
            'genre' => [
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'literatures' => $results,
            'selectedType' => $selectedType,
            'selectedSort' => $selectedSort,
            'types' => Literature::TYPE_LABELS,
            'routes' => [
                'genre' => route('literatures.genre', $category),
                'catalog' => route('literatures.index'),
            ],
        ]);
    }
}
