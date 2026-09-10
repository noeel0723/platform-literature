import { Link, router } from '@inertiajs/react';

import { ReportForm, SectionHeading, SpoilerContent, StarRating, TimeLabel, plural } from './DetailUi';

export default function ReviewSection({ reviews, ratingSummary, viewer, reportReasons, reportAction }) {
    const toggleLike = (review) => {
        const options = { preserveScroll: true };
        if (review.is_liked) router.delete(review.unlike_url, options);
        else router.post(review.like_url, {}, options);
    };

    return (
        <section id="reviews" className="scroll-mt-24 border-t border-ink-950/10">
            <div className="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <div className="flex flex-col gap-3 border-b border-ink-950/15 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <SectionHeading>Ratings &amp; reviews</SectionHeading>
                    <div className="text-left sm:text-right">
                        <div className="flex items-center gap-3 sm:justify-end">
                            <StarRating rating={ratingSummary.average ?? 0} />
                            <p className="font-serif text-3xl font-bold text-ink-950">{ratingSummary.average ? Number(ratingSummary.average).toFixed(1) : '—'}</p>
                        </div>
                        <p className="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">{plural(ratingSummary.count, 'rating')}</p>
                    </div>
                </div>

                <div className="mt-6">
                    <SectionHeading as="h3">Reader reviews</SectionHeading>
                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                        {reviews.length > 0 ? reviews.map((review) => (
                            <article key={review.id} id={`review-${review.id}`} className="scroll-mt-28 border border-ink-950/10 bg-white/35 p-4 sm:p-5">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <Link href={review.user.url} className="font-bold text-ink-950 transition hover:text-brand-coral">{review.user.name}</Link>
                                        <TimeLabel value={review.created_at} className="mt-1 block text-xs font-semibold uppercase tracking-wider text-ink-950/45" />
                                    </div>
                                    <div className="text-right">
                                        <StarRating rating={review.rating} />
                                        <p className="mt-1 text-xs font-semibold text-ink-950/50">{Number(review.rating).toFixed(1)} / 5</p>
                                    </div>
                                </div>
                                <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-ink-950/10 pt-4">
                                    <span className="text-xs font-bold uppercase tracking-wider text-ink-950/45">{plural(review.likes_count, 'like')}</span>
                                    {viewer.authenticated && (
                                        <>
                                            <button type="button" onClick={() => toggleLike(review)} className={`border px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition ${review.is_liked ? 'border-brand-coral bg-brand-coral text-brand-cream' : 'border-ink-950/20 text-ink-950 hover:border-brand-coral'}`} aria-pressed={review.is_liked}>{review.is_liked ? 'Liked' : 'Like'}</button>
                                            {review.can_report && <ReportForm targetType="review" targetId={review.id} reasons={reportReasons} action={reportAction} />}
                                        </>
                                    )}
                                </div>
                                {review.body ? (
                                    <div className="mt-4">
                                        {review.contains_spoiler ? <SpoilerContent body={review.body} kind="review" className="mt-3 leading-7 text-ink-900" /> : <p className="whitespace-pre-line leading-7 text-ink-900">{review.body}</p>}
                                    </div>
                                ) : <p className="mt-4 text-sm italic text-ink-950/50">Rating only.</p>}
                            </article>
                        )) : <div className="border border-dashed border-ink-950/20 p-7 text-ink-950/60">No reviews yet. Be the first reader to share a rating.</div>}
                    </div>
                </div>
            </div>
        </section>
    );
}
