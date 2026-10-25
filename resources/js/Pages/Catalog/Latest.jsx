import { Head } from '@inertiajs/react';

import CatalogPagination from '../../Components/CatalogPagination';
import EmptyState from '../../Components/EmptyState';
import LiteratureCard from '../../Components/LiteratureCard';
import SiteContainer from '../../Components/SiteContainer';

export default function CatalogLatest({ literatures, query, routes }) {
    const pageTitle = query ? `All matches for ${query}` : 'Latest literature';

    return (
        <>
            <Head title={pageTitle} />

            <SiteContainer as="section" className="py-8 lg:py-10" aria-labelledby="latest-literature-title">
                <div className="mb-5 flex flex-col gap-2 border-b border-ink-950/15 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="latest-literature-title" className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">All latest matches</p>
                    <p className="text-sm text-ink-950/50">{literatures.total} matches · {literatures.per_page} per page</p>
                </div>

                {literatures.data.length === 0 ? (
                    <EmptyState
                        title="No matching literature found."
                        description="Browse the catalog to explore other titles."
                        action={{ href: routes.catalog, label: 'Back to catalog' }}
                    />
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 sm:gap-x-4 md:grid-cols-4 lg:grid-cols-5" data-latest-literature-grid>
                            {literatures.data.map((literature) => (
                                <LiteratureCard key={literature.slug} literature={literature} compact />
                            ))}
                        </div>

                        <CatalogPagination pagination={literatures} label="Latest literature pagination" />
                    </>
                )}
            </SiteContainer>
        </>
    );
}
