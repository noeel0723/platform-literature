import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const compactSelect = 'h-9 min-w-28 border border-ink-950/15 bg-white/45 px-2.5 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-ink-950 outline-none transition hover:border-brand-blue focus:border-brand-coral focus:ring-2 focus:ring-brand-coral/20';

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

function BrowseSelect({ label, name, value = '', onChange, children, className = '', stretch = false }) {
    return (
        <label className={`relative shrink-0 ${className}`}>
            <span className="sr-only">{label}</span>
            <select
                name={name}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className={`${compactSelect} ${stretch ? 'w-full' : ''}`}
                aria-label={label}
            >
                {children}
            </select>
        </label>
    );
}

export function LiteratureBrowseSearch({ browseUrl, filters = {}, fullWidth = false }) {
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
        <form onSubmit={submit} className={`flex h-10 w-full min-w-0 overflow-hidden rounded-full border border-ink-950/20 bg-white/50 focus-within:border-brand-coral ${fullWidth ? '' : 'sm:w-72 lg:w-80'}`} role="search">
            <label htmlFor="global-literature-search" className="sr-only">Search local literature</label>
            <input
                id="global-literature-search"
                type="search"
                name="q"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Find literature..."
                className="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/40 focus:bg-white/50"
            />
            <button type="submit" className="grid size-10 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream" aria-label="Search literature">
                <svg aria-hidden="true" className="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-4-4" />
                </svg>
            </button>
        </form>
    );
}

export default function LiteratureBrowseFilters({ browseUrl, filters = {}, options, includeSort = false, includeSearch = false }) {
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

    return (
        <div className={includeSearch ? 'grid w-full min-w-0 grid-cols-2 gap-2 md:grid-cols-[minmax(110px,0.85fr)_minmax(140px,1fr)_minmax(200px,2fr)_minmax(140px,1fr)]' : 'flex max-w-full flex-wrap items-center gap-1.5'} data-literature-browse-filters>
            {includeSearch && (
                <div className="col-span-2 min-w-0 md:col-span-1 md:col-start-3 md:row-start-1">
                    <LiteratureBrowseSearch browseUrl={browseUrl} filters={filters} fullWidth />
                </div>
            )}
            <BrowseSelect label="Publication year" name="decade" value={filters.decade} onChange={(value) => navigate('decade', value)} stretch={includeSearch} className={includeSearch ? 'col-start-1 row-start-2 min-w-0 md:col-start-1 md:row-start-2' : ''}>
                <option value="">All years</option>
                {options.decades.map((decade) => <option key={decade.value} value={decade.value}>{decade.label}</option>)}
            </BrowseSelect>

            <BrowseSelect label="Ratings" name="rating" value={filters.rating} onChange={(value) => navigate('rating', value)} stretch={includeSearch} className={includeSearch ? 'col-start-2 row-start-2 min-w-0 md:col-start-2 md:row-start-2' : ''}>
                <option value="">All ratings</option>
                {options.ratings.map((rating) => <option key={rating.value} value={rating.value}>{rating.label}</option>)}
            </BrowseSelect>

            <BrowseSelect label="Genre" name="genre" value={filters.genre} onChange={(value) => navigate('genre', value)} stretch={includeSearch} className={includeSearch ? 'col-start-1 row-start-3 min-w-0 md:col-start-3 md:row-start-2' : ''}>
                <option value="">Any genre</option>
                {options.genres.map((genre) => <option key={genre.slug} value={genre.slug}>{genre.name}</option>)}
            </BrowseSelect>

            {includeSort && (
                <BrowseSelect label="Sort literature" name="sort" value={filters.sort ?? 'popularity'} onChange={(value) => navigate('sort', value)} stretch={includeSearch} className={includeSearch ? 'col-start-2 row-start-3 min-w-0 md:col-start-4 md:row-start-2' : ''}>
                    {Object.entries(sortsByGroup).map(([group, sorts]) => (
                        <optgroup key={group} label={group}>
                            {sorts.map((sort) => <option key={sort.value} value={sort.value}>{sort.label}</option>)}
                        </optgroup>
                    ))}
                </BrowseSelect>
            )}
        </div>
    );
}
