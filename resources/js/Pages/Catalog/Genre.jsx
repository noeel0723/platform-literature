import { Head, Link, router } from '@inertiajs/react';

import CatalogPagination from '../../Components/CatalogPagination';
import LiteratureCard from '../../Components/LiteratureCard';
import SiteContainer from '../../Components/SiteContainer';

const queryUrl = (base, type, sort) => {
    const params = new URLSearchParams();

    if (type) params.set('type', type);
    if (sort && sort !== 'latest') params.set('sort', sort);

    const query = params.toString();

    return query ? `${base}?${query}` : base;
};

export default function CatalogGenre({ genre, literatures, selectedType, selectedSort, types, routes }) {
    const handleSort = (event) => {
        router.get(
            routes.genre,
            { type: selectedType || undefined, sort: event.target.value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={`${genre.name} literature`} />

            <SiteContainer as="section" className="py-8 lg:py-10" aria-labelledby="genre-title">
                <header className="border-b border-ink-950/15 pb-4">
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Literature</p>
                    <div className="mt-1 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h1 id="genre-title" className="text-2xl font-bold uppercase tracking-[0.04em] text-ink-950 sm:text-3xl">
                                {genre.name}
                            </h1>
                            <p className="mt-2 text-sm text-ink-950/65">
                                There are {literatures.total} {genre.name} literature.
                            </p>
                        </div>

                        <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-ink-950/55">
                            Sort
                            <select
                                value={selectedSort}
                                onChange={handleSort}
                                className="min-h-9 border border-ink-950/15 bg-white/50 px-3 text-sm font-semibold normal-case tracking-normal text-ink-950 focus:border-brand-coral focus:outline-none"
                            >
                                <option value="latest">Recently updated</option>
                                <option value="title">Title</option>
                                <option value="year">Publication year</option>
                            </select>
                        </label>
                    </div>
                </header>

                <nav className="mt-4 flex flex-wrap gap-2" aria-label={`${genre.name} literature type`}>
                    <Link
                        href={queryUrl(routes.genre, '', selectedSort)}
                        preserveScroll
                        className={`border px-3 py-2 text-xs font-bold uppercase tracking-[0.1em] transition ${selectedType === '' ? 'border-brand-blue bg-brand-blue text-brand-cream' : 'border-ink-950/15 bg-white/40 text-ink-950/65 hover:border-brand-coral hover:text-brand-coral'}`}
                    >
                        All
                    </Link>
                    {Object.entries(types).map(([type, label]) => (
                        <Link
                            key={type}
                            href={queryUrl(routes.genre, type, selectedSort)}
                            preserveScroll
                            className={`border px-3 py-2 text-xs font-bold uppercase tracking-[0.1em] transition ${selectedType === type ? 'border-brand-blue bg-brand-blue text-brand-cream' : 'border-ink-950/15 bg-white/40 text-ink-950/65 hover:border-brand-coral hover:text-brand-coral'}`}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>

                {literatures.data.length === 0 ? (
                    <div className="mt-6 border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                        <p className="text-xl font-bold text-ink-950">No {genre.name} literature matches this filter.</p>
                        <Link href={routes.catalog} className="mt-5 inline-flex min-h-10 items-center bg-ink-950 px-5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral">
                            Browse catalog
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="mt-6 grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 sm:gap-x-4 md:grid-cols-4 lg:grid-cols-5" data-genre-literature-grid>
                            {literatures.data.map((literature) => (
                                <LiteratureCard key={literature.slug} literature={literature} compact />
                            ))}
                        </div>

                        <CatalogPagination pagination={literatures} label={`${genre.name} literature pagination`} />
                    </>
                )}
            </SiteContainer>
        </>
    );
}
