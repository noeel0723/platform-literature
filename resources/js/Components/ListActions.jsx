import { Link } from '@inertiajs/react';

import { openLoginPanel } from '../Support/loginPanel';

const actionClass = 'flex min-h-11 w-full items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-ink-950 transition-colors hover:bg-brand-sky/15 focus-visible:outline-2 focus-visible:outline-brand-coral disabled:cursor-not-allowed disabled:opacity-50';

export default function ListActions({ list, canEdit, commenting = {}, commentsCount = 0, processing, onLike, onDelete }) {
    const progress = list.progress;
    const canLike = Boolean(list.like_url);
    const requiresVerification = commenting.authenticated && commenting.verification_url;
    return (
        <aside aria-label="List actions and reading progress" className="min-w-0 space-y-4">
            <div className="divide-y divide-ink-950/10 overflow-hidden rounded-xl border border-ink-950/15 bg-white/50">
                {requiresVerification ? <Link href={commenting.verification_url} className={actionClass}>Verify email to like this list</Link> : (
                    <button type="button" aria-label={list.is_liked ? 'Unlike this list' : 'Like this list'} aria-pressed={Boolean(list.is_liked)} aria-busy={Boolean(processing)} disabled={processing || (commenting.authenticated && !canLike)} onClick={canLike ? onLike : openLoginPanel} className={actionClass}>
                        <svg aria-hidden="true" viewBox="0 0 24 24" className={`size-4 shrink-0 ${list.is_liked ? 'fill-brand-coral text-brand-coral' : 'fill-none text-ink-950/55'}`} stroke="currentColor" strokeWidth="1.5"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" /></svg>
                        {list.is_liked ? 'Liked' : 'Like this list'} <span className="font-normal text-ink-950/50">{list.likes_count ?? 0}</span>
                    </button>
                )}
                <a href="#comments" className={actionClass}>{commentsCount} {commentsCount === 1 ? 'comment' : 'comments'}</a>
                {canEdit && <>
                    <Link href={list.edit_url} className={actionClass}>Edit list</Link>
                    <button type="button" disabled={processing} onClick={onDelete} className={`${actionClass} text-brand-coral`}>Delete list</button>
                </>}
            </div>
            <div className="overflow-hidden rounded-xl border border-ink-950/15 bg-white/30">
                {progress ? <>
                    <div className="flex items-center justify-between gap-3 p-4">
                        <p className="text-xs leading-5 text-ink-950/65">You’ve read<span className="block font-semibold text-ink-950">{progress.completed} of {progress.total} literature</span></p>
                        <p className="font-serif text-3xl text-ink-950">{progress.percentage}<span className="text-base">%</span></p>
                    </div>
                    <div role="progressbar" aria-label="List reading progress" aria-valuemin={0} aria-valuemax={100} aria-valuenow={progress.percentage} aria-valuetext={`${progress.completed} of ${progress.total} literature completed`} className="h-1.5 bg-ink-950/10">
                        <div className="h-full bg-brand-coral" style={{ width: `${progress.percentage}%` }} />
                    </div>
                </> : <button type="button" onClick={openLoginPanel} className={`${actionClass} text-xs`}>Log in to track your reading progress</button>}
            </div>
        </aside>
    );
}
