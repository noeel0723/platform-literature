<x-app-shell title="Curate {{ $literature->displayTitle() }}">
    @php
        $fields = [
            'title' => ['label' => 'Display title', 'type' => 'text', 'base' => $literature->title],
            'original_title' => ['label' => 'Original or edition title', 'type' => 'text', 'base' => $literature->original_title],
            'publication_year' => ['label' => 'Publication year', 'type' => 'number', 'base' => $literature->publication_year],
            'tagline' => ['label' => 'Short description', 'type' => 'textarea', 'base' => $literature->tagline],
            'synopsis' => ['label' => 'Synopsis', 'type' => 'textarea', 'base' => $literature->synopsis],
            'cover_url' => ['label' => 'Cover URL', 'type' => 'url', 'base' => $literature->cover_url],
            'backdrop_url' => ['label' => 'Hero artwork URL', 'type' => 'url', 'base' => $literature->backdrop_url],
            'publisher' => ['label' => 'Publisher', 'type' => 'text', 'base' => $literature->publisher],
            'language' => ['label' => 'Language code', 'type' => 'text', 'base' => $literature->language],
            'format' => ['label' => 'Format', 'type' => 'text', 'base' => $literature->format],
        ];
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
            <form action="{{ route('admin.literatures.metadata.update', $literature) }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="border border-ink-950/12 bg-white/45 p-5 sm:p-7">
                    <p class="text-sm leading-6 text-ink-900">Only filled fields override API metadata. Leave a field empty to inherit its current API value again.</p>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        @foreach ($fields as $name => $field)
                            <div @class(['sm:col-span-2' => in_array($name, ['tagline', 'synopsis', 'cover_url', 'backdrop_url'], true)])>
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
                    <button class="rounded-full bg-brand-coral px-5 py-3 text-sm font-bold text-white transition hover:bg-ink-950">Save curated metadata</button>
                    @if ($override)
                        <button name="reset" value="1" class="rounded-full border border-ink-950/20 px-5 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral hover:text-brand-coral">Reset all to API</button>
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
</x-app-shell>
