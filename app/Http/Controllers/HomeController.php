<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Literature;
use App\Services\Recommendations\PersonalRecommendationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, PersonalRecommendationService $recommendations): Response
    {
        $viewer = $request->user();
        $friendIds = $viewer?->following()
            ->whereNull('deactivated_at')
            ->pluck('users.id') ?? collect();

        $activities = collect();
        $popularLiteratures = collect();
        $recommendedLiteratures = $viewer === null
            ? collect()
            : $recommendations->recommend($viewer, 8);

        if ($friendIds->isNotEmpty()) {
            $activities = Activity::query()
                ->visibleToReaders()
                ->withoutRedundantCompletions()
                ->with(['user', 'literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride', 'review'])
                ->whereIn('user_id', $friendIds)
                ->whereIn('type', [
                    Activity::TYPE_COMPLETED,
                    Activity::TYPE_RATED,
                    Activity::TYPE_REVIEWED,
                ])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->unique(fn (Activity $activity): string => $activity->user_id.'-'.$activity->literature_id)
                ->take(6)
                ->values();

            $popularLiteratures = Literature::query()
                ->with(['authors', 'metadataOverride', 'sourceMapping.canonicalWork.metadataOverride', 'readingLists' => function ($readingLists) use ($friendIds): void {
                    $readingLists
                        ->whereIn('user_id', $friendIds)
                        ->whereIn('status', ['reading', 'completed'])
                        ->with('user')
                        ->latest('updated_at');
                }])
                ->withCount(['readingLists as friends_count' => function (Builder $readingLists) use ($friendIds): void {
                    $readingLists
                        ->whereIn('user_id', $friendIds)
                        ->whereIn('status', ['reading', 'completed']);
                }])
                ->whereHas('readingLists', function (Builder $readingLists) use ($friendIds): void {
                    $readingLists
                        ->whereIn('user_id', $friendIds)
                        ->whereIn('status', ['reading', 'completed']);
                })
                ->orderByDesc('friends_count')
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get();
        }

        return Inertia::render('Home/Index', [
            'activities' => $activities->map(fn (Activity $activity): array => $this->presentActivity($activity))->all(),
            'popularLiteratures' => $popularLiteratures
                ->map(fn (Literature $literature): array => $this->presentPopularLiterature($literature))
                ->all(),
            'recommendations' => $recommendedLiteratures
                ->map(fn (array $recommendation): array => [
                    ...$this->presentRecommendation($recommendation['literature']),
                    'reason' => $recommendation['reason'],
                    'score' => $recommendation['score'],
                    'cold_start' => $recommendation['cold_start'],
                ])
                ->all(),
            'viewer' => $viewer === null ? null : [
                'name' => $viewer->name,
            ],
            'routes' => [
                'catalog' => route('literatures.index'),
                'login' => route('login'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentActivity(Activity $activity): array
    {
        $literature = $activity->literature;

        return [
            'id' => $activity->id,
            'action' => match ($activity->type) {
                Activity::TYPE_COMPLETED => 'Completed',
                Activity::TYPE_RATED => 'Rated',
                Activity::TYPE_REVIEWED => 'Reviewed',
                default => 'Updated',
            },
            'rating' => data_get($activity->metadata, 'rating'),
            'occurred_at' => $activity->occurred_at->utc()->toIso8601String(),
            'reader' => [
                'name' => $activity->user->name,
                'avatar_url' => $activity->user->avatarUrl(),
                'initials' => $this->initials($activity->user->name),
            ],
            'literature' => [
                'title' => $literature->displayTitle(),
                'url' => route('literatures.show', $literature),
                'cover_url' => $literature->displayCoverUrl(),
                'initials' => $this->initials($literature->displayTitle()),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function presentPopularLiterature(Literature $literature): array
    {
        return [
            'id' => $literature->id,
            'title' => $literature->displayTitle(),
            'url' => route('literatures.show', $literature),
            'cover_url' => $literature->displayCoverUrl(),
            'initials' => $this->initials($literature->displayTitle()),
            'friends_count' => $literature->friends_count,
            'friends' => $literature->readingLists
                ->take(3)
                ->map(fn ($readingList): array => [
                    'name' => $readingList->user->name,
                    'avatar_url' => $readingList->user->avatarUrl(),
                    'initials' => $this->initials($readingList->user->name),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentRecommendation(Literature $literature): array
    {
        return [
            'id' => $literature->id,
            'title' => $literature->displayTitle(),
            'url' => route('literatures.show', $literature),
            'cover_url' => $literature->displayCoverUrl(),
            'initials' => $this->initials($literature->displayTitle()),
        ];
    }

    private function initials(string $value): string
    {
        return collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'LH';
    }
}
