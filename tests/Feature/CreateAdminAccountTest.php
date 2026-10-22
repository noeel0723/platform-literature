<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateAdminAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_is_created_with_a_hashed_password_and_requires_email_verification(): void
    {
        Notification::fake();

        $this->artisan('accounts:create-admin')
            ->expectsQuestion('Name', 'New Admin')
            ->expectsQuestion('Username', 'NEW_ADMIN')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Password', 'strong-password123')
            ->expectsQuestion('Confirm password', 'strong-password123')
            ->assertSuccessful();

        $admin = User::query()->sole();
        $this->assertSame('new_admin', $admin->username);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('strong-password123', $admin->password));
        Notification::assertSentTo($admin, VerifyEmail::class);
    }

    public function test_invalid_admin_details_do_not_create_an_account(): void
    {
        Notification::fake();

        $this->artisan('accounts:create-admin')
            ->expectsQuestion('Name', 'Admin')
            ->expectsQuestion('Username', 'new_admin')
            ->expectsQuestion('Email', 'invalid-email')
            ->expectsQuestion('Password', 'password123')
            ->expectsQuestion('Confirm password', 'different-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_non_interactive_admin_creation_is_refused(): void
    {
        $this->artisan('accounts:create-admin', ['--no-interaction' => true])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
