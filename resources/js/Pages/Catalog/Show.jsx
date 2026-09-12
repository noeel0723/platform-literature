import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useState } from 'react';

import LiteratureCard from '../../Components/LiteratureCard';
import LiteratureDetailHero from '../../Components/LiteratureDetailHero';
import SiteContainer from '../../Components/SiteContainer';
import ActionPanel from '../../Components/LiteratureDetail/ActionPanel';
import DiscussionSection from '../../Components/LiteratureDetail/DiscussionSection';
import ReviewDialog from '../../Components/LiteratureDetail/ReviewDialog';
import ReviewSection from '../../Components/LiteratureDetail/ReviewSection';
import { SectionHeading, plural } from '../../Components/LiteratureDetail/DetailUi';

function Summary({ literature }) {
    return (
        <SiteContainer as="section" className="py-10 lg:py-12">
            <nav className="flex gap-6 overflow-x-auto border-b border-ink-950/10 text-sm font-bold uppercase tracking-[0.14em] text-ink-950/60" aria-label="Literature details">
                <a href="#summary" className="border-b-2 border-brand-coral pb-4 text-ink-950">Summary</a>
                <a href="#authors" className="pb-4 text-ink-950/70 hover:text-brand-coral">Authors</a>
                <a href="#genres" className="pb-4 text-ink-950/70 hover:text-brand-coral">Genre</a>
                <a href="#relationships" className="pb-4 text-ink-950/70 hover:text-brand-coral">Discovery</a>
                <a href="#reviews" className="pb-4 text-ink-950/70 hover:text-brand-coral">Reviews</a>
                <a href="#discussions" className="pb-4 text-ink-950/70 hover:text-brand-coral">Discussions</a>
            </nav>
            <div className="mt-7">
                <div id="summary" className="scroll-mt-24 border border-ink-950/10 bg-white/40 p-5 sm:p-6">
                    <SectionHeading>Metadata summary</SectionHeading>
                    <p className="mt-4 max-w-[68ch] text-base leading-7 text-ink-900">{literature.synopsis}</p>
                    {literature.synopsis_source_name && literature.synopsis_source_url && (
                        <p className="mt-4 text-xs leading-5 text-ink-950/50">
                            Supplemental summary from <a href={literature.synopsis_source_url} target="_blank" rel="noopener noreferrer" className="font-semibold underline decoration-brand-coral underline-offset-4">{literature.synopsis_source_name}</a> under the <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer" className="underline underline-offset-4">CC BY-SA</a> license.
                        </p>
                    )}
                    <div id="authors" className="mt-10 scroll-mt-24 border-t border-ink-950/10 pt-7">
                        <SectionHeading as="h3">Authors and creators</SectionHeading>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {literature.author_links.length > 0 ? literature.author_links.map((author) => <Link key={author.url} href={author.url} className="bg-brand-sky/25 px-3 py-2 font-semibold text-ink-950 transition hover:bg-brand-coral hover:text-brand-cream">{author.name}</Link>) : <span className="text-sm text-ink-950/55">Author unavailable</span>}
                        </div>
                    </div>
                    <div id="genres" className="mt-8 scroll-mt-24 border-t border-ink-950/10 pt-7">
                        <SectionHeading as="h3">Genre</SectionHeading>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {literature.genres.length > 0 ? literature.genres.map((genre) => <span key={genre} className="border border-ink-950/15 px-3 py-2 text-ink-950/70">{genre}</span>) : <span className="text-sm text-ink-950/55">Genre unavailable</span>}
                        </div>
                    </div>
                </div>
            </div>
        </SiteContainer>
    );
}

function Discovery({ relationshipGroups, authorDiscoveries }) {
    const [expandedGroups, setExpandedGroups] = useState({});

    const toggleGroup = (groupType) => {
        setExpandedGroups((current) => ({
            ...current,
            [groupType]: !current[groupType],
        }));
    };

    return (
        <section id="relationships" className="scroll-mt-24 border-t border-ink-950/10 bg-white/20">
            <SiteContainer className="py-9 lg:py-10">
                <div className="border-b border-ink-950/15 pb-3"><SectionHeading>Relationship Explorer</SectionHeading></div>
                {relationshipGroups.length > 0 ? relationshipGroups.map((group) => {
                    const isExpanded = Boolean(expandedGroups[group.type]);
                    const canExpand = group.items.length > 6;
                    const visibleItems = isExpanded ? group.items : group.items.slice(0, 6);
                    const gridId = `relationship-grid-${group.type}`;

                    return (
                        <section key={group.type} className="mt-6" aria-labelledby={`relationship-${group.type}`}>
                            <div className="mb-3 flex items-center justify-between gap-4">
                                <SectionHeading as="h3" id={`relationship-${group.type}`}>{group.label}</SectionHeading>
                                <div className="flex items-center gap-3">
                                    <span className="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-950/40">{plural(group.items.length, 'work')}</span>
                                    {canExpand && (
                                        <button
                                            type="button"
                                            aria-controls={gridId}
                                            aria-expanded={isExpanded}
                                            onClick={() => toggleGroup(group.type)}
                                            className="text-[10px] font-bold uppercase tracking-[0.14em] text-brand-coral transition hover:text-ink-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral"
                                        >
                                            {isExpanded ? 'Show less' : 'More'}
                                        </button>
                                    )}
                                </div>
                            </div>
                            <div id={gridId} className="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
                                {visibleItems.map((item) => (
                                    <div key={`${group.type}-${item.slug}`} className="min-w-0">
                                        <div className="mb-1.5 flex items-center justify-between gap-1 border-l-2 border-brand-coral bg-brand-cream/70 px-2 py-1.5 text-[0.52rem] font-bold uppercase tracking-[0.1em] text-ink-950"><span>{group.label}</span><span className="truncate text-ink-950/45">{item.relation_source}</span></div>
                                        <LiteratureCard literature={item} compact />
                                    </div>
                                ))}
                            </div>
                        </section>
                    );
                }) : (
                    <div className="mt-6 border border-dashed border-ink-950/20 bg-brand-cream/40 p-5">
                        <p className="font-serif text-xl font-bold text-ink-950">No confirmed relationships yet</p>
                        <p className="mt-1.5 max-w-2xl text-sm leading-6 text-ink-950/55">Relationship data appears after this work is refreshed from a supported source or linked through internal catalog curation.</p>
                    </div>
                )}
                <section className="mt-8 border-t border-ink-950/10 pt-5" aria-labelledby="more-by-authors">
                    <div className="mb-3"><SectionHeading as="h3" id="more-by-authors">More by these authors</SectionHeading></div>
                    {authorDiscoveries.length > 0 ? <div className="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">{authorDiscoveries.map((item) => <LiteratureCard key={item.slug} literature={item} compact />)}</div> : <p className="border border-dashed border-ink-950/20 p-4 text-sm text-ink-950/55">No other works by the same authors are available in the local catalog yet.</p>}
                </section>
            </SiteContainer>
        </section>
    );
}

export default function Show({ literature, viewer, ratingSummary, reviews, discussions, discussionCount, relationshipGroups, authorDiscoveries, reportReasons, successMessage, routes }) {
    const [reviewOpen, setReviewOpen] = useState(false);
    const backdropUrl = literature.backdrop_url || literature.cover_url;
    const hasDedicatedBackdrop = Boolean(literature.backdrop_url);
    const reviewForm = useForm({
        rating: viewer.current_review?.rating ?? '',
        body: viewer.current_review?.body ?? '',
        contains_spoiler: viewer.current_review?.contains_spoiler ?? false,
    });

    useEffect(() => {
        if (reviewForm.errors.rating || reviewForm.errors.body) setReviewOpen(true);
    }, [reviewForm.errors.rating, reviewForm.errors.body]);

    useLayoutEffect(() => {
        document.body.classList.add('has-literature-hero');

        return () => document.body.classList.remove('has-literature-hero');
    }, []);

    const chooseRating = (rating) => {
        reviewForm.setData('rating', rating);
        setReviewOpen(true);
    };

    return (
        <>
            <Head title={literature.title} />
            {successMessage && <div role="status" className="border-b border-brand-sky/50 bg-brand-sky/20 px-5 py-3 text-center text-sm font-semibold text-ink-950">{successMessage}</div>}
            <section data-literature-backdrop className="relative isolate min-h-[360px] overflow-hidden bg-ink-950 sm:min-h-[430px] lg:min-h-[520px]">
                {backdropUrl ? (
                    <>
                        <div className="absolute inset-0 -z-20 overflow-hidden" aria-hidden="true"><img src={backdropUrl} alt="" className={`size-full object-cover object-center ${hasDedicatedBackdrop ? 'scale-[1.02] opacity-90' : 'scale-110 opacity-62 blur-[3px]'}`} /></div>
                        <div className="absolute inset-0 -z-10 bg-linear-to-t from-brand-cream via-ink-950/5 to-ink-950/25" aria-hidden="true" />
                        <div className="absolute inset-0 -z-10 bg-linear-to-r from-ink-950/45 via-transparent to-ink-950/35" aria-hidden="true" />
                    </>
                ) : <div className="catalog-grid absolute inset-0 -z-10 opacity-70" aria-hidden="true" />}
                <SiteContainer className="relative pt-20 sm:pt-24 lg:pt-28">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Link href={routes.catalog} className="inline-flex items-center gap-2 rounded-full bg-ink-950/65 px-4 py-2 text-sm font-semibold text-brand-cream backdrop-blur transition hover:bg-brand-coral"><span aria-hidden="true">←</span> Back to catalog</Link>
                        {routes.admin_edit_metadata && <Link href={routes.admin_edit_metadata} className="rounded-full border border-brand-cream/40 bg-ink-950/65 px-4 py-2 text-xs font-bold uppercase tracking-[0.1em] text-brand-cream backdrop-blur transition hover:border-brand-coral hover:bg-brand-coral">Edit metadata</Link>}
                    </div>
                </SiteContainer>
            </section>

            <section className="border-b border-ink-950/10 bg-brand-cream">
                <SiteContainer data-literature-detail-grid className="relative grid items-start gap-6 py-9 md:grid-cols-[180px_minmax(0,1fr)] lg:grid-cols-[190px_minmax(0,1fr)_240px] lg:gap-7 lg:py-11">
                    <LiteratureDetailHero literature={literature} />
                    <ActionPanel literature={literature} viewer={viewer} ratingSummary={ratingSummary} routes={routes} onOpenReview={() => setReviewOpen(true)} onChooseRating={chooseRating} />
                </SiteContainer>
            </section>

            <Summary literature={literature} />
            <Discovery relationshipGroups={relationshipGroups} authorDiscoveries={authorDiscoveries} />
            <ReviewSection reviews={reviews} ratingSummary={ratingSummary} viewer={viewer} reportReasons={reportReasons} reportAction={routes.report_store} />
            {viewer.authenticated && <ReviewDialog literature={literature} viewer={viewer} routes={routes} form={reviewForm} open={reviewOpen} onClose={() => setReviewOpen(false)} />}
            <DiscussionSection literature={literature} discussions={discussions} discussionCount={discussionCount} viewer={viewer} routes={routes} reportReasons={reportReasons} />
        </>
    );
}
