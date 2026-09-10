import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { plural, SpoilerContent, StarRating, TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfilePageHeading } from '../../Components/ProfilePageUi';

function ReviewItem({ review }) {
    return (
        <article className="grid grid-cols-[52px_minmax(0,1fr)] gap-3 border-b border-ink-950/12 py-4 sm:grid-cols-[60px_minmax(0,1fr)] sm:gap-4" data-profile-review>
            <Link href={review.literature.url} className="block self-start">
                <LiteratureCover literature={review.literature} className="aspect-[2/3] rounded-sm shadow-[0_3px_10px_rgba(47,58,85,0.06)]" />
            </Link>

            <div className="min-w-0">
                <div className="flex min-w-0 items-start justify-between gap-3">
                    <h2 className="min-w-0 truncate text-lg font-bold leading-6 text-ink-950 sm:text-xl">
                        <Link href={review.literature.url} className="hover:text-brand-coral" title={review.literature.title}>{review.literature.title}</Link>
                        {review.literature.year && <span className="ml-1.5 text-xs font-normal text-ink-950/45">{review.literature.year}</span>}
                    </h2>
                    {review.edit_url && <Link href={review.edit_url} className="shrink-0 text-[0.65rem] font-bold uppercase tracking-wider text-brand-coral hover:underline">Edit review</Link>}
                </div>

                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink-950/50">
                    <StarRating rating={review.rating} size="text-sm" />
                    <span className="font-bold text-ink-950/65">{Number(review.rating).toFixed(1)}</span>
                    <TimeLabel value={review.updated_at} />
                </div>

                {review.body ? (
                    review.contains_spoiler
                        ? <div className="mt-2"><SpoilerContent body={review.body} kind="review" className="text-[0.95rem] leading-6 text-ink-900" /></div>
                        : <p className="mt-2 whitespace-pre-line text-[0.95rem] leading-6 text-ink-900">{review.body}</p>
                ) : <p className="mt-2 text-xs italic text-ink-950/45">Rating only.</p>}

                <p className="mt-2 text-xs text-ink-950/45">{plural(review.likes_count, 'like')}</p>
            </div>
        </article>
    );
}

export default function ProfileReviews({ profile, navigation, reviews, summary }) {
    return (
        <>
            <Head title={`${profile.name} Reviews`} />
            <ProfileSubNavigation navigation={navigation} />

            <section className="mx-auto grid max-w-7xl gap-7 px-5 py-8 sm:px-8 lg:grid-cols-[minmax(0,1fr)_220px] lg:px-10 lg:py-10">
                <div className="min-w-0" aria-labelledby="profile-reviews-heading">
                    <ProfilePageHeading eyebrow="Reader notes" title={`${profile.name}'s Reviews`} count={plural(summary.total_reviews, 'review')} id="profile-reviews-heading" />

                    <div data-profile-review-list data-density="compact">
                        {reviews.data.length ? reviews.data.map((review) => <ReviewItem key={review.id} review={review} />) : (
                            <div className="border-b border-ink-950/12 py-10 text-center">
                                <p className="text-xl font-bold text-ink-950">No reviews yet.</p>
                                <p className="mt-2 text-sm text-ink-950/55">Written reviews and rating-only entries will appear here.</p>
                            </div>
                        )}
                    </div>
                    <Pagination paginator={reviews} label="Profile reviews pagination" />
                </div>

                <aside className="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-5">
                    <section className="sticky top-24 border border-ink-950/10 bg-brand-cream/80 p-4" aria-labelledby="review-summary-heading" data-review-summary>
                        <p className="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Review summary</p>
                        <h2 id="review-summary-heading" className="mt-1 text-lg font-bold text-ink-950">Reader average</h2>
                        <div className="mt-3 flex items-end gap-2">
                            <strong className="text-3xl font-bold leading-none text-ink-950">{summary.total_reviews ? Number(summary.average_rating).toFixed(1) : '—'}</strong>
                            <span className="pb-0.5 text-xs text-ink-950/45">out of 5</span>
                        </div>
                        {summary.total_reviews > 0 && <StarRating rating={summary.average_rating} size="mt-2 text-sm" />}
                        <dl className="mt-4 border-t border-ink-950/10 pt-3"><div className="flex items-center justify-between text-xs"><dt className="text-ink-950/55">Reviews</dt><dd className="font-bold text-ink-950">{summary.total_reviews}</dd></div></dl>
                    </section>
                </aside>
            </section>
        </>
    );
}
