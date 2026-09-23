<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLiteratureMetadataOverrideRequest;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
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
        'backdrop_path',
        'backdrop_url',
        'publisher',
        'language',
        'format',
    ];

    public function edit(Literature $literature): Response
    {
        $literature->load([
            'apiSource',
            'authors',
            'metadataOverride.editor',
            'sourceMapping.canonicalWork.metadataOverride.editor',
        ]);

        $override = $literature->effectiveMetadataOverride();

        return Inertia::render('Admin/LiteratureMetadata/Edit', [
            'literature' => [
                'title' => $literature->displayTitle(),
                'source_name' => $literature->apiSource->name,
                'url' => route('literatures.show', $literature),
                'api_values' => [
                    'title' => $literature->title,
                    'original_title' => $literature->original_title,
                    'publication_year' => $literature->publication_year,
                    'tagline' => $literature->tagline,
                    'synopsis' => $literature->synopsis,
                    'cover_url' => $literature->cover_url,
                    'backdrop_url' => $literature->backdrop_url,
                    'publisher' => $literature->publisher,
                    'language' => $literature->language,
                    'format' => $literature->format,
                ],
                'effective' => [
                    'title' => $literature->displayTitle(),
                    'year' => $literature->displayPublicationYear(),
                    'cover_url' => $literature->displayCoverUrl(),
                    'backdrop_url' => $literature->displayBackdropUrl(),
                ],
            ],
            'override' => $override === null ? null : [
                ...collect([...self::METADATA_FIELDS, 'source_url', 'notes'])
                    ->mapWithKeys(fn (string $field): array => [$field => $override->{$field}])
                    ->all(),
                'uploaded_cover_url' => $override->uploadedCoverUrl(),
                'uploaded_backdrop_url' => $override->uploadedBackdropUrl(),
                'updated_label' => $override->updated_at->diffForHumans(),
                'editor_name' => $override->editor?->name,
            ],
            'updateUrl' => route('admin.literatures.metadata.update', $literature),
            'maxYear' => now()->year + 5,
        ]);
    }

    public function update(
        UpdateLiteratureMetadataOverrideRequest $request,
        Literature $literature,
    ): RedirectResponse {
        $validated = $request->validated();
        $override = $literature->effectiveMetadataOverride();
        $oldCoverPath = $override?->cover_path;
        $oldBackdropPath = $override?->backdrop_path;

        if ($request->boolean('reset')) {
            DB::transaction(function () use ($override): void {
                $override?->delete();
            });
            $this->deleteManagedImage($oldCoverPath, 'literature-covers');
            $this->deleteManagedImage($oldBackdropPath, 'literature-backdrops');

            return redirect()
                ->route('literatures.show', $literature)
                ->with('success', 'Curated metadata was reset to the API values.');
        }

        $canonicalWorkId = $literature->sourceMapping?->canonical_work_id;
        $newCoverPath = null;
        $newBackdropPath = null;

        try {
            $newCoverPath = $request->hasFile('cover_upload')
                ? $this->storeUploadedImage(
                    $request->file('cover_upload'),
                    $literature,
                    $canonicalWorkId,
                    'literature-covers',
                    'cover_upload',
                    'cover',
                )
                : null;
            $newBackdropPath = $request->hasFile('backdrop_upload')
                ? $this->storeUploadedImage(
                    $request->file('backdrop_upload'),
                    $literature,
                    $canonicalWorkId,
                    'literature-backdrops',
                    'backdrop_upload',
                    'hero artwork',
                )
                : null;
            $values = collect($validated)
                ->except([
                    'cover_upload',
                    'remove_cover_upload',
                    'backdrop_upload',
                    'remove_backdrop_upload',
                    'reset',
                ])
                ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
                ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
                ->all();
            $values['cover_path'] = $newCoverPath
                ?? ($request->boolean('remove_cover_upload') ? null : $oldCoverPath);
            $values['backdrop_path'] = $newBackdropPath
                ?? ($request->boolean('remove_backdrop_upload') ? null : $oldBackdropPath);
            $hasMetadataOverride = collect(self::METADATA_FIELDS)
                ->contains(fn (string $field): bool => filled($values[$field] ?? null));

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
                $this->deleteManagedImage($newCoverPath, 'literature-covers');
            }

            if ($newBackdropPath !== null) {
                $this->deleteManagedImage($newBackdropPath, 'literature-backdrops');
            }

            throw $exception;
        }

        if ($oldCoverPath !== null && $oldCoverPath !== $values['cover_path']) {
            $this->deleteManagedImage($oldCoverPath, 'literature-covers');
        }

        if ($oldBackdropPath !== null && $oldBackdropPath !== $values['backdrop_path']) {
            $this->deleteManagedImage($oldBackdropPath, 'literature-backdrops');
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

    private function storeUploadedImage(
        UploadedFile $image,
        Literature $literature,
        ?int $canonicalWorkId,
        string $directory,
        string $inputName,
        string $label,
    ): string {
        $scope = $canonicalWorkId === null
            ? "literature-{$literature->id}"
            : "canonical-{$canonicalWorkId}";
        $path = $image->storeAs(
            "{$directory}/{$scope}",
            Str::uuid()->toString().'.'.$image->extension(),
            'public',
        );

        if (! is_string($path)) {
            throw ValidationException::withMessages([
                $inputName => "The {$label} could not be stored. Please try again.",
            ]);
        }

        return $path;
    }

    private function deleteManagedImage(?string $path, string $directory): void
    {
        if (! $this->isManagedImagePath($path, $directory)) {
            return;
        }

        if (! Storage::disk('public')->delete($path)) {
            Log::warning('A managed literature image could not be deleted.', [
                'image_path' => $path,
            ]);
        }
    }

    private function isManagedImagePath(?string $path, string $directory): bool
    {
        if (blank($path)) {
            return false;
        }

        $normalizedPath = str_replace('\\', '/', $path);

        return Str::startsWith($normalizedPath, "{$directory}/")
            && ! in_array('..', explode('/', $normalizedPath), true);
    }
}
