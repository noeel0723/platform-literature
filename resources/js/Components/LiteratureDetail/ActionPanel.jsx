import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { RatingInput, StarRating, plural } from './DetailUi';

export default function ActionPanel({ literature, viewer, ratingSummary, routes, onOpenReview, onChooseRating }) {
    const [processing, setProcessing] = useState(false);
    const isCompleted = viewer.reading_status === 'completed';
    const isInReadlist = viewer.reading_status === 'want_to_read';
    const listForm = useForm({
        title: '',
        description: '',
        is_private: false,
        literature_id: literature.id,
        return_to: 'literature',
    });

    const updateReading = (status, active) => {
        setProcessing(true);
        const options = { preserveScroll: true, onFinish: () => setProcessing(false) };

        if (active) router.delete(routes.reading_destroy, options);
        else router.put(routes.reading_update, { status }, options);
    };

    const toggleList = (list) => {
        setProcessing(true);
        const options = { preserveScroll: true, onFinish: () => setProcessing(false) };

        if (list.contains) router.delete(list.destroy_url, options);
        else router.post(list.store_url, { literature_id: literature.id }, options);
    };

    const createList = (event) => {
        event.preventDefault();
        listForm.post(routes.custom_list_store, {
            preserveScroll: true,
            onSuccess: () => listForm.reset('title'),
        });
    };

    return (
        <aside className="self-start overflow-hidden border border-ink-950/25 bg-brand-cream/95 shadow-[0_12px_32px_rgba(47,58,85,0.08)] backdrop-blur-md md:col-span-2 lg:col-span-1" aria-label="Your literature actions">
            <div data-community-rating className="border-b border-ink-950/10 p-4 text-center">
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
                            className={`px-2 py-4 text-center font-bold text-ink-950 transition disabled:opacity-50 ${isCompleted ? 'bg-brand-sky/30' : 'hover:bg-brand-sky/35'}`}
                            onClick={() => updateReading('completed', isCompleted)}
                        >
                            <svg className={`mx-auto size-8 ${isCompleted ? 'text-brand-coral' : 'text-ink-950'}`} aria-hidden="true" viewBox="0 0 32 32" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="16" cy="16" r="13" /><path d="m10 16 4 4 8-9" /></svg>
                            <span className="mt-2 block text-xs sm:text-sm">Completed</span>
                        </button>
                        <button type="button" className="px-2 py-4 text-center font-bold text-ink-950 transition hover:bg-brand-coral hover:text-brand-cream" onClick={onOpenReview}>
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
                            className={`px-2 py-4 text-center font-bold text-ink-950 transition disabled:opacity-50 ${isInReadlist ? 'bg-brand-sky/30' : 'hover:bg-brand-sky/35'}`}
                            onClick={() => updateReading('want_to_read', isInReadlist)}
                        >
                            <svg className={`mx-auto size-8 ${isInReadlist ? 'fill-brand-coral text-brand-coral' : 'text-ink-950'}`} aria-hidden="true" viewBox="0 0 32 32" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 5h14a2 2 0 0 1 2 2v20l-9-5-9 5V7a2 2 0 0 1 2-2Z" /></svg>
                            <span className="mt-2 block text-xs sm:text-sm">Readlist</span>
                        </button>
                    </div>
                    <div data-your-rating className="border-b border-ink-950/10 p-4 text-center">
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/55">Your Rating</p>
                        <div className="mt-3 flex flex-wrap items-center justify-center gap-3">
                            <RatingInput value={viewer.current_review?.rating ?? ''} onChange={() => {}} onCommit={onChooseRating} compact />
                            <span className="min-w-16 text-sm font-bold text-ink-950/60">{viewer.current_review ? `${Number(viewer.current_review.rating).toFixed(1)} / 5` : 'Choose a rating'}</span>
                        </div>
                        <p className="mt-2 text-xs text-ink-950/45">Hover to preview. Click a star to continue in the review form.</p>
                    </div>
                    <details className="group border-b border-ink-950/10">
                        <summary className="cursor-pointer list-none px-4 py-3 text-center text-sm font-bold text-ink-950 transition hover:bg-brand-sky/25">Add to List</summary>
                        <div className="border-t border-ink-950/10 p-3">
                            {viewer.custom_lists.length > 0 ? (
                                <div className="space-y-1">
                                    {viewer.custom_lists.map((list) => (
                                        <button
                                            key={list.id}
                                            type="button"
                                            disabled={processing}
                                            onClick={() => toggleList(list)}
                                            className={`flex w-full items-center justify-between gap-3 px-2.5 py-2 text-left text-xs font-semibold transition disabled:opacity-50 ${list.contains ? 'bg-brand-sky/35 text-ink-950' : 'hover:bg-brand-sky/20'}`}
                                        >
                                            <span className="truncate">{list.title}{list.is_private ? ' · Private' : ''}</span>
                                            <span aria-hidden="true">{list.contains ? '✓' : '+'}</span>
                                        </button>
                                    ))}
                                </div>
                            ) : <p className="text-xs text-ink-950/50">You have no custom lists yet.</p>}

                            <form onSubmit={createList} className="mt-3 border-t border-ink-950/10 pt-3">
                                <label htmlFor="quick-list-title" className="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-ink-950/50">Create a new list</label>
                                <div className="mt-1.5 flex">
                                    <input id="quick-list-title" value={listForm.data.title} onChange={(event) => listForm.setData('title', event.target.value)} className="min-w-0 flex-1 border border-ink-950/20 bg-white/60 px-2.5 py-2 text-xs outline-none focus:border-brand-coral" placeholder="List title" required maxLength={120} />
                                    <button type="submit" disabled={listForm.processing} className="bg-ink-950 px-3 text-xs font-bold text-brand-cream transition hover:bg-brand-coral disabled:opacity-50">Create</button>
                                </div>
                                {listForm.errors.title && <p className="mt-1 text-[0.65rem] text-red-700">{listForm.errors.title}</p>}
                            </form>
                        </div>
                    </details>
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
