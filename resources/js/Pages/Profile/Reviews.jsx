import { Head, Link } from '@inertiajs/react';

import ProfileSubNavigation from '../../Components/ProfileSubNavigation';
import { plural, SpoilerContent, StarRating, TimeLabel } from '../../Components/LiteratureDetail/DetailUi';
import { LiteratureCover, Pagination, ProfileContentContainer, ProfilePageHeading } from '../../Components/ProfilePageUi';

function ReviewItem({ review }) {
    return (
        <article className="grid grid-cols-[48px_minmax(0,1fr)] gap-3 border-b border-ink-950/12 py-3.5 sm:grid-cols-[56px_minmax(0,1fr)] sm:gap-4" data-profile-review>
            <Link href={review.literature.url} className="block self-start">
                <LiteratureCover literature={review.literature} className="aspect-[2/3] rounded-sm shadow-[0_2px_8px_rgba(47,58,85,0.06)]" />
            </Link>

            <div className="min-w-0">
                <div className="flex min-w-0 items-start justify-between gap-2">
                    <h2 className="min-w-0 truncate text-base font-bold leading-5 text-ink-950 sm:text-lg">
                        <Link href={review.literature.url} className="hover:text-brand-coral" title={review.literature.title}>{review.literature.title}</Link>
                        {review.literature.year && <span className="ml-1.5 text-xs font-normal text-ink-950/45">{review.literature.year}</span>}
                    </h2>
                    {review.edit_url && <Link href={review.edit_url} className="shrink-0 text-[0.65rem] font-bold uppercase tracking-wider text-brand-coral hover:underline">Edit review</Link>}
                </div>

                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[0.7rem] text-ink-950/50">
                    <StarRating rating={review.rating} size="text-sm" />
                    <span className="font-bold text-ink-950/65">{Number(review.rating).toFixed(1)}</span>
                    <span>Reviewed <TimeLabel value={review.updated_at} /></span>
                </div>

                {review.body ? (
                    review.contains_spoiler
                        ? <div className="mt-1.5"><SpoilerContent body={review.body} kind="review" className="line-clamp-3 text-sm leading-5 text-ink-900" /></div>
                        : <p className="mt-1.5 line-clamp-3 whitespace-pre-line text-sm leading-5 text-ink-900">{review.body}</p>
                ) : <p className="mt-1.5 text-xs italic text-ink-950/45">Rating only.</p>}

                {review.likes_count > 0 && <p className="mt-1.5 text-[0.7rem] text-ink-950/45">{plural(review.likes_count, 'like')}</p>}
            </div>
        </article>
    );
}

export default function ProfileReviews({ profile, navigation, reviews, summary }) {
    return (
        <>
            <Head title={`${profile.name} Reviews`} />
            <ProfileSubNavigation navigation={navigation} />

            <ProfileContentContainer className="py-7 lg:py-9">
                <div className="min-w-0" aria-labelledby="profile-reviews-heading">
                    <ProfilePageHeading eyebrow="Reader notes" title={`${profile.name}'s Reviews`} count={plural(summary.total_reviews, 'review')} id="profile-reviews-heading" />

                    <div className="flex min-h-10 flex-wrap items-center justify-between gap-2 border-b border-ink-950/12 text-xs text-ink-950/50" data-review-summary>
                        <span className="font-bold uppercase tracking-[0.13em] text-ink-950/65">Reviews</span>
                        <span className="flex items-center gap-2">
                            <span>Average</span>
                            <strong className="text-sm text-ink-950">{summary.total_reviews ? Number(summary.average_rating).toFixed(1) : '—'}</strong>
                            {summary.total_reviews > 0 && <StarRating rating={summary.average_rating} size="text-xs" />}
                        </span>
                    </div>

                    <div data-profile-review-list data-density="compact">
                        {reviews.data.length ? reviews.data.map((review) => <ReviewItem key={review.id} review={review} />) : (
                            <div className="border-b border-ink-950/12 py-9 text-center">
                                <p className="text-xl font-bold text-ink-950">No reviews yet.</p>
                                <p className="mt-2 text-sm text-ink-950/55">Written reviews and rating-only entries will appear here.</p>
                            </div>
                        )}
                    </div>
                    <Pagination paginator={reviews} label="Profile reviews pagination" />
                </div>
            </ProfileContentContainer>
        </>
    );
}
