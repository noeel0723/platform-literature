import { Link } from '@inertiajs/react';
import { RatingInput } from './LiteratureDetail/DetailUi';

const actionClass = 'flex min-h-10 w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-xs font-semibold text-ink-950 hover:bg-brand-sky/20 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:cursor-wait disabled:opacity-50';

export default function ActivityLiteratureActions({ preview, routes, literature, processing, onReading, onReview, onList }) {
    const reading = preview.viewer.reading_status;
    const rating = preview.literature.review?.rating;

    return (
        <aside aria-label="Your literature actions" className="min-w-0 self-start overflow-hidden rounded-xl border border-ink-950/15 bg-white/50">
            <div className="grid grid-cols-3 divide-x divide-ink-950/10 border-b border-ink-950/10">
                <button type="button" onClick={() => onReading('completed')} disabled={processing} aria-pressed={reading === 'completed'} className="min-h-19 px-1 py-3 text-center text-[11px] font-semibold text-ink-950 hover:bg-brand-sky/20 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={`mx-auto mb-1 size-6 ${reading === 'completed' ? 'text-brand-coral' : ''}`}><circle cx="12" cy="12" r="9" /><path d="m7 12 3 3 7-7" /></svg>Completed
                </button>
                <button type="button" onClick={() => onReview()} disabled={processing} className="min-h-19 px-1 py-3 text-center text-[11px] font-semibold text-ink-950 hover:bg-brand-sky/20 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50"><span aria-hidden="true" className="mb-1 block text-2xl leading-6 text-brand-coral">★</span>Rate & Review</button>
                <button type="button" onClick={() => onReading('want_to_read')} disabled={processing} aria-pressed={reading === 'want_to_read'} className="min-h-19 px-1 py-3 text-center text-[11px] font-semibold text-ink-950 hover:bg-brand-sky/20 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:opacity-50">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" className={`mx-auto mb-1 size-6 ${reading === 'want_to_read' ? 'fill-brand-coral text-brand-coral' : ''}`}><path d="M6 3h12v18l-6-4-6 4V3Z" /></svg>Readlist
                </button>
            </div>
            <div className="border-b border-ink-950/10 px-3 py-4 text-center">
                <p className="text-[10px] font-bold uppercase tracking-wider text-ink-950/50">Your rating</p>
                <fieldset disabled={processing} className="mt-2 flex min-w-0 justify-center disabled:opacity-50"><legend className="sr-only">Choose your rating</legend><RatingInput value={rating ?? ''} compact onChange={() => {}} onCommit={onReview} /></fieldset>
            </div>
            <Link href={routes.own_activity} className={actionClass}>Your activity <span aria-hidden="true">→</span></Link>
            {routes.actor_activity && routes.actor_activity !== routes.own_activity && <Link href={routes.actor_activity} className={`${actionClass} border-t border-ink-950/10`}>Reader’s activity <span aria-hidden="true">→</span></Link>}
            <details className="border-t border-ink-950/10">
                <summary className={`${actionClass} cursor-pointer list-none`}>Add to a list <span aria-hidden="true">+</span></summary>
                <div className="max-h-56 overflow-y-auto border-t border-ink-950/10 p-2">
                    {preview.lists.length > 0 ? preview.lists.map((list) => <button key={list.id} type="button" disabled={processing} onClick={() => onList(list)} aria-pressed={list.contains} className={`${actionClass} rounded-md`}><span className="min-w-0 truncate">{list.title}{list.is_private ? ' · Private' : ''}</span><span aria-hidden="true">{list.contains ? '✓' : '+'}</span></button>) : <p className="p-2 text-xs text-ink-950/55">No custom lists yet.</p>}
                    <Link href={preview.routes.create_list} className="block px-2 py-2 text-xs font-semibold text-brand-coral">Create a list</Link>
                </div>
            </details>
            <Link href={literature.url} className={`${actionClass} border-t border-ink-950/10`}>View literature details <span aria-hidden="true">→</span></Link>
        </aside>
    );
}
