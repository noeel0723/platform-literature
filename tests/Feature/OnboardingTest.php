<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_must_sign_in_before_viewing_or_saving_onboarding(): void
    {
        $this->get(route('onboarding.show'))->assertRedirect(route('login'));
        $this->patchJson(route('onboarding.update'), ['action' => 'skip'])->assertUnauthorized();
    }

    public function test_unverified_users_stay_in_the_verification_flow(): void
    {
        $user = User::factory()->pendingOnboarding()->unverified()->create();

        $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('verification.notice'));
        $this->patchJson(route('onboarding.update'), ['action' => 'skip'])->assertForbidden();

        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertNull($user->fresh()->preferred_formats);
    }

    public function test_new_registrations_are_sent_to_onboarding_only_after_email_verification(): void
    {
        Notification::fake();
        $this->post(route('register'), [
            'name' => 'New Reader', 'username' => 'new_reader', 'email' => 'new@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'onboarding_required' => false,
        ])->assertRedirect(route('verification.notice'));
        $user = User::query()->sole();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertTrue($user->onboarding_required);
        $this->assertFalse($user->hasVerifiedEmail());

        $this->get($this->verificationUrl($user))->assertRedirect(route('onboarding.show'));

        $this->get(route('onboarding.show'))->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Show')->where('step', 1)->where('preferredFormats', [])
            ->has('formats', 4)->where('formats.3.value', 'western-comic'));
    }

    public function test_existing_users_are_not_redirected_to_onboarding(): void
    {
        $user = User::factory()->create(['username' => 'existing_reader']);

        $this->post(route('login'), ['email' => $user->username, 'password' => 'password'])
            ->assertRedirect(route('literatures.index'));
        $this->get(route('onboarding.show'))->assertRedirect(route('home'));
        $this->patch(route('onboarding.update'), ['action' => 'next', 'formats' => ['manga']])
            ->assertRedirect(route('home'));

        $this->assertFalse($user->fresh()->onboarding_required);
        $this->assertNull($user->fresh()->preferred_formats);
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_existing_unverified_accounts_are_not_enrolled_when_they_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect(route('literatures.index'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($user->fresh()->needsOnboarding());
    }

    #[TestWith(['onboard_reader'])]
    #[TestWith(['onboard@example.com'])]
    public function test_verified_new_users_can_sign_in_by_username_or_email_and_resume(string $identifier): void
    {
        $user = User::factory()->pendingOnboarding()->create(['username' => 'onboard_reader', 'email' => 'onboard@example.com', 'onboarding_step' => 2]);

        $this->post(route('login'), ['email' => $identifier, 'password' => 'password'])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('onboarding.show'))->assertInertia(fn (Assert $page) => $page->where('step', 2));
    }

    public function test_next_saves_formats_and_back_keeps_them_across_reload(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'next', 'formats' => ['novel', 'western-comic']])
            ->assertRedirect(route('onboarding.show'));

        $this->assertSame(['novel', 'western-comic'], $user->fresh()->preferred_formats);
        $this->get(route('onboarding.show'))->assertInertia(fn (Assert $page) => $page->where('step', 2)->where('preferredFormats', ['novel', 'western-comic']));
        $this->patch(route('onboarding.update'), ['action' => 'back'])->assertRedirect(route('onboarding.show'));
        $this->get(route('onboarding.show'))->assertInertia(fn (Assert $page) => $page->where('step', 1)->where('preferredFormats', ['novel', 'western-comic']));
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_complete_persists_status_and_future_logins_do_not_repeat_onboarding(): void
    {
        $user = User::factory()->pendingOnboarding()->create(['username' => 'completed_reader']);
        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'next', 'formats' => ['manga']])->assertRedirect();
        $this->patch(route('onboarding.update'), ['action' => 'next'])->assertRedirect();

        $this->patch(route('onboarding.update'), ['action' => 'complete'])->assertRedirect(route('home'));

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->assertSame(['manga'], $user->fresh()->preferred_formats);
        $this->assertSame(3, $user->fresh()->onboarding_step);
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login'), ['email' => $user->username, 'password' => 'password'])->assertRedirect(route('literatures.index'));
        $this->get(route('onboarding.show'))->assertRedirect(route('home'));
    }

    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([3])]
    public function test_skip_is_saved_from_any_step_without_losing_existing_preferences(int $step): void
    {
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => $step, 'preferred_formats' => ['manhwa']]);

        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'skip'])->assertRedirect(route('home'));

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->assertSame(['manhwa'], $user->fresh()->preferred_formats);
        $this->assertFalse($user->fresh()->needsOnboarding());
    }

    public function test_skip_without_a_format_keeps_the_default_recommendation_fallback(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'skip', 'formats' => []])->assertRedirect(route('home'));

        $this->assertSame([], $user->fresh()->preferred_formats);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_completed_onboarding_cannot_be_replayed_to_change_preferences_or_timestamp(): void
    {
        $this->freezeTime();
        $user = User::factory()->pendingOnboarding()->create(['preferred_formats' => ['novel']]);
        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'skip'])->assertRedirect();
        $completed = $user->fresh()->onboarding_completed_at->toISOString();
        $this->travel(5)->minutes();

        $this->patch(route('onboarding.update'), ['action' => 'skip', 'formats' => ['manga']])->assertRedirect(route('home'));

        $this->assertSame($completed, $user->fresh()->onboarding_completed_at->toISOString());
        $this->assertSame(['novel'], $user->fresh()->preferred_formats);
    }

    #[TestWith([['comic']])]
    #[TestWith([['manga', 'manga']])]
    #[TestWith([['unknown']])]
    #[TestWith([['novel', 'manga', 'manhwa', 'western-comic', 'novel']])]
    public function test_invalid_or_duplicate_formats_return_422_without_saving(array $formats): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->patchJson(route('onboarding.update'), ['action' => 'next', 'formats' => $formats])->assertUnprocessable();

        $this->assertSame(1, $user->fresh()->onboarding_step);
        $this->assertNull($user->fresh()->preferred_formats);
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_complete_cannot_jump_from_the_first_step_without_an_explicit_skip(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->patchJson(route('onboarding.update'), ['action' => 'complete', 'formats' => ['manga']])
            ->assertUnprocessable()->assertJsonValidationErrors('action');

        $this->assertSame(1, $user->fresh()->onboarding_step);
        $this->assertNull($user->fresh()->preferred_formats);
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_unknown_actions_are_rejected_without_changing_the_saved_step(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->patchJson(route('onboarding.update'), ['action' => 'restart', 'formats' => ['manga']])
            ->assertUnprocessable()->assertJsonValidationErrors('action');

        $this->assertSame(1, $user->fresh()->onboarding_step);
        $this->assertNull($user->fresh()->preferred_formats);
    }

    public function test_deactivated_accounts_cannot_save_onboarding_preferences(): void
    {
        $user = User::factory()->pendingOnboarding()->create(['deactivated_at' => now()]);

        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'skip', 'formats' => ['manga']])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertNull($user->fresh()->preferred_formats);
    }

    public function test_only_the_authenticated_users_preferences_are_changed(): void
    {
        $user = User::factory()->pendingOnboarding()->create();
        $other = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->patch(route('onboarding.update'), [
            'action' => 'next', 'formats' => ['manga'], 'user_id' => $other->id, 'role' => 'admin', 'onboarding_required' => false,
        ])->assertRedirect(route('onboarding.show'));

        $this->assertSame(['manga'], $user->fresh()->preferred_formats);
        $this->assertSame(User::ROLE_USER, $user->fresh()->role);
        $this->assertTrue($user->fresh()->onboarding_required);
        $this->assertNull($other->fresh()->preferred_formats);
        $this->assertSame(1, $other->fresh()->onboarding_step);
    }

    public function test_unfinished_onboarding_does_not_block_normal_verified_features(): void
    {
        $user = User::factory()->pendingOnboarding()->create();

        $this->actingAs($user)->get(route('home'))->assertOk();
        $this->get(route('literatures.index'))->assertOk();
        $this->get(route('profiles.edit'))->assertOk();
    }

    public function test_discovery_shows_popular_local_titles_only_in_the_selected_formats(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2, 'preferred_formats' => ['novel', 'manga']]);
        $popular = Literature::factory()->create(['type' => 'novel', 'title' => 'Popular Novel']);
        Literature::factory()->create(['type' => 'novel', 'title' => 'Quiet Novel']);
        $manga = Literature::factory()->create(['type' => 'manga']);
        Literature::factory()->create(['type' => 'manhwa']);
        Activity::factory()->for($popular)->create(['type' => Activity::TYPE_STARTED_READING]);

        $response = $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->has('discovery.groups', 2)
            ->where('discovery.groups.0.format', 'Novel')
            ->where('discovery.groups.0.literatures.0.id', $popular->id)
            ->where('discovery.groups.1.format', 'Manga')
            ->where('discovery.groups.1.literatures.0.id', $manga->id)
            ->where('routes.discovery', route('onboarding.show')));
        Http::assertNothingSent();
    }

    public function test_discovery_uses_all_four_formats_when_no_preference_was_selected(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2]);

        $response = $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

        $this->assertSame(['Novel', 'Manga', 'Manhwa', 'Western Comic'], collect($response->inertiaProps('discovery.groups'))->pluck('format')->all());
        Http::assertNothingSent();
    }

    public function test_an_unavailable_selected_format_does_not_show_unselected_formats_instead(): void
    {
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2, 'preferred_formats' => ['manhwa']]);
        Literature::factory()->create(['type' => 'novel']);

        $response = $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

        $this->assertSame([['format' => 'Manhwa', 'literatures' => []]], $response->inertiaProps('discovery.groups'));
    }

    public function test_popular_discovery_deduplicates_editions_of_the_same_canonical_work(): void
    {
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2, 'preferred_formats' => ['novel']]);
        $preferred = Literature::factory()->create(['type' => 'novel']);
        $sibling = Literature::factory()->create(['type' => 'novel']);
        $canonical = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);
        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->create([
                'canonical_work_id' => $canonical->id, 'literature_id' => $literature->id, 'api_source_id' => $literature->api_source_id,
            ]);
        }

        $response = $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

        $this->assertSame([$preferred->id], collect($response->inertiaProps('discovery.groups.0.literatures'))->pluck('id')->all());
    }

    #[TestWith(['Earth Chronicles'])]
    #[TestWith(['Local Writer'])]
    public function test_discovery_searches_local_titles_and_authors_without_calling_catalog_apis(string $query): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2, 'preferred_formats' => ['novel']]);
        $literature = Literature::factory()->create(['title' => 'Earth Chronicles', 'type' => 'manga']);
        $author = Author::factory()->create(['name' => 'Local Writer']);
        $literature->authors()->attach($author);
        Literature::factory()->create(['title' => 'An Unrelated Work']);

        $response = $this->actingAs($user)->get(route('onboarding.show', ['q' => $query]))->assertOk();

        $this->assertSame([$literature->id], collect($response->inertiaProps('discovery.results'))->pluck('id')->all());
        $this->assertSame($query, $response->inertiaProps('discovery.query'));
        $this->assertSame([], $response->inertiaProps('discovery.groups'));
        $this->assertSame(route('literature.browse', ['q' => $query]), $response->inertiaProps('routes.browse'));
        $this->assertSame(2, $user->fresh()->onboarding_step);
        $this->assertSame(['novel'], $user->fresh()->preferred_formats);
        Http::assertNothingSent();
    }

    public function test_discovery_search_is_bounded_and_an_unknown_local_title_has_no_results(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2]);
        Literature::factory()->count(8)->sequence(fn ($sequence) => ['title' => 'Earth Volume '.$sequence->index])->create();

        $response = $this->actingAs($user)->get(route('onboarding.show', ['q' => 'Earth']))->assertOk();
        $this->assertCount(6, $response->inertiaProps('discovery.results'));
        $response = $this->get(route('onboarding.show', ['q' => 'No Such Local Title']))->assertOk();
        $this->assertSame([], $response->inertiaProps('discovery.results'));
        Http::assertNothingSent();
    }

    public function test_discovery_rejects_a_malformed_search_without_changing_preferences(): void
    {
        $user = User::factory()->pendingOnboarding()->create(['onboarding_step' => 2, 'preferred_formats' => ['manga']]);

        $this->actingAs($user)->getJson(route('onboarding.show', ['q' => ['invalid']]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');

        $this->assertSame(['manga'], $user->fresh()->preferred_formats);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
    }
}
