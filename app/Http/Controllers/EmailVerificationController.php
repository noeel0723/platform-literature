<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->needsOnboarding() ? 'onboarding.show' : 'literatures.index');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $request->user()->email,
            'status' => $request->session()->get('status'),
            'expires_in' => config('auth.verification.expire'),
            'routes' => [
                'resend' => route('verification.send'),
                'home' => route('home'),
                'logout' => route('logout'),
                'cancel' => $request->user()->canCancelRegistration() ? route('registration.cancel') : null,
            ],
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_if($request->user() !== null && (string) $request->user()->getKey() !== (string) $request->route('id'), 403);

        [$user, $newlyVerified] = DB::transaction(function () use ($request): array {
            $user = User::query()->whereKey($request->route('id'))->lockForUpdate()->firstOrFail();
            abort_unless($user->isActive() && hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')), 403);

            if ($user->hasVerifiedEmail()) {
                return [$user, false];
            }

            $user->markEmailAsVerified();

            return [$user, true];
        }, 3);

        if ($newlyVerified) {
            event(new Verified($user));
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget('url.intended');
        } elseif ($request->user() === null) {
            // A consumed verification link must not become a reusable sign-in link.
            return redirect()->route('home', ['login' => 1]);
        } else {
            Auth::setUser($user);
        }

        return redirect()->route($user->needsOnboarding() ? 'onboarding.show' : 'literatures.index');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->needsOnboarding() ? 'onboarding.show' : 'literatures.index');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['verification' => 'We could not send your verification email. Please try again later.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
