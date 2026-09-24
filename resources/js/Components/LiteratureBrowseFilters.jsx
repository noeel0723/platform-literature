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

function BrowseSelect({ label, name, value = '', onChange, children }) {
    return (
        <label className="relative shrink-0">
            <span className="sr-only">{label}</span>
            <select
                name={name}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className={compactSelect}
                aria-label={label}
            >
                {children}
            </select>
        </label>
    );
}

export function LiteratureBrowseSearch({ browseUrl, filters = {} }) {
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
        <form onSubmit={submit} className="flex h-10 w-full min-w-0 overflow-hidden rounded-full border border-ink-950/20 bg-white/50 focus-within:border-brand-coral sm:w-72 lg:w-80" role="search">
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

export default function LiteratureBrowseFilters({ browseUrl, filters = {}, options, includeSort = false }) {
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
        <div className="flex max-w-full flex-wrap items-center gap-1.5" data-literature-browse-filters>
            <BrowseSelect label="Publication year" name="decade" value={filters.decade} onChange={(value) => navigate('decade', value)}>
                <option value="">All years</option>
                {options.decades.map((decade) => <option key={decade.value} value={decade.value}>{decade.label}</option>)}
            </BrowseSelect>

            <BrowseSelect label="Ratings" name="rating" value={filters.rating} onChange={(value) => navigate('rating', value)}>
                <option value="">All ratings</option>
                {options.ratings.map((rating) => <option key={rating.value} value={rating.value}>{rating.label}</option>)}
            </BrowseSelect>

            <BrowseSelect label="Genre" name="genre" value={filters.genre} onChange={(value) => navigate('genre', value)}>
                <option value="">Any genre</option>
                {options.genres.map((genre) => <option key={genre.slug} value={genre.slug}>{genre.name}</option>)}
            </BrowseSelect>

            {includeSort && (
                <BrowseSelect label="Sort literature" name="sort" value={filters.sort ?? 'popularity'} onChange={(value) => navigate('sort', value)}>
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
