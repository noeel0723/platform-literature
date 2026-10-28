<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class CancelRegistrationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canCancelRegistration(), 403);
        $request->validate(['confirmation' => ['required', 'accepted']]);

        DB::transaction(function () use ($request): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->canCancelRegistration(), 403);

            Password::broker()->deleteToken($user);
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            $user->delete();
        }, 3);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home', ['register' => 1]);
    }
}
