<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function showRegister(): RedirectResponse
    {
        return redirect()->route('home', ['register' => 1]);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'username', 'email', 'password']));
        $user->forceFill(['onboarding_required' => true])->save();

        Auth::login($user);
        $request->session()->regenerate();

        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return redirect()->route('verification.notice')
                ->with('status', 'verification-send-failed');
        }

        return redirect()->route('verification.notice');
    }

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'csrf_token' => csrf_token(),
            'routes' => [
                'home' => route('home'),
                'login' => route('login'),
                'register' => route('register'),
                'password_request' => route('password.request'),
            ],
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $identifier = Str::lower(trim($request->validated('email')));
        $identifierColumn = filter_var($identifier, FILTER_VALIDATE_EMAIL) === false ? 'username' : 'email';
        $credentials = [
            $identifierColumn => $identifier,
            'password' => $request->validated('password'),
            'deactivated_at' => null,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The username, email address, or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($request->user()->needsOnboarding()) {
            return redirect()->route('onboarding.show');
        }

        return redirect()->intended(route('literatures.index'))
            ->with('success', 'Welcome back.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
