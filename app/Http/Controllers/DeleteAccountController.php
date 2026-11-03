<?php

namespace App\Http\Controllers;

use App\Services\Accounts\DeleteUserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeleteAccountController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, DeleteUserAccount $deleteAccount): RedirectResponse
    {
        $request->validateWithBag('deleteAccount', [
            'password' => ['required', 'string', 'current_password:web'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $deleteAccount->delete($request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
