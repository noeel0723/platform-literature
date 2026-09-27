import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const compactSelect = 'h-9 bg-transparent px-3 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-ink-950 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-coral/50';

function destinationUrl(baseUrl, filters, key, value) {
    const url = new URL(baseUrl, window.location.origin);

    Object.entries(filters ?? {}).forEach(([filter, current]) => {
        if (current !== null && current !== '' && !(filter === 'sort' && current === 'popularity')) {
            url.searchParams.set(filter, current);
        }
    });

    if (value === '') {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }

    url.searchParams.delete('page');

    return `${url.pathname}${url.search}`;
}

function BrowseSelect({ label, name, value = '', onChange, children, stretch = false, compactWidth = '' }) {
    return (
        <label className="relative min-w-0 shrink-0 bg-white/55 transition-colors hover:bg-brand-yogurt/55 focus-within:bg-brand-yogurt/55">
            <span className="sr-only">{label}</span>
            <select
                name={name}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className={compactWidth
                    ? `h-8 ${compactWidth} bg-transparent px-2 text-[0.625rem] font-bold uppercase tracking-[0.08em] text-ink-950 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-coral/50`
                    : `${compactSelect} ${stretch ? 'w-full min-w-0' : 'min-w-28'}`}
                aria-label={label}
            >
                {children}
            </select>
        </label>
    );
}

export function LiteratureBrowseSearch({ browseUrl, filters = {}, fullWidth = false, compact = false }) {
    const [query, setQuery] = useState(filters.q ?? '');

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    const submit = (event) => {
        event.preventDefault();
        router.get(destinationUrl(browseUrl, filters, 'q', query.trim()), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <form onSubmit={submit} className={`flex w-full min-w-0 overflow-hidden border border-ink-950/20 bg-white/50 focus-within:border-brand-coral ${compact ? 'h-8 rounded-md sm:w-64 lg:w-72' : `h-10 rounded-full ${fullWidth ? '' : 'sm:w-72 lg:w-80'}`}`} role="search">
            <label htmlFor="global-literature-search" className="sr-only">Search local literature</label>
            <input
                id="global-literature-search"
                type="search"
                name="q"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Find literature..."
                className={`min-w-0 flex-1 bg-transparent text-ink-950 outline-none placeholder:text-ink-950/40 focus:bg-white/50 ${compact ? 'px-3 text-xs' : 'px-4 text-sm'}`}
            />
            <button type="submit" className={`grid shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream ${compact ? 'size-8' : 'size-10'}`} aria-label="Search literature">
                <svg aria-hidden="true" className="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-4-4" />
                </svg>
            </button>
        </form>
    );
}

export default function LiteratureBrowseFilters({ browseUrl, filters = {}, options, includeSort = false, includeSearch = false, compact = false }) {
    const navigate = (key, value) => {
        router.get(destinationUrl(browseUrl, filters, key, value), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };
    const sortsByGroup = (options.sorts ?? []).reduce((groups, option) => {
        groups[option.group] ??= [];
        groups[option.group].push(option);

        return groups;
    }, {});

    const controls = (
        <>
            <BrowseSelect label="Publication year" name="decade" value={filters.decade} onChange={(value) => navigate('decade', value)} stretch={includeSearch} compactWidth={compact ? 'w-24' : ''}>
                <option value="">All years</option>
                {options.decades.map((decade) => <option key={decade.value} value={decade.value}>{decade.label}</option>)}
            </BrowseSelect>

            <BrowseSelect label="Ratings" name="rating" value={filters.rating} onChange={(value) => navigate('rating', value)} stretch={includeSearch} compactWidth={compact ? 'w-28' : ''}>
                <option value="">All ratings</option>
                {options.ratings.map((rating) => <option key={rating.value} value={rating.value}>{rating.label}</option>)}
            </BrowseSelect>

            <BrowseSelect label="Genre" name="genre" value={filters.genre} onChange={(value) => navigate('genre', value)} stretch={includeSearch} compactWidth={compact ? 'w-32' : ''}>
                <option value="">Any genre</option>
                {options.genres.map((genre) => <option key={genre.slug} value={genre.slug}>{genre.name}</option>)}
            </BrowseSelect>

            {includeSort && (
                <BrowseSelect label="Sort literature" name="sort" value={filters.sort ?? 'popularity'} onChange={(value) => navigate('sort', value)} stretch={includeSearch}>
                    {Object.entries(sortsByGroup).map(([group, sorts]) => (
                        <optgroup key={group} label={group}>
                            {sorts.map((sort) => <option key={sort.value} value={sort.value}>{sort.label}</option>)}
                        </optgroup>
                    ))}
                </BrowseSelect>
            )}
        </>
    );

    if (includeSearch) {
        return (
            <div className="grid w-full min-w-0 grid-cols-2 gap-y-1.5 md:grid-cols-[minmax(110px,0.85fr)_minmax(140px,1fr)_minmax(200px,2fr)_minmax(140px,1fr)]" data-literature-browse-filters>
                <div className="col-span-2 min-w-0 md:col-span-1 md:col-start-3">
                    <LiteratureBrowseSearch browseUrl={browseUrl} filters={filters} fullWidth />
                </div>
                <div className="col-span-2 grid min-w-0 grid-cols-2 gap-px overflow-hidden rounded-md border border-ink-950/15 bg-ink-950/15 md:col-span-4 md:grid-cols-subgrid">
                    {controls}
                </div>
            </div>
        );
    }

    return (
        <div className="flex w-fit max-w-full flex-wrap gap-px rounded-md border border-ink-950/15 bg-ink-950/15" data-literature-browse-filters>
            {controls}
        </div>
    );
}
