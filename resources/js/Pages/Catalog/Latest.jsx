import { Head, Link } from '@inertiajs/react';

import LiteratureCard from '../../Components/LiteratureCard';

function PaginationLink({ href, relation, children }) {
    const sharedClasses = 'inline-flex min-h-10 items-center border px-5 text-sm font-semibold';

    if (!href) {
        return (
            <span
                className={`${sharedClasses} border-ink-950/10 text-ink-950/30`}
                aria-disabled="true"
            >
                {children}
            </span>
        );
    }

    return (
        <Link
            href={href}
            rel={relation}
            preserveScroll
            className={`${sharedClasses} border-ink-950/20 bg-white/45 text-ink-950 transition hover:border-brand-blue hover:bg-brand-blue hover:text-brand-cream`}
        >
            {children}
        </Link>
    );
}

export default function CatalogLatest({ literatures, query, selectedType, types, routes }) {
    const title = query
        ? `Results for “${query}”`
        : selectedType
            ? `Latest ${types[selectedType] ?? 'literature'}`
            : 'Latest literature';
    const pageTitle = query ? `All matches for ${query}` : 'Latest literature';

    return (
        <>
            <Head title={pageTitle} />

            <section className="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14" aria-labelledby="latest-literature-title">
                <div className="mb-6 flex flex-col gap-3 border-b border-ink-950/15 pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">All latest matches</p>
                        <h1 id="latest-literature-title" className="mt-1 text-2xl font-bold text-ink-950 sm:text-3xl">
                            {title}
                        </h1>
                    </div>
                    <p className="text-sm text-ink-950/50">{literatures.total} matches · {literatures.per_page} per page</p>
                </div>

                {literatures.data.length === 0 ? (
                    <div className="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                        <p className="text-xl font-bold text-ink-950">No matching literature is available.</p>
                        <Link href={routes.catalog} className="mt-5 inline-flex min-h-10 items-center bg-ink-950 px-5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral">
                            Back to catalog
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-3 sm:gap-x-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6" data-latest-literature-grid>
                            {literatures.data.map((literature) => (
                                <LiteratureCard key={literature.slug} literature={literature} compact />
                            ))}
                        </div>

                        <nav className="mt-9 flex items-center justify-between border-t border-ink-950/15 pt-5" aria-label="Latest literature pagination">
                            <PaginationLink href={literatures.prev_page_url} relation="prev">Previous</PaginationLink>
                            <span className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">
                                Page {literatures.current_page} of {literatures.last_page}
                            </span>
                            <PaginationLink href={literatures.next_page_url} relation="next">Next</PaginationLink>
                        </nav>
                    </>
                )}
            </section>
        </>
    );
}
