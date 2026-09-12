<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Literature;
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
}
