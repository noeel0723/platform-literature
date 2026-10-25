import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import EmptyState from '../../Components/EmptyState';
import { plural, TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

function QuickAdd({ suggestions, catalogUrl }) {
    const [query, setQuery] = useState('');
    const [processingId, setProcessingId] = useState(null);
    const visibleSuggestions = useMemo(() => {
        const term = query.trim().toLocaleLowerCase();
        if (!term) return suggestions;

        return suggestions.filter((item) => `${item.title} ${item.author}`.toLocaleLowerCase().includes(term));
    }, [query, suggestions]);

    const add = (literature) => {
        setProcessingId(literature.id);
        router.put(literature.update_url, { status: 'want_to_read', return_to: 'readlist' }, {
            preserveScroll: true,
            onFinish: () => setProcessingId(null),
        });
    };

    return (
        <section className="sticky top-24" aria-labelledby="add-to-readlist-heading" data-readlist-add-panel>
            <div className="border-t border-ink-950/20 pt-4">
                <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Quick add</p>
                <h2 id="add-to-readlist-heading" className="mt-0.5 text-base font-bold text-ink-950">Add literature</h2>
                <label htmlFor="readlist-suggestion-search" className="sr-only">Filter literature suggestions</label>
                <div className="mt-3 flex border border-ink-950/15 bg-white/45 focus-within:border-brand-coral">
                    <input id="readlist-suggestion-search" type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search literature..." autoComplete="off" className="min-w-0 flex-1 bg-transparent px-2.5 py-2 text-xs outline-none placeholder:text-ink-950/35" data-readlist-suggestion-search />
                    <span className="grid w-8 place-items-center text-xs text-ink-950/50" aria-hidden="true">⌕</span>
                </div>

                <div className="mt-2 max-h-[22rem] overflow-y-auto pr-1" data-readlist-suggestions>
                    {visibleSuggestions.map((literature) => (
                        <article key={literature.id} className="flex items-center gap-2 border-b border-ink-950/10 py-2 last:border-b-0" data-readlist-suggestion data-suggestion-literature-id={literature.id}>
                            <Link href={literature.url} className="block shrink-0"><LiteratureCover literature={literature} alt={false} className="h-12 w-8 rounded-sm" /></Link>
                            <div className="min-w-0 flex-1"><Link href={literature.url} title={literature.title} className="line-clamp-2 text-xs font-bold leading-5 text-ink-950 [overflow-wrap:anywhere] hover:text-brand-coral">{literature.title}</Link><p title={literature.author} className="mt-0.5 truncate text-xs leading-5 text-ink-950/75">{literature.author}</p></div>
                            <button type="button" disabled={processingId === literature.id} onClick={() => add(literature)} className="grid size-7 shrink-0 place-items-center rounded-full border border-ink-950/15 text-base text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-white disabled:opacity-40" aria-label={`Add ${literature.title} to Readlist`}>+</button>
                        </article>
                    ))}
                </div>
                {visibleSuggestions.length === 0 && <EmptyState compact data-readlist-no-results title={query.trim() ? 'No matching suggestions.' : 'No suggestions available.'} description={query.trim() ? 'Try another title or author.' : undefined} />}
                <Link href={catalogUrl} className="mt-3 block border border-ink-950/15 px-3 py-2 text-center text-xs font-bold text-ink-950 transition hover:border-brand-coral hover:bg-brand-yogurt/45">Browse the full catalog</Link>
            </div>
        </section>
    );
}

export default function ProfileReadlist({ profile, navigation, readlist, suggestions, isOwner, viewerAuthenticated, routes }) {
    return (
        <>
            <Head title={`${profile.name} Readlist`} />
            <ProfileSubNavigation navigation={navigation} />

            <ProfileContentContainer className="grid gap-7 py-7 lg:grid-cols-[minmax(0,1fr)_220px] lg:gap-6 lg:py-9">
                <div className="min-w-0" aria-labelledby="readlist-heading">
                    {readlist.data.length === 0 ? (
                        <EmptyState className="mt-5" title="This Readlist is empty." description={isOwner ? 'Use Quick add to save your next read.' : 'This reader has not saved any literature for later.'} action={isOwner ? { href: routes.catalog, label: 'Explore literature' } : null} />
                    ) : (
                        <>
                            <div className="mt-4 grid grid-cols-3 gap-x-2.5 gap-y-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-5">
                                {readlist.data.map((item) => (
                                    <article key={item.id} className="group min-w-0" data-readlist-item>
                                        <Link href={item.literature.url} className="block"><LiteratureCover literature={item.literature} className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)] transition duration-200 group-hover:-translate-y-0.5 group-hover:border-brand-coral" /><h2 title={item.literature.title} className="mt-1 line-clamp-2 text-xs font-bold leading-5 text-ink-950 [overflow-wrap:anywhere] transition group-hover:text-brand-coral sm:text-sm">{item.literature.title}</h2><p title={item.literature.author} className="mt-0.5 truncate text-xs leading-5 text-ink-950/75">{item.literature.author}</p></Link>
                                        <p className="mt-0.5 truncate text-[11px] leading-4 text-ink-950/65">Saved <TimeLabel value={item.saved_at} /></p>
                                    </article>
                                ))}
                            </div>
                            <Pagination paginator={readlist} label="Readlist pagination" />
                        </>
                    )}
                </div>

                <aside className="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-5">
                    {isOwner ? <QuickAdd suggestions={suggestions} catalogUrl={routes.catalog} /> : (
                        <section className="border-t border-ink-950/15 pt-4" aria-labelledby="public-readlist-help"><h2 id="public-readlist-help" className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950">About Readlists</h2><p className="mt-2 text-xs leading-5 text-ink-950/55">A Readlist is a personal shelf for literature a reader wants to explore later.</p>{!viewerAuthenticated && <Link href={routes.login} className="mt-4 inline-block bg-ink-950 px-3 py-2 text-xs font-bold text-brand-cream">Log in to build yours</Link>}</section>
                    )}
                </aside>
            </ProfileContentContainer>
        </>
    );
}
