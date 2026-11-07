<?php

namespace Tests\Feature;

use App\Models\CustomList;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OptionalEmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verification_is_enabled_by_default_and_registration_still_sends_email(): void
    {
        Notification::fake();
        $this->assertTrue(config('auth.verification.enabled'));

        $this->post(route('register'), $this->registrationData())->assertRedirect(route('verification.notice'));

        $user = User::query()->sole();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_disabled_registration_allows_onboarding_without_verifying_or_sending_email(): void
    {
        config()->set('auth.verification.enabled', false);
        Notification::fake();
        Event::fake([Verified::class]);

        $this->post(route('register'), [...$this->registrationData(), 'email_verified_at' => now()->toIso8601String()])
            ->assertRedirect(route('onboarding.show'));

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->get(route('onboarding.show'))->assertInertia(fn (Assert $page) => $page->component('Onboarding/Show')->where('step', 1));
        $this->patch(route('onboarding.update'), ['action' => 'next', 'formats' => ['manga']])->assertRedirect(route('onboarding.show'));
        $this->patch(route('onboarding.update'), ['action' => 'skip', 'formats' => ['manga']])->assertRedirect(route('home'));

        $user->refresh();
        $this->assertSame(['manga'], $user->preferredFormats());
        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertNothingSent();
        Event::assertNotDispatched(Verified::class);
    }

    #[TestWith([true, 'optional_reader'])]
    #[TestWith([true, 'optional@example.com'])]
    #[TestWith([false, 'optional_reader'])]
    #[TestWith([false, 'optional@example.com'])]
    public function test_existing_unverified_accounts_can_login_by_username_or_email_in_both_modes(bool $enabled, string $identifier): void
    {
        config()->set('auth.verification.enabled', $enabled);
        Notification::fake();
        $user = User::factory()->unverified()->create(['username' => 'optional_reader', 'email' => 'optional@example.com']);

        $this->post(route('login'), ['email' => $identifier, 'password' => 'password'])
            ->assertRedirect(route($enabled ? 'verification.notice' : 'home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->needsOnboarding());
        Notification::assertNothingSent();
    }

    public function test_disabled_login_continues_pending_onboarding_and_preserves_the_intended_destination_for_legacy_accounts(): void
    {
        config()->set('auth.verification.enabled', false);
        $newUser = User::factory()->unverified()->pendingOnboarding()->create(['username' => 'new_reader']);
        $legacy = User::factory()->unverified()->create(['username' => 'legacy_reader']);

        $this->post(route('login'), ['email' => $newUser->username, 'password' => 'password'])->assertRedirect(route('onboarding.show'));
        $this->post(route('logout'));
        $this->withSession(['url.intended' => route('profiles.edit')])->post(route('login'), ['email' => $legacy->username, 'password' => 'password'])
            ->assertRedirect(route('profiles.edit'));

        $this->assertNull($newUser->fresh()->email_verified_at);
        $this->assertNull($legacy->fresh()->email_verified_at);
    }

    public function test_disabled_verification_routes_do_not_send_mail_or_consume_resend_limits(): void
    {
        config()->set('auth.verification.enabled', false);
        Notification::fake();
        $user = User::factory()->unverified()->pendingOnboarding()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect(route('onboarding.show'));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect(route('onboarding.show'));
        }
        $user->sendEmailVerificationNotification();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();

        config()->set('auth.verification.enabled', true);
        $this->from(route('verification.notice'))->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
        $this->post(route('verification.send'))->assertSessionHasErrors('verification');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_disabled_unverified_users_can_use_interactive_features_and_undo(): void
    {
        config()->set('auth.verification.enabled', false);
        $user = User::factory()->unverified()->create(['username' => 'interactive_reader']);
        $literature = Literature::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get(route('profiles.edit'))->assertOk();
        $this->getJson(route('quick-log.literatures'))->assertOk();
        $response = $this->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read'])->assertOk();
        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'literature_id' => $literature->id]);
        $this->postJson($response->json('feedback.undo.url'))->assertOk();
        $this->assertDatabaseMissing('reading_lists', ['user_id' => $user->id, 'literature_id' => $literature->id]);
        $this->put(route('reviews.update', $literature), ['rating' => 4, 'body' => 'A memorable read.'])->assertRedirect();
        $this->post(route('profiles.follow.store', $other))->assertRedirect();
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'literature_id' => $literature->id, 'rating' => 4]);
        $this->assertTrue($user->isFollowing($other));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_disabled_private_lists_keep_owner_authorization_and_connections_management(): void
    {
        config()->set('auth.verification.enabled', false);
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('custom-lists.store'), ['title' => 'Private favorites', 'is_private' => true])->assertRedirect();
        $list = CustomList::query()->sole();
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
        $this->get(route('profiles.lists', $user))->assertInertia(fn (Assert $page) => $page->where('isOwner', true)->has('lists.data', 1));
        $this->get(route('profiles.connections', $user))->assertInertia(fn (Assert $page) => $page->where('canManage', true)->where('counts.blocked', 0));
        $this->put(route('custom-lists.update', $list), ['title' => 'Updated favorites', 'is_private' => true])->assertRedirect();

        $this->actingAs($other)->get(route('custom-lists.show', $list))->assertForbidden();
        $this->put(route('custom-lists.update', $list), ['title' => 'Unauthorized change'])->assertForbidden();
        $this->delete(route('custom-lists.destroy', $list))->assertForbidden();
        $this->get(route('profiles.connections', [$user, 'relationship' => 'blocked']))->assertNotFound();
        $this->assertDatabaseHas('custom_lists', ['id' => $list->id, 'user_id' => $user->id, 'title' => 'Updated favorites']);
        $this->assertFalse($other->can('update', $list));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_home_detail_preview_and_header_present_the_current_verification_requirement(bool $enabled): void
    {
        config()->set('auth.verification.enabled', $enabled);
        $user = User::factory()->unverified()->create();
        $literature = Literature::factory()->create();
        CustomList::factory()->for($user)->create(['is_private' => true]);

        $response = $this->actingAs($user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('emailVerificationRequired', $enabled)
            ->where('routes.verification', $enabled ? route('verification.notice') : null)
            ->where('viewer', $enabled ? null : ['name' => $user->name, 'profile_url' => route('profiles.show', $user)]));
        preg_match('/data-react-header-props>(.*?)<\/script>/s', $response->getContent(), $matches);
        $header = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($header['user']['email_verified']);
        $this->assertSame($enabled, $header['user']['requires_email_verification']);
        $this->get(route('literatures.show', $literature))->assertInertia(fn (Assert $page) => $page
            ->where('viewer.authenticated', ! $enabled)
            ->where('routes.verification', $enabled ? route('verification.notice') : null));
        $this->getJson(route('literatures.preview', $literature))->assertOk()
            ->assertJsonPath('viewer.authenticated', ! $enabled)
            ->assertJsonPath('routes.verification', $enabled ? route('verification.notice') : null)
            ->assertJsonCount($enabled ? 0 : 1, 'lists');
    }

    public function test_reenabling_verification_restricts_existing_unverified_sessions_without_changing_their_email(): void
    {
        config()->set('auth.verification.enabled', false);
        $user = User::factory()->unverified()->create();
        $verifiedUser = User::factory()->create();
        $literature = Literature::factory()->create();
        $this->actingAs($user)->get(route('profiles.edit'))->assertOk();
        $response = $this->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read'])->assertOk();

        config()->set('auth.verification.enabled', true);
        $this->get(route('profiles.edit'))->assertRedirect(route('verification.notice'));
        $this->putJson(route('reading-list.update', $literature), ['status' => 'completed'])->assertForbidden();
        $this->postJson($response->json('feedback.undo.url'))->assertForbidden();
        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'status' => 'want_to_read']);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->get($this->verificationUrl($user))->assertRedirect(route('literatures.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get(route('profiles.edit'))->assertOk();
        $this->actingAs($verifiedUser)->get(route('profiles.edit'))->assertOk();
    }

    public function test_onboarding_completed_while_disabled_stays_completed_after_verification_is_reenabled(): void
    {
        config()->set('auth.verification.enabled', false);
        $user = User::factory()->unverified()->pendingOnboarding()->create();
        $this->actingAs($user)->patch(route('onboarding.update'), ['action' => 'skip'])->assertRedirect(route('home'));
        $completedAt = $user->fresh()->onboarding_completed_at;
        $this->deleteJson(route('registration.cancel'), ['confirmation' => true])->assertForbidden();

        config()->set('auth.verification.enabled', true);
        $this->deleteJson(route('registration.cancel'), ['confirmation' => true])->assertForbidden();
        $this->get($this->verificationUrl($user))->assertRedirect(route('literatures.index'));

        $this->assertSame($completedAt->toDateTimeString(), $user->fresh()->onboarding_completed_at->toDateTimeString());
        $this->assertFalse($user->fresh()->needsOnboarding());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_email_validation_and_uniqueness_are_preserved_in_both_modes(bool $enabled): void
    {
        config()->set('auth.verification.enabled', $enabled);
        Notification::fake();
        User::factory()->create(['username' => 'taken_reader', 'email' => 'taken@example.com']);

        $this->postJson(route('register'), [...$this->registrationData(), 'email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('register'), [...$this->registrationData(), 'email' => 'taken@example.com'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('register'), [...$this->registrationData(), 'username' => 'taken_reader'])->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_deactivated_accounts_and_unauthorized_admin_access_are_still_rejected(bool $enabled): void
    {
        config()->set('auth.verification.enabled', $enabled);
        $inactive = User::factory()->unverified()->create(['username' => 'inactive_reader', 'deactivated_at' => now()]);
        $regular = User::factory()->create();

        $this->get(route('profiles.edit'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => $inactive->username, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($inactive)->get(route('profiles.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($regular)->get(route('admin.moderation.index'))->assertForbidden();
    }

    /** @return array<string, string> */
    private function registrationData(): array
    {
        return ['name' => 'Optional Reader', 'username' => 'optional_reader', 'email' => 'optional@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'];
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    }
}
