import { Head, Link } from '@inertiajs/react';

import { Pagination } from '../../Components/ProfilePageUi';
import ReaderRows from '../../Components/ReaderRows';
import ReaderSearch from '../../Components/ReaderSearch';
import SiteContainer from '../../Components/SiteContainer';

export default function AllReaders({ readers, query, routes }) {
    return (
        <>
            <Head title="All readers" />
            <SiteContainer as="section" className="py-8 sm:py-10 lg:py-12" aria-labelledby="all-readers-heading">
                <div className="mx-auto max-w-5xl">
                    <Link href={routes.overview} className="text-xs font-semibold text-ink-950/60 hover:text-brand-coral">← Readers overview</Link>
                    <div className="mt-5 flex flex-wrap items-center justify-between gap-4 border-b border-ink-950/15 pb-3">
                        <div><h1 id="all-readers-heading" className="text-[11px] font-bold uppercase tracking-[0.16em] text-brand-coral">{query ? 'Reader search' : 'All readers'}</h1><p className="mt-1 text-xs text-ink-950/60">{readers.total} {readers.total === 1 ? 'reader' : 'readers'} · Most followed first</p></div>
                        <ReaderSearch query={query} action={routes.index} />
                    </div>
                    {query && <p className="my-3 text-xs text-ink-950/65">Results for <span className="font-semibold [overflow-wrap:anywhere]">“{query}”</span><Link href={routes.index} className="ml-3 font-semibold underline decoration-brand-coral underline-offset-4">Clear search</Link></p>}
                    <ReaderRows readers={readers.data} routes={routes} />
                    <Pagination paginator={readers} label="Readers pagination" />
                </div>
            </SiteContainer>
        </>
    );
}
