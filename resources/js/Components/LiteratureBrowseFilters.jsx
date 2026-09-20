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
        <form onSubmit={submit} className="flex h-9 min-w-0 border border-ink-950/15 bg-white/55 focus-within:border-brand-coral focus-within:ring-2 focus-within:ring-brand-coral/20" role="search">
            <label htmlFor="global-literature-search" className="sr-only">Search local literature</label>
            <input
                id="global-literature-search"
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Find literature"
                className="min-w-0 w-44 bg-transparent px-3 text-sm text-ink-950 outline-none placeholder:text-ink-950/45 sm:w-52"
            />
            <button type="submit" className="shrink-0 border-l border-ink-950/10 px-3 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-brand-blue transition hover:bg-brand-coral hover:text-white">
                Search
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
