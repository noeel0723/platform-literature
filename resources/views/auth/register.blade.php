<x-app-shell title="Create account">
    <section class="catalog-grid min-h-[70vh] border-b border-ink-950/10 px-5 py-14 sm:px-8 lg:py-20">
        <div class="mx-auto max-w-md border border-ink-950/15 bg-white/45 p-7 shadow-[0_12px_32px_rgba(47,58,85,0.08)] sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Join Literahaven</p>
            <h1 class="mt-3 font-serif text-4xl font-bold text-ink-950">Create account</h1>
            <p class="mt-3 leading-7 text-ink-950/65">One account keeps your Readlist, reading progress, and activity history together.</p>

            <form action="{{ route('register') }}" method="POST" class="mt-8 grid gap-5">
                @csrf
                <div>
                    <label for="name" class="text-sm font-bold text-ink-950">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('name') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="username" class="text-sm font-bold text-ink-950">Username</label>
                    <div class="mt-2 flex border border-ink-950/20 bg-brand-cream/40 focus-within:border-brand-coral">
                        <span class="grid place-items-center border-r border-ink-950/10 px-3 font-semibold text-ink-950/45">@</span>
                        <input id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="50" pattern="[a-z0-9_]+" autocomplete="username" placeholder="imanuel_reader" class="min-w-0 flex-1 bg-transparent px-4 py-3 outline-none">
                    </div>
                    <p class="mt-2 text-xs text-ink-950/50">Use lowercase letters, numbers, and underscores.</p>
                    @error('username') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="text-sm font-bold text-ink-950">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('email') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="text-sm font-bold text-ink-950">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('password') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-bold text-ink-950">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                </div>
                <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Create account</button>
            </form>

            <p class="mt-6 text-sm text-ink-950/65">Already have an account? <a href="{{ route('login') }}" class="font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4">Log in</a></p>
        </div>
    </section>
</x-app-shell>
