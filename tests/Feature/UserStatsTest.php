<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\ReadingLog;
use App\Models\Review;
use App\Models\User;
use App\Services\Profile\UserStatsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserStatsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stats_deduplicate_canonical_works_and_calculate_totals(): void
    {
        $user = User::factory()->create();
        $firstEdition = Literature::factory()->create(['title' => 'Dune', 'type' => 'novel']);
        $secondEdition = Literature::factory()->create(['title' => 'Dune Anniversary', 'type' => 'novel']);
        $manga = Literature::factory()->create(['title' => 'Berserk', 'type' => 'manga']);
        $saved = Literature::factory()->create(['title' => 'Saved Novel']);
        $canonical = CanonicalWork::factory()->create([
            'canonical_title' => 'Dune',
            'normalized_title' => 'dune',
            'preferred_literature_id' => $firstEdition->id,
        ]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $canonical->id,
            'literature_id' => $firstEdition->id,
            'api_source_id' => $firstEdition->api_source_id,
        ]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $canonical->id,
            'literature_id' => $secondEdition->id,
            'api_source_id' => $secondEdition->api_source_id,
        ]);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $firstEdition->id, 'status' => 'completed', 'completed_at' => now()->subYearNoOverflow()]);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $secondEdition->id, 'status' => 'completed', 'completed_at' => now()]);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $manga->id, 'status' => 'completed', 'completed_at' => now()]);
        $savedEntry = ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $saved->id, 'status' => 'want_to_read']);
        ReadingLog::factory()->create([
            'reading_list_id' => $savedEntry->id,
            'occurred_at' => now()->subYearNoOverflow(),
        ]);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $firstEdition->id, 'rating' => 3, 'updated_at' => now()->subDay()]);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $secondEdition->id, 'rating' => 5, 'updated_at' => now()]);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $manga->id, 'rating' => 4, 'updated_at' => now()]);
        $stats = app(UserStatsService::class)->forUser($user);

        $this->assertSame(3, $stats['summary']['literature']);
        $this->assertSame(2, $stats['summary']['completed']);
        $this->assertSame(1, $stats['summary']['readlist']);
        $this->assertSame(2, $stats['summary']['reviews']);
        $this->assertSame(4.5, $stats['summary']['average_rating']);
        $this->assertSame(2, $stats['summary']['completed_this_year']);
        $this->assertSame([
            ['label' => 'Novel', 'count' => 1],
            ['label' => 'Manga', 'count' => 1],
        ], $stats['by_type']);
        $this->assertSame(2, collect($stats['completed_by_month'])->firstWhere('month', now()->format('M'))['count']);
        $this->assertSame([
            ['year' => now()->year, 'count' => 2],
        ], $stats['completed_by_year']);
        $this->assertSame(1, collect($stats['activity_by_year'])->firstWhere('year', now()->subYearNoOverflow()->year)['days']);
    }

    public function test_selected_year_controls_monthly_and_yearly_completed_statistics(): void
    {
        $user = User::factory()->create();
        $january = Literature::factory()->create();
        $june = Literature::factory()->create();
        $followingYear = Literature::factory()->create();
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $january->id,
            'status' => 'completed',
            'completed_at' => '2024-01-12 10:00:00',
        ]);
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $june->id,
            'status' => 'completed',
            'completed_at' => '2024-06-23 10:00:00',
        ]);
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $followingYear->id,
            'status' => 'completed',
            'completed_at' => '2025-02-03 10:00:00',
        ]);

        $this->get(route('profiles.stats', ['user' => $user, 'year' => 2024]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Stats')
                ->where('stats.year', 2024)
                ->where('stats.summary.completed_this_year', 2)
                ->where('stats.completed_by_month.0', ['month' => 'Jan', 'count' => 1])
                ->where('stats.completed_by_month.5', ['month' => 'Jun', 'count' => 1])
                ->where('stats.completed_by_year', [
                    ['year' => 2024, 'count' => 2],
                    ['year' => 2025, 'count' => 1],
                ]));
    }

    public function test_stats_page_is_public_for_an_active_profile(): void
    {
        $user = User::factory()->create();

        $this->get(route('profiles.stats', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Stats')
                ->where('profile.username', $user->username)
                ->where('stats.summary.completed', 0));
    }
}
