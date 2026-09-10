import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { RatingInput } from './DetailUi';

const coverThemes = {
    coral: 'from-brand-coral via-brand-cream to-brand-sky',
    sky: 'from-brand-sky via-brand-cream to-ink-950',
    cream: 'from-brand-cream via-brand-sky to-brand-cream',
    deep: 'from-ink-950 via-brand-cream to-brand-coral',
    mixed: 'from-brand-cream via-brand-coral to-brand-sky',
};

export default function ReviewDialog({ literature, viewer, routes, form, open, onClose }) {
    const dialogRef = useRef(null);

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const submit = (event) => {
        event.preventDefault();
        form.put(routes.review_update, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const remove = () => {
        if (!window.confirm('Delete your rating and review? This action cannot be undone.')) return;

        router.delete(routes.review_destroy, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <dialog
            ref={dialogRef}
            id="review-dialog"
            data-review-dialog
            className="review-dialog m-auto max-h-[90vh] w-[min(920px,calc(100%_-_2rem))] overflow-y-auto border border-ink-950/20 bg-brand-cream p-0 text-ink-950 shadow-[0_18px_48px_rgba(47,58,85,0.18)] backdrop:bg-ink-950/70"
            onCancel={(event) => { event.preventDefault(); onClose(); }}
            onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}
        >
            <div className="sticky top-0 z-10 flex items-center justify-between border-b border-ink-950/10 bg-ink-950 px-5 py-4 text-brand-cream sm:px-7">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-sky">Your reading experience</p>
                    <h2 className="mt-1 font-serif text-2xl font-bold">Rate &amp; review</h2>
                </div>
                <button type="button" className="grid size-10 place-items-center border border-brand-cream/30 text-2xl leading-none transition hover:border-brand-coral hover:text-brand-coral" aria-label="Close review dialog" onClick={onClose}>×</button>
            </div>

            <form onSubmit={submit} className="grid gap-7 p-5 sm:p-7 md:grid-cols-[180px_minmax(0,1fr)]">
                <div>
                    <div className={`aspect-[2/3] overflow-hidden border border-ink-950/15 bg-linear-to-br ${coverThemes[literature.theme] ?? 'from-brand-cream via-brand-sky to-brand-coral'} shadow-[0_8px_24px_rgba(47,58,85,0.10)]`}>
                        {literature.cover_url ? <img src={literature.cover_url} alt={`Cover of ${literature.title}`} className="size-full object-cover" /> : <div className="grid size-full place-items-center p-4 text-center font-serif text-5xl font-bold">{literature.initials}</div>}
                    </div>
                    <p className="mt-3 text-center text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">{literature.type_label}</p>
                </div>

                <div className="min-w-0">
                    <h3 className="font-serif text-3xl font-bold leading-tight text-ink-950">{literature.title}</h3>
                    <p className="mt-1 text-sm text-ink-950/55">{literature.author} · {literature.year}</p>

                    <fieldset className="mt-6">
                        <legend className="text-sm font-bold uppercase tracking-[0.16em] text-ink-950">Your Rating</legend>
                        <div className="mt-2 flex flex-wrap items-center gap-4">
                            <RatingInput value={form.data.rating} onChange={(rating) => form.setData('rating', rating)} />
                            <output className="min-w-16 text-sm font-bold text-ink-950/60">{form.data.rating ? `${Number(form.data.rating).toFixed(1)} / 5` : 'Choose a rating'}</output>
                        </div>
                        <p className="mt-2 text-xs text-ink-950/50">Hover to preview a rating, then click to select it. Half-star ratings are supported.</p>
                        {form.errors.rating && <p className="mt-2 text-sm font-semibold text-red-700">{form.errors.rating}</p>}
                    </fieldset>

                    <div className="mt-6">
                        <label htmlFor="review-body" className="text-sm font-bold text-ink-950">Review <span className="font-normal text-ink-950/50">(optional)</span></label>
                        <textarea id="review-body" value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} rows="7" maxLength="5000" placeholder="What stayed with you after reading?" className="mt-2 w-full resize-y border border-ink-950/20 bg-white/55 px-4 py-3 leading-7 outline-none focus:border-brand-coral" />
                        {form.errors.body && <p className="mt-2 text-sm font-semibold text-red-700">{form.errors.body}</p>}
                    </div>

                    <label className="mt-5 flex items-start gap-3 text-sm leading-6 text-ink-950/70">
                        <input type="checkbox" checked={form.data.contains_spoiler} onChange={(event) => form.setData('contains_spoiler', event.target.checked)} className="mt-1 size-4 accent-brand-coral" />
                        This review contains spoilers. Hide its text until another reader chooses to reveal it.
                    </label>

                    <div className="mt-7 flex flex-wrap items-center justify-end gap-3 border-t border-ink-950/10 pt-5">
                        {viewer.current_review && <button type="button" onClick={remove} className="border border-red-700/30 px-5 py-3 font-bold text-red-800 transition hover:border-red-700 hover:bg-red-700 hover:text-white sm:mr-auto">Delete review</button>}
                        <button type="button" onClick={onClose} className="border border-ink-950/20 px-5 py-3 font-bold text-ink-950 transition hover:border-brand-coral">Cancel</button>
                        <button disabled={form.processing} className="bg-ink-950 px-6 py-3 font-bold text-brand-cream transition hover:bg-brand-coral disabled:opacity-50">{viewer.current_review ? 'Update review' : 'Publish review'}</button>
                    </div>
                </div>
            </form>
        </dialog>
    );
}
