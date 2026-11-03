import { useForm } from '@inertiajs/react';

export default function ReaderSearch({ query, action }) {
    const search = useForm({ q: query });
    const submit = (event) => {
        event.preventDefault();
        if (!search.processing) search.get(action, { preserveScroll: true, preserveState: true });
    };

    return (
        <form onSubmit={submit} role="search" aria-label="Find readers" className="w-full sm:w-64" aria-busy={search.processing}>
            <label htmlFor="reader-search" className="sr-only">Find a reader by name or username</label>
            <div className="flex min-w-0 overflow-hidden rounded-md border border-ink-950/20 bg-white/55 focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/15">
                <input id="reader-search" name="q" type="search" value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} placeholder="Find a reader…" maxLength={100} className="h-9 min-w-0 flex-1 bg-transparent px-3 text-sm outline-none placeholder:text-ink-950/45" />
                <button type="submit" disabled={search.processing} aria-label="Search readers" className="grid w-10 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white disabled:opacity-50"><svg aria-hidden="true" className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></svg></button>
            </div>
            {search.errors.q && <p role="alert" className="mt-1 text-xs text-red-700">{search.errors.q}</p>}
        </form>
    );
}
