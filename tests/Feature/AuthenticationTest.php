<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_register_and_is_automatically_authenticated(): void
    {
        $response = $this->post('/register', [
            'name' => 'Imanuel',
            'email' => 'imanuel@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('literatures.index'));
        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('password123', User::query()->firstOrFail()->password));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('literatures.index'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
