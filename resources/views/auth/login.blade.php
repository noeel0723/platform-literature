<x-app-shell title="Log in">
    <section class="catalog-grid min-h-[70vh] border-b border-ink-950/10 px-5 py-14 sm:px-8 lg:py-20">
        <div class="mx-auto max-w-md border border-ink-950/15 bg-white/45 p-7 shadow-[0_12px_32px_rgba(47,58,85,0.08)] sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader account</p>
            <h1 class="mt-3 font-serif text-4xl font-bold text-ink-950">Log in</h1>
            <p class="mt-3 leading-7 text-ink-950/65">Continue your Readlist, progress, and Personal Diary.</p>

            <form action="{{ route('login') }}" method="POST" class="mt-8 grid gap-5">
                @csrf
                <div>
                    <label for="email" class="text-sm font-bold text-ink-950">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('email') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="text-sm font-bold text-ink-950">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('password') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-3 text-sm text-ink-950/70">
                    <input name="remember" type="checkbox" value="1" class="size-4 accent-brand-coral">
                    Remember me
                </label>
                <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Log in</button>
            </form>

            <p class="mt-6 text-sm text-ink-950/65">New to Literahaven? <a href="{{ route('register') }}" class="font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4">Create an account</a></p>
        </div>
    </section>
</x-app-shell>
