import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { RatingInput, StarRating, plural } from './DetailUi';

export default function ActionPanel({ viewer, ratingSummary, routes, onOpenReview, onChooseRating }) {
    const [processing, setProcessing] = useState(false);
    const isCompleted = viewer.reading_status === 'completed';
    const isInReadlist = viewer.reading_status === 'want_to_read';

    const updateReading = (status, active) => {
        setProcessing(true);
        const options = { preserveScroll: true, onFinish: () => setProcessing(false) };

        if (active) router.delete(routes.reading_destroy, options);
        else router.put(routes.reading_update, { status }, options);
    };

    return (
        <aside className="self-start overflow-hidden border border-ink-950/25 bg-brand-cream/95 shadow-[0_12px_32px_rgba(47,58,85,0.08)] backdrop-blur-md md:col-span-2 lg:col-span-1" aria-label="Your literature actions">
            <div data-community-rating className="border-b border-ink-950/10 p-5 text-center">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/55">Community rating</p>
                <div className="mt-3 flex items-center justify-center gap-3">
                    <StarRating rating={ratingSummary.average ?? 0} />
                    <span className="font-serif text-2xl font-bold text-ink-950">{ratingSummary.average ? Number(ratingSummary.average).toFixed(1) : '—'}</span>
                </div>
                <p className="mt-2 text-xs text-ink-950/45">Average from {plural(ratingSummary.count, 'reader')}</p>
            </div>

            {viewer.authenticated ? (
                <>
                    <div data-literature-actions className="grid grid-cols-3 divide-x divide-ink-950/10 border-b border-ink-950/10">
                        <button
                            type="button"
                            data-reading-toggle="completed"
                            data-active={isCompleted}
                            disabled={processing}
                            aria-pressed={isCompleted}
                            title={isCompleted ? 'Remove completed status' : 'Mark as completed'}
                            className={`px-2 py-5 text-center font-bold text-ink-950 transition disabled:opacity-50 ${isCompleted ? 'bg-brand-sky/30' : 'hover:bg-brand-sky/35'}`}
                            onClick={() => updateReading('completed', isCompleted)}
                        >
                            <svg className={`mx-auto size-8 ${isCompleted ? 'text-brand-coral' : 'text-ink-950'}`} aria-hidden="true" viewBox="0 0 32 32" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="16" cy="16" r="13" /><path d="m10 16 4 4 8-9" /></svg>
                            <span className="mt-2 block text-xs sm:text-sm">Completed</span>
                        </button>
                        <button type="button" className="px-2 py-5 text-center font-bold text-ink-950 transition hover:bg-brand-coral hover:text-brand-cream" onClick={onOpenReview}>
                            <span className="block text-3xl leading-none" aria-hidden="true">★</span>
                            <span className="mt-2 block text-xs sm:text-sm">{viewer.current_review ? 'Edit Review' : 'Rate & Review'}</span>
                        </button>
                        <button
                            type="button"
                            data-reading-toggle="readlist"
                            data-active={isInReadlist}
                            disabled={processing}
                            aria-pressed={isInReadlist}
                            title={isInReadlist ? 'Remove from Readlist' : 'Add to Readlist'}
                            className={`px-2 py-5 text-center font-bold text-ink-950 transition disabled:opacity-50 ${isInReadlist ? 'bg-brand-sky/30' : 'hover:bg-brand-sky/35'}`}
                            onClick={() => updateReading('want_to_read', isInReadlist)}
                        >
                            <svg className={`mx-auto size-8 ${isInReadlist ? 'fill-brand-coral text-brand-coral' : 'text-ink-950'}`} aria-hidden="true" viewBox="0 0 32 32" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 5h14a2 2 0 0 1 2 2v20l-9-5-9 5V7a2 2 0 0 1 2-2Z" /></svg>
                            <span className="mt-2 block text-xs sm:text-sm">Readlist</span>
                        </button>
                    </div>
                    <div data-your-rating className="border-b border-ink-950/10 p-5 text-center">
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/55">Your Rating</p>
                        <div className="mt-3 flex flex-wrap items-center justify-center gap-3">
                            <RatingInput value={viewer.current_review?.rating ?? ''} onChange={() => {}} onCommit={onChooseRating} compact />
                            <span className="min-w-16 text-sm font-bold text-ink-950/60">{viewer.current_review ? `${Number(viewer.current_review.rating).toFixed(1)} / 5` : 'Choose a rating'}</span>
                        </div>
                        <p className="mt-2 text-xs text-ink-950/45">Hover to preview. Click a star to continue in the review form.</p>
                    </div>
                </>
            ) : (
                <div className="p-5">
                    <p className="text-sm leading-6 text-ink-950/65">Log in to rate, review, and track this literature.</p>
                    <Link href={routes.login} className="mt-4 block bg-ink-950 px-4 py-3 text-center font-bold text-brand-cream transition hover:bg-brand-coral">Log in</Link>
                </div>
            )}
        </aside>
    );
}
