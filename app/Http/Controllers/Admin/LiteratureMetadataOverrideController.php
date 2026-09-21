<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLiteratureMetadataOverrideRequest;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LiteratureMetadataOverrideController extends Controller
{
    /** @var list<string> */
    private const METADATA_FIELDS = [
        'title',
        'original_title',
        'publication_year',
        'tagline',
        'synopsis',
        'cover_path',
        'cover_url',
        'backdrop_url',
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
        $override = $literature->effectiveMetadataOverride();
        $oldCoverPath = $override?->cover_path;

        if ($request->boolean('reset')) {
            DB::transaction(function () use ($override): void {
                $override?->delete();
            });
            $this->deleteManagedCover($oldCoverPath);

            return redirect()
                ->route('literatures.show', $literature)
                ->with('success', 'Curated metadata was reset to the API values.');
        }

        $canonicalWorkId = $literature->sourceMapping?->canonical_work_id;
        $newCoverPath = $request->hasFile('cover_upload')
            ? $this->storeUploadedCover($request->file('cover_upload'), $literature, $canonicalWorkId)
            : null;
        $values = collect($validated)
            ->except(['cover_upload', 'remove_cover_upload', 'reset'])
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
            ->all();
        $values['cover_path'] = $newCoverPath
            ?? ($request->boolean('remove_cover_upload') ? null : $oldCoverPath);
        $hasMetadataOverride = collect(self::METADATA_FIELDS)
            ->contains(fn (string $field): bool => filled($values[$field] ?? null));

        try {
            DB::transaction(function () use (
                $hasMetadataOverride,
                $override,
                $literature,
                $canonicalWorkId,
                $values,
                $request,
            ): void {
                if (! $hasMetadataOverride) {
                    $override?->delete();

                    return;
                }

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
            });
        } catch (Throwable $exception) {
            if ($newCoverPath !== null) {
                $this->deleteManagedCover($newCoverPath);
            }

            throw $exception;
        }

        if ($oldCoverPath !== null && $oldCoverPath !== $values['cover_path']) {
            $this->deleteManagedCover($oldCoverPath);
        }

        if (! $hasMetadataOverride) {
            return redirect()
                ->route('literatures.show', $literature)
                ->with('success', 'Curated metadata was reset to the API values.');
        }

        return redirect()
            ->route('literatures.show', $literature)
            ->with('success', 'Curated metadata was saved and will take priority over API metadata.');
    }

    private function storeUploadedCover(
        UploadedFile $cover,
        Literature $literature,
        ?int $canonicalWorkId,
    ): string {
        $scope = $canonicalWorkId === null
            ? "literature-{$literature->id}"
            : "canonical-{$canonicalWorkId}";
        $path = $cover->storeAs(
            "literature-covers/{$scope}",
            Str::uuid()->toString().'.'.$cover->extension(),
            'public',
        );

        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'cover_upload' => 'The cover could not be stored. Please try again.',
            ]);
        }

        return $path;
    }

    private function deleteManagedCover(?string $path): void
    {
        if (! $this->isManagedCoverPath($path)) {
            return;
        }

        if (! Storage::disk('public')->delete($path)) {
            Log::warning('A managed literature cover could not be deleted.', [
                'cover_path' => $path,
            ]);
        }
    }

    private function isManagedCoverPath(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        $normalizedPath = str_replace('\\', '/', $path);

        return Str::startsWith($normalizedPath, 'literature-covers/')
            && ! in_array('..', explode('/', $normalizedPath), true);
    }
}
