import { Head, Link } from '@inertiajs/react';

import EmptyState from '../../Components/EmptyState';
import { Pagination } from '../../Components/ProfilePageUi';
import ReaderCard from '../../Components/ReaderCard';
import ReaderRows from '../../Components/ReaderRows';
import ReaderSearch from '../../Components/ReaderSearch';
import SiteContainer from '../../Components/SiteContainer';

export function AdminDirectory({ admins, routes }) {
    if (!admins?.data.length) return null;

    return (
        <section className="mt-8" aria-labelledby="readers-admin-heading">
            <h2 id="readers-admin-heading" className="border-b border-ink-950/15 pb-3 text-left text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">Literahaven admins</h2>
            <div className="flex flex-wrap justify-center gap-x-8 gap-y-6 pt-6">
                {admins.data.map((admin) => <div key={admin.username} className="w-48 max-w-full"><ReaderCard reader={admin} routes={routes} /></div>)}
            </div>
            <Pagination paginator={admins} label="Admin directory pagination" />
        </section>
    );
}

export default function Readers({ readers, admins, directory, query, routes }) {
    return (
        <>
            <Head title="Readers" />
            <SiteContainer as="section" className="py-8 sm:py-10 lg:py-12" aria-labelledby="readers-heading">
                <h1 id="readers-heading" className="text-center font-serif text-2xl font-semibold tracking-tight text-ink-950 sm:text-3xl">Discover readers.</h1>
                <p className="mt-2 text-center text-sm text-ink-950/60">Find your next read through people who love stories.</p>

                <div className="mt-8 flex flex-wrap items-center justify-between gap-4 border-b border-ink-950/15 pb-3">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h2 className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">{query ? 'Reader search' : 'Readers to discover'}</h2>
                        <span className="text-xs text-ink-950/60">{readers.total} {readers.total === 1 ? 'reader' : 'readers'}</span>
                    </div>
                    <ReaderSearch query={query} action={routes.index} />
                </div>
                {query && <p className="mt-3 text-xs text-ink-950/65">Results for <span className="font-semibold [overflow-wrap:anywhere]">“{query}”</span><Link href={routes.index} preserveScroll className="ml-3 font-semibold text-ink-950 underline decoration-brand-coral underline-offset-4">Clear search</Link></p>}

                {readers.data.length > 0 ? (
                    <div className="grid grid-cols-2 gap-x-5 gap-y-9 pt-6 sm:grid-cols-3 sm:gap-x-7 lg:grid-cols-5 lg:gap-x-6">
                        {readers.data.map((reader) => <ReaderCard key={reader.username} reader={reader} routes={routes} />)}
                    </div>
                ) : <EmptyState className="mt-6" title={query ? 'No readers found.' : 'No readers to discover yet.'} description={query ? 'Try another name or username.' : 'Reader profiles will appear here as the community grows.'} action={query ? { href: routes.index, label: 'Browse readers' } : null} />}
                {directory && <section className="mt-10" aria-labelledby="reader-directory-heading">
                    <div className="flex flex-wrap items-end justify-between gap-2 border-b border-ink-950/15 pb-3"><h2 id="reader-directory-heading" className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">Readers</h2><span className="text-xs text-ink-950/55">{directory.total} {directory.total === 1 ? 'reader' : 'readers'} · Most followed first</span></div>
                    <ReaderRows readers={directory.data} routes={routes} />
                    {directory.more_url && <div className="mt-5 text-center"><Link href={directory.more_url} className="inline-flex h-9 items-center rounded-md border border-ink-950/15 bg-white/50 px-6 text-xs font-semibold text-ink-950 hover:border-brand-coral hover:text-brand-coral focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral">View more</Link></div>}
                </section>}
                <AdminDirectory admins={admins} routes={routes} />
            </SiteContainer>
        </>
    );
}
