<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOnboardingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->needsOnboarding()) {
            return redirect()->route('home');
        }

        return Inertia::render('Onboarding/Show', [
            'step' => max(1, min(3, (int) $request->user()->onboarding_step)),
            'preferredFormats' => $request->user()->preferredFormats(),
            'formats' => [
                ['value' => 'novel', 'label' => 'Novel'],
                ['value' => 'manga', 'label' => 'Manga'],
                ['value' => 'manhwa', 'label' => 'Manhwa'],
                ['value' => 'western-comic', 'label' => 'Western Comic'],
            ],
            'routes' => ['update' => route('onboarding.update'), 'catalog' => route('literatures.index')],
        ]);
    }

    public function update(UpdateOnboardingRequest $request): RedirectResponse
    {
        $finished = DB::transaction(function () use ($request): bool {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! $user->needsOnboarding()) {
                return true;
            }

            $action = $request->validated('action');
            $step = max(1, min(3, (int) $user->onboarding_step));
            if ($action === 'complete' && $step !== 3) {
                throw ValidationException::withMessages(['action' => 'Continue to the last step, or choose Skip to finish early.']);
            }

            $finished = in_array($action, ['complete', 'skip'], true);
            $user->forceFill([
                'preferred_formats' => $request->validated('formats', $user->preferredFormats()),
                'onboarding_step' => match ($action) {
                    'next' => min(3, $step + 1),
                    'back' => max(1, $step - 1),
                    default => $step,
                },
                'onboarding_completed_at' => $finished ? now() : null,
            ])->save();

            return $finished;
        }, 3);

        $request->user()->refresh();

        return redirect()->route($finished ? 'home' : 'onboarding.show');
    }
}
