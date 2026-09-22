<x-app-shell title="Curate {{ $literature->displayTitle() }}">
    @php
        $fields = [
            'title' => ['label' => 'Display title', 'type' => 'text', 'base' => $literature->title],
            'original_title' => ['label' => 'Original or edition title', 'type' => 'text', 'base' => $literature->original_title],
            'publication_year' => ['label' => 'Publication year', 'type' => 'number', 'base' => $literature->publication_year],
            'tagline' => ['label' => 'Short description', 'type' => 'textarea', 'base' => $literature->tagline],
            'synopsis' => ['label' => 'Synopsis', 'type' => 'textarea', 'base' => $literature->synopsis],
            'publisher' => ['label' => 'Publisher', 'type' => 'text', 'base' => $literature->publisher],
            'language' => ['label' => 'Language code', 'type' => 'text', 'base' => $literature->language],
            'format' => ['label' => 'Format', 'type' => 'text', 'base' => $literature->format],
        ];
        $uploadedCoverUrl = $override?->uploadedCoverUrl();
        $uploadedBackdropUrl = $override?->uploadedBackdropUrl();
    @endphp

    <section class="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
        <div class="flex flex-col gap-4 border-b border-ink-950/15 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-coral">Admin curation</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink-950">Edit literature metadata</h1>
                <p class="mt-2 text-sm text-ink-950/55">{{ $literature->displayTitle() }} · {{ $literature->apiSource->name }}</p>
            </div>
            <a href="{{ route('literatures.show', $literature) }}" class="text-sm font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Back to literature</a>
        </div>

        <div class="mt-7 grid gap-7 lg:grid-cols-[minmax(0,1fr)_250px]">
            <form id="literature-metadata-form" action="{{ route('admin.literatures.metadata.update', $literature) }}" method="POST" enctype="multipart/form-data" novalidate class="space-y-5">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div id="metadata-validation-errors" role="alert" tabindex="-1" class="border border-red-700/25 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-bold">Metadata could not be saved. Please check the fields below.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="border border-ink-950/12 bg-white/45 p-5 sm:p-7">
                    <p class="text-sm leading-6 text-ink-900">Only filled fields override API metadata. Leave a field empty to inherit its current API value again.</p>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">Cover</h2>
                            <div class="mt-3 grid gap-4 sm:grid-cols-[minmax(0,1fr)_100px] sm:items-start">
                                <div>
                                    <label for="cover_upload" class="text-xs font-semibold text-ink-950">Upload cover</label>
                                    <input id="cover_upload" name="cover_upload" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 file:mr-3 file:border-0 file:bg-brand-sky/30 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-950 focus:border-brand-coral">
                                    <p class="mt-1.5 text-xs leading-5 text-ink-950/55">JPG, PNG, or WebP. Maximum file size 5 MB.</p>
                                    @error('cover_upload')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror

                                    @if ($uploadedCoverUrl)
                                        <label class="mt-3 flex items-center gap-2 text-xs font-semibold text-ink-950/70">
                                            <input name="remove_cover_upload" type="checkbox" value="1" @checked(old('remove_cover_upload')) class="size-4 accent-brand-coral">
                                            Remove uploaded cover
                                        </label>
                                    @endif
                                </div>

                                <div id="cover-upload-preview-shell" @class(['hidden' => ! $uploadedCoverUrl, 'overflow-hidden border border-ink-950/15 bg-brand-cream'])>
                                    <img id="cover-upload-preview" src="{{ $uploadedCoverUrl }}" alt="Current curated upload" class="aspect-[2/3] w-full object-cover">
                                </div>
                            </div>

                            <div class="my-4 flex items-center gap-3 text-[0.65rem] font-bold uppercase tracking-[0.16em] text-ink-950/45">
                                <span class="h-px flex-1 bg-ink-950/10"></span>
                                <span>Or</span>
                                <span class="h-px flex-1 bg-ink-950/10"></span>
                            </div>

                            <label for="cover_url" class="text-xs font-semibold text-ink-950">External Cover URL</label>
                            <input id="cover_url" name="cover_url" type="url" value="{{ old('cover_url', $override?->cover_url) }}" placeholder="https://..." class="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral">
                            <p class="mt-1.5 text-xs leading-5 text-ink-950/55">Uploaded cover takes priority over an external cover URL.</p>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-ink-950/45">API value: {{ filled($literature->cover_url) ? $literature->cover_url : 'Unavailable' }}</p>
                            @error('cover_url')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                        </div>

                        <div class="border-t border-ink-950/10 pt-5 sm:col-span-2">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">Hero artwork</h2>
                            <div class="mt-3 grid gap-4 sm:grid-cols-[minmax(0,1fr)_180px] sm:items-start">
                                <div>
                                    <label for="backdrop_upload" class="text-xs font-semibold text-ink-950">Upload hero artwork</label>
                                    <input id="backdrop_upload" name="backdrop_upload" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 file:mr-3 file:border-0 file:bg-brand-sky/30 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ink-950 focus:border-brand-coral">
                                    <p class="mt-1.5 text-xs leading-5 text-ink-950/55">JPG, PNG, or WebP. A wide landscape image works best. Maximum file size 5 MB.</p>
                                    @error('backdrop_upload')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror

                                    @if ($uploadedBackdropUrl)
                                        <label class="mt-3 flex items-center gap-2 text-xs font-semibold text-ink-950/70">
                                            <input name="remove_backdrop_upload" type="checkbox" value="1" @checked(old('remove_backdrop_upload')) class="size-4 accent-brand-coral">
                                            Remove uploaded hero artwork
                                        </label>
                                    @endif
                                </div>

                                <div id="backdrop-upload-preview-shell" @class(['hidden' => ! $uploadedBackdropUrl, 'overflow-hidden border border-ink-950/15 bg-brand-cream'])>
                                    <img id="backdrop-upload-preview" src="{{ $uploadedBackdropUrl }}" alt="Current curated hero artwork" class="aspect-video w-full object-cover">
                                </div>
                            </div>

                            <div class="my-4 flex items-center gap-3 text-[0.65rem] font-bold uppercase tracking-[0.16em] text-ink-950/45">
                                <span class="h-px flex-1 bg-ink-950/10"></span>
                                <span>Or</span>
                                <span class="h-px flex-1 bg-ink-950/10"></span>
                            </div>

                            <label for="backdrop_url" class="text-xs font-semibold text-ink-950">External Hero Artwork URL</label>
                            <input id="backdrop_url" name="backdrop_url" type="url" value="{{ old('backdrop_url', $override?->backdrop_url) }}" placeholder="https://..." class="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral">
                            <p class="mt-1.5 text-xs leading-5 text-ink-950/55">Uploaded hero artwork takes priority over an external hero URL.</p>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-ink-950/45">API value: {{ filled($literature->backdrop_url) ? $literature->backdrop_url : 'Unavailable' }}</p>
                            @error('backdrop_url')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                        </div>

                        @foreach ($fields as $name => $field)
                            <div @class(['sm:col-span-2' => in_array($name, ['tagline', 'synopsis'], true)])>
                                <label for="{{ $name }}" class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">{{ $field['label'] }}</label>
                                @if ($field['type'] === 'textarea')
                                    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $name === 'synopsis' ? 7 : 3 }}" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm leading-6 text-ink-950 outline-none focus:border-brand-coral">{{ old($name, $override?->{$name}) }}</textarea>
                                @else
                                    <input id="{{ $name }}" name="{{ $name }}" type="{{ $field['type'] }}" value="{{ old($name, $override?->{$name}) }}" @if ($name === 'publication_year') min="1" max="{{ now()->year + 5 }}" @endif class="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral">
                                @endif
                                <p class="mt-1.5 line-clamp-2 text-xs leading-5 text-ink-950/45">API value: {{ filled($field['base']) ? $field['base'] : 'Unavailable' }}</p>
                                @error($name)<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border border-ink-950/12 bg-white/45 p-5 sm:p-7">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-ink-950">Curation provenance</h2>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="source_url" class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">Reference URL</label>
                            <input id="source_url" name="source_url" type="url" value="{{ old('source_url', $override?->source_url) }}" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral">
                            @error('source_url')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="notes" class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-950">Internal notes</label>
                            <textarea id="notes" name="notes" rows="3" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/65 px-3 py-2.5 text-sm text-ink-950 outline-none focus:border-brand-coral">{{ old('notes', $override?->notes) }}</textarea>
                            @error('notes')<p class="mt-1 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" data-metadata-save class="rounded-full bg-brand-coral px-5 py-3 text-sm font-bold text-white transition hover:bg-ink-950 disabled:cursor-wait disabled:opacity-60">Save curated metadata</button>
                    @if ($override)
                        <button type="submit" name="reset" value="1" class="rounded-full border border-ink-950/20 px-5 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral hover:text-brand-coral">Reset all to API</button>
                    @endif
                </div>
            </form>

            <aside class="h-fit border border-ink-950/12 bg-brand-sky/15 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-ink-950/55">Effective preview</p>
                <div class="mt-4 aspect-video overflow-hidden border border-ink-950/15 bg-brand-cream">
                    @if ($literature->displayBackdropUrl())
                        <img src="{{ $literature->displayBackdropUrl() }}" alt="" class="size-full object-cover">
                    @elseif ($literature->displayCoverUrl())
                        <img src="{{ $literature->displayCoverUrl() }}" alt="" class="size-full scale-110 object-cover blur-sm">
                    @endif
                </div>
                <p class="mt-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-ink-950/45">Hero artwork</p>
                <div class="mt-4 aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-cream">
                    @if ($literature->displayCoverUrl())
                        <img src="{{ $literature->displayCoverUrl() }}" alt="" class="size-full object-cover">
                    @endif
                </div>
                <h2 class="mt-4 text-lg font-bold text-ink-950">{{ $literature->displayTitle() }}</h2>
                <p class="mt-1 text-sm text-ink-950/55">{{ $literature->displayPublicationYear() ?? 'Year unavailable' }}</p>
                @if ($override)
                    <p class="mt-4 border-t border-ink-950/10 pt-4 text-xs leading-5 text-ink-950/55">Last curated {{ $override->updated_at->diffForHumans() }}@if($override->editor) by {{ $override->editor->name }}@endif.</p>
                @endif
            </aside>
        </div>
    </section>

    <script>
        (() => {
            const form = document.getElementById('literature-metadata-form');
            const saveButton = form?.querySelector('[data-metadata-save]');
            const validationErrors = document.getElementById('metadata-validation-errors');
            const previewCleanups = [];

            const bindUploadPreview = (inputId, previewId, shellId, alt) => {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const previewShell = document.getElementById(shellId);
                let objectUrl = null;

                if (! (input instanceof HTMLInputElement) || ! (preview instanceof HTMLImageElement) || ! previewShell) {
                    return;
                }

                input.addEventListener('change', () => {
                    if (objectUrl) {
                        URL.revokeObjectURL(objectUrl);
                        objectUrl = null;
                    }

                    const [file] = input.files;

                    if (! file) {
                        return;
                    }

                    objectUrl = URL.createObjectURL(file);
                    preview.src = objectUrl;
                    preview.alt = alt;
                    previewShell.classList.remove('hidden');
                });

                previewCleanups.push(() => {
                    if (objectUrl) {
                        URL.revokeObjectURL(objectUrl);
                    }
                });
            };

            validationErrors?.focus();

            form?.addEventListener('submit', (event) => {
                if (event.submitter !== saveButton || ! (saveButton instanceof HTMLButtonElement)) {
                    return;
                }

                saveButton.disabled = true;
                saveButton.textContent = 'Saving…';
            });

            bindUploadPreview('cover_upload', 'cover-upload-preview', 'cover-upload-preview-shell', 'Selected cover preview');
            bindUploadPreview('backdrop_upload', 'backdrop-upload-preview', 'backdrop-upload-preview-shell', 'Selected hero artwork preview');

            window.addEventListener('beforeunload', () => {
                previewCleanups.forEach((cleanup) => cleanup());
            });
        })();
    </script>
</x-app-shell>
