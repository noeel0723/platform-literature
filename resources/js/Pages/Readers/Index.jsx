import { Head, Link, useForm } from '@inertiajs/react';

import EmptyState from '../../Components/EmptyState';
import { Pagination } from '../../Components/ProfilePageUi';
import ReaderCard from '../../Components/ReaderCard';
import SiteContainer from '../../Components/SiteContainer';

export function AdminDirectory({ admins, routes }) {
    if (!admins?.data.length) return null;

    return (
        <section className="mt-8" aria-labelledby="readers-admin-heading">
            <h2 id="readers-admin-heading" className="border-b border-ink-950/15 pb-3 text-center text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">Literahaven admins</h2>
            <div className="flex flex-wrap justify-center gap-x-8 gap-y-6 pt-6">
                {admins.data.map((admin) => <div key={admin.username} className="w-48 max-w-full"><ReaderCard reader={admin} routes={routes} /></div>)}
            </div>
            <Pagination paginator={admins} label="Admin directory pagination" />
        </section>
    );
}

export default function Readers({ readers, admins, query, routes }) {
    const search = useForm({ q: query });
    const submitSearch = (event) => {
        event.preventDefault();
        if (!search.processing) search.get(routes.index, { preserveScroll: true, preserveState: true });
    };

    return (
        <>
            <Head title="Readers" />
            <SiteContainer as="section" className="py-8 sm:py-10 lg:py-12" aria-labelledby="readers-heading">
                <h1 id="readers-heading" className="text-center font-serif text-2xl font-semibold tracking-tight text-ink-950 sm:text-3xl">Discover readers.</h1>
                <p className="mt-2 text-center text-sm text-ink-950/60">Find your next read through people who love stories.</p>
                <AdminDirectory admins={admins} routes={routes} />

                <div className="mt-8 flex flex-wrap items-center justify-between gap-4 border-b border-ink-950/15 pb-3">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h2 className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">{query ? 'Reader search' : 'Readers to discover'}</h2>
                        <span className="text-xs text-ink-950/60">{readers.total} {readers.total === 1 ? 'reader' : 'readers'}</span>
                    </div>
                    <form onSubmit={submitSearch} role="search" aria-label="Find readers" className="w-full sm:w-64" aria-busy={search.processing}>
                        <label htmlFor="reader-search" className="sr-only">Find a reader by name or username</label>
                        <div className="flex min-w-0 overflow-hidden rounded-md border border-ink-950/20 bg-white/55 focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/15">
                            <input id="reader-search" name="q" type="search" value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} placeholder="Find a reader…" maxLength={100} className="h-9 min-w-0 flex-1 bg-transparent px-3 text-sm outline-none placeholder:text-ink-950/45" />
                            <button type="submit" disabled={search.processing} aria-label="Search readers" className="grid w-10 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white disabled:opacity-50"><svg aria-hidden="true" className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></svg></button>
                        </div>
                        {search.errors.q && <p role="alert" className="mt-1 text-xs text-red-700">{search.errors.q}</p>}
                    </form>
                </div>
                {query && <p className="mt-3 text-xs text-ink-950/65">Results for <span className="font-semibold [overflow-wrap:anywhere]">“{query}”</span><Link href={routes.index} preserveScroll className="ml-3 font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Clear search</Link></p>}

                {readers.data.length > 0 ? (
                    <div className="grid grid-cols-2 gap-x-5 gap-y-9 pt-6 sm:grid-cols-3 sm:gap-x-7 lg:grid-cols-5 lg:gap-x-6">
                        {readers.data.map((reader) => <ReaderCard key={reader.username} reader={reader} routes={routes} />)}
                    </div>
                ) : <EmptyState className="mt-6" title={query ? 'No readers found.' : 'No readers to discover yet.'} description={query ? 'Try another name or username.' : 'Reader profiles will appear here as the community grows.'} action={query ? { href: routes.index, label: 'Browse readers' } : null} />}
                <Pagination paginator={readers} label="Readers pagination" />
            </SiteContainer>
        </>
    );
}
