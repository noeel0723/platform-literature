<?php

namespace App\Http\Controllers;

use App\Models\Literature;
use App\Services\Literature\LiteratureBrowseService;
use App\Services\Literature\LiteraturePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiteratureBrowseController extends Controller
{
    public function index(
        LiteratureBrowseService $browser,
        LiteraturePresenter $presenter,
    ): Response {
        return Inertia::render('Literature/Index', [
            'popularLiteratures' => $browser->popular(4)
                ->map(fn (Literature $literature): array => $this->present($literature, $presenter))
                ->values()
                ->all(),
            'options' => $browser->options(),
            'routes' => [
                'browse' => route('literature.browse'),
            ],
        ]);
    }

    public function browse(
        Request $request,
        LiteratureBrowseService $browser,
        LiteraturePresenter $presenter,
    ): Response {
        $filters = $browser->normalizeFilters($request->only(['q', 'decade', 'rating', 'genre', 'sort']));
        $literatures = $browser->browse($filters)
            ->withQueryString()
            ->through(fn (Literature $literature): array => $this->present($literature, $presenter));

        return Inertia::render('Literature/Browse', [
            'literatures' => $literatures,
            'filters' => $filters,
            'options' => $browser->options(),
            'routes' => [
                'index' => route('literature.index'),
                'browse' => route('literature.browse'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Literature $literature, LiteraturePresenter $presenter): array
    {
        return [
            ...$presenter->present($literature),
            'average_rating' => $literature->getAttribute('average_rating') === null
                ? null
                : (float) $literature->getAttribute('average_rating'),
            'rating_count' => (int) $literature->getAttribute('rating_count'),
            'weekly_popularity_score' => (float) $literature->getAttribute('weekly_popularity_score'),
            'overall_popularity_score' => (float) $literature->getAttribute('overall_popularity_score'),
        ];
    }
}
