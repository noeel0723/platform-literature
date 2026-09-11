<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BlockController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $blocker = $request->user();

        if ($blocker->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot block your own profile.',
            ]);
        }

        DB::transaction(function () use ($blocker, $user): void {
            $blocker->following()->detach($user);
            $user->following()->detach($blocker);
            $blocker->blockedUsers()->syncWithoutDetaching([$user->id]);
        });

        return back()->with('success', "{$user->name} has been blocked.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->blockedUsers()->detach($user);

        return back()->with('success', "{$user->name} has been unblocked.");
    }
}
