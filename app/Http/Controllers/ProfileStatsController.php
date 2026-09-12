<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Profile\UserStatsService;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileStatsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        User $user,
        UserStatsService $stats,
        ProfilePagePresenter $presenter,
    ): Response {
        return Inertia::render('Profile/Stats', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'stats', $request->user()),
            'stats' => $stats->forUser($user, $request->integer('year') ?: null),
        ]);
    }
}
