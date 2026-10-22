<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('literatures.index');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $request->user()->email,
            'status' => $request->session()->get('status'),
            'expires_in' => config('auth.verification.expire'),
            'routes' => [
                'resend' => route('verification.send'),
                'home' => route('home'),
                'logout' => route('logout'),
            ],
        ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('literatures.index');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('literatures.index');
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
