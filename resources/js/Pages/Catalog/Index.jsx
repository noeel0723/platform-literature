import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import LiteratureCard from '../../Components/LiteratureCard';

function SearchIcon() {
    return (
        <svg aria-hidden="true" className="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </svg>
    );
}

function catalogUrl(baseUrl, query, type) {
    const url = new URL(baseUrl, window.location.origin);

    if (query) {
        url.searchParams.set('q', query);
    }

    if (type) {
        url.searchParams.set('type', type);
    }

    return `${url.pathname}${url.search}`;
}

export default function CatalogIndex({
    literatures,
    query,
    selectedType,
    canExpand,
    sourceWarning,
    types,
    routes,
}) {
    const [search, setSearch] = useState(query);

    useEffect(() => {
        setSearch(query);
    }, [query]);

    const submitSearch = (event) => {
        event.preventDefault();

        router.get(routes.catalog, {
            q: search.trim() || undefined,
            type: selectedType || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const resultTitle = query
        ? `Results for “${query}”`
        : selectedType
            ? `Latest ${types[selectedType] ?? 'literature'}`
            : 'Search the catalog';

    return (
        <>
            <Head title="Catalog" />

            <section className="catalog-grid border-b border-ink-950/10">
                <div className="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-6 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
                    <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                        <span className="shrink-0 text-xs font-bold uppercase tracking-[0.2em] text-ink-950/45">
                            Browse by format
                        </span>
                        <nav className="flex gap-2 overflow-x-auto pb-1" aria-label="Literature format filters">
                            <Link
                                href={catalogUrl(routes.catalog, query, '')}
                                preserveScroll
                                className={`whitespace-nowrap border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition ${selectedType === '' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/20 text-ink-950/70 hover:border-ink-950 hover:bg-brand-sky/25'}`}
                            >
                                All
                            </Link>
                            {Object.entries(types).map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={catalogUrl(routes.catalog, query, value)}
                                    preserveScroll
                                    className={`whitespace-nowrap border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition ${selectedType === value ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/20 text-ink-950/70 hover:border-ink-950 hover:bg-brand-sky/25'}`}
                                >
                                    {label}
                                </Link>
                            ))}
                        </nav>
                    </div>

                    <form onSubmit={submitSearch} role="search" className="flex h-10 w-full overflow-hidden rounded-full border border-ink-950/20 bg-white/50 sm:w-72 lg:w-80">
                        <label htmlFor="catalog-page-search" className="sr-only">Find literature</label>
                        <input
                            id="catalog-page-search"
                            name="q"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Find literature..."
                            className="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/40 focus:bg-white/50"
                        />
                        <button type="submit" className="grid size-10 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream" aria-label="Search catalog">
                            <SearchIcon />
                        </button>
                    </form>
                </div>
            </section>

            <section className="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16" aria-labelledby="catalog-results-title">
                <div className="mb-7 flex flex-col gap-3 border-b border-ink-950/15 pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Latest matches</p>
                        <h1 id="catalog-results-title" className="mt-1 font-serif text-3xl font-bold text-ink-950">
                            {resultTitle}
                        </h1>
                    </div>
                    {(query || selectedType) && (
                        <div className="flex items-center gap-5">
                            <p className="text-sm text-ink-950/50">Showing {literatures.length} of the best matches</p>
                            {canExpand && (
                                <Link href={routes.latest} className="text-xs font-bold uppercase tracking-[0.16em] text-ink-950 transition hover:text-brand-coral">
                                    More
                                </Link>
                            )}
                        </div>
                    )}
                </div>

                {sourceWarning && (
                    <div role="status" className="mb-8 border border-brand-coral/35 bg-white/35 px-4 py-3 text-sm leading-6 text-ink-950/70">
                        <span className="font-bold text-ink-950">The local catalog remains available.</span>{' '}
                        {sourceWarning}
                    </div>
                )}

                {!query && !selectedType ? (
                    <div className="border border-dashed border-ink-950/20 bg-white/25 px-6 py-14 text-center">
                        <p className="font-serif text-2xl font-bold text-ink-950">What would you like to read next?</p>
                        <p className="mx-auto mt-2 max-w-xl leading-7 text-ink-950/60">
                            Enter a title, author, or genre above. The strongest matching works will be shown first.
                        </p>
                    </div>
                ) : literatures.length === 0 ? (
                    <div className="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                        <p className="font-serif text-2xl font-bold text-ink-950">No matching titles found.</p>
                        <p className="mt-2 text-ink-950/60">Try another keyword or choose a different format.</p>
                        <Link href={routes.catalog} className="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">
                            Clear search
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-x-4 gap-y-9 sm:gap-x-6 lg:grid-cols-4 lg:gap-x-7" data-catalog-results>
                        {literatures.map((literature) => (
                            <LiteratureCard key={literature.slug} literature={literature} />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
