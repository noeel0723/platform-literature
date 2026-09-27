<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_open_recovery_forms_without_changing_login_layout(): void
    {
        config()->set('mail.default', 'log');

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('routes.password_request', route('password.request')));

        $this->get(route('password.request'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ForgotPassword')
                ->where('routes.email', route('password.email'))
                ->where('routes.home_login', route('home', ['login' => 1]))
                ->where('uses_log_mailer', true));

        $this->get(route('password.reset', ['token' => 'example-token', 'email' => 'reader@example.com']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'example-token')
                ->where('email', 'reader@example.com')
                ->where('routes.update', route('password.update'))
                ->where('routes.home_login', route('home', ['login' => 1])));
    }

    public function test_existing_account_receives_a_tokenized_reset_notification(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account matches that email, a password reset link has been prepared.');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_unknown_email_receives_the_same_message_without_creating_a_token(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account matches that email, a password reset link has been prepared.');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@example.com']);
    }

    public function test_request_rejects_an_invalid_email(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password_and_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password123',
            'remember_token' => 'original-remember-token',
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('home', ['login' => 1]))
            ->assertSessionMissing('success');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertNotSame('original-remember-token', $user->fresh()->getRememberToken());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
    }

    public function test_invalid_token_cannot_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password123']);

        $this->from(route('password.reset', 'invalid-token'))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])->assertRedirect(route('password.reset', 'invalid-token'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_expired_token_cannot_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password123']);
        $token = Password::createToken($user);
        $this->travel(61)->minutes();

        $this->from(route('password.reset', $token))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])->assertRedirect(route('password.reset', $token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }

    public function test_recovery_requests_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'unknown@example.com'])
                ->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertTooManyRequests();
    }
}
