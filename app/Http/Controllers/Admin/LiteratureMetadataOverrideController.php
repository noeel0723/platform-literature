<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLiteratureMetadataOverrideRequest;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LiteratureMetadataOverrideController extends Controller
{
    /** @var list<string> */
    private const METADATA_FIELDS = [
        'title',
        'original_title',
        'publication_year',
        'tagline',
        'synopsis',
        'cover_url',
        'publisher',
        'language',
        'format',
    ];

    public function edit(Literature $literature): View
    {
        $literature->load([
            'apiSource',
            'authors',
            'metadataOverride.editor',
            'sourceMapping.canonicalWork.metadataOverride.editor',
        ]);

        return view('admin.literature-metadata.edit', [
            'literature' => $literature,
            'override' => $literature->effectiveMetadataOverride(),
        ]);
    }

    public function update(
        UpdateLiteratureMetadataOverrideRequest $request,
        Literature $literature,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($request->boolean('reset')) {
            $literature->effectiveMetadataOverride()?->delete();

            return redirect()
                ->route('literatures.show', $literature)
                ->with('success', 'Curated metadata was reset to the API values.');
        }

        $values = collect($validated)
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
            ->all();
        $hasMetadataOverride = collect(self::METADATA_FIELDS)
            ->contains(fn (string $field): bool => filled($values[$field] ?? null));

        if (! $hasMetadataOverride) {
            $literature->effectiveMetadataOverride()?->delete();

            return redirect()
                ->route('literatures.show', $literature)
                ->with('success', 'Curated metadata was reset to the API values.');
        }

        $canonicalWorkId = $literature->sourceMapping?->canonical_work_id;
        $identity = $canonicalWorkId === null
            ? ['literature_id' => $literature->id]
            : ['canonical_work_id' => $canonicalWorkId];

        LiteratureMetadataOverride::query()->updateOrCreate(
            $identity,
            [
                'literature_id' => $literature->id,
                'canonical_work_id' => $canonicalWorkId,
                ...collect(self::METADATA_FIELDS)->mapWithKeys(
                    fn (string $field): array => [$field => $values[$field] ?? null],
                )->all(),
                'source_url' => $values['source_url'] ?? null,
                'notes' => $values['notes'] ?? null,
                'edited_by' => $request->user()->id,
            ],
        );

        return redirect()
            ->route('literatures.show', $literature)
            ->with('success', 'Curated metadata was saved and will take priority over API metadata.');
    }
}
