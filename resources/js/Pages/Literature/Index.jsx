import { Head, Link } from '@inertiajs/react';

import LiteratureBrowseFilters from '../../Components/LiteratureBrowseFilters';
import LiteratureCard from '../../Components/LiteratureCard';
import SiteContainer from '../../Components/SiteContainer';

export default function LiteratureIndex({ popularLiteratures, options, routes }) {
    return (
        <>
            <Head title="Literature" />

            <section className="border-b border-ink-950/10 bg-brand-yogurt/20">
                <SiteContainer className="flex flex-col gap-3 py-5 sm:flex-row sm:items-center">
                    <span className="shrink-0 text-xs font-bold uppercase tracking-[0.2em] text-ink-950/50">Browse by</span>
                    <LiteratureBrowseFilters browseUrl={routes.browse} options={options} />
                </SiteContainer>
            </section>

            <SiteContainer as="section" className="py-8 lg:py-10" aria-labelledby="popular-literature-title">
                <div className="mb-5 flex items-end justify-between border-b border-ink-950/15 pb-3">
                    <h1 id="popular-literature-title" className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral sm:text-sm">
                        Popular literature this week
                    </h1>
                    <Link href={`${routes.browse}?sort=popularity`} className="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/65 transition hover:text-brand-coral">
                        More
                    </Link>
                </div>

                {popularLiteratures.length > 0 ? (
                    <div className="grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-4 sm:gap-x-4" data-popular-literature-grid>
                        {popularLiteratures.map((literature) => <LiteratureCard key={literature.id} literature={literature} />)}
                    </div>
                ) : (
                    <div className="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                        <p className="font-serif text-2xl font-bold text-ink-950">No local literature is available yet.</p>
                        <p className="mt-2 text-sm text-ink-950/60">Synced literature will appear here without making another provider request.</p>
                    </div>
                )}
            </SiteContainer>
        </>
    );
}
