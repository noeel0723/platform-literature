<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAuthorMetadataRequest;
use App\Models\Author;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuthorMetadataController extends Controller
{
    public function edit(Author $author): Response
    {
        return Inertia::render('Admin/AuthorMetadata/Edit', [
            'author' => [
                'name' => $author->name,
                'url' => route('authors.show', $author),
                'biography_override' => $author->biography_override,
                'image_url_override' => $author->image_url_override,
                'uploaded_image_url' => $author->uploadedImageUrl(),
                'api_biography' => $author->biography,
                'api_image_url' => $author->image_url,
            ],
            'updateUrl' => route('admin.authors.metadata.update', $author),
        ]);
    }

    public function update(UpdateAuthorMetadataRequest $request, Author $author): RedirectResponse
    {
        $validated = $request->validated();
        $newPath = null;
        $oldPath = null;
        $savedPath = null;

        try {
            if ($request->hasFile('image_upload')) {
                $newPath = $request->file('image_upload')->store('author-portraits', 'public');
                if (! is_string($newPath)) {
                    throw ValidationException::withMessages(['image_upload' => 'The portrait could not be stored. Please try again.']);
                }
            }
            DB::transaction(function () use ($author, $request, $validated, $newPath, &$oldPath, &$savedPath): void {
                $lockedAuthor = Author::query()->whereKey($author->id)->lockForUpdate()->firstOrFail();
                $oldPath = $lockedAuthor->image_path;
                $savedPath = $newPath ?? ($request->boolean('remove_image_upload') ? null : $oldPath);
                $lockedAuthor->update([
                    'biography_override' => array_key_exists('biography_override', $validated)
                        ? (filled($validated['biography_override']) ? trim($validated['biography_override']) : null)
                        : $lockedAuthor->biography_override,
                    'image_url_override' => array_key_exists('image_url_override', $validated)
                        ? (filled($validated['image_url_override']) ? trim($validated['image_url_override']) : null)
                        : $lockedAuthor->image_url_override,
                    'image_path' => $savedPath,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                $this->deleteManagedImage($newPath);
            }
            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $savedPath) {
            $this->deleteManagedImage($oldPath);
        }

        return redirect()->route('authors.show', $author)->with('success', 'Author details updated.');
    }

    private function deleteManagedImage(string $path): void
    {
        if (! preg_match('#\Aauthor-portraits/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z#', $path)) {
            return;
        }
        if (! Storage::disk('public')->delete($path)) {
            Log::warning('A managed author portrait could not be deleted.', ['image_path' => $path]);
        }
    }
}
