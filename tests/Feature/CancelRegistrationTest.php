<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CancelRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_cancel_an_account(): void
    {
        $reader = User::factory()->unverified()->pendingOnboarding()->create();

        $this->delete(route('registration.cancel'), ['confirmation' => true])->assertRedirect(route('login'));

        $this->assertModelExists($reader);
    }

    #[TestWith([])]
    #[TestWith(['false'])]
    public function test_cancellation_requires_explicit_confirmation(?string $confirmation = null): void
    {
        $reader = User::factory()->unverified()->pendingOnboarding()->create();

        $this->actingAs($reader)->deleteJson(route('registration.cancel'), ['confirmation' => $confirmation])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation');

        $this->assertModelExists($reader);
        $this->assertAuthenticatedAs($reader);
    }

    public function test_cancelling_only_removes_the_current_pending_account_and_its_authentication_data(): void
    {
        $reader = User::factory()->unverified()->pendingOnboarding()->create();
        $other = User::factory()->create();
        $literature = Literature::factory()->create();
        Password::broker()->createToken($reader);
        Password::broker()->createToken($other);
        foreach ([$reader, $other] as $user) {
            DB::table('sessions')->insert([
                'id' => 'reader-session-'.$user->id, 'user_id' => $user->id,
                'payload' => base64_encode('session'), 'last_activity' => now()->timestamp,
            ]);
        }
        $oldLink = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $reader->id, 'hash' => sha1($reader->email)]);

        $this->actingAs($reader)->delete(route('registration.cancel'), ['confirmation' => true, 'user_id' => $other->id])
            ->assertRedirect(route('home', ['register' => 1]));

        $this->assertGuest();
        $this->assertModelMissing($reader);
        $this->assertModelExists($other);
        $this->assertModelExists($literature);
        $this->assertDatabaseMissing('sessions', ['user_id' => $reader->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $reader->email]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $other->email]);
        $this->get($oldLink)->assertNotFound();
    }

    public function test_a_cancelled_registration_can_be_recreated_with_the_correct_email(): void
    {
        $reader = User::factory()->unverified()->pendingOnboarding()->create(['username' => 'new_reader']);
        Notification::fake();
        $this->actingAs($reader)->delete(route('registration.cancel'), ['confirmation' => true]);

        $this->post(route('register'), [
            'name' => 'New Reader', 'username' => 'new_reader', 'email' => 'correct@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['username' => 'new_reader', 'email' => 'correct@example.com', 'email_verified_at' => null]);
        $this->assertDatabaseMissing('users', ['id' => $reader->id]);
        $this->assertDatabaseCount('users', 1);
        Notification::assertSentTo(User::query()->sole(), VerifyEmail::class);
    }

    #[TestWith(['verified'])]
    #[TestWith(['legacy'])]
    #[TestWith(['admin'])]
    public function test_cancellation_cannot_delete_verified_legacy_or_admin_accounts(string $type): void
    {
        $reader = match ($type) {
            'verified' => User::factory()->pendingOnboarding()->create(),
            'legacy' => User::factory()->unverified()->create(),
            'admin' => User::factory()->unverified()->pendingOnboarding()->create(['role' => User::ROLE_ADMIN]),
        };

        $this->actingAs($reader)->delete(route('registration.cancel'), ['confirmation' => true])->assertForbidden();

        $this->assertModelExists($reader);
    }

    public function test_the_verification_notice_only_offers_cancellation_for_a_pending_registration(): void
    {
        $reader = User::factory()->unverified()->pendingOnboarding()->create();
        $legacy = User::factory()->unverified()->create();

        $response = $this->actingAs($reader)->get(route('verification.notice'));
        $this->assertSame(route('registration.cancel'), $response->inertiaProps('routes.cancel'));
        $response = $this->actingAs($legacy)->get(route('verification.notice'));
        $this->assertNull($response->inertiaProps('routes.cancel'));
    }
}
