import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import LiteratureCard from '../../Components/LiteratureCard';
import LiteratureDetailHero from '../../Components/LiteratureDetailHero';
import ActionPanel from '../../Components/LiteratureDetail/ActionPanel';
import DiscussionSection from '../../Components/LiteratureDetail/DiscussionSection';
import ReviewDialog from '../../Components/LiteratureDetail/ReviewDialog';
import ReviewSection from '../../Components/LiteratureDetail/ReviewSection';
import { SectionHeading, plural } from '../../Components/LiteratureDetail/DetailUi';

function Summary({ literature }) {
    return (
        <section className="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <nav className="flex gap-6 overflow-x-auto border-b border-ink-950/10 text-sm font-bold uppercase tracking-[0.14em] text-ink-950/60" aria-label="Literature details">
                <a href="#summary" className="border-b-2 border-brand-coral pb-4 text-ink-950">Summary</a>
                <a href="#authors" className="pb-4 text-ink-950/70 hover:text-brand-coral">Authors</a>
                <a href="#genres" className="pb-4 text-ink-950/70 hover:text-brand-coral">Genre</a>
                <a href="#relationships" className="pb-4 text-ink-950/70 hover:text-brand-coral">Discovery</a>
                <a href="#reviews" className="pb-4 text-ink-950/70 hover:text-brand-coral">Reviews</a>
                <a href="#discussions" className="pb-4 text-ink-950/70 hover:text-brand-coral">Discussions</a>
            </nav>
            <div className="mt-10">
                <div id="summary" className="scroll-mt-24 border border-ink-950/10 bg-white/40 p-6 sm:p-8">
                    <SectionHeading>Metadata summary</SectionHeading>
                    <p className="mt-4 max-w-3xl text-base leading-8 text-ink-900">{literature.synopsis}</p>
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
        </section>
    );
}

function Discovery({ relationshipGroups, authorDiscoveries }) {
    return (
        <section id="relationships" className="scroll-mt-24 border-t border-ink-950/10 bg-white/20">
            <div className="mx-auto max-w-7xl px-5 py-9 sm:px-8 lg:px-10 lg:py-12">
                <div className="border-b border-ink-950/15 pb-3"><SectionHeading>Relationship Explorer</SectionHeading></div>
                {relationshipGroups.length > 0 ? relationshipGroups.map((group) => (
                    <section key={group.type} className="mt-6" aria-labelledby={`relationship-${group.type}`}>
                        <div className="mb-3 flex items-center justify-between gap-4">
                            <SectionHeading as="h3" id={`relationship-${group.type}`}>{group.label}</SectionHeading>
                            <span className="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-950/40">{plural(group.items.length, 'work')}</span>
                        </div>
                        <div className="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
                            {group.items.map((item) => (
                                <div key={`${group.type}-${item.slug}`} className="min-w-0">
                                    <div className="mb-1.5 flex items-center justify-between gap-1 border-l-2 border-brand-coral bg-brand-cream/70 px-2 py-1.5 text-[0.52rem] font-bold uppercase tracking-[0.1em] text-ink-950"><span>{group.label}</span><span className="truncate text-ink-950/45">{item.relation_source}</span></div>
                                    <LiteratureCard literature={item} compact />
                                </div>
                            ))}
                        </div>
                    </section>
                )) : (
                    <div className="mt-6 border border-dashed border-ink-950/20 bg-brand-cream/40 p-5">
                        <p className="font-serif text-xl font-bold text-ink-950">No confirmed relationships yet</p>
                        <p className="mt-1.5 max-w-2xl text-sm leading-6 text-ink-950/55">Relationship data appears after this work is refreshed from a supported source or linked through internal catalog curation.</p>
                    </div>
                )}
                <section className="mt-8 border-t border-ink-950/10 pt-5" aria-labelledby="more-by-authors">
                    <div className="mb-3"><SectionHeading as="h3" id="more-by-authors">More by these authors</SectionHeading></div>
                    {authorDiscoveries.length > 0 ? <div className="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">{authorDiscoveries.map((item) => <LiteratureCard key={item.slug} literature={item} compact />)}</div> : <p className="border border-dashed border-ink-950/20 p-4 text-sm text-ink-950/55">No other works by the same authors are available in the local catalog yet.</p>}
                </section>
            </div>
        </section>
    );
}

export default function Show({ literature, viewer, ratingSummary, reviews, discussions, discussionCount, relationshipGroups, authorDiscoveries, reportReasons, successMessage, routes }) {
    const [reviewOpen, setReviewOpen] = useState(false);
    const reviewForm = useForm({
        rating: viewer.current_review?.rating ?? '',
        body: viewer.current_review?.body ?? '',
        contains_spoiler: viewer.current_review?.contains_spoiler ?? false,
    });

    useEffect(() => {
        if (reviewForm.errors.rating || reviewForm.errors.body) setReviewOpen(true);
    }, [reviewForm.errors.rating, reviewForm.errors.body]);

    const chooseRating = (rating) => {
        reviewForm.setData('rating', rating);
        setReviewOpen(true);
    };

    return (
        <>
            <Head title={literature.title} />
            {successMessage && <div role="status" className="border-b border-brand-sky/50 bg-brand-sky/20 px-5 py-3 text-center text-sm font-semibold text-ink-950">{successMessage}</div>}
            <section data-literature-backdrop className="relative isolate min-h-[360px] overflow-hidden bg-ink-950 sm:min-h-[460px] lg:min-h-[540px]">
                {literature.cover_url ? (
                    <>
                        <div className="absolute inset-0 -z-20 overflow-hidden" aria-hidden="true"><img src={literature.cover_url} alt="" className="size-full scale-110 object-cover object-center opacity-65 blur-[2px]" /></div>
                        <div className="absolute inset-0 -z-10 bg-linear-to-t from-brand-cream via-ink-950/15 to-ink-950/45" aria-hidden="true" />
                        <div className="absolute inset-0 -z-10 bg-linear-to-r from-ink-950/50 via-transparent to-ink-950/45" aria-hidden="true" />
                    </>
                ) : <div className="catalog-grid absolute inset-0 -z-10 opacity-70" aria-hidden="true" />}
                <div className="relative mx-auto max-w-7xl px-5 pt-8 sm:px-8 lg:px-10 lg:pt-10">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Link href={routes.catalog} className="inline-flex items-center gap-2 rounded-full bg-ink-950/65 px-4 py-2 text-sm font-semibold text-brand-cream backdrop-blur transition hover:bg-brand-coral"><span aria-hidden="true">←</span> Back to catalog</Link>
                        {routes.admin_edit_metadata && <Link href={routes.admin_edit_metadata} className="rounded-full border border-brand-cream/40 bg-ink-950/65 px-4 py-2 text-xs font-bold uppercase tracking-[0.1em] text-brand-cream backdrop-blur transition hover:border-brand-coral hover:bg-brand-coral">Edit metadata</Link>}
                    </div>
                </div>
            </section>

            <section className="border-b border-ink-950/10 bg-brand-cream">
                <div data-literature-detail-grid className="relative mx-auto -mt-24 grid max-w-7xl gap-8 px-5 pb-14 sm:px-8 md:grid-cols-[220px_minmax(0,1fr)] lg:grid-cols-[250px_minmax(0,1fr)_300px] lg:px-10 lg:pb-20">
                    <LiteratureDetailHero literature={literature} />
                    <ActionPanel viewer={viewer} ratingSummary={ratingSummary} routes={routes} onOpenReview={() => setReviewOpen(true)} onChooseRating={chooseRating} />
                </div>
            </section>

            <Summary literature={literature} />
            <Discovery relationshipGroups={relationshipGroups} authorDiscoveries={authorDiscoveries} />
            <ReviewSection reviews={reviews} ratingSummary={ratingSummary} viewer={viewer} reportReasons={reportReasons} reportAction={routes.report_store} />
            {viewer.authenticated && <ReviewDialog literature={literature} viewer={viewer} routes={routes} form={reviewForm} open={reviewOpen} onClose={() => setReviewOpen(false)} />}
            <DiscussionSection literature={literature} discussions={discussions} discussionCount={discussionCount} viewer={viewer} routes={routes} reportReasons={reportReasons} />
        </>
    );
}
