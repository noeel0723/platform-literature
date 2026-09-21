<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Category;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use App\Services\Recommendations\PersonalRecommendationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PersonalRecommendationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_scores_matching_tastes_and_excludes_tracked_literature(): void
    {
        $user = User::factory()->create();
        $fantasy = Category::factory()->create(['name' => 'Fantasy']);
        $history = Category::factory()->create(['name' => 'History']);
        $liked = Literature::factory()->create(['title' => 'Loved Fantasy']);
        $recommended = Literature::factory()->create(['title' => 'Another Fantasy']);
        $unrelated = Literature::factory()->create(['title' => 'Dry History']);
        $readlist = Literature::factory()->create(['title' => 'Already Saved']);
        $completed = Literature::factory()->create(['title' => 'Already Finished']);
        $liked->categories()->attach($fantasy);
        $recommended->categories()->attach($fantasy);
        $unrelated->categories()->attach($history);
        $readlist->categories()->attach($fantasy);
        $completed->categories()->attach($fantasy);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $liked->id, 'rating' => 5]);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $liked->id, 'status' => 'completed', 'completed_at' => now()]);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $readlist->id, 'status' => 'want_to_read']);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $completed->id, 'status' => 'completed', 'completed_at' => now()]);

        $results = app(PersonalRecommendationService::class)->recommend($user, 12);
        $ids = $results->pluck('literature.id');

        $this->assertSame($recommended->id, $results->first()['literature']->id);
        $this->assertStringContainsString('Fantasy', $results->first()['reason']);
        $this->assertFalse($ids->contains($liked->id));
        $this->assertFalse($ids->contains($readlist->id));
        $this->assertFalse($ids->contains($completed->id));
        $this->assertTrue($ids->contains($unrelated->id));
    }

    public function test_it_uses_local_popularity_for_a_cold_start_user(): void
    {
        $newUser = User::factory()->create();
        $popular = Literature::factory()->create(['title' => 'Local Favorite']);
        $quiet = Literature::factory()->create(['title' => 'Quiet Work']);

        User::factory()->count(3)->create()->each(function (User $reader) use ($popular): void {
            ReadingList::factory()->create([
                'user_id' => $reader->id,
                'literature_id' => $popular->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        $results = app(PersonalRecommendationService::class)->recommend($newUser, 2);

        $this->assertSame($popular->id, $results->first()['literature']->id);
        $this->assertTrue($results->first()['cold_start']);
        $this->assertSame('Trending with Literahaven readers', $results->first()['reason']);
        $this->assertTrue($results->pluck('literature.id')->contains($quiet->id));
    }

    public function test_an_old_genre_match_outside_the_latest_three_hundred_is_still_a_candidate(): void
    {
        $user = User::factory()->create();
        $source = ApiSource::factory()->create();
        $fantasy = Category::factory()->create(['name' => 'Fantasy']);
        $liked = Literature::factory()->create(['api_source_id' => $source->id, 'title' => 'Loved Fantasy']);
        $liked->categories()->attach($fantasy);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $liked->id, 'rating' => 5]);
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $liked->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Literature::factory()->count(99)->create(['api_source_id' => $source->id]);
        $oldMatch = Literature::factory()->create([
            'api_source_id' => $source->id,
            'title' => 'Forgotten Fantasy Classic',
            'updated_at' => now()->subYears(5),
        ]);
        $oldMatch->categories()->attach($fantasy);
        Literature::factory()->count(301)->create([
            'api_source_id' => $source->id,
            'updated_at' => now(),
        ]);

        $results = app(PersonalRecommendationService::class)->recommend($user);

        $this->assertTrue($results->pluck('literature.id')->contains($oldMatch->id));
    }

    public function test_matching_author_candidates_are_added_to_the_pool(): void
    {
        $user = User::factory()->create();
        $author = Author::factory()->create(['name' => 'A Beloved Author']);
        $liked = Literature::factory()->create(['title' => 'The First Favorite']);
        $candidate = Literature::factory()->create(['title' => 'A Different Book']);
        $liked->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $candidate->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $liked->id, 'rating' => 5]);
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $liked->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $secondCompleted = Literature::factory()->create(['title' => 'Another Completed Work']);
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $secondCompleted->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $results = app(PersonalRecommendationService::class)->recommend($user);

        $this->assertTrue($results->pluck('literature.id')->contains($candidate->id));
        $this->assertStringContainsString($liked->title, $results->firstWhere('literature.id', $candidate->id)['reason']);
    }

    public function test_similar_reader_works_are_candidate_sources_even_when_not_in_popular_fallback(): void
    {
        $user = User::factory()->create();
        $similarReader = User::factory()->create();
        $popularReaders = User::factory()->count(2)->create();
        $source = ApiSource::factory()->create();
        $sharedWorks = Literature::factory()->count(2)->create(['api_source_id' => $source->id]);

        foreach ($sharedWorks as $work) {
            foreach ([$user, $similarReader] as $reader) {
                ReadingList::factory()->create([
                    'user_id' => $reader->id,
                    'literature_id' => $work->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        $popularNoise = Literature::factory()->count(100)->create(['api_source_id' => $source->id]);

        foreach ($popularNoise as $work) {
            foreach ($popularReaders as $reader) {
                ReadingList::factory()->create([
                    'user_id' => $reader->id,
                    'literature_id' => $work->id,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        $similarReaderChoice = Literature::factory()->create([
            'api_source_id' => $source->id,
            'title' => 'A Similar Reader Discovery',
        ]);
        ReadingList::factory()->create([
            'user_id' => $similarReader->id,
            'literature_id' => $similarReaderChoice->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $results = app(PersonalRecommendationService::class)->recommend($user);
        $recommendation = $results->firstWhere('literature.id', $similarReaderChoice->id);

        $this->assertNotNull($recommendation);
        $this->assertSame('Popular among readers with similar interests', $recommendation['reason']);
    }

    public function test_candidates_are_canonical_deduplicated_excluded_and_limited_to_twelve(): void
    {
        $user = User::factory()->create();
        $fantasy = Category::factory()->create(['name' => 'Fantasy']);
        $liked = Literature::factory()->create(['title' => 'Taste Seed']);
        $liked->categories()->attach($fantasy);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $liked->id, 'rating' => 5]);
        ReadingList::factory()->create([
            'user_id' => $user->id,
            'literature_id' => $liked->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $favorite = Literature::factory()->create(['title' => 'Already Favorite']);
        $favorite->categories()->attach($fantasy);
        $user->favoriteLiteratures()->attach($favorite->id, ['position' => 0]);

        $preferred = Literature::factory()->create(['title' => 'Canonical Preferred']);
        $sibling = Literature::factory()->create(['title' => 'Canonical Sibling']);
        $preferred->categories()->attach($fantasy);
        $sibling->categories()->attach($fantasy);
        $canonical = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);

        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->create([
                'canonical_work_id' => $canonical->id,
                'literature_id' => $literature->id,
                'api_source_id' => $literature->api_source_id,
            ]);
        }

        User::factory()->count(3)->create()->each(function (User $reader) use ($sibling): void {
            ReadingList::factory()->create([
                'user_id' => $reader->id,
                'literature_id' => $sibling->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        Literature::factory()->count(20)->create()->each(
            fn (Literature $literature) => $literature->categories()->attach($fantasy),
        );

        $results = app(PersonalRecommendationService::class)->recommend($user, 50);
        $ids = $results->pluck('literature.id');

        $this->assertCount(12, $results);
        $this->assertFalse($ids->contains($liked->id));
        $this->assertFalse($ids->contains($favorite->id));
        $this->assertTrue($ids->contains($preferred->id));
        $this->assertFalse($ids->contains($sibling->id));
        $this->assertSame($ids->unique()->count(), $ids->count());
    }
}
