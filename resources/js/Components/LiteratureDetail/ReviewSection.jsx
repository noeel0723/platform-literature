import { Link, router } from '@inertiajs/react';

import SiteContainer from '../SiteContainer';
import { ReportForm, SectionHeading, SpoilerContent, StarRating, TimeLabel, UserAvatar, plural } from './DetailUi';

export default function ReviewSection({ reviews, ratingSummary, viewer, reportReasons, reportAction }) {
    const toggleLike = (review) => {
        const options = { preserveScroll: true };
        if (review.is_liked) router.delete(review.unlike_url, options);
        else router.post(review.like_url, {}, options);
    };

    return (
        <section id="reviews" className="scroll-mt-24 border-t border-ink-950/10">
            <SiteContainer className="py-9 lg:py-10">
                <div className="mx-auto max-w-3xl">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-950/20 pb-2.5">
                        <SectionHeading>Reader reviews</SectionHeading>
                        <div className="flex items-center gap-2 text-sm text-ink-950/65">
                            <StarRating rating={ratingSummary.average ?? 0} size="text-sm" />
                            <span className="font-bold text-ink-950">{ratingSummary.average ? Number(ratingSummary.average).toFixed(1) : '—'}</span>
                            <span>{plural(ratingSummary.count, 'rating')}</span>
                        </div>
                    </div>

                    <div className="divide-y divide-ink-950/15">
                        {reviews.length > 0 ? reviews.map((review) => (
                            <article key={review.id} id={`review-${review.id}`} className="scroll-mt-28 py-5 first:pt-4 sm:py-6">
                                <div className="flex items-start gap-3">
                                    <UserAvatar user={review.user} className="size-9 sm:size-10" />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm text-ink-950/65">
                                            <span>Review by <Link href={review.user.url} className="font-bold text-ink-950 transition hover:text-brand-coral">{review.user.name}</Link></span>
                                            <StarRating rating={review.rating} size="text-sm" />
                                            <TimeLabel value={review.created_at} className="text-xs text-ink-950/60" />
                                        </div>

                                        {review.body ? (
                                            <div className="mt-3">
                                                {review.contains_spoiler ? <SpoilerContent body={review.body} kind="review" className="font-serif text-lg leading-relaxed text-ink-950/85" /> : <p className="whitespace-pre-line font-serif text-lg leading-relaxed text-ink-950/85">{review.body}</p>}
                                            </div>
                                        ) : <p className="mt-3 text-sm italic text-ink-950/60">Rating only.</p>}

                                        <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-ink-950/65">
                                            {viewer.authenticated && <button type="button" onClick={() => toggleLike(review)} className={`font-semibold transition hover:text-brand-coral ${review.is_liked ? 'text-brand-coral' : 'text-ink-950/70'}`} aria-pressed={review.is_liked}>{review.is_liked ? '♥ Liked' : '♥ Like review'}</button>}
                                            <span>{plural(review.likes_count, 'like')}</span>
                                            {viewer.authenticated && review.can_report && <ReportForm targetType="review" targetId={review.id} reasons={reportReasons} action={reportAction} />}
                                        </div>
                                    </div>
                                </div>
                            </article>
                        )) : <div className="py-7 text-sm text-ink-950/65">No reviews yet. Be the first reader to share a rating.</div>}
                    </div>
                </div>
            </SiteContainer>
        </section>
    );
}
