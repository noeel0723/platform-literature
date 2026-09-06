<x-app-shell title="Edit profile">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-5xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <a href="{{ route('profiles.show', $user) }}" class="text-sm font-bold text-ink-950/65 transition hover:text-brand-coral">&larr; Back to profile</a>
            <p class="mt-8 text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Profile &amp; identity</p>
            <h1 class="mt-3 font-serif text-5xl font-bold text-ink-950">Edit your profile</h1>
            <p class="mt-4 max-w-2xl leading-7 text-ink-950/65">Choose how other Literahaven readers see you and highlight the works and authors that define your taste.</p>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
        <form action="{{ route('profiles.update') }}" method="POST" enctype="multipart/form-data" class="grid gap-10">
            @csrf
            @method('PUT')

            <div class="grid gap-6 border border-ink-950/10 bg-white/35 p-6 sm:grid-cols-2 sm:p-8">
                <div class="sm:col-span-2"><p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Public identity</p><h2 class="mt-2 font-serif text-3xl font-bold text-ink-950">Reader information</h2></div>
                <div class="grid gap-5 border-b border-ink-950/10 pb-6 sm:col-span-2 sm:grid-cols-[112px_minmax(0,1fr)] sm:items-center">
                    <div class="grid size-28 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-3xl font-bold text-brand-cream">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="Current profile photo" class="size-full object-cover">
                        @else
                            {{ Str::upper(Str::substr($user->name, 0, 2)) }}
                        @endif
                    </div>
                    <div>
                        <label for="avatar" class="text-sm font-bold text-ink-950">Profile photo</label>
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full border border-ink-950/20 bg-brand-cream/55 p-3 text-sm file:mr-4 file:border-0 file:bg-ink-950 file:px-4 file:py-2 file:font-bold file:text-brand-cream">
                        <p class="mt-2 text-xs leading-5 text-ink-950/50">JPG, PNG, or WebP. Maximum file size: 2 MB.</p>
                        @error('avatar') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                        @if ($user->avatar_path)
                            <label class="mt-3 flex items-center gap-2 text-sm text-ink-950/65"><input name="remove_avatar" type="checkbox" value="1" class="size-4 accent-brand-coral"> Remove the current photo</label>
                        @endif
                    </div>
                </div>
                <div>
                    <label for="name" class="text-sm font-bold text-ink-950">Display name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" autocomplete="name" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/55 px-4 py-3 outline-none focus:border-brand-coral">
                    @error('name') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="username" class="text-sm font-bold text-ink-950">Username</label>
                    <div class="mt-2 flex border border-ink-950/20 bg-brand-cream/55 focus-within:border-brand-coral"><span class="grid place-items-center border-r border-ink-950/10 px-3 font-semibold text-ink-950/45">@</span><input id="username" name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="50" pattern="[a-z0-9_]+" autocomplete="username" class="min-w-0 flex-1 bg-transparent px-4 py-3 outline-none"></div>
                    @error('username') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="location" class="text-sm font-bold text-ink-950">Location <span class="font-normal text-ink-950/45">(optional)</span></label>
                    <input id="location" name="location" value="{{ old('location', $user->location) }}" maxlength="100" autocomplete="address-level2" placeholder="Makassar, Indonesia" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/55 px-4 py-3 outline-none focus:border-brand-coral">
                    @error('location') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="bio" class="text-sm font-bold text-ink-950">Bio <span class="font-normal text-ink-950/45">(maximum 500 characters)</span></label>
                    <textarea id="bio" name="bio" rows="5" maxlength="500" placeholder="Tell other readers about your literary interests..." class="mt-2 w-full resize-y border border-ink-950/20 bg-brand-cream/55 px-4 py-3 leading-7 outline-none focus:border-brand-coral">{{ old('bio', $user->bio) }}</textarea>
                    @error('bio') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <fieldset class="border border-ink-950/10 bg-white/35 p-6 sm:p-8">
                    <legend class="px-2 font-serif text-2xl font-bold text-ink-950">Favorite literature</legend>
                    <p class="mb-5 text-sm leading-6 text-ink-950/55">Select up to four works. Their order here becomes their order on your profile.</p>
                    <div class="grid gap-4">
                        @foreach (range(0, 3) as $slot)
                            <div><label for="favorite-literature-{{ $slot }}" class="text-xs font-bold uppercase tracking-wider text-ink-950/50">Position {{ $slot + 1 }}</label><select id="favorite-literature-{{ $slot }}" name="favorite_literature_ids[]" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/55 px-4 py-3 outline-none focus:border-brand-coral"><option value="">No selection</option>@foreach ($literatures as $literature)<option value="{{ $literature->id }}" @selected((string) old('favorite_literature_ids.'.$slot, $favoriteLiteratureIds[$slot] ?? '') === (string) $literature->id)>{{ $literature->original_title ?? $literature->title }}</option>@endforeach</select></div>
                        @endforeach
                    </div>
                    @error('favorite_literature_ids') <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    @error('favorite_literature_ids.*') <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </fieldset>

                <fieldset class="border border-ink-950/10 bg-white/35 p-6 sm:p-8">
                    <legend class="px-2 font-serif text-2xl font-bold text-ink-950">Favorite authors</legend>
                    <p class="mb-5 text-sm leading-6 text-ink-950/55">Select up to four authors or creators from the integrated catalog.</p>
                    <div class="grid gap-4">
                        @foreach (range(0, 3) as $slot)
                            <div><label for="favorite-author-{{ $slot }}" class="text-xs font-bold uppercase tracking-wider text-ink-950/50">Position {{ $slot + 1 }}</label><select id="favorite-author-{{ $slot }}" name="favorite_author_ids[]" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/55 px-4 py-3 outline-none focus:border-brand-coral"><option value="">No selection</option>@foreach ($authors as $author)<option value="{{ $author->id }}" @selected((string) old('favorite_author_ids.'.$slot, $favoriteAuthorIds[$slot] ?? '') === (string) $author->id)>{{ $author->name }}</option>@endforeach</select></div>
                        @endforeach
                    </div>
                    @error('favorite_author_ids') <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    @error('favorite_author_ids.*') <p class="mt-3 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </fieldset>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-ink-950/10 pt-6 sm:flex-row sm:justify-end"><a href="{{ route('profiles.show', $user) }}" class="border border-ink-950/20 px-6 py-3 text-center font-bold text-ink-950 transition hover:border-brand-coral">Cancel</a><button class="bg-ink-950 px-7 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Save profile</button></div>
        </form>
    </section>
</x-app-shell>
