<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register', [
            'routes' => [
                'register' => route('register'),
                'login' => route('login'),
            ],
        ]);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'username', 'email', 'password']));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('literatures.index')
            ->with('success', 'Your account is ready. You can now save literature to your Readlist.');
    }

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'routes' => [
                'login' => route('login'),
                'register' => route('register'),
            ],
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            ...$request->safe()->only(['email', 'password']),
            'deactivated_at' => null,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The email address or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('literatures.index'))
            ->with('success', 'Welcome back.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been logged out.');
    }
}
