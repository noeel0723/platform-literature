import { Head, Link } from '@inertiajs/react';

import SiteContainer from '../../Components/SiteContainer';

const scopeLabels = {
    all: 'All',
    literature: 'Literature',
    authors: 'Authors',
    readers: 'Readers',
};

function searchUrl(baseUrl, query, scope) {
    const parameters = new URLSearchParams();

    if (query) {
        parameters.set('q', query);
    }

    if (scope !== 'all') {
        parameters.set('scope', scope);
    }

    const queryString = parameters.toString();

    return queryString ? `${baseUrl}?${queryString}` : baseUrl;
}

function RoundPortrait({ imageUrl, initials, name }) {
    return (
        <span className="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 text-sm font-bold text-brand-cream">
            {imageUrl ? <img src={imageUrl} alt={`Portrait of ${name}`} className="size-full object-cover" loading="lazy" /> : initials}
        </span>
    );
}

function LiteratureResults({ items }) {
    if (items.length === 0) return null;

    return (
        <section id="literature-results" className="scroll-mt-24" aria-labelledby="literature-results-heading">
            <h2 id="literature-results-heading" className="border-b border-ink-950/15 py-2.5 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-ink-950/55">Literature</h2>
            {items.map((literature) => (
                <article key={literature.id} className="grid grid-cols-[52px_minmax(0,1fr)] gap-3 border-b border-ink-950/10 py-3.5 sm:grid-cols-[64px_minmax(0,1fr)] sm:gap-4 sm:py-4" data-search-literature>
                    <Link href={literature.url} className="aspect-[2/3] self-start overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_2px_7px_rgba(47,58,85,0.06)]">
                        {literature.cover_url ? <img src={literature.cover_url} alt={`Cover of ${literature.title}`} className="size-full object-cover" loading="lazy" /> : <span className="grid size-full place-items-center p-1 text-center text-xs font-bold text-ink-950">{literature.initials}</span>}
                    </Link>
                    <div className="min-w-0 self-start">
                        <h3 className="text-base font-bold leading-5 text-ink-950 sm:text-lg sm:leading-6">
                            <Link href={literature.url} className="hover:text-brand-coral">{literature.title}</Link>{' '}
                            {literature.year && <span className="whitespace-nowrap text-xs font-normal text-ink-950/45">{literature.year}</span>}
                        </h3>
                        <p className="mt-0.5 truncate text-xs text-ink-950/55">By <span className="font-semibold text-ink-950/70">{literature.author}</span></p>
                        {literature.alternative_title && <p className="mt-1 line-clamp-1 text-xs text-ink-950/45"><span className="font-semibold">Alternative title:</span> {literature.alternative_title}</p>}
                        <p className="mt-1 line-clamp-2 text-xs leading-5 text-ink-950/55">{literature.synopsis}</p>
                        <span className="mt-1.5 inline-block text-[0.62rem] font-bold uppercase tracking-[0.14em] text-brand-coral">{literature.type_label}</span>
                    </div>
                </article>
            ))}
        </section>
    );
}

function AuthorResults({ items }) {
    if (items.length === 0) return null;

    return (
        <section id="author-results" className="scroll-mt-24 pt-5" aria-labelledby="author-results-heading">
            <h2 id="author-results-heading" className="border-b border-ink-950/15 pb-2.5 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-ink-950/55">Authors</h2>
            <div>
                {items.map((author) => (
                    <article key={author.id} className="flex min-w-0 gap-3 border-b border-ink-950/10 py-3" data-search-author>
                        <RoundPortrait imageUrl={author.image_url} initials={author.initials} name={author.name} />
                        <div className="min-w-0">
                            <h3 className="truncate text-base font-bold leading-5 text-ink-950"><Link href={author.url} className="hover:text-brand-coral">{author.name}</Link></h3>
                            <p className="mt-0.5 text-[0.68rem] text-ink-950/45">{author.literatures_count} catalog {author.literatures_count === 1 ? 'title' : 'titles'}</p>
                            <p className="mt-1 line-clamp-1 text-xs text-ink-950/55">{author.literature_titles.join(', ') || 'No linked literature yet.'}</p>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

function ReaderResults({ items }) {
    if (items.length === 0) return null;

    return (
        <section id="member-results" className="scroll-mt-24 pt-5" aria-labelledby="member-results-heading">
            <h2 id="member-results-heading" className="border-b border-ink-950/15 pb-2.5 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-ink-950/55">Readers</h2>
            <div>
                {items.map((reader) => (
                    <Link key={reader.id} href={reader.url} className="flex min-w-0 items-center gap-3 border-b border-ink-950/10 py-3 transition hover:bg-brand-yogurt/35" data-search-member>
                        <RoundPortrait imageUrl={reader.avatar_url} initials={reader.initials} name={reader.name} />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-base font-bold leading-5 text-ink-950">{reader.name}</span>
                            <span className="mt-0.5 block truncate text-xs text-ink-950/50">@{reader.username}</span>
                            <span className="mt-1 block text-[0.68rem] text-ink-950/45">{reader.completed_count} completed {'\u00b7'} {reader.reviews_count} reviews</span>
                        </span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

export default function SearchIndex({ query, scope, literatures, authors, readers, counts, routes }) {
    const visibleItems = scope === 'literature'
        ? literatures
        : scope === 'authors'
            ? authors
            : scope === 'readers'
                ? readers
                : [...literatures, ...authors, ...readers];
    const showLiterature = ['all', 'literature'].includes(scope);
    const showAuthors = ['all', 'authors'].includes(scope);
    const showReaders = ['all', 'readers'].includes(scope);

    return (
        <>
            <Head title={query ? `Search results for ${query}` : 'Search'} />

            <SiteContainer as="section" className="grid gap-7 py-7 lg:grid-cols-[minmax(0,1fr)_220px] lg:gap-8 lg:py-9">
                <main className="min-w-0" aria-labelledby="search-results-heading">
                    <header className="border-b border-ink-950/20 pb-2.5">
                        <p className="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Literahaven search</p>
                        <h1 id="search-results-heading" className="mt-1 text-2xl font-bold leading-tight text-ink-950">{query ? `Showing matches for "${query}"` : 'Search across Literahaven'}</h1>
                    </header>

                    {!query ? (
                        <div className="border-b border-ink-950/10 py-10 text-center">
                            <p className="text-lg font-bold text-ink-950">What are you looking for?</p>
                            <p className="mt-1.5 text-sm text-ink-950/55">Search for a title, author name, display name, or username.</p>
                        </div>
                    ) : visibleItems.length === 0 ? (
                        <div className="border-b border-ink-950/10 py-10 text-center">
                            <p className="text-lg font-bold text-ink-950">No results found.</p>
                            <p className="mt-1.5 text-sm text-ink-950/55">Check the spelling or try a shorter keyword.</p>
                        </div>
                    ) : (
                        <>
                            {showLiterature && <LiteratureResults items={literatures} />}
                            {showAuthors && <AuthorResults items={authors} />}
                            {showReaders && <ReaderResults items={readers} />}
                        </>
                    )}
                </main>

                <aside className="min-w-0 border-t border-ink-950/15 pt-5 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                    <div className="lg:sticky lg:top-24">
                        <h2 className="border-b border-ink-950/20 pb-2.5 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-ink-950/65">Search results for</h2>
                        <p className="mt-2.5 break-words text-base font-bold text-ink-950">{query ? `"${query}"` : 'Everything'}</p>
                        <nav className="mt-3 grid grid-cols-2 border-t border-ink-950/10 text-xs sm:grid-cols-4 lg:grid-cols-1" aria-label="Search result categories">
                            {Object.entries(scopeLabels).map(([value, label]) => (
                                <Link
                                    key={value}
                                    href={searchUrl(routes.search, query, value)}
                                    aria-current={scope === value ? 'page' : undefined}
                                    preserveScroll
                                    className={`flex min-h-9 items-center justify-between gap-2 border-b border-ink-950/10 px-3 py-2 transition ${scope === value ? 'bg-ink-950 font-bold text-brand-cream' : 'text-ink-950/65 hover:bg-brand-yogurt/45'}`}
                                >
                                    <span>{label}</span><span className={scope === value ? 'text-brand-cream/75' : 'text-ink-950/40'}>{counts[value] ?? 0}</span>
                                </Link>
                            ))}
                        </nav>
                    </div>
                </aside>
            </SiteContainer>
        </>
    );
}
