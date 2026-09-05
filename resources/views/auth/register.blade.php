<x-app-shell title="Daftar">
    <section class="catalog-grid min-h-[70vh] border-b border-ink-950/10 px-5 py-14 sm:px-8 lg:py-20">
        <div class="mx-auto max-w-md border border-ink-950/15 bg-white/45 p-7 shadow-xl sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 2</p>
            <h1 class="mt-3 font-serif text-4xl font-bold text-ink-950">Buat akun</h1>
            <p class="mt-3 leading-7 text-ink-950/65">Satu akun menyimpan Readlist, progres, dan riwayat aktivitas bacaanmu.</p>

            <form action="{{ route('register') }}" method="POST" class="mt-8 grid gap-5">
                @csrf
                <div>
                    <label for="name" class="text-sm font-bold text-ink-950">Nama</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('name') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="text-sm font-bold text-ink-950">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('email') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="text-sm font-bold text-ink-950">Kata sandi</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                    @error('password') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-bold text-ink-950">Ulangi kata sandi</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full border border-ink-950/20 bg-brand-cream/40 px-4 py-3 outline-none transition focus:border-brand-coral">
                </div>
                <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Buat akun</button>
            </form>

            <p class="mt-6 text-sm text-ink-950/65">Sudah memiliki akun? <a href="{{ route('login') }}" class="font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4">Masuk di sini</a></p>
        </div>
    </section>
</x-app-shell>
