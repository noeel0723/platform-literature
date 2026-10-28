<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOnboardingRequest;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Services\Literature\LiteratureBrowseService;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(
        Request $request,
        LiteratureBrowseService $browser,
        CanonicalLiteratureSearch $canonicalSearch,
        ProfilePagePresenter $presenter,
    ): Response|RedirectResponse {
        if (! $request->user()->needsOnboarding()) {
            return redirect()->route('home');
        }

        $step = max(1, min(3, (int) $request->user()->onboarding_step));
        $preferredFormats = $request->user()->preferredFormats();
        $formats = [
            ['value' => 'novel', 'label' => 'Novel'],
            ['value' => 'manga', 'label' => 'Manga'],
            ['value' => 'manhwa', 'label' => 'Manhwa'],
            ['value' => 'western-comic', 'label' => 'Western Comic'],
        ];
        $discovery = ['query' => '', 'groups' => [], 'results' => []];

        if ($step === 2) {
            $input = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:100']]);
            $discovery['query'] = trim($input['q'] ?? '');

            if ($discovery['query'] !== '') {
                $discovery['results'] = $canonicalSearch->rankedQuery($discovery['query'])
                    ->limit(6)->get()->map(fn (Literature $literature): array => $presenter->literature($literature))->all();
            } else {
                $discovery['groups'] = collect($formats)
                    ->filter(fn (array $format): bool => $preferredFormats === [] || in_array($format['value'], $preferredFormats, true))
                    ->map(fn (array $format): array => [
                        'format' => $format['label'],
                        'literatures' => $browser->popular(2, [$format['value']])
                            ->map(fn (Literature $literature): array => $presenter->literature($literature))->all(),
                    ])->values()->all();
            }
        }

        return Inertia::render('Onboarding/Show', [
            'step' => $step,
            'preferredFormats' => $preferredFormats,
            'formats' => $formats,
            'discovery' => $discovery,
            'routes' => [
                'update' => route('onboarding.update'),
                'discovery' => route('onboarding.show'),
                'browse' => route('literature.browse', ['q' => $discovery['query']]),
            ],
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
