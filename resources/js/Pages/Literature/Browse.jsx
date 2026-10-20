import { Head, Link } from '@inertiajs/react';

import CatalogPagination from '../../Components/CatalogPagination';
import LiteratureBrowseFilters, { LiteratureBrowseSearch } from '../../Components/LiteratureBrowseFilters';
import LiteratureCard from '../../Components/LiteratureCard';
import SiteContainer from '../../Components/SiteContainer';

export default function LiteratureBrowse({ literatures, filters, options, routes }) {
    return (
        <>
            <Head title="Browse Literature" />

            <section className="border-b border-ink-950/10 bg-brand-yogurt/20" aria-label="Literature search and filters">
                <SiteContainer className="flex flex-col gap-2 py-3.5 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                        <Link href={routes.index} className="shrink-0 text-xs font-bold uppercase tracking-[0.2em] text-ink-950/50 transition hover:text-brand-coral">Browse by</Link>
                        <LiteratureBrowseFilters browseUrl={routes.browse} filters={filters} options={options} includeSort compact />
                    </div>
                    <div className="flex w-full min-w-0 items-center gap-2 lg:ml-auto lg:w-auto">
                        <LiteratureBrowseSearch browseUrl={routes.browse} filters={filters} compact />
                    </div>
                </SiteContainer>
            </section>

            <SiteContainer as="section" className="py-6 lg:py-8" aria-labelledby="browse-literature-title">
                <h1 id="browse-literature-title" className="sr-only">Browse literature</h1>

                <p className="mb-5 border border-ink-950/10 bg-white/35 px-4 py-3 text-center text-sm text-ink-950/65">
                    There {literatures.total === 1 ? 'is' : 'are'} <strong className="text-ink-950">{literatures.total.toLocaleString()}</strong> {literatures.total === 1 ? 'literature' : 'literature works'}.
                </p>

                {literatures.data.length > 0 ? (
                    <>
                        <div className="grid grid-cols-2 gap-x-3 gap-y-6 min-[460px]:grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8" data-global-literature-grid>
                            {literatures.data.map((literature) => <LiteratureCard key={literature.id} literature={literature} compact />)}
                        </div>
                        <CatalogPagination pagination={literatures} label="Global literature pagination" />
                    </>
                ) : (
                    <div className="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                        <p className="font-serif text-2xl font-bold text-ink-950">No local literature matches these filters.</p>
                        <Link href={routes.browse} className="mt-5 inline-flex min-h-10 items-center bg-ink-950 px-5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral">
                            Clear filters
                        </Link>
                    </div>
                )}
            </SiteContainer>
        </>
    );
}
