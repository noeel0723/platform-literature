<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FollowController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot follow your own profile.',
            ]);
        }

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        return back()->with('success', "You are now following {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->following()->detach($user);

        return back()->with('success', "You are no longer following {$user->name}.");
    }
}
