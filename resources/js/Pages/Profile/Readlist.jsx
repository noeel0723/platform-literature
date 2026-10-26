import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import useFeedbackAction from '../../Support/useFeedbackAction';
import EmptyState from '../../Components/EmptyState';
import { TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfileContentContainer } from '../../Components/ProfilePageUi';

function RecommendedReads({ suggestions, catalogUrl }) {
    const { processing, visit } = useFeedbackAction();

    const add = (literature) => {
        if (processing) return;
        visit('put', literature.update_url, { status: 'want_to_read', return_to: 'readlist' });
    };

    return (
        <section className="sticky top-24" aria-labelledby="add-to-readlist-heading" data-readlist-add-panel>
            <div className="border-t border-ink-950/20 pt-4">
                <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Your next read</p>
                <h2 id="add-to-readlist-heading" className="mt-0.5 text-base font-bold text-ink-950">Recommended for you</h2>
                <p className="mt-2 text-xs leading-5 text-ink-950/60">Find your next read and save it with +.</p>

                <div className="mt-2 max-h-[22rem] overflow-y-auto pr-1" data-readlist-suggestions>
                    {suggestions.map((literature) => (
                        <article key={literature.id} className="flex items-center gap-2 border-b border-ink-950/10 py-2 last:border-b-0" data-readlist-suggestion data-suggestion-literature-id={literature.id}>
                            <Link href={literature.url} className="block shrink-0"><LiteratureCover literature={literature} alt={false} className="h-12 w-8 rounded-sm" /></Link>
                            <div className="min-w-0 flex-1"><Link href={literature.url} title={literature.title} className="line-clamp-2 text-xs font-bold leading-5 text-ink-950 [overflow-wrap:anywhere] hover:text-brand-coral">{literature.title}</Link><p title={literature.author} className="mt-0.5 truncate text-xs leading-5 text-ink-950/75">{literature.author}</p><p className="mt-0.5 text-[11px] leading-4 text-ink-950/55">{literature.reason}</p></div>
                            <button type="button" disabled={processing} onClick={() => add(literature)} className="grid size-7 shrink-0 place-items-center rounded-full border border-ink-950/15 text-base text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-white disabled:opacity-40" aria-label={`Add ${literature.title} to Readlist`}>+</button>
                        </article>
                    ))}
                </div>
                {suggestions.length === 0 && <EmptyState compact data-readlist-no-results title="No recommendations available yet." description="Explore the catalog to find your next read." />}
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
                        <EmptyState className="mt-5" title="This Readlist is empty." description={isOwner ? 'Save a recommendation or explore the catalog to find your next read.' : 'This reader has not saved any literature for later.'} action={isOwner ? { href: routes.catalog, label: 'Explore literature' } : null} />
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
                    {isOwner ? <RecommendedReads suggestions={suggestions} catalogUrl={routes.catalog} /> : (
                        <section className="border-t border-ink-950/15 pt-4" aria-labelledby="public-readlist-help"><h2 id="public-readlist-help" className="text-xs font-bold uppercase tracking-[0.14em] text-ink-950">About Readlists</h2><p className="mt-2 text-xs leading-5 text-ink-950/55">A Readlist is a personal shelf for literature a reader wants to explore later.</p>{!viewerAuthenticated && <Link href={routes.login} className="mt-4 inline-block bg-ink-950 px-3 py-2 text-xs font-bold text-brand-cream">Log in to build yours</Link>}</section>
                    )}
                </aside>
            </ProfileContentContainer>
        </>
    );
}
