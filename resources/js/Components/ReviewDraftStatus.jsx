const messages = {
    checking: 'Checking saved review…',
    pending: 'Saving draft…',
    saved: 'Draft saved locally',
    recovered: 'Draft recovered · saved locally',
    conflict: 'The saved review may have changed. Restore or discard your local draft before editing.',
    unavailable: 'Local draft storage is unavailable. You can still save your review.',
    offline: 'Latest review unavailable. Changes will still be saved locally.',
    discarded: 'Draft discarded.',
    'account-changed': 'Your account changed. Refresh before writing a review.',
};

export default function ReviewDraftStatus({ draft, processing = false }) {
    if (!messages[draft.status] && !draft.hasDraft) return null;
    return (
        <div className="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px] leading-5 text-brand-blueberry/65">
            <p role="status" aria-live="polite">{messages[draft.status]}</p>
            {draft.hasDraft && <span className="flex shrink-0 gap-3">
                {draft.status === 'conflict' && <button type="button" disabled={processing} onClick={draft.restore} className="font-semibold text-brand-blueberry underline decoration-brand-coral/60 underline-offset-2 disabled:opacity-50">Restore draft</button>}
                <button type="button" disabled={processing} onClick={draft.discard} className="font-semibold underline underline-offset-2 hover:text-brand-coral disabled:opacity-50">Discard draft</button>
            </span>}
        </div>
    );
}
