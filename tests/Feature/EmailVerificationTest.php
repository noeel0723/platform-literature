<?php

namespace Tests\Feature;

use App\Models\CustomList;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_must_login_before_verifying_or_resending(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
        $this->post(route('verification.send'))->assertRedirect(route('login'));
    }

    public function test_unverified_reader_sees_the_react_notice_and_can_browse_public_pages(): void
    {
        $reader = User::factory()->unverified()->create();
        $this->actingAs($reader)->get(route('verification.notice'))
            ->assertInertia(fn (Assert $page) => $page->component('Auth/VerifyEmail')->where('email', $reader->email)->where('expires_in', 60));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->component('Home/Index')->where('viewer', null));
        $this->get(route('literature.index'))->assertOk();
        $this->get(route('literatures.index'))->assertOk();
    }

    #[TestWith(['reader_name'])]
    #[TestWith(['reader@example.com'])]
    public function test_unverified_reader_can_login_by_username_or_email_but_is_sent_to_verification(string $identifier): void
    {
        $reader = User::factory()->unverified()->create(['username' => 'reader_name', 'email' => 'reader@example.com']);

        $this->post(route('login'), ['email' => $identifier, 'password' => 'password'])
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($reader);
    }

    #[TestWith(['not-an-email'])]
    #[TestWith(['reader@'])]
    #[TestWith([''])]
    public function test_registration_rejects_invalid_email_without_creating_an_account_or_sending_mail(string $email): void
    {
        Notification::fake();

        $this->from(route('home'))->post(route('register'), [
            'name' => 'Reader', 'username' => 'reader_name', 'email' => $email,
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('home'))->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_registered_reader_receives_a_working_signed_email_and_can_verify_it(): void
    {
        $this->freezeTime();

        $this->post(route('register'), [
            'name' => 'Reader', 'username' => 'reader_name', 'email' => 'reader@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $reader = User::query()->sole();
        $mail = (new VerifyEmail)->toMail($reader);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
        $this->assertStringContainsString('expires=', $mail->actionUrl);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('reader@example.com', $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertStringContainsString(htmlspecialchars($mail->actionUrl, ENT_QUOTES), $messages->first()->getOriginalMessage()->getHtmlBody());
        $this->get($mail->actionUrl)->assertRedirect(route('onboarding.show'));
        $this->assertTrue($reader->fresh()->hasVerifiedEmail());
    }

    public function test_valid_verification_dispatches_the_verified_event_once(): void
    {
        $reader = User::factory()->unverified()->create();
        Event::fake([Verified::class]);
        $url = $this->verificationUrl($reader);

        $this->actingAs($reader)->get($url)->assertRedirect(route('literatures.index'));
        $this->get($url)->assertRedirect(route('literatures.index'));

        $this->assertTrue($reader->fresh()->hasVerifiedEmail());
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_a_signed_link_verifies_and_signs_in_a_new_reader_without_an_existing_session(): void
    {
        $reader = User::factory()->unverified()->pendingOnboarding()->create();
        Event::fake([Verified::class]);
        $this->withSession(['url.intended' => route('verification.notice')]);
        $oldSessionId = session()->getId();

        $this->get($this->verificationUrl($reader))
            ->assertRedirect(route('onboarding.show'))->assertSessionMissing('url.intended');

        $this->assertAuthenticatedAs($reader);
        $this->assertTrue($reader->fresh()->hasVerifiedEmail());
        $this->assertNotSame($oldSessionId, session()->getId());
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->get(route('verification.notice'))->assertRedirect(route('onboarding.show'));
    }

    public function test_a_consumed_verification_link_cannot_be_reused_to_sign_in_after_logout(): void
    {
        $reader = User::factory()->unverified()->create();
        $url = $this->verificationUrl($reader);
        Event::fake([Verified::class]);
        $this->get($url)->assertRedirect(route('literatures.index'));
        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->get($url)->assertRedirect(route('home', ['login' => 1]));

        $this->assertGuest();
        $this->assertTrue($reader->fresh()->hasVerifiedEmail());
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_a_guest_cannot_verify_with_an_expired_link(): void
    {
        $this->freezeTime();
        $reader = User::factory()->unverified()->create();
        $url = $this->verificationUrl($reader);
        $this->travel(61)->minutes();

        $this->get($url)->assertForbidden();

        $this->assertGuest();
        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_a_guest_cannot_verify_with_an_unsigned_or_tampered_link(): void
    {
        $reader = User::factory()->unverified()->create();

        $this->get(route('verification.verify', ['id' => $reader->id, 'hash' => sha1($reader->email)]))->assertForbidden();
        $this->get($this->verificationUrl($reader).'&tampered=1')->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $reader->id, 'hash' => sha1('wrong@example.com'),
        ]))->assertForbidden();

        $this->assertGuest();
        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_for_an_old_email_address_cannot_verify_or_sign_in_a_guest(): void
    {
        $reader = User::factory()->unverified()->create();
        $url = $this->verificationUrl($reader);
        $reader->forceFill(['email' => 'changed@example.com'])->save();

        $this->get($url)->assertForbidden();

        $this->assertGuest();
        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_a_deactivated_account_cannot_be_verified_or_signed_in_through_email(): void
    {
        $reader = User::factory()->unverified()->create(['deactivated_at' => now()]);

        $this->get($this->verificationUrl($reader))->assertForbidden();

        $this->assertGuest();
        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_expired_verification_link_is_rejected_without_verifying_the_reader(): void
    {
        $this->freezeTime();
        $reader = User::factory()->unverified()->create();
        $url = $this->verificationUrl($reader);
        $this->travel(61)->minutes();

        $this->actingAs($reader)->get($url)->assertForbidden();

        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_rejected(): void
    {
        $reader = User::factory()->unverified()->create();

        $this->actingAs($reader)->get($this->verificationUrl($reader).'&tampered=1')->assertForbidden();

        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_signed_link_for_another_account_or_email_is_rejected(): void
    {
        $reader = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($reader)->get($this->verificationUrl($other))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $reader->id, 'hash' => sha1('wrong@example.com')]))->assertForbidden();

        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_notification_and_rate_limits_repeated_requests(): void
    {
        $this->freezeTime();
        $reader = User::factory()->unverified()->create();
        Notification::fake();

        $this->actingAs($reader)->from(route('verification.notice'))->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'verification-link-sent');
        $this->post(route('verification.send'))->assertSessionHasErrors('verification');
        $this->travel(61)->seconds();
        $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentToTimes($reader, VerifyEmail::class, 2);
    }

    public function test_verified_reader_is_not_sent_another_verification_email(): void
    {
        $reader = User::factory()->create();
        Notification::fake();

        $this->actingAs($reader)->get(route('verification.notice'))->assertRedirect(route('literatures.index'));
        $this->post(route('verification.send'))->assertRedirect(route('literatures.index'));

        Notification::assertNothingSent();
    }

    public function test_hourly_resend_limit_cannot_be_bypassed_by_waiting_one_minute(): void
    {
        $this->freezeTime();
        $reader = User::factory()->unverified()->create();
        Notification::fake();
        $this->actingAs($reader)->from(route('verification.notice'));

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
            $this->travel(61)->seconds();
        }
        $this->post(route('verification.send'))->assertSessionHasErrors('verification');

        Notification::assertSentToTimes($reader, VerifyEmail::class, 6);
    }

    public function test_resend_after_expiry_provides_a_new_working_link(): void
    {
        $this->freezeTime();
        $reader = User::factory()->unverified()->create();
        $this->travel(61)->minutes();
        Notification::fake();
        $newUrl = null;

        $this->actingAs($reader)->from(route('verification.notice'))->post(route('verification.send'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($reader, VerifyEmail::class, function (VerifyEmail $notification) use ($reader, &$newUrl): bool {
            $newUrl = $notification->toMail($reader)->actionUrl;

            return true;
        });
        $this->get($newUrl)->assertRedirect(route('literatures.index'));
        $this->assertTrue($reader->fresh()->hasVerifiedEmail());
    }

    public function test_email_transport_failure_keeps_the_new_account_unverified_and_allows_retry(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));

        $this->post(route('register'), [
            'name' => 'Reader', 'username' => 'reader_name', 'email' => 'reader@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'verification-send-failed');

        $reader = User::query()->sole();
        $this->assertFalse($reader->hasVerifiedEmail());
        $this->assertAuthenticatedAs($reader);
    }

    public function test_resend_transport_failure_returns_a_readable_error_without_verifying_the_account(): void
    {
        $reader = User::factory()->unverified()->create();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));

        $this->actingAs($reader)->from(route('verification.notice'))->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))->assertSessionHasErrors('verification');

        $this->assertFalse($reader->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_reader_can_logout(): void
    {
        $reader = User::factory()->unverified()->create();

        $this->actingAs($reader)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_unverified_reader_cannot_use_interactive_features_or_admin_routes(): void
    {
        $reader = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create();
        $literature = Literature::factory()->create();
        $this->actingAs($reader);

        $this->put(route('reading-list.update', $literature), ['status' => 'want_to_read'])->assertRedirect(route('verification.notice'));
        $this->put(route('reviews.update', $literature), ['rating' => 4])->assertRedirect(route('verification.notice'));
        $this->post(route('discussions.store', $literature), ['body' => 'Test'])->assertRedirect(route('verification.notice'));
        $this->post(route('profiles.follow.store', $other))->assertRedirect(route('verification.notice'));
        $this->post(route('custom-lists.store'), ['title' => 'Test'])->assertRedirect(route('verification.notice'));
        $this->put(route('profiles.update'), ['name' => 'Changed'])->assertRedirect(route('verification.notice'));
        $this->get(route('quick-log.literatures'))->assertRedirect(route('verification.notice'));
        $this->get(route('admin.moderation.index'))->assertRedirect(route('verification.notice'));
        $this->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read'])->assertForbidden();

        foreach (['reading_lists', 'reviews', 'discussions', 'follows', 'custom_lists'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNotSame('Changed', $reader->fresh()->name);
    }

    public function test_verification_unlocks_interactive_features(): void
    {
        $reader = User::factory()->unverified()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($reader)->get($this->verificationUrl($reader))->assertRedirect(route('literatures.index'));
        $this->put(route('reading-list.update', $literature), ['status' => 'want_to_read'])->assertRedirect();

        $this->assertDatabaseHas('reading_lists', ['user_id' => $reader->id, 'literature_id' => $literature->id, 'status' => 'want_to_read']);
    }

    public function test_unverified_legacy_reader_only_sees_public_lists_and_no_owner_management_controls(): void
    {
        $reader = User::factory()->unverified()->create();
        $private = CustomList::factory()->create(['user_id' => $reader->id, 'is_private' => true]);
        $public = CustomList::factory()->create(['user_id' => $reader->id, 'is_private' => false]);

        $this->actingAs($reader)->get(route('profiles.lists', $reader))->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Lists')->where('isOwner', false)->has('lists.data', 1)->where('lists.data.0.title', $public->title));
        $this->get(route('custom-lists.show', $private))->assertForbidden();
        $this->get(route('custom-lists.show', $public))->assertInertia(fn (Assert $page) => $page->component('Lists/Show')->where('canEdit', false));
        $this->get(route('profiles.connections', $reader))->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Connections')->where('canManage', false)->where('counts.blocked', null)->where('routes.blocked', null));
        $this->get(route('profiles.connections', [$reader, 'relationship' => 'blocked']))->assertNotFound();
    }

    public function test_unverified_reader_can_view_literature_but_cannot_access_private_lists_via_preview(): void
    {
        $reader = User::factory()->unverified()->create();
        $literature = Literature::factory()->create();
        CustomList::factory()->create(['user_id' => $reader->id, 'is_private' => true]);

        $this->actingAs($reader)->get(route('literatures.show', $literature))->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Show')->where('viewer.authenticated', false)->where('viewer.custom_lists', [])->where('routes.verification', route('verification.notice')));
        $this->getJson(route('literatures.preview', $literature))->assertOk()
            ->assertJsonPath('lists', [])->assertJsonPath('viewer.authenticated', false)
            ->assertJsonPath('routes.verification', route('verification.notice'));
    }

    private function verificationUrl(User $reader): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $reader->id, 'hash' => sha1($reader->email),
        ]);
    }
}
