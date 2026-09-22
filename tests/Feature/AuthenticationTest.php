<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_form_is_rendered_by_react_and_register_link_opens_landing_page(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('routes.home', route('home'))
                ->where('routes.login', route('login'))
                ->where('routes.register', route('register')));

        $this->get(route('register'))
            ->assertRedirect(route('home', ['register' => 1]));

        $this->get(route('home', ['register' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Index')
                ->where('viewer', null)
                ->where('routes.register', route('register')));
    }

    public function test_invalid_registration_returns_to_landing_page_with_errors_and_keeps_non_password_input(): void
    {
        $this->from(route('home'))->post(route('register'), [
            'name' => 'Imanuel',
            'username' => 'imanuel_reader',
            'email' => 'imanuel@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertRedirect(route('home'))
            ->assertSessionHasErrors('password');

        $this->assertSame('Imanuel', session()->getOldInput('name'));
        $this->assertSame('imanuel_reader', session()->getOldInput('username'));
        $this->assertSame('imanuel@example.com', session()->getOldInput('email'));
        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    public function test_user_can_register_and_is_automatically_authenticated(): void
    {
        $response = $this->post('/register', [
            'name' => 'Imanuel',
            'username' => 'imanuel_reader',
            'email' => 'imanuel@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('literatures.index'));
        $this->assertAuthenticated();
        $this->assertSame('imanuel_reader', User::query()->firstOrFail()->username);
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

    public function test_user_can_login_with_their_username(): void
    {
        $user = User::factory()->create([
            'username' => 'compact_reader',
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => 'compact_reader',
            'password' => 'password123',
        ])->assertRedirect(route('literatures.index'));

        $this->assertAuthenticatedAs($user);
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
